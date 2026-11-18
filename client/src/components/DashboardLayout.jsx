import { useEffect, useMemo, useRef, useState } from 'react';
import { Link, NavLink, Outlet, useLocation } from 'react-router-dom';
import { api } from '../lib/api';
import { useAuth } from './AuthContext';
import Icon from './Icons';
import WorkspaceSearch from './WorkspaceSearch';
import { statusLabel } from '../lib/format';

const nav = {
  admin: [
    ['Dashboard', '/dashboard', 'dashboard'], ['Task board', '/admin/workflow', 'activity'], ['Orders', '/admin/orders', 'orders'], ['Clients', '/admin/clients', 'users'], ['Staff workload', '/admin/staff', 'users'], ['Services', '/admin/services', 'briefcase'], ['Users', '/admin/users', 'users'], ['Partners', '/admin/partners', 'users'], ['Executives', '/admin/executive', 'users'], ['Operations', '/admin/operations', 'activity'], ['SEO & marketing', '/admin/marketing', 'trending'], ['Intelligence lab', '/admin/intelligence', 'sparkles'], ['Reports', '/admin/reports', 'bar'], ['Audit logs', '/admin/audit', 'shield'], ['Application management', '/admin/management', 'settings'], ['Coupons & payments', '/admin/payment-settings', 'wallet'], ['Notifications', '/notifications', 'bell'], ['Notification workflows', '/admin/notification-workflows', 'bell'], ['Content & settings', '/admin/content', 'settings']
  ],
  manager: [
    ['Dashboard', '/dashboard', 'dashboard'], ['Task board', '/admin/workflow', 'activity'], ['Orders', '/admin/orders', 'orders'], ['Services', '/admin/services', 'briefcase'], ['Clients', '/admin/clients', 'users'], ['Staff workload', '/admin/staff', 'users'], ['Partners', '/admin/partners', 'users'], ['Operations', '/admin/operations', 'activity'], ['Reports', '/admin/reports', 'bar'], ['Audit logs', '/admin/audit', 'shield'], ['Notifications', '/notifications', 'bell'], ['Support inbox', '/support', 'support']
  ],
  executive: [
    ['Dashboard', '/dashboard', 'dashboard'], ['My task board', '/staff/workflow', 'activity'], ['Assigned orders', '/admin/orders', 'orders'], ['Reports', '/admin/reports', 'bar'], ['Payouts', '/executive/payouts', 'wallet'], ['Notifications', '/notifications', 'bell']
  ],
  partners: [['Dashboard', '/partner/dashboard', 'dashboard'], ['Partner orders', '/partner/orders', 'orders'], ['Clients', '/partner/clients', 'users'], ['Service jobs', '/partner/jobs', 'orders'], ['Services', '/services', 'briefcase'], ['Profile', '/partner/profile', 'userCircle']],
  partner: [['Dashboard', '/partner/dashboard', 'dashboard'], ['Partner orders', '/partner/orders', 'orders'], ['Clients', '/partner/clients', 'users'], ['Service jobs', '/partner/jobs', 'orders'], ['Services', '/services', 'briefcase'], ['Profile', '/partner/profile', 'userCircle']],
  client: [['Dashboard', '/dashboard', 'dashboard'], ['My orders', '/client/orders', 'orders'], ['Services', '/services', 'briefcase'], ['Support', '/support', 'support'], ['Profile', '/client/profile', 'userCircle']]
};

function urlBase64ToUint8Array(value) {
  const padded = String(value || '') + '='.repeat((4 - String(value || '').length % 4) % 4);
  const binary = window.atob(padded.replace(/-/g, '+').replace(/_/g, '/'));
  return Uint8Array.from(binary, (character) => character.charCodeAt(0));
}

