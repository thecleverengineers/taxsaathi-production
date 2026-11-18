/**
 * Order checkout semantics shared by the API and tests.
 * Selecting Pay Later is a deferred payment obligation, not a completed payment.
 * Existing API consumers without payment_choice retain the former defaults.
 */
export function resolveOrderPaymentChoice(choice, fee, role = 'client') {
  const normalizedRole = String(role || '').toLowerCase();
  const selected = String(choice || (['partner', 'partners'].includes(normalizedRole) ? 'pay_later' : 'upi')).trim().toLowerCase();
  if (!['upi', 'pay_later'].includes(selected)) {
    throw new Error('Choose Pay with UPI or Pay Later before continuing.');
  }
  const amount = Number(fee) || 0;
  const paymentFirst = selected === 'upi' && amount > 0 && !['admin', 'manager'].includes(normalizedRole);
  const deferred = selected === 'pay_later' && amount > 0;
  return {
    payment_choice: selected,
    payment_first: paymentFirst,
    payment_method: deferred ? 'deferred' : 'upi',
    payment_status: deferred ? 'deferred' : paymentFirst ? 'unpaid' : 'not_due',
    status: paymentFirst ? 'awaiting_payment' : 'submitted'
  };
}
