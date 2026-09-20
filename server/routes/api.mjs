import express from 'express';
import crypto from 'node:crypto';
import multer from 'multer';
import bcrypt from 'bcryptjs';
import PDFDocument from 'pdfkit';
import {
  allowRoles,
  allowPermissions,
  authRequired,
  clearTokenCookie,
  isStaff,
  optionalAuth,
  publicUser,
  roleSlug,
  setTokenCookie,
  signUser
} from '../lib/auth.mjs';
import {
  allowedOrderFilter,
  asBool,
  asNumber,
  assetUrl,
  enrichServices,
  isoNow,
  money,
  nextOrderNo,
  parseJson,
  resolveOrder,
  safeUser,
  servicePayload,
  settingsMap,
  slugify,
  statusLabel,
  timeAgo
} from '../lib/legacy.mjs';
import { saveUpload, streamStoredFile } from '../lib/file-store.mjs';
import { rateLimit } from '../lib/rate-limit.mjs';
import { runWorkflowReminders } from '../lib/workflow.mjs';
import { recordAudit } from '../lib/audit.mjs';
import { permissionCatalogRows } from '../lib/rbac.mjs';

const router = express.Router();
const otpStore = new Map();
const OTP_TTL_MS = 10 * 60 * 1000;
const MAX_OTP_ATTEMPTS = 5;
const upload = multer({
  storage: multer.memoryStorage(),
  fileFilter: (_request, file, callback) => {
    const mime = String(file.mimetype || '').toLowerCase();
    const allowed = mime === 'application/pdf'
      || mime.startsWith('image/')
      || mime === 'text/plain'
      || mime === 'application/zip'
      || mime.includes('word')
      || mime.includes('excel')
      || mime.includes('spreadsheet');
    callback(allowed ? null : new Error('This file type is not supported.'), allowed);
  },
  limits: { fileSize: Number(process.env.UPLOAD_MAX_MB || 15) * 1024 * 1024, files: 1 }
});
const paymentQrUpload = multer({
  storage: multer.memoryStorage(),
  fileFilter: (_request, file, callback) => callback(null, String(file.mimetype || '').startsWith('image/')),
  limits: { fileSize: 5 * 1024 * 1024, files: 1 }
});
const paymentProofUpload = multer({
  storage: multer.memoryStorage(),
  fileFilter: (_request, file, callback) => {
    const mime = String(file.mimetype || '').toLowerCase();
    callback(mime === 'application/pdf' || mime.startsWith('image/') ? null : new Error('Payment proof must be a PDF or image.'), mime === 'application/pdf' || mime.startsWith('image/'));
  },
  limits: { fileSize: Number(process.env.UPLOAD_MAX_MB || 15) * 1024 * 1024, files: 1 }
});

const asyncRoute = (handler) => (request, response, next) =>
  Promise.resolve(handler(request, response, next)).catch(next);

const numericId = (value) => {
  const number = Number(value);
  return Number.isFinite(number) ? number : null;
};

const roleGuard = (store, ...roles) => [authRequired(store), allowRoles(...roles)];
const permissionGuard = (store, ...permissions) => [authRequired(store), allowPermissions(...permissions)];
const loginRateLimit = rateLimit({ windowMs: 15 * 60 * 1000, max: 25, message: 'Too many sign-in attempts. Please try again later.' });
const otpRateLimit = rateLimit({ windowMs: 10 * 60 * 1000, max: 5, message: 'Too many OTP requests. Please try again later.' });
const publicFormRateLimit = rateLimit({ windowMs: 10 * 60 * 1000, max: 20, message: 'Too many submissions. Please try again later.' });

async function findUserByEmail(store, email) {
  const value = String(email || '').trim().toLowerCase();
  if (!value) return null;
  const escaped = value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  return store.findOne('users', { email: { $regex: `^${escaped}$`, $options: 'i' } });
}

function normalizePhone(value) {
  const digits = String(value || '').replace(/\D/g, '');
  if (digits.length === 12 && digits.startsWith('91')) return digits.slice(2);
  if (digits.length === 11 && digits.startsWith('0')) return digits.slice(1);
  if (digits.length === 10) return digits;
  return null;
}

function currentFinancialYear() {
  const now = new Date();
  const year = now.getUTCMonth() >= 3 ? now.getUTCFullYear() : now.getUTCFullYear() - 1;
  return `${year}-${String(year + 1).slice(-2)}`;
}

async function findUserByPhone(store, phone) {
  const normalized = normalizePhone(phone);
  if (!normalized) return null;
  const users = await store.find('users', {});
  return users.find((user) => normalizePhone(user.phone) === normalized) || null;
}

function compatibleBcryptHash(value) {
  return String(value || '').replace(/^\$2y\$/, '$2b$');
}

function hashOtp(code) {
  return crypto
    .createHash('sha256')
    .update(`${process.env.JWT_SECRET || 'taxsaathi-development-secret'}:${code}`)
    .digest('hex');
}

function issueOtp({ purpose, phone, userId = null }) {
  const code = String(crypto.randomInt(100000, 1000000));
  const requestId = `${Date.now()}-${crypto.randomBytes(10).toString('hex')}`;
  otpStore.set(requestId, {
    purpose,
    phone,
    userId: userId ? Number(userId) : null,
    code_hash: hashOtp(code),
    attempts: 0,
    expires_at: Date.now() + OTP_TTL_MS
  });
  return { code, requestId };
}

function consumeOtp({ requestId, purpose, phone, code, userId = null }) {
  const pending = otpStore.get(String(requestId || ''));
  if (!pending || pending.purpose !== purpose || pending.expires_at < Date.now()) return null;
  if (pending.phone !== phone || (userId && Number(pending.userId) !== Number(userId))) return null;
  pending.attempts += 1;
  if (pending.attempts > MAX_OTP_ATTEMPTS) {
    otpStore.delete(String(requestId));
    return null;
  }
  if (pending.code_hash !== hashOtp(String(code || '').trim())) return null;
  otpStore.delete(String(requestId));
  return pending;
}

async function sendFast2SmsOtp(phone, code) {
  const authorization = String(process.env.FAST2SMS_API_KEY || '').trim();
  if (!authorization || authorization.includes('*')) {
    throw new Error('FAST2SMS_API_KEY is not configured.');
  }

  const providerResponse = await fetch(process.env.FAST2SMS_URL || 'https://www.fast2sms.com/dev/bulkV2', {
    method: 'POST',
    headers: {
      authorization,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      route: process.env.FAST2SMS_ROUTE || 'dlt',
      sender_id: process.env.FAST2SMS_SENDER_ID || 'SECAST',
      message: process.env.FAST2SMS_MESSAGE_ID || '204251',
      variables_values: code,
      schedule_time: process.env.FAST2SMS_SCHEDULE_TIME || '',
      numbers: phone
    })
  });

  let providerPayload = {};
  try {
    providerPayload = await providerResponse.json();
  } catch {
    providerPayload = {};
  }

  if (!providerResponse.ok || providerPayload.return === false) {
    console.error(`Fast2SMS OTP request failed with HTTP ${providerResponse.status}.`);
    throw new Error('Fast2SMS rejected the OTP request.');
  }

  return providerPayload;
}

async function currentUserResponse(store, user) {
  if (!user) return null;
  const assignments = await store.find('user_roles', { user_id: Number(user.id) });
  const roleIds = [...new Set([user.role_id, ...assignments.map((item) => item.role_id)].map(Number).filter(Number.isFinite))];
  const roles = [];
  for (const roleId of roleIds) {
    const role = await store.findOne('roles', { id: roleId });
    if (role) roles.push(role);
  }
  const role = roles.find((item) => Number(item.id) === Number(user.role_id)) || roles[0] || null;
  return publicUser(user, role, roles);
}

function responseError(response, message, status = 400) {
  return response.status(status).json({ ok: false, message });
}

function serializeNotification(row) {
  return {
    ...row,
    is_read: asBool(row.is_read),
    severity: row.severity || 'info',
    time_ago: timeAgo(row.created_at),
    order_no: row.order_no || null
  };
}

function isGlobalRole(auth) {
  return ['admin', 'manager'].includes(roleSlug(auth));
}

async function getCommandCenterMetrics(store) {
  const [totalUsers, activeUsers, corporateAccounts, executives, totalCases, completedCases, cancelledCases, rejectedCases, overdueCases, slaRiskCases, pendingDocuments, pendingApprovals, pendingPayments, outstandingInvoices, activeSubscriptions, expiringSubscriptions, securityAlerts, auditEvents, backupRuns] = await Promise.all([
    store.count('users', {}),
    store.count('users', { is_active: 1 }),
    store.count('clients', { client_type: { $in: ['corporate', 'company', 'business'] } }),
    store.count('users', { role_id: 3 }),
    store.count('orders', {}),
    store.count('orders', { status: 'completed' }),
    store.count('orders', { status: 'cancelled' }),
    store.count('orders', { status: 'rejected' }),
    store.count('orders', { due_date: { $lt: new Date().toISOString() }, status: { $ne: 'completed' } }),
    store.count('orders', { sla_status: { $in: ['at_risk', 'risk', 'overdue'] } }),
    store.count('order_documents', { document_status: { $in: ['submitted', 'pending', 'processing', 'requires_review'] } }),
    store.count('approvals', { status: 'pending' }),
    store.count('orders', { payment_status: { $in: ['pending', 'unpaid', 'partial', 'partially_paid'] } }),
    store.count('invoices', { $or: [{ status: { $in: ['unpaid', 'pending', 'overdue', 'partially_paid'] } }, { payment_status: { $in: ['unpaid', 'pending', 'overdue', 'partially_paid'] } }] }),
    store.count('subscriptions', { status: { $in: ['active', 'trialing'] } }),
    store.count('subscriptions', { status: { $in: ['active', 'trialing'] }, expires_at: { $lt: new Date(Date.now() + (30 * 24 * 60 * 60 * 1000)).toISOString() } }),
    store.count('notifications', { severity: { $in: ['warning', 'danger', 'critical'] } }),
    store.count('audit_logs', {}),
    store.find('backup_runs', {}, { sort: { created_at: -1 }, limit: 1 })
  ]);

  return {
    total_users: totalUsers,
    active_users: activeUsers,
    corporate_accounts: corporateAccounts,
    active_executives: executives,
    active_cases: Math.max(0, totalCases - completedCases - cancelledCases - rejectedCases),
    completed_cases: completedCases,
    overdue_cases: overdueCases,
    sla_risk_cases: slaRiskCases,
    documents_awaiting_verification: pendingDocuments,
    approvals_pending: pendingApprovals,
    payments_pending: pendingPayments,
    outstanding_invoices: outstandingInvoices,
    active_subscriptions: activeSubscriptions,
    subscriptions_expiring: expiringSubscriptions,
    security_alerts: securityAlerts,
    audit_events: auditEvents,
    backup: backupRuns[0] || null
  };
}

function partnerOwnerFilter(auth) {
  const user = auth?.user || {};
  const ids = [...new Set([user.id, user.partner_id].map(Number).filter((value) => Number.isFinite(value) && value > 0))];
  return { partner_id: { $in: ids.length ? ids : [0] } };
}

const paymentSettingKeys = [
  'payment_qr_code',
  'payment_upi_id',
  'payment_account_name',
  'payment_bank_name',
  'payment_account_number',
  'payment_ifsc_code',
  'payment_branch',
  'payment_instructions'
];

function publicSettings(settings) {
  return Object.fromEntries(Object.entries(settings || {}).filter(([key]) => {
    if (/token|secret|password|api|smtp|auth|private/i.test(key)) return false;
    return /^(site_|hero_|why_|process_|calc_|contact_address$)/i.test(key);
  }));
}

function paymentDetails(settings) {
  return {
    qr_code: assetUrl(settings.payment_qr_code || ''),
    upi_id: String(settings.payment_upi_id || settings.site_upi || 'taxsaathi@upi').trim(),
    account_name: String(settings.payment_account_name || '').trim(),
    bank_name: String(settings.payment_bank_name || '').trim(),
    account_number: String(settings.payment_account_number || '').trim(),
    ifsc_code: String(settings.payment_ifsc_code || '').trim(),
    branch: String(settings.payment_branch || '').trim(),
    instructions: String(settings.payment_instructions || '').trim(),
    razorpay_enabled: Boolean(process.env.RAZORPAY_KEY_ID && process.env.RAZORPAY_KEY_SECRET),
    razorpay_key_id: String(process.env.RAZORPAY_KEY_ID || '').trim()
  };
}

async function razorpayRequest(endpoint, options = {}) {
  const keyId = String(process.env.RAZORPAY_KEY_ID || '').trim();
  const keySecret = String(process.env.RAZORPAY_KEY_SECRET || '').trim();
  if (!keyId || !keySecret) throw new Error('Razorpay is not configured.');
  const response = await fetch(`https://api.razorpay.com/v1${endpoint}`, {
    ...options,
    headers: {
      Authorization: `Basic ${Buffer.from(`${keyId}:${keySecret}`).toString('base64')}`,
      'Content-Type': 'application/json',
      ...(options.headers || {})
    }
  });
  const payload = await response.json().catch(() => ({}));
  if (!response.ok) throw new Error(payload.error?.description || 'Razorpay request failed.');
  return payload;
}

function verifyRazorpaySignature(orderId, paymentId, signature) {
  const expected = crypto.createHmac('sha256', String(process.env.RAZORPAY_KEY_SECRET || '')).update(`${orderId}|${paymentId}`).digest('hex');
  const received = Buffer.from(String(signature || ''));
  const expectedBuffer = Buffer.from(expected);
  return received.length === expectedBuffer.length && crypto.timingSafeEqual(expectedBuffer, received);
}

function couponDate(value) {
  return value ? String(value).slice(0, 10) : '';
}

function completedPayment(value) {
  return ['paid', 'verified', 'approved'].includes(String(value || '').toLowerCase());
}

function normalizeCoupon(row, source = 'coupons') {
  if (!row) return null;
  return {
    ...row,
    id: Number(row.id),
    source,
    code: String(row.code || '').trim().toUpperCase(),
    discount_type: String(row.discount_type || 'percent').toLowerCase() === 'percentage' ? 'percent' : String(row.discount_type || 'percent').toLowerCase(),
    discount_value: asNumber(row.discount_value),
    max_discount_amount: asNumber(row.max_discount_amount),
    min_order_amount: asNumber(row.min_order_amount),
    usage_limit: asNumber(row.usage_limit),
    per_user_limit: asNumber(row.per_user_limit || row.partner_usage_limit || 1, 1),
    service_id: row.service_id ? Number(row.service_id) : null,
    is_active: asBool(row.is_active)
  };
}

async function findCoupon(store, code) {
  const normalized = String(code || '').trim().toUpperCase();
  if (!normalized) return null;
  const coupon = await store.findOne('coupons', { code: normalized });
  if (coupon) return normalizeCoupon(coupon, 'coupons');
  const legacy = await store.findOne('partner_coupons', { code: normalized });
  return legacy ? normalizeCoupon(legacy, 'partner_coupons') : null;
}

function couponActor(auth) {
  const role = roleSlug(auth);
  if (role === 'partner' || role === 'partners') {
    return { role: 'partner', user_id: Number(auth.user.id), partner_id: Number(auth.user.partner_id || auth.user.id), client_id: null };
  }
  return { role: 'client', user_id: Number(auth.user.id), partner_id: null, client_id: Number(auth.user.client_id || 0) };
}

async function couponUsage(store, coupon, actor) {
  const current = await store.count('coupon_redemptions', { coupon_id: Number(coupon.id) });
  const legacy = coupon.source === 'partner_coupons'
    ? await store.count('partner_coupon_redemptions', { coupon_id: Number(coupon.id) })
    : 0;
  const actorFilter = actor.role === 'partner'
    ? { coupon_id: Number(coupon.id), partner_id: Number(actor.partner_id) }
    : { coupon_id: Number(coupon.id), user_id: Number(actor.user_id) };
  const actorCurrent = await store.count('coupon_redemptions', actorFilter);
  const actorLegacy = actor.role === 'partner' && coupon.source === 'partner_coupons'
    ? await store.count('partner_coupon_redemptions', { coupon_id: Number(coupon.id), partner_id: Number(actor.partner_id) })
    : 0;
  return { total: current + legacy, actor: actorCurrent + actorLegacy };
}

async function validateCoupon(store, { code, serviceId, amount, auth }) {
  const coupon = await findCoupon(store, code);
  if (!coupon || !coupon.is_active) return { error: 'This coupon is invalid or inactive.' };
  const today = new Date().toISOString().slice(0, 10);
  if (couponDate(coupon.starts_at) && couponDate(coupon.starts_at) > today) return { error: 'This coupon is not active yet.' };
  if (couponDate(coupon.expires_at) && couponDate(coupon.expires_at) < today) return { error: 'This coupon has expired.' };
  if (coupon.service_id && Number(coupon.service_id) !== Number(serviceId)) return { error: 'This coupon is not valid for this service.' };
  if (asNumber(amount) < coupon.min_order_amount) return { error: `This coupon requires a minimum order of ₹${coupon.min_order_amount.toLocaleString('en-IN')}.` };
  const actor = couponActor(auth);
  const usage = await couponUsage(store, coupon, actor);
  if (coupon.usage_limit > 0 && usage.total >= coupon.usage_limit) return { error: 'This coupon has reached its usage limit.' };
  if (coupon.per_user_limit > 0 && usage.actor >= coupon.per_user_limit) return { error: 'You have already used this coupon.' };
  let discount = coupon.discount_type === 'fixed'
    ? coupon.discount_value
    : (asNumber(amount) * coupon.discount_value) / 100;
  if (coupon.discount_type !== 'fixed' && coupon.max_discount_amount > 0) discount = Math.min(discount, coupon.max_discount_amount);
  discount = Math.max(0, Math.min(asNumber(amount), discount));
  return { coupon, actor, discount, payable: Math.max(0, asNumber(amount) - discount) };
}

async function resolvePartnerOrder(store, order) {
  if (!order) return null;
  const [service, documents] = await Promise.all([
    store.findOne('services', { id: Number(order.service_id) }),
    store.find('partner_order_documents', { partner_order_id: Number(order.id) }, { sort: { id: 1 } })
  ]);
  return {
    ...order,
    filing_fee: asNumber(order.filing_fee),
    coupon_discount_amount: asNumber(order.coupon_discount_amount),
    payable_amount: asNumber(order.payable_amount || order.filing_fee),
    service: service ? { id: service.id, title: service.title, slug: service.slug } : null,
    documents
  };
}

