export const permissionCatalog = [
  ['users.view', 'View users', 'Users'],
  ['users.create', 'Create users', 'Users'],
  ['users.edit', 'Edit users', 'Users'],
  ['users.disable', 'Activate or deactivate users', 'Users'],
  ['users.roles.manage', 'Manage user roles', 'Users'],
  ['corporates.view', 'View corporate accounts', 'Corporates'],
  ['corporates.manage', 'Manage corporate accounts', 'Corporates'],
  ['cases.view', 'View service cases', 'Cases'],
  ['cases.create', 'Create service cases', 'Cases'],
  ['cases.assign', 'Assign cases', 'Cases'],
  ['cases.edit', 'Edit case workflow', 'Cases'],
  ['cases.approve', 'Approve cases', 'Cases'],
  ['cases.close', 'Close cases', 'Cases'],
  ['documents.view', 'View authorized documents', 'Documents'],
  ['documents.upload', 'Upload documents', 'Documents'],
  ['documents.verify', 'Verify documents', 'Documents'],
  ['documents.delete', 'Delete documents', 'Documents'],
  ['billing.view', 'View billing records', 'Billing'],
  ['billing.create', 'Create billing records', 'Billing'],
  ['billing.refund', 'Process refunds', 'Billing'],
  ['payments.view', 'View payments', 'Payments'],
  ['payments.manage', 'Manage payment status', 'Payments'],
  ['services.view', 'View services', 'Services'],
  ['services.manage', 'Manage services', 'Services'],
  ['reports.view', 'View reports', 'Reports'],
  ['reports.export', 'Export reports', 'Reports'],
  ['tasks.view', 'View tasks', 'Tasks'],
  ['tasks.manage', 'Manage tasks', 'Tasks'],
  ['notifications.view', 'View notifications', 'Notifications'],
  ['notifications.manage', 'Manage notifications', 'Notifications'],
  ['support.view', 'View support conversations', 'Support'],
  ['support.manage', 'Manage support conversations', 'Support'],
  ['audit.view', 'View audit history', 'Security'],
  ['security.manage', 'Manage security settings', 'Security'],
  ['settings.manage', 'Manage platform settings', 'Settings']
].map(([permission_key, label, module]) => ({ permission_key, label, module }));

const defaults = {
  admin: ['*'],
  manager: [
    'users.view', 'corporates.*', 'cases.*', 'documents.*', 'billing.view', 'payments.*',
    'services.*', 'reports.*', 'tasks.*', 'notifications.*', 'support.*', 'audit.view', 'settings.manage'
  ],
  executive: [
    'corporates.view', 'cases.view', 'cases.edit', 'documents.view', 'documents.upload',
    'documents.verify', 'payments.view', 'reports.view', 'tasks.*', 'notifications.view', 'support.*'
  ],
  partners: ['services.view', 'cases.view', 'documents.view', 'payments.view', 'tasks.*', 'support.*', 'notifications.view'],
  partner: ['services.view', 'cases.view', 'documents.view', 'payments.view', 'tasks.*', 'support.*', 'notifications.view'],
  client: ['services.view', 'cases.view', 'documents.view', 'documents.upload', 'payments.view', 'support.*', 'notifications.view']
};

function parsePermissionList(value) {
  if (Array.isArray(value)) return value;
  if (value && typeof value === 'object') return value.permissions || value.permission_keys || [];
  const raw = String(value || '').trim();
  if (!raw) return [];
  try {
    const parsed = JSON.parse(raw);
    return Array.isArray(parsed) ? parsed : parsePermissionList(parsed);
  } catch {
    return raw.split(',');
  }
}

export function rolePermissions(role) {
  const configured = parsePermissionList(role?.permissions_json ?? role?.permissions);
  if (configured.length) return configured.map((item) => String(item).trim()).filter(Boolean);
  return defaults[String(role?.slug || '').toLowerCase()] || [];
}

export function effectivePermissions(roles = []) {
  return [...new Set(roles.flatMap((role) => rolePermissions(role)))].sort();
}

export function hasPermission(auth, permission) {
  const requested = String(permission || '').trim().toLowerCase();
  if (!requested) return false;
  const roles = (auth?.roles?.length ? auth.roles : [auth?.role]).filter(Boolean);
  if (roles.some((role) => String(role.slug || '').toLowerCase() === 'admin')) return true;
  const permissions = auth?.permissions || effectivePermissions(roles);
  return permissions.includes('*') || permissions.includes(requested) || permissions.some((item) => item.endsWith('.*') && requested.startsWith(`${item.slice(0, -1)}`));
}

export function allowPermissions(...required) {
  const permissions = required.flat().map((item) => String(item).trim()).filter(Boolean);
  return (request, response, next) => {
    if (permissions.some((permission) => hasPermission(request.auth, permission))) return next();
    return response.status(403).json({ ok: false, message: 'You do not have the required permission for this action.' });
  };
}

export function permissionCatalogRows() {
  return permissionCatalog.map((item) => ({ ...item }));
}
