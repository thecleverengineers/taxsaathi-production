import { useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../lib/api';
import Icon from '../components/Icons';
import { EmptyState, PageIntro, PageLoader, StatusBadge } from '../components/Ui';
import { dateLabel, money, statusLabel } from '../lib/format';

export default function PartnerJobsPage() {
  const [orders, setOrders] = useState(null);
  const [search, setSearch] = useState('');

  useEffect(() => {
    api('/orders').then((payload) => setOrders(payload.orders || [])).catch(() => setOrders([]));
  }, []);

  const jobs = useMemo(() => (orders || []).filter((order) =>
    !search || [order.order_no, order.service?.title, order.customer_name, order.customer_email]
      .join(' ').toLowerCase().includes(search.toLowerCase())
  ), [orders, search]);

  if (!orders) return <PageLoader label="Loading service jobs…" />;

  return <div className="workspace-page">
    <PageIntro eyebrow="Partner workspace" title="Service jobs" text="Submit client work, follow staff progress, reply to questions, and access approved deliverables." actions={<Link className="button dark" to="/services">Submit a job <Icon name="plus" size={16} /></Link>} />
    <div className="filter-panel">
      <div className="search-field compact"><Icon name="search" size={17} /><input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Search job, service, or client…" /></div>
    </div>
    {jobs.length ? <div className="orders-table-card"><div className="table-scroll"><table className="data-table orders-table"><thead><tr><th>Job</th><th>Client</th><th>Service</th><th>Amount</th><th>Payment</th><th>Status</th><th>Created</th><th /></tr></thead><tbody>{jobs.map((job) => <tr key={job.id}><td><Link className="table-primary" to={`/partner/jobs/${job.id}`}>{job.order_no || `#${job.id}`}</Link></td><td><strong>{job.customer_name || job.client?.name || 'Client details pending'}</strong><small>{job.customer_email || job.client?.email || ''}</small></td><td>{job.service?.title || 'Tax service'}</td><td>{money(job.payable_amount || job.fee_amount)}</td><td><StatusBadge value={job.payment_status || 'not_due'} /></td><td><StatusBadge value={statusLabel(job.status)} /></td><td>{dateLabel(job.created_at)}</td><td><Link className="icon-link" to={`/partner/jobs/${job.id}`}><Icon name="chevronRight" size={17} /></Link></td></tr>)}</tbody></table></div></div> : <EmptyState icon="orders" title="No service jobs yet." text="Choose a TaxSaathi service and submit the client information to start a job." action={<Link className="button dark" to="/services">Browse services</Link>} />}
  </div>;
}
