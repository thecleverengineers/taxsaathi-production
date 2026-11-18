import { useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { api, assetUrl } from '../lib/api';
import { DEFAULT_SERVICES_PAGE, normalizeServicesPage, resolveServicesHero } from '../lib/services-page-config';
import Icon from '../components/Icons';
import { EmptyState, ServiceCard } from '../components/Ui';
import './services-premium.css';

const categoryStyles = [
  [/income.tax/, 'file'],
  [/gst/, 'money'],
  [/business.registration/, 'briefcase'],
  [/account|audit/, 'calculator'],
  [/roc/, 'orders'],
  [/trademark/, 'tag']
];

function categoryRank(category) {
  const rank = categoryStyles.findIndex(([pattern]) => pattern.test(String(category.slug || '')));
  return rank < 0 ? categoryStyles.length : rank;
}
function sortCategories(items, presentation) {
  const source = [...(items || [])];
  const mode = presentation?.sortMode || 'current';
  if (mode === 'alphabetical') return source.sort((a, b) => String(a.title || '').localeCompare(String(b.title || '')));
  if (mode === 'database') return source.sort((a, b) => Number(a.sort_order || 0) - Number(b.sort_order || 0) || Number(a.id) - Number(b.id));
  if (mode === 'custom') {
    const ranking = new Map((presentation.customOrder || []).map((slug, index) => [slug, index]));
    return source.sort((a, b) =>
      (ranking.get(a.slug) ?? 9999) - (ranking.get(b.slug) ?? 9999) ||
      Number(a.sort_order || 0) - Number(b.sort_order || 0));
  }
  return source.sort((a, b) => categoryRank(a) - categoryRank(b));
}
function CTA({ url, className, children }) {
  if (String(url || '').startsWith('#')) return <a href={url} className={className}>{children}</a>;
  if (/^https?:\/\//i.test(String(url || ''))) return <a href={url} className={className} rel="noopener noreferrer" target="_blank">{children}</a>;
  return <Link to={url || '/services'} className={className}>{children}</Link>;
}

export default function ServicesPage() {
  const [params, setParams] = useSearchParams();
  const [catalog, setCatalog] = useState(null);
  const [data, setData] = useState(null);
  const [site, setSite] = useState({});
  const [presentation, setPresentation] = useState(DEFAULT_SERVICES_PAGE);
  const [previewMode, setPreviewMode] = useState(false);
  const [catalogError, setCatalogError] = useState(false);
  const [attempt, setAttempt] = useState(0);
  const search = params.get('search') || '';
  const category = params.get('category') || '';
  const preview = params.get('services_preview') === '1';
  const page = Math.max(1, Number.parseInt(params.get('page'), 10) || 1);
  const browsing = !category && !search.trim();

  const config = normalizeServicesPage(presentation);
  const browser = config.sections.browser;
  const categoryOptions = config.sections.categories;
  const cards = config.sections.cards;
  const footer = config.sections.footer;
  const displayCatalog = sortCategories(catalog || [], categoryOptions);
  const selectedCategory = displayCatalog.find((item) => item.slug === category || String(item.id) === category);
  const categorySettings = selectedCategory ? config.categoryOverrides[selectedCategory.slug] : null;
  const hero = resolveServicesHero(config, selectedCategory || { slug: category });
  const heading = browsing ? browser.title || 'Browse services'
    : (categorySettings?.enabled && categorySettings.browserTitle) || selectedCategory?.title || browser.categoryFallbackTitle || 'Browse services';
  const unavailable = browsing ? catalogError : data?.error;
  const loading = !unavailable && !(browsing ? catalog : data);

  useEffect(() => {
    api('/meta').then((payload) => setSite(payload.settings || {})).catch(() => {});
  }, []);

  useEffect(() => {
    let alive = true;
    api('/public/services-page')
      .then(async (publicData) => {
        if (preview) {
          try {
            const cms = await api('/admin/services-page-builder');
            return { content: cms.draft || cms.published || DEFAULT_SERVICES_PAGE, preview: true };
          } catch { /* Never show draft content to unauthenticated visitors. */ }
        }
        return publicData;
      })
      .then((payload) => {
        if (alive) {
          setPresentation(normalizeServicesPage(payload?.content || DEFAULT_SERVICES_PAGE));
          setPreviewMode(payload?.preview === true);
        }
      }).catch(() => {
        if (alive) { setPresentation(DEFAULT_SERVICES_PAGE); setPreviewMode(false); }
      });
    return () => { alive = false; };
  }, [preview]);

  useEffect(() => {
    const controller = new AbortController();
    setCatalogError(false);
    api('/public/categories', { signal: controller.signal })
      .then((payload) => { if (!controller.signal.aborted) setCatalog(payload.categories || []); })
      .catch(() => { if (!controller.signal.aborted) setCatalogError(true); });
    return () => controller.abort();
  }, [attempt]);

  useEffect(() => {
    if (browsing) return undefined;
    const controller = new AbortController();
    setData(null);
    const timer = setTimeout(() => {
      const query = new URLSearchParams({
        search: search.trim(), category, page, limit: Math.min(100, Math.max(1, Number(browser.pageSize) || 24))
      });
      api('/public/services?' + query, { signal: controller.signal })
        .then((payload) => { if (!controller.signal.aborted) setData(payload); })
        .catch(() => { if (!controller.signal.aborted) setData({ error: true }); });
    }, search ? 250 : 0);
    return () => { clearTimeout(timer); controller.abort(); };
  }, [search, category, page, browsing, attempt, browser.pageSize]);

  function updateFilter(name, value, replace = false) {
    const next = new URLSearchParams(params);
    if (name !== 'page') next.delete('page');
    if (value) next.set(name, value);
    else next.delete(name);
    setParams(next, { replace });
  }

  return <div className="ts-services">
    {previewMode && <div className="ts-services-draft-preview">ADMIN DRAFT PREVIEW — not published</div>}
    {hero.enabled !== false && <section className="ts-services-hero" aria-labelledby="services-title">
      <div className="container-wide ts-services-hero-content">
        <div className="ts-services-hero-copy">
          <p>{hero.eyebrow}</p>
          <h1 id="services-title">{hero.title}</h1>
          <p>{hero.description}</p>
          {hero.buttonLabel && <CTA url={hero.buttonHref || '#browse-services'} className="ts-services-primary">
            {hero.buttonLabel} <Icon name="arrow" aria-hidden="true" />
          </CTA>}
        </div>
      </div>
      {hero.imageUrl && <img className="ts-services-hero-image" src={assetUrl(hero.imageUrl)} width="732" height="425"
        style={{ objectPosition: hero.imagePosition || '53% center' }} alt={hero.imageAlt || ''} fetchPriority="high" />}
    </section>}
    {browser.enabled !== false && <section id="browse-services" className="container-wide ts-services-catalog" aria-labelledby="browse-services-title">
      <div className="ts-services-toolbar">
        <h2 id="browse-services-title" tabIndex={-1}>{heading}</h2>
        <div className={"ts-services-controls" + (browser.showSearch === false || browser.showCategoryFilter === false ? " is-single" : "")}>
          {browser.showSearch !== false && <label className="ts-services-search">
            <Icon name="search" aria-hidden="true" />
            <input type="search" aria-label="Search services" placeholder={browser.searchPlaceholder || 'Search services'}
              value={search} onChange={(event) => updateFilter('search', event.target.value, true)} />
          </label>}
          {browser.showCategoryFilter !== false && <select aria-label="Filter by category" value={selectedCategory?.slug || category}
            onChange={(event) => updateFilter('category', event.target.value)}>
            <option value="">{browser.filterAllLabel || 'All services'}</option>
            {category && !selectedCategory && <option value={category}>Selected category</option>}
            {displayCatalog.map((item) => <option key={item.id} value={item.slug}>{item.title}</option>)}
          </select>}
        </div>
      </div>
      {loading && <p className="ts-services-status" role="status">{browser.loadingLabel}</p>}
      {unavailable && <EmptyState icon="search" title={browser.unavailableTitle}
        action={<button type="button" className="ts-services-primary" onClick={() => setAttempt((current) => current + 1)}>{browser.retryLabel}</button>} />}
      {browsing && !unavailable && catalog && <>
        {categoryOptions.enabled !== false && <div className={'ts-services-category-grid ' + (categoryOptions.displayMode === 'list' ? 'ts-services-category-list' : '')}>
          {displayCatalog.map((item) => <Link key={item.id} to={'?category=' + encodeURIComponent(item.slug) + (preview ? '&services_preview=1' : '') + '#browse-services'}
            className="ts-services-category">
            <span className="ts-services-category-icon">
              {categoryOptions.showImages !== false && item.image_url
                ? <img src={assetUrl(item.image_url)} alt="" loading="lazy" />
                : <Icon name={categoryStyles[categoryRank(item)]?.[1] || 'services'} size={28} aria-hidden="true" />}
            </span>
            <span><strong>{item.title}</strong>
              {categoryOptions.showCount !== false && <small>{item.service_count} {Number(item.service_count) === 1 ? 'service' : 'services'}</small>}</span>
            <Icon className="ts-services-chevron" name="chevronRight" size={18} aria-hidden="true" />
          </Link>)}
        </div>}
        {!displayCatalog.length && <EmptyState title={browser.categoryEmptyTitle} />}
      </>}
      {!browsing && data && !unavailable && <>
        {browser.showResultsCount !== false && <p className="ts-services-status" role="status">{data.pagination?.total ?? data.services.length} {browser.resultsSuffix}</p>}
        {data.services?.length ? (cards.enabled !== false ? <div className="service-grid ts-services-results">
          {data.services.map((service) => <ServiceCard key={service.id} service={service}
            contact={{ phone: site.site_phone, whatsapp: site.site_whatsapp }} presentation={cards} />)}
        </div> : <p className="ts-services-status">Service cards are hidden.</p>) : <EmptyState icon="search"
          title={browser.resultEmptyTitle} text={browser.resultEmptyDescription} />}
        {data.pagination?.pages > 1 && <nav className="ts-services-pagination" aria-label="Services pages">
          <button type="button" disabled={page === 1} onClick={() => updateFilter('page', page - 1)}>{browser.previousLabel}</button>
          <span>Page {page} of {data.pagination.pages}</span>
          <button type="button" disabled={page >= data.pagination.pages} onClick={() => updateFilter('page', page + 1)}>{browser.nextLabel}</button>
        </nav>}
      </>}
    </section>}
    {footer.enabled === true && <section className="ts-services-footer-cta">
      <div className="container-wide ts-services-footer-inner">
        <div><span>{footer.eyebrow}</span><h2>{footer.title}</h2><p>{footer.description}</p></div>
        {footer.buttonLabel && <CTA url={footer.buttonHref || '#browse-services'} className="ts-services-primary">{footer.buttonLabel}<Icon name="arrow" aria-hidden="true" /></CTA>}
      </div>
    </section>}
  </div>;
}
