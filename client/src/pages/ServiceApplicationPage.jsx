import { useEffect, useState } from 'react';
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
  const [data, setData] = useState(null);
  const [form, setForm] = useState({
    customer_name: draft.customer_name || user?.name || '',
    pan_number: draft.pan_number || '',
    mobile: draft.mobile || user?.phone || '',
    email: draft.email || user?.email || ''
  });
  const [additionalNotes, setAdditionalNotes] = useState(draft.additional_notes || '');
  const [paymentMethod, setPaymentMethod] = useState(draft.payment_method || 'upi');
  const [selectedRequirement, setSelectedRequirement] = useState('');
  const [documents, setDocuments] = useState([]);
  const [message, setMessage] = useState('');
  const [messageType, setMessageType] = useState('danger');
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    setData(null);
    api(`/public/services/${slug}`).then(setData).catch(() => setData({ service: null }));
  }, [slug]);

  useEffect(() => {
    if (!user) return;
    setForm((current) => ({
      ...current,
      customer_name: current.customer_name || user.name || '',
      mobile: current.mobile || user.phone || '',
      email: current.email || user.email || ''
    }));
  }, [user]);

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
    sessionStorage.setItem(draftKey, JSON.stringify({ ...form, additional_notes: additionalNotes, payment_method: paymentMethod }));
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
      return [...existing, ...filesToAdd.map((file) => ({ requirement, file }))];
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

  async function submitOrder(event) {
    event.preventDefault();
    setMessage('');
    if (!user) {
      goToAuth();
      return;
    }
    if (!['client', 'admin', 'manager'].includes(String(user.role?.slug || '').toLowerCase())) {
      feedback('Only a client account can submit a new service order.');
      return;
    }
    const missing = requiredRequirements.filter((requirement) => !attachedFor(requirement.id).length);
    if (missing.length) {
      feedback(`Please add: ${missing.map((item) => item.label).join(', ')}`);
      return;
    }
    setSubmitting(true);
    try {
      const contactNotes = [
        'Customer contact details:',
        `Name as per PAN: ${form.customer_name.trim()}`,
        `PAN Number: ${form.pan_number.trim().toUpperCase()}`,
        `Mobile Number: ${form.mobile.trim()}`,
        `Email Address: ${form.email.trim()}`
      ].join('\n');
      const result = await api('/orders', {
        method: 'POST',
        body: {
          service_id: service.id,
          financial_year: '2026-27',
          payment_method: paymentMethod,
          notes: [contactNotes, additionalNotes.trim()].filter(Boolean).join('\n\n'),
          customer_name: form.customer_name.trim(),
          pan_number: form.pan_number.trim().toUpperCase(),
          customer_mobile: form.mobile.trim(),
          customer_email: form.email.trim()
        }
      });
      const orderId = result.order?.id;
      if (!orderId) throw new Error('The order was not created. Please try again.');
      for (const document of documents) {
        const body = new FormData();
        body.append('file', document.file);
        body.append('label', document.requirement.label);
        body.append('requirement_id', document.requirement.id);
        await api(`/orders/${orderId}/documents`, { method: 'POST', body });
      }
      sessionStorage.removeItem(draftKey);
      navigate(`/client/orders/${orderId}`, {
        replace: true,
        state: { message: `${result.order.order_no || 'Your order'} was submitted. Continue with the payment instructions in your order workspace.` }
      });
    } catch (error) {
      feedback(error.message);
    } finally {
      setSubmitting(false);
    }
  }

  return <main className="service-application-page">
    <div className="container-wide">
      <div className="application-back-row">
        <Link to={`/services/${service.slug}`}><Icon name="chevronLeft" size={14} /> Back to service</Link>
        <span>Application · Step 1 of 2</span>
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
            <span className="application-step">STEP 1</span>
            <h1>Submit order details</h1>
            <p>Upload all required documents and submit your order. If you are not logged in, you can log in or sign up here without losing the form.</p>
          </div>
          {message && <div className={`form-message ${messageType}`}>{message}</div>}

          <section className="application-section">
            <div className="application-section-heading"><span>Payment Method</span><small>Step 2 will confirm the payment instructions.</small></div>
            <select className="application-control" value={paymentMethod} onChange={(event) => setPaymentMethod(event.target.value)}>
              <option value="upi">UPI</option>
              <option value="manual">Manual payment</option>
            </select>
            <div className="application-payment-note"><strong>Payment method will be confirmed in Step 2.</strong><span>After this form is submitted, your order will be created first. Follow the instructions for your selected method.</span></div>
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

          <button className="button primary full application-submit-button" type="submit" disabled={submitting}>{submitting ? <><span className="spinner" />Submitting order…</> : <>Submit Order &amp; Continue to Payment <Icon name="arrow" size={16} /></>}</button>
          <small className="application-footnote">Your order will appear in your client workspace after submission. You can add more documents later if requested by the service team.</small>
        </form>
      </div>
    </div>
  </main>;
}
