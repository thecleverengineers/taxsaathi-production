import { useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../lib/api';
import Icon from '../components/Icons';
import { EmptyState, PageIntro, PageLoader, StatusBadge, Table } from '../components/Ui';
import { dateLabel, statusLabel } from '../lib/format';

const tabs = [
  ['leads', 'Leads'],
  ['applications', 'Applications'],
  ['catalog', 'Service details'],
  ['roles', 'Roles & permissions'],
  ['seo', 'SEO workspace'],
  ['activity', 'Activity timeline']
];

function TabBar({ active }) {
  return <div className="filter-pills dark-pills admin-tabs">{tabs.map(([key, label]) => <Link key={key} className={active === key ? 'active' : ''} to={`/admin/operations/${key}`}>{label}</Link>)}</div>;
}

function LeadsPanel() {
  const [rows, setRows] = useState(null);
  const [filter, setFilter] = useState('');
  useEffect(() => { api(`/admin/leads${filter ? `?status=${encodeURIComponent(filter)}` : ''}`).then((payload) => setRows(payload.leads || [])).catch(() => setRows([])); }, [filter]);
  if (!rows) return <PageLoader />;
  async function update(id, status) { await api(`/admin/leads/${id}`, { method: 'PATCH', body: { status } }); setRows((current) => current.map((row) => row.id === id ? { ...row, status } : row)); }
  return <><div className="filter-panel"><span className="result-count">{rows.length} enquiries</span><select className="input compact-input" value={filter} onChange={(event) => setFilter(event.target.value)}><option value="">All statuses</option><option value="new">New</option><option value="contacted">Contacted</option><option value="converted">Converted</option><option value="closed">Closed</option></select></div><section className="panel"><Table rows={rows} empty="No enquiries found." columns={[{ key: 'name', label: 'Lead', render: (row) => <div className="table-person"><span className="avatar small">{row.name?.slice(0, 1)}</span><span><strong>{row.name}</strong><small>{row.email || row.phone}</small></span></div> }, { key: 'service', label: 'Service', render: (row) => row.service || 'General enquiry' }, { key: 'created_at', label: 'Received', render: (row) => dateLabel(row.created_at) }, { key: 'status', label: 'Status', render: (row) => <select className="input compact-input" value={row.status || 'new'} onChange={(event) => update(row.id, event.target.value)}><option value="new">New</option><option value="contacted">Contacted</option><option value="converted">Converted</option><option value="closed">Closed</option></select> }]} /></section></>;
}

function ApplicationsPanel() {
  const [rows, setRows] = useState(null);
  useEffect(() => { api('/admin/applications').then((payload) => setRows(payload.applications || [])).catch(() => setRows([])); }, []);
  if (!rows) return <PageLoader />;
  async function update(id, status) { await api(`/admin/applications/${id}`, { method: 'PATCH', body: { status } }); setRows((current) => current.map((row) => row.id === id ? { ...row, status } : row)); }
  return <section className="panel"><Table rows={rows} empty="No applications found." columns={[{ key: 'full_name', label: 'Applicant', render: (row) => <div><strong>{row.full_name}</strong><small>{row.email} · {row.mobile}</small></div> }, { key: 'service_title', label: 'Service' }, { key: 'created_at', label: 'Created', render: (row) => dateLabel(row.created_at) }, { key: 'status', label: 'Status', render: (row) => <select className="input compact-input" value={row.status || 'new'} onChange={(event) => update(row.id, event.target.value)}><option value="new">New</option><option value="contacted">Contacted</option><option value="converted">Converted</option><option value="closed">Closed</option><option value="rejected">Rejected</option></select> }]} /></section>;
}

function RolesPanel() {
  const [data, setData] = useState(null);
  useEffect(() => { api('/admin/roles').then(setData).catch(() => setData({ roles: [], permissions: [] })); }, []);
  if (!data) return <PageLoader />;
  return <div className="workspace-grid"><section className="panel"><div className="panel-heading"><div><span className="eyebrow">RBAC</span><h3>Roles</h3></div></div><Table rows={data.roles || []} empty="No roles found." columns={[{ key: 'name', label: 'Role', render: (row) => <div><strong>{row.name}</strong><small>{row.slug}</small></div> }, { key: 'users', label: 'Users' }, { key: 'description', label: 'Description' }]} /></section><section className="panel"><div className="panel-heading"><div><span className="eyebrow">Permission catalog</span><h3>Available permissions</h3></div></div><Table rows={data.permissions || []} empty="No permissions found." columns={[{ key: 'permission_key', label: 'Key', render: (row) => row.permission_key || row.slug || row.name }, { key: 'label', label: 'Label' }, { key: 'module', label: 'Module' }]} /></section></div>;
}

function SeoPanel() {
  const [keywords, setKeywords] = useState(null);
  const [tasks, setTasks] = useState([]);
  const [keyword, setKeyword] = useState('');
  useEffect(() => { Promise.all([api('/admin/seo/keywords'), api('/admin/seo/tasks')]).then(([keywordPayload, taskPayload]) => { setKeywords(keywordPayload.keywords || []); setTasks(taskPayload.tasks || []); }).catch(() => { setKeywords([]); setTasks([]); }); }, []);
  if (!keywords) return <PageLoader />;
  async function addKeyword(event) { event.preventDefault(); if (!keyword.trim()) return; const result = await api('/admin/seo/keywords', { method: 'POST', body: { keyword } }); setKeywords((current) => [result.keyword, ...current]); setKeyword(''); }
  return <><section className="panel"><div className="panel-heading"><div><span className="eyebrow">Rank tracker</span><h3>Target keywords</h3></div><form className="inline-actions" onSubmit={addKeyword}><input className="input" value={keyword} onChange={(event) => setKeyword(event.target.value)} placeholder="Add keyword" /><button className="button dark small"><Icon name="plus" size={14} /> Add</button></form></div><Table rows={keywords} empty="No SEO keywords found." columns={[{ key: 'keyword', label: 'Keyword' }, { key: 'target_url', label: 'Target URL' }, { key: 'current_rank', label: 'Current rank', render: (row) => row.current_rank || '—' }, { key: 'priority', label: 'Priority', render: (row) => <StatusBadge value={row.priority || 'medium'} /> }, { key: 'status', label: 'Status', render: (row) => statusLabel(row.status || 'new') }]} /></section><section className="panel"><div className="panel-heading"><div><span className="eyebrow">On-page work</span><h3>SEO tasks</h3></div></div><Table rows={tasks} empty="No SEO tasks found." columns={[{ key: 'task_title', label: 'Task' }, { key: 'task_type', label: 'Type' }, { key: 'priority', label: 'Priority' }, { key: 'due_date', label: 'Due', render: (row) => row.due_date || '—' }, { key: 'status', label: 'Status' }]} /></section></>;
}

function CatalogPanel() {
  const [services, setServices] = useState(null);
  const [selected, setSelected] = useState(null);
  const [kind, setKind] = useState('requirements');
  const [rows, setRows] = useState([]);
  const [value, setValue] = useState('');
  useEffect(() => { api('/admin/services').then((payload) => { setServices(payload.services || []); if (payload.services?.[0]) setSelected(payload.services[0]); }).catch(() => setServices([])); }, []);
  useEffect(() => { if (selected) api(`/admin/services/${selected.id}/${kind}`).then((payload) => setRows(payload.rows || [])).catch(() => setRows([])); }, [selected, kind]);
  if (!services) return <PageLoader />;
  async function add(event) { event.preventDefault(); if (!selected || !value.trim()) return; const result = await api(`/admin/services/${selected.id}/${kind}`, { method: 'POST', body: { title: value, name: value, text: value, label: value, description: value } }); setRows((current) => [...current, result.row]); setValue(''); }
  return <section className="panel"><div className="panel-heading"><div><span className="eyebrow">Catalogue detail</span><h3>Requirements, benefits, types, reviews, and banners</h3></div></div><div className="form-row"><label>Service<select value={selected?.id || ''} onChange={(event) => setSelected(services.find((service) => Number(service.id) === Number(event.target.value)))}>{services.map((service) => <option key={service.id} value={service.id}>{service.title}</option>)}</select></label><label>Detail type<select value={kind} onChange={(event) => setKind(event.target.value)}>{Object.keys({ requirements: 1, benefits: 1, types: 1, reviews: 1, banners: 1 }).map((item) => <option key={item} value={item}>{item}</option>)}</select></label></div><form className="inline-actions" onSubmit={add}><input className="input" value={value} onChange={(event) => setValue(event.target.value)} placeholder={`Add ${kind.slice(0, -1)}`} /><button className="button dark small"><Icon name="plus" size={14} /> Add detail</button></form>{rows.length ? <div className="simple-record-list">{rows.map((row) => <div key={row.id}><span>{row.title || row.name || row.label || row.question || row.image || `Record #${row.id}`}</span><small>{row.description || row.text || row.answer || row.quote || ''}</small></div>)}</div> : <EmptyState title={`No ${kind} configured for this service.`} />}</section>;
}

function ActivityPanel() {
  const [rows, setRows] = useState(null);
  useEffect(() => { api('/admin/activity?limit=200').then((payload) => setRows(payload.activity || [])).catch(() => setRows([])); }, []);
  if (!rows) return <PageLoader />;
  return <section className="panel"><Table rows={rows} empty="No activity found." columns={[{ key: 'action', label: 'Action' }, { key: 'description', label: 'Description' }, { key: 'user_id', label: 'Actor', render: (row) => row.user_id ? `User #${row.user_id}` : 'System' }, { key: 'order_id', label: 'Order', render: (row) => row.order_id ? `#${row.order_id}` : '—' }, { key: 'created_at', label: 'Time', render: (row) => dateLabel(row.created_at) }]} /></section>;
}

export default function AdvancedAdminPage({ section = 'leads' }) {
  const title = tabs.find(([key]) => key === section)?.[1] || 'Operations';
  const panel = useMemo(() => ({ leads: <LeadsPanel />, applications: <ApplicationsPanel />, catalog: <CatalogPanel />, roles: <RolesPanel />, seo: <SeoPanel />, activity: <ActivityPanel /> }[section] || <LeadsPanel />), [section]);
  return <div className="workspace-page"><PageIntro eyebrow="Advanced administration" title={title} text="The migrated operations console covers the legacy CRM, catalogue, content, RBAC, SEO, and audit workflows." /><TabBar active={section} />{panel}</div>;
}
