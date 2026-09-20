import test from 'node:test';
import assert from 'node:assert/strict';
import { matches } from '../lib/store.mjs';
import { allowedOrderFilter, slugify } from '../lib/legacy.mjs';
import { effectivePermissions, hasPermission } from '../lib/rbac.mjs';

test('snapshot matcher supports legacy query operators', () => {
  const row = { id: 7, status: 'approved', amount: 1250, title: 'Company Incorporation' };
  assert.equal(matches(row, { status: 'approved' }), true);
  assert.equal(matches(row, { id: { $in: [6, 7] } }), true);
  assert.equal(matches(row, { amount: { $gte: 1000, $lt: 2000 } }), true);
  assert.equal(matches(row, { title: { $regex: 'incorporation' } }), true);
  assert.equal(matches(row, { status: { $ne: 'rejected' } }), true);
});

test('slugify produces stable public service paths', () => {
  assert.equal(slugify('  GST & Tax Filing  '), 'gst-tax-filing');
});

test('order visibility stays scoped by role', () => {
  assert.deepEqual(allowedOrderFilter({ role: { slug: 'client' }, user: { client_id: 12 } }), { client_id: 12 });
  assert.deepEqual(allowedOrderFilter({ role: { slug: 'executive' }, user: { id: 29 } }), { assigned_user_id: 29 });
  assert.deepEqual(allowedOrderFilter({ role: { slug: 'admin' }, user: { id: 1 } }), {});
});

test('permission evaluation supports configured role permissions and namespaces', () => {
  const auth = { roles: [{ slug: 'executive', permissions_json: '["cases.view","documents.*"]' }] };
  assert.equal(hasPermission(auth, 'cases.view'), true);
  assert.equal(hasPermission(auth, 'documents.verify'), true);
  assert.equal(hasPermission(auth, 'billing.refund'), false);
  assert.deepEqual(effectivePermissions(auth.roles), ['cases.view', 'documents.*']);
});

test('administrators retain full permission access', () => {
  assert.equal(hasPermission({ roles: [{ slug: 'admin' }] }, 'security.manage'), true);
});