function isPartnerRole(role) {
  const slug = String(role?.slug || '').toLowerCase();
  const name = String(role?.name || '').toLowerCase();
  return ['partner', 'partners'].includes(slug) || ['partner', 'partners'].includes(name);
}

function partnerIdentityKeys(user) {
  return new Set([user?.id, user?.partner_id]
    .map(Number)
    .filter((value) => Number.isFinite(value) && value > 0));
}

function partnerProfileKeys(user) {
  return new Set([user?.id, user?.partner_id, user?.client_id]
    .map(Number)
    .filter((value) => Number.isFinite(value) && value > 0));
}

async function adminPartnerRecords(store) {
  const [users, roles, userRoles, profiles, partnerOrders, orders, services, clients] = await Promise.all([
    store.find('users', {}, { sort: { id: -1 } }),
    store.find('roles', {}),
    store.find('user_roles', {}),
    store.find('partner_profiles', {}),
    store.find('partner_orders', {}, { sort: { id: -1 } }),
    store.find('orders', {}, { sort: { id: -1 } }),
    store.find('services', {}),
    store.find('clients', {})
  ]);
  const rolesById = new Map(roles.map((role) => [Number(role.id), role]));
  const partnerRoleIds = new Set(roles.filter((role) => isPartnerRole(role)).map((role) => Number(role.id)));
  const partnerRoleByUser = new Map(userRoles
    .filter((row) => partnerRoleIds.has(Number(row.role_id)))
    .map((row) => [Number(row.user_id), rolesById.get(Number(row.role_id))]));
  const servicesById = new Map(services.map((service) => [Number(service.id), service]));
  const clientsById = new Map(clients.map((client) => [Number(client.id), client]));
  const partnerUsers = users.filter((user) => partnerRoleIds.has(Number(user.role_id)) || partnerRoleByUser.has(Number(user.id)));

  return partnerUsers.map((user) => {
    const keys = partnerIdentityKeys(user);
    const profileKeys = partnerProfileKeys(user);
    const profile = profiles.find((row) => profileKeys.has(Number(row.partner_id)) || profileKeys.has(Number(row.user_id))) || null;
    const ownPartnerOrders = partnerOrders
      .filter((row) => keys.has(Number(row.partner_id)))
      .map((row) => ({
        ...row,
        source: 'partner_orders',
        service: servicesById.get(Number(row.service_id)) || null
      }));
    const ownOrders = orders
      .filter((row) => keys.has(Number(row.partner_id)))
      .map((row) => ({
        ...row,
        source: 'orders',
        service: servicesById.get(Number(row.service_id)) || null,
        client: clientsById.get(Number(row.client_id)) || null
      }));
    const partnerOrderList = [...ownPartnerOrders, ...ownOrders].sort((left, right) => String(right.created_at || right.id).localeCompare(String(left.created_at || left.id), undefined, { numeric: true }));
    const value = partnerOrderList.reduce((total, row) => total + asNumber(row.payable_amount || row.filing_fee || row.fee_amount), 0);
    const approved = partnerOrderList.filter((row) => ['approved', 'completed'].includes(String(row.order_status || row.status || '').toLowerCase())).length;
    const paid = partnerOrderList.filter((row) => ['paid', 'approved', 'verified'].includes(String(row.payment_status || '').toLowerCase())).length;
    const role = partnerRoleByUser.get(Number(user.id)) || rolesById.get(Number(user.role_id)) || null;
    return {
      id: Number(user.id),
      user: { ...safeUser(user), role: role ? { id: role.id, name: role.name, slug: role.slug } : null },
      profile,
      orders: partnerOrderList,
      stats: { total_orders: partnerOrderList.length, approved, paid, value }
    };
  });
}

async function notificationRows(store, userId, limit = 40, auth = null) {
  const filter = isGlobalRole(auth)
    ? { $or: [{ user_id: Number(userId) }, { user_id: null }] }
    : { user_id: Number(userId) };
  const rows = await store.find('notifications', filter, { sort: { id: -1 }, limit });
  const globalUids = rows.filter((row) => !row.user_id && row.uid).map((row) => row.uid);
  const reads = globalUids.length
    ? await store.find('notification_user_reads', { user_id: Number(userId), notification_uid: { $in: globalUids }, is_read: 1 })
    : [];
  const readUids = new Set(reads.map((row) => String(row.notification_uid)));
  return rows.map((row) => serializeNotification({ ...row, is_read: row.user_id ? row.is_read : (readUids.has(String(row.uid)) ? 1 : row.is_read) }));
}

async function notify(store, { userId = null, title, message, severity = 'info', url = '', kind = 'system', orderId = null }) {
  return store.insert('notifications', {
    uid: `mern-${Date.now()}-${Math.random().toString(16).slice(2)}`,
    user_id: userId,
    title,
    message,
    body: message,
    kind,
    status_label: statusLabel(severity),
    severity,
    url,
    data_json: JSON.stringify({ order_id: orderId }),
    payload: JSON.stringify({ order_id: orderId }),
    is_read: 0,
    read_at: null,
    created_at: isoNow(),
    updated_at: isoNow()
  });
}

async function scopedOrder(store, request, id) {
  return store.findOne('orders', {
    id: Number(id),
    ...allowedOrderFilter(request.auth)
  });
}

async function getDashboard(store, request) {
  const role = roleSlug(request.auth);
  const filter = allowedOrderFilter(request.auth);
  const orders = await store.find('orders', filter, { sort: { id: -1 } });
  const allServices = await store.find('services', {}, { sort: { sort_order: 1, id: 1 } });
  const unread = (await notificationRows(store, request.auth.user.id, 1000, request.auth)).filter((item) => !item.is_read).length;
  const statusSummary = Object.entries(
    orders.reduce((summary, order) => {
      const key = order.status || 'submitted';
      summary[key] = (summary[key] || 0) + 1;
      return summary;
    }, {})
  ).map(([status, total]) => ({ status, total, label: statusLabel(status) }));
  const revenue = orders.reduce((total, order) => total + asNumber(order.payable_amount || order.fee_amount), 0);
  const paid = orders
    .filter((order) => ['paid', 'verified', 'approved'].includes(String(order.payment_status).toLowerCase()))
    .reduce((total, order) => total + asNumber(order.payable_amount || order.fee_amount), 0);

  const commonStats = [
    { label: 'Total orders', value: orders.length, icon: 'orders', tone: 'indigo' },
    { label: 'Revenue value', value: money(revenue), icon: 'revenue', tone: 'cyan' },
    { label: 'Paid / approved', value: money(paid), icon: 'paid', tone: 'emerald' },
    { label: 'Unread alerts', value: unread, icon: 'notifications', tone: 'amber' },
    { label: 'Active services', value: allServices.filter((service) => asBool(service.is_active)).length, icon: 'services', tone: 'violet' },
    { label: 'Completed', value: orders.filter((order) => order.status === 'completed').length, icon: 'completed', tone: 'slate' }
  ];
  const scopedStats = role === 'client'
    ? [
        { label: 'My orders', value: orders.length, icon: 'orders', tone: 'indigo' },
        { label: 'My payable value', value: money(revenue), icon: 'revenue', tone: 'cyan' },
        { label: 'My paid value', value: money(paid), icon: 'paid', tone: 'emerald' },
        { label: 'Unread alerts', value: unread, icon: 'notifications', tone: 'amber' },
        { label: 'Available services', value: allServices.filter((service) => asBool(service.is_active)).length, icon: 'services', tone: 'violet' },
        { label: 'Completed', value: orders.filter((order) => order.status === 'completed').length, icon: 'completed', tone: 'slate' }
      ]
    : role === 'executive'
      ? [
          { label: 'Assigned cases', value: orders.length, icon: 'orders', tone: 'indigo' },
          { label: 'Assigned value', value: money(revenue), icon: 'revenue', tone: 'cyan' },
          { label: 'Paid / approved', value: money(paid), icon: 'paid', tone: 'emerald' },
          { label: 'Unread alerts', value: unread, icon: 'notifications', tone: 'amber' },
          { label: 'Assigned clients', value: new Set(orders.map((order) => Number(order.client_id)).filter(Boolean)).size, icon: 'users', tone: 'violet' },
          { label: 'Completed', value: orders.filter((order) => order.status === 'completed').length, icon: 'completed', tone: 'slate' }
        ]
      : commonStats;

  return {
    ok: true,
    role,
    user: await currentUserResponse(store, request.auth.user),
    stats: scopedStats,
    statusSummary,
    recentOrders: await Promise.all(orders.slice(0, 8).map((order) => resolveOrder(store, order))),
    notifications: (await notificationRows(store, request.auth.user.id, 8, request.auth)).slice(0, 8),
    adminMetrics: isGlobalRole(request.auth) ? await getCommandCenterMetrics(store) : null,
    dataMode: store.mode
  };
}

router.get('/health', asyncRoute(async (request, response) => {
  response.json({
    ok: true,
    service: 'taxsaathi-mern',
    mode: request.app.locals.store.mode,
    host: process.env.HOST || '0.0.0.0',
    port: Number(process.env.PORT || 4001),
    public_url: request.app.locals.publicUrl || process.env.PUBLIC_URL || `http://127.0.0.1:${process.env.PORT || 4001}`,
    now: new Date().toISOString()
  });
}));

router.get('/meta', optionalAuth((request) => request.app.locals.store), asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const counts = isGlobalRole(request.auth)
    ? await store.stats()
    : request.auth
      ? {
          own_orders: await store.count('orders', allowedOrderFilter(request.auth)),
          own_notifications: await store.count('notifications', { user_id: Number(request.auth.user.id) })
        }
      : {};
  const settings = await settingsMap(store);
  response.json({
    ok: true,
    app: 'TaxSaathi',
    public_url: request.app.locals.publicUrl || process.env.PUBLIC_URL || `http://127.0.0.1:${process.env.PORT || 4001}`,
    mode: store.mode,
    counts,
    settings: {
      site_name: settings.site_name || 'TaxSaathi',
      site_logo: assetUrl(settings.site_logo || '/uploads/site/site_logo_20260605161136_36ef8081dd8a.png'),
      site_logo_alt: settings.site_logo_alt || 'TaxSaathi Logo',
      site_phone: settings.site_phone || '',
      site_whatsapp: settings.site_whatsapp || settings.site_phone || '',
      site_email: settings.site_email || '',
      contact_address: settings.contact_address || ''
    }
  });
}));

router.get('/payment-details', asyncRoute(async (request, response) => {
  const settings = await settingsMap(request.app.locals.store);
  response.json({ ok: true, payment: paymentDetails(settings) });
}));

router.get('/files/public/:id', asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const settings = await settingsMap(store);
  const expected = `/api/files/public/${String(request.params.id)}`;
  if (String(settings.payment_qr_code || '') !== expected) return responseError(response, 'Public file not found.', 404);
  const sent = await streamStoredFile(store, `gridfs:${request.params.id}`, response, { localFolder: 'payment', downloadName: 'taxsaathi-payment-qr.png' });
  if (!sent) return responseError(response, 'Public file not found.', 404);
}));

router.get('/public/home', asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const [settings, categories, rows, testimonials, faqs] = await Promise.all([
    settingsMap(store),
    store.find('service_categories', { is_active: 1 }, { sort: { sort_order: 1, id: 1 } }),
    store.find('services', { is_active: 1 }, { sort: { sort_order: 1, id: 1 } }),
    store.find('testimonials', { is_active: 1 }, { sort: { id: 1 } }),
    store.find('faqs', { is_active: 1 }, { sort: { sort_order: 1, id: 1 } })
  ]);
  const services = await enrichServices(store, rows);
  response.json({
    ok: true,
    settings: publicSettings(settings),
    categories: categories.map((category) => ({ ...category, image_url: assetUrl(category.image) })),
    featured: services.filter((service) => service.is_featured).slice(0, 8),
    services: services.slice(0, 12),
    testimonials,
    faqs,
    counts: {
      services: services.length,
      categories: categories.length,
      testimonials: testimonials.length,
      faqs: faqs.length
    }
  });
}));

router.get('/public/categories', asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const [categories, services] = await Promise.all([
    store.find('service_categories', { is_active: 1 }, { sort: { sort_order: 1, id: 1 } }),
    store.find('services', { is_active: 1 }, { sort: { sort_order: 1, id: 1 } })
  ]);
  const serviceCounts = services.reduce((counts, service) => {
    const categoryId = Number(service.service_category_id);
    counts.set(categoryId, (counts.get(categoryId) || 0) + 1);
    return counts;
  }, new Map());
  response.json({
    ok: true,
    categories: categories.map((category) => ({
      ...category,
      image_url: assetUrl(category.image),
      service_count: serviceCounts.get(Number(category.id)) || 0
    }))
  });
}));

router.get('/public/services', asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const search = String(request.query.search || '').trim().toLowerCase();
  const category = String(request.query.category || '').trim().toLowerCase();
  const featuredOnly = String(request.query.featured || '') === '1';
  const rows = await store.find('services', {}, { sort: { sort_order: 1, id: 1 } });
  const categories = await store.find('service_categories', {}, { sort: { sort_order: 1, id: 1 } });
  const categoryById = new Map(categories.map((item) => [Number(item.id), item]));
  let services = await enrichServices(store, rows);
  services = services.filter((service) => {
    if (!asBool(service.is_active)) return false;
    if (featuredOnly && !service.is_featured) return false;
    if (category && ![String(service.category?.slug).toLowerCase(), String(service.service_category_id)].includes(category)) return false;
    if (!search) return true;
    return [service.title, service.excerpt, service.description, service.category?.title]
      .some((value) => String(value || '').toLowerCase().includes(search));
  });
  const page = Math.max(1, Number(request.query.page || 1));
  const limit = Math.min(100, Math.max(1, Number(request.query.limit || 24)));
  const total = services.length;
  services = services.slice((page - 1) * limit, page * limit);
  response.json({ ok: true, services, categories, pagination: { page, limit, total, pages: Math.ceil(total / limit) } });
}));

router.get('/public/services/:slug', asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const identifier = request.params.slug;
  const service = await store.findOne('services', /^\d+$/.test(identifier) ? { id: Number(identifier) } : { slug: identifier });
  if (!service) return responseError(response, 'Service not found.', 404);
  response.json({ ok: true, service: await servicePayload(store, service) });
}));

router.get('/public/calculators', asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const [rules, settings] = await Promise.all([
    store.find('tax_rules', {}, { sort: { income_from: 1, id: 1 } }),
    settingsMap(store)
  ]);
  response.json({ ok: true, rules, settings: publicSettings(settings) });
}));

router.post('/public/calculators', asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const income = Math.max(0, asNumber(request.body.income));
  const regime = ['new', 'old'].includes(String(request.body.regime || 'new').toLowerCase()) ? String(request.body.regime || 'new').toLowerCase() : 'new';
  const settings = await settingsMap(store);
  const requestedYear = String(request.body.financial_year || '').trim();
  const allRules = await store.find('tax_rules', { regime }, { sort: { income_from: 1, id: 1 } });
  const financialYears = [...new Set(allRules.map((rule) => String(rule.financial_year || '').trim()).filter(Boolean))];
  const financialYear = requestedYear && financialYears.includes(requestedYear) ? requestedYear : financialYears.at(-1) || '2025-26';
  const rules = allRules.filter((rule) => !rule.financial_year || String(rule.financial_year) === financialYear);
  const standardDeduction = Math.max(0, asNumber(settings[`calc_standard_deduction_${regime}`]));
  const taxableIncome = Math.max(0, income - standardDeduction);
  let tax = 0;
  let remaining = taxableIncome;
  for (const rule of rules) {
    const from = asNumber(rule.income_from);
    const to = rule.income_to === null || rule.income_to === '' ? taxableIncome : asNumber(rule.income_to, taxableIncome);
    const taxable = Math.max(0, Math.min(taxableIncome, to) - from);
    if (taxable > 0) tax += taxable * (asNumber(rule.rate_percent) / 100);
    remaining -= taxable;
    if (remaining <= 0) break;
  }
  const rebateThreshold = Math.max(0, asNumber(settings[`calc_rebate_threshold_${regime}`]));
  const rebateAmount = Math.max(0, asNumber(settings[`calc_rebate_amount_${regime}`]));
  const rebate = taxableIncome <= rebateThreshold ? Math.min(tax, rebateAmount) : 0;
  const netTax = Math.max(0, tax - rebate);
  const cessPercent = Math.max(0, asNumber(settings.calc_cess_percent, 4));
  const cess = netTax * (cessPercent / 100);
  response.json({
    ok: true,
    income,
    regime,
    financial_year: financialYear,
    standard_deduction: Math.round(standardDeduction),
    taxable_income: Math.round(taxableIncome),
    rebate: Math.round(rebate),
    estimated_tax: Math.round(netTax),
    cess: Math.round(cess),
    total: Math.round(netTax + cess)
  });
}));

router.post('/public/contact', publicFormRateLimit, asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const name = String(request.body.name || '').trim();
  const email = String(request.body.email || '').trim();
  const message = String(request.body.message || '').trim();
  if (!name || !email || !message) return responseError(response, 'Name, email, and message are required.');
  const lead = await store.insert('leads', {
    name,
    phone: String(request.body.phone || '').trim(),
    email,
    service: String(request.body.service || '').trim(),
    message,
    source: 'website',
    status: 'new',
    assigned_to: null,
    created_at: isoNow(),
    updated_at: isoNow()
  });
  response.status(201).json({ ok: true, message: 'Thank you. Your enquiry has been received.', lead: safeUser(lead) });
}));

