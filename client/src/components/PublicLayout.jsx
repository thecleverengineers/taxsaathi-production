import { useEffect, useRef, useState } from 'react';
import { Link, NavLink, Outlet } from 'react-router-dom';
import { api, assetUrl } from '../lib/api';
import { useAuth } from './AuthContext';
import Icon from './Icons';

export default function PublicLayout() {
  const { user, logout } = useAuth();
  const [site, setSite] = useState({ site_name: 'TaxSaathi', site_logo: '/uploads/site/site_logo_20260605161136_36ef8081dd8a.png' });
  const [categories, setCategories] = useState([]);
  const [categoriesLoading, setCategoriesLoading] = useState(true);
  const [open, setOpen] = useState(false);
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

  const navClass = ({ isActive }) => `public-nav-link ${isActive ? 'active' : ''}`;
  const closeMenus = () => { setOpen(false); setServicesOpen(false); };
  return (
    <div className="public-site">
      <div className="ambient-orb orb-one" />
      <div className="ambient-orb orb-two" />
      <header className="public-header">
        <div className="public-nav container-wide">
          <Link to="/" className="brand-mark-link" onClick={closeMenus}>
            <span className="brand-logo-frame"><img src={assetUrl(site.site_logo)} alt={site.site_logo_alt || site.site_name} /></span>
            <span><strong>{site.site_name}</strong><small>Tax & compliance services</small></span>
          </Link>
          <button className="icon-button mobile-only" onClick={() => { setOpen((value) => !value); setServicesOpen(false); }} aria-label="Open navigation"><Icon name={open ? 'close' : 'menu'} /></button>
          <nav className={`public-nav-links ${open ? 'open' : ''}`}>
            <NavLink to="/" className={navClass} end onClick={closeMenus}>Home</NavLink>
            <div className="nav-dropdown" ref={servicesDropdownRef}>
              <button
                type="button"
                className={`public-nav-link dropdown-trigger services-trigger ${servicesOpen ? 'active open' : ''}`}
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
            {user ? <Link to="/dashboard" className="public-nav-link" onClick={closeMenus}>Workspace</Link> : <NavLink to="/auth" className={navClass} onClick={closeMenus}>Login</NavLink>}
          </nav>
          {user ? <button className="nav-cta" onClick={logout}><Icon name="logout" size={16} /> Sign out</button> : <Link to="/auth" className="nav-cta">Get started <Icon name="arrow" size={16} /></Link>}
        </div>
      </header>
      <main><Outlet /></main>
      <footer className="public-footer">
        <div className="container-wide footer-grid">
          <div><div className="footer-brand"><span className="brand-logo-frame small"><img src={assetUrl(site.site_logo)} alt="" /></span><strong>{site.site_name}</strong></div><p>Structured tax, filing, and compliance services backed by a clear digital workflow.</p></div>
          <div><span className="footer-title">Explore</span><Link to="/services">Services</Link><Link to="/tax-calculators">Calculators</Link><Link to="/contact">Contact</Link></div>
          <div><span className="footer-title">Reach us</span><a href={`tel:${site.site_phone || ''}`}>{site.site_phone || 'Phone support'}</a><a href={`mailto:${site.site_email || ''}`}>{site.site_email || 'Email support'}</a><span>{site.contact_address || 'Guwahati, India'}</span></div>
        </div>
        <div className="footer-bottom container-wide"><span>© {new Date().getFullYear()} {site.site_name}. All rights reserved.</span><span>Built for clarity, confidence, and faster delivery.</span></div>
      </footer>
    </div>
  );
}
