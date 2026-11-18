import { useEffect, useRef, useState } from 'react';
import { Link, NavLink, Outlet, useLocation } from 'react-router-dom';
import { api, assetUrl } from '../lib/api';
import { useAuth } from './AuthContext';
import Icon from './Icons';

function PublicAccountMenu({ user, logout, ordersPath, closeMenus }) {
  const [open, setOpen] = useState(false);
  const menuRef = useRef(null);
  const triggerRef = useRef(null);
  const location = useLocation();
  const displayName = String(user?.name || user?.full_name || 'My account').trim() || 'My account';
  const profileImage = user?.avatar_url || user?.profile_image_url || user?.profile_photo_url || '';
  const closeAccountMenu = () => setOpen(false);

  useEffect(() => { setOpen(false); }, [location.pathname, location.search, user?.id]);

  useEffect(() => {
    if (!open) return undefined;
    function dismissOutside(event) {
      if (!menuRef.current?.contains(event.target)) setOpen(false);
    }
    function dismissKeyboard(event) {
      if (event.key === 'Escape') {
        setOpen(false);
        triggerRef.current?.focus();
      }
    }
    document.addEventListener('pointerdown', dismissOutside);
    document.addEventListener('keydown', dismissKeyboard);
    return () => {
      document.removeEventListener('pointerdown', dismissOutside);
      document.removeEventListener('keydown', dismissKeyboard);
    };
  }, [open]);

  function handleSignOut() {
    closeAccountMenu();
    closeMenus();
    void logout();
  }

  return (
    <div className="public-account-wrap" ref={menuRef}>
      <button
        ref={triggerRef}
        type="button"
        className="public-account-trigger"
        aria-label={`Account menu for ${displayName}`}
        aria-haspopup="menu"
        aria-expanded={open}
        aria-controls="public-account-dropdown"
        onClick={() => { closeMenus(); setOpen((current) => !current); }}
      >
        <span className="public-account-avatar" aria-hidden="true">
          {profileImage
            ? <img src={assetUrl(profileImage)} alt="" />
            : <Icon name="user" size={18} />}
        </span>
        <span className="public-account-name" title={displayName}>{displayName}</span>
        <Icon name="chevronDown" size={15} className={open ? 'public-account-chevron is-open' : 'public-account-chevron'} aria-hidden="true" />
      </button>
      {open && (
        <div id="public-account-dropdown" className="public-account-dropdown" role="menu" aria-label="Account options">
          <div className="public-account-dropdown-head">
            <strong>{displayName}</strong>
            <small>{user?.email || user?.role?.name || 'Tax Saathi account'}</small>
          </div>
          <Link to="/dashboard" role="menuitem" onClick={closeAccountMenu}>
            <Icon name="dashboard" size={17} /> My workspace
          </Link>
          <Link to={ordersPath} role="menuitem" onClick={closeAccountMenu}>
            <Icon name="orders" size={17} /> My orders
          </Link>
          <Link to="/profile" role="menuitem" onClick={closeAccountMenu}>
            <Icon name="settings" size={17} /> Profile & settings
          </Link>
          <div className="public-account-dropdown-divider" role="separator" />
          <button type="button" role="menuitem" className="public-account-signout" onClick={handleSignOut}>
            <Icon name="logout" size={17} /> Sign out
          </button>
        </div>
      )}
    </div>
  );
}