router.post('/public/applications', optionalAuth((request) => request.app.locals.store), publicFormRateLimit, asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const identifier = String(request.body.service || request.body.service_id || '').trim();
  const service = await store.findOne('services', /^\d+$/.test(identifier) ? { id: Number(identifier) } : { slug: identifier });
  if (!service) return responseError(response, 'Please choose a valid service.');
  const required = ['full_name', 'email', 'mobile'];
  if (required.some((field) => !String(request.body[field] || '').trim())) return responseError(response, 'Name, email, and mobile are required.');
  const application = await store.insert('service_applications', {
    service_id: Number(service.id),
    service_slug: service.slug,
    service_title: service.title,
    user_id: request.auth?.user?.id || null,
    full_name: String(request.body.full_name).trim(),
    email: String(request.body.email).trim(),
    mobile: String(request.body.mobile).trim(),
    state_name: String(request.body.state_name || '').trim(),
    status: 'new',
    is_read: 0,
    admin_email_sent: 0,
    user_email_sent: 0,
    ip_address: request.ip,
    user_agent: request.headers['user-agent'] || '',
    created_at: isoNow(),
    updated_at: isoNow(),
    payment_method: 'manual',
    payment_status: 'pending',
    payment_reference: null,
    paid_at: null
  });
  response.status(201).json({ ok: true, message: 'Application submitted. Our team will contact you shortly.', application });
}));

router.post('/auth/login', loginRateLimit, asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const email = String(request.body.email || '').trim().toLowerCase();
  const password = String(request.body.password || '');
  if (!email || !password) return responseError(response, 'Email and password are required.', 422);
  const user = await findUserByEmail(store, email);
  if (!user || Number(user.is_active) === 0) return responseError(response, 'Invalid credentials.', 401);
  if (!user.password_hash || !(await bcrypt.compare(password, compatibleBcryptHash(user.password_hash)))) return responseError(response, 'Invalid email or password.', 401);
  await store.update('users', { id: Number(user.id) }, { last_login_at: isoNow() });
  const token = signUser(user);
  setTokenCookie(response, token);
  await recordAudit(store, request, { actorUserId: user.id, action: 'auth.login', resourceType: 'user', resourceId: user.id });
  response.json({ ok: true, token, user: await currentUserResponse(store, user) });
}));

router.post('/auth/register/otp/request', otpRateLimit, asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const name = String(request.body.name || '').trim();
  const email = String(request.body.email || '').trim().toLowerCase();
  const phone = normalizePhone(request.body.phone || request.body.mobile);
  const password = String(request.body.password || '');
  if (!name || !email || !phone || password.length < 6) return responseError(response, 'Name, email, mobile number, and a six-character password are required.');
  if (await findUserByEmail(store, email)) return responseError(response, 'An account with this email already exists.', 409);
  if (await findUserByPhone(store, phone)) return responseError(response, 'An account with this mobile number already exists.', 409);

  const { code, requestId } = issueOtp({ purpose: 'register', phone });
  try {
    await sendFast2SmsOtp(phone, code);
  } catch (error) {
    otpStore.delete(requestId);
    const message = error.message.includes('not configured')
      ? 'Mobile OTP service is not configured.'
      : 'Unable to send the mobile OTP right now.';
    return responseError(response, message, 503);
  }

  response.json({ ok: true, request_id: requestId, message: 'OTP sent to your mobile number.' });
}));

router.post('/auth/register', asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const name = String(request.body.name || '').trim();
  const email = String(request.body.email || '').trim().toLowerCase();
  const phone = normalizePhone(request.body.phone || request.body.mobile);
  const password = String(request.body.password || '');
  const requestId = String(request.body.otp_request_id || '');
  const otp = String(request.body.otp || '').trim();
  if (!name || !email || !phone || password.length < 6 || !requestId || !otp) {
    return responseError(response, 'Name, email, mobile number, password, and OTP are required.');
  }

  const verified = consumeOtp({ requestId, purpose: 'register', phone, code: otp });
  if (!verified) return responseError(response, 'The mobile OTP is invalid or has expired.', 401);
  if (await findUserByEmail(store, email)) return responseError(response, 'An account with this email already exists.', 409);
  if (await findUserByPhone(store, phone)) return responseError(response, 'An account with this mobile number already exists.', 409);

  const client = await store.insert('clients', { name, phone, email, client_type: 'individual', status: 'active', created_at: isoNow(), updated_at: isoNow() });
  const user = await store.insert('users', {
    role_id: 5,
    client_id: client.id,
    name,
    phone,
    email,
    password_hash: await bcrypt.hash(password, 12),
    is_phone_verified: 1,
    is_email_verified: 1,
    is_active: 1,
    created_at: isoNow(),
    updated_at: isoNow()
  });
  const token = signUser(user);
  setTokenCookie(response, token);
  response.status(201).json({ ok: true, token, user: await currentUserResponse(store, user) });
}));

router.post('/auth/forgot-password/otp/request', otpRateLimit, asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const phone = normalizePhone(request.body.phone || request.body.mobile);
  if (!phone) return responseError(response, 'Enter a valid 10-digit mobile number.');
  const user = await findUserByPhone(store, phone);
  if (!user || Number(user.is_active) === 0) return responseError(response, 'No active account was found for this mobile number.', 404);

  const { code, requestId } = issueOtp({ purpose: 'forgot_password', phone, userId: user.id });
  try {
    await sendFast2SmsOtp(phone, code);
  } catch (error) {
    otpStore.delete(requestId);
    const message = error.message.includes('not configured')
      ? 'Mobile OTP service is not configured.'
      : 'Unable to send the mobile OTP right now.';
    return responseError(response, message, 503);
  }

  response.json({ ok: true, request_id: requestId, message: 'OTP sent to your registered mobile number.' });
}));

router.post('/auth/forgot-password/reset', asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const phone = normalizePhone(request.body.phone || request.body.mobile);
  const requestId = String(request.body.otp_request_id || '');
  const otp = String(request.body.otp || '').trim();
  const password = String(request.body.password || '');
  if (!phone || !requestId || !otp || password.length < 6) return responseError(response, 'Mobile number, OTP, and a six-character password are required.');

  const user = await findUserByPhone(store, phone);
  if (!user || Number(user.is_active) === 0) return responseError(response, 'Account not found.', 404);
  if (!consumeOtp({ requestId, purpose: 'forgot_password', phone, code: otp, userId: user.id })) {
    return responseError(response, 'The mobile OTP is invalid or has expired.', 401);
  }

  await store.update('users', { id: Number(user.id) }, {
    password_hash: await bcrypt.hash(password, 12),
    is_phone_verified: 1,
    updated_at: isoNow()
  });
  response.json({ ok: true, message: 'Password reset successfully. You can now sign in.' });
}));

router.get('/auth/me', authRequired((request) => request.app.locals.store), asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  response.json({ ok: true, user: await currentUserResponse(store, request.auth.user) });
}));

router.post('/auth/logout', asyncRoute(async (request, response) => {
  clearTokenCookie(response);
  response.json({ ok: true });
}));

router.get('/dashboard', authRequired((request) => request.app.locals.store), asyncRoute(async (request, response) => {
  response.json(await getDashboard(request.app.locals.store, request));
}));

router.get('/orders', authRequired((request) => request.app.locals.store), asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const filter = allowedOrderFilter(request.auth);
  const status = String(request.query.status || '').trim();
  if (status) filter.status = status;
  const rows = await store.find('orders', filter, { sort: { id: -1 } });
  response.json({ ok: true, orders: await Promise.all(rows.map((order) => resolveOrder(store, order))), total: rows.length });
}));

router.get('/orders/:id', authRequired((request) => request.app.locals.store), asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const order = await scopedOrder(store, request, request.params.id);
  if (!order) return responseError(response, 'Order not found or access is not allowed.', 404);
  response.json({ ok: true, order: await resolveOrder(store, order) });
}));

router.get('/orders/:orderId/documents/:documentId', authRequired((request) => request.app.locals.store), asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const order = await scopedOrder(store, request, request.params.orderId);
  if (!order) return responseError(response, 'Order not found or access is not allowed.', 404);
  const document = await store.findOne('order_documents', { id: Number(request.params.documentId), order_id: order.id });
  if (!document || (!asBool(document.is_client_visible) && !isStaff(request.auth))) return responseError(response, 'Document not found or access is not allowed.', 404);
  const reference = document.stored_name || document.file_id;
  const sent = await streamStoredFile(store, reference, response, { localFolder: 'documents', downloadName: document.original_name || document.label || `document-${document.id}` });
  if (!sent) return responseError(response, 'Document file not found.', 404);
}));

router.post('/orders', ...roleGuard(request => request.app.locals.store, 'admin', 'manager', 'client'), asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const serviceId = numericId(request.body.service_id || request.body.service);
  const service = serviceId ? await store.findOne('services', { id: serviceId }) : null;
  if (!service) return responseError(response, 'Choose a valid service.');
  const clientId = Number(request.auth.user.client_id || 0);
  if (!clientId) return responseError(response, 'A client profile is required before placing an order.');
  const fee = asNumber(service.filing_fee);
  const customerName = String(request.body.customer_name || request.auth.user.name || '').trim();
  const customerPan = String(request.body.pan_number || '').trim().toUpperCase();
  const customerMobile = String(request.body.customer_mobile || request.auth.user.phone || '').trim();
  const customerEmail = String(request.body.customer_email || request.auth.user.email || '').trim();
  const latestTaxRule = await store.find('tax_rules', {}, { sort: { id: -1 }, limit: 1 });
  const contactNotes = [
    'Customer contact details:',
    `Name as per PAN: ${customerName}`,
    `PAN Number: ${customerPan}`,
    `Mobile Number: ${customerMobile}`,
    `Email Address: ${customerEmail}`
  ].join('\n');
  const notes = [contactNotes, String(request.body.notes || '').trim()].filter(Boolean).join('\n\n');
  const order = await store.insert('orders', {
    order_no: await nextOrderNo(store),
    client_id: clientId,
    service_id: serviceId,
    financial_year: request.body.financial_year || latestTaxRule[0]?.financial_year || currentFinancialYear(),
    fee_amount: fee,
    gross_fee_amount: fee,
    payable_amount: fee,
    coupon_discount_amount: 0,
    status: 'submitted',
    payment_method: request.body.payment_method || 'manual',
    payment_status: 'pending',
    notes,
    customer_name: customerName,
    pan_number: customerPan,
    customer_mobile: customerMobile,
    customer_email: customerEmail,
    created_at: isoNow(),
    updated_at: isoNow()
  });
  await store.insert('activity_logs', { user_id: request.auth.user.id, order_id: order.id, action: 'order.created', description: `Created order ${order.order_no}`, meta_json: '{}', created_at: isoNow() });
  await notify(store, { userId: request.auth.user.id, title: 'Order submitted', message: `${order.order_no} is now in review.`, severity: 'success', url: `/client/orders/${order.id}`, orderId: order.id });
  response.status(201).json({ ok: true, order: await resolveOrder(store, order) });
}));

router.post('/orders/:id/status', ...roleGuard(request => request.app.locals.store, 'admin', 'manager', 'executive'), asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const order = await scopedOrder(store, request, request.params.id);
  if (!order) return responseError(response, 'Order not found or access is not allowed.', 404);
  const nextStatus = String(request.body.status || '').trim();
  const allowed = ['submitted', 'approved', 'work_in_progress', 'completed', 'rejected', 'cancelled'];
  if (!allowed.includes(nextStatus)) return responseError(response, 'Unsupported order status.');
  if (roleSlug(request.auth) === 'executive' && !['work_in_progress', 'completed'].includes(nextStatus)) return responseError(response, 'Executives can update workflow progress only.', 403);
  const updated = await store.update('orders', { id: Number(order.id) }, { status: nextStatus, updated_at: isoNow(), completed_at: nextStatus === 'completed' ? isoNow() : order.completed_at });
  await store.insert('activity_logs', { user_id: request.auth.user.id, order_id: order.id, action: 'order.status.updated', description: `Order status changed to ${nextStatus}`, meta_json: JSON.stringify({ status: nextStatus }), created_at: isoNow() });
  if (order.client_id) await notify(store, { userId: await store.findOne('users', { client_id: Number(order.client_id), role_id: 5 }).then((user) => user?.id || null), title: 'Order status updated', message: `${order.order_no} is now ${statusLabel(nextStatus)}.`, severity: nextStatus === 'completed' ? 'success' : 'info', url: `/client/orders/${order.id}`, orderId: order.id });
  response.json({ ok: true, order: await resolveOrder(store, updated) });
}));

router.post('/orders/:id/approve', ...roleGuard(request => request.app.locals.store, 'admin', 'manager'), asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const order = await store.findOne('orders', { id: Number(request.params.id) });
  if (!order) return responseError(response, 'Order not found.', 404);
  const updated = await store.update('orders', { id: Number(order.id) }, { status: 'approved', approved_by: request.auth.user.id, approved_at: isoNow(), updated_at: isoNow() });
  await store.insert('activity_logs', { user_id: request.auth.user.id, order_id: order.id, action: 'order.approved', description: `Approved ${order.order_no}`, meta_json: '{}', created_at: isoNow() });
  response.json({ ok: true, order: await resolveOrder(store, updated) });
}));

router.post('/orders/:id/assign', ...roleGuard(request => request.app.locals.store, 'admin', 'manager'), asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const order = await store.findOne('orders', { id: Number(request.params.id) });
  const assignee = await store.findOne('users', { id: Number(request.body.assigned_user_id) });
  if (!order || !assignee) return responseError(response, 'Order or assignee not found.', 404);
  const updated = await store.update('orders', { id: Number(order.id) }, { assigned_user_id: assignee.id, updated_at: isoNow() });
  await notify(store, { userId: assignee.id, title: 'Order assigned to you', message: `${order.order_no} has been assigned to your workflow.`, severity: 'info', url: `/orders/${order.id}`, orderId: order.id });
  response.json({ ok: true, order: await resolveOrder(store, updated) });
}));

router.post('/orders/:id/coupon', ...roleGuard(request => request.app.locals.store, 'admin', 'manager', 'client'), asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const order = await scopedOrder(store, request, request.params.id);
  if (!order) return responseError(response, 'Order not found or access is not allowed.', 404);
  if (['paid', 'verified'].includes(String(order.payment_status || '').toLowerCase())) return responseError(response, 'A coupon cannot be applied after payment has been verified.');
  const code = String(request.body.code || '').trim().toUpperCase();
  if (!code) return responseError(response, 'Enter a coupon code.');
  if (order.coupon_code) {
    if (String(order.coupon_code).toUpperCase() === code) return response.json({ ok: true, order: await resolveOrder(store, order), message: 'This coupon is already applied.' });
    return responseError(response, 'Only one coupon can be applied to an order.');
  }
  const result = await validateCoupon(store, { code, serviceId: order.service_id, amount: order.gross_fee_amount || order.fee_amount, auth: request.auth });
  if (result.error) return responseError(response, result.error, 422);
  const updated = await store.update('orders', { id: Number(order.id) }, {
    coupon_id: result.coupon.id,
    coupon_code: result.coupon.code,
    coupon_discount_type: result.coupon.discount_type,
    coupon_discount_value: result.coupon.discount_value,
    coupon_discount_amount: result.discount,
    payable_amount: result.payable,
    updated_at: isoNow()
  });
  await store.insert('coupon_redemptions', {
    coupon_id: result.coupon.id,
    coupon_code: result.coupon.code,
    user_id: result.actor.user_id,
    client_id: result.actor.client_id,
    partner_id: result.actor.partner_id,
    order_id: order.id,
    role: result.actor.role,
    discount_amount: result.discount,
    created_at: isoNow()
  });
  response.json({ ok: true, message: `${result.coupon.code} applied successfully.`, order: await resolveOrder(store, updated) });
}));

router.post('/orders/:id/payment-proof', authRequired((request) => request.app.locals.store), paymentProofUpload.single('file'), asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const order = await scopedOrder(store, request, request.params.id);
  if (!order) return responseError(response, 'Order not found or access is not allowed.', 404);
  if (!request.file) return responseError(response, 'Payment screenshot is required.');
  const transactionId = String(request.body.transaction_id || request.body.reference_no || '').trim();
  if (!transactionId) return responseError(response, 'Transaction ID is required.');
  const stored = await saveUpload(store, request.file, {
    folder: 'payment',
    metadata: { kind: 'payment-proof', order_id: order.id, uploaded_by: request.auth.user.id }
  });
  const amount = asNumber(order.payable_amount || order.fee_amount);
  const existing = await store.findOne('payments', { order_id: order.id, status: 'pending_review' }, { sort: { id: -1 } });
  const paymentData = {
    invoice_id: null,
    order_id: order.id,
    amount,
    method: String(request.body.payment_method || order.payment_method || 'manual'),
    reference_no: transactionId,
    notes: 'Payment proof submitted by client.',
    proof_file: stored.stored_name,
    proof_file_id: stored.file_id,
    proof_storage: stored.storage,
    submitted_by: request.auth.user.id,
    status: 'pending_review',
    created_at: isoNow(),
    updated_at: isoNow()
  };
  if (existing) await store.update('payments', { id: existing.id }, paymentData);
  else await store.insert('payments', paymentData);
  const updated = await store.update('orders', { id: order.id }, {
    payment_status: 'pending_review',
    payment_method: paymentData.method,
    payment_reference: transactionId,
    payment_proof: stored.stored_name,
    payment_proof_file_id: stored.file_id,
    payment_proof_storage: stored.storage,
    updated_at: isoNow()
  });
  await notify(store, { userId: null, title: 'Payment proof submitted', message: `${order.order_no} is ready for payment verification.`, severity: 'info', url: `/admin/orders/${order.id}`, orderId: order.id });
  response.status(201).json({ ok: true, message: 'Payment proof submitted for verification.', order: await resolveOrder(store, updated) });
}));

router.get('/orders/:id/payment-proof', authRequired((request) => request.app.locals.store), asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const order = await scopedOrder(store, request, request.params.id);
  if (!order) return responseError(response, 'Order not found or access is not allowed.', 404);
  const payment = await store.findOne('payments', { order_id: order.id }, { sort: { id: -1 } });
  const filename = order.payment_proof || payment?.proof_file;
  if (!filename) return responseError(response, 'Payment screenshot not found.', 404);
  const sent = await streamStoredFile(store, filename, response, { localFolder: 'payment', downloadName: `${order.order_no || order.id}-payment-proof` });
  if (!sent) return responseError(response, 'Payment screenshot not found.', 404);
}));

