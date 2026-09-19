import { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { api } from '../lib/api';
import Icon from '../components/Icons';
import { PageLoader, ServiceCard } from '../components/Ui';

export default function ServicesPage() {
  const [params, setParams] = useSearchParams();
  const [data, setData] = useState(null);
  const [site, setSite] = useState({});
  const [search, setSearch] = useState(params.get('search') || '');
  const category = params.get('category') || '';
  useEffect(() => { api('/meta').then((payload) => setSite(payload.settings || {})).catch(() => {}); }, []);
  useEffect(() => { setData(null); api(`/public/services?limit=100&search=${encodeURIComponent(search)}&category=${encodeURIComponent(category)}`).then(setData).catch(() => setData({ services: [], categories: [] })); }, [search, category]);
  if (!data) return <PageLoader label="Loading services…" />;
  const chooseCategory = (value) => { const next = new URLSearchParams(params); value ? next.set('category', value) : next.delete('category'); setParams(next); };
  return <>
    <section className="page-banner"><div className="container-wide page-banner-content"><span className="eyebrow">Explore the catalogue</span><h1>Services made clearer.</h1><p>Compare the TaxSaathi services, pricing, categories, and turnaround details carried over from the legacy system.</p></div></section>
    <section className="section container-wide"><div className="service-toolbar"><div className="search-field"><Icon name="search" size={18} /><input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Search by service, category, or keyword…" /><kbd>⌘ K</kbd></div><span className="result-count">{data.pagination?.total || data.services.length} services</span></div><div className="filter-pills"><button className={!category ? 'active' : ''} onClick={() => chooseCategory('')}>All services</button>{data.categories.map((item) => <button key={item.id} className={category === item.slug ? 'active' : ''} onClick={() => chooseCategory(item.slug)}>{item.title}</button>)}</div><div className="service-grid wide">{data.services.map((service) => <ServiceCard service={service} contact={{ phone: site.site_phone, whatsapp: site.site_whatsapp }} key={service.id} />)}</div>{!data.services.length && <div className="empty-state large"><span className="empty-icon"><Icon name="search" /></span><h3>No services matched your search.</h3><p>Try another keyword or clear the category filter.</p></div>}</section>
  </>;
}
