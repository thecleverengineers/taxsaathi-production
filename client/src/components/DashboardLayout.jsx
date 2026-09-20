import { useEffect, useMemo, useState } from 'react';
import { Link, NavLink, Outlet, useLocation } from 'react-router-dom';
import { api, assetUrl } from '../lib/api';
import { useAuth } from './AuthContext';
import Icon from './Icons';
import { statusLabel } from '../lib/format';

const nav = {
  admin: [
    ['Dashboard', '/dashboard', 'dashboard'], ['Orders', '/admin/orders', 'orders'], ['Services', '/admin/services', 'briefcase'], ['Users', '/admin/users', 'users'], ['Partners', '/admin/partners', 'users'], ['Executives', '/admin/executive', 'users'], ['Operations', '/admin/operations', 'activity'], ['SEO & marketing', '/admin/marketing', 'trending'], ['Intelligence lab', '/admin/intelligence', 'sparkles'], ['Reports', '/admin/reports', 'bar'], ['Audit logs', '/admin/audit', 'shield'], ['Application management', '/admin/management', 'settings'], ['Coupons & payments', '/admin/payment-settings', 'wallet'], ['Notifications', '/notifications', 'bell'], ['Notification workflows', '/admin/notification-workflows', 'bell'], ['Content & settings', '/admin/content', 'settings']
  ],
  manager: [
    ['Dashboard', '/dashboard', 'dashboard'], ['Orders', '/admin/orders', 'orders'], ['Partners', '/admin/partners', 'users'], ['Operations', '/admin/operations', 'activity'], ['Reports', '/admin/reports', 'bar'], ['Audit logs', '/admin/audit', 'shield'], ['Notifications', '/notifications', 'bell'], ['Support inbox', '/support', 'support']
  ],
  executive: [
    ['Dashboard', '/dashboard', 'dashboard'], ['Assigned orders', '/admin/orders', 'orders'], ['Reports', '/admin/reports', 'bar'], ['Payouts', '/executive/payouts', 'wallet'], ['Notifications', '/notifications', 'bell']
  ],
  partners: [['Dashboard', '/partner/dashboard', 'dashboard'], ['Partner orders', '/partner/orders', 'orders'], ['Services', '/services', 'briefcase'], ['Profile', '/partner/profile', 'userCircle']],
  partner: [['Dashboard', '/partner/dashboard', 'dashboard'], ['Partner orders', '/partner/orders', 'orders'], ['Services', '/services', 'briefcase'], ['Profile', '/partner/profile', 'userCircle']],
  client: [['Dashboard', '/dashboard', 'dashboard'], ['My orders', '/client/orders', 'orders'], ['Services', '/services', 'briefcase'], ['Support', '/support', 'support'], ['Profile', '/client/profile', 'userCircle']]
};

