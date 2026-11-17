import { useEffect, useState } from 'react';
import { Link, useLocation, useNavigate, useParams } from 'react-router-dom';
import { api, downloadBlob } from '../lib/api';
import { useAuth } from '../components/AuthContext';
import Icon from '../components/Icons';
import { EmptyState, PageLoader, StatusBadge } from '../components/Ui';
import { dateLabel, money } from '../lib/format';

function completedPayment(value) {
  return ['paid', 'verified', 'approved'].includes(String(value || '').toLowerCase());
}

export default function OrderDetailPage({ partner = false, job = false }) {
  const { id } = useParams();
  const { user } = useAuth();
  const location = useLocation();
  const navigate = useNavigate();
  const [order, setOrder] = useState(null);
  const [status, setStatus] = useState('');
  const [couponCode, setCouponCode] = useState('');
  const [couponLoading, setCouponLoading] = useState(false);
  const [uploading, setUploading] = useState(false);
  const [outputUploading, setOutputUploading] = useState(false);
  const [reviewStatus, setReviewStatus] = useState('verified');
  const [reviewing, setReviewing] = useState(false);
  const [assignees, setAssignees] = useState([]);
  const [assignedUserId, setAssignedUserId] = useState('');
  const [assignmentSaving, setAssignmentSaving] = useState(false);
  const [approvalSaving, setApprovalSaving] = useState(false);
  const [messages, setMessages] = useState([]);
  const [messageText, setMessageText] = useState('');
  const [messageSaving, setMessageSaving] = useState(false);
  const [notice, setNotice] = useState(location.state?.message || '');
  const userRoles = [user?.role?.slug, ...(user?.roles || []).map((role) => role.slug)];
  const role = String(user?.role?.slug || '').toLowerCase();
  const isStaff = userRoles.some((item) => ['admin', 'manager', 'executive'].includes(item));
  const canReview = ['admin', 'manager'].includes(role);
  const orderPath = job ? `/orders/${id}` : partner ? `/partner/orders/${id}` : `/orders/${id}`;
  const listPath = job ? '/partner/jobs' : partner ? '/partner/orders' : isStaff ? '/admin/orders' : '/client/orders';

  useEffect(() => {
    api(orderPath).then((payload) => { setOrder(payload.order); setStatus(payload.order.status || payload.order.order_status || 'submitted'); setAssignedUserId(payload.order.assigned_user_id || ''); }).catch(() => setOrder(false));
  }, [orderPath]);

  useEffect(() => {
    if (partner) return;
    api(`/orders/${id}/messages`).then((payload) => setMessages(payload.messages || [])).catch(() => setMessages([]));
  }, [id, partner]);

  useEffect(() => {
    if (canReview) api('/admin/assignees').then((payload) => setAssignees(payload.assignees || [])).catch(() => setAssignees([]));
  }, [canReview]);

  if (order === null) return <PageLoader label="Loading order…" />;
  if (!order) return <EmptyState title="Order not found." action={<Link className="button dark" to={listPath}>Back to orders</Link>} />;

  const fee = Number(order.fee_amount || order.filing_fee || 0);
  const discount = Number(order.coupon_discount_amount || 0);
  const payable = Number(order.payable_amount || fee);
  const paymentStatus = String(order.payment_status || 'pending').toLowerCase();
  const paymentProofUrl = partner ? `/api/partner/orders/${id}/payment-proof` : `/api/orders/${id}/payment-proof`;
  const canWork = Boolean(order && (canReview || Number(order.assigned_user_id) === Number(user?.id)));
  const canPay = Boolean(order && (Number(order.submitted_by_user_id) === Number(user?.id) || role === 'client' && Number(order.client_id) === Number(user?.client_id) || ['partner', 'partners'].includes(role) && Number(order.partner_id) === Number(user?.partner_id || user?.id)));
  const visibleDocuments = (order.documents || []).filter((document) => isStaff || document.source !== 'staff' || Boolean(document.is_client_visible));
  const noticeIsSuccess = /updated|uploaded|submitted|applied|copied|recorded|created|saved/i.test(notice);

  async function updateStatus() {
    try { const result = await api(`/orders/${id}/status`, { method: 'POST', body: { status } }); setOrder(result.order); setNotice('Job status updated.'); } catch (error) { setNotice(error.message); }
  }

  async function assignJob() {
    if (!assignedUserId) return;
    setAssignmentSaving(true);
    try { const result = await api(`/orders/${id}/assign`, { method: 'POST', body: { assigned_user_id: Number(assignedUserId) } }); setOrder(result.order); setStatus(result.order.status); setNotice('Job assigned.'); } catch (error) { setNotice(error.message); } finally { setAssignmentSaving(false); }
  }

  async function approveDeliverable() {
    setApprovalSaving(true);
    try { const result = await api(`/orders/${id}/approve`, { method: 'POST' }); setOrder(result.order); setStatus(result.order.status); setNotice('Deliverable approved and released to the customer.'); } catch (error) { setNotice(error.message); } finally { setApprovalSaving(false); }
  }

  async function sendMessage(event) {
    event.preventDefault();
    if (!messageText.trim()) return;
    setMessageSaving(true);
    try { const result = await api(`/orders/${id}/messages`, { method: 'POST', body: { message: messageText.trim() } }); setMessages((current) => [...current, result.message]); setMessageText(''); } catch (error) { setNotice(error.message); } finally { setMessageSaving(false); }
  }

  async function choosePayLater() {
    try { const result = await api(`/orders/${id}/pay-later`, { method: 'POST' }); setOrder(result.order); setNotice(result.message || 'Payment marked for later.'); } catch (error) { setNotice(error.message); }
  }

  async function uploadFile(event) {
    const file = event.target.files?.[0];
    if (!file) return;
    setUploading(true);
    const body = new FormData(); body.append('file', file); body.append('label', file.name);
    try { await api(`/orders/${id}/documents`, { method: 'POST', body }); const result = await api(`/orders/${id}`); setOrder(result.order); setNotice('Document uploaded.'); } catch (error) { setNotice(error.message); } finally { setUploading(false); event.target.value = ''; }
  }

  async function uploadOutput(event) {
    const file = event.target.files?.[0];
    if (!file) return;
    setOutputUploading(true);
    const body = new FormData(); body.append('file', file); body.append('label', file.name); body.append('complete', 'true');
    try { const result = await api(`/orders/${id}/output`, { method: 'POST', body }); setOrder(result.order); setNotice('Deliverable submitted for admin approval.'); } catch (error) { setNotice(error.message); } finally { setOutputUploading(false); event.target.value = ''; }
  }

  async function markDocument(document, nextStatus) {
    const reason = nextStatus === 'wrong' ? window.prompt('Why does this document need correction?', document.wrong_reason || '') : '';
    if (nextStatus === 'wrong' && reason === null) return;
    try { await api(`/orders/${id}/documents/${document.id}/status`, { method: 'POST', body: { status: nextStatus, reason } }); const result = await api(`/orders/${id}`); setOrder(result.order); setNotice('Document status updated.'); } catch (error) { setNotice(error.message); }
  }

  async function reuploadDocument(document, event) {
    const file = event.target.files?.[0];
    if (!file) return;
    const body = new FormData(); body.append('file', file); body.append('label', document.label || file.name);
    try { await api(`/orders/${id}/documents/${document.id}/reupload`, { method: 'POST', body }); const result = await api(`/orders/${id}`); setOrder(result.order); setNotice('Replacement document uploaded.'); } catch (error) { setNotice(error.message); } finally { event.target.value = ''; }
  }

  async function applyCoupon(event) {
    event.preventDefault();
    if (!couponCode.trim()) return;
    setCouponLoading(true);
    try { const result = await api(`${partner ? `/partner/orders/${id}` : `/orders/${id}`}/coupon`, { method: 'POST', body: { code: couponCode } }); setOrder(result.order); setCouponCode(''); setNotice(result.message || 'Coupon applied.'); } catch (error) { setNotice(error.message); } finally { setCouponLoading(false); }
  }

  async function reviewPayment() {
    setReviewing(true);
    try { const result = await api(`${partner ? `/partner/orders/${id}` : `/orders/${id}`}/payment`, { method: 'POST', body: { status: reviewStatus, amount: payable, method: order.payment_method || 'upi', reference_no: order.payment_reference || '' } }); setOrder(result.order); setNotice(reviewStatus === 'verified' ? 'Payment recorded and verified.' : 'Payment marked as rejected.'); } catch (error) { setNotice(error.message); } finally { setReviewing(false); }
  }

  async function printInvoice() {
    try { const blob = await downloadBlob(`/orders/${id}/invoice.pdf`); const url = URL.createObjectURL(blob); window.open(url, '_blank', 'noopener,noreferrer'); } catch (error) { setNotice(error.message); }
  }

  return <div className="workspace-page">
    <div className="detail-back-row"><button className="back-button" onClick={() => navigate(-1)}><Icon name="chevronLeft" size={16} /> Back</button><span>{order.order_no || `Order #${order.id}`}</span></div>
    <div className="order-detail-hero"><div><span className="eyebrow">{job ? 'Partner service job' : partner ? 'Partner order' : 'Order workspace'}</span><h2>{order.service?.title || 'Tax service'}</h2><p>{order.order_no} · Created {dateLabel(order.created_at)}</p></div><div className="order-hero-side"><StatusBadge value={order.status || order.order_status} /><strong>{money(payable)}</strong></div></div>
    {notice && <div className={`form-message ${noticeIsSuccess ? 'success' : 'danger'}`}>{notice}</div>}
    <div className="order-detail-grid"><div className="detail-main">
      <section className="panel"><div className="panel-heading"><div><span className="eyebrow">Workflow</span><h3>Progress and status</h3></div>{isStaff && !partner && <div className="inline-actions"><select className="input compact-input" value={status} onChange={(event) => setStatus(event.target.value)}>{canReview && <option value="submitted">Submitted</option>}<option value="work_in_progress">Work in progress</option>{canReview && <><option value="rejected">Rejected</option><option value="cancelled">Cancelled</option></>}</select><button className="button dark small" onClick={updateStatus}>Update</button></div>}</div><div className="workflow-line"><div className={['submitted', 'assigned', 'work_in_progress', 'awaiting_approval', 'completed'].includes(order.status || order.order_status) ? 'done' : ''}><span>1</span><strong>Submitted</strong></div><i /><div className={['assigned', 'work_in_progress', 'awaiting_approval', 'completed'].includes(order.status || order.order_status) ? 'done' : ''}><span>2</span><strong>Assigned</strong></div><i /><div className={['work_in_progress', 'awaiting_approval', 'completed'].includes(order.status || order.order_status) ? 'done' : ''}><span>3</span><strong>Work submitted</strong></div><i /><div className={(order.status || order.order_status) === 'completed' ? 'done' : ''}><span>4</span><strong>Approved</strong></div></div>{canReview && <div className="inline-actions" style={{ marginTop: 18 }}><select className="input compact-input" value={assignedUserId} onChange={(event) => setAssignedUserId(event.target.value)}><option value="">Select staff member or partner</option>{assignees.map((person) => <option key={person.id} value={person.id}>{person.name} · {person.role}</option>)}</select><button className="button dark small" onClick={assignJob} disabled={!assignedUserId || assignmentSaving}>{assignmentSaving ? 'Assigning…' : 'Assign job'}</button></div>}{canReview && order.status === 'awaiting_approval' && <div className="inline-actions" style={{ marginTop: 14 }}><button className="button primary small" onClick={approveDeliverable} disabled={approvalSaving}>{approvalSaving ? 'Approving…' : 'Approve and release deliverable'}</button><small>Payment must be verified or marked for later first.</small></div>}</section>
      <section className="panel"><div className="panel-heading"><div><span className="eyebrow">Required records</span><h3>Documents</h3></div><div className="inline-actions">{canWork && !partner && ['assigned', 'work_in_progress', 'awaiting_approval'].includes(order.status) && <label className="button primary small upload-button"><Icon name="upload" size={15} />{outputUploading ? 'Delivering…' : 'Submit deliverable'}<input type="file" onChange={uploadOutput} disabled={outputUploading} /></label>}{!partner && <label className="button light small upload-button"><Icon name="upload" size={15} />{uploading ? 'Uploading…' : 'Upload file'}<input type="file" onChange={uploadFile} disabled={uploading} /></label>}</div></div>{visibleDocuments.length ? <div className="document-list">{visibleDocuments.map((document) => <div className="document-row" key={document.id}><span className="document-icon"><Icon name="file" size={17} /></span><div><strong>{document.label || document.original_name || document.title}</strong><small>{document.original_name} · {document.document_status || document.status || 'Submitted'}</small><span className="inline-actions">{document.download_url && <a className="text-link" href={document.download_url} target="_blank" rel="noreferrer">Open</a>}{!partner && (document.document_status === 'wrong' || document.source === 'client') && <label className="text-link">Replace<input type="file" hidden onChange={(event) => reuploadDocument(document, event)} /></label>}{isStaff && document.document_status !== 'pending_approval' && <><button className="text-link" type="button" onClick={() => markDocument(document, 'active')}>Accept</button><button className="text-link danger-text" type="button" onClick={() => markDocument(document, 'wrong')}>Mark wrong</button></>}</span></div><StatusBadge value={document.document_status || document.status || 'submitted'} /></div>)}</div> : <EmptyState icon="file" title="No documents uploaded yet." text="Upload a file when your service team requests it." />}</section>
      {!partner && <section className="panel"><div className="panel-heading"><div><span className="eyebrow">Payments</span><h3>Payment records</h3></div><StatusBadge value={order.payment_status} /></div>{order.payments?.length ? <div className="payment-list">{order.payments.map((payment) => <div className="payment-row" key={payment.id}><span><strong>{money(payment.amount)}</strong><small>{payment.method} · {payment.reference_no || 'No reference'} · {payment.status || 'Recorded'}</small></span><small>{dateLabel(payment.received_at || payment.created_at)}</small></div>)}</div> : <EmptyState icon="wallet" title="No payment has been recorded." text="Use Pay now to submit your transaction proof." />}</section>}
      {partner && order.payment_reference && <section className="panel"><div className="panel-heading"><div><span className="eyebrow">Payment submission</span><h3>Transaction proof</h3></div><StatusBadge value={order.payment_status} /></div><div className="payment-submission-summary"><div><small>Transaction ID</small><strong>{order.payment_reference}</strong></div>{order.payment_proof && <a className="button light small" href={paymentProofUrl} target="_blank" rel="noreferrer"><Icon name="file" size={14} /> View screenshot</a>}</div></section>}
      {canReview && order.payment_reference && <section className="panel payment-review-panel"><div className="panel-heading"><div><span className="eyebrow">Admin review</span><h3>Payment verification</h3></div><StatusBadge value={order.payment_status} /></div><div className="payment-submission-summary"><div><small>Transaction ID</small><strong>{order.payment_reference}</strong></div>{order.payment_proof && <a className="button light small" href={paymentProofUrl} target="_blank" rel="noreferrer"><Icon name="file" size={14} /> View screenshot</a>}</div>{!completedPayment(order.payment_status) && <div className="inline-actions payment-review-actions"><select className="input compact-input" value={reviewStatus} onChange={(event) => setReviewStatus(event.target.value)}><option value="verified">Verify payment</option><option value="rejected">Reject proof</option></select><button className="button dark small" onClick={reviewPayment} disabled={reviewing}>{reviewing ? 'Saving…' : 'Save review'}</button></div>}</section>}
    </div><aside className="detail-side">
      <section className="side-summary order-summary-card"><span className="eyebrow">Order summary</span><div className="summary-line"><span>Service fee</span><strong>{money(fee)}</strong></div><div className="summary-line"><span>Discount</span><strong>{discount ? `−${money(discount)}` : money(0)}</strong></div>{order.coupon_code && <div className="applied-coupon"><Icon name="tag" size={13} /><span>{order.coupon_code} applied</span></div>}<div className="summary-line total"><span>Payable</span><strong>{money(payable)}</strong></div>{canPay && order.status === 'awaiting_approval' && !completedPayment(paymentStatus) && <Link className="button primary full" to={job ? `/partner/jobs/${id}/pay` : partner ? `/partner/orders/${id}/pay` : `/client/orders/${id}/pay`}><Icon name="wallet" size={16} /> Pay now</Link>}{canPay && order.status === 'awaiting_approval' && !['verified', 'paid', 'deferred', 'pending_review'].includes(paymentStatus) && <button className="button light full" type="button" onClick={choosePayLater}>Choose payment later</button>}{order.invoice && <button className="button dark full" onClick={printInvoice}><Icon name="invoice" size={16} /> Open invoice PDF</button>} {!order.coupon_code && !completedPayment(paymentStatus) && <form className="coupon-form" onSubmit={applyCoupon}><label>Have a coupon?</label><div><input value={couponCode} onChange={(event) => setCouponCode(event.target.value.toUpperCase())} placeholder="Enter coupon code" /><button className="button light small" type="submit" disabled={couponLoading}>{couponLoading ? '…' : 'Apply'}</button></div><small>Each coupon can be used once per account.</small></form>}</section>
      {!partner && <section className="panel"><div className="panel-heading"><div><span className="eyebrow">Task conversation</span><h3>Messages</h3></div></div><div className="task-message-list">{messages.length ? messages.map((item) => <div className="task-message" key={item.id}><strong>{item.sender_name || 'TaxSaathi user'} <small>{item.sender_role || ''} · {dateLabel(item.created_at)}</small></strong><p>{item.message}</p></div>) : <EmptyState icon="support" title="No messages yet." text="Use this thread for questions about this job." />}</div><form className="task-message-form" onSubmit={sendMessage}><textarea className="input" value={messageText} onChange={(event) => setMessageText(event.target.value)} placeholder="Ask a question or share a job update" maxLength={3000} /><button className="button dark small" type="submit" disabled={messageSaving || !messageText.trim()}>{messageSaving ? 'Sending…' : 'Send message'}</button></form></section>}
      {(order.customer_name || order.client) && <section className="side-summary"><span className="eyebrow">Client profile</span><h3>{order.client?.name || order.customer_name}</h3><p>{order.client?.email || order.customer_email}</p><p>{order.client?.phone || order.customer_mobile}</p></section>}{order.assignee && <section className="side-summary"><span className="eyebrow">Assigned to</span><h3>{order.assignee.name}</h3><p>{order.assignee.email}</p></section>}
    </aside></div>
  </div>;
}
