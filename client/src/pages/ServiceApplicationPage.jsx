import { useEffect, useRef, useState } from 'react';
import { Link, useLocation, useNavigate, useParams } from 'react-router-dom';
import { api } from '../lib/api';
import { useAuth } from '../components/AuthContext';
import Icon from '../components/Icons';
import { PageLoader } from '../components/Ui';
import { money } from '../lib/format';

const MAX_FILE_SIZE = 15 * 1024 * 1024;

function readDraft(key) {
  try {
    return JSON.parse(sessionStorage.getItem(key) || '{}');
  } catch {
    return {};
  }
}

function isActive(value) {
  return value !== false && value !== 0 && value !== '0' && value !== 'false';
}

function requirementIcon(label) {
  const value = String(label || '').toLowerCase();
  if (value.includes('pan') || value.includes('aadhaar') || value.includes('kyc')) return 'user';
  if (value.includes('address') || value.includes('office') || value.includes('location')) return 'home';
  if (value.includes('bank') || value.includes('account')) return 'wallet';
  return 'file';
}

export default function ServiceApplicationPage() {
  const { slug } = useParams();
  const location = useLocation();
  const navigate = useNavigate();
  const { user } = useAuth();
  const draftKey = `taxsaathi-apply-draft:${slug}`;
  const draft = readDraft(draftKey);
  const submissionKeyRef = useRef(draft.idempotency_key || (typeof crypto !== 'undefined' && crypto.randomUUID ? crypto.randomUUID() : String(Date.now())));
  const submissionKey = submissionKeyRef.current;
  const [data, setData] = useState(null);
  const [form, setForm] = useState({
    customer_name: draft.customer_name || user?.name || '',
    pan_number: draft.pan_number || '',
    mobile: draft.mobile || user?.phone || '',
    email: draft.email || user?.email || ''
  });
  const [additionalNotes, setAdditionalNotes] = useState(draft.additional_notes || '');
  const [paymentChoice, setPaymentChoice] = useState(['upi', 'pay_later'].includes(draft.payment_choice) ? draft.payment_choice : '');
  const [detailsUnlocked, setDetailsUnlocked] = useState(['upi', 'pay_later'].includes(draft.payment_choice));
  const createdOrderRef = useRef(draft.created_order_id || null);
  const createdOrderPayloadRef = useRef(null);
  const uploadedFileIdsRef = useRef(new Set());
  const [selectedRequirement, setSelectedRequirement] = useState('');
  const [partnerClients, setPartnerClients] = useState([]);
  const [chosenClientId, setChosenClientId] = useState(draft.client_id || '');
  const [documents, setDocuments] = useState([]);
  const [message, setMessage] = useState('');
  const [messageType, setMessageType] = useState('danger');
  const [submitting, setSubmitting] = useState(false);
  const [upiAvailable, setUpiAvailable] = useState(null);

  useEffect(() => {
    setData(null);
    api(`/public/services/${slug}`).then(setData).catch(() => setData({ service: null }));
  }, [slug]);

  useEffect(() => {
    let active = true;
    api('/payment-details')
      .then(({ payment }) => {
        if (!active) return;
        const upiId = String(payment?.upi_id || '').trim();
        setUpiAvailable(Boolean((upiId && upiId !== 'taxsaathi@upi') || payment?.qr_code || payment?.razorpay_enabled));
      })
      .catch(() => { if (active) setUpiAvailable(false); });
    return () => { active = false; };
  }, []);

  useEffect(() => {
    if (!user) return;
    setForm((current) => ({
      ...current,
      customer_name: current.customer_name || user.name || '',
      mobile: current.mobile || user.phone || '',
      email: current.email || user.email || ''
    }));
  }, [user]);

  useEffect(() => {
    if (!['partner', 'partners'].includes(String(user?.role?.slug || '').toLowerCase())) return;
    api('/workflow/clients').then((response) => setPartnerClients(response.clients || [])).catch(() => setPartnerClients([]));
  }, [user?.id, user?.role?.slug]);

  if (!data) return <PageLoader label="Loading application form…" />;
  if (!data.service) return <div className="empty-state large"><h3>Service not found.</h3><Link className="button dark" to="/services">Back to services</Link></div>;

  const service = data.service;
  const requirements = (service.requirements || []).filter((item) => isActive(item.is_active));
  const requiredRequirements = requirements.filter((item) => isActive(item.is_required));
  const attachedFor = (requirementId) => documents.filter((item) => Number(item.requirement.id) === Number(requirementId));

  function feedback(text, type = 'danger') {
    setMessage(text);
    setMessageType(type);
  }

  function updateField(field, value) {
    setForm((current) => ({ ...current, [field]: value }));
  }

  function saveDraft() {
    try {
      sessionStorage.setItem(draftKey, JSON.stringify({ ...form, additional_notes: additionalNotes, client_id: chosenClientId, payment_choice: paymentChoice, created_order_id: createdOrderRef.current, idempotency_key: submissionKey }));
    } catch {
      // Session storage can be unavailable in private browsing. Keep the in-memory form usable.
    }
  }

  function goToAuth(path = '/auth') {
    saveDraft();
    navigate(path, {
      state: {
        from: location.pathname,
        message: 'Sign in to submit your service order. Your entered details will stay on this page.'
      }
    });
  }

  function addDocuments(requirement, files) {
    const incomingFiles = Array.from(files || []);
    if (!requirement || !incomingFiles.length) return;
    const filesToAdd = isActive(requirement.allow_multiple) ? incomingFiles : incomingFiles.slice(0, 1);
    const oversized = filesToAdd.find((file) => file.size > MAX_FILE_SIZE);
    if (oversized) {
      feedback(`${oversized.name} is larger than 15 MB.`);
      return;
    }
    setDocuments((current) => {
      const existing = isActive(requirement.allow_multiple)
        ? current
        : current.filter((item) => Number(item.requirement.id) !== Number(requirement.id));
      return [...existing, ...filesToAdd.map((file) => ({ id: typeof crypto !== 'undefined' && crypto.randomUUID ? crypto.randomUUID() : `${Date.now()}-${Math.random()}`, requirement, file }))];
    });
    setSelectedRequirement('');
    feedback(`${filesToAdd.length} document${filesToAdd.length > 1 ? 's' : ''} added to this order.`, 'success');
  }

  function handleFileChange(event) {
    const files = event.target.files;
    const requirement = requirements.find((item) => String(item.id) === String(selectedRequirement));
    if (!requirement) {
      feedback('Select a document type before choosing a file.');
      event.target.value = '';
      return;
    }
    addDocuments(requirement, files);
    event.target.value = '';
  }

  function removeDocument(index) {
    setDocuments((current) => current.filter((_item, itemIndex) => itemIndex !== index));
  }

  function choosePayment(value) {
    if (submitting || createdOrderRef.current) return;
    setPaymentChoice(value);
    setDetailsUnlocked(false);
    setMessage('');
  }

  function continueToJobDetails() {
    if (!paymentChoice) {
      feedback('Choose Pay with UPI or Pay Later before continuing.');
      return;
    }
    if (paymentChoice === 'upi' && !upiAvailable) {
      feedback('UPI payment is not configured. Choose Pay Later for now, or contact support.');
      return;
    }
    setDetailsUnlocked(true);
    feedback('');
    saveDraft();
  }

  async function submitOrder(event) {
    event.preventDefault();
    if (submitting) return;
    setMessage('');
    if (!paymentChoice || !detailsUnlocked) {
      feedback('Choose a payment method first, then continue to the job details.');
      return;
    }
    if (paymentChoice === 'upi' && !upiAvailable && !createdOrderRef.current) {
      feedback('UPI payment is temporarily unavailable. Choose Pay Later or contact support.');
      return;
    }
    if (!user) {
      goToAuth();
      return;
    }
    const role = String(user.role?.slug || '').toLowerCase();
    const isPartner = ['partner', 'partners'].includes(role);
    if (!['client', 'admin', 'manager', 'partner', 'partners'].includes(role)) {
      feedback('Sign in with a client or partner account to submit a service job.');
      return;
    }
    const missing = requiredRequirements.filter((requirement) => !attachedFor(requirement.id).length);
    if (missing.length && !createdOrderRef.current) {
      feedback(\`Please add: \${missing.map((item) => item.label).join(', ')}\`);
      return;
    }
    saveDraft();
    setSubmitting(true);
    try {
      let order = createdOrderPayloadRef.current;
      if (!order && createdOrderRef.current) {
        const existing = await api(\`/orders/\${createdOrderRef.current}\`, { timeoutMs: 45000 });
        order = existing.order;
      }
      if (!order) {
        const contactNotes = [
          'Customer contact details:',
          \`Name as per PAN: \${form.customer_name.trim()}\`,
          \`PAN Number: \${form.pan_number.trim().toUpperCase()}\`,
          \`Mobile Number: \${form.mobile.trim()}\`,
          \`Email Address: \${form.email.trim()}\`
        ].join('\n');
        const result = await api('/orders', {
          method: 'POST',
          timeoutMs: 60000,
          body: {
            service_id: service.id,
            client_id: chosenClientId ? Number(chosenClientId) : null,
            idempotency_key: submissionKey,
            payment_choice: paymentChoice,
            financial_year: '2026-27',
            notes: [contactNotes, additionalNotes.trim()].filter(Boolean).join('\n\n'),
            customer_name: form.customer_name.trim(),
            pan_number: form.pan_number.trim().toUpperCase(),
            customer_mobile: form.mobile.trim(),
            customer_email: form.email.trim()
          }
        });
        order = result.order;
        if (!order?.id) throw new Error('The order was not confirmed. Please retry using the same form.');
        createdOrderRef.current = order.id;
        createdOrderPayloadRef.current = order;
        saveDraft();
      }
      if (!order?.id) throw new Error('Unable to retrieve the created order. Please contact support.');
      for (const document of documents) {
        if (uploadedFileIdsRef.current.has(document.id)) continue;
        const body = new FormData();
        body.append('file', document.file);
        body.append('label', document.requirement.label);
        body.append('requirement_id', document.requirement.id);
        await api(\`/orders/\${order.id}/documents\`, { method: 'POST', body, timeoutMs: 120000 });
        uploadedFileIdsRef.current.add(document.id);
      }
      sessionStorage.removeItem(draftKey);
      const needsPayment = Boolean(order.payment_first && order.status === 'awaiting_payment');
      const target = isPartner
        ? needsPayment ? \`/partner/jobs/\${order.id}/pay\` : \`/partner/jobs/\${order.id}\`
        : needsPayment ? \`/client/orders/\${order.id}/pay\` : \`/client/orders/\${order.id}\`;
      navigate(target, {
        replace: true,
        state: { message: needsPayment
          ? \`\${order.order_no || 'Your order'} was created. Complete UPI payment to activate processing.\`
          : \`\${order.order_no || 'Your job'} was submitted. \${paymentChoice === 'pay_later' ? 'Payment is deferred and remains outstanding.' : 'Your request is ready for review.'}\` }
      });
    } catch (error) {
      feedback(createdOrderRef.current
        ? \`Your order was created (ID \${createdOrderRef.current}), but the upload or confirmation did not finish. \${error.message} Retry to continue the same order, or open the order to upload missing documents.\`
        : error.message);
    } finally {
      setSubmitting(false);
    }
  }

  return <main className="service-application-page">
    <div className="container-wide">
      <div className="application-back-row">
        <Link to={`/services/${service.slug}`}><Icon name="chevronLeft" size={14} /> Back to service</Link>
        <span>Application · Job request</span>
      </div>
      {!user && <div className="application-login-note"><Icon name="shield" size={16} /><span>Already have an account? <button type="button" onClick={() => goToAuth()}>Log in</button> or <button type="button" onClick={() => goToAuth('/register')}>sign up</button> to submit without losing your details.</span></div>}
      <div className="application-layout">
        <aside className="application-summary-card">
          <span className="eyebrow">Service Summary</span>
          <h2>{service.title}</h2>
          <p className="application-summary-intro">Review the service details and document checklist before placing your order.</p>
          <div className="application-summary-values">
            <div><span>Filing Fee</span><strong>{money(service.filing_fee)}</strong></div>
            <div><span>Turnaround</span><strong>{service.turnaround_days || 7} days</strong></div>
          </div>
          <div className="application-summary-section">
            <strong>Required documents</strong>
            <small>{requiredRequirements.length ? 'Prepare clear copies before continuing.' : 'No documents are required for this service.'}</small>
            <div className="application-requirements">
              {requiredRequirements.map((requirement) => {
                const attached = attachedFor(requirement.id).length;
                return <div className={`application-requirement ${attached ? 'attached' : ''}`} key={requirement.id}>
                  <span className="application-requirement-icon"><Icon name={requirementIcon(requirement.label)} size={14} /></span>
                  <span><strong>{requirement.label}</strong><small>{requirement.help_text || 'A clear copy may be required.'}</small><em>{attached ? `${attached} FILE${attached > 1 ? 'S' : ''} ADDED` : 'NO FILE UPLOADED'}</em></span>
                </div>;
              })}
            </div>
          </div>
        </aside>

        <form className="application-form-card" onSubmit={submitOrder}>
          <div className="application-form-head">
            <span className="application-step">STEP 1 · REQUIRED</span>
            <h1>Choose payment method first</h1>
            <p>Select how you want to pay for this service. Continue to the customer details and document upload only after making a selection.</p>
          </div>
          {message && <div className={`form-message ${messageType}`} role="alert">{message}
            {createdOrderRef.current && <Link className="application-recover-link" to={['partner','partners'].includes(String(user?.role?.slug || '').toLowerCase()) ? `/partner/jobs/${createdOrderRef.current}` : `/client/orders/${createdOrderRef.current}`}>View created order <Icon name="arrow" size={14}/></Link>}
          </div>}
          <fieldset className="application-payment-choice-fieldset" aria-describedby="application-payment-choice-description">
            <legend>Payment options <span>Choose one</span></legend>
            <p id="application-payment-choice-description">No payment is taken on this screen. Your selection determines the next step after job submission.</p>
            <div className="application-payment-choice-grid">
              <label className={`application-payment-choice ${paymentChoice === 'upi' ? 'selected' : ''}`}>
                <input type="radio" name="payment_choice" value="upi" checked={paymentChoice === 'upi'} onChange={() => choosePayment('upi')} disabled={Boolean(createdOrderRef.current) || upiAvailable !== true} />
                <span className="application-payment-choice-icon"><Icon name="wallet" size={21} /></span>
                <span className="application-payment-choice-label"><strong>Pay with UPI</strong><small>{upiAvailable === null ? 'Checking payment availability…' : upiAvailable ? 'Submit the job, then pay using UPI and provide proof. Payment verification is required before work begins.' : 'UPI is not configured yet. Please select Pay Later or contact support.'}</small></span>
                <Icon name="check" size={19} className="application-payment-choice-check" />
              </label>
              <label className={`application-payment-choice ${paymentChoice === 'pay_later' ? 'selected' : ''}`}>
                <input type="radio" name="payment_choice" value="pay_later" checked={paymentChoice === 'pay_later'} onChange={() => choosePayment('pay_later')} disabled={Boolean(createdOrderRef.current)} />
                <span className="application-payment-choice-icon"><Icon name="file" size={21} /></span>
                <span className="application-payment-choice-label"><strong>Pay Later</strong><small>Submit the job now with payment outstanding. An unpaid invoice is recorded for follow-up.</small></span>
                <Icon name="check" size={19} className="application-payment-choice-check" />
              </label>
            </div>
          </fieldset>
          {!detailsUnlocked && <button type="button" className="button primary full application-continue-button" disabled={!paymentChoice || (paymentChoice === 'upi' && !upiAvailable)} onClick={continueToJobDetails}>
            Continue to job details <Icon name="arrow" size={17} />
          </button>}
          {detailsUnlocked && <>
          <div className="application-form-head application-form-head-second">
            <span className="application-step">STEP 2</span>
            <h2>Submit job details</h2>
            <p>Complete your details and upload required documents. Your payment choice is <strong>{paymentChoice === 'upi' ? 'Pay with UPI' : 'Pay Later'}</strong>.</p>
          </div>
          {['partner','partners'].includes(String(user?.role?.slug || '').toLowerCase()) && <div className="workflow-inline-form">
            <label>Linked client (optional)
              <select className="input" value={chosenClientId} onChange={(event) => {
                const selected = event.target.value;
                setChosenClientId(selected);
                const client = partnerClients.find((item) => String(item.id) === selected);
                if (client) setForm((previous) => ({
                  ...previous, customer_name: client.name || previous.customer_name,
                  mobile: client.phone || previous.mobile, email: client.email || previous.email
                }));
              }}>
                <option value="">Enter new client details manually</option>
                {partnerClients.map((client) => <option key={client.id} value={client.id}>{client.name}{client.company_name ? ' · ' + client.company_name : ''}</option>)}
              </select>
            </label>
            <small>Manage your records in the <Link to="/partner/clients">partner client directory</Link>.</small>
          </div>}
          <section className="application-section application-payment-selection-summary" aria-label="Selected payment method">
            <div className="application-section-heading"><span>Payment selection</span><small>{paymentChoice === 'upi' ? 'UPI payment required after submission' : 'Payment deferred'}</small></div>
            <p>{paymentChoice === 'upi' ? 'After submission, you will be taken to the UPI payment page. The team begins processing after payment verification.' : 'Your job can be submitted without paying immediately. The amount remains unpaid and will be tracked for later settlement.'}</p>
          </section>

          <section className="application-section">
            <label className="application-label" htmlFor="additional-notes">Additional Notes</label>
            <textarea id="additional-notes" className="application-control application-textarea" value={additionalNotes} onChange={(event) => setAdditionalNotes(event.target.value)} placeholder="Mention any important context for this filing" />
          </section>

          <section className="application-section">
            <div className="application-section-heading"><span>Customer Contact Details</span><small>These details are used for order updates and payment follow-up.</small></div>
            <div className="application-fields-grid">
              <label className="application-label">Name as per PAN<input className="application-control" required value={form.customer_name} onChange={(event) => updateField('customer_name', event.target.value)} placeholder="Enter name exactly as per PAN" /></label>
              <label className="application-label">PAN Number<input className="application-control" required value={form.pan_number} onChange={(event) => updateField('pan_number', event.target.value.toUpperCase())} placeholder="ABCDE1234F" maxLength={10} /></label>
              <label className="application-label">Mobile Number<input className="application-control" required inputMode="tel" value={form.mobile} onChange={(event) => updateField('mobile', event.target.value)} placeholder="Enter mobile number" /></label>
              <label className="application-label">Email Address<input className="application-control" required type="email" value={form.email} onChange={(event) => updateField('email', event.target.value)} placeholder="Enter email address" /></label>
            </div>
          </section>

          <section className="application-section application-upload-section">
            <div className="application-section-heading"><span>Upload Documents</span><small>Select the document type, choose the file, and add it before final submission.</small></div>
            <div className="application-upload-row">
              <label className="application-label">Select Document<select className="application-control" value={selectedRequirement} onChange={(event) => setSelectedRequirement(event.target.value)} disabled={!requirements.length}><option value="">Choose document type</option>{requirements.map((requirement) => <option value={requirement.id} key={requirement.id}>{requirement.label}</option>)}</select></label>
              <label className="application-label">Choose File<span className="application-file-picker"><span>{selectedRequirement ? 'Choose file — added automatically' : 'Select document type first'}</span><input type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" disabled={!selectedRequirement} multiple={isActive(requirements.find((item) => String(item.id) === String(selectedRequirement))?.allow_multiple)} onChange={handleFileChange} /></span></label>
            </div>
            <div className="application-documents-box">
              <span className="application-documents-title">DOCUMENTS ADDED BEFORE FINAL SUBMISSION</span>
              {documents.length ? <div className="application-added-list">{documents.map((document, index) => <div className="application-added-document" key={`${document.requirement.id}-${document.file.name}-${index}`}><span><Icon name="file" size={14} /><strong>{document.requirement.label}</strong><small>{document.file.name}</small></span><button type="button" aria-label={`Remove ${document.file.name}`} onClick={() => removeDocument(index)}><Icon name="close" size={14} /></button></div>)}</div> : <div className="application-no-documents">No documents added yet.</div>}
            </div>
          </section>

          <button className="button primary full application-submit-button" type="submit" disabled={submitting}>{submitting ? <><span className="spinner" />{createdOrderRef.current ? 'Uploading documents…' : 'Submitting order…'}</> : <>{createdOrderRef.current ? 'Retry remaining uploads' : paymentChoice === 'upi' ? 'Submit & Continue to UPI' : 'Submit Job · Pay Later'} <Icon name="arrow" size={16} /></>}</button>
          <small className="application-footnote">Your job will appear in your client or partner workspace. {paymentChoice === 'upi' ? 'UPI payment and verification are required before processing.' : 'Payment will be due later.'} You can upload additional documents from the order page.</small>
          </>}
        </form>
      </div>
    </div>
  </main>;
}
