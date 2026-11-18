import test from 'node:test';
import assert from 'node:assert/strict';
import router from '../routes/api.mjs';
import { allowedOrderFilter } from '../lib/legacy.mjs';
import { publicUser, roleSlug } from '../lib/auth.mjs';

const routeRows = router.stack
  .filter(layer => layer.route)
  .flatMap(layer => Object.keys(layer.route.methods).map(method => method.toUpperCase() + ' ' + layer.route.path));

test('role-specific workspace APIs remain registered', () => {
  for (const route of [
    'GET /workspace/search',
    'GET /corporate/overview',
    'PATCH /corporate/company',
    'POST /corporate/deadlines',
    'PATCH /corporate/deadlines/:id',
    'GET /auth/avatar',
    'POST /auth/avatar',
    'DELETE /auth/avatar',
    'GET /partner/orders/:id/messages',
    'POST /partner/orders/:id/messages',
    'GET /orders/:id/messages',
    'POST /orders/:id/messages'
  ]) assert.ok(routeRows.includes(route), 'Missing ' + route);
});

test('order scoping never elevates staff, client or partner to administrator', () => {
  assert.deepEqual(allowedOrderFilter({ role: { slug: 'client' }, user: { client_id: 10 } }), { client_id: 10 });
  assert.deepEqual(allowedOrderFilter({ role: { slug: 'executive' }, user: { id: 21 } }), { assigned_user_id: 21 });
  assert.ok(allowedOrderFilter({ role: { slug: 'partner' }, user: { id: 33, partner_id: 31 } }).$or.length > 0);
  assert.deepEqual(allowedOrderFilter({ role: { slug: 'admin' }, user: { id: 1 } }), {});
});

test('role selection remains bounded by assigned roles', () => {
  assert.equal(roleSlug({ role: { slug: 'client' } }), 'client');
  assert.equal(roleSlug({ roles: [{ slug: 'partner' }] }), 'partner');
  assert.equal(roleSlug({ roles: [{ slug: 'executive' }, { slug: 'client' }] }), 'executive');
});

test('public user serialization excludes private account credentials', () => {
  const person = publicUser({ id: 10, name: 'Example', email: 'a@example.org', password_hash: 'secret', avatar_stored_name: 'gridfs:secret' }, { id: 5, slug: 'client', name: 'Client' }, []);
  assert.equal(person.password_hash, undefined);
  assert.equal(person.avatar_stored_name, undefined);
});
