import { useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { api } from '../lib/api';
import Icon from '../components/Icons';
import { EmptyState, ServiceCard } from '../components/Ui';
import './services-premium.css';

const categoryStyles = [
  [/income.tax/, 'file'],
  [/gst/, 'money'],
  [/business.registration/, 'briefcase'],
  [/account|audit/, 'calculator'],
  [/roc/, 'orders'],
  [/trademark/, 'tag'],
];

function categoryRank(category) {
  const rank = categoryStyles.findIndex(([pattern]) => pattern.test(category.slug));
  return rank < 0 ? categoryStyles.length : rank;
}

export default function ServicesPage() {
  const [params, setParams] = useSearchParams();
  const [catalog, setCatalog] = useState(null);
  const [data, setData] = useState(null);
  const [site, setSite] = useState({});
  const [catalogError, setCatalogError] = useState(false);
  const [attempt, setAttempt] = useState(0);
  const search = params.get('search') || '';
  const category = params.get('category') || '';
  const page = Math.max(1, Number.parseInt(params.get('page'), 10) || 1);
  const browsing = !category && !search.trim();

  useEffect(() => {
    api('/meta').then((payload) => setSite(payload.settings || {})).catch(() => {});
  }, []);

  useEffect(() => {
    const controller = new AbortController();
    setCatalogError(false);
    api('/public/categories', { signal: controller.signal })
      .then((payload) => {
        if (!controller.signal.aborted) {
          setCatalog([...payload.categories].sort((a, b) => categoryRank(a) - categoryRank(b)));
        }
      })
      .catch(() => {
        if (!controller.signal.aborted) setCatalogError(true);
      });
    return () => controller.abort();
  }, [attempt]);

  useEffect(() => {
    if (browsing) return undefined;
    const controller = new AbortController();
    setData(null);
    const timer = setTimeout(() => {
      const query = new URLSearchParams({ search: search.trim(), category, page, limit: 24 });
      api(`/public/services?${query}`, { signal: controller.signal })
        .then((payload) => {
          if (!controller.signal.aborted) setData(payload);
        })
        .catch(() => {
          if (!controller.signal.aborted) setData({ error: true });
        });
    }, search ? 250 : 0);
    return () => {
      clearTimeout(timer);
      controller.abort();
    };
  }, [search, category, page, browsing, attempt]);

  function updateFilter(name, value, replace = false) {
    const next = new URLSearchParams(params);
    if (name !== 'page') next.delete('page');
    if (value) next.set(name, value);
    else next.delete(name);
    setParams(next, { replace });
  }

  const selectedCategory = catalog?.find((item) => item.slug === category || String(item.id) === category);
  const heading = browsing ? 'Browse services' : selectedCategory?.title || 'Browse services';
  const unavailable = browsing ? catalogError : data?.error;
  const loading = !unavailable && !(browsing ? catalog : data);

  return <div className="ts-services">
    <section className="ts-services-hero" aria-labelledby="services-title">
      <div className="container-wide ts-services-hero-content">
        <div className="ts-services-hero-copy">
          <p>Tax &amp; compliance services</p>
          <h1 id="services-title">Expert help with tax and compliance</h1>
          <p>Find the right service and manage your documents in one place.</p>
          <a href="#browse-services" className="ts-services-primary">Explore services <Icon name="arrow" aria-hidden="true" /></a>
        </div>
      </div>
      <img className="ts-services-hero-image" src="/media/taxsaathi-services-consultation.webp" width="732" height="425" alt="" fetchPriority="high" />
    </section>
    <section id="browse-services" className="container-wide ts-services-catalog" aria-labelledby="browse-services-title">
      <div className="ts-services-toolbar">
        <h2 id="browse-services-title" tabIndex={-1}>{heading}</h2>
        <div className="ts-services-controls">
          <label className="ts-services-search">
            <Icon name="search" aria-hidden="true" />
            <input type="search" aria-label="Search services" placeholder="Search services" value={search}
              onChange={(event) => updateFilter('search', event.target.value, true)} />
          </label>
          <select aria-label="Filter by category" value={selectedCategory?.slug || category}
            onChange={(event) => updateFilter('category', event.target.value)}>
            <option value="">All services</option>
            {category && !selectedCategory && <option value={category}>Selected category</option>}
            {catalog?.map((item) => <option key={item.id} value={item.slug}>{item.title}</option>)}
          </select>
        </div>
      </div>
      {loading && <p className="ts-services-status" role="status">Loading services…</p>}
      {unavailable && <EmptyState icon="search" title="Services are temporarily unavailable."
        action={<button type="button" className="ts-services-primary" onClick={() => setAttempt(attempt + 1)}>Try again</button>} />}
      {browsing && !unavailable && catalog && <>
        <div className="ts-services-category-grid">
          {catalog.map((item) => <Link key={item.id} to={`?category=${encodeURIComponent(item.slug)}#browse-services`} className="ts-services-category">
            <span className="ts-services-category-icon"><Icon name={categoryStyles[categoryRank(item)]?.[1] || 'services'} size={28} aria-hidden="true" /></span>
            <span><strong>{item.title}</strong><small>{item.service_count} {Number(item.service_count) === 1 ? 'service' : 'services'}</small></span>
            <Icon className="ts-services-chevron" name="chevronRight" size={18} aria-hidden="true" />
          </Link>)}
        </div>
        {!catalog.length && <EmptyState title="No service categories are available yet." />}
      </>}
      {!browsing && data && !unavailable && <>
        <p className="ts-services-status" role="status">{data.pagination?.total ?? data.services.length} services found</p>
        {data.services.length ? <div className="service-grid wide">{data.services.map((service) =>
          <ServiceCard key={service.id} service={service} contact={{ phone: site.site_phone, whatsapp: site.site_whatsapp }} />
        )}</div> : <EmptyState icon="search" title="No services matched your search." text="Try another keyword or clear the category filter." />}
        {data.pagination?.pages > 1 && <nav className="ts-services-pagination" aria-label="Services pages">
          <button type="button" disabled={page === 1} onClick={() => updateFilter('page', page - 1)}>Previous</button>
          <span>Page {page} of {data.pagination.pages}</span>
          <button type="button" disabled={page >= data.pagination.pages} onClick={() => updateFilter('page', page + 1)}>Next</button>
        </nav>}
      </>}
    </section>
  </div>;
}
