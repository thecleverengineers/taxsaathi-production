import express from 'express';
import crypto from 'node:crypto';
import { allowRoles, authRequired, roleSlug } from '../lib/auth.mjs';
import { allowedOrderFilter, asBool, isoNow } from '../lib/legacy.mjs';
import { recordAudit } from '../lib/audit.mjs';
import { dispatchWebPushNotifications } from '../lib/web-push.mjs';

const router = express.Router();
const asyncRoute = (handler) => (request, response, next) =>
  Promise.resolve(handler(request, response, next)).catch(next);
const authenticate = authRequired((request) => request.app.locals.store);
const guard = (...roles) => [authenticate, allowRoles(...roles)];
const isAdmin = (auth) => ['admin', 'manager'].includes(roleSlug(auth));
const isPartner = (auth) => ['partner', 'partners'].includes(roleSlug(auth));
const isExecutive = (auth) => roleSlug(auth) === 'executive';
const positiveId = (raw) => {
  const id = Number(raw);
  return Number.isSafeInteger(id) && id > 0 ? id : null;
};
const error = (res, message, status = 400) => res.status(status).json({ ok: false, message });
const safeText = (value, max = 3000) => String(value ?? '').trim().slice(0, max);
const actorId = (request) => Number(request.auth.user.id);

export function validatePlan(input) {
  const changes = {};
  if (Object.hasOwn(input, 'priority')) {
    const priority = safeText(input.priority, 30).toLowerCase();
    if (!['low', 'normal', 'high', 'urgent'].includes(priority)) throw new Error('Priority must be low, normal, high or urgent.');
    changes.priority = priority;
  }
  if (Object.hasOwn(input, 'due_date')) {
    const date = input.due_date === null || input.due_date === '' ? null : safeText(input.due_date, 10);
    if (date && (!/^\d{4}-\d{2}-\d{2}$/.test(date) || Number.isNaN(Date.parse(date + 'T00:00:00Z')) || new Date(date + 'T00:00:00Z').toISOString().slice(0, 10) !== date)) {
      throw new Error('Deadline must be a valid YYYY-MM-DD date.');
    }
    changes.due_date = date;
  }
  return changes;
}

async function scopedOrder(request) {
  const id = positiveId(request.params.id);
  if (!id) return null;
  return request.app.locals.store.findOne('orders', { id, ...allowedOrderFilter(request.auth) });
}

async function notify(store, recipientId, title, body, url, orderId) {
  if (!recipientId) return;
  const row = await store.insert('notifications', {
    uid: crypto.randomUUID(), user_id: recipientId, title, message: body, body,
    kind: 'workflow', severity: 'info', is_read: 0,
    url, data_json: JSON.stringify({ order_id: orderId }),
    created_at: isoNow(), updated_at: isoNow()
  });
  dispatchWebPushNotifications(store, { userId: recipientId, title, message: body, url, orderId }).catch(() => {});
  return row;
}

async function notifyReviewers(store, title, body, orderId) {
  const [users, roles] = await Promise.all([store.find('users', { is_active: 1 }), store.find('roles', {})]);
  const reviewerRoleIds = new Set(roles.filter((role) => ['admin', 'manager'].includes(String(role.slug).toLowerCase())).map((role) => Number(role.id)));
  await Promise.all(users.filter((user) => reviewerRoleIds.has(Number(user.role_id))).slice(0, 25)
    .map((user) => notify(store, user.id, title, body, '/admin/orders/' + orderId, orderId)));
}

function ownPartnerIds(auth) {
  return [...new Set([auth.user.id, auth.user.partner_id].map(Number).filter((id) => Number.isSafeInteger(id) && id > 0))];
}

function clientPublicFields(row) {
  return {
    id: row.id, client_type: row.client_type || 'individual', name: row.name,
    company_name: row.company_name || '', email: row.email || '', phone: row.phone || '',
    contact_person: row.contact_person || '', partner_id: row.partner_id || null,
    created_at: row.created_at, updated_at: row.updated_at
  };
}

