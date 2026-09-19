import { useEffect, useMemo, useState } from 'react';
import { Link, Navigate } from 'react-router-dom';
import { api } from '../lib/api';
import { useAuth } from '../components/AuthContext';
import Icon from '../components/Icons';
import { EmptyState, OrderRow, PageIntro, PageLoader, StatCard } from '../components/Ui';
import { statusLabel } from '../lib/format';

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
      intro: 'Your applications, documents, payments, and updates stay in one private workspace.',
      primary: { label: 'Start a service', to: '/services', icon: 'arrow' },
      secondary: { label: 'Get support', to: '/support' },
      orders: '/client/orders',
      orderLabel: 'My orders',
      latestLabel: 'Latest work',
      panelTitle: 'Your client workflow',
      panelText: 'Review your order, payment state, documents, or support conversations.',
      actions: [
        ['My orders', '/client/orders', 'orders'],
        ['Browse services', '/services', 'briefcase'],
        ['Support', '/support', 'support']
      ]
    };
    if (isExecutive) return {
      eyebrow: 'Tax executive workspace',
      intro: 'Review only the cases assigned to you, update workflow progress, and follow your own performance.',
      primary: { label: 'Assigned cases', to: '/admin/orders', icon: 'orders' },
      secondary: { label: 'View my reports', to: '/admin/reports' },
      orders: '/admin/orders',
      orderLabel: 'Assigned cases',
      latestLabel: 'Latest assigned work',
      panelTitle: 'Assigned workflow',
      panelText: 'Only cases assigned to your executive account are included in this dashboard.',
      actions: [
        ['Assigned cases', '/admin/orders', 'orders'],
        ['My reports', '/admin/reports', 'bar'],
        ['My payouts', '/executive/payouts', 'wallet']
      ]
    };
    return {
      eyebrow: isGlobal ? `${user?.role?.name || 'Operations'} workspace` : 'Workspace',
      intro: isGlobal ? 'Monitor the authorized operations scope across orders, services, users, and reports.' : 'Your role determines which workspace data and actions are available.',
      primary: { label: 'Open orders', to: '/admin/orders', icon: 'orders' },
      secondary: { label: 'Open reports', to: '/admin/reports' },
      orders: '/admin/orders',
      orderLabel: 'Orders',
      latestLabel: 'Latest order activity',
      panelTitle: 'Operations overview',
      panelText: 'Review workflow progress, payments, assignments, and operational activity.',
      actions: [
        ['Orders', '/admin/orders', 'orders'],
        ['Reports', '/admin/reports', 'bar'],
        ['Notifications', '/notifications', 'bell']
      ]
    };
  }, [isClient, isExecutive, isGlobal, isPartner, role, user?.role?.name]);

  if (isPartner) return <Navigate to="/partner/dashboard" replace />;
  if (!data) return <PageLoader label="Loading your workspace…" />;
  const max = Math.max(1, ...(data.statusSummary || []).map((item) => item.total));

  return (
    <div className="workspace-page">
      <PageIntro
        eyebrow={scope.eyebrow}
        title={`Good to see you, ${(user?.name || 'there').split(' ')[0]}.`}
        text={scope.intro}
        actions={<><Link className="button dark" to={scope.primary.to}><Icon name={scope.primary.icon} size={16} /> {scope.primary.label}</Link><Link className="button light" to={scope.secondary.to}>{scope.secondary.label}</Link></>}
      />

      <div className="stats-grid">{data.stats.map((stat) => <StatCard key={stat.label} {...stat} />)}</div>

      <div className="workspace-grid">
        <section className="panel">
          <div className="panel-heading">
            <div><span className="eyebrow">Workflow pulse</span><h3>{scope.panelTitle}</h3></div>
            <Link to={scope.orders} className="text-link">{scope.orderLabel} <Icon name="arrow" size={14} /></Link>
          </div>
          <div className="bar-summary">
            {(data.statusSummary || []).map((item) => <div className="bar-row" key={item.status}><span>{statusLabel(item.status)}</span><div><i style={{ width: `${Math.max(8, item.total / max * 100)}%` }} /></div><strong>{item.total}</strong></div>)}
            {!data.statusSummary?.length && <EmptyState title="No workflow activity yet." />}
          </div>
        </section>
        <section className="panel gradient-panel">
          <span className="eyebrow">Private role scope</span>
          <h3>{scope.panelText}</h3>
          <p>Analytics and actions on this page are calculated from records related to your signed-in role.</p>
          <div className="mini-action-grid">{scope.actions.map(([label, to, icon]) => <Link to={to} key={to}><Icon name={icon} size={17} /><span>{label}</span></Link>)}</div>
        </section>
      </div>

      <div className="workspace-grid lower">
        <section className="panel">
          <div className="panel-heading"><div><span className="eyebrow">{scope.latestLabel}</span><h3>{scope.orderLabel}</h3></div><Link to={scope.orders} className="text-link">View all <Icon name="arrow" size={14} /></Link></div>
          {data.recentOrders?.length ? <div className="order-list">{data.recentOrders.map((order) => <OrderRow key={order.id} order={order} href={`${scope.orders}/${order.id}`} />)}</div> : <EmptyState icon="orders" title={`No ${isClient ? 'orders' : 'assigned cases'} yet.`} text={isClient ? 'Start a service to create your first order.' : 'New related work will appear here.'} action={isClient && <Link className="button dark" to="/services">Explore services</Link>} />}
        </section>
        <section className="panel">
          <div className="panel-heading"><div><span className="eyebrow">Activity center</span><h3>Recent notifications</h3></div><Link to="/notifications" className="icon-link"><Icon name="arrow" size={15} /></Link></div>
          <div className="activity-list">{data.notifications?.length ? data.notifications.map((item) => <div className="activity-item" key={`${item.id}-${item.uid || ''}`}><span className={`notification-dot ${item.severity || 'info'}`} /><div><strong>{item.title}</strong><p>{item.message}</p><small>{item.time_ago}</small></div></div>) : <EmptyState icon="bell" title="You’re all caught up." />}</div>
        </section>
      </div>
    </div>
  );
}
