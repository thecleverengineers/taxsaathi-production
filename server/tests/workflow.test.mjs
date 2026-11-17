import test from 'node:test';
import assert from 'node:assert/strict';
import { validatePlan } from '../routes/workflow.mjs';
import { allowedOrderFilter } from '../lib/legacy.mjs';

test('workflow planning accepts only supported priorities', () => {
  for (const priority of ['low', 'normal', 'high', 'urgent']) {
    assert.equal(validatePlan({ priority }).priority, priority);
  }
  assert.throws(() => validatePlan({ priority: 'superuser' }), /Priority must/);
});

test('workflow deadline validation accepts valid dates and clearable deadlines', () => {
  assert.equal(validatePlan({ due_date: '2026-11-25' }).due_date, '2026-11-25');
  assert.equal(validatePlan({ due_date: null }).due_date, null);
  assert.throws(() => validatePlan({ due_date: '2026-02-31' }), /Deadline must/);
  assert.throws(() => validatePlan({ due_date: 'tomorrow' }), /Deadline must/);
});

test('planning ignores non-allowlisted fields including assigned user and private notes', () => {
  const result = validatePlan({ priority: 'urgent', assigned_user_id: 1, status: 'completed', internal_notes: 'secret' });
  assert.deepEqual(result, { priority: 'urgent' });
});

test('workflow order filter restricts customer, staff and partner visibility', () => {
  assert.deepEqual(allowedOrderFilter({ role: { slug: 'client' }, user: { client_id: 7 } }), { client_id: 7 });
  assert.deepEqual(allowedOrderFilter({ role: { slug: 'executive' }, user: { id: 13 } }), { assigned_user_id: 13 });
  const partner = allowedOrderFilter({ role: { slug: 'partner' }, user: { id: 19, partner_id: 4 } });
  assert.equal(partner.$or.length, 2);
  assert.deepEqual(partner.$or[0], { partner_id: { $in: [4, 19] } });
  assert.deepEqual(partner.$or[1], { assigned_user_id: 19 });
});
