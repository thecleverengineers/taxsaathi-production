import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../lib/api';
import { useAuth } from '../components/AuthContext';
import Icon from '../components/Icons';
import { EmptyState, PageIntro, PageLoader, StatCard, StatusBadge, Table } from '../components/Ui';
import { dateLabel, money } from '../lib/format';

export default function PartnerDashboardPage() {
  const { user } = useAuth();
  const [data, setData] = useState(null);

  useEffect(() => {
    api('/partner/dashboard')
      .then(setData)
      .catch(() => setData({ stats: {}, orders: [], referrals: [], referred_clients: [] }));
  }, []);

  if (!data) return <PageLoader label="Loading your partner workspace…" />;

  const stats = data.stats || {};
  const orders = data.orders || [];
  const role = user?.role?.slug;
  const isGlobal = ['admin', 'manager'].includes(role);

  return (
    <div className="workspace-page">
      <PageIntro
        eyebrow={isGlobal ? 'Partner operations' : 'Your partner workspace'}
        title={isGlobal ? 'Partner performance' : `Good to see you, ${(user?.name || 'partner').split(' ')[0]}.`}
        text={isGlobal ? 'Review partner activity from the authorized operations scope.' : 'Track only your own referred orders, approvals, payments, and partner performance.'}
        actions={<Link className="button light" to="/partner/orders">Open partner orders <Icon name="arrow" size={16} /></Link>}
      />

      <div className="stats-grid">
        <StatCard label="My partner orders" value={stats.total_orders || 0} icon="orders" tone="indigo" />
        <StatCard label="Approved orders" value={stats.approved || 0} icon="check" tone="cyan" />
        <StatCard label="Paid orders" value={stats.paid || 0} icon="paid" tone="emerald" />
        <StatCard label="Own order value" value={money(stats.value)} icon="revenue" tone="violet" />
        <StatCard label="My commissions" value={money(stats.commissions)} icon="wallet" tone="amber" />
        <StatCard label="My referrals" value={stats.referrals || 0} icon="users" tone="slate" />
      </div>

      <div className="workspace-grid">
        <section className="panel">
          <div className="panel-heading">
            <div><span className="eyebrow">Own pipeline</span><h3>Recent partner orders</h3></div>
            <Link to="/partner/orders" className="text-link">View all <Icon name="arrow" size={14} /></Link>
          </div>
          {orders.length ? (
            <Table
              rows={orders}
              columns={[
                { key: 'order_no', label: 'Order', render: (row) => <Link className="table-primary" to={`/partner/orders/${row.id}`}>{row.order_no || `#${row.id}`}</Link> },
                { key: 'service_id', label: 'Service', render: (row) => row.service?.title || `Service #${row.service_id}` },
                { key: 'payable_amount', label: 'Value', render: (row) => money(row.payable_amount || row.filing_fee) },
                { key: 'order_status', label: 'Status', render: (row) => <StatusBadge value={row.order_status || row.status} /> },
                { key: 'created_at', label: 'Created', render: (row) => dateLabel(row.created_at) }
              ]}
            />
          ) : <EmptyState icon="orders" title="No partner orders yet." text="Your referred orders will appear here." />}
        </section>

        <section className="panel gradient-panel">
          <span className="eyebrow">Access boundary</span>
          <h3>This workspace is limited to your partner data.</h3>
          <p>Your referral performance, orders, commissions, profile, and related notifications are scoped to your partner account.</p>
          <div className="mini-action-grid">
            <Link to="/partner/orders"><Icon name="orders" size={17} /><span>My orders</span></Link>
            <Link to="/partner/profile"><Icon name="userCircle" size={17} /><span>My profile</span></Link>
            <Link to="/support"><Icon name="support" size={17} /><span>Support</span></Link>
          </div>
        </section>
      </div>
    </div>
  );
}
