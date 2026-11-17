import { useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../lib/api';
import { useAuth } from '../components/AuthContext';
import { PageIntro, PageLoader, StatCard, EmptyState, StatusBadge } from '../components/Ui';
import { dateLabel, statusLabel } from '../lib/format';
import Icon from '../components/Icons';

const priorities = ['low', 'normal', 'high', 'urgent'];
const today = () => new Date().toISOString().slice(0, 10);
const isOverdue = (job) => job.due_date && job.due_date.slice(0, 10) < today() && !['completed','cancelled','rejected'].includes(job.status);

export default function WorkflowBoardPage() {
  const { user } = useAuth();
  const role = String(user?.role?.slug || 'client').toLowerCase();
  const isAdmin = ['admin','manager'].includes(role);
  const [tasks, setTasks] = useState(null);
  const [stats, setStats] = useState({});
  const [query, setQuery] = useState('');
  const [status, setStatus] = useState('all');
  const [priority, setPriority] = useState('all');
  const [editing, setEditing] = useState(null);
  const [plan, setPlan] = useState({priority:'normal',due_date:''});
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState('');
  useEffect(() => {
    Promise.all([api('/workflow/tasks'), api('/workflow/overview')]).then(([t,o]) => {
      setTasks(t.tasks || []); setStats(o.stats || {});
    }).catch((err) => {setMessage(err.message);setTasks([]);});
  },[]);
  const filtered = useMemo(() => (tasks || []).filter((item) =>
    (status === 'all' || (status === 'overdue' ? isOverdue(item) : item.status === status)) &&
    (priority === 'all' || item.priority === priority) &&
    (!query || [item.order_no,item.service,item.client_name].join(' ').toLowerCase().includes(query.toLowerCase()))
  ),[tasks,status,priority,query]);
  const statuses = [...new Set((tasks || []).map((item) => item.status).filter(Boolean))];
  const pathFor = (item) => ['partner','partners'].includes(role) ? '/partner/jobs/' + item.id : role === 'client' ? '/client/orders/' + item.id : '/admin/orders/' + item.id;
  async function save(event) {
    event.preventDefault(); if(!editing) return; setSaving(true); setMessage('');
    try {
      const result = await api('/workflow/orders/' + editing.id + '/plan',{method:'PATCH',body:{priority:plan.priority,due_date:plan.due_date||null}});
      setTasks((rows) => rows.map((row) => row.id === editing.id ? {...row,...result.plan} : row));
      setEditing(null);setMessage('Task planning updated.');
    } catch(err) {setMessage(err.message);} finally {setSaving(false);}
  }
  if(!tasks) return <PageLoader label="Loading task board…" />;
  return <div className="workspace-page">
    <PageIntro eyebrow="Workflow operations" title={isAdmin ? 'Task assignment & monitoring' : 'My assigned tasks'}
      text="Track assignment, deadlines, work in progress, approval requests and completed services."
      actions={<Link className="button dark" to="/services">New service <Icon name="plus" size={16}/></Link>}/>
    {message && <div className="workflow-feedback" role="status">{message}</div>}
    <div className="workflow-stats">
      <StatCard label="Total jobs" value={stats.total || 0} icon="orders" tone="indigo"/>
      <StatCard label="Unassigned" value={stats.awaiting_assignment || 0} icon="users" tone="slate"/>
      <StatCard label="In progress" value={stats.in_progress || 0} icon="activity" tone="cyan"/>
      <StatCard label="Awaiting approval" value={stats.approvals || 0} icon="check" tone="violet"/>
      <StatCard label="Overdue" value={stats.overdue || 0} icon="bell" tone="amber"/>
      <StatCard label="Completed" value={stats.completed || 0} icon="completed" tone="emerald"/>
    </div>
    <section className="panel workflow-panel">
      <div className="workflow-tools">
        <div className="search-field compact"><Icon name="search" size={17}/><input value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Search job, client or service…" aria-label="Search tasks"/></div>
        <select className="input compact-input" value={status} onChange={(event) => setStatus(event.target.value)} aria-label="Filter job status"><option value="all">All statuses</option><option value="overdue">Overdue</option>{statuses.map((v) => <option key={v} value={v}>{statusLabel(v)}</option>)}</select>
        <select className="input compact-input" value={priority} onChange={(event) => setPriority(event.target.value)} aria-label="Filter priority"><option value="all">All priorities</option>{priorities.map((v) => <option key={v} value={v}>{v.toUpperCase()}</option>)}</select>
        <span className="result-count">{filtered.length} jobs</span>
      </div>
      {filtered.length ? <div className="table-scroll"><table className="data-table orders-table">
        <thead><tr><th>Job</th><th>Client</th><th>Priority</th><th>Deadline</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>{filtered.map((item) => <tr key={item.id}>
          <td><Link className="table-primary" to={pathFor(item)}>{item.order_no || '#' + item.id}</Link><small>{item.service}</small></td>
          <td><strong>{item.client_name}</strong></td>
          <td><span className={'workflow-priority ' + item.priority}>{item.priority}</span></td>
          <td><span className={isOverdue(item) ? 'workflow-overdue' : ''}>{item.due_date ? dateLabel(item.due_date) : 'Not set'}</span></td>
          <td><StatusBadge value={item.status}/></td>
          <td><div className="inline-actions"><Link className="button light small" to={pathFor(item)}>Open</Link>
            {isAdmin && <button type="button" className="button light small" onClick={() => {setEditing(item);setPlan({priority:item.priority||'normal',due_date:item.due_date?.slice(0,10)||''});}}>Plan</button>}</div></td>
        </tr>)}</tbody>
      </table></div> : <EmptyState icon="folder" title="No matching jobs" text="Try another filter or search."/>}
    </section>
    {editing && <div className="modal-backdrop" onClick={() => setEditing(null)}><form className="modal-card workflow-modal" onClick={(event) => event.stopPropagation()} onSubmit={save}>
      <div className="panel-heading"><h3>{editing.order_no} · Planning</h3><button type="button" className="icon-link" aria-label="Close" onClick={() => setEditing(null)}><Icon name="close"/></button></div>
      <label>Priority<select value={plan.priority} onChange={(event) => setPlan({...plan,priority:event.target.value})}>{priorities.map((v) => <option key={v} value={v}>{v}</option>)}</select></label>
      <label>Deadline<input type="date" value={plan.due_date} onChange={(event) => setPlan({...plan,due_date:event.target.value})}/></label>
      <p className="workflow-help">Use the job detail for staff instructions, private notes and approvals.</p>
      <div className="inline-actions"><button type="button" className="button light" onClick={() => setEditing(null)}>Cancel</button><button className="button dark" disabled={saving}>{saving?'Saving…':'Save plan'}</button></div>
    </form></div>}
  </div>;
}

export function StaffWorkloadPage() {
  const [staff,setStaff] = useState(null);
  useEffect(() => {Promise.all([api('/admin/assignees'),api('/workflow/tasks')]).then(([people,jobs]) => {
    const tasks=jobs.tasks||[];
    setStaff((people.assignees||[]).filter((person) => person.role==='executive').map((person) => {
      const assigned=tasks.filter((job) => Number(job.assigned_user_id)===Number(person.id));
      return {...person,total:assigned.length,active:assigned.filter((job) => !['completed','cancelled','rejected'].includes(job.status)).length,overdue:assigned.filter(isOverdue).length};
    }));
  }).catch(() => setStaff([]));},[]);
  if(!staff) return <PageLoader label="Loading staff workload…"/>;
  return <div className="workspace-page"><PageIntro eyebrow="Administration" title="Staff workload" text="View workloads, active work and overdue assignments. Reassign work from each job."/>
    <section className="panel workflow-panel">{staff.length?<div className="table-scroll"><table className="data-table"><thead><tr><th>Staff</th><th>Active</th><th>Total</th><th>Overdue</th><th>Actions</th></tr></thead>
    <tbody>{staff.map((person)=><tr key={person.id}><td><strong>{person.name}</strong><small>{person.email}</small></td><td>{person.active}</td><td>{person.total}</td><td><span className={person.overdue?'workflow-overdue':''}>{person.overdue}</span></td><td><Link className="button light small" to="/admin/workflow">Task board</Link></td></tr>)}</tbody></table></div>:<EmptyState icon="users" title="No staff accounts found"/>}</section>
  </div>;
}