export default function DashboardLayout() {
  const { user, logout } = useAuth();
  const location = useLocation();
  const [sidebar, setSidebar] = useState(false);
  const [notifications, setNotifications] = useState([]);
  const [notificationOpen, setNotificationOpen] = useState(false);
  const [site, setSite] = useState({ site_name: 'TaxSaathi', site_logo: '' });
  const role = user?.role?.slug || 'client';
  const navItems = useMemo(() => nav[role] || nav.client, [role]);
  const unread = notifications.filter((item) => !item.is_read).length;

  useEffect(() => {
    api('/notifications?limit=30').then((payload) => setNotifications(payload.items || [])).catch(() => {});
    api('/meta').then((payload) => setSite(payload.settings)).catch(() => {});
  }, [location.pathname]);

  useEffect(() => {
    const source = new EventSource('/api/notifications/stream', { withCredentials: true });
    source.addEventListener('notification', (event) => {
      try { setNotifications((current) => [JSON.parse(event.data), ...current].slice(0, 30)); } catch {}
    });
    return () => source.close();
  }, []);

  const isActive = (path) => location.pathname === path || (path !== '/dashboard' && location.pathname.startsWith(path));
  const pageName = navItems.find((item) => isActive(item[1]))?.[0] || 'Workspace';

  async function markRead(id) {
    await api(`/notifications/${id}/read`, { method: 'POST' }).catch(() => {});
    setNotifications((current) => current.map((item) => item.id === id ? { ...item, is_read: true } : item));
  }

  async function markAllRead() {
    await api('/notifications/read-all', { method: 'POST' }).catch(() => {});
    setNotifications((current) => current.map((item) => ({ ...item, is_read: true })));
  }

  return (
    <div className="dashboard-app">
      <div className={`sidebar-backdrop ${sidebar ? 'show' : ''}`} onClick={() => setSidebar(false)} />
      <aside className={`dashboard-sidebar ${sidebar ? 'open' : ''}`}>
        <div className="sidebar-top"><Link className="sidebar-brand" to="/dashboard"><span className="sidebar-logo"><img src={assetUrl(site.site_logo)} alt="" /></span><span><strong>{site.site_name}</strong><small>{user?.role?.name || 'Workspace'}</small></span></Link><button className="icon-button mobile-only" onClick={() => setSidebar(false)}><Icon name="close" /></button></div>
        <div className="sidebar-section-label">Workspace</div>
        <nav className="sidebar-nav">{navItems.map(([label, path, icon]) => <NavLink key={path} to={path} className={isActive(path) ? 'active' : ''} onClick={() => setSidebar(false)}><span className="nav-icon"><Icon name={icon} size={17} /></span><span>{label}</span>{isActive(path) && <b />}</NavLink>)}</nav>
        <button className="sidebar-logout" onClick={logout}><Icon name="logout" size={17} /> Sign out</button>
      </aside>
      <div className="dashboard-main">
        <header className="dashboard-header">
          <div className="header-left"><button className="icon-button mobile-only" onClick={() => setSidebar(true)}><Icon name="menu" /></button><div><div className="breadcrumb"><span>TaxSaathi</span><i>•</i><span>{user?.role?.name || 'Workspace'}</span></div><h1>{pageName}</h1></div></div>
          <label className="dashboard-search"><Icon name="search" size={15} /><input aria-label="Search workspace" placeholder="Search pages, orders, charts…" /><kbd>⌘K</kbd></label>
          <div className="header-actions"><div className="notification-wrap"><button className="notification-button" onClick={() => setNotificationOpen((value) => !value)}><Icon name="bell" size={19} />{unread > 0 && <em>{unread > 99 ? '99+' : unread}</em>}</button>{notificationOpen && <div className="notification-panel"><div className="notification-panel-head"><div><small>Activity center</small><strong>Notifications</strong></div><button onClick={markAllRead}>Mark all read</button></div><div className="notification-list">{notifications.length ? notifications.slice(0, 12).map((item) => <button key={`${item.id}-${item.uid || ''}`} className={`notification-item ${item.is_read ? '' : 'unread'}`} onClick={() => markRead(item.id)}><span className={`notification-dot ${item.severity || 'info'}`} /><span><strong>{item.title}</strong><small>{item.message}</small><i>{item.time_ago || 'Recently'}</i></span></button>) : <div className="empty-panel">No new activity.</div>}</div><Link to="/notifications" className="notification-footer" onClick={() => setNotificationOpen(false)}>Open notification center <Icon name="arrow" size={14} /></Link></div>}</div><div className="profile-chip"><span className="avatar small">{(user?.name || 'U').slice(0, 1).toUpperCase()}</span><span>{user?.name || 'User'}</span><Icon name="chevronDown" size={14} /></div></div>
        </header>
        <main className="dashboard-content"><Outlet /></main>
        <nav className="mobile-bottom-nav">{navItems.slice(0, 5).map(([label, path, icon]) => <Link key={path} to={path} className={isActive(path) ? 'active' : ''}><Icon name={icon} size={18} /><span>{label.split(' ')[0]}</span></Link>)}</nav>
      </div>
    </div>
  );
}
