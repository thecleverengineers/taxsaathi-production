import { useEffect, useMemo, useRef, useState } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { api } from '../lib/api';
import Icon from './Icons';

/* Searches navigable pages instantly and a server-scoped subset of recent orders. */
export default function WorkspaceSearch({ navItems = [], role = 'client' }) {
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState('');
  const [orders, setOrders] = useState([]);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const fieldRef = useRef(null);
  const containerRef = useRef(null);
  const navigate = useNavigate();
  const location = useLocation();

  const pageMatches = useMemo(() => {
    const q = query.trim().toLowerCase();
    return (q ? navItems.filter(([name, path]) => (name + ' ' + path).toLowerCase().includes(q)) : navItems.slice(0, 6)).slice(0, 8);
  }, [navItems, query]);

  const orderBase = ['partner', 'partners'].includes(role) ? '/partner/jobs' :
    ['admin', 'manager', 'executive'].includes(role) ? '/admin/orders' : '/client/orders';

  useEffect(() => {
    function handleKeys(event) {
      if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        setOpen((value) => !value);
      } else if (event.key === 'Escape') setOpen(false);
    }
    document.addEventListener('keydown', handleKeys);
    return () => document.removeEventListener('keydown', handleKeys);
  }, []);
  useEffect(() => { setOpen(false); setQuery(''); setOrders([]); }, [location.pathname]);
  useEffect(() => { if (open) fieldRef.current?.focus(); }, [open]);
  useEffect(() => {
    if (!open) return undefined;
    const dismiss = (event) => { if (!containerRef.current?.contains(event.target)) setOpen(false); };
    document.addEventListener('pointerdown', dismiss);
    return () => document.removeEventListener('pointerdown', dismiss);
  }, [open]);
  useEffect(() => {
    const q = query.trim();
    if (!open || q.length < 2) { setOrders([]); setError(''); setBusy(false); return undefined; }
    let active = true;
    const timer = setTimeout(async () => {
      setBusy(true);
      try {
        const r = await api('/workspace/search?q=' + encodeURIComponent(q), { timeoutMs: 12000 });
        if (active) { setOrders(r.orders || []); setError(''); }
      } catch (err) { if (active) { setOrders([]); setError(err.message); } }
      finally { if (active) setBusy(false); }
    }, 250);
    return () => { active = false; clearTimeout(timer); };
  }, [query, open]);

  function choose(path) { setOpen(false); navigate(path); }
  return <div className="workspace-search-control" ref={containerRef}>
    <button className="workspace-search-trigger" type="button" aria-haspopup="dialog" aria-expanded={open}
      aria-label="Search workspace pages and orders" onClick={() => setOpen((old) => !old)}>
      <Icon name="search" size={17} /><span>Search pages and orders…</span><kbd>Ctrl K</kbd>
    </button>
    {open && <div className="workspace-search-popover" role="dialog" aria-label="Search workspace">
      <div className="workspace-search-input-row">
        <Icon name="search" size={19}/>
        <input ref={fieldRef} autoComplete="off" aria-label="Search pages and recent orders"
          placeholder="Search pages, orders or clients…" value={query} onChange={(event) => setQuery(event.target.value)}
          onKeyDown={(event) => {
            if (event.key !== 'Enter') return;
            const target = pageMatches[0]?.[1] || (orders[0] && orderBase + '/' + orders[0].id);
            if (target) { event.preventDefault(); choose(target); }
          }}/>
        <button type="button" aria-label="Close search" onClick={() => setOpen(false)}><Icon name="close" size={17}/></button>
      </div>
      <div className="workspace-search-results">
        <strong className="workspace-search-section-label">PAGES</strong>
        {pageMatches.length ? pageMatches.map(([label, path, icon]) => <Link key={path} to={path}
          onClick={() => setOpen(false)}><Icon name={icon} size={17}/><span>{label}</span><Icon name="arrow" size={13}/></Link>)
          : <p>No matching pages.</p>}
        {query.trim().length >= 2 && <>
          <strong className="workspace-search-section-label">RECENT ORDERS</strong>
          {busy ? <p>Searching your authorised orders…</p> : error ? <p role="alert">{error}</p> :
          orders.length ? orders.map((order) => <Link key={order.id} to={orderBase + '/' + order.id}
            onClick={() => setOpen(false)}><Icon name="orders" size={17}/>
            <span>{order.order_no || 'Order #' + order.id}<small>{[order.customer_name, order.status].filter(Boolean).join(' · ')}</small></span>
            <Icon name="arrow" size={13}/></Link>)
            : <p>No matching orders among your 250 most recent authorised orders.</p>}
        </>}
      </div>
    </div>}
  </div>;
}
