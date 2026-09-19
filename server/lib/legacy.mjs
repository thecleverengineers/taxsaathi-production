export const roleNames = ['Administrator', 'Manager', 'Executive', 'Partners', 'Client'];

export function parseJson(value, fallback = null) {
  if (value && typeof value === 'object') return value;
  if (typeof value !== 'string' || value.trim() === '') return fallback;
  try {
    return JSON.parse(value);
  } catch {
    try {
      return JSON.parse(value.replaceAll('\\"', '"'));
    } catch {
      return fallback;
    }
  }
}

export function asBool(value) {
  return value === true || value === 1 || value === '1' || value === 'true';
}

export function asNumber(value, fallback = 0) {
  const number = Number(value);
  return Number.isFinite(number) ? number : fallback;
}

export function money(value) {
  return `₹${asNumber(value).toLocaleString('en-IN', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2
  })}`;
}

export function statusLabel(value) {
  return String(value || 'pending')
    .replaceAll('_', ' ')
    .replace(/\b\w/g, (character) => character.toUpperCase());
}

export function timeAgo(value) {
  const date = new Date(String(value || '').replace(' ', 'T'));
  if (Number.isNaN(date.getTime())) return 'Recently';
  const seconds = Math.max(0, Math.floor((Date.now() - date.getTime()) / 1000));
  if (seconds < 60) return 'Just now';
  const minutes = Math.floor(seconds / 60);
  if (minutes < 60) return `${minutes}m ago`;
  const hours = Math.floor(minutes / 60);
  if (hours < 24) return `${hours}h ago`;
  const days = Math.floor(hours / 24);
  if (days < 7) return `${days}d ago`;
  return date.toLocaleDateString('en-IN');
}

export function isoNow() {
  return new Date().toISOString().slice(0, 19).replace('T', ' ');
}

export function slugify(value) {
  return String(value || '')
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');
}

export function assetUrl(value) {
  const path = String(value || '').trim();
  if (!path) return '';
  if (/^(https?:)?\/\//i.test(path) || path.startsWith('data:')) return path;
  if (path.startsWith('/uploads/')) return `/legacy-assets${path}`;
  if (path.startsWith('uploads/')) return `/legacy-assets/${path}`;
  if (path.startsWith('/storage/')) return `/legacy-assets${path}`;
  return path.startsWith('/') ? path : `/${path}`;
}

export async function settingsMap(store) {
  const rows = await store.find('website_settings', {}, { sort: { id: 1 } });
  return Object.fromEntries(rows.map((row) => [row.setting_key, row.setting_value]));
}

export async function enrichServices(store, services) {
  const categories = await store.find('service_categories', {}, { sort: { sort_order: 1, id: 1 } });
  const categoryById = new Map(categories.map((category) => [Number(category.id), category]));
  return services.map((service) => {
    const category = categoryById.get(Number(service.service_category_id));
    return {
      ...service,
      id: Number(service.id),
      filing_fee: asNumber(service.filing_fee),
      is_active: asBool(service.is_active),
      is_featured: asBool(service.is_featured),
      category: category
        ? { id: category.id, title: category.title, slug: category.slug, image: assetUrl(category.image) }
        : {
            id: service.service_category_id,
            title: service.service_category || 'Services',
            slug: slugify(service.service_category || 'services')
          },
      icon_url: assetUrl(service.icon)
    };
  });
}

export async function servicePayload(store, service) {
  if (!service) return null;
  const [all, benefits, types, requirements, reviews, banners] = await Promise.all([
    enrichServices(store, [service]),
    store.find('service_benefits', { service_id: Number(service.id) }, { sort: { sort_order: 1, id: 1 } }),
    store.find('service_types', { service_id: Number(service.id) }, { sort: { sort_order: 1, id: 1 } }),
    store.find('service_requirements', { service_id: Number(service.id) }, { sort: { sort_order: 1, id: 1 } }),
    store.find('service_reviews', { service_id: Number(service.id), is_active: 1 }, { sort: { sort_order: 1, id: 1 } }),
    store.find('service_banners', { service_id: Number(service.id), is_active: 1 }, { sort: { sort_order: 1, id: 1 } })
  ]);
  return {
    ...all[0],
    benefits,
    types,
    requirements,
    reviews,
    banners: banners.map((banner) => ({ ...banner, image_url: assetUrl(banner.image) }))
  };
}

export async function userWithRole(store, user) {
  if (!user) return null;
  const role = user.role_id ? await store.findOne('roles', { id: Number(user.role_id) }) : null;
  return { user, role };
}

export async function resolveOrder(store, order) {
  if (!order) return null;
  const [service, client, assignee, documents, invoice, payments] = await Promise.all([
    store.findOne('services', { id: Number(order.service_id) }),
    store.findOne('clients', { id: Number(order.client_id) }),
    order.assigned_user_id ? store.findOne('users', { id: Number(order.assigned_user_id) }) : null,
    store.find('order_documents', { order_id: Number(order.id) }, { sort: { id: 1 } }),
    store.findOne('invoices', { order_id: Number(order.id) }),
    store.find('payments', { order_id: Number(order.id) }, { sort: { id: -1 } })
  ]);
  return {
    ...order,
    fee_amount: asNumber(order.fee_amount),
    gross_fee_amount: asNumber(order.gross_fee_amount || order.fee_amount),
    payable_amount: asNumber(order.payable_amount || order.fee_amount),
    service: service ? { id: service.id, title: service.title, slug: service.slug } : null,
    client: client
      ? { id: client.id, name: client.name, phone: client.phone, email: client.email, company_name: client.company_name }
      : null,
    assignee: assignee ? { id: assignee.id, name: assignee.name, email: assignee.email } : null,
    documents: documents.map((document) => ({
      ...document,
      download_url: document.id ? `/api/orders/${order.id}/documents/${document.id}` : null
    })),
    payment_proof_url: order.payment_proof ? `/api/orders/${order.id}/payment-proof` : null,
    invoice,
    payments
  };
}

export function allowedOrderFilter(auth) {
  const role = String(auth?.role?.slug || '').toLowerCase();
  const user = auth?.user || {};
  if (role === 'admin' || role === 'manager') return {};
  if (role === 'client') return { client_id: Number(user.client_id || 0) };
  if (role === 'executive') return { assigned_user_id: Number(user.id) };
  if (role === 'partners' || role === 'partner') {
    const ids = [...new Set([user.id, user.partner_id]
      .map(Number)
      .filter((value) => Number.isFinite(value) && value > 0))];
    return { partner_id: { $in: ids.length ? ids : [0] } };
  }
  return { client_id: Number(user.client_id || 0) };
}

export async function nextOrderNo(store, prefix = 'TSO') {
  const orders = await store.find('orders', {}, { sort: { id: -1 }, limit: 1 });
  const last = Number(orders[0]?.id || 0) + 1;
  return `${prefix}-${new Date().getFullYear()}-${String(last).padStart(5, '0')}`;
}

export function safeUser(user) {
  if (!user) return null;
  const { password_hash: _password, ...safe } = user;
  return safe;
}
