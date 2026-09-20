import { useEffect, useMemo, useState } from 'react';
import { Link, Navigate } from 'react-router-dom';
import { api } from '../lib/api';
import { useAuth } from '../components/AuthContext';
import Icon from '../components/Icons';
import { EmptyState, OrderRow, PageLoader } from '../components/Ui';
import { statusLabel } from '../lib/format';

const chartColors = ['#087f73', '#f59e0b', '#e83e72', '#8b7cf6', '#39b779', '#2c6f88', '#d97706'];

function donutStyle(items) {
  const total = items.reduce((sum, item) => sum + Number(item.total || 0), 0);
  if (!total) return { background: '#d9e7e4' };
  let cursor = 0;
  const stops = items.map((item, index) => {
    const start = cursor;
    cursor += (Number(item.total || 0) / total) * 100;
    return `${chartColors[index % chartColors.length]} ${start}% ${cursor}%`;
  });
  return { background: `conic-gradient(${stops.join(', ')})` };
}

function DashboardKpi({ stat, index }) {
  return <article className={`dashboard-kpi-card dashboard-kpi-${index % 4}`}>
    <div className="dashboard-kpi-top"><span>{stat.label}</span><Icon name={stat.icon || 'activity'} size={16} /></div>
    <strong>{stat.value}</strong>
    <div className="dashboard-kpi-bottom"><small>{stat.note || 'Current workspace total'}</small><b>{index % 2 === 0 ? '↗' : '•'}</b></div>
  </article>;
}