function accessibleClientFilter(auth) {
  if (isAdmin(auth)) return {};
  if (isPartner(auth)) return { partner_id: { $in: ownPartnerIds(auth) } };
  if (isExecutive(auth)) return null;
  return { id: Number(auth.user.client_id || 0) };
}

// Role-scoped operational dashboard. No private notes, tax IDs or passwords are returned.
router.get('/overview', authenticate, asyncRoute(async (req, res) => {
  const store = req.app.locals.store;
  const orders = await store.find('orders', allowedOrderFilter(req.auth), { sort: { id: -1 }, limit: 1500 });
  const today = isoNow().slice(0, 10);
  const statuses = {};
  for (const order of orders) statuses[order.status || 'submitted'] = (statuses[order.status || 'submitted'] || 0) + 1;
  const openOrders = orders.filter((order) => !['completed', 'cancelled', 'rejected'].includes(order.status));
  const overdue = openOrders.filter((order) => order.due_date && String(order.due_date).slice(0, 10) < today);
  const dueSoon = openOrders.filter((order) => order.due_date && String(order.due_date).slice(0, 10) >= today && String(order.due_date).slice(0, 10) <= new Date(Date.now() + 3 * 86400000).toISOString().slice(0, 10));
  return res.json({ ok: true, stats: {
    total: orders.length, awaiting_assignment: orders.filter((order) => order.status === 'submitted').length,
    in_progress: orders.filter((order) => ['assigned', 'work_in_progress'].includes(order.status)).length,
    approvals: orders.filter((order) => order.status === 'awaiting_approval').length,
    completed: orders.filter((order) => order.status === 'completed').length,
    overdue: overdue.length, due_soon: dueSoon.length,
    payments_pending: orders.filter((order) => !['paid', 'verified'].includes(String(order.payment_status || '').toLowerCase())).length
  }, statuses, urgent: [...overdue, ...dueSoon.filter((order) => !overdue.includes(order))]
    .slice(0, 12).map((order) => ({
      id: order.id, order_no: order.order_no, status: order.status,
      priority: order.priority || 'normal', due_date: order.due_date,
      assigned_user_id: order.assigned_user_id || null
    })) });
}));

router.get('/tasks', authenticate, asyncRoute(async (req, res) => {
  const store = req.app.locals.store;
  const orders = await store.find('orders', allowedOrderFilter(req.auth), { sort: { id: -1 }, limit: 500 });
  const [services, clients] = await Promise.all([store.find('services', {}), store.find('clients', {})]);
  const serviceNames = new Map(services.map((s) => [Number(s.id), s.title]));
  const clientNames = new Map(clients.map((c) => [Number(c.id), c.name || c.company_name]));
  res.json({ ok: true, total: orders.length, tasks: orders.map((order) => ({
    id: order.id, order_no: order.order_no, service: serviceNames.get(Number(order.service_id)) || 'Tax service',
    client_name: clientNames.get(Number(order.client_id)) || order.customer_name || 'Client',
    status: order.status || 'submitted', priority: order.priority || 'normal',
    due_date: order.due_date || null, assigned_user_id: order.assigned_user_id || null,
    partner_id: order.partner_id || null, payment_status: order.payment_status || 'not_due',
    amount: Number(order.payable_amount || order.fee_amount || 0), created_at: order.created_at
  })) });
}));

router.get('/clients', authenticate, asyncRoute(async (req, res) => {
  const store = req.app.locals.store;
  const filter = accessibleClientFilter(req.auth);
  if (filter) {
    const clients = await store.find('clients', filter, { sort: { id: -1 }, limit: 500 });
    return res.json({ ok: true, clients: clients.map(clientPublicFields) });
  }
  if (isExecutive(req.auth)) {
    const assigned = await store.find('orders', { assigned_user_id: actorId(req) }, { limit: 500 });
    const ids = [...new Set(assigned.map((order) => Number(order.client_id)).filter((id) => id > 0))];
    const clients = ids.length ? await store.find('clients', { id: { $in: ids } }) : [];
    return res.json({ ok: true, clients: clients.map(clientPublicFields) });
  }
  return error(res, 'Client access is not allowed.', 403);
}));