export default function DashboardLayout() {
  const { user, logout } = useAuth();
  const location = useLocation();
  const [sidebar, setSidebar] = useState(false);
  const [premiumMode, setPremiumMode] = useState(false);
  const [notifications, setNotifications] = useState([]);
  const [pushSupported, setPushSupported] = useState(null);
  const [pushEnabled, setPushEnabled] = useState(false);
  const [pushBusy, setPushBusy] = useState(false);
  const [pushMessage, setPushMessage] = useState('');
  const [notificationOpen, setNotificationOpen] = useState(false);
  const [profileOpen, setProfileOpen] = useState(false);
  const profileMenuRef = useRef(null);
  const [site, setSite] = useState({ site_name: 'TaxSaathi', site_logo: '' });
  const role = user?.role?.slug || 'client';
  const navItems = useMemo(() => {
    const items = nav[role] || nav.client;
    return role === 'client' && String(user?.client_type).toLowerCase() === 'corporate'
      ? [items[0], ['Company workspace', '/client/company', 'briefcase'], ...items.slice(1)] : items;
  }, [role, user?.client_type]);
  const premiumStorageKey = user?.id ? `taxsaathi:premium-mode:${user.id}` : '';

  useEffect(() => {
    if (!profileOpen) return undefined;
    function dismissOnOutsidePointer(event) {
      if (!profileMenuRef.current?.contains(event.target)) setProfileOpen(false);
    }
    function dismissOnEscape(event) {
      if (event.key === 'Escape') setProfileOpen(false);
    }
    document.addEventListener('pointerdown', dismissOnOutsidePointer);
    document.addEventListener('keydown', dismissOnEscape);
    return () => {
      document.removeEventListener('pointerdown', dismissOnOutsidePointer);
      document.removeEventListener('keydown', dismissOnEscape);
    };
  }, [profileOpen]);

  useEffect(() => {
    if (!premiumStorageKey) { setPremiumMode(false); return; }
    try { setPremiumMode(window.localStorage.getItem(premiumStorageKey) === 'on'); }
    catch { setPremiumMode(false); }
  }, [premiumStorageKey]);

  function togglePremiumMode() {
    if (!premiumStorageKey) return;
    setPremiumMode((current) => {
      const next = !current;
      try { window.localStorage.setItem(premiumStorageKey, next ? 'on' : 'off'); } catch {}
      return next;
    });
  }
  const unread = notifications.filter((item) => !item.is_read).length;

  useEffect(() => {
    let active = true;
    async function restorePushSubscription() {
      const supported = Boolean(
        window.isSecureContext &&
        'serviceWorker' in navigator &&
        'PushManager' in window &&
        'Notification' in window
      );
      if (!active) return;
      setPushSupported(supported);
      if (!supported || Notification.permission !== 'granted') return;
      const registration = await navigator.serviceWorker.register('/push-sw.js', { scope: '/' });
      const subscription = await registration.pushManager.getSubscription();
      if (!subscription) return;
      await api('/notifications/push-subscription', {
        method: 'POST',
        body: { subscription: subscription.toJSON() }
      }).catch(() => {});
      if (active) setPushEnabled(true);
    }
    restorePushSubscription().catch(() => {
      if (active) setPushMessage('Could not sync push settings for this device.');
    });
    return () => { active = false; };
  }, [user?.id]);

  async function togglePushNotifications() {
    if (pushBusy) return;
    setPushBusy(true);
    setPushMessage('');
    try {
      if (pushEnabled) {
        const registration = await navigator.serviceWorker.getRegistration('/');
        const subscription = await registration?.pushManager?.getSubscription();
        if (subscription) {
          await api('/notifications/push-subscription', {
            method: 'DELETE',
            body: { endpoint: subscription.endpoint }
          }).catch(() => {});
          await subscription.unsubscribe().catch(() => false);
        }
        setPushEnabled(false);
        setPushMessage('Push notifications are off on this device.');
        return;
      }
      if (!pushSupported) throw new Error('Push notifications are not available in this browser.');
      if (Notification.permission === 'denied') {
        throw new Error('Notifications are blocked. Allow them in this site’s browser settings.');
      }
      let permission = Notification.permission;
      if (permission === 'default') permission = await Notification.requestPermission();
      if (permission !== 'granted') throw new Error('Allow notifications to enable device alerts.');
      const config = await api('/notifications/push-public-key');
      if (!config.enabled || !config.public_key) throw new Error('Push alerts are not configured yet.');
      const registration = await navigator.serviceWorker.register('/push-sw.js', { scope: '/' });
      let subscription = await registration.pushManager.getSubscription();
      if (!subscription) {
        subscription = await registration.pushManager.subscribe({
          userVisibleOnly: true,
          applicationServerKey: urlBase64ToUint8Array(config.public_key)
        });
      }
      await api('/notifications/push-subscription', {
        method: 'POST',
        body: { subscription: subscription.toJSON() }
      });
      setPushEnabled(true);
      setPushMessage('Push notifications are enabled on this device.');
    } catch (error) {
      setPushMessage(error.message || 'Could not update push notification settings.');
    } finally {
      setPushBusy(false);
    }
  }

  const pushDetail = pushSupported === null
    ? 'Checking browser support…'
    : !pushSupported
      ? 'Not available in this browser'
      : pushEnabled
        ? 'Enabled on this device'
        : typeof Notification !== 'undefined' && Notification.permission === 'denied'
          ? 'Blocked in browser settings'
          : 'Receive alerts while the app is in the background';

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
    <div className={'dashboard-app' + (premiumMode ? ' premium-mode' : '')}>
      <div className={`sidebar-backdrop ${sidebar ? 'show' : ''}`} onClick={() => setSidebar(false)} />
      <aside id="workspace-sidebar" className={`dashboard-sidebar ${sidebar ? 'open' : ''}`}>
        <div className="sidebar-top"><Link className="sidebar-brand" to="/dashboard"><span className="sidebar-logo"><img src="/taxsaathi-logo.png" alt="" /></span><span><strong>Tax Saathi</strong><small>{user?.role?.name || 'Workspace'}</small></span></Link><button className="icon-button mobile-only" onClick={() => setSidebar(false)}><Icon name="close" /></button></div>
        <div className="sidebar-section-label">Workspace</div>
        <nav className="sidebar-nav">{navItems.map(([label, path, icon]) => <NavLink key={path} to={path} className={isActive(path) ? 'active' : ''} onClick={() => setSidebar(false)}><span className="nav-icon"><Icon name={icon} size={17} /></span><span>{label}</span>{isActive(path) && <b />}</NavLink>)}</nav>
        <section className={'sidebar-premium-card' + (premiumMode ? ' enabled' : '')} aria-label="Premium appearance">
          <div className="sidebar-premium-row">
            <span className="sidebar-premium-icon"><Icon name="sparkles" size={16} /></span>
            <span className="sidebar-premium-copy">
              <strong>Premium mode</strong>
              <small>{premiumMode ? 'Premium style enabled' : 'Standard workspace style'}</small>
            </span>
            <button className="sidebar-premium-switch" type="button" role="switch" aria-checked={premiumMode} aria-label={premiumMode ? 'Turn off premium appearance' : 'Turn on premium appearance'} onClick={togglePremiumMode}><span /></button>
          </div>
          <p>Changes the workspace appearance on this device.</p>
        </section>
        <button className="sidebar-logout" onClick={logout}><Icon name="logout" size={17} /> Sign out</button>
      </aside>
      <div className="dashboard-main">
        <header className="dashboard-header">
          <div className="header-left"><button className="icon-button mobile-only" aria-label="Open workspace menu" aria-controls="workspace-sidebar" aria-expanded={sidebar} onClick={() => setSidebar(true)}><Icon name="menu" /></button><div><div className="breadcrumb"><span>Tax Saathi</span><i>•</i><span>{user?.role?.name || 'Workspace'}</span></div><h1>{pageName}</h1></div></div>
          <WorkspaceSearch role={role} navItems={navItems} />
          <div className="header-actions"><div className="notification-wrap"><button className="notification-button" onClick={() => setNotificationOpen((value) => !value)}><Icon name="bell" size={19} />{unread > 0 && <em>{unread > 99 ? '99+' : unread}</em>}</button>{notificationOpen && <div className="notification-panel"><div className="notification-panel-head"><div><small>Activity center</small><strong>Notifications</strong></div><button onClick={markAllRead}>Mark all read</button></div><div className="notification-push-control"><span className="notification-push-copy"><strong>Device push alerts</strong><small>{pushDetail}</small></span><button className="button light small" type="button" disabled={pushSupported === null || !pushSupported || pushBusy} onClick={togglePushNotifications}>{pushBusy ? "Saving…" : pushEnabled ? "Turn off" : "Enable"}</button></div>{pushMessage && <p className="notification-push-message" role="status">{pushMessage}</p>}<div className="notification-list">{notifications.length ? notifications.slice(0, 12).map((item) => <button key={`${item.id}-${item.uid || ''}`} className={`notification-item ${item.is_read ? '' : 'unread'}`} onClick={() => markRead(item.id)}><span className={`notification-dot ${item.severity || 'info'}`} /><span><strong>{item.title}</strong><small>{item.message}</small><i>{item.time_ago || 'Recently'}</i></span></button>) : <div className="empty-panel">No new activity.</div>}</div><Link to="/notifications" className="notification-footer" onClick={() => setNotificationOpen(false)}>Open notification center <Icon name="arrow" size={14} /></Link></div>}</div><div className="profile-menu-wrap" ref={profileMenuRef}>
            <button className="profile-chip" type="button" aria-label="Open profile menu" aria-haspopup="menu" aria-expanded={profileOpen} onClick={() => setProfileOpen((value) => !value)}>
              <span className="avatar small" aria-hidden="true">{user?.avatar_url ? <img src={user.avatar_url} alt="" className="user-avatar-image" /> : (user?.name || 'U').slice(0, 1).toUpperCase()}</span>
              <span className="profile-name">{user?.name || 'User'}</span>
              <Icon name="chevronDown" size={14} aria-hidden="true" />
            </button>
            {profileOpen && <div className="profile-menu" role="menu">
              <div className="profile-menu-head"><strong>{user?.name || 'User'}</strong><small>{user?.email || user?.role?.name || 'TaxSaathi account'}</small></div>
              <Link to="/profile" className="profile-menu-item" role="menuitem" onClick={() => setProfileOpen(false)}><Icon name="settings" size={16} /><span>Settings</span></Link>
              <button className="profile-menu-item danger" type="button" role="menuitem" onClick={() => { setProfileOpen(false); void logout(); }}><Icon name="logout" size={16} /><span>Sign out</span></button>
            </div>}
          </div></div>
        </header>
        <main className="dashboard-content"><Outlet /></main>
        <nav className="mobile-bottom-nav">{navItems.slice(0, 5).map(([label, path, icon]) => <Link key={path} to={path} className={isActive(path) ? 'active' : ''}><Icon name={icon} size={18} /><span>{label.split(' ')[0]}</span></Link>)}</nav>
      </div>
    </div>
  );
}
