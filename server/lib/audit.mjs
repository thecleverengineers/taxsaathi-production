const blockedKeys = /password|token|secret|api[_-]?key|private[_-]?key|authorization/i;

function safeValue(value) {
  if (value === undefined || value === null) return value ?? null;
  if (typeof value !== 'object') return String(value).length > 500 ? `${String(value).slice(0, 500)}…` : value;
  if (Array.isArray(value)) return value.slice(0, 50).map(safeValue);
  return Object.fromEntries(Object.entries(value).filter(([key]) => !blockedKeys.test(key)).slice(0, 100).map(([key, item]) => [key, safeValue(item)]));
}

export async function recordAudit(store, request, details = {}) {
  const actor = request?.auth?.user || null;
  const role = request?.auth?.role || null;
  return store.insert('audit_logs', {
    actor_user_id: details.actorUserId ?? (actor?.id ? Number(actor.id) : null),
    actor_role: details.actorRole || role?.slug || null,
    organization_id: actor?.organization_id || actor?.organizationId || null,
    action: String(details.action || 'unknown'),
    resource_type: String(details.resourceType || details.resource_type || 'system'),
    resource_id: details.resourceId ?? details.resource_id ?? null,
    previous_value: safeValue(details.previousValue ?? details.previous_value ?? null),
    new_value: safeValue(details.newValue ?? details.new_value ?? null),
    reason: details.reason ? String(details.reason).slice(0, 500) : null,
    ip_address: request?.ip || request?.headers?.['x-forwarded-for'] || null,
    user_agent: String(request?.headers?.['user-agent'] || '').slice(0, 500) || null,
    created_at: new Date().toISOString()
  });
}
