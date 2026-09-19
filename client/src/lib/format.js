export function money(value) {
  return `₹${Number(value || 0).toLocaleString('en-IN', { maximumFractionDigits: 2 })}`;
}

export function phoneHref(value) {
  const phone = String(value || '').trim();
  return phone ? `tel:${phone.replace(/[^\d+]/g, '')}` : '';
}

export function whatsappHref(value, message = '') {
  let digits = String(value || '').replace(/\D/g, '');
  if (digits.length === 10) digits = `91${digits}`;
  if (digits.length === 11 && digits.startsWith('0')) digits = `91${digits.slice(1)}`;
  if (!digits) return '';
  return `https://wa.me/${digits}${message ? `?text=${encodeURIComponent(message)}` : ''}`;
}

export function dateLabel(value) {
  if (!value) return '—';
  const date = new Date(String(value).replace(' ', 'T'));
  return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}

export function statusLabel(value) {
  return String(value || 'pending').replaceAll('_', ' ').replace(/\b\w/g, (character) => character.toUpperCase());
}

export function statusTone(value) {
  const key = String(value || '').toLowerCase();
  if (['completed', 'approved', 'verified', 'paid', 'success'].includes(key)) return 'success';
  if (['rejected', 'cancelled', 'failed', 'unpaid'].includes(key)) return 'danger';
  if (['work_in_progress', 'pending_review', 'pending', 'submitted', 'partial'].includes(key)) return 'warning';
  return 'neutral';
}
