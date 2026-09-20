import { useEffect, useMemo, useState } from 'react';
import { Link, Navigate } from 'react-router-dom';
import { api } from '../lib/api';
import { useAuth } from '../components/AuthContext';
import Icon from '../components/Icons';
import { EmptyState, OrderRow, PageLoader } from '../components/Ui';
import { money, statusLabel } from '../lib/format';

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

function AdminCommandMetric({ metric }) {
  return <Link className="dashboard-command-metric" to={metric.to}><span className="dashboard-command-icon"><Icon name={metric.icon} size={15} /></span><span><small>{metric.label}</small><strong>{metric.value}</strong><em>{metric.note}</em></span><Icon name="arrow" size={13} /></Link>;
}

export default function DashboardHomePage() {
  const { user } = useAuth();
  const [data, setData] = useState(null);
  const [reports, setReports] = useState(null);
  const role = user?.role?.slug || 'client';
  const isClient = role === 'client';
  const isExecutive = role === 'executive';
  const isPartner = ['partner', 'partners'].includes(role);
  const isGlobal = ['admin', 'manager'].includes(role);
  const needsReports = isGlobal || isExecutive;

  useEffect(() => {
    if (isPartner) return;
    const requests = [api('/dashboard')];
    if (needsReports) requests.push(api('/admin/reports'));
    Promise.all(requests)
      .then(([dashboard, reportData]) => { setData(dashboard); setReports(reportData || null); })
      .catch(() => { setData({ stats: [], recentOrders: [], statusSummary: [], notifications: [] }); setReports(null); });
  }, [isPartner, needsReports]);

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
  const reportSummary = reports?.summary || {};
  const analyticsStatuses = reports?.statuses?.length ? reports.statuses : statusSummary;
  const analyticsMax = Math.max(1, ...analyticsStatuses.map((item) => Number(item.total || 0)));
  const completedCount = Number(reportSummary.completed ?? statusSummary.find((item) => item.status === 'completed')?.total ?? 0);
  const orderCount = Number(reportSummary.orders ?? totalStatus);
  const completionRate = orderCount ? Math.round((completedCount / orderCount) * 100) : 0;
  const averageValue = reports ? money(reportSummary.average) : '—';
  const revenueValue = reports ? money(reportSummary.revenue) : (data.stats?.find((stat) => /value/i.test(stat.label))?.value || '—');
  const activeServices = data.stats?.find((stat) => /service/i.test(stat.label))?.value || '—';
  const paidValue = data.stats?.find((stat) => /paid/i.test(stat.label))?.value || '—';
  const commandMetrics = data.adminMetrics ? [
    { label: 'Active users', value: data.adminMetrics.active_users, note: `${data.adminMetrics.total_users} total`, icon: 'users', to: '/admin/users' },
    { label: 'Corporate accounts', value: data.adminMetrics.corporate_accounts, note: 'Customer organizations', icon: 'briefcase', to: '/admin/partners' },
    { label: 'Active cases', value: data.adminMetrics.active_cases, note: `${data.adminMetrics.completed_cases} completed`, icon: 'orders', to: '/admin/orders' },
    { label: 'SLA-risk cases', value: data.adminMetrics.sla_risk_cases, note: 'Needs attention', icon: 'activity', to: '/admin/orders' },
    { label: 'Documents to verify', value: data.adminMetrics.documents_awaiting_verification, note: 'Review queue', icon: 'file', to: '/admin/orders' },
    { label: 'Payments pending', value: data.adminMetrics.payments_pending, note: `${data.adminMetrics.outstanding_invoices} invoices open`, icon: 'wallet', to: '/admin/reports' },
    { label: 'Security alerts', value: data.adminMetrics.security_alerts, note: `${data.adminMetrics.audit_events} audit events`, icon: 'shield', to: '/admin/audit' },
    { label: 'Subscriptions active', value: data.adminMetrics.active_subscriptions, note: `${data.adminMetrics.subscriptions_expiring} expiring soon`, icon: 'trending', to: '/admin/reports' }
  ] : [];

  return (
    <div className="workspace-page reference-dashboard-page">
      <section className="dashboard-overview-head">
        <div>
          <span className="dashboard-overline">{scope.eyebrow}</span>
          <h2>{scope.title}</h2>
          <p>{scope.intro}</p>
        </div>
        <div className="dashboard-overview-actions">
          <span className="dashboard-data-status"><i /> Live data</span>
          <Link className="dashboard-outline-button" to={scope.secondary.to}>{scope.secondary.label}</Link>
          <Link className="dashboard-primary-button" to={scope.primary.to}><Icon name={scope.primary.icon} size={15} /> {scope.primary.label}</Link>
        </div>
      </section>

      <div className="dashboard-kpi-grid">
        {(data.stats || []).slice(0, 4).map((stat, index) => <DashboardKpi key={stat.label} stat={stat} index={index} />)}
      </div>

      <section className="dashboard-quick-actions-panel">
        <div className="dashboard-quick-actions-heading"><div><span>Workspace shortcuts</span><h3>Quick actions</h3></div><small>Available for your role</small></div>
        <div className="dashboard-quick-actions-grid">
          {scope.actions.map(([label, to, icon]) => <Link className="dashboard-quick-action" to={to} key={to}><span><Icon name={icon} size={17} /></span><strong>{label}</strong><Icon name="arrow" size={13} /></Link>)}
        </div>
      </section>

      {commandMetrics.length ? <section className="dashboard-command-center"><div className="dashboard-command-heading"><div><span className="dashboard-overline">Admin command center</span><h3>Operational control room</h3><p>Drill into the live queues that need attention.</p></div><Link className="dashboard-panel-link" to="/admin/reports">View performance <Icon name="arrow" size={13} /></Link></div><div className="dashboard-command-grid">{commandMetrics.map((metric) => <AdminCommandMetric key={metric.label} metric={metric} />)}</div></section> : null}

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

      <section className="dashboard-analytics-shell">
        <div className="dashboard-analytics-heading">
          <div><span className="dashboard-overline">Decision intelligence</span><h3>Performance analytics</h3><p>Role-scoped signals from your live TaxSaathi workflow.</p></div>
          {needsReports && <Link className="dashboard-panel-link" to="/admin/reports">Open full reports <Icon name="arrow" size={13} /></Link>}
        </div>
        <div className="dashboard-analytics-grid">
          <section className="dashboard-reference-panel dashboard-insight-panel">
            <div className="dashboard-panel-heading"><div><span>At a glance</span><h3>Business health</h3></div><Icon name="activity" size={16} /></div>
            <div className="dashboard-insight-metrics">
              <div><small>Completion rate</small><strong>{completionRate}%</strong><span><i style={{ width: `${completionRate}%` }} /></span></div>
              <div><small>Average order value</small><strong>{averageValue}</strong><em>Across visible orders</em></div>
              <div><small>Workflow volume</small><strong>{orderCount}</strong><em>{completedCount} completed</em></div>
              <div><small>Tracked value</small><strong>{revenueValue}</strong><em>{paidValue} paid / approved</em></div>
            </div>
          </section>
          <section className="dashboard-reference-panel dashboard-service-panel">
            <div className="dashboard-panel-heading"><div><span>Portfolio mix</span><h3>Top services by value</h3></div><span className="dashboard-period-pill">{activeServices} active</span></div>
            {reports?.topServices?.length ? <div className="dashboard-service-ranking">{reports.topServices.slice(0, 5).map((item, index) => { const maxValue = Math.max(1, ...reports.topServices.map((service) => Number(service.amount || 0))); return <div className="dashboard-service-row" key={item.service}><span className="dashboard-service-rank">{String(index + 1).padStart(2, '0')}</span><div><strong>{item.service}</strong><span><i style={{ width: `${Math.max(5, (Number(item.amount || 0) / maxValue) * 100)}%` }} /></span></div><b>{money(item.amount)}</b></div>; })}</div> : <div className="dashboard-analytics-empty"><Icon name="bar" size={18} /><strong>{needsReports ? 'No service revenue yet' : 'Detailed mix is role restricted'}</strong><small>{needsReports ? 'Service value will appear as orders are created.' : 'Your dashboard only shows analytics permitted for your account.'}</small></div>}
          </section>
        </div>
        <section className="dashboard-reference-panel dashboard-status-panel">
          <div className="dashboard-panel-heading"><div><span>Operational pulse</span><h3>Workflow throughput</h3></div><span className="dashboard-period-pill">{analyticsStatuses.length} tracked stages</span></div>
          {analyticsStatuses.length ? <div className="dashboard-status-bars">{analyticsStatuses.slice(0, 7).map((item, index) => <div className="dashboard-status-bar" key={item.status}><div><span>{item.label || statusLabel(item.status)}</span><b>{item.total}</b></div><span><i style={{ width: `${Math.max(6, (Number(item.total || 0) / analyticsMax) * 100)}%`, background: chartColors[index % chartColors.length] }} /></span></div>)}</div> : <div className="dashboard-analytics-empty"><Icon name="activity" size={18} /><strong>No workflow stages to analyze</strong><small>New activity will populate this view automatically.</small></div>}
        </section>
      </section>

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