export default function PublicLayout() {
  const { user, logout } = useAuth();
  const [site, setSite] = useState({ site_name: 'TaxSaathi', site_logo: '/uploads/site/site_logo_20260605161136_36ef8081dd8a.png' });
  const [categories, setCategories] = useState([]);
  const [categoriesLoading, setCategoriesLoading] = useState(true);
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const sidebarRef = useRef(null);
  const sidebarToggleRef = useRef(null);
  const location = useLocation();
  const onHome = location.pathname === '/';
  const onServices = /^\/services\/?$/.test(location.pathname);
  const [servicesOpen, setServicesOpen] = useState(false);
  const servicesDropdownRef = useRef(null);

  useEffect(() => {
    api('/meta').then((payload) => setSite(payload.settings)).catch(() => {});
    api('/public/categories')
      .then((payload) => setCategories(payload.categories || []))
      .catch(() => setCategories([]))
      .finally(() => setCategoriesLoading(false));
  }, []);

  useEffect(() => {
    if (!servicesOpen) return undefined;
    const dismiss = (event) => {
      if (!servicesDropdownRef.current?.contains(event.target)) setServicesOpen(false);
    };
    const closeOnEscape = (event) => {
      if (event.key === 'Escape') setServicesOpen(false);
    };
    document.addEventListener('pointerdown', dismiss);
    document.addEventListener('keydown', closeOnEscape);
    return () => {
      document.removeEventListener('pointerdown', dismiss);
      document.removeEventListener('keydown', closeOnEscape);
    };
  }, [servicesOpen]);


  useEffect(() => {
    setSidebarOpen(false);
    setServicesOpen(false);
  }, [location.pathname, location.search]);

  useEffect(() => {
    if (!sidebarOpen) return undefined;
    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    const drawer = sidebarRef.current;
    drawer?.querySelector('[data-sidebar-close]')?.focus();
    function handleSidebarKeyboard(event) {
      if (event.key === 'Escape') {
        event.preventDefault();
        setSidebarOpen(false);
        return;
      }
      if (event.key !== 'Tab' || !drawer) return;
      const focusable = Array.from(drawer.querySelectorAll('a[href], button:not([disabled])'))
        .filter((element) => element.getClientRects().length > 0);
      if (!focusable.length) return;
      if (event.shiftKey && document.activeElement === focusable[0]) {
        event.preventDefault();
        focusable[focusable.length - 1].focus();
      } else if (!event.shiftKey && document.activeElement === focusable[focusable.length - 1]) {
        event.preventDefault();
        focusable[0].focus();
      }
    }
    document.addEventListener('keydown', handleSidebarKeyboard);
    return () => {
      document.body.style.overflow = previousOverflow;
      document.removeEventListener('keydown', handleSidebarKeyboard);
      sidebarToggleRef.current?.focus();
    };
  }, [sidebarOpen]);

  const navClass = ({ isActive }) => `public-nav-link ${isActive ? 'active' : ''}`;
  const closeMenus = () => { setSidebarOpen(false); setServicesOpen(false); };
  const role = user?.role?.slug || 'client';
  const ordersPath = ['partner', 'partners'].includes(role) ? '/partner/orders' : ['admin', 'manager', 'executive'].includes(role) ? '/admin/orders' : '/client/orders';
  const sidebarLinkClass = ({ isActive }) => `public-sidebar-link ${isActive ? 'active' : ''}`;
  // Authentication uses its own full-bleed responsive cover without the public header/footer.
  if (['/auth', '/register', '/forgot-password'].includes(location.pathname)) {
    return <Outlet />;
  }

  return (
    <div className={`public-site ts-brand-site${onHome ? ' ts-home-site' : ''}${onServices ? ' ts-services-site' : ''}`}>
      <div className="ambient-orb orb-one" />
      <div className="ambient-orb orb-two" />
      <header className="public-header">
        <div className="public-nav container-wide">
          <button
            ref={sidebarToggleRef}
            type="button"
            className="public-sidebar-trigger"
            aria-controls="public-navigation-drawer"
            aria-expanded={sidebarOpen}
            aria-label={sidebarOpen ? 'Hide sidebar' : 'Show sidebar'}
            onClick={() => { setSidebarOpen((current) => !current); setServicesOpen(false); }}
          ><Icon name={sidebarOpen ? 'close' : 'menu'} size={21} /></button>
          <Link to="/" className="brand-mark-link" onClick={closeMenus}>
            <span className="brand-logo-frame"><img src="/taxsaathi-logo.png" alt={site.site_logo_alt || site.site_name} /></span>
            <span><strong>{'Tax Saathi'}</strong><small>Tax & compliance services</small></span>
          </Link>
          <nav className="public-nav-links">
            <NavLink to="/" className={navClass} end onClick={closeMenus}>Home</NavLink>
            <div className="nav-dropdown" ref={servicesDropdownRef}>
              <button
                type="button"
                className={`public-nav-link dropdown-trigger services-trigger ${servicesOpen ? 'active open' : onServices ? 'active' : ''}`}
                onClick={() => setServicesOpen((value) => !value)}
                aria-expanded={servicesOpen}
                aria-haspopup="true"
              >
                Services <Icon name="chevronDown" size={15} />
              </button>
              <div className={`services-mega-menu ${servicesOpen ? 'show' : ''}`}>
                <div className="services-menu-head">
                  <div><span>SERVICE MENU</span><small>Choose a category or service</small></div>
                  <Link className="services-menu-view-all" to="/services" onClick={closeMenus}>View all <Icon name="arrow" size={14} /></Link>
                </div>
                <div className="services-category-grid">
                  {categories.length ? categories.map((category) => {
                    const count = Number(category.service_count || 0);
                    return <Link className="services-category-item" key={category.id} to={`/services?category=${encodeURIComponent(category.slug)}`} onClick={closeMenus}>
                      <span className="services-category-thumb">{category.image_url ? <img src={assetUrl(category.image_url)} alt="" /> : <b>{String(category.title || 'S').slice(0, 1)}</b>}</span>
                      <span className="services-category-copy"><strong>{category.title}</strong><small>{count} {count === 1 ? 'SERVICE' : 'SERVICES'}</small></span>
                      <Icon name="arrow" size={14} className="services-category-arrow" />
                    </Link>;
                  }) : <div className="services-menu-empty">{categoriesLoading ? 'Loading service categories…' : 'No service categories available.'}</div>}
                </div>
              </div>
            </div>
            <NavLink to="/about" className={navClass} onClick={closeMenus}>About</NavLink>
            <NavLink to="/contact" className={navClass} onClick={closeMenus}>Contact</NavLink>
            {user && <Link to="/dashboard" className="public-nav-link" onClick={closeMenus}>Workspace</Link>}
          </nav>
          {user
            ? <PublicAccountMenu user={user} logout={logout} ordersPath={ordersPath} closeMenus={closeMenus} />
            : <Link to="/auth" className="public-login-trigger" aria-label="Login to Tax Saathi" onClick={closeMenus}>
                <Icon name="userCircle" size={19} aria-hidden="true" />
                <span>{onServices ? 'Sign in' : 'Login'}</span>
              </Link>}
        </div>
      </header>

      <div className={`public-sidebar-backdrop ${sidebarOpen ? 'show' : ''}`} aria-hidden="true" onClick={() => setSidebarOpen(false)} />
      <aside id="public-navigation-drawer" ref={sidebarRef} role="dialog" aria-label="TaxSaathi navigation sidebar"
        aria-modal={sidebarOpen} aria-hidden={!sidebarOpen} inert={!sidebarOpen}
        className={`public-sidebar-drawer ${sidebarOpen ? 'open' : ''}`}>
        <div className="public-sidebar-head">
          <div className="public-sidebar-brand"><span className="brand-logo-frame"><img src="/taxsaathi-logo.png" alt="" /></span>
            <span><strong>{'Tax Saathi'}</strong><small>Navigation menu</small></span></div>
          <button type="button" data-sidebar-close className="public-sidebar-close" aria-label="Hide sidebar" onClick={() => setSidebarOpen(false)}>
            <Icon name="close" size={21} />
          </button>
        </div>
        <div className="public-sidebar-content">
          <span className="public-sidebar-label">EXPLORE</span>
          <nav className="public-sidebar-nav" aria-label="Main navigation">
            <NavLink end to="/" className={sidebarLinkClass} onClick={closeMenus}><Icon name="home" size={18} />Home<Icon name="chevronRight" size={15} /></NavLink>
            <NavLink to="/services" className={sidebarLinkClass} onClick={closeMenus}><Icon name="services" size={18} />All services<Icon name="chevronRight" size={15} /></NavLink>
            <NavLink to="/tax-calculators" className={sidebarLinkClass} onClick={closeMenus}><Icon name="calculator" size={18} />Tax calculators<Icon name="chevronRight" size={15} /></NavLink>
            <NavLink to="/about" className={sidebarLinkClass} onClick={closeMenus}><Icon name="shield" size={18} />About TaxSaathi<Icon name="chevronRight" size={15} /></NavLink>
            <NavLink to="/contact" className={sidebarLinkClass} onClick={closeMenus}><Icon name="phone" size={18} />Contact support<Icon name="chevronRight" size={15} /></NavLink>
          </nav>
          {user && <>
            <span className="public-sidebar-label">MY ACCOUNT</span>
            <nav className="public-sidebar-nav" aria-label="Account navigation">
              <NavLink to="/dashboard" className={sidebarLinkClass} onClick={closeMenus}><Icon name="dashboard" size={18} />My workspace<Icon name="chevronRight" size={15} /></NavLink>
              <NavLink to={ordersPath} className={sidebarLinkClass} onClick={closeMenus}><Icon name="orders" size={18} />My orders<Icon name="chevronRight" size={15} /></NavLink>
              {['admin', 'manager'].includes(role) && <NavLink to="/admin/services" className={sidebarLinkClass} onClick={closeMenus}><Icon name="settings" size={18} />Manage services<Icon name="chevronRight" size={15} /></NavLink>}
              <NavLink to="/profile" className={sidebarLinkClass} onClick={closeMenus}><Icon name="userCircle" size={18} />Profile & settings<Icon name="chevronRight" size={15} /></NavLink>
            </nav>
          </>}
          <div className="public-sidebar-categories-head"><span className="public-sidebar-label">SERVICE CATEGORIES</span>
            <Link to="/services" onClick={closeMenus}>View all</Link></div>
          <nav className="public-sidebar-categories" aria-label="Service categories">
            {categories.length ? categories.map((category) => (
              <Link key={category.id} to={`/services?category=${encodeURIComponent(category.slug)}`}
                className={`public-sidebar-category ${location.pathname === '/services' && new URLSearchParams(location.search).get('category') === category.slug ? 'active' : ''}`}
                onClick={closeMenus}>
                <span className="public-sidebar-category-image">{category.image_url ? <img src={assetUrl(category.image_url)} alt="" /> : <Icon name="services" size={17} />}</span>
                <span>{category.title}</span><small>{Number(category.service_count || 0)}</small>
              </Link>
            )) : <p className="public-sidebar-empty">{categoriesLoading ? 'Loading service categories…' : 'No categories available.'}</p>}
          </nav>
        </div>
        <div className="public-sidebar-foot">
          {user ? <button type="button" onClick={() => { closeMenus(); void logout(); }}><Icon name="logout" size={17} />Sign out</button> :
            <Link to="/auth" onClick={closeMenus}><Icon name="userCircle" size={17} />Sign in to TaxSaathi</Link>}
        </div>
      </aside>
      <main><Outlet /></main>
      <footer className="public-footer">
        <div className="container-wide footer-grid">
          <div><div className="footer-brand"><span className="brand-logo-frame small"><img src="/taxsaathi-logo.png" alt="" /></span><strong>{'Tax Saathi'}</strong></div><p>Structured tax, filing, and compliance services backed by a clear digital workflow.</p></div>
          <div><span className="footer-title">Explore</span><Link to="/services">Services</Link><Link to="/tax-calculators">Calculators</Link><Link to="/contact">Contact</Link></div>
          <div><span className="footer-title">Reach us</span><a href={`tel:${site.site_phone || ''}`}>{site.site_phone || 'Phone support'}</a><a href={`mailto:${site.site_email || ''}`}>{site.site_email || 'Email support'}</a><span>{site.contact_address || 'Guwahati, India'}</span></div>
        </div>
        <div className="footer-bottom container-wide"><span>© {new Date().getFullYear()} {site.site_name}. All rights reserved.</span><span>Built for clarity, confidence, and faster delivery.</span></div>
      </footer>
    </div>
  );
}