router.post('/clients', ...guard('admin', 'manager', 'partner', 'partners'), asyncRoute(async (req, res) => {
  const store = req.app.locals.store;
  const name = safeText(req.body.name, 120);
  const client_type = safeText(req.body.client_type || 'individual', 20).toLowerCase();
  if (!name || !['individual', 'corporate'].includes(client_type)) return error(res, 'Name and a valid client type are required.', 422);
  const partner_id = isPartner(req.auth) ? Number(req.auth.user.partner_id || req.auth.user.id) : positiveId(req.body.partner_id);
  const candidate = {
    name, client_type, partner_id: partner_id || null,
    company_name: safeText(req.body.company_name, 150),
    contact_person: safeText(req.body.contact_person, 120),
    email: safeText(req.body.email, 160).toLowerCase(),
    phone: safeText(req.body.phone, 30),
    created_by_user_id: actorId(req), created_at: isoNow(), updated_at: isoNow()
  };
  if (client_type === 'corporate' && !candidate.company_name) return error(res, 'Company name is required for a corporate client.', 422);
  const clients = await store.find('clients', partner_id ? { partner_id } : {}, { limit: 1500 });
  if (candidate.email && clients.some((c) => String(c.email || '').toLowerCase() === candidate.email)) return error(res, 'This email is already registered for this partner.', 409);
  const created = await store.insert('clients', candidate);
  await recordAudit(store, req, { action: 'client.created', resourceType: 'clients', resourceId: created.id, newValue: { client_type, partner_id } });
  res.status(201).json({ ok: true, client: clientPublicFields(created) });
}));

router.patch('/clients/:id', ...guard('admin', 'manager', 'partner', 'partners'), asyncRoute(async (req, res) => {
  const store = req.app.locals.store;
  const id = positiveId(req.params.id);
  const filter = isAdmin(req.auth) ? { id } : { id, partner_id: { $in: ownPartnerIds(req.auth) } };
  const previous = await store.findOne('clients', filter);
  if (!previous) return error(res, 'Client not found.', 404);
  const changes = { updated_at: isoNow() };
  for (const [key, max] of Object.entries({ name: 120, company_name: 150, contact_person: 120, email: 160, phone: 30 })) {
    if (Object.hasOwn(req.body, key)) changes[key] = safeText(req.body[key], max);
  }
  if (changes.email) changes.email = changes.email.toLowerCase();
  const updated = await store.update('clients', filter, changes);
  await recordAudit(store, req, { action: 'client.updated', resourceType: 'clients', resourceId: id, previousValue: clientPublicFields(previous), newValue: changes });
  res.json({ ok: true, client: clientPublicFields(updated) });
}));

router.patch('/orders/:id/plan', ...guard('admin', 'manager'), asyncRoute(async (req, res) => {
  const store = req.app.locals.store;
  const order = await store.findOne('orders', { id: positiveId(req.params.id) });
  if (!order) return error(res, 'Order not found.', 404);
  let changes;
  try { changes = validatePlan(req.body); }
  catch (e) { return error(res, e.message, 422); }
  if (!Object.keys(changes).length && !Object.hasOwn(req.body, 'internal_instructions')) return error(res, 'No planning fields were provided.', 422);
  const updated = Object.keys(changes).length ? await store.update('orders', { id: order.id }, { ...changes, updated_at: isoNow() }) : order;
  if (Object.hasOwn(req.body, 'internal_instructions')) {
    const instructions = safeText(req.body.internal_instructions, 4000);
    await store.upsert('order_workflow_details', { order_id: Number(order.id) }, { order_id: Number(order.id), internal_instructions: instructions, updated_by: actorId(req), updated_at: isoNow() });
  }
  await recordAudit(store, req, { action: 'order.plan.updated', resourceType: 'orders', resourceId: order.id, previousValue: { priority: order.priority, due_date: order.due_date }, newValue: changes });
  if (updated.assigned_user_id) await notify(store, updated.assigned_user_id, 'Job planning updated', updated.order_no + ' priority or deadline has changed.', '/admin/orders/' + updated.id, updated.id);
  res.json({ ok: true, plan: { priority: updated.priority || 'normal', due_date: updated.due_date || null } });
}));

