import test from 'node:test';
import assert from 'node:assert/strict';
import { resolveOrderPaymentChoice } from '../lib/order-payment.mjs';

test('UPI selection requires payment before processing a billable client job', () => {
  assert.deepEqual(resolveOrderPaymentChoice('upi', 8999, 'client'), {
    payment_choice: 'upi', payment_first: true, payment_method: 'upi',
    payment_status: 'unpaid', status: 'awaiting_payment'
  });
});

test('Pay Later allows submission but remains an unpaid deferred obligation', () => {
  assert.deepEqual(resolveOrderPaymentChoice('pay_later', 8999, 'client'), {
    payment_choice: 'pay_later', payment_first: false, payment_method: 'deferred',
    payment_status: 'deferred', status: 'submitted'
  });
});

test('partner pay later and UPI are processed consistently', () => {
  assert.equal(resolveOrderPaymentChoice('pay_later', 799, 'partner').status, 'submitted');
  assert.equal(resolveOrderPaymentChoice('upi', 799, 'partner').status, 'awaiting_payment');
});

test('free service never requires upfront UPI payment', () => {
  const value = resolveOrderPaymentChoice('upi', 0, 'client');
  assert.equal(value.payment_first, false);
  assert.equal(value.payment_status, 'not_due');
});

test('invalid payment choices cannot bypass validation', () => {
  assert.throws(() => resolveOrderPaymentChoice('cash', 500, 'client'), /Choose Pay with UPI/);
});

test('older API clients retain the previous default payment selection', () => {
  assert.equal(resolveOrderPaymentChoice(undefined, 999, 'client').payment_choice, 'upi');
  assert.equal(resolveOrderPaymentChoice(undefined, 999, 'partner').payment_choice, 'pay_later');
});
