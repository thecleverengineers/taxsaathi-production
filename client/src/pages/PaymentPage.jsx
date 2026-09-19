import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { api, assetUrl } from '../lib/api';
import Icon from '../components/Icons';
import { EmptyState, PageLoader } from '../components/Ui';
import { money } from '../lib/format';

export default function PaymentPage({ partner = false }) {
  const { id } = useParams();
  const navigate = useNavigate();
  const [order, setOrder] = useState(null);
  const [payment, setPayment] = useState(null);
  const [transactionId, setTransactionId] = useState('');
  const [file, setFile] = useState(null);
  const [notice, setNotice] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [razorpayLoading, setRazorpayLoading] = useState(false);
  const orderPath = partner ? `/partner/orders/${id}` : `/orders/${id}`;
  const proofPath = partner ? `/partner/orders/${id}/payment-proof` : `/orders/${id}/payment-proof`;
  const backPath = partner ? `/partner/orders/${id}` : `/client/orders/${id}`;

  useEffect(() => {
    Promise.all([api(orderPath), api('/payment-details')])
      .then(([orderPayload, paymentPayload]) => { setOrder(orderPayload.order); setPayment(paymentPayload.payment || {}); })
      .catch(() => { setOrder(false); setPayment({}); });
  }, [orderPath]);

  if (order === null) return <PageLoader label="Loading payment page…" />;
  if (!order) return <EmptyState title="Order not found." action={<Link className="button dark" to={partner ? '/partner/orders' : '/client/orders'}>Back to orders</Link>} />;

  const amount = Number(order.payable_amount || order.fee_amount || order.filing_fee || 0);
  const isComplete = ['paid', 'verified', 'approved'].includes(String(order.payment_status || '').toLowerCase());
  const qrCode = assetUrl(payment?.qr_code);

  async function copy(value, label) {
    if (!value) return;
    try { await navigator.clipboard.writeText(value); setNotice(`${label} copied.`); } catch { setNotice(`Copy ${label} manually: ${value}`); }
  }

  function downloadBankDetails() {
    const text = bankDetailsText();
    const url = URL.createObjectURL(new Blob([text], { type: 'text/plain;charset=utf-8' }));
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = `taxsaathi-bank-details-${order.order_no || order.id}.txt`;
    anchor.click();
    URL.revokeObjectURL(url);
  }

  function bankDetailsText() {
    return [
      'TaxSaathi Payment Details',
      `Account Name: ${payment.account_name || '—'}`,
      `Bank Name: ${payment.bank_name || '—'}`,
      `Account Number: ${payment.account_number || '—'}`,
      `IFSC Code: ${payment.ifsc_code || '—'}`,
      `Branch: ${payment.branch || '—'}`,
      `UPI ID: ${payment.upi_id || '—'}`
    ].join('\n');
  }

  async function submitProof(event) {
    event.preventDefault();
    setNotice('');
    if (!transactionId.trim() || !file) {
      setNotice('Enter the transaction ID and attach the payment screenshot.');
      return;
    }
    setSubmitting(true);
    const body = new FormData();
    body.append('transaction_id', transactionId.trim());
    body.append('payment_method', order.payment_method || 'upi');
    body.append('file', file);
    try {
      const result = await api(proofPath, { method: 'POST', body });
      setOrder(result.order);
      setNotice(result.message || 'Payment proof submitted for verification.');
      setFile(null);
      setTransactionId('');
    } catch (error) {
      setNotice(error.message);
    } finally {
      setSubmitting(false);
    }
  }

  async function payWithRazorpay() {
    setRazorpayLoading(true); setNotice('');
    try {
      const result = await api(`/orders/${id}/razorpay/order`, { method: 'POST' });
      if (!window.Razorpay) {
        await new Promise((resolve, reject) => { const script = document.createElement('script'); script.src = 'https://checkout.razorpay.com/v1/checkout.js'; script.onload = resolve; script.onerror = reject; document.body.appendChild(script); });
      }
      const checkout = new window.Razorpay({ key: result.key_id, amount: Math.round(result.amount * 100), currency: result.currency, name: 'TaxSaathi', description: order.service?.title || 'Tax service', order_id: result.order.id, prefill: { name: order.client?.name || '', email: order.client?.email || '', contact: order.client?.phone || '' }, theme: { color: '#0b3c91' }, handler: async (paymentResponse) => { try { const verified = await api(`/orders/${id}/razorpay/verify`, { method: 'POST', body: paymentResponse }); setOrder(verified.order); setNotice(verified.message || 'Payment verified successfully.'); } catch (error) { setNotice(error.message); } } });
      checkout.open();
    } catch (error) { setNotice(error.message); } finally { setRazorpayLoading(false); }
  }

  return <main className="payment-page"><div className="container-wide payment-page-inner">
    <div className="detail-back-row"><button className="back-button" onClick={() => navigate(backPath)}><Icon name="chevronLeft" size={16} /> Back to order</button><span>{order.order_no || `Order #${order.id}`}</span></div>
    <div className="payment-page-heading"><div><span className="eyebrow">Secure payment</span><h1>Complete your payment</h1><p>Pay the amount shown below using the payment details updated by TaxSaathi, then submit your transaction proof for verification.</p></div><div className="payment-amount-badge"><small>Payable amount</small><strong>{money(amount)}</strong></div></div>
    {notice && <div className={`form-message ${notice.toLowerCase().includes('submitted') || notice.toLowerCase().includes('copied') ? 'success' : 'danger'}`}>{notice}</div>}
    {isComplete ? <div className="payment-complete-card"><span className="payment-complete-icon"><Icon name="check" size={24} /></span><h2>Payment already recorded</h2><p>This order has already reached the payment verification stage.</p><Link className="button dark" to={backPath}>Return to order</Link></div> : <div className="payment-layout">
      <section className="payment-method-card"><div className="payment-card-heading"><div><span className="eyebrow">Step 1</span><h2>Pay TaxSaathi</h2></div><span className="payment-status-pill">{order.payment_status || 'Payment pending'}</span></div>
        <div className="payment-qr-layout"><div className="payment-qr-box">{qrCode ? <img src={qrCode} alt="TaxSaathi payment QR code" /> : <div className="payment-qr-empty"><Icon name="qr" size={34} /><span>QR code will appear here after admin setup.</span></div>}</div><div className="payment-upi-copy"><span className="payment-small-title">UPI PAYMENT</span><strong>{payment.upi_id || 'UPI ID not configured'}</strong><button className="button light small" type="button" onClick={() => copy(payment.upi_id, 'UPI ID')} disabled={!payment.upi_id}><Icon name="copy" size={14} /> Copy UPI ID</button>{payment.upi_id && <a className="button primary small" href={`upi://pay?pa=${encodeURIComponent(payment.upi_id)}&pn=TaxSaathi&am=${encodeURIComponent(amount)}&cu=INR`}><Icon name="wallet" size={14} /> Open UPI app</a>}{!partner && payment.razorpay_enabled && <button className="button dark small" type="button" onClick={payWithRazorpay} disabled={razorpayLoading}><Icon name="wallet" size={14} /> {razorpayLoading ? 'Opening…' : 'Pay securely online'}</button>}</div></div>
        <div className="payment-bank-card"><div className="payment-card-heading"><div><span className="eyebrow">Bank transfer</span><h3>Pay using bank details</h3></div><div className="payment-bank-actions"><button className="button light small" type="button" onClick={() => copy(bankDetailsText(), 'bank details')}><Icon name="copy" size={14} /> Copy bank details</button><button className="button light small" type="button" onClick={downloadBankDetails}><Icon name="download" size={14} /> Download details</button></div></div><div className="payment-bank-grid"><div><small>Account name</small><strong>{payment.account_name || '—'}</strong></div><div><small>Bank name</small><strong>{payment.bank_name || '—'}</strong></div><div><small>Account number</small><strong>{payment.account_number || '—'}</strong><button type="button" onClick={() => copy(payment.account_number, 'account number')}>Copy</button></div><div><small>IFSC code</small><strong>{payment.ifsc_code || '—'}</strong><button type="button" onClick={() => copy(payment.ifsc_code, 'IFSC code')}>Copy</button></div><div><small>Branch</small><strong>{payment.branch || '—'}</strong></div></div>{payment.instructions && <p className="payment-instructions">{payment.instructions}</p>}</div>
      </section>
      <form className="payment-proof-card" onSubmit={submitProof}><div className="payment-card-heading"><div><span className="eyebrow">Step 2</span><h2>Submit payment proof</h2></div><span className="payment-proof-required">Required</span></div><p>After paying, enter the transaction ID and upload the screenshot. Admin will verify the payment before work begins.</p><label>Transaction ID<input required value={transactionId} onChange={(event) => setTransactionId(event.target.value)} placeholder="Enter UPI or bank transaction ID" /></label><label>Payment screenshot<span className="payment-file-picker"><Icon name="upload" size={16} /><span>{file?.name || 'Choose screenshot'}</span><input required type="file" accept="image/*,.pdf" onChange={(event) => setFile(event.target.files?.[0] || null)} /></span></label><button className="button primary full" type="submit" disabled={submitting}>{submitting ? 'Submitting…' : 'Submit payment proof'} <Icon name="arrow" size={16} /></button><small className="form-footnote">Only submit proof after the payment has been completed. Your receipt will be attached to {order.order_no || 'this order'}.</small></form>
    </div>}
  </div></main>;
}