router.post('/orders/:id/razorpay/order', ...roleGuard(request => request.app.locals.store, 'admin', 'manager', 'client'), asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const order = await scopedOrder(store, request, request.params.id);
  if (!order) return responseError(response, 'Order not found or access is not allowed.', 404);
  if (completedPayment(order.payment_status)) return responseError(response, 'This order is already paid.');
  const amount = Math.round(asNumber(order.payable_amount || order.fee_amount) * 100);
  if (amount <= 0) return responseError(response, 'This order does not have a payable amount.');
  try {
    const gatewayOrder = await razorpayRequest('/orders', { method: 'POST', body: JSON.stringify({ amount, currency: 'INR', receipt: String(order.order_no || `TS-${order.id}`).slice(0, 40), notes: { taxsaathi_order_id: String(order.id) } }) });
    await store.update('orders', { id: order.id }, { payment_method: 'razorpay', payment_status: 'initiated', gateway_order_id: gatewayOrder.id, updated_at: isoNow() });
    await store.insert('payments', { order_id: order.id, amount: amount / 100, method: 'razorpay', gateway: 'razorpay', gateway_order_id: gatewayOrder.id, status: 'initiated', submitted_by: request.auth.user.id, created_at: isoNow(), updated_at: isoNow() });
    response.status(201).json({ ok: true, key_id: String(process.env.RAZORPAY_KEY_ID), order: gatewayOrder, amount: amount / 100, currency: 'INR' });
  } catch (error) {
    responseError(response, error.message, 503);
  }
}));

router.post('/orders/:id/razorpay/verify', ...roleGuard(request => request.app.locals.store, 'admin', 'manager', 'client'), asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const order = await scopedOrder(store, request, request.params.id);
  const gatewayOrderId = String(request.body.razorpay_order_id || '').trim();
  const paymentId = String(request.body.razorpay_payment_id || '').trim();
  const signature = String(request.body.razorpay_signature || '').trim();
  if (!order || !gatewayOrderId || !paymentId || !signature || gatewayOrderId !== String(order.gateway_order_id || '') || !verifyRazorpaySignature(gatewayOrderId, paymentId, signature)) return responseError(response, 'Razorpay payment verification failed.', 422);
  const amount = asNumber(order.payable_amount || order.fee_amount);
  const existing = await store.findOne('payments', { order_id: order.id, gateway_order_id: gatewayOrderId }, { sort: { id: -1 } });
  const payment = existing
    ? await store.update('payments', { id: existing.id }, { amount, method: 'razorpay', gateway: 'razorpay', gateway_payment_id: paymentId, gateway_signature: signature, status: 'verified', received_at: isoNow(), updated_at: isoNow() })
    : await store.insert('payments', { order_id: order.id, amount, method: 'razorpay', gateway: 'razorpay', gateway_order_id: gatewayOrderId, gateway_payment_id: paymentId, gateway_signature: signature, status: 'verified', received_by: request.auth.user.id, received_at: isoNow(), created_at: isoNow(), updated_at: isoNow() });
  const updated = await store.update('orders', { id: order.id }, { payment_method: 'razorpay', payment_status: 'verified', payment_reference: paymentId, paid_at: isoNow(), updated_at: isoNow() });
  let invoice = await store.findOne('invoices', { order_id: order.id });
  if (!invoice) invoice = await store.insert('invoices', { order_id: order.id, client_id: order.client_id, invoice_no: `INV-${String(order.id).padStart(5, '0')}`, issue_date: isoNow().slice(0, 10), subtotal: amount, tax_percent: 0, tax_amount: 0, total_amount: amount, paid_amount: amount, status: 'paid', notes: 'Razorpay payment', created_at: isoNow(), updated_at: isoNow(), partner_id: order.partner_id || null });
  await store.update('payments', { id: payment.id }, { invoice_id: invoice.id });
  response.json({ ok: true, message: 'Payment verified successfully.', order: await resolveOrder(store, updated) });
}));

router.post('/orders/:id/payment', ...roleGuard(request => request.app.locals.store, 'admin', 'manager'), asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const order = await store.findOne('orders', { id: Number(request.params.id) });
  if (!order) return responseError(response, 'Order not found.', 404);
  const reviewStatus = String(request.body.status || 'verified').toLowerCase();
  const amount = asNumber(request.body.amount || order.payable_amount || order.fee_amount);
  const pendingPayment = await store.findOne('payments', { order_id: order.id, status: 'pending_review' }, { sort: { id: -1 } });
  const payment = pendingPayment
    ? await store.update('payments', { id: pendingPayment.id }, { amount, method: request.body.method || pendingPayment.method || 'manual', reference_no: request.body.reference_no || pendingPayment.reference_no || '', notes: request.body.notes || pendingPayment.notes || '', received_by: request.auth.user.id, received_at: reviewStatus === 'verified' ? isoNow() : null, status: reviewStatus, updated_at: isoNow(), partner_id: order.partner_id || null })
    : await store.insert('payments', { invoice_id: null, order_id: order.id, amount, method: request.body.method || 'manual', reference_no: request.body.reference_no || '', notes: request.body.notes || '', received_by: request.auth.user.id, received_at: reviewStatus === 'verified' ? isoNow() : null, status: reviewStatus, created_at: isoNow(), partner_id: order.partner_id || null });
  const updated = await store.update('orders', { id: order.id }, { payment_status: reviewStatus, payment_method: request.body.method || order.payment_method || 'manual', payment_reference: request.body.reference_no || order.payment_reference || '', updated_at: isoNow() });
  if (reviewStatus !== 'verified') return response.json({ ok: true, order: await resolveOrder(store, updated) });
  let invoice = await store.findOne('invoices', { order_id: order.id });
  if (!invoice) {
    invoice = await store.insert('invoices', { order_id: order.id, client_id: order.client_id, invoice_no: `INV-${String(order.id).padStart(5, '0')}`, issue_date: isoNow().slice(0, 10), due_date: null, subtotal: amount, tax_percent: 0, tax_amount: 0, total_amount: amount, paid_amount: amount, status: 'paid', notes: '', created_at: isoNow(), updated_at: isoNow(), partner_id: order.partner_id || null });
  } else {
    invoice = await store.update('invoices', { id: invoice.id }, { paid_amount: amount, status: 'paid', updated_at: isoNow() });
  }
  await store.update('payments', { id: payment.id }, { invoice_id: invoice.id });
  response.json({ ok: true, order: await resolveOrder(store, updated) });
}));

router.post('/orders/:id/documents', authRequired((request) => request.app.locals.store), upload.single('file'), asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const order = await scopedOrder(store, request, request.params.id);
  if (!order) return responseError(response, 'Order not found or access is not allowed.', 404);
  if (!request.file) return responseError(response, 'Choose a file to upload.');
  const stored = await saveUpload(store, request.file, {
    folder: 'documents',
    metadata: { kind: 'order-document', order_id: order.id, uploaded_by: request.auth.user.id }
  });
  const document = await store.insert('order_documents', {
    order_id: order.id,
    requirement_id: numericId(request.body.requirement_id),
    source: roleSlug(request.auth) === 'client' ? 'client' : 'staff',
    label: String(request.body.label || request.file.originalname),
    original_name: request.file.originalname,
    stored_name: stored.stored_name,
    file_id: stored.file_id,
    storage: stored.storage,
    mime_type: stored.mime_type,
    size_bytes: stored.size_bytes,
    is_client_visible: 1,
    uploaded_by: request.auth.user.id,
    created_at: isoNow(),
    document_status: 'submitted'
  });
  response.status(201).json({ ok: true, document });
}));

router.get('/orders/:id/invoice.pdf', authRequired((request) => request.app.locals.store), asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const order = await scopedOrder(store, request, request.params.id);
  if (!order) return responseError(response, 'Order not found or access is not allowed.', 404);
  const item = await resolveOrder(store, order);
  const settings = await settingsMap(store);
  response.setHeader('Content-Type', 'application/pdf');
  response.setHeader('Content-Disposition', `inline; filename="${item.invoice?.invoice_no || item.order_no}.pdf"`);
  const document = new PDFDocument({ size: 'A4', margin: 42 });
  document.pipe(response);
  document.fillColor('#0b3c91').fontSize(26).font('Helvetica-Bold').text(settings.site_name || 'TaxSaathi');
  document.fillColor('#111827').fontSize(10).font('Helvetica').text(settings.contact_address || 'Professional tax and compliance services');
  document.moveDown(1.5);
  document.fillColor('#0f172a').fontSize(22).font('Helvetica-Bold').text('INVOICE');
  document.moveDown(0.4);
  document.fontSize(10).font('Helvetica').text(`Invoice: ${item.invoice?.invoice_no || `INV-${item.id}`}`);
  document.text(`Order: ${item.order_no}`);
  document.text(`Issue date: ${item.invoice?.issue_date || String(item.created_at || '').slice(0, 10)}`);
  document.moveDown();
  document.roundedRect(42, document.y, 510, 80, 10).fillAndStroke('#f4f7fb', '#dbe4ee');
  document.fillColor('#0f172a').font('Helvetica-Bold').fontSize(11).text('Billed to', 58, document.y + 16);
  document.font('Helvetica').fontSize(10).text(item.client?.name || 'Client', 58, document.y + 34);
  document.text(item.client?.email || '', 58, document.y + 49);
  document.fillColor('#0f172a').font('Helvetica-Bold').fontSize(11).text('Service', 330, document.y - 49);
  document.font('Helvetica').fontSize(10).text(item.service?.title || 'Service', 330, document.y - 33, { width: 190 });
  document.moveDown(5);
  document.font('Helvetica-Bold').fontSize(11).text('Description', 42, document.y);
  document.text('Amount', 450, document.y - 13);
  document.moveTo(42, document.y + 4).lineTo(552, document.y + 4).stroke('#dbe4ee');
  document.font('Helvetica').fontSize(10).text(item.service?.title || 'Tax service', 42, document.y + 16);
  document.text(money(item.payable_amount || item.fee_amount), 450, document.y - 13);
  document.moveTo(42, document.y + 30).lineTo(552, document.y + 30).stroke('#dbe4ee');
  document.font('Helvetica-Bold').fontSize(13).text('Total', 42, document.y + 48);
  document.text(money(item.payable_amount || item.fee_amount), 450, document.y - 15);
  document.moveDown(5);
  document.font('Helvetica').fontSize(9).fillColor('#64748b').text('This invoice was generated by the TaxSaathi MERN application.');
  document.end();
}));

router.get('/notifications', authRequired((request) => request.app.locals.store), asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const items = await notificationRows(store, request.auth.user.id, Math.min(100, Number(request.query.limit || 40)), request.auth);
  response.json({ ok: true, items, unread_count: items.filter((item) => !item.is_read).length, latest_id: items[0]?.id || 0 });
}));

router.post('/notifications/:id/read', authRequired((request) => request.app.locals.store), asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const item = await store.findOne('notifications', { id: Number(request.params.id) });
  if (!item || (item.user_id && Number(item.user_id) !== Number(request.auth.user.id))) return responseError(response, 'Notification not found.', 404);
  if (item.user_id) {
    await store.update('notifications', { id: item.id }, { is_read: 1, read_at: isoNow(), updated_at: isoNow() });
  } else if (item.uid) {
    const existing = await store.findOne('notification_user_reads', { user_id: Number(request.auth.user.id), notification_uid: item.uid });
    if (existing) await store.update('notification_user_reads', { id: existing.id }, { is_read: 1, read_at: isoNow(), updated_at: isoNow() });
    else await store.insert('notification_user_reads', { user_id: Number(request.auth.user.id), notification_uid: item.uid, is_read: 1, read_at: isoNow(), created_at: isoNow(), updated_at: isoNow() });
  }
  response.json({ ok: true });
}));

router.post('/notifications/read-all', authRequired((request) => request.app.locals.store), asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  await store.updateMany('notifications', { user_id: Number(request.auth.user.id), is_read: 0 }, { is_read: 1, read_at: isoNow(), updated_at: isoNow() });
  if (isGlobalRole(request.auth)) {
    const globalRows = await store.find('notifications', { user_id: null, is_read: 0 }, { limit: 1000 });
    for (const item of globalRows) {
      const existing = await store.findOne('notification_user_reads', { user_id: Number(request.auth.user.id), notification_uid: item.uid });
      if (existing) await store.update('notification_user_reads', { id: existing.id }, { is_read: 1, read_at: isoNow(), updated_at: isoNow() });
      else await store.insert('notification_user_reads', { user_id: Number(request.auth.user.id), notification_uid: item.uid, is_read: 1, read_at: isoNow(), created_at: isoNow(), updated_at: isoNow() });
    }
  }
  response.json({ ok: true });
}));

router.get('/notifications/stream', authRequired((request) => request.app.locals.store), asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  response.writeHead(200, { 'Content-Type': 'text/event-stream', 'Cache-Control': 'no-cache', Connection: 'keep-alive', 'X-Accel-Buffering': 'no' });
  response.write(`event: ready\ndata: ${JSON.stringify({ ok: true })}\n\n`);
  let lastId = Number(request.query.after_id || 0);
  const timer = setInterval(async () => {
    const filter = isGlobalRole(request.auth)
      ? { $or: [{ user_id: Number(request.auth.user.id) }, { user_id: null }], id: { $gt: lastId } }
      : { user_id: Number(request.auth.user.id), id: { $gt: lastId } };
    const items = await store.find('notifications', filter, { sort: { id: 1 }, limit: 20 });
    for (const item of items) {
      lastId = Math.max(lastId, Number(item.id) || 0);
      response.write(`event: notification\ndata: ${JSON.stringify(serializeNotification(item))}\n\n`);
    }
    response.write(`event: ping\ndata: ${JSON.stringify({ at: Date.now() })}\n\n`);
  }, 10000);
  request.on('close', () => clearInterval(timer));
}));

router.get('/support/conversations', authRequired((request) => request.app.locals.store), asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const role = roleSlug(request.auth);
  const filter = isGlobalRole(request.auth)
    ? {}
    : role === 'executive'
      ? { $or: [{ requester_user_id: Number(request.auth.user.id) }, { assigned_to_user_id: Number(request.auth.user.id) }] }
      : { requester_user_id: Number(request.auth.user.id) };
  const conversations = await store.find('support_conversations', filter, { sort: { id: -1 }, limit: 50 });
  response.json({ ok: true, conversations });
}));

router.post('/support/conversations', authRequired((request) => request.app.locals.store), asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const conversation = await store.insert('support_conversations', {
    public_id: `TS-${Date.now().toString(36).toUpperCase()}`,
    requester_user_id: request.auth.user.id,
    requester_role: roleSlug(request.auth),
    requester_name: request.auth.user.name,
    requester_email: request.auth.user.email,
    requester_phone: request.auth.user.phone,
    subject: String(request.body.subject || 'New support request'),
    category: String(request.body.category || 'general'),
    priority: String(request.body.priority || 'normal'),
    status: 'open',
    last_message_at: isoNow(),
    last_message_preview: String(request.body.message || '').slice(0, 180),
    last_message_by: request.auth.user.id,
    requester_unread_count: 0,
    support_unread_count: 1,
    created_at: isoNow(),
    updated_at: isoNow()
  });
  if (request.body.message) await store.insert('support_messages', { conversation_id: conversation.id, sender_user_id: request.auth.user.id, sender_role: roleSlug(request.auth), sender_name: request.auth.user.name, message: String(request.body.message), message_type: 'text', created_at: isoNow() });
  response.status(201).json({ ok: true, conversation });
}));

router.get('/support/conversations/:id/messages', authRequired((request) => request.app.locals.store), asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const conversation = await store.findOne('support_conversations', { id: Number(request.params.id) });
  if (!conversation) return responseError(response, 'Conversation not found.', 404);
  const role = roleSlug(request.auth);
  const canAccess = isGlobalRole(request.auth)
    || Number(conversation.requester_user_id) === Number(request.auth.user.id)
    || (role === 'executive' && Number(conversation.assigned_to_user_id) === Number(request.auth.user.id));
  if (!canAccess) return responseError(response, 'Conversation access is not allowed.', 403);
  const messages = await store.find('support_messages', { conversation_id: conversation.id }, { sort: { id: 1 } });
  response.json({ ok: true, conversation, messages });
}));

router.post('/support/conversations/:id/messages', authRequired((request) => request.app.locals.store), asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const conversation = await store.findOne('support_conversations', { id: Number(request.params.id) });
  if (!conversation) return responseError(response, 'Conversation not found.', 404);
  const role = roleSlug(request.auth);
  const isSupport = isGlobalRole(request.auth) || (role === 'executive' && Number(conversation.assigned_to_user_id) === Number(request.auth.user.id));
  const canAccess = isSupport || Number(conversation.requester_user_id) === Number(request.auth.user.id);
  if (!canAccess) return responseError(response, 'Conversation access is not allowed.', 403);
  const messageText = String(request.body.message || '').trim();
  if (!messageText) return responseError(response, 'Message cannot be empty.');
  const message = await store.insert('support_messages', { conversation_id: conversation.id, sender_user_id: request.auth.user.id, sender_role: role, sender_name: request.auth.user.name, message: messageText, message_type: 'text', created_at: isoNow() });
  await store.update('support_conversations', { id: conversation.id }, { last_message_at: isoNow(), last_message_preview: messageText.slice(0, 180), last_message_by: request.auth.user.id, updated_at: isoNow(), ...(isSupport ? { requester_unread_count: Number(conversation.requester_unread_count || 0) + 1 } : { support_unread_count: Number(conversation.support_unread_count || 0) + 1 }) });
  response.status(201).json({ ok: true, message });
}));

router.get('/partner/orders', ...roleGuard(request => request.app.locals.store, 'admin', 'manager', 'partners', 'partner'), asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const filter = isGlobalRole(request.auth) ? {} : partnerOwnerFilter(request.auth);
  const rows = await store.find('partner_orders', filter, { sort: { id: -1 } });
  const services = await store.find('services', {});
  const servicesById = new Map(services.map((service) => [Number(service.id), service]));
  response.json({ ok: true, orders: rows.map((row) => ({ ...row, service: servicesById.get(Number(row.service_id)) || null })) });
}));

router.get('/partner/orders/:id', ...roleGuard(request => request.app.locals.store, 'admin', 'manager', 'partners', 'partner'), asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const filter = isGlobalRole(request.auth) ? {} : partnerOwnerFilter(request.auth);
  const order = await store.findOne('partner_orders', { id: Number(request.params.id), ...filter });
  if (!order) return responseError(response, 'Partner order not found or access is not allowed.', 404);
  response.json({ ok: true, order: await resolvePartnerOrder(store, order) });
}));

