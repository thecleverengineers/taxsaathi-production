import { useEffect, useMemo, useState } from 'react';
import { api, assetUrl } from '../lib/api';
import { DEFAULT_SERVICES_PAGE, normalizeServicesPage, resolveServicesHero } from '../lib/services-page-config';
import './home-builder.css';
import './services-page-builder.css';

const tabs = [
  ['hero', 'Page hero'],
  ['browser', 'Search & browsing'],
  ['categories', 'Category tiles'],
  ['cards', 'Service cards'],
  ['categoryOverrides', 'Category pages'],
  ['footer', 'Final CTA']
];
const fields = {
  hero: [['eyebrow', 'Eyebrow'], ['title', 'Headline'], ['description', 'Description', 'textarea'],
    ['buttonLabel', 'Hero button text'], ['buttonHref', 'Hero button destination'],
    ['imageUrl', 'Hero image', 'image'], ['imageAlt', 'Image alt text'], ['imagePosition', 'Image positioning']],
  browser: [['title', 'Default catalogue heading'], ['categoryFallbackTitle', 'Fallback category heading'],
    ['searchPlaceholder', 'Search field placeholder'], ['filterAllLabel', 'All-category option'],
    ['loadingLabel', 'Loading text'], ['unavailableTitle', 'Error text'],
    ['retryLabel', 'Retry button'], ['categoryEmptyTitle', 'No categories message'],
    ['resultsSuffix', 'Results count suffix'], ['resultEmptyTitle', 'No results headline'],
    ['resultEmptyDescription', 'No results description'], ['previousLabel', 'Previous page label'],
    ['nextLabel', 'Next page label'], ['pageSize', 'Services per page (1–100)', 'number']],
  categories: [],
  cards: [['viewLabel', 'View service link'], ['callLabel', 'Call button'], ['whatsappLabel', 'WhatsApp button'],
    ['applyLabel', 'Apply button'], ['featuredLabel', 'Featured badge'], ['emptyFeeLabel', 'Missing price label']],
  footer: [['eyebrow', 'Eyebrow'], ['title', 'CTA headline'], ['description', 'Description', 'textarea'],
    ['buttonLabel', 'CTA button'], ['buttonHref', 'CTA destination']]
};
const toggles = {
  browser: [['showSearch','Show search'], ['showCategoryFilter','Show category selector'], ['showResultsCount','Show results count']],
  categories: [['showCount','Show service counts'], ['showImages','Show existing category images']],
  cards: [['showImages','Always show existing service images'], ['showCategory','Show category labels'],
    ['showPrice','Show prices'], ['showFeatured','Show Featured badge'],
    ['showContactButtons','Show Call and WhatsApp'], ['showApplyButton','Show Apply button']]
};
const EMPTY_OVERRIDE = { enabled: false, eyebrow: '', title: '', description: '', imageUrl: '', imagePosition: '', browserTitle: '' };
const stringify = (value) => JSON.stringify(normalizeServicesPage(value));
function EditableField({ label, type = 'text', value, onChange, upload }) {
  const [uploading, setUploading] = useState(false);
  const [uploadError, setUploadError] = useState('');
  async function sendFile(event) {
    const file = event.target.files?.[0];
    if (!file) return;
    setUploading(true); setUploadError('');
    try { await upload(file, onChange); }
    catch (error) { setUploadError(error.message); }
    finally { setUploading(false); event.target.value = ''; }
  }
  return <div className={'home-builder-field ' + (type === 'textarea' || type === 'image' ? 'services-builder-wide' : '')}>
    <span>{label}</span>
    {type === 'textarea'
      ? <textarea aria-label={label} rows="3" value={value ?? ''} onChange={(event) => onChange(event.target.value)} />
      : <input aria-label={label} type={type === 'number' ? 'number' : 'text'} min={type === 'number' ? 1 : undefined}
          max={type === 'number' ? 100 : undefined} value={value ?? ''}
          onChange={(event) => onChange(type === 'number' ? Number(event.target.value) : event.target.value)} />}
    {type === 'image' && <>
      <div className="services-builder-media-tools">
        <label className="home-builder-upload">{uploading ? 'Uploading image…' : 'Upload replacement hero image'}
          <input type="file" accept="image/png,image/jpeg,image/webp,image/avif,image/gif" disabled={uploading} onChange={sendFile} />
        </label>
        {value && <button type="button" className="home-builder-mini" onClick={() => onChange('')}>Clear custom URL</button>}
      </div>
      {value && <img className="services-builder-image" src={assetUrl(value)} alt="Current hero visual" />}
      {uploadError && <small className="services-builder-error" role="alert">{uploadError}</small>}
    </>}
  </div>;
}
export default function ServicesPageBuilder() {
  const [content, setContent] = useState(null);
  const [published, setPublished] = useState(null);
  const [saved, setSaved] = useState(null);
  const [categories, setCategories] = useState([]);
  const [tab, setTab] = useState('hero');
  const [categorySlug, setCategorySlug] = useState('income-tax-services');
  const [busy, setBusy] = useState('');
  const [notice, setNotice] = useState('');
  useEffect(() => {
    Promise.all([api('/admin/services-page-builder'), api('/public/categories')])
      .then(([cms, catalogue]) => {
        const draft = normalizeServicesPage(cms.draft || cms.published || DEFAULT_SERVICES_PAGE);
        setContent(draft); setSaved(draft);
        setPublished(normalizeServicesPage(cms.published || DEFAULT_SERVICES_PAGE));
        const available = catalogue.categories || [];
        setCategories(available);
        if (available.length && !available.some((item) => item.slug === 'income-tax-services')) {
          setCategorySlug(available[0].slug);
        }
      })
      .catch((error) => setNotice(error.message));
  }, []);
  const orderedCategories = useMemo(() => {
    if (!content) return categories;
    const order = content.sections.categories.customOrder || [];
    return [...categories].sort((a, b) => {
      const ai = order.indexOf(a.slug), bi = order.indexOf(b.slug);
      if (ai >= 0 && bi >= 0) return ai - bi;
      if (ai >= 0) return -1;
      if (bi >= 0) return 1;
      return Number(a.sort_order || 0) - Number(b.sort_order || 0);
    });
  }, [categories, content]);
  if (!content) return <div className="workspace-page"><h2>Services Page Builder</h2><p>{notice || 'Loading page settings…'}</p></div>;

  const section = content.sections[tab] || {};
  const selectedCategory = categories.find((item) => item.slug === categorySlug);
  const override = content.categoryOverrides[categorySlug] || EMPTY_OVERRIDE;
  const currentPreviewHero = resolveServicesHero(content, selectedCategory || { slug: categorySlug });
  const dirty = stringify(content) !== stringify(saved);
  const notLive = stringify(content) !== stringify(published);

  function patchSection(name, changes) {
    setContent((current) => ({ ...current, sections: { ...current.sections, [name]: { ...current.sections[name], ...changes } } }));
  }
  function patchOverride(changes) {
    if (!categorySlug) return;
    setContent((current) => ({
      ...current, categoryOverrides: {
        ...current.categoryOverrides, [categorySlug]: { ...(current.categoryOverrides[categorySlug] || EMPTY_OVERRIDE), ...changes }
      }
    }));
  }
  function changeOrder(category, amount) {
    const items = orderedCategories.map((item) => item.slug), index = items.indexOf(category), next = index + amount;
    if (index < 0 || next < 0 || next >= items.length) return;
    [items[index], items[next]] = [items[next], items[index]];
    patchSection('categories', { customOrder: items, sortMode: 'custom' });
  }
  async function upload(file, onChange) {
    if (file.size > 45 * 1024 * 1024) throw new Error('Max upload size is 45 MB.');
    const body = new FormData(); body.append('file', file);
    const uploaded = await api('/admin/home-builder/media', { method: 'POST', body, timeoutMs: 180000 });
    onChange(uploaded.url);
    setNotice('New hero image uploaded. Publish the page when you want to show it publicly.');
  }
  async function save(mode) {
    setBusy(mode); setNotice('');
    try {
      const endpoint = mode === 'publish' ? '/admin/services-page-builder/publish' : '/admin/services-page-builder/draft';
      const result = await api(endpoint, { method: mode === 'publish' ? 'POST' : 'PUT', body: { content } });
      const next = normalizeServicesPage(mode === 'publish' ? result.content : result.draft);
      setSaved(next);
      if (mode === 'publish') setPublished(next);
      setNotice(mode === 'publish' ? 'Services page published. Service images and catalogue records were preserved.' : 'Draft saved. Live Services page is unchanged.');
    } catch (error) { setNotice(error.message); }
    finally { setBusy(''); }
  }
  async function preview() {
    const url = '/services?' + (categorySlug ? 'category=' + encodeURIComponent(categorySlug) + '&' : '') + 'services_preview=1#browse-services';
    const tabRef = window.open('about:blank', '_blank');
    if (tabRef) tabRef.opener = null;
    setBusy('preview'); setNotice('');
    try {
      const data = await api('/admin/services-page-builder/draft', { method: 'PUT', body: { content } });
      setSaved(normalizeServicesPage(data.draft));
      if (tabRef) tabRef.location.href = url;
      else window.location.assign(url);
      setNotice('Preview loaded from the draft without changing the public page.');
    } catch (error) { tabRef?.close(); setNotice(error.message); }
    finally { setBusy(''); }
  }
  return <div className="workspace-page home-builder services-builder">
    <div className="home-builder-header">
      <div><span className="home-builder-overline">TAX SAATHI · WEBSITE CMS</span><h1>Services Page Builder</h1>
        <p>Manage the service catalogue layout and category landing pages without changing existing service images, fees or records.</p></div>
      <div className="home-builder-actions">
        <button type="button" className="button light" disabled={!!busy} onClick={preview}>Preview draft</button>
        <button type="button" className="button light" disabled={!!busy || !dirty} onClick={() => save('draft')}>{busy === 'draft' ? 'Saving…' : 'Save draft'}</button>
        <button type="button" className="button dark" disabled={!!busy || !notLive} onClick={() => save('publish')}>{busy === 'publish' ? 'Publishing…' : 'Publish live'}</button>
      </div>
    </div>
    {notice && <div className="home-builder-notice" role="status">{notice}</div>}
    <div className="home-builder-layout">
      <aside className="home-builder-sidebar" aria-label="Services page editor sections">
        <div className="home-builder-side-title"><strong>Page settings</strong><span>6 sections</span></div>
        {tabs.map(([id, name], index) => <div key={id} className={'home-builder-navitem ' + (id === tab ? 'selected' : '')}>
          <button type="button" className="home-builder-select" onClick={() => setTab(id)}>
            <span className="home-builder-section-number">{String(index + 1).padStart(2, '0')}</span><span>{name}</span>
          </button>
        </div>)}
        <p className="home-builder-side-note">Original service images are not modified here. Cards continue using each service’s current image in the database.</p>
        <a href="/admin/services" className="services-builder-catalog-link">Manage individual services</a>
      </aside>
      <main className="home-builder-main">
        <div className="home-builder-section-top">
          <div><span className="home-builder-overline">{tab === 'categoryOverrides' ? 'BY CATEGORY' : 'PAGE PRESENTATION'}</span>
            <h2>{tabs.find(([id]) => id === tab)?.[1]}</h2>
            {tab === 'cards' && <p>These options affect card presentation only; existing images and service metadata remain unchanged.</p>}
            {tab === 'categoryOverrides' && <p>Override banner copy or hero image for an individual category. Blank fields use the default Services page hero.</p>}
          </div>
          {tab !== 'categoryOverrides' && <label className="home-builder-toggle">
            <input type="checkbox" checked={section.enabled !== false} onChange={(event) => patchSection(tab, { enabled: event.target.checked })} /> Show section
          </label>}
        </div>

        {tab === 'categoryOverrides' ? <div className="services-builder-category-editor">
          <div className="services-builder-category-picker">
            <label>Choose category
              <select value={categorySlug} onChange={(event) => setCategorySlug(event.target.value)}>
                {!selectedCategory && <option value={categorySlug}>{categorySlug || 'Select category'}</option>}
                {categories.map((item) => <option key={item.id} value={item.slug}>{item.title}</option>)}
              </select>
            </label>
            {selectedCategory?.image_url && <img src={assetUrl(selectedCategory.image_url)} alt="Current category image (unchanged)" />}
          </div>
          <label className="home-builder-toggle">
            <input type="checkbox" checked={override.enabled === true} onChange={(event) => patchOverride({ enabled: event.target.checked })} />
            Use a custom hero for this category
          </label>
          <div className="home-builder-fields">
            {[
              ['eyebrow', 'Category eyebrow'],
              ['title', 'Category hero headline'],
              ['description', 'Category hero description', 'textarea'],
              ['browserTitle', 'Heading above filtered results'],
              ['imageUrl', 'Category hero image (optional)', 'image'],
              ['imagePosition', 'Category image position']
            ].map(([field, label, type]) => <EditableField key={field} label={label} type={type} value={override[field]}
              onChange={(value) => patchOverride({ [field]: value })} upload={upload} />)}
          </div>
          <div className="services-builder-hero-review">
            <span>Category preview</span><strong>{currentPreviewHero.title}</strong>
            <p>{currentPreviewHero.description}</p>
            <small>Existing category and service images remain untouched.</small>
          </div>
        </div> : <>
          <div className="home-builder-fields">
            {(fields[tab] || []).map(([field, label, type]) => <EditableField key={field} label={label} type={type}
              value={section[field]} onChange={(value) => patchSection(tab, { [field]: value })} upload={upload} />)}
          </div>
          {(toggles[tab] || []).length > 0 && <div className="services-builder-toggles">
            {(toggles[tab] || []).map(([field, label]) => <label key={field}>
              <input type="checkbox" checked={section[field] !== false} onChange={(event) => patchSection(tab, { [field]: event.target.checked })} /> {label}
            </label>)}
          </div>}
          {tab === 'categories' && <>
            <div className="services-builder-selectors">
              <label>Category sort
                <select value={section.sortMode || 'current'} onChange={(event) => patchSection(tab, { sortMode: event.target.value })}>
                  <option value="current">Current order (preserve site)</option>
                  <option value="database">Catalogue sort order</option>
                  <option value="alphabetical">A–Z</option>
                  <option value="custom">Custom order below</option>
                </select>
              </label>
              <label>Layout
                <select value={section.displayMode || 'grid'} onChange={(event) => patchSection(tab, { displayMode: event.target.value })}>
                  <option value="grid">Grid</option><option value="list">List</option>
                </select>
              </label>
            </div>
            <div className="services-builder-category-order">
              <h3>Arrange category cards</h3>
              {orderedCategories.map((item, index) => <div key={item.slug} className="services-builder-category-row">
                {item.image_url ? <img src={assetUrl(item.image_url)} alt="" /> : <span className="services-builder-category-fallback">TS</span>}
                <span><strong>{item.title}</strong><small>{item.service_count} services · {item.slug}</small></span>
                <div>
                  <button type="button" aria-label={'Move ' + item.title + ' up'} disabled={!index} onClick={() => changeOrder(item.slug, -1)}>↑</button>
                  <button type="button" aria-label={'Move ' + item.title + ' down'} disabled={index === orderedCategories.length - 1} onClick={() => changeOrder(item.slug, 1)}>↓</button>
                </div>
              </div>)}
            </div>
          </>}
          {tab === 'hero' && <p className="services-builder-preserve-note">Default hero image preserved until you choose to replace it. The income-tax-services category can have its own optional hero.</p>}
          {tab === 'cards' && <p className="services-builder-preserve-note">Service titles, prices, descriptions, category and all existing image URLs are still populated from the Services database.</p>}
        </>}
        <div className="home-builder-bottom">
          <button type="button" className="button light" onClick={() => {
            if (window.confirm('Discard local edits and restore published page settings?')) { setContent(normalizeServicesPage(published)); setNotice('Published settings restored in this editor.'); }
          }}>Revert to live</button>
          <span>{dirty ? 'Unsaved changes' : notLive ? 'Draft differs from live' : 'All changes published'}</span>
          <button type="button" className="button dark" disabled={!!busy || !notLive} onClick={() => save('publish')}>Publish live</button>
        </div>
      </main>
    </div>
  </div>;
}
