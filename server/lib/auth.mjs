import jwt from 'jsonwebtoken';

function jwtSecret() {
  const value = String(process.env.JWT_SECRET || '').trim();
  if (process.env.NODE_ENV === 'production' && value.length < 32) {
    throw new Error('JWT_SECRET must be configured with at least 32 characters in production.');
  }
  return value || 'taxsaathi-development-secret-change-me';
}

function bearerToken(request) {
  const header = request.headers.authorization || '';
  if (header.startsWith('Bearer ')) return header.slice(7);
  return request.cookies?.taxsaathi_token || null;
}

export function publicUser(user, role = null, roles = []) {
  if (!user) return null;
  return {
    id: user.id,
    name: user.name,
    phone: user.phone,
    email: user.email,
    client_id: user.client_id ?? null,
    partner_id: user.partner_id ?? null,
    role_id: user.role_id ?? null,
    role: role
      ? { id: role.id, name: role.name, slug: role.slug }
      : null,
    roles: roles.map((item) => ({ id: item.id, name: item.name, slug: item.slug })),
    is_active: user.is_active
  };
}

export function signUser(user) {
  return jwt.sign(
    {
      sub: String(user.id),
      legacy_id: Number(user.id),
      role_id: user.role_id ?? null,
      client_id: user.client_id ?? null,
      partner_id: user.partner_id ?? null
    },
    jwtSecret(),
    { expiresIn: process.env.JWT_EXPIRES_IN || '7d' }
  );
}

export function authRequired(store) {
  return async (request, response, next) => {
    const dataStore = typeof store === 'function' ? store(request) : store;
    const token = bearerToken(request);
    if (!token) return response.status(401).json({ ok: false, message: 'Authentication required.' });

    try {
      const payload = jwt.verify(token, jwtSecret());
      const user = await dataStore.findOne('users', { id: Number(payload.legacy_id || payload.sub) });
      if (!user || Number(user.is_active) === 0) {
        return response.status(401).json({ ok: false, message: 'Your account is inactive or no longer exists.' });
      }
      const assignments = await dataStore.find('user_roles', { user_id: Number(user.id) });
      const roleIds = [...new Set([user.role_id, ...assignments.map((item) => item.role_id)].map(Number).filter(Number.isFinite))];
      const roles = [];
      for (const roleId of roleIds) {
        const role = await dataStore.findOne('roles', { id: roleId });
        if (role) roles.push(role);
      }
      const role = roles.find((item) => Number(item.id) === Number(user.role_id)) || roles[0] || null;
      request.auth = { user, role, roles, payload };
      return next();
    } catch (error) {
      return response.status(401).json({ ok: false, message: 'Your session has expired. Please sign in again.' });
    }
  };
}

export function optionalAuth(store) {
  return async (request, response, next) => {
    const dataStore = typeof store === 'function' ? store(request) : store;
    const token = bearerToken(request);
    if (!token) return next();
    try {
      const payload = jwt.verify(token, jwtSecret());
      const user = await dataStore.findOne('users', { id: Number(payload.legacy_id || payload.sub) });
      if (user) {
        const assignments = await dataStore.find('user_roles', { user_id: Number(user.id) });
        const roleIds = [...new Set([user.role_id, ...assignments.map((item) => item.role_id)].map(Number).filter(Number.isFinite))];
        const roles = [];
        for (const roleId of roleIds) {
          const role = await dataStore.findOne('roles', { id: roleId });
          if (role) roles.push(role);
        }
        const role = roles.find((item) => Number(item.id) === Number(user.role_id)) || roles[0] || null;
        request.auth = { user, role, roles, payload };
      }
    } catch {
      // Public endpoints should stay public when an old/expired token is present.
    }
    return next();
  };
}

export function allowRoles(...slugs) {
  const accepted = new Set(slugs.flat().map((slug) => String(slug).toLowerCase()));
  return (request, response, next) => {
    const roles = (request.auth?.roles?.length ? request.auth.roles : [request.auth?.role]).filter(Boolean);
    if (roles.some((role) => accepted.has(String(role.slug || '').toLowerCase()) || String(role.slug || '').toLowerCase() === 'admin')) return next();
    return response.status(403).json({ ok: false, message: 'You do not have permission for this action.' });
  };
}

export function roleSlug(auth) {
  const roles = (auth?.roles?.length ? auth.roles : [auth?.role]).filter(Boolean).map((role) => String(role.slug || '').toLowerCase());
  const priority = ['admin', 'manager', 'executive', 'partner', 'partners', 'client'];
  return priority.find((slug) => roles.includes(slug)) || roles[0] || 'client';
}

export function isStaff(auth) {
  return ['admin', 'manager', 'executive'].includes(roleSlug(auth));
}

export function setTokenCookie(response, token) {
  response.cookie('taxsaathi_token', token, {
    httpOnly: true,
    sameSite: 'lax',
    secure: String(process.env.COOKIE_SECURE).toLowerCase() === 'true',
    ...(process.env.COOKIE_DOMAIN ? { domain: process.env.COOKIE_DOMAIN } : {}),
    path: '/',
    maxAge: 7 * 24 * 60 * 60 * 1000
  });
}

export function clearTokenCookie(response) {
  response.clearCookie('taxsaathi_token');
}