router.post('/partner/orders/:id/coupon', ...roleGuard(request => request.app.locals.store, 'admin', 'manager', 'partners', 'partner'), asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const filter = isGlobalRole(request.auth) ? {} : partnerOwnerFilter(request.auth);
  const order = await store.findOne('partner_orders', { id: Number(request.params.id), ...filter });
  if (!order) return responseError(response, 'Partner order not found or access is not allowed.', 404);
  if (['paid', 'verified', 'approved'].includes(String(order.payment_status || '').toLowerCase())) return responseError(response, 'A coupon cannot be applied after payment has been verified.');
  const code = String(request.body.code || '').trim().toUpperCase();
  if (!code) return responseError(response, 'Enter a coupon code.');
  if (order.coupon_code) {
    if (String(order.coupon_code).toUpperCase() === code) return response.json({ ok: true, order: await resolvePartnerOrder(store, order), message: 'This coupon is already applied.' });
    return responseError(response, 'Only one coupon can be applied to an order.');
  }
  const result = await validateCoupon(store, { code, serviceId: order.service_id, amount: order.filing_fee, auth: request.auth });
  if (result.error) return responseError(response, result.error, 422);
  const updated = await store.update('partner_orders', { id: Number(order.id) }, {
    coupon_id: result.coupon.id,
    coupon_code: result.coupon.code,
    coupon_discount_type: result.coupon.discount_type,
    coupon_discount_value: result.coupon.discount_value,
    coupon_discount_amount: result.discount,
    payable_amount: result.payable,
    updated_at: isoNow()
  });
  await store.insert('coupon_redemptions', {
    coupon_id: result.coupon.id,
    coupon_code: result.coupon.code,
    user_id: result.actor.user_id,
    client_id: null,
    partner_id: result.actor.partner_id,
    partner_order_id: order.id,
    order_id: null,
    role: result.actor.role,
    discount_amount: result.discount,
    created_at: isoNow()
  });
  response.json({ ok: true, message: `${result.coupon.code} applied successfully.`, order: await resolvePartnerOrder(store, updated) });
}));

router.post('/partner/orders/:id/payment-proof', ...roleGuard(request => request.app.locals.store, 'admin', 'manager', 'partners', 'partner'), paymentProofUpload.single('file'), asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const filter = isGlobalRole(request.auth) ? {} : partnerOwnerFilter(request.auth);
  const order = await store.findOne('partner_orders', { id: Number(request.params.id), ...filter });
  if (!order) return responseError(response, 'Partner order not found or access is not allowed.', 404);
  if (!request.file) return responseError(response, 'Payment screenshot is required.');
  const transactionId = String(request.body.transaction_id || request.body.reference_no || '').trim();
  if (!transactionId) return responseError(response, 'Transaction ID is required.');
  const stored = await saveUpload(store, request.file, {
    folder: 'payment',
    metadata: { kind: 'partner-payment-proof', partner_order_id: order.id, uploaded_by: request.auth.user.id }
  });
  const updated = await store.update('partner_orders', { id: Number(order.id) }, {
    payment_method: String(request.body.payment_method || order.payment_method || 'manual'),
    payment_reference: transactionId,
    payment_proof: stored.stored_name,
    payment_proof_file_id: stored.file_id,
    payment_proof_storage: stored.storage,
    payment_status: 'pending_review',
    order_status: 'payment_submitted',
    updated_at: isoNow()
  });
  await notify(store, { userId: null, title: 'Partner payment proof submitted', message: `${order.order_no} is ready for payment verification.`, severity: 'info', url: `/partner/orders/${order.id}`, orderId: order.id });
  response.status(201).json({ ok: true, message: 'Payment proof submitted for verification.', order: await resolvePartnerOrder(store, updated) });
}));

router.get('/partner/orders/:id/payment-proof', ...roleGuard(request => request.app.locals.store, 'admin', 'manager', 'partners', 'partner'), asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const filter = isGlobalRole(request.auth) ? {} : partnerOwnerFilter(request.auth);
  const order = await store.findOne('partner_orders', { id: Number(request.params.id), ...filter });
  if (!order || !order.payment_proof) return responseError(response, 'Payment screenshot not found.', 404);
  const sent = await streamStoredFile(store, order.payment_proof, response, { localFolder: 'payment', downloadName: `${order.order_no || order.id}-payment-proof` });
  if (!sent) return responseError(response, 'Payment screenshot not found.', 404);
}));

router.post('/partner/orders/:id/payment', ...roleGuard(request => request.app.locals.store, 'admin', 'manager'), asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const order = await store.findOne('partner_orders', { id: Number(request.params.id) });
  if (!order) return responseError(response, 'Partner order not found.', 404);
  const status = String(request.body.status || 'verified').toLowerCase();
  const updated = await store.update('partner_orders', { id: Number(order.id) }, {
    payment_status: status,
    order_status: status === 'verified' ? 'approved' : status === 'rejected' ? 'payment_rejected' : order.order_status,
    paid_at: status === 'verified' ? isoNow() : order.paid_at,
    updated_at: isoNow()
  });
  response.json({ ok: true, order: await resolvePartnerOrder(store, updated) });
}));

router.get('/partner/dashboard', ...roleGuard(request => request.app.locals.store, 'admin', 'manager', 'partners', 'partner'), asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const filter = isGlobalRole(request.auth) ? {} : partnerOwnerFilter(request.auth);
  const orders = await store.find('partner_orders', filter, { sort: { id: -1 } });
  const referrals = await store.find('partner_leads', filter, { sort: { id: -1 } });
  const referredClients = await store.find('clients', filter, { sort: { id: -1 } });
  const profile = await store.findOne('partner_profiles', filter);
  const subscription = await store.findOne('partner_subscriptions', filter, { sort: { id: -1 } });
  const approvedOrders = orders.filter((row) => ['approved', 'completed'].includes(String(row.order_status || '').toLowerCase()));
  const paidOrders = orders.filter((row) => ['paid', 'approved', 'verified'].includes(String(row.payment_status || '').toLowerCase()));
  const commissions = orders.reduce((total, row) => total + asNumber(row.commission_amount || row.partner_commission || row.commission), 0);
  response.json({
    ok: true,
    stats: {
      total_orders: orders.length,
      approved: approvedOrders.length,
      paid: paidOrders.length,
      value: orders.reduce((total, row) => total + asNumber(row.payable_amount || row.filing_fee), 0),
      commissions,
      referrals: referrals.length || referredClients.length
    },
    profile,
    subscription,
    orders: orders.slice(0, 8),
    referrals: referrals.slice(0, 8),
    referred_clients: referredClients.slice(0, 8)
  });
}));

const collectionWhitelist = new Set([
  'activity_logs', 'activity_notification_reads', 'clients', 'coupon_redemptions', 'coupons', 'customer_details', 'executive_order_payouts', 'executive_payment_profiles', 'executive_salary_payments', 'faqs', 'grow_ranking_keywords', 'grow_ranking_logs', 'grow_ranking_tasks', 'invoices', 'leads', 'marketing_campaigns', 'intelligence_models', 'notifications', 'notification_logs', 'notification_manager', 'notification_templates', 'notification_workflows', 'notification_user_reads', 'orders', 'order_documents', 'order_workflow_reminder_logs', 'partner_activity_logs', 'partner_client_portal_access', 'partner_coupons', 'partner_coupon_redemptions', 'partner_documents', 'partner_leads', 'partner_messages', 'partner_notifications', 'partner_orders', 'partner_order_documents', 'partner_profiles', 'partner_staff', 'partner_subscriptions', 'partner_subscription_requests', 'partner_tasks', 'payments', 'permission_catalog', 'roles', 'seo_keywords', 'services', 'service_applications', 'service_banners', 'service_benefits', 'service_categories', 'service_requirements', 'service_reviews', 'service_types', 'sidebar_menus', 'support_conversations', 'support_messages', 'tax_rules', 'testimonials', 'users', 'user_push_tokens', 'user_roles', 'website_settings'
]);

const adminOnlyCollections = new Set(['roles', 'user_roles', 'permission_catalog', 'users', 'website_settings']);
const protectedCollectionFields = new Set(['_id', 'id', '_legacy_table', 'password_hash', 'reset_token', 'otp_code', 'otp_hash', 'secret', 'api_key']);
const userEditableFields = new Set(['name', 'email', 'phone', 'client_id', 'partner_id', 'is_active', 'is_phone_verified', 'is_email_verified']);

function redactCollectionRow(row) {
  if (!row) return row;
  const output = { ...row };
  for (const key of Object.keys(output)) {
    if (/password|secret|token|api_key|private_key/i.test(key)) delete output[key];
  }
  return output;
}

function pickCollectionChanges(collection, body, actorRole) {
  const changes = {};
  for (const [key, value] of Object.entries(body || {})) {
    if (protectedCollectionFields.has(key)) continue;
    if (collection === 'users' && !userEditableFields.has(key)) continue;
    if (actorRole !== 'admin' && ['website_settings', 'roles', 'user_roles', 'permission_catalog'].includes(collection)) continue;
    changes[key] = value;
  }
  changes.updated_at = isoNow();
  return changes;
}

router.get('/admin/overview', ...roleGuard(request => request.app.locals.store, 'admin', 'manager'), asyncRoute(async (request, response) => {
  response.json(await getDashboard(request.app.locals.store, request));
}));

router.get('/admin/collections', ...roleGuard(request => request.app.locals.store, 'admin', 'manager'), asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const counts = await store.stats();
  response.json({ ok: true, collections: Object.entries(counts).map(([name, count]) => ({ name, count })).sort((a, b) => a.name.localeCompare(b.name)) });
}));

router.get('/admin/collections/:collection', ...roleGuard(request => request.app.locals.store, 'admin', 'manager'), asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const collection = request.params.collection;
  if (!collectionWhitelist.has(collection)) return responseError(response, 'Collection is not exposed.', 404);
  if (adminOnlyCollections.has(collection) && roleSlug(request.auth) !== 'admin') return responseError(response, 'This collection requires administrator access.', 403);
  const page = Math.max(1, Number(request.query.page || 1));
  const limit = Math.min(100, Math.max(1, Number(request.query.limit || 50)));
  const search = String(request.query.search || '').trim().toLowerCase();
  let rows = await store.find(collection, {}, { sort: { id: -1 } });
  if (search) rows = rows.filter((row) => JSON.stringify(row).toLowerCase().includes(search));
  response.json({ ok: true, collection, rows: rows.slice((page - 1) * limit, page * limit).map(redactCollectionRow), total: rows.length, page, limit });
}));

router.patch('/admin/collections/:collection/:id', ...roleGuard(request => request.app.locals.store, 'admin', 'manager'), asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const collection = request.params.collection;
  if (!collectionWhitelist.has(collection)) return responseError(response, 'Collection is not exposed.', 404);
  const actorRole = roleSlug(request.auth);
  if (adminOnlyCollections.has(collection) && actorRole !== 'admin') return responseError(response, 'This collection requires administrator access.', 403);
  const changes = pickCollectionChanges(collection, request.body, actorRole);
  const row = await store.update(collection, { id: Number(request.params.id) }, changes);
  if (!row) return responseError(response, 'Record not found.', 404);
  response.json({ ok: true, row: redactCollectionRow(row) });
}));

router.post('/admin/collections/:collection', ...roleGuard(request => request.app.locals.store, 'admin'), asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const collection = request.params.collection;
  if (!collectionWhitelist.has(collection) || adminOnlyCollections.has(collection) && roleSlug(request.auth) !== 'admin') {
    return responseError(response, 'This collection is not available for creation.', 403);
  }
  const changes = pickCollectionChanges(collection, request.body, roleSlug(request.auth));
  delete changes.updated_at;
  const row = await store.insert(collection, { ...changes, created_at: request.body.created_at || isoNow(), updated_at: isoNow() });
  await recordAudit(store, request, { action: 'admin.collection.created', resourceType: collection, resourceId: row.id, newValue: redactCollectionRow(row) });
  response.status(201).json({ ok: true, row: redactCollectionRow(row) });
}));

router.delete('/admin/collections/:collection/:id', ...roleGuard(request => request.app.locals.store, 'admin'), asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const collection = request.params.collection;
  if (!collectionWhitelist.has(collection) || ['users', 'roles', 'services', 'orders'].includes(collection)) return responseError(response, 'This collection cannot be deleted through the generic editor.', 403);
  const result = await store.remove(collection, { id: Number(request.params.id) });
  response.json({ ok: true, deleted: result.deletedCount > 0 });
}));

router.get('/admin/users', ...roleGuard(request => request.app.locals.store, 'admin', 'manager'), asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const users = await store.find('users', {}, { sort: { id: -1 } });
  const roles = await store.find('roles', {});
  const rolesById = new Map(roles.map((role) => [Number(role.id), role]));
  response.json({ ok: true, users: users.map((user) => ({ ...safeUser(user), role: rolesById.get(Number(user.role_id)) || null })) });
}));

router.get('/admin/partners', ...roleGuard(request => request.app.locals.store, 'admin', 'manager'), asyncRoute(async (request, response) => {
  const partners = await adminPartnerRecords(request.app.locals.store);
  response.json({ ok: true, partners, total: partners.length });
}));

router.get('/admin/partners/:id', ...roleGuard(request => request.app.locals.store, 'admin', 'manager'), asyncRoute(async (request, response) => {
  const partner = (await adminPartnerRecords(request.app.locals.store)).find((row) => Number(row.id) === Number(request.params.id));
  if (!partner) return responseError(response, 'Partner user not found.', 404);
  response.json({ ok: true, partner });
}));

router.post('/admin/users/:id/toggle', ...roleGuard(request => request.app.locals.store, 'admin'), asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const user = await store.findOne('users', { id: Number(request.params.id) });
  if (!user) return responseError(response, 'User not found.', 404);
  const updated = await store.update('users', { id: user.id }, { is_active: asBool(user.is_active) ? 0 : 1, updated_at: isoNow() });
  response.json({ ok: true, user: safeUser(updated) });
}));

router.get('/admin/services', ...roleGuard(request => request.app.locals.store, 'admin', 'manager'), asyncRoute(async (request, response) => {
  const services = await enrichServices(request.app.locals.store, await request.app.locals.store.find('services', {}, { sort: { sort_order: 1, id: 1 } }));
  response.json({ ok: true, services });
}));

router.post('/admin/services', ...roleGuard(request => request.app.locals.store, 'admin'), asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const title = String(request.body.title || '').trim();
  if (!title) return responseError(response, 'Service title is required.');
  const service = await store.insert('services', { service_category_id: numericId(request.body.service_category_id), title, slug: slugify(request.body.slug || title), excerpt: request.body.excerpt || '', description: request.body.description || '', filing_fee: asNumber(request.body.filing_fee), turnaround_days: asNumber(request.body.turnaround_days, 7), icon: request.body.icon || '', service_category: request.body.service_category || '', sort_order: asNumber(request.body.sort_order, 0), is_featured: asBool(request.body.is_featured) ? 1 : 0, is_active: 1, created_at: isoNow(), updated_at: isoNow() });
  response.status(201).json({ ok: true, service });
}));

router.patch('/admin/services/:id', ...roleGuard(request => request.app.locals.store, 'admin'), asyncRoute(async (request, response) => {
  const changes = { ...request.body, updated_at: isoNow() };
  delete changes.id;
  const service = await request.app.locals.store.update('services', { id: Number(request.params.id) }, changes);
  if (!service) return responseError(response, 'Service not found.', 404);
  response.json({ ok: true, service });
}));

router.get('/admin/categories', ...roleGuard(request => request.app.locals.store, 'admin', 'manager'), asyncRoute(async (request, response) => {
  const categories = await request.app.locals.store.find('service_categories', {}, { sort: { sort_order: 1, id: 1 } });
  response.json({ ok: true, categories });
}));

router.get('/admin/settings', ...roleGuard(request => request.app.locals.store, 'admin'), asyncRoute(async (request, response) => {
  const settings = await request.app.locals.store.find('website_settings', {}, { sort: { id: 1 } });
  response.json({ ok: true, settings });
}));

router.patch('/admin/settings/:key', ...roleGuard(request => request.app.locals.store, 'admin'), asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const key = request.params.key;
  const existing = await store.findOne('website_settings', { setting_key: key });
  const value = String(request.body.value ?? '');
  const row = existing
    ? await store.update('website_settings', { id: existing.id }, { setting_value: value, updated_at: isoNow() })
    : await store.insert('website_settings', { setting_key: key, setting_value: value, created_at: isoNow(), updated_at: isoNow() });
  response.json({ ok: true, setting: row });
}));

router.get('/admin/payment-settings', ...roleGuard(request => request.app.locals.store, 'admin'), asyncRoute(async (request, response) => {
  const settings = await settingsMap(request.app.locals.store);
  response.json({ ok: true, payment: paymentDetails(settings), settings: paymentSettingKeys.map((key) => ({ setting_key: key, setting_value: settings[key] || '' })) });
}));

router.patch('/admin/payment-settings', ...roleGuard(request => request.app.locals.store, 'admin'), asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const saved = [];
  for (const key of paymentSettingKeys.filter((item) => item !== 'payment_qr_code')) {
    if (request.body[key] === undefined) continue;
    const existing = await store.findOne('website_settings', { setting_key: key });
    const value = String(request.body[key] ?? '').trim();
    const row = existing
      ? await store.update('website_settings', { id: existing.id }, { setting_value: value, updated_at: isoNow() })
      : await store.insert('website_settings', { setting_key: key, setting_value: value, created_at: isoNow(), updated_at: isoNow() });
    saved.push(row);
  }
  response.json({ ok: true, settings: saved });
}));