router.get('/orders/:id/plan', authenticate, asyncRoute(async (req, res) => {
  const order = await scopedOrder(req);
  if (!order) return error(res, 'Order not found.', 404);
  const permitted = isAdmin(req.auth) || (isExecutive(req.auth) && Number(order.assigned_user_id) === actorId(req));
  const instructions = permitted
    ? await req.app.locals.store.findOne('order_workflow_details', { order_id: Number(order.id) })
    : null;
  res.json({ ok: true, plan: { priority: order.priority || 'normal', due_date: order.due_date || null, ...(permitted ? { internal_instructions: instructions?.internal_instructions || '' } : {}) } });
}));

router.get('/orders/:id/internal-notes', authenticate, asyncRoute(async (req, res) => {
  const order = await scopedOrder(req);
  if (!order) return error(res, 'Order not found.', 404);
  if (!isAdmin(req.auth) && !(isExecutive(req.auth) && Number(order.assigned_user_id) === actorId(req))) return error(res, 'Internal notes are restricted to authorised staff.', 403);
  const notes = await req.app.locals.store.find('order_internal_notes', { order_id: Number(order.id) }, { sort: { id: 1 }, limit: 250 });
  res.json({ ok: true, notes });
}));

router.post('/orders/:id/internal-notes', authenticate, asyncRoute(async (req, res) => {
  const order = await scopedOrder(req);
  if (!order) return error(res, 'Order not found.', 404);
  if (!isAdmin(req.auth) && !(isExecutive(req.auth) && Number(order.assigned_user_id) === actorId(req))) return error(res, 'Internal notes are restricted to authorised staff.', 403);
  const text = safeText(req.body.note, 3000);
  if (!text) return error(res, 'Write a note first.', 422);
  const store = req.app.locals.store;
  const note = await store.insert('order_internal_notes', {
    order_id: Number(order.id), author_id: actorId(req),
    author_name: safeText(req.auth.user.name, 120), text, created_at: isoNow()
  });
  await recordAudit(store, req, { action: 'order.internal_note.created', resourceType: 'orders', resourceId: order.id });
  res.status(201).json({ ok: true, note });
}));

router.get('/orders/:id/revisions', authenticate, asyncRoute(async (req, res) => {
  const order = await scopedOrder(req);
  if (!order) return error(res, 'Order not found.', 404);
  const revisions = await req.app.locals.store.find('order_revisions', { order_id: Number(order.id) }, { sort: { id: -1 }, limit: 100 });
  res.json({ ok: true, revisions });
}));

router.post('/orders/:id/return', ...guard('admin', 'manager'), asyncRoute(async (req, res) => {
  const store = req.app.locals.store;
  const order = await store.findOne('orders', { id: positiveId(req.params.id) });
  if (!order) return error(res, 'Order not found.', 404);
  if (order.status !== 'awaiting_approval') return error(res, 'Only deliverables pending approval can be returned for revision.', 409);
  const reason = safeText(req.body.reason, 1000);
  if (reason.length < 5) return error(res, 'Provide a revision reason (at least five characters).', 422);
  const pending = await store.find('order_documents', { order_id: Number(order.id), source: 'staff', document_status: 'pending_approval' });
  if (!pending.length) return error(res, 'There are no pending deliverables to return.', 409);
  for (const document of pending) await store.update('order_documents', { id: document.id }, { document_status: 'revision_required', is_client_visible: 0, revision_reason: reason, updated_at: isoNow() });
  const updated = await store.update('orders', { id: Number(order.id), status: 'awaiting_approval' }, { status: 'work_in_progress', revision_reason: reason, updated_at: isoNow() });
  if (!updated || updated.status !== 'work_in_progress') return error(res, 'This job changed during review. Refresh and retry.', 409);
  const revision = await store.insert('order_revisions', { order_id: Number(order.id), requested_by: actorId(req), reason, type: 'admin_return', status: 'in_progress', document_ids: pending.map((d) => d.id), created_at: isoNow() });
  await recordAudit(store, req, { action: 'deliverable.returned', resourceType: 'orders', resourceId: order.id, reason });
  if (order.assigned_user_id) await notify(store, order.assigned_user_id, 'Deliverable requires revision', order.order_no + ': ' + reason.slice(0, 160), '/admin/orders/' + order.id, order.id);
  res.json({ ok: true, revision, status: 'work_in_progress' });
}));

