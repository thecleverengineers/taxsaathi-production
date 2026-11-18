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
  const [planning, setPlanning] = useState({ priority: 'normal', due_date: '', internal_instructions: '' });
  const [planningSaving, setPlanningSaving] = useState(false);
  const [internalNotes, setInternalNotes] = useState([]);
  const [noteText, setNoteText] = useState('');
  const [noteSaving, setNoteSaving] = useState(false);
  const [revisions, setRevisions] = useState([]);
  const [revisionReason, setRevisionReason] = useState('');
  const [revisionSaving, setRevisionSaving] = useState(false);
  const [messageText, setMessageText] = useState('');
  const [messageSaving, setMessageSaving] = useState(false);
  const [notice, setNotice] = useState(location.state?.message || '');
  const userRoles = [user?.role?.slug, ...(user?.roles || []).map((role) => role.slug)];
  const role = String(user?.role?.slug || '').toLowerCase();
  const isStaff = userRoles.some((item) => ['admin', 'manager', 'executive'].includes(item));
  const canReview = ['admin', 'manager'].includes(role);
  const orderPath = partner ? `/partner/orders/${id}` : `/orders/${id}`;
  const messagesPath = partner ? `/partner/orders/${id}/messages` : `/orders/${id}/messages`;
  const listPath = job ? '/partner/jobs' : partner ? '/partner/orders' : isStaff ? '/admin/orders' : '/client/orders';

  useEffect(() => {
    api(orderPath).then((payload) => { setOrder(payload.order); setStatus(payload.order.status || payload.order.order_status || 'submitted'); setAssignedUserId(payload.order.assigned_user_id || ''); }).catch(() => setOrder(false));
  }, [orderPath]);

  useEffect(() => {
    let active = true;
    const load = () => {
      if (document.visibilityState === 'hidden') return;
      api(messagesPath).then(payload => { if (active) setMessages(payload.messages || []); }).catch(() => {});
    };
    load();
    const timer = setInterval(load, 12000);
    return () => { active = false; clearInterval(timer); };
  }, [messagesPath]);

  useEffect(() => {
    if (canReview) api('/admin/assignees').then((payload) => setAssignees(payload.assignees || [])).catch(() => setAssignees([]));
  }, [canReview]);

  useEffect(() => {
    api(`/workflow/orders/${id}/plan`).then((r) => setPlanning({
      priority: r.plan?.priority || 'normal',
      due_date: r.plan?.due_date?.slice(0, 10) || '',
      internal_instructions: r.plan?.internal_instructions || ''
    })).catch(() => {});
    api(`/workflow/orders/${id}/revisions`).then((r) => setRevisions(r.revisions || [])).catch(() => setRevisions([]));
    if (isStaff) api(`/workflow/orders/${id}/internal-notes`).then((r) => setInternalNotes(r.notes || [])).catch(() => setInternalNotes([]));
  }, [id, isStaff]);

  if (order === null) return <PageLoader label="Loading order…" />;
  if (!order) return <EmptyState title="Order not found." action={<Link className="button dark" to={listPath}>Back to orders</Link>} />;

  const fee = Number(order.fee_amount || order.filing_fee || 0);
  const discount = Number(order.coupon_discount_amount || 0);
  const payable = Number(order.payable_amount || fee);
  const paymentStatus = String(order.payment_status || 'pending').toLowerCase();
  const paymentProofUrl = partner ? `/api/partner/orders/${id}/payment-proof` : `/api/orders/${id}/payment-proof`;
  const canWork = Boolean(order && (canReview || Number(order.assigned_user_id) === Number(user?.id)));
  const canPay = Boolean(order && (Number(order.submitted_by_user_id) === Number(user?.id) || role === 'client' && Number(order.client_id) === Number(user?.client_id) || ['partner', 'partners'].includes(role) && Number(order.partner_id) === Number(user?.partner_id || user?.id)));
  const visibleDocuments = (order.documents || []).filter((document) => isStaff || Boolean(document.is_client_visible));
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

  async function savePlanning(event) {
    event.preventDefault(); setPlanningSaving(true);
    try {
      const result = await api(`/workflow/orders/${id}/plan`, {
        method: 'PATCH', body: { priority: planning.priority, due_date: planning.due_date || null, internal_instructions: planning.internal_instructions }
      });
      setPlanning((current) => ({ ...current, ...result.plan }));
      setOrder((current) => ({ ...current, priority: result.plan.priority, due_date: result.plan.due_date }));
      setNotice('Task plan updated.');
    } catch (error) { setNotice(error.message); } finally { setPlanningSaving(false); }
  }

  async function addInternalNote(event) {
    event.preventDefault(); if (!noteText.trim()) return; setNoteSaving(true);
    try {
      const result = await api(`/workflow/orders/${id}/internal-notes`, { method: 'POST', body: { note: noteText.trim() } });
      setInternalNotes((items) => [...items, result.note]); setNoteText(''); setNotice('Private note saved.');
    } catch (error) { setNotice(error.message); } finally { setNoteSaving(false); }
  }

  async function sendRevisionRequest(event, adminReturn = false) {
    event.preventDefault(); if (!revisionReason.trim()) return; setRevisionSaving(true);
    try {
      const result = await api(`/workflow/orders/${id}/${adminReturn ? 'return' : 'revision-request'}`, {
        method: 'POST', body: { reason: revisionReason.trim() }
      });
      setRevisions((items) => [result.revision, ...items]); setRevisionReason('');
      if (adminReturn) {
        const refreshed = await api(`/orders/${id}`);
        setOrder(refreshed.order); setStatus(refreshed.order.status);
      }
      setNotice(adminReturn ? 'Deliverable returned to staff for revision.' : 'Revision request submitted for admin review.');
    } catch (error) { setNotice(error.message); } finally { setRevisionSaving(false); }
  }

  async function decideRevision(revision, decision) {
    setRevisionSaving(true);
    try {
      const result = await api(`/workflow/orders/${id}/revisions/${revision.id}/decision`, {
        method: 'POST', body: { decision }
      });
      setRevisions((items) => items.map((item) => item.id === revision.id ? result.revision : item));
      const refreshed = await api(`/orders/${id}`);
      setOrder(refreshed.order); setStatus(refreshed.order.status);
      setNotice('Revision request ' + decision + '.');
    } catch (error) { setNotice(error.message); } finally { setRevisionSaving(false); }
  }

  async function sendMessage(event) {
    event.preventDefault();
    if (!messageText.trim()) return;
    setMessageSaving(true);
    try { const result = await api(messagesPath, { method: 'POST', body: { message: messageText.trim() } }); setMessages((current) => [...current, result.message]); setMessageText(''); } catch (error) { setNotice(error.message); } finally { setMessageSaving(false); }
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
      <section className="panel">
        <div className="panel-heading"><div><span className="eyebrow">Scheduling</span><h3>Priority & deadline</h3></div>
          <span className={'workflow-priority ' + planning.priority}>{planning.priority}</span></div>
        {canReview ? <form className="workflow-inline-form" onSubmit={savePlanning}>
          <div className="inline-actions">
            <label>Priority <select className="input compact-input" value={planning.priority} onChange={(e) => setPlanning({...planning,priority:e.target.value})}>
              <option value="low">Low</option><option value="normal">Normal</option><option value="high">High</option><option value="urgent">Urgent</option>
            </select></label>
            <label>Deadline <input className="input compact-input" type="date" value={planning.due_date} onChange={(e) => setPlanning({...planning,due_date:e.target.value})}/></label>
          </div>
          <label>Internal instructions (staff only) <textarea className="input" rows={3} maxLength={4000} value={planning.internal_instructions}
            onChange={(e) => setPlanning({...planning,internal_instructions:e.target.value})}/></label>
          <div><button className="button dark small" disabled={planningSaving}>{planningSaving?'Saving…':'Save task plan'}</button></div>
        </form> : <div className="summary-line"><span>Deadline</span><strong>{planning.due_date ? dateLabel(planning.due_date) : 'Not set'}</strong></div>}
        {!canReview && isStaff && planning.internal_instructions && <p className="workflow-help">{planning.internal_instructions}</p>}
      </section>
      <section className="panel">
        <div className="panel-heading"><div><span className="eyebrow">Quality control</span><h3>Revision requests</h3></div></div>
        {revisions.map((revision) => <div className="workflow-revision-card" key={revision.id}>
          <strong>{revision.type === 'admin_return' ? 'Admin returned deliverable' : 'Correction request'}</strong>
          <small>{revision.status} · {dateLabel(revision.created_at)}</small><p>{revision.reason}</p>
          {canReview && revision.status === 'pending' && <div className="inline-actions">
            <button type="button" className="button dark small" disabled={revisionSaving} onClick={() => decideRevision(revision,'approved')}>Approve</button>
            <button type="button" className="button light small" disabled={revisionSaving} onClick={() => decideRevision(revision,'rejected')}>Decline</button>
          </div>}
        </div>)}
        {((canReview && order.status === 'awaiting_approval') || (canPay && order.status === 'completed' && !canReview)) && <form className="workflow-inline-form" onSubmit={(event) => sendRevisionRequest(event,canReview)}>
          <textarea className="input" rows={3} required minLength={5} maxLength={1000}
            value={revisionReason} onChange={(event) => setRevisionReason(event.target.value)}
            placeholder={canReview?'Explain what staff must revise…':'Describe the issue with the approved deliverable…'}/>
          <div><button className="button light small" disabled={revisionSaving}>{revisionSaving?'Submitting…':canReview?'Return deliverable for revision':'Request correction'}</button></div>
        </form>}
        {!revisions.length && <p className="workflow-help">No revisions have been recorded.</p>}
      </section>
      {isStaff && canWork && <section className="panel">
        <div className="panel-heading"><div><span className="eyebrow">Staff only</span><h3>Private internal notes</h3></div></div>
        <p className="workflow-help">Only Admin and authorised assigned staff can view these notes.</p>
        <div className="workflow-note-list">{internalNotes.map((note)=><div className="workflow-note-card" key={note.id}>
          <strong>{note.author_name||'Staff'} <small>{dateLabel(note.created_at)}</small></strong><p>{note.text}</p>
        </div>)}</div>
        <form className="workflow-inline-form" onSubmit={addInternalNote}>
          <textarea className="input" required maxLength={3000} value={noteText} onChange={(event)=>setNoteText(event.target.value)} placeholder="Staff notes, blockers, internal decisions…"/>
          <div><button className="button dark small" disabled={noteSaving}>{noteSaving?'Saving…':'Add private note'}</button></div>
        </form>
      </section>}
      <section className="panel"><div className="panel-heading"><div><span className="eyebrow">Required records</span><h3>Documents</h3></div><div className="inline-actions">{canWork && !partner && ['assigned', 'work_in_progress', 'awaiting_approval'].includes(order.status) && <label className="button primary small upload-button"><Icon name="upload" size={15} />{outputUploading ? 'Delivering…' : 'Submit deliverable'}<input type="file" onChange={uploadOutput} disabled={outputUploading} /></label>}{!partner && <label className="button light small upload-button"><Icon name="upload" size={15} />{uploading ? 'Uploading…' : 'Upload file'}<input type="file" onChange={uploadFile} disabled={uploading} /></label>}</div></div>{visibleDocuments.length ? <div className="document-list">{visibleDocuments.map((document) => <div className="document-row" key={document.id}><span className="document-icon"><Icon name="file" size={17} /></span><div><strong>{document.label || document.original_name || document.title}</strong><small>{document.original_name} · {document.document_status || document.status || 'Submitted'}</small><span className="inline-actions">{document.download_url && <a className="text-link" href={document.download_url} target="_blank" rel="noreferrer">Open</a>}{!partner && (document.document_status === 'wrong' || document.source === 'client') && <label className="text-link">Replace<input type="file" hidden onChange={(event) => reuploadDocument(document, event)} /></label>}{isStaff && document.document_status !== 'pending_approval' && <><button className="text-link" type="button" onClick={() => markDocument(document, 'active')}>Accept</button><button className="text-link danger-text" type="button" onClick={() => markDocument(document, 'wrong')}>Mark wrong</button></>}</span></div><StatusBadge value={document.document_status || document.status || 'submitted'} /></div>)}</div> : <EmptyState icon="file" title="No documents uploaded yet." text="Upload a file when your service team requests it." />}</section>
      {!partner && <section className="panel"><div className="panel-heading"><div><span className="eyebrow">Payments</span><h3>Payment records</h3></div><StatusBadge value={order.payment_status} /></div>{order.payments?.length ? <div className="payment-list">{order.payments.map((payment) => <div className="payment-row" key={payment.id}><span><strong>{money(payment.amount)}</strong><small>{payment.method} · {payment.reference_no || 'No reference'} · {payment.status || 'Recorded'}</small></span><small>{dateLabel(payment.received_at || payment.created_at)}</small></div>)}</div> : <EmptyState icon="wallet" title="No payment has been recorded." text="Use Pay now to submit your transaction proof." />}</section>}
      {partner && order.payment_reference && <section className="panel"><div className="panel-heading"><div><span className="eyebrow">Payment submission</span><h3>Transaction proof</h3></div><StatusBadge value={order.payment_status} /></div><div className="payment-submission-summary"><div><small>Transaction ID</small><strong>{order.payment_reference}</strong></div>{order.payment_proof && <a className="button light small" href={paymentProofUrl} target="_blank" rel="noreferrer"><Icon name="file" size={14} /> View screenshot</a>}</div></section>}
      {canReview && order.payment_reference && <section className="panel payment-review-panel"><div className="panel-heading"><div><span className="eyebrow">Admin review</span><h3>Payment verification</h3></div><StatusBadge value={order.payment_status} /></div><div className="payment-submission-summary"><div><small>Transaction ID</small><strong>{order.payment_reference}</strong></div>{order.payment_proof && <a className="button light small" href={paymentProofUrl} target="_blank" rel="noreferrer"><Icon name="file" size={14} /> View screenshot</a>}</div>{!completedPayment(order.payment_status) && <div className="inline-actions payment-review-actions"><select className="input compact-input" value={reviewStatus} onChange={(event) => setReviewStatus(event.target.value)}><option value="verified">Verify payment</option><option value="rejected">Reject proof</option></select><button className="button dark small" onClick={reviewPayment} disabled={reviewing}>{reviewing ? 'Saving…' : 'Save review'}</button></div>}</section>}
    </div><aside className="detail-side">
      <section className="side-summary order-summary-card"><span className="eyebrow">Order summary</span><div className="summary-line"><span>Service fee</span><strong>{money(fee)}</strong></div><div className="summary-line"><span>Discount</span><strong>{discount ? `−${money(discount)}` : money(0)}</strong></div>{order.coupon_code && <div className="applied-coupon"><Icon name="tag" size={13} /><span>{order.coupon_code} applied</span></div>}<div className="summary-line total"><span>Payable</span><strong>{money(payable)}</strong></div>{canPay && ['awaiting_payment', 'awaiting_approval'].includes(order.status) && !completedPayment(paymentStatus) && <Link className="button primary full" to={job ? `/partner/jobs/${id}/pay` : partner ? `/partner/orders/${id}/pay` : `/client/orders/${id}/pay`}><Icon name="wallet" size={16} /> Pay now</Link>}{canPay && order.status === 'awaiting_approval' && !order.payment_first && !['verified', 'paid', 'deferred', 'pending_review'].includes(paymentStatus) && <button className="button light full" type="button" onClick={choosePayLater}>Choose payment later</button>}{order.invoice && <button className="button dark full" onClick={printInvoice}><Icon name="invoice" size={16} /> Open invoice PDF</button>} {!order.coupon_code && !completedPayment(paymentStatus) && <form className="coupon-form" onSubmit={applyCoupon}><label>Have a coupon?</label><div><input value={couponCode} onChange={(event) => setCouponCode(event.target.value.toUpperCase())} placeholder="Enter coupon code" /><button className="button light small" type="submit" disabled={couponLoading}>{couponLoading ? '…' : 'Apply'}</button></div><small>Each coupon can be used once per account.</small></form>}</section>
      {<section className="panel"><div className="panel-heading"><div><span className="eyebrow">Task conversation</span><h3>Messages</h3></div></div><div className="task-message-list">{messages.length ? messages.map((item) => <div className="task-message" key={item.id}><strong>{item.sender_name || 'TaxSaathi user'} <small>{item.sender_role || ''} · {dateLabel(item.created_at)}</small></strong><p>{item.message}</p></div>) : <EmptyState icon="support" title="No messages yet." text="Use this thread for questions about this job." />}</div><form className="task-message-form" onSubmit={sendMessage}><textarea className="input" value={messageText} onChange={(event) => setMessageText(event.target.value)} placeholder="Ask a question or share a job update" maxLength={3000} /><button className="button dark small" type="submit" disabled={messageSaving || !messageText.trim()}>{messageSaving ? 'Sending…' : 'Send message'}</button></form></section>}
      {(order.customer_name || order.client) && <section className="side-summary"><span className="eyebrow">Client profile</span><h3>{order.client?.name || order.customer_name}</h3><p>{order.client?.email || order.customer_email}</p><p>{order.client?.phone || order.customer_mobile}</p></section>}{order.assignee && <section className="side-summary"><span className="eyebrow">Assigned to</span><h3>{order.assignee.name}</h3><p>{order.assignee.email}</p></section>}
    </aside></div>
  </div>;
}