router.post('/admin/payment-settings/qr', ...roleGuard(request => request.app.locals.store, 'admin'), paymentQrUpload.single('file'), asyncRoute(async (request, response) => {
  if (!request.file) return responseError(response, 'Choose a QR image to upload.');
  const store = request.app.locals.store;
  const stored = await saveUpload(store, request.file, { folder: 'payment', metadata: { kind: 'payment-qr', public: true, uploaded_by: request.auth.user.id } });
  const existing = await store.findOne('website_settings', { setting_key: 'payment_qr_code' });
  const value = stored.file_id ? `/api/files/public/${stored.file_id}` : `/payment-assets/${stored.stored_name}`;
  const row = existing
    ? await store.update('website_settings', { id: existing.id }, { setting_value: value, updated_at: isoNow() })
    : await store.insert('website_settings', { setting_key: 'payment_qr_code', setting_value: value, created_at: isoNow(), updated_at: isoNow() });
  response.status(201).json({ ok: true, setting: row, payment_qr_code: value });
}));

router.get('/admin/coupons', ...roleGuard(request => request.app.locals.store, 'admin', 'manager'), asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const [coupons, legacy] = await Promise.all([
    store.find('coupons', {}, { sort: { id: -1 } }),
    store.find('partner_coupons', {}, { sort: { id: -1 } })
  ]);
  response.json({ ok: true, coupons: [...coupons.map((row) => normalizeCoupon(row, 'coupons')), ...legacy.map((row) => normalizeCoupon(row, 'partner_coupons'))] });
}));

router.post('/admin/coupons', ...roleGuard(request => request.app.locals.store, 'admin'), asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const code = String(request.body.code || '').trim().toUpperCase();
  const discountType = String(request.body.discount_type || 'percent').toLowerCase() === 'fixed' ? 'fixed' : 'percent';
  if (!code) return responseError(response, 'Coupon code is required.');
  if (await findCoupon(store, code)) return responseError(response, 'A coupon with this code already exists.', 409);
  const coupon = await store.insert('coupons', {
    code,
    title: String(request.body.title || code).trim(),
    description: String(request.body.description || '').trim(),
    service_id: numericId(request.body.service_id),
    discount_type: discountType,
    discount_value: asNumber(request.body.discount_value),
    max_discount_amount: asNumber(request.body.max_discount_amount),
    min_order_amount: asNumber(request.body.min_order_amount),
    usage_limit: asNumber(request.body.usage_limit),
    per_user_limit: 1,
    starts_at: request.body.starts_at || null,
    expires_at: request.body.expires_at || null,
    is_active: asBool(request.body.is_active === undefined ? true : request.body.is_active) ? 1 : 0,
    created_by: request.auth.user.id,
    created_at: isoNow(),
    updated_at: isoNow()
  });
  response.status(201).json({ ok: true, coupon: normalizeCoupon(coupon, 'coupons') });
}));

router.patch('/admin/coupons/:id', ...roleGuard(request => request.app.locals.store, 'admin'), asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const existing = await store.findOne('coupons', { id: Number(request.params.id) });
  if (!existing) return responseError(response, 'Coupon not found.', 404);
  const changes = { ...request.body, updated_at: isoNow() };
  delete changes.id;
  delete changes._id;
  if (changes.code) changes.code = String(changes.code).trim().toUpperCase();
  if (changes.discount_value !== undefined) changes.discount_value = asNumber(changes.discount_value);
  if (changes.usage_limit !== undefined) changes.usage_limit = asNumber(changes.usage_limit);
  const coupon = await store.update('coupons', { id: existing.id }, changes);
  response.json({ ok: true, coupon: normalizeCoupon(coupon, 'coupons') });
}));

router.get('/executive/payouts', ...roleGuard(request => request.app.locals.store, 'admin', 'manager', 'executive'), asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const filter = isGlobalRole(request.auth)
    ? {}
    : { executive_user_id: Number(request.auth.user.id) };
  const rows = await store.find('executive_order_payouts', filter, { sort: { id: -1 } });
  response.json({ ok: true, rows, total: rows.length });
}));

router.get('/admin/reports', ...roleGuard(request => request.app.locals.store, 'admin', 'manager', 'executive'), asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  let orders = await store.find('orders', allowedOrderFilter(request.auth), { sort: { id: 1 } });
  const statuses = Object.entries(orders.reduce((summary, order) => { summary[order.status || 'submitted'] = (summary[order.status || 'submitted'] || 0) + 1; return summary; }, {})).map(([status, total]) => ({ status, label: statusLabel(status), total }));
  const byService = new Map();
  for (const order of orders) byService.set(Number(order.service_id), (byService.get(Number(order.service_id)) || 0) + asNumber(order.payable_amount || order.fee_amount));
  const services = await store.find('services', {});
  const topServices = [...byService.entries()].map(([serviceId, amount]) => ({ service: services.find((service) => Number(service.id) === serviceId)?.title || `Service #${serviceId}`, amount })).sort((a, b) => b.amount - a.amount).slice(0, 8);
  const revenue = orders.reduce((total, order) => total + asNumber(order.payable_amount || order.fee_amount), 0);
  response.json({ ok: true, summary: { orders: orders.length, revenue, average: orders.length ? Math.round(revenue / orders.length) : 0, completed: orders.filter((order) => order.status === 'completed').length }, statuses, topServices, orders });
}));

router.get('/admin/reports.csv', ...roleGuard(request => request.app.locals.store, 'admin', 'manager', 'executive'), asyncRoute(async (request, response) => {
  const orders = await request.app.locals.store.find('orders', allowedOrderFilter(request.auth), { sort: { id: 1 } });
  const rows = [['Order', 'Client ID', 'Service ID', 'Amount', 'Status', 'Payment Status', 'Created At'], ...orders.map((order) => [order.order_no, order.client_id, order.service_id, order.payable_amount || order.fee_amount, order.status, order.payment_status, order.created_at])];
  const csv = rows.map((row) => row.map((value) => `"${String(value ?? '').replaceAll('"', '""')}"`).join(',')).join('\n');
  response.setHeader('Content-Type', 'text/csv');
  response.setHeader('Content-Disposition', 'attachment; filename="taxsaathi-orders.csv"');
  response.send(csv);
}));

// Profile, password, and device-session management.
router.patch('/auth/profile', authRequired((request) => request.app.locals.store), asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const current = request.auth.user;
  const changes = {};
  if (request.body.name !== undefined) changes.name = String(request.body.name).trim();
  if (request.body.email !== undefined) {
    const email = String(request.body.email).trim().toLowerCase();
    if (!email) return responseError(response, 'Email is required.');
    const duplicate = await findUserByEmail(store, email);
    if (duplicate && Number(duplicate.id) !== Number(current.id)) return responseError(response, 'That email is already in use.', 409);
    changes.email = email;
  }
  if (request.body.phone !== undefined) {
    const phone = normalizePhone(request.body.phone);
    if (!phone) return responseError(response, 'Enter a valid 10-digit mobile number.');
    const duplicate = await findUserByPhone(store, phone);
    if (duplicate && Number(duplicate.id) !== Number(current.id)) return responseError(response, 'That mobile number is already in use.', 409);
    changes.phone = phone;
  }
  if (!Object.keys(changes).length) return responseError(response, 'No profile changes were supplied.');
  changes.updated_at = isoNow();
  const user = await store.update('users', { id: Number(current.id) }, changes);
  if (user?.client_id) await store.update('clients', { id: Number(user.client_id) }, { ...(changes.name ? { name: changes.name } : {}), ...(changes.email ? { email: changes.email } : {}), ...(changes.phone ? { phone: changes.phone } : {}), updated_at: isoNow() });
  response.json({ ok: true, user: await currentUserResponse(store, user) });
}));

router.post('/auth/change-password', authRequired((request) => request.app.locals.store), asyncRoute(async (request, response) => {
  const current = request.auth.user;
  const oldPassword = String(request.body.current_password || '');
  const newPassword = String(request.body.new_password || '');
  if (newPassword.length < 8) return responseError(response, 'New password must be at least eight characters.');
  if (!current.password_hash || !(await bcrypt.compare(oldPassword, compatibleBcryptHash(current.password_hash)))) return responseError(response, 'Current password is incorrect.', 401);
  await request.app.locals.store.update('users', { id: Number(current.id) }, { password_hash: await bcrypt.hash(newPassword, 12), updated_at: isoNow() });
  await recordAudit(request.app.locals.store, request, { action: 'user.password.changed', resourceType: 'user', resourceId: current.id });
  response.json({ ok: true, message: 'Password changed successfully.' });
}));

router.post('/notifications/push-token', authRequired((request) => request.app.locals.store), asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const token = String(request.body.token || '').trim();
  if (!token || token.length > 2048) return responseError(response, 'A valid push token is required.');
  const existing = await store.findOne('user_push_tokens', { user_id: Number(request.auth.user.id), token });
  const payload = { user_id: Number(request.auth.user.id), token, platform: String(request.body.platform || 'web'), user_agent: String(request.headers['user-agent'] || '').slice(0, 500), last_seen_at: isoNow(), updated_at: isoNow() };
  const row = existing ? await store.update('user_push_tokens', { id: existing.id }, payload) : await store.insert('user_push_tokens', { ...payload, created_at: isoNow() });
  response.status(existing ? 200 : 201).json({ ok: true, token: { id: row.id, platform: row.platform, last_seen_at: row.last_seen_at } });
}));

router.delete('/notifications/push-token', authRequired((request) => request.app.locals.store), asyncRoute(async (request, response) => {
  const token = String(request.body.token || request.query.token || '').trim();
  if (token) await request.app.locals.store.remove('user_push_tokens', { user_id: Number(request.auth.user.id), token });
  response.json({ ok: true });
}));

// Complete the legacy document workflow: wrong-document feedback, reuploads, and final outputs.
router.post('/orders/:id/documents/:documentId/status', ...roleGuard(request => request.app.locals.store, 'admin', 'manager', 'executive'), asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const order = await store.findOne('orders', { id: Number(request.params.id) });
  const document = await store.findOne('order_documents', { id: Number(request.params.documentId), order_id: Number(request.params.id) });
  if (!order || !document) return responseError(response, 'Order document not found.', 404);
  const documentStatus = String(request.body.status || 'active').toLowerCase();
  if (!['active', 'submitted', 'wrong', 'rejected', 'delivered', 'replaced'].includes(documentStatus)) return responseError(response, 'Unsupported document status.');
  const updated = await store.update('order_documents', { id: document.id }, {
    document_status: documentStatus,
    wrong_reason: documentStatus === 'wrong' ? String(request.body.reason || '').trim() : document.wrong_reason || null,
    wrong_marked_by: documentStatus === 'wrong' ? request.auth.user.id : document.wrong_marked_by || null,
    wrong_marked_at: documentStatus === 'wrong' ? isoNow() : document.wrong_marked_at || null,
    is_client_visible: request.body.is_client_visible === undefined ? 1 : (asBool(request.body.is_client_visible) ? 1 : 0),
    updated_at: isoNow()
  });
  const clientUser = order.client_id ? await store.findOne('users', { client_id: Number(order.client_id), role_id: 5 }) : null;
  if (clientUser) await notify(store, { userId: clientUser.id, title: documentStatus === 'wrong' ? 'Document needs attention' : 'Document status updated', message: `${document.label || document.original_name || 'A document'} for ${order.order_no} is ${statusLabel(documentStatus)}.`, severity: documentStatus === 'wrong' ? 'warning' : 'info', url: `/client/orders/${order.id}`, orderId: order.id });
  response.json({ ok: true, document: updated });
}));

router.post('/orders/:id/documents/:documentId/reupload', authRequired((request) => request.app.locals.store), upload.single('file'), asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const order = await scopedOrder(store, request, request.params.id);
  const previous = await store.findOne('order_documents', { id: Number(request.params.documentId), order_id: Number(request.params.id) });
  if (!order || !previous) return responseError(response, 'Order document not found or access is not allowed.', 404);
  if (!request.file) return responseError(response, 'Choose a replacement file.');
  if (roleSlug(request.auth) === 'client' && !asBool(previous.is_client_visible)) return responseError(response, 'This document cannot be replaced by the client.', 403);
  const stored = await saveUpload(store, request.file, { folder: 'documents', metadata: { kind: 'document-reupload', order_id: order.id, replaces_document_id: previous.id, uploaded_by: request.auth.user.id } });
  const replacement = await store.insert('order_documents', {
    order_id: order.id,
    requirement_id: previous.requirement_id || null,
    source: roleSlug(request.auth) === 'client' ? 'client' : 'staff',
    label: String(request.body.label || previous.label || request.file.originalname),
    original_name: stored.original_name,
    stored_name: stored.stored_name,
    file_id: stored.file_id,
    storage: stored.storage,
    mime_type: stored.mime_type,
    size_bytes: stored.size_bytes,
    is_client_visible: 1,
    uploaded_by: request.auth.user.id,
    reuploaded_for_document_id: previous.id,
    created_at: isoNow(),
    updated_at: isoNow(),
    document_status: 'submitted'
  });
  await store.update('order_documents', { id: previous.id }, { document_status: 'replaced', replaced_by_document_id: replacement.id, updated_at: isoNow() });
  response.status(201).json({ ok: true, document: replacement });
}));

router.post('/orders/:id/output', ...roleGuard(request => request.app.locals.store, 'admin', 'manager', 'executive'), upload.single('file'), asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const order = await store.findOne('orders', { id: Number(request.params.id) });
  if (!order) return responseError(response, 'Order not found.', 404);
  if (!request.file) return responseError(response, 'Choose an output file.');
  const stored = await saveUpload(store, request.file, { folder: 'documents', metadata: { kind: 'order-output', order_id: order.id, uploaded_by: request.auth.user.id } });
  const document = await store.insert('order_documents', {
    order_id: order.id,
    requirement_id: null,
    source: 'staff',
    label: String(request.body.label || 'Final output'),
    original_name: stored.original_name,
    stored_name: stored.stored_name,
    file_id: stored.file_id,
    storage: stored.storage,
    mime_type: stored.mime_type,
    size_bytes: stored.size_bytes,
    is_client_visible: 1,
    uploaded_by: request.auth.user.id,
    document_status: 'delivered',
    created_at: isoNow(),
    updated_at: isoNow()
  });
  const nextStatus = String(request.body.complete || '').toLowerCase() === 'true' ? 'completed' : order.status;
  if (nextStatus !== order.status) await store.update('orders', { id: order.id }, { status: nextStatus, completed_at: isoNow(), updated_at: isoNow() });
  const clientUser = order.client_id ? await store.findOne('users', { client_id: Number(order.client_id), role_id: 5 }) : null;
  if (clientUser) await notify(store, { userId: clientUser.id, title: 'Final document delivered', message: `A final output is ready for ${order.order_no}.`, severity: 'success', url: `/client/orders/${order.id}`, orderId: order.id });
  response.status(201).json({ ok: true, document, order: await resolveOrder(store, await store.findOne('orders', { id: order.id })) });
}));

router.get('/orders/:id/activity', authRequired((request) => request.app.locals.store), asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const order = await scopedOrder(store, request, request.params.id);
  if (!order) return responseError(response, 'Order not found or access is not allowed.', 404);
  const rows = await store.find('activity_logs', { order_id: order.id }, { sort: { id: -1 }, limit: 200 });
  response.json({ ok: true, activity: rows });
}));

// Role and permission administration.
router.get('/admin/roles', ...permissionGuard(request => request.app.locals.store, 'users.roles.manage'), asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const [roles, assignments, storedPermissions] = await Promise.all([store.find('roles', {}, { sort: { id: 1 } }), store.find('user_roles', {}), store.find('permission_catalog', {}, { sort: { id: 1 } })]);
  const permissions = storedPermissions.length ? storedPermissions : permissionCatalogRows();
  response.json({ ok: true, roles: roles.map((role) => ({ ...role, users: assignments.filter((item) => Number(item.role_id) === Number(role.id)).length })), permissions });
}));

router.post('/admin/users/:id/roles', ...permissionGuard(request => request.app.locals.store, 'users.roles.manage'), asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const user = await store.findOne('users', { id: Number(request.params.id) });
  if (!user) return responseError(response, 'User not found.', 404);
  const roleIds = [...new Set((Array.isArray(request.body.role_ids) ? request.body.role_ids : [request.body.role_id]).map(Number).filter(Number.isFinite))];
  if (!roleIds.length) return responseError(response, 'At least one role is required.');
  const roles = await store.find('roles', { id: { $in: roleIds } });
  if (roles.length !== roleIds.length) return responseError(response, 'One or more roles are invalid.');
  const current = await store.find('user_roles', { user_id: user.id });
  for (const assignment of current) await store.remove('user_roles', { id: assignment.id });
  for (const roleId of roleIds) await store.insert('user_roles', { user_id: user.id, role_id: roleId, created_at: isoNow(), updated_at: isoNow() });
  const primaryRoleId = Number(request.body.primary_role_id || roleIds[0]);
  const updated = await store.update('users', { id: user.id }, { role_id: primaryRoleId, updated_at: isoNow() });
  await recordAudit(store, request, { action: 'user.roles.updated', resourceType: 'user', resourceId: user.id, previousValue: { role_id: user.role_id }, newValue: { role_ids: roleIds, primary_role_id: primaryRoleId } });
  response.json({ ok: true, user: await currentUserResponse(store, updated) });
}));

router.post('/admin/users/:id/password', ...permissionGuard(request => request.app.locals.store, 'security.manage'), asyncRoute(async (request, response) => {
  const password = String(request.body.password || '');
  if (password.length < 8) return responseError(response, 'Password must be at least eight characters.');
  const user = await request.app.locals.store.findOne('users', { id: Number(request.params.id) });
  if (!user) return responseError(response, 'User not found.', 404);
  await request.app.locals.store.update('users', { id: user.id }, { password_hash: await bcrypt.hash(password, 12), updated_at: isoNow() });
  await recordAudit(request.app.locals.store, request, { action: 'user.password.reset', resourceType: 'user', resourceId: user.id });
  response.json({ ok: true, message: 'Password updated.' });
}));

// Service catalogue details which were previously managed only by the PHP admin views.
const serviceChildTables = {
  requirements: 'service_requirements',
  benefits: 'service_benefits',
  types: 'service_types',
  reviews: 'service_reviews',
  banners: 'service_banners'
};

router.get('/admin/services/:id/catalog', ...roleGuard(request => request.app.locals.store, 'admin', 'manager'), asyncRoute(async (request, response) => {
  const service = await servicePayload(request.app.locals.store, await request.app.locals.store.findOne('services', { id: Number(request.params.id) }));
  if (!service) return responseError(response, 'Service not found.', 404);
  response.json({ ok: true, service });
}));