export default function DashboardHomePage() {
  const { user } = useAuth();
  const [data, setData] = useState(null);
  const role = user?.role?.slug || 'client';
  const isClient = role === 'client';
  const isExecutive = role === 'executive';
  const isPartner = ['partner', 'partners'].includes(role);
  const isGlobal = ['admin', 'manager'].includes(role);

  useEffect(() => {
    if (isPartner) return;
    api('/dashboard')
      .then(setData)
      .catch(() => setData({ stats: [], recentOrders: [], statusSummary: [], notifications: [] }));
  }, [isPartner]);

  const scope = useMemo(() => {
    if (isClient) return {
      eyebrow: 'Client workspace',
      title: 'Your tax workspace',
      intro: 'Applications, documents, payments, and updates stay in one private workspace.',
      primary: { label: 'Start a service', to: '/services', icon: 'arrow' },
      secondary: { label: 'Get support', to: '/support' },
      orders: '/client/orders',
      orderLabel: 'My orders',
      panelTitle: 'Your client workflow',
      panelText: 'Review your order, payment state, documents, or support conversations.',
      actions: [['My orders', '/client/orders', 'orders'], ['Browse services', '/services', 'briefcase'], ['Support', '/support', 'support']]
    };
    if (isExecutive) return {
      eyebrow: 'Tax executive workspace',
      title: 'Assigned work overview',
      intro: 'Review assigned cases, update workflow progress, and follow your own performance.',
      primary: { label: 'Assigned cases', to: '/admin/orders', icon: 'orders' },
      secondary: { label: 'View my reports', to: '/admin/reports' },
      orders: '/admin/orders',
      orderLabel: 'Assigned cases',
      panelTitle: 'Assigned workflow',
      panelText: 'Only cases assigned to your executive account are included in this dashboard.',
      actions: [['Assigned cases', '/admin/orders', 'orders'], ['My reports', '/admin/reports', 'bar'], ['My payouts', '/executive/payouts', 'wallet']]
    };
    return {
      eyebrow: isGlobal ? `${user?.role?.name || 'Operations'} workspace` : 'Workspace',
      title: isGlobal ? 'Tax operations overview' : 'Workspace overview',
      intro: isGlobal ? 'Monitor authorized operations across orders, services, users, and reports.' : 'Your role determines which workspace data and actions are available.',
      primary: { label: 'Open orders', to: '/admin/orders', icon: 'orders' },
      secondary: { label: 'Open reports', to: '/admin/reports' },
      orders: '/admin/orders',
      orderLabel: 'Orders',
      panelTitle: 'Operations overview',
      panelText: 'Review workflow progress, payments, assignments, and operational activity.',
      actions: [['Orders', '/admin/orders', 'orders'], ['Reports', '/admin/reports', 'bar'], ['Notifications', '/notifications', 'bell']]
    };
  }, [isClient, isExecutive, isGlobal, isPartner, role, user?.role?.name]);

  if (isPartner) return <Navigate to="/partner/dashboard" replace />;
  if (!data) return <PageLoader label="Loading your workspace…" />;

  const statusSummary = data.statusSummary || [];
  const totalStatus = statusSummary.reduce((sum, item) => sum + Number(item.total || 0), 0);
  const maxStatus = Math.max(1, ...statusSummary.map((item) => Number(item.total || 0)));
  const chartItems = statusSummary.slice(0, 7);

  return (
    <div className="workspace-page reference-dashboard-page">
      <section className="dashboard-overview-head">
        <div>
          <span className="dashboard-overline">{scope.eyebrow}</span>
          <h2>{scope.title}</h2>
          <p>{scope.intro}</p>
        </div>
        <div className="dashboard-overview-actions">
          <Link className="dashboard-outline-button" to={scope.secondary.to}>{scope.secondary.label}</Link>
          <Link className="dashboard-primary-button" to={scope.primary.to}><Icon name={scope.primary.icon} size={15} /> {scope.primary.label}</Link>
        </div>
      </section>

      <div className="dashboard-kpi-grid">
        {(data.stats || []).slice(0, 4).map((stat, index) => <DashboardKpi key={stat.label} stat={stat} index={index} />)}
      </div>

      <div className="dashboard-chart-grid">
        <section className="dashboard-reference-panel dashboard-donut-panel">
          <div className="dashboard-panel-heading"><div><span>Workflow status</span><h3>Case distribution</h3></div><button className="dashboard-live-pill">Live</button></div>
          <div className="dashboard-donut-layout">
            <div className="dashboard-donut" style={donutStyle(statusSummary)}><div><strong>{totalStatus}</strong><small>Total</small></div></div>
            <div className="dashboard-legend">
              {statusSummary.slice(0, 5).map((item, index) => <div key={item.status}><span style={{ background: chartColors[index % chartColors.length] }} /><b>{statusLabel(item.status)}</b><small>{item.total}</small></div>)}
              {!statusSummary.length && <EmptyState title="No workflow status yet." />}
            </div>
          </div>
        </section>

        <section className="dashboard-reference-panel dashboard-performance-panel">
          <div className="dashboard-panel-heading"><div><span>Workflow activity</span><h3>Recent performance</h3></div><span className="dashboard-period-pill">Current period⌄</span></div>
          {chartItems.length ? <div className="dashboard-column-chart">{chartItems.map((item, index) => <div className="dashboard-chart-column" key={item.status}><div className="dashboard-column-track"><i style={{ height: `${Math.max(16, (Number(item.total || 0) / maxStatus) * 100)}%`, background: chartColors[index % chartColors.length] }} /></div><small>{statusLabel(item.status).split(' ')[0]}</small></div>)}</div> : <EmptyState title="No recent workflow activity." />}
        </section>
      </div>

      <section className="dashboard-reference-panel dashboard-utilization-panel">
        <div className="dashboard-panel-heading"><div><span>Service workflow</span><h3>Current utilization</h3></div><span className="dashboard-period-pill">Open details <Icon name="arrow" size={12} /></span></div>
        <div className="dashboard-utilization-grid">
          {statusSummary.slice(0, 4).map((item, index) => <div className="dashboard-utilization-card" key={item.status}><div className="dashboard-utilization-icon"><Icon name={index % 2 ? 'file' : 'briefcase'} size={15} /></div><div><strong>{statusLabel(item.status)}</strong><small>{item.total} active record{item.total === 1 ? '' : 's'}</small></div><b>{Math.round((Number(item.total || 0) / maxStatus) * 100)}%</b><span><i style={{ width: `${Math.max(8, (Number(item.total || 0) / maxStatus) * 100)}%` }} /></span></div>)}
          {!statusSummary.length && <EmptyState title="No service activity yet." />}
        </div>
      </section>

      <div className="dashboard-lower-grid">
        <section className="dashboard-reference-panel dashboard-orders-panel">
          <div className="dashboard-panel-heading"><div><span>Latest workflow</span><h3>{scope.orderLabel}</h3></div><Link to={scope.orders} className="dashboard-panel-link">View all <Icon name="arrow" size={13} /></Link></div>
          {data.recentOrders?.length ? <div className="dashboard-order-list">{data.recentOrders.slice(0, 5).map((order) => <OrderRow key={order.id} order={order} href={`${scope.orders}/${order.id}`} />)}</div> : <EmptyState icon="orders" title={`No ${isClient ? 'orders' : 'assigned cases'} yet.`} text={isClient ? 'Start a service to create your first order.' : 'New related work will appear here.'} action={isClient && <Link className="dashboard-primary-button" to="/services">Explore services</Link>} />}
        </section>
        <section className="dashboard-reference-panel dashboard-activity-panel">
          <div className="dashboard-panel-heading"><div><span>Activity center</span><h3>Recent notifications</h3></div><Link to="/notifications" className="dashboard-panel-link">Open <Icon name="arrow" size={13} /></Link></div>
          <div className="dashboard-activity-list">{data.notifications?.length ? data.notifications.slice(0, 5).map((item) => <div className="dashboard-activity-row" key={`${item.id}-${item.uid || ''}`}><span className={`notification-dot ${item.severity || 'info'}`} /><div><strong>{item.title}</strong><p>{item.message}</p><small>{item.time_ago}</small></div></div>) : <EmptyState icon="bell" title="You’re all caught up." />}</div>
        </section>
      </div>
    </div>
  );
}