router.post('/orders/:id/revision-request', authenticate, asyncRoute(async (req, res) => {
  const store = req.app.locals.store;
  const order = await scopedOrder(req);
  if (!order) return error(res, 'Order not found.', 404);
  if (!['client', 'partner', 'partners'].includes(roleSlug(req.auth))) return error(res, 'Only the customer or submitting partner can request a revision.', 403);
  const owned = Number(order.submitted_by_user_id) === actorId(req) ||
    (roleSlug(req.auth) === 'client' && Number(order.client_id) === Number(req.auth.user.client_id || 0)) ||
    (isPartner(req.auth) && ownPartnerIds(req.auth).includes(Number(order.partner_id)));
  if (!owned) return error(res, 'Only the order owner can request a revision.', 403);
  if (order.status !== 'completed') return error(res, 'Revision requests are available after completion.', 409);
  const reason = safeText(req.body.reason, 1000);
  if (reason.length < 5) return error(res, 'Explain what needs correction.', 422);
  const existing = await store.findOne('order_revisions', { order_id: Number(order.id), type: 'customer_request', status: 'pending' });
  if (existing) return error(res, 'A revision request is already awaiting review.', 409);
  const revision = await store.insert('order_revisions', { order_id: Number(order.id), requested_by: actorId(req), reason, type: 'customer_request', status: 'pending', created_at: isoNow() });
  await recordAudit(store, req, { action: 'deliverable.revision.requested', resourceType: 'orders', resourceId: order.id, reason });
  await notifyReviewers(store, 'Revision request received', order.order_no + ': ' + reason.slice(0, 160), order.id);
  res.status(201).json({ ok: true, revision });
}));

router.post('/orders/:id/revisions/:revisionId/decision', ...guard('admin', 'manager'), asyncRoute(async (req, res) => {
  const store = req.app.locals.store;
  const order = await store.findOne('orders', { id: positiveId(req.params.id) });
  const revision = await store.findOne('order_revisions', { id: positiveId(req.params.revisionId), order_id: positiveId(req.params.id), type: 'customer_request' });
  if (!order || !revision) return error(res, 'Revision request not found.', 404);
  if (revision.status !== 'pending') return error(res, 'Revision request has already been reviewed.', 409);
  const decision = safeText(req.body.decision, 15);
  if (!['approved', 'rejected'].includes(decision)) return error(res, 'Decision must be approved or rejected.', 422);
  if (decision === 'approved' && (order.status !== 'completed' || !order.assigned_user_id)) return error(res, 'A completed and assigned order is required to reopen work.', 409);
  const updated = await store.update('order_revisions', { id: revision.id, status: 'pending' }, {
    status: decision, decided_by: actorId(req), decision_note: safeText(req.body.note, 1000), decided_at: isoNow()
  });
  if (decision === 'approved') {
    await store.update('orders', { id: Number(order.id), status: 'completed' }, { status: 'work_in_progress', revision_reason: revision.reason, updated_at: isoNow() });
    await notify(store, order.assigned_user_id, 'Revision work assigned', order.order_no + ': ' + revision.reason.slice(0, 160), '/admin/orders/' + order.id, order.id);
  }
  if (order.submitted_by_user_id) await notify(store, order.submitted_by_user_id, 'Revision request ' + decision, order.order_no + ' revision request was ' + decision + '.', ['partner', 'partners'].includes(String(order.submitter_role || '')) ? '/partner/jobs/' + order.id : '/client/orders/' + order.id, order.id);
  await recordAudit(store, req, { action: 'deliverable.revision.' + decision, resourceType: 'orders', resourceId: order.id, reason: revision.reason });
  res.json({ ok: true, revision: updated, status: decision === 'approved' ? 'work_in_progress' : order.status });
}));

export default router;