for (const [segment, table] of Object.entries(serviceChildTables)) {
  router.get(`/admin/services/:id/${segment}`, ...roleGuard(request => request.app.locals.store, 'admin', 'manager'), asyncRoute(async (request, response) => {
    const rows = await request.app.locals.store.find(table, { service_id: Number(request.params.id) }, { sort: { sort_order: 1, id: 1 } });
    response.json({ ok: true, rows });
  }));
  router.post(`/admin/services/:id/${segment}`, ...roleGuard(request => request.app.locals.store, 'admin'), asyncRoute(async (request, response) => {
    const service = await request.app.locals.store.findOne('services', { id: Number(request.params.id) });
    if (!service) return responseError(response, 'Service not found.', 404);
    const payload = { ...request.body, service_id: service.id, created_at: isoNow(), updated_at: isoNow() };
    delete payload.id; delete payload._id; delete payload._legacy_table;
    const row = await request.app.locals.store.insert(table, payload);
    response.status(201).json({ ok: true, row });
  }));
  router.patch(`/admin/services/:id/${segment}/:childId`, ...roleGuard(request => request.app.locals.store, 'admin'), asyncRoute(async (request, response) => {
    const changes = { ...request.body, updated_at: isoNow() };
    delete changes.id; delete changes._id; delete changes.service_id;
    const row = await request.app.locals.store.update(table, { id: Number(request.params.childId), service_id: Number(request.params.id) }, changes);
    if (!row) return responseError(response, 'Service detail not found.', 404);
    response.json({ ok: true, row });
  }));
  router.delete(`/admin/services/:id/${segment}/:childId`, ...roleGuard(request => request.app.locals.store, 'admin'), asyncRoute(async (request, response) => {
    const result = await request.app.locals.store.remove(table, { id: Number(request.params.childId), service_id: Number(request.params.id) });
    response.json({ ok: true, deleted: result.deletedCount > 0 });
  }));
}

router.post('/admin/categories', ...roleGuard(request => request.app.locals.store, 'admin'), asyncRoute(async (request, response) => {
  const title = String(request.body.title || '').trim();
  if (!title) return responseError(response, 'Category title is required.');
  const category = await request.app.locals.store.insert('service_categories', { title, slug: slugify(request.body.slug || title), description: String(request.body.description || ''), image: String(request.body.image || ''), sort_order: asNumber(request.body.sort_order), is_active: asBool(request.body.is_active === undefined ? true : request.body.is_active) ? 1 : 0, created_at: isoNow(), updated_at: isoNow() });
  response.status(201).json({ ok: true, category });
}));

router.patch('/admin/categories/:id', ...roleGuard(request => request.app.locals.store, 'admin'), asyncRoute(async (request, response) => {
  const changes = { ...request.body, updated_at: isoNow() };
  delete changes.id; delete changes._id;
  if (changes.slug) changes.slug = slugify(changes.slug);
  const category = await request.app.locals.store.update('service_categories', { id: Number(request.params.id) }, changes);
  if (!category) return responseError(response, 'Category not found.', 404);
  response.json({ ok: true, category });
}));

router.get('/admin/content', ...roleGuard(request => request.app.locals.store, 'admin'), asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const [settings, faqs, testimonials, categories] = await Promise.all([store.find('website_settings', {}, { sort: { id: 1 } }), store.find('faqs', {}, { sort: { sort_order: 1, id: 1 } }), store.find('testimonials', {}, { sort: { id: 1 } }), store.find('service_categories', {}, { sort: { sort_order: 1, id: 1 } })]);
  response.json({ ok: true, settings, faqs, testimonials, categories });
}));

router.get('/admin/leads', ...roleGuard(request => request.app.locals.store, 'admin', 'manager'), asyncRoute(async (request, response) => {
  const filter = request.query.status ? { status: String(request.query.status) } : {};
  const leads = await request.app.locals.store.find('leads', filter, { sort: { id: -1 }, limit: 500 });
  response.json({ ok: true, leads });
}));

router.patch('/admin/leads/:id', ...roleGuard(request => request.app.locals.store, 'admin', 'manager'), asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const previous = await store.findOne('leads', { id: Number(request.params.id) });
  if (!previous) return responseError(response, 'Lead not found.', 404);
  const changes = {};
  if (request.body.status !== undefined) changes.status = String(request.body.status).trim().toLowerCase();
  if (request.body.assigned_to !== undefined) changes.assigned_to = numericId(request.body.assigned_to);
  if (request.body.notes !== undefined) changes.notes = String(request.body.notes);
  changes.updated_at = isoNow();
  const lead = await store.update('leads', { id: previous.id }, changes);
  if (!lead) return responseError(response, 'Lead not found.', 404);
  await recordAudit(store, request, { action: 'lead.workflow.updated', resourceType: 'lead', resourceId: previous.id, previousValue: previous, newValue: lead });
  response.json({ ok: true, lead });
}));

router.get('/admin/applications', ...roleGuard(request => request.app.locals.store, 'admin', 'manager'), asyncRoute(async (request, response) => {
  const filter = request.query.status ? { status: String(request.query.status) } : {};
  const applications = await request.app.locals.store.find('service_applications', filter, { sort: { id: -1 }, limit: 500 });
  response.json({ ok: true, applications });
}));

router.patch('/admin/applications/:id', ...roleGuard(request => request.app.locals.store, 'admin', 'manager'), asyncRoute(async (request, response) => {
  const allowed = ['new', 'contacted', 'converted', 'closed', 'rejected'];
  const status = String(request.body.status || '').toLowerCase();
  if (!allowed.includes(status)) return responseError(response, 'Unsupported application status.');
  const application = await request.app.locals.store.update('service_applications', { id: Number(request.params.id) }, { status, admin_notes: String(request.body.admin_notes || ''), updated_at: isoNow() });
  if (!application) return responseError(response, 'Application not found.', 404);
  response.json({ ok: true, application });
}));

router.get('/admin/seo/keywords', ...roleGuard(request => request.app.locals.store, 'admin', 'manager'), asyncRoute(async (request, response) => {
  const keywords = await request.app.locals.store.find('grow_ranking_keywords', {}, { sort: { priority: 1, id: -1 }, limit: 500 });
  response.json({ ok: true, keywords });
}));

router.post('/admin/seo/keywords', ...roleGuard(request => request.app.locals.store, 'admin'), asyncRoute(async (request, response) => {
  const keyword = String(request.body.keyword || '').trim();
  if (!keyword) return responseError(response, 'Keyword is required.');
  const row = await request.app.locals.store.insert('grow_ranking_keywords', { keyword, keyword_slug: slugify(keyword), target_type: request.body.target_type || 'service', target_id: numericId(request.body.target_id), target_slug: String(request.body.target_slug || ''), target_url: String(request.body.target_url || ''), search_engine: String(request.body.search_engine || 'google'), location: String(request.body.location || 'India'), device: String(request.body.device || 'desktop'), current_rank: numericId(request.body.current_rank), previous_rank: numericId(request.body.previous_rank), best_rank: numericId(request.body.best_rank), search_volume: numericId(request.body.search_volume), keyword_difficulty: numericId(request.body.keyword_difficulty), priority: String(request.body.priority || 'medium'), status: String(request.body.status || 'new'), notes: String(request.body.notes || ''), is_active: 1, created_at: isoNow(), updated_at: isoNow() });
  response.status(201).json({ ok: true, keyword: row });
}));

router.get('/admin/seo/tasks', ...roleGuard(request => request.app.locals.store, 'admin', 'manager'), asyncRoute(async (request, response) => {
  const tasks = await request.app.locals.store.find('grow_ranking_tasks', {}, { sort: { due_date: 1, id: -1 }, limit: 500 });
  response.json({ ok: true, tasks });
}));

router.post('/admin/workflow/reminders/run', ...roleGuard(request => request.app.locals.store, 'admin', 'manager'), asyncRoute(async (request, response) => {
  response.json(await runWorkflowReminders(request.app.locals.store));
}));

router.get('/admin/activity', ...roleGuard(request => request.app.locals.store, 'admin', 'manager', 'executive'), asyncRoute(async (request, response) => {
  const filter = request.query.order_id ? { order_id: Number(request.query.order_id) } : {};
  const activity = await request.app.locals.store.find('activity_logs', filter, { sort: { id: -1 }, limit: Math.min(500, Number(request.query.limit || 100)) });
  response.json({ ok: true, activity });
}));

router.get('/admin/audit', ...permissionGuard(request => request.app.locals.store, 'audit.view'), asyncRoute(async (request, response) => {
  const filter = {};
  if (request.query.actor_user_id) filter.actor_user_id = Number(request.query.actor_user_id);
  if (request.query.resource_type) filter.resource_type = String(request.query.resource_type);
  if (request.query.action) filter.action = { $regex: String(request.query.action), $options: 'i' };
  if (request.query.search) {
    const search = String(request.query.search).slice(0, 80);
    filter.$or = [
      { action: { $regex: search, $options: 'i' } },
      { resource_type: { $regex: search, $options: 'i' } },
      { reason: { $regex: search, $options: 'i' } }
    ];
  }
  const limit = Math.min(500, Math.max(1, Number(request.query.limit || 100)));
  const audit = await request.app.locals.store.find('audit_logs', filter, { sort: { id: -1, created_at: -1 }, limit });
  response.json({ ok: true, audit, total: audit.length });
}));

// Partner CRM functionality retained from the PHP portal.
router.get('/partner/clients', ...roleGuard(request => request.app.locals.store, 'admin', 'manager', 'partners', 'partner'), asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const filter = isGlobalRole(request.auth) ? {} : partnerOwnerFilter(request.auth);
  const clients = await store.find('clients', filter, { sort: { id: -1 }, limit: 500 });
  response.json({ ok: true, clients });
}));

router.get('/partner/leads', ...roleGuard(request => request.app.locals.store, 'admin', 'manager', 'partners', 'partner'), asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const filter = isGlobalRole(request.auth) ? {} : partnerOwnerFilter(request.auth);
  response.json({ ok: true, leads: await store.find('partner_leads', filter, { sort: { id: -1 }, limit: 500 }) });
}));

router.post('/partner/leads', ...roleGuard(request => request.app.locals.store, 'partners', 'partner'), asyncRoute(async (request, response) => {
  const partnerId = Number(request.auth.user.partner_id || request.auth.user.id);
  const name = String(request.body.name || '').trim();
  if (!name) return responseError(response, 'Lead name is required.');
  const lead = await request.app.locals.store.insert('partner_leads', { partner_id: partnerId, name, email: String(request.body.email || ''), phone: String(request.body.phone || ''), company_name: String(request.body.company_name || ''), source: String(request.body.source || 'partner'), status: String(request.body.status || 'new'), follow_up_date: request.body.follow_up_date || null, notes: String(request.body.notes || ''), created_at: isoNow(), updated_at: isoNow() });
  response.status(201).json({ ok: true, lead });
}));

router.patch('/partner/leads/:id', ...roleGuard(request => request.app.locals.store, 'admin', 'manager', 'partners', 'partner'), asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const filter = isGlobalRole(request.auth) ? { id: Number(request.params.id) } : { id: Number(request.params.id), ...partnerOwnerFilter(request.auth) };
  const changes = { ...request.body, updated_at: isoNow() };
  delete changes.id; delete changes.partner_id;
  const lead = await store.update('partner_leads', filter, changes);
  if (!lead) return responseError(response, 'Partner lead not found.', 404);
  response.json({ ok: true, lead });
}));

router.get('/partner/tasks', ...roleGuard(request => request.app.locals.store, 'admin', 'manager', 'partners', 'partner'), asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const filter = isGlobalRole(request.auth) ? {} : partnerOwnerFilter(request.auth);
  response.json({ ok: true, tasks: await store.find('partner_tasks', filter, { sort: { due_date: 1, id: -1 }, limit: 500 }) });
}));

router.post('/partner/tasks', ...roleGuard(request => request.app.locals.store, 'partners', 'partner'), asyncRoute(async (request, response) => {
  const task = await request.app.locals.store.insert('partner_tasks', { partner_id: Number(request.auth.user.partner_id || request.auth.user.id), client_id: numericId(request.body.client_id), order_id: numericId(request.body.order_id), title: String(request.body.title || '').trim(), description: String(request.body.description || ''), status: String(request.body.status || 'todo'), priority: String(request.body.priority || 'normal'), due_date: request.body.due_date || null, created_by: request.auth.user.id, created_at: isoNow(), updated_at: isoNow() });
  if (!task.title) return responseError(response, 'Task title is required.');
  response.status(201).json({ ok: true, task });
}));

router.get('/partner/messages', ...roleGuard(request => request.app.locals.store, 'admin', 'manager', 'partners', 'partner'), asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const filter = isGlobalRole(request.auth) ? {} : partnerOwnerFilter(request.auth);
  response.json({ ok: true, messages: await store.find('partner_messages', filter, { sort: { id: -1 }, limit: 500 }) });
}));

router.post('/partner/messages', ...roleGuard(request => request.app.locals.store, 'partners', 'partner'), asyncRoute(async (request, response) => {
  const message = String(request.body.message || '').trim();
  if (!message) return responseError(response, 'Message cannot be empty.');
  const row = await request.app.locals.store.insert('partner_messages', { partner_id: Number(request.auth.user.partner_id || request.auth.user.id), client_id: numericId(request.body.client_id), channel: String(request.body.channel || 'portal'), subject: String(request.body.subject || ''), message, status: 'open', created_at: isoNow() });
  response.status(201).json({ ok: true, message: row });
}));

router.get('/partner/subscription', ...roleGuard(request => request.app.locals.store, 'admin', 'manager', 'partners', 'partner'), asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const filter = isGlobalRole(request.auth) ? {} : partnerOwnerFilter(request.auth);
  const [subscription, requests] = await Promise.all([store.findOne('partner_subscriptions', filter, { sort: { id: -1 } }), store.find('partner_subscription_requests', filter, { sort: { id: -1 }, limit: 50 })]);
  response.json({ ok: true, subscription, requests });
}));

router.post('/partner/subscription/request', ...roleGuard(request => request.app.locals.store, 'partners', 'partner'), asyncRoute(async (request, response) => {
  const partnerId = Number(request.auth.user.partner_id || request.auth.user.id);
  const row = await request.app.locals.store.insert('partner_subscription_requests', { partner_id: partnerId, plan_name: String(request.body.plan_name || ''), notes: String(request.body.notes || ''), status: 'pending', created_at: isoNow(), updated_at: isoNow() });
  response.status(201).json({ ok: true, request: row });
}));

// Admin profile workspaces. These endpoints intentionally return operational data only;
// password hashes, tokens, secrets, and private file paths never leave the server.
function operationalOutlook({ pending = 0, overdue = 0, completed = 0, total = 0, paid = 0 }) {
  if (overdue > 0) return { label: 'Needs attention', tone: 'danger', reason: `${overdue} overdue workflow item${overdue === 1 ? '' : 's'}.`, confidence: Math.min(96, 72 + overdue * 4) };
  if (pending > 0) return { label: 'In progress', tone: 'warning', reason: `${pending} item${pending === 1 ? '' : 's'} still moving through workflow.`, confidence: Math.min(92, 64 + pending * 3) };
  if (total > 0 && completed / total >= 0.7) return { label: 'Healthy', tone: 'success', reason: `${completed} of ${total} items are complete.`, confidence: 84 };
  if (paid > 0) return { label: 'Stable', tone: 'info', reason: `${paid} payment record${paid === 1 ? '' : 's'} confirmed.`, confidence: 70 };
  return { label: 'Insufficient signal', tone: 'neutral', reason: 'More workflow data is needed for a reliable outlook.', confidence: 35 };
}

async function adminUserDetail(store, id) {
  const user = await store.findOne('users', { id: Number(id) });
  if (!user) return null;
  const [roles, assignments, orders, payments, invoices, documents, notifications, audit, activity, sessions] = await Promise.all([
    store.find('roles', {}, { sort: { id: 1 } }),
    store.find('user_roles', { user_id: Number(user.id) }),
    store.find('orders', {}, { sort: { id: -1 }, limit: 500 }),
    store.find('payments', {}, { sort: { id: -1 }, limit: 500 }),
    store.find('invoices', {}, { sort: { id: -1 }, limit: 500 }),
    user.client_id ? store.find('order_documents', {}, { sort: { id: -1 }, limit: 500 }) : Promise.resolve([]),
    store.find('notifications', { user_id: Number(user.id) }, { sort: { id: -1 }, limit: 50 }),
    store.find('audit_logs', { $or: [{ actor_user_id: Number(user.id) }, { resource_type: 'user', resource_id: Number(user.id) }] }, { sort: { id: -1 }, limit: 100 }),
    store.find('activity_logs', { user_id: Number(user.id) }, { sort: { id: -1 }, limit: 100 }),
    store.find('sessions', { user_id: Number(user.id) }, { sort: { last_active_at: -1 }, limit: 20 })
  ]);
  const roleById = new Map(roles.map((role) => [Number(role.id), role]));
  const roleRows = assignments.map((row) => roleById.get(Number(row.role_id))).filter(Boolean);
  const userOrders = orders.filter((row) => Number(row.client_id) === Number(user.client_id) || Number(row.assigned_user_id) === Number(user.id) || Number(row.partner_id) === Number(user.partner_id || user.id));
  const orderIds = new Set(userOrders.map((row) => Number(row.id)));
  const userPayments = payments.filter((row) => orderIds.has(Number(row.order_id)) || Number(row.received_by) === Number(user.id));
  const userInvoices = invoices.filter((row) => orderIds.has(Number(row.order_id)) || Number(row.client_id) === Number(user.client_id));
  const userDocuments = documents.filter((row) => orderIds.has(Number(row.order_id)) || Number(row.uploaded_by) === Number(user.id));
  const completed = userOrders.filter((row) => ['completed', 'closed'].includes(String(row.status || '').toLowerCase())).length;
  const pending = userOrders.filter((row) => !['completed', 'closed', 'cancelled', 'rejected'].includes(String(row.status || '').toLowerCase())).length;
  return { profile: { ...safeUser(user), role: roleById.get(Number(user.role_id)) || null }, roles: roleRows, available_roles: roles, workflow: { orders: userOrders.slice(0, 100), documents: userDocuments.slice(0, 100), notifications, activity: activity.slice(0, 100) }, payments: userPayments.slice(0, 100), invoices: userInvoices.slice(0, 100), sessions: sessions.map((row) => ({ id: row.id, device: row.device, browser: row.browser, ip_address: row.ip_address, last_active_at: row.last_active_at, created_at: row.created_at, revoked_at: row.revoked_at })), audit: audit.slice(0, 100), outlook: operationalOutlook({ pending, completed, total: userOrders.length, paid: userPayments.filter((row) => ['paid', 'verified', 'success'].includes(String(row.status || '').toLowerCase())).length }) };
}

