import { useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../lib/api';
import { useAuth } from '../components/AuthContext';
import Icon from '../components/Icons';
import { EmptyState, PageIntro, PageLoader, StatusBadge } from '../components/Ui';
import { dateLabel, money, statusLabel } from '../lib/format';

export default function OrdersPage({ partner = false }) {
  const { user } = useAuth();
  const [orders, setOrders] = useState(null);
  const [filter, setFilter] = useState('all');
  const [search, setSearch] = useState('');
  const isStaff = ['admin', 'manager', 'executive'].includes(user?.role?.slug);
  useEffect(() => { api(partner ? '/partner/orders' : '/orders').then((payload) => setOrders(payload.orders || [])).catch(() => setOrders([])); }, [partner]);
  const filtered = useMemo(() => (orders || []).filter((order) => (filter === 'all' || order.status === filter) && (!search || JSON.stringify(order).toLowerCase().includes(search.toLowerCase()))), [orders, filter, search]);
  if (!orders) return <PageLoader label="Loading orders…" />;
  const statuses = [...new Set(orders.map((order) => order.status || order.order_status || 'pending'))];
  const linkFor = (order) => partner ? `/partner/orders/${order.id}` : isStaff ? `/admin/orders/${order.id}` : `/client/orders/${order.id}`;
  return <div className="workspace-page"><PageIntro eyebrow={partner ? 'Partner workspace' : isStaff ? 'Operations workspace' : 'Client portal'} title={partner ? 'Partner orders' : 'Orders and applications'} text={partner ? 'Review partner-submitted orders and their approval and payment states.' : 'Every service application and document workflow stays connected to its order record.'} actions={!partner && <Link className="button dark" to="/services">New service <Icon name="plus" size={16} /></Link>} /><div className="filter-panel"><div className="search-field compact"><Icon name="search" size={17} /><input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Search order, service, or client…" /></div><div className="filter-pills dark-pills"><button className={filter === 'all' ? 'active' : ''} onClick={() => setFilter('all')}>All <span>{orders.length}</span></button>{statuses.map((status) => <button className={filter === status ? 'active' : ''} key={status} onClick={() => setFilter(status)}>{statusLabel(status)} <span>{orders.filter((order) => (order.status || order.order_status) === status).length}</span></button>)}</div></div>{filtered.length ? <div className="orders-table-card"><div className="table-scroll"><table className="data-table orders-table"><thead><tr><th>Order</th><th>{isStaff ? 'Client' : 'Service'}</th><th>Amount</th><th>Created</th><th>Status</th><th /></tr></thead><tbody>{filtered.map((order) => <tr key={order.id}><td><Link className="table-primary" to={linkFor(order)}>{order.order_no || `#${order.id}`}</Link><small>{order.payment_status || 'Payment pending'}</small></td><td><strong>{isStaff ? (order.client?.name || `Client #${order.client_id}`) : (order.service?.title || 'Tax service')}</strong><small>{isStaff ? (order.service?.title || 'Tax service') : (order.service?.category?.title || 'TaxSaathi')}</small></td><td><strong>{money(order.payable_amount || order.fee_amount || order.filing_fee)}</strong><small>{order.payment_status || 'pending'}</small></td><td>{dateLabel(order.created_at)}</td><td><StatusBadge value={order.status || order.order_status} /></td><td><Link className="icon-link" to={linkFor(order)}><Icon name="chevronRight" size={17} /></Link></td></tr>)}</tbody></table></div></div> : <EmptyState icon="orders" title="No matching orders." text="Try changing the filter or begin a new service." action={!partner && <Link className="button dark" to="/services">Browse services</Link>} />}</div>;
}
