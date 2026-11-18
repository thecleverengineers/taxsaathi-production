import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../lib/api';
import { money, dateLabel } from '../lib/format';
import { useAuth } from '../components/AuthContext';
import Icon from '../components/Icons';
import { PageIntro, PageLoader, EmptyState, StatusBadge } from '../components/Ui';

export default function CorporateWorkspacePage() {
  const { user } = useAuth();
  const [data, setData] = useState(null);
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');
  const [company, setCompany] = useState({ company_name: '', contact_person: '' });
  const [saving, setSaving] = useState(false);
  const [eventForm, setEventForm] = useState({ title: '', due_date: '', notes: '' });
  const [eventBusy, setEventBusy] = useState(false);
  const [filter, setFilter] = useState('all');

  async function reload() {
    const response = await api('/corporate/overview');
    setData(response);
    setCompany({
      company_name: response.company?.company_name || '',
      contact_person: response.company?.contact_person || ''
    });
    return response;
  }
  useEffect(() => {
    let active = true;
    api('/corporate/overview')
      .then(response => { if (active) { setData(response); setCompany({
        company_name: response.company?.company_name || '',
        contact_person: response.company?.contact_person || ''
      }); }})
      .catch(e => { if (active) setError(e.message); });
    return () => { active = false; };
  }, []);

  async function saveCompany(event) {
    event.preventDefault();
    setSaving(true); setError(''); setMessage('');
    try {
      const result = await api('/corporate/company', { method: 'PATCH', body: company });
      setData(current => ({ ...current, company: { ...current.company, ...result.company } }));
      setMessage('Company profile updated.');
    } catch (e) { setError(e.message); }
    finally { setSaving(false); }
  }

  async function addDeadline(event) {
    event.preventDefault();
    if (!eventForm.title.trim() || !eventForm.due_date) return;
    setEventBusy(true); setError(''); setMessage('');
    try {
      await api('/corporate/deadlines', { method: 'POST', body: eventForm });
      await reload();
      setEventForm({ title: '', due_date: '', notes: '' });
      setMessage('Compliance reminder saved.');
    } catch (e) { setError(e.message); }
    finally { setEventBusy(false); }
  }

  async function updateDeadline(item, status) {
    setError(''); setMessage('');
    try {
      await api('/corporate/deadlines/' + item.id, { method: 'PATCH', body: { status } });
      await reload();
      setMessage(status === 'completed' ? 'Reminder marked completed.' : 'Reminder reopened.');
    } catch (e) { setError(e.message); }
  }

  if (error && !data) return <div className="workspace-page"><PageIntro eyebrow="Corporate workspace" title="Company access" text="Only authorised Corporate client accounts can view company records." /><div className="form-message danger" role="alert">{error}</div><Link className="button light" to="/dashboard">Return to dashboard</Link></div>;
  if (!data) return <PageLoader label="Loading corporate workspace…" />;
  const companyData = data.company || {};
  const summary = data.summary || {};
  const deadlines = [...(data.events || []), ...(data.deadlines || []).map(row => ({ ...row, title: 'Order ' + (row.order_no || row.id), source: 'order', id: 'order-' + row.id }))]
    .sort((a,b)=>String(a.due_date).localeCompare(String(b.due_date)));
  const filtered = filter === 'open' ? deadlines.filter(row => row.status !== 'completed') : filter === 'completed' ? deadlines.filter(row => row.status === 'completed') : deadlines;

  return <div className="workspace-page corporate-workspace">
    <PageIntro eyebrow="Corporate client" title={companyData.company_name || companyData.name || 'Company workspace'}
      text="Company-wide service overview, payments, editable corporate records, and compliance reminders. Only orders linked to your authorised company are shown."
      actions={<Link to="/services" className="button primary"><Icon name="plus" size={16}/> New service request</Link>}/>
    {error && <div className="form-message danger" role="alert">{error}</div>}
    {message && <div className="form-message success" role="status">{message}</div>}
    <div className="corporate-stat-grid">
      {[
        ['Total service orders', summary.total_orders, 'orders'],
        ['Open cases', summary.open_orders, 'activity'],
        ['Payment follow-ups', summary.outstanding_payments, 'wallet'],
        ['Completed jobs', summary.completed_orders, 'check']
      ].map(([label,value,icon]) => <div className="corporate-stat-card" key={label}><Icon name={icon} size={20}/><span>{label}</span><strong>{value ?? 0}</strong></div>)}
    </div>
    <div className="corporate-panels">
      <section className="panel">
        <div className="panel-heading"><div><span className="eyebrow">Company profile</span><h3>Corporate details</h3></div><Icon name="briefcase" size={20}/></div>
        <form onSubmit={saveCompany} className="corporate-profile-form">
          <label>Registered company name<input required maxLength={150} value={company.company_name}
            onChange={e => setCompany({...company,company_name:e.target.value})}/></label>
          <label>Primary contact person<input maxLength={120} value={company.contact_person}
            onChange={e => setCompany({...company,contact_person:e.target.value})}/></label>
          <div className="corporate-contact-readonly">Account contact: <strong>{companyData.email || user?.email || '—'}</strong> · {companyData.phone || '—'}</div>
          <button className="button dark" disabled={saving}>{saving ? 'Saving…' : 'Save company details'}</button>
        </form>
      </section>
      <section className="panel">
        <div className="panel-heading"><div><span className="eyebrow">Compliance planner</span><h3>Deadlines and reminders</h3></div><Icon name="calendar" size={20}/></div>
        <p className="corporate-help">Add your own reminders. These are planning entries, not automatic statutory filing confirmations.</p>
        <form className="corporate-deadline-form" onSubmit={addDeadline}>
          <label>Reminder title<input required maxLength={140} value={eventForm.title}
            onChange={e=>setEventForm({...eventForm,title:e.target.value})} placeholder="e.g. GST return review"/></label>
          <label>Due date<input type="date" required value={eventForm.due_date}
            onChange={e=>setEventForm({...eventForm,due_date:e.target.value})}/></label>
          <label>Notes (optional)<textarea rows={2} maxLength={1000} value={eventForm.notes}
            onChange={e=>setEventForm({...eventForm,notes:e.target.value})}/></label>
          <button className="button primary" disabled={eventBusy}>{eventBusy ? 'Saving…' : 'Add reminder'} <Icon name="plus" size={15}/></button>
        </form>
      </section>
    </div>
    <section className="panel">
      <div className="panel-heading"><div><span className="eyebrow">Due dates</span><h3>Compliance calendar</h3></div>
        <select value={filter} onChange={e=>setFilter(e.target.value)} aria-label="Filter deadlines">
          <option value="all">All reminders</option><option value="open">Open</option><option value="completed">Completed</option>
        </select></div>
      {filtered.length ? <div className="corporate-reminder-list">{filtered.map(row => <article key={row.id} className="corporate-reminder">
        <div><strong>{row.title}</strong><small>{dateLabel(row.due_date)} · {row.source === 'order' ? 'Order deadline' : row.status || 'open'}</small>
          {row.notes && <p>{row.notes}</p>}</div>
        {row.source === 'order' ? <Link className="button light small" to={'/client/orders/' + row.id.replace('order-','')}>View job</Link>
          : <button type="button" className="button light small" onClick={() => updateDeadline(row, row.status === 'completed' ? 'open' : 'completed')}>
              {row.status === 'completed' ? 'Reopen' : 'Mark completed'}</button>}
      </article>)}</div> : <EmptyState title="No matching reminders" text="Add a compliance reminder or create a service request." />}
    </section>
    <section className="panel">
      <div className="panel-heading"><div><span className="eyebrow">Consolidated register</span><h3>Company service orders</h3></div>
        <Link className="button light small" to="/client/orders">All orders</Link></div>
      {(data.orders || []).length ? <div className="table-scroll"><table className="data-table"><thead><tr><th>Reference</th><th>Created</th><th>Amount</th><th>Payment</th><th>Status</th><th>Action</th></tr></thead><tbody>
        {data.orders.map(row => <tr key={row.id}><td><strong>{row.order_no || '#' + row.id}</strong></td>
          <td>{dateLabel(row.created_at)}</td><td>{money(row.fee_amount || 0)}</td>
          <td><StatusBadge value={row.payment_status || 'unpaid'}/></td>
          <td><StatusBadge value={row.status}/></td>
          <td><Link to={'/client/orders/' + row.id} className="text-link">Open</Link></td></tr>)}
      </tbody></table></div> : <EmptyState title="No corporate orders yet" action={<Link className="button dark" to="/services">Explore services</Link>}/>}
      {data.truncated && <p className="corporate-help">Showing a limited recent history. Use My Orders for the full order register.</p>}
    </section>
    <p className="corporate-help">Company contacts are managed in the authorised client directory. This workspace does not grant employee logins or new access permissions.</p>
  </div>;
}