router.get('/admin/users/:id', ...permissionGuard(request => request.app.locals.store, 'users.view'), asyncRoute(async (request, response) => {
  const detail = await adminUserDetail(request.app.locals.store, request.params.id);
  if (!detail) return responseError(response, 'User not found.', 404);
  response.json({ ok: true, ...detail });
}));

router.patch('/admin/users/:id', ...permissionGuard(request => request.app.locals.store, 'users.edit'), asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const current = await store.findOne('users', { id: Number(request.params.id) });
  if (!current) return responseError(response, 'User not found.', 404);
  const changes = {};
  for (const field of ['name', 'email', 'phone', 'client_id', 'partner_id']) if (request.body[field] !== undefined) changes[field] = ['name', 'email', 'phone'].includes(field) ? String(request.body[field]).trim() : numericId(request.body[field]);
  if (request.body.is_active !== undefined) changes.is_active = asBool(request.body.is_active) ? 1 : 0;
  if (!Object.keys(changes).length) return responseError(response, 'No editable user fields were supplied.', 422);
  changes.updated_at = isoNow();
  const updated = await store.update('users', { id: current.id }, changes);
  await recordAudit(store, request, { action: 'user.profile.updated', resourceType: 'user', resourceId: current.id, previousValue: safeUser(current), newValue: safeUser(updated) });
  response.json({ ok: true, user: safeUser(updated) });
}));

router.get('/admin/leads/:id', ...roleGuard(request => request.app.locals.store, 'admin', 'manager'), asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const lead = await store.findOne('leads', { id: Number(request.params.id) });
  if (!lead) return responseError(response, 'Lead not found.', 404);
  const [applications, conversations, orders, users, audit] = await Promise.all([
    store.find('service_applications', { $or: [{ email: lead.email }, { mobile: lead.phone }] }, { sort: { id: -1 }, limit: 50 }),
    store.find('support_conversations', { $or: [{ requester_email: lead.email }, { requester_phone: lead.phone }] }, { sort: { id: -1 }, limit: 50 }),
    store.find('orders', {}, { sort: { id: -1 }, limit: 500 }),
    store.find('users', {}, { sort: { id: -1 } }),
    store.find('audit_logs', { resource_type: 'lead', resource_id: Number(lead.id) }, { sort: { id: -1 }, limit: 100 })
  ]);
  const linkedOrders = orders.filter((row) => String(row.customer_mobile || '') === String(lead.phone || '') || String(row.customer_email || '').toLowerCase() === String(lead.email || '').toLowerCase());
  const assigned = users.find((user) => Number(user.id) === Number(lead.assigned_to));
  response.json({ ok: true, lead, assigned_user: assigned ? safeUser(assigned) : null, customer: { name: lead.name, email: lead.email, phone: lead.phone, source: lead.source }, inquiry: { service: lead.service, message: lead.message, received_at: lead.created_at }, workflow: { applications, orders: linkedOrders, conversations, audit } });
}));

async function adminPartnerDetail(store, id) {
  const partner = (await adminPartnerRecords(store)).find((row) => Number(row.id) === Number(id));
  if (!partner) return null;
  const keys = [...partnerIdentityKeys(partner.user)].map(Number);
  const [activity, tasks, subscriptions, subscriptionRequests, payments, invoices] = await Promise.all([
    store.find('partner_activity_logs', { partner_id: { $in: keys } }, { sort: { id: -1 }, limit: 100 }),
    store.find('partner_tasks', { partner_id: { $in: keys } }, { sort: { id: -1 }, limit: 100 }),
    store.find('partner_subscriptions', { partner_id: { $in: keys } }, { sort: { id: -1 }, limit: 20 }),
    store.find('partner_subscription_requests', { partner_id: { $in: keys } }, { sort: { id: -1 }, limit: 20 }),
    store.find('payments', { partner_id: { $in: keys } }, { sort: { id: -1 }, limit: 100 }),
    store.find('invoices', { partner_id: { $in: keys } }, { sort: { id: -1 }, limit: 100 })
  ]);
  const total = partner.orders.length;
  const completed = partner.orders.filter((row) => ['completed', 'approved'].includes(String(row.order_status || row.status || '').toLowerCase())).length;
  const pending = total - completed;
  const paid = partner.orders.filter((row) => ['paid', 'approved', 'verified'].includes(String(row.payment_status || '').toLowerCase())).length;
  return { ...partner, workflow: { activity, tasks, subscriptions, subscription_requests }, payments, invoices, outlook: operationalOutlook({ pending, completed, total, paid }) };
}

router.get('/admin/partners/:id/workspace', ...roleGuard(request => request.app.locals.store, 'admin', 'manager'), asyncRoute(async (request, response) => {
  const partner = await adminPartnerDetail(request.app.locals.store, request.params.id);
  if (!partner) return responseError(response, 'Partner user not found.', 404);
  response.json({ ok: true, partner });
}));

async function adminExecutiveRecords(store) {
  const [users, roles, assignments, orders, payouts, paymentProfiles, salaries] = await Promise.all([
    store.find('users', {}, { sort: { id: -1 } }), store.find('roles', {}), store.find('user_roles', {}), store.find('orders', {}, { sort: { id: -1 } }), store.find('executive_order_payouts', {}, { sort: { id: -1 } }), store.find('executive_payment_profiles', {}, { sort: { id: -1 } }), store.find('executive_salary_payments', {}, { sort: { id: -1 } })
  ]);
  const roleById = new Map(roles.map((role) => [Number(role.id), role]));
  const executiveRoleIds = new Set(roles.filter((role) => String(role.slug || '').toLowerCase() === 'executive' || String(role.name || '').toLowerCase() === 'executive').map((role) => Number(role.id)));
  const executiveIds = new Set(assignments.filter((row) => executiveRoleIds.has(Number(row.role_id))).map((row) => Number(row.user_id)));
  users.filter((user) => executiveRoleIds.has(Number(user.role_id))).forEach((user) => executiveIds.add(Number(user.id)));
  return users.filter((user) => executiveIds.has(Number(user.id))).map((user) => {
    const ownOrders = orders.filter((row) => Number(row.assigned_user_id) === Number(user.id));
    const ownPayouts = payouts.filter((row) => Number(row.executive_user_id) === Number(user.id));
    const ownSalaries = salaries.filter((row) => Number(row.executive_user_id || row.user_id) === Number(user.id));
    const completed = ownOrders.filter((row) => ['completed', 'closed'].includes(String(row.status || '').toLowerCase())).length;
    const pending = ownOrders.filter((row) => !['completed', 'closed', 'cancelled', 'rejected'].includes(String(row.status || '').toLowerCase())).length;
    const overdue = ownOrders.filter((row) => ['overdue', 'at_risk'].includes(String(row.sla_status || '').toLowerCase())).length;
    return { id: Number(user.id), user: { ...safeUser(user), role: roleById.get(Number(user.role_id)) || null }, orders: ownOrders.slice(0, 100), payouts: ownPayouts, salary: ownSalaries, payment_profile: paymentProfiles.find((row) => Number(row.executive_user_id || row.user_id) === Number(user.id)) || null, stats: { total: ownOrders.length, completed, pending, overdue, payout_total: ownPayouts.reduce((sum, row) => sum + asNumber(row.amount), 0), salary_total: ownSalaries.reduce((sum, row) => sum + asNumber(row.amount || row.net_amount), 0) }, outlook: operationalOutlook({ pending, overdue, completed, total: ownOrders.length, paid: ownPayouts.filter((row) => String(row.status || '').toLowerCase() === 'paid').length }) };
  });
}

router.get('/admin/executives', ...roleGuard(request => request.app.locals.store, 'admin', 'manager'), asyncRoute(async (request, response) => {
  const executives = await adminExecutiveRecords(request.app.locals.store);
  response.json({ ok: true, executives, total: executives.length });
}));

router.get('/admin/executives/:id', ...roleGuard(request => request.app.locals.store, 'admin', 'manager'), asyncRoute(async (request, response) => {
  const executive = (await adminExecutiveRecords(request.app.locals.store)).find((row) => Number(row.id) === Number(request.params.id));
  if (!executive) return responseError(response, 'Executive not found.', 404);
  const [activity, audit] = await Promise.all([request.app.locals.store.find('activity_logs', { user_id: Number(executive.id) }, { sort: { id: -1 }, limit: 100 }), request.app.locals.store.find('audit_logs', { $or: [{ actor_user_id: Number(executive.id) }, { resource_type: 'user', resource_id: Number(executive.id) }] }, { sort: { id: -1 }, limit: 100 })]);
  response.json({ ok: true, executive: { ...executive, workflow: { activity, audit } } });
}));

router.get('/admin/notification-workflows', ...roleGuard(request => request.app.locals.store, 'admin'), asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const [templates, workflows] = await Promise.all([store.find('notification_templates', {}, { sort: { id: 1 } }), store.find('notification_workflows', {}, { sort: { id: -1 } })]);
  response.json({ ok: true, templates, workflows });
}));

router.post('/admin/notification-workflows', ...roleGuard(request => request.app.locals.store, 'admin'), asyncRoute(async (request, response) => {
  const name = String(request.body.name || request.body.label || '').trim();
  const eventKey = String(request.body.event_key || '').trim();
  if (!name || !eventKey) return responseError(response, 'Workflow name and event key are required.', 422);
  const row = await request.app.locals.store.insert('notification_workflows', { name, event_key: eventKey, channels: Array.isArray(request.body.channels) ? request.body.channels : ['in_app'], audience_roles: Array.isArray(request.body.audience_roles) ? request.body.audience_roles : [], priority: String(request.body.priority || 'normal'), voice_enabled: asBool(request.body.voice_enabled) ? 1 : 0, voice_provider: String(request.body.voice_provider || 'browser_speech'), voice_script: String(request.body.voice_script || ''), status: String(request.body.status || 'active'), conditions: request.body.conditions || {}, created_by: request.auth.user.id, created_at: isoNow(), updated_at: isoNow() });
  await recordAudit(request.app.locals.store, request, { action: 'notification.workflow.created', resourceType: 'notification_workflow', resourceId: row.id, newValue: row });
  response.status(201).json({ ok: true, workflow: row });
}));

router.patch('/admin/notification-workflows/:id', ...roleGuard(request => request.app.locals.store, 'admin'), asyncRoute(async (request, response) => {
  const changes = {};
  for (const field of ['name', 'event_key', 'priority', 'voice_provider', 'voice_script', 'status']) if (request.body[field] !== undefined) changes[field] = String(request.body[field]);
  if (request.body.voice_enabled !== undefined) changes.voice_enabled = asBool(request.body.voice_enabled) ? 1 : 0;
  for (const field of ['channels', 'audience_roles', 'conditions']) if (request.body[field] !== undefined) changes[field] = request.body[field];
  changes.updated_at = isoNow();
  const row = await request.app.locals.store.update('notification_workflows', { id: Number(request.params.id) }, changes);
  if (!row) return responseError(response, 'Notification workflow not found.', 404);
  await recordAudit(request.app.locals.store, request, { action: 'notification.workflow.updated', resourceType: 'notification_workflow', resourceId: row.id, newValue: row });
  response.json({ ok: true, workflow: row });
}));

router.get('/admin/management/overview', ...roleGuard(request => request.app.locals.store, 'admin'), asyncRoute(async (request, response) => {
  const collections = await request.app.locals.store.stats();
  response.json({ ok: true, collections: Object.entries(collections).filter(([name]) => collectionWhitelist.has(name)).map(([name, count]) => ({ name, count })).sort((a, b) => a.name.localeCompare(b.name)), protected_collections: [...adminOnlyCollections] });
}));

router.get('/admin/marketing/overview', ...roleGuard(request => request.app.locals.store, 'admin', 'manager'), asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const [keywords, tasks, campaigns, leads, applications, settings] = await Promise.all([store.find('grow_ranking_keywords', {}, { sort: { id: -1 }, limit: 500 }), store.find('grow_ranking_tasks', {}, { sort: { due_date: 1, id: -1 }, limit: 500 }), store.find('marketing_campaigns', {}, { sort: { id: -1 }, limit: 100 }), store.find('leads', {}, { sort: { id: -1 }, limit: 500 }), store.find('service_applications', {}, { sort: { id: -1 }, limit: 500 }), store.find('website_settings', {}, { sort: { id: 1 } })]);
  const convertedLeads = leads.filter((row) => ['converted', 'closed'].includes(String(row.status || '').toLowerCase())).length;
  response.json({ ok: true, keywords, tasks, campaigns, settings: settings.filter((row) => /seo|meta|og|canonical|robots|marketing/i.test(String(row.setting_key))), funnel: { leads: leads.length, applications: applications.length, converted_leads: convertedLeads, conversion_rate: leads.length ? Math.round(convertedLeads / leads.length * 100) : 0 } });
}));

router.post('/admin/marketing/campaigns', ...roleGuard(request => request.app.locals.store, 'admin'), asyncRoute(async (request, response) => {
  const name = String(request.body.name || '').trim();
  if (!name) return responseError(response, 'Campaign name is required.', 422);
  const row = await request.app.locals.store.insert('marketing_campaigns', { name, channel: String(request.body.channel || 'organic'), objective: String(request.body.objective || ''), budget: asNumber(request.body.budget), status: String(request.body.status || 'draft'), landing_path: String(request.body.landing_path || ''), utm_source: String(request.body.utm_source || ''), utm_medium: String(request.body.utm_medium || ''), utm_campaign: String(request.body.utm_campaign || ''), notes: String(request.body.notes || ''), created_by: request.auth.user.id, created_at: isoNow(), updated_at: isoNow() });
  await recordAudit(request.app.locals.store, request, { action: 'marketing.campaign.created', resourceType: 'marketing_campaign', resourceId: row.id, newValue: row });
  response.status(201).json({ ok: true, campaign: row });
}));

router.patch('/admin/marketing/campaigns/:id', ...roleGuard(request => request.app.locals.store, 'admin'), asyncRoute(async (request, response) => {
  const changes = {};
  for (const field of ['name', 'channel', 'objective', 'status', 'landing_path', 'utm_source', 'utm_medium', 'utm_campaign', 'notes']) if (request.body[field] !== undefined) changes[field] = String(request.body[field]);
  if (request.body.budget !== undefined) changes.budget = asNumber(request.body.budget);
  changes.updated_at = isoNow();
  const row = await request.app.locals.store.update('marketing_campaigns', { id: Number(request.params.id) }, changes);
  if (!row) return responseError(response, 'Campaign not found.', 404);
  response.json({ ok: true, campaign: row });
}));

router.get('/admin/intelligence/overview', ...roleGuard(request => request.app.locals.store, 'admin'), asyncRoute(async (request, response) => {
  const store = request.app.locals.store;
  const [models, users, leads, orders, notifications, audit] = await Promise.all([store.find('intelligence_models', {}, { sort: { id: -1 }}), store.find('users', {}, { limit: 1000 }), store.find('leads', {}, { limit: 1000 }), store.find('orders', {}, { limit: 1000 }), store.find('notifications', {}, { sort: { id: -1 }, limit: 1000 }), store.find('audit_logs', {}, { sort: { id: -1 }, limit: 1000 })]);
  const convertedLeads = leads.filter((row) => ['converted', 'closed'].includes(String(row.status || '').toLowerCase())).length;
  const completedOrders = orders.filter((row) => ['completed', 'closed'].includes(String(row.status || '').toLowerCase())).length;
  response.json({ ok: true, models, metrics: { data_records: users.length + leads.length + orders.length, lead_conversion_rate: leads.length ? Math.round(convertedLeads / leads.length * 100) : 0, order_completion_rate: orders.length ? Math.round(completedOrders / orders.length * 100) : 0, notification_delivery_records: notifications.filter((row) => row.delivered_at || row.is_read).length, audit_events: audit.length }, safeguards: ['Server-side authorization is required.', 'AI output is advisory and source-linked.', 'Human approval is required for official filings, payments, and legal responses.', 'No quantum or deep-learning result is presented without a configured model/provider.'] });
}));

router.post('/admin/intelligence/models', ...roleGuard(request => request.app.locals.store, 'admin'), asyncRoute(async (request, response) => {
  const name = String(request.body.name || '').trim();
  if (!name) return responseError(response, 'Model name is required.', 422);
  const row = await request.app.locals.store.insert('intelligence_models', { name, domain: String(request.body.domain || 'operations'), paradigm: String(request.body.paradigm || 'statistical'), provider: String(request.body.provider || 'not_configured'), version: String(request.body.version || '0.1'), status: String(request.body.status || 'planned'), confidence_threshold: asNumber(request.body.confidence_threshold, 0.8), data_source: String(request.body.data_source || ''), human_review_required: 1, created_by: request.auth.user.id, created_at: isoNow(), updated_at: isoNow() });
  await recordAudit(request.app.locals.store, request, { action: 'intelligence.model.created', resourceType: 'intelligence_model', resourceId: row.id, newValue: row });
  response.status(201).json({ ok: true, model: row });
}));

router.patch('/admin/intelligence/models/:id', ...roleGuard(request => request.app.locals.store, 'admin'), asyncRoute(async (request, response) => {
  const changes = {};
  for (const field of ['name', 'domain', 'paradigm', 'provider', 'version', 'status', 'data_source']) if (request.body[field] !== undefined) changes[field] = String(request.body[field]);
  if (request.body.confidence_threshold !== undefined) changes.confidence_threshold = asNumber(request.body.confidence_threshold);
  changes.updated_at = isoNow();
  const row = await request.app.locals.store.update('intelligence_models', { id: Number(request.params.id) }, changes);
  if (!row) return responseError(response, 'Intelligence model not found.', 404);
  response.json({ ok: true, model: row });
}));

export default router;
