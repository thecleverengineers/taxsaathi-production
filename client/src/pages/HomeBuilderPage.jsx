import { useEffect, useState } from 'react';
import { api, assetUrl } from '../lib/api';
import { DEFAULT_HOME, HOME_SECTIONS, homeId, normalizeHome } from '../lib/home-config';
import './home-builder.css';

const sectionMeta = {
  hero: { eyebrow: 'Homepage carousel', help: 'Add, remove, reorder and upload separate images or videos for each hero slide.', fields: [['autoplayMs', 'Slide rotation (milliseconds)', 'number']] },
  trust: { eyebrow: 'Value propositions', fields: [['title', 'Section title'], ['description', 'Supporting line']] },
  video: { eyebrow: 'Promotional media', fields: [['eyebrow', 'Overline'], ['title', 'Headline'], ['accent', 'Emphasised headline'], ['description', 'Description', 'textarea'], ['mediaUrl', 'Video URL', 'video'], ['posterUrl', 'Poster image', 'image'], ['ctaLabel', 'Button label'], ['ctaLink', 'Button link'], ['supportingText', 'Trust message'], ['caption', 'Video footer label'], ['metadata', 'Video footer metadata']] },
  services: { eyebrow: 'Live catalogue', help: 'Cards and prices come directly from Admin → Services. This section controls the presentation.', fields: [['eyebrow', 'Overline'], ['title', 'Headline'], ['description', 'Description', 'textarea'], ['actionLabel', 'View all label'], ['actionLink', 'View all link'], ['limit', 'Number of services (1–12)', 'number']] },
  categories: { eyebrow: 'Live service categories', help: 'Category cards are managed through the service catalogue. Edit this section’s heading and display count here.', fields: [['eyebrow', 'Overline'], ['title', 'Headline'], ['description', 'Description', 'textarea'], ['limit', 'Number of categories (1–12)', 'number']] },
  editorial: { eyebrow: 'Image and text highlights', fields: [['ctaLabel', 'Button label'], ['ctaLink', 'Button link'], ['autoplayMs', 'Rotation (milliseconds)', 'number']] },
  workflow: { eyebrow: 'Customer journey', fields: [['eyebrow', 'Overline'], ['title', 'Headline'], ['accent', 'Emphasised headline'], ['description', 'Description', 'textarea'], ['ctaLabel', 'Button label'], ['ctaLink', 'Button link']] },
  testimonials: { eyebrow: 'Client reviews', help: 'Reviews use the existing published testimonial records.', fields: [['eyebrow', 'Overline'], ['title', 'Headline'], ['limit', 'Maximum items (1–12)', 'number']] },
  faq: { eyebrow: 'Knowledge base', help: 'Questions and answers come from the existing FAQ records.', fields: [['eyebrow', 'Overline'], ['title', 'Headline'], ['limit', 'Maximum items (1–12)', 'number']] },
  cta: { eyebrow: 'Conversion banner', fields: [['eyebrow', 'Overline'], ['title', 'Headline'], ['accent', 'Emphasised headline'], ['description', 'Description', 'textarea'], ['primaryLabel', 'Primary button label'], ['primaryLink', 'Primary destination'], ['secondaryLabel', 'Secondary button label'], ['secondaryLink', 'Secondary destination']] }
};
const slideFields = {
  hero: [['eyebrow', 'Overline'], ['title', 'Headline'], ['accent', 'Emphasised headline'], ['description', 'Supporting text', 'textarea'], ['imageDescription', 'Accessible image description'], ['mediaUrl', 'Image or video URL', 'media'], ['mediaPosition', 'Media position (CSS: center, 50% 30%)'], ['primaryLabel', 'Primary button'], ['primaryLink', 'Primary destination'], ['secondaryLabel', 'Secondary button'], ['secondaryLink', 'Secondary destination']],
  editorial: [['eyebrow', 'Overline'], ['title', 'Headline'], ['description', 'Description', 'textarea'], ['mediaUrl', 'Image', 'image']],
  trust: [['icon', 'Icon name'], ['title', 'Title'], ['description', 'Caption']],
  workflow: [['title', 'Step title'], ['description', 'Step description', 'textarea']]
};
const repeatProps = { hero: 'slides', editorial: 'slides', trust: 'items', workflow: 'steps' };
const draftText = (value) => JSON.stringify(normalizeHome(value));

function ContentField({ field, label, type = 'text', value, onChange, upload }) {
  const [uploading, setUploading] = useState(false);
  async function uploadFile(event) {
    const file = event.target.files?.[0];
    if (!file) return;
    setUploading(true);
    try { await upload(file, onChange); }
    finally { setUploading(false); event.target.value = ''; }
  }
  const isMedia = type === 'image' || type === 'video' || type === 'media';
  return <label className="home-builder-field">
    <span>{label}</span>
    {type === 'textarea' ? <textarea rows="3" value={value ?? ''} onChange={(e) => onChange(e.target.value)} />
      : <input type={type === 'number' ? 'number' : 'text'} min={type === 'number' ? 0 : undefined} value={value ?? ''} onChange={(e) => onChange(type === 'number' ? Number(e.target.value) : e.target.value)} />}
    {isMedia && <>
      <span className="home-builder-upload-line">
        <label className="home-builder-upload"> {uploading ? 'Uploading…' : 'Upload ' + (type === 'video' ? 'video' : type === 'image' ? 'image' : 'image / video')}
          <input disabled={uploading} type="file" accept={type === 'image' ? 'image/jpeg,image/png,image/webp,image/avif,image/gif' : type === 'video' ? 'video/mp4,video/webm' : 'image/*,video/mp4,video/webm'} onChange={uploadFile} />
        </label>
        {value && <button type="button" className="home-builder-mini" onClick={() => onChange('')}>Clear media</button>}
      </span>
      {value && <span className="home-builder-media-preview">
        {/\.(mp4|webm)(\?|$)/i.test(value) || type === 'video' || (type === 'media' && String(value).startsWith('/api/public/home/media/') && field === 'mediaUrl' && false)
          ? <video src={assetUrl(value)} controls muted playsInline /> : <img src={assetUrl(value)} alt="Media preview" loading="lazy" />}
      </span>}
    </>}
  </label>;
}
function listItem(template, kind) {
  if (kind === 'hero') return { ...DEFAULT_HOME.sections.hero.slides[0], id: homeId(), mediaUrl: '', title: 'New hero slide', accent: '', description: '' };
  if (kind === 'editorial') return { id: homeId(), enabled: true, eyebrow: '', title: 'New highlight', description: '', mediaUrl: '' };
  if (kind === 'trust') return { id: homeId(), icon: 'shield', title: 'New benefit', description: '' };
  return { id: homeId(), title: 'New step', description: '' };
}
export default function HomeBuilderPage() {
  const [content, setContent] = useState(null);
  const [published, setPublished] = useState(null);
  const [savedDraft, setSavedDraft] = useState(null);
  const [selected, setSelected] = useState('hero');
  const [notice, setNotice] = useState('');
  const [busy, setBusy] = useState('');
  useEffect(() => {
    api('/admin/home-builder').then((payload) => {
      const draft = normalizeHome(payload.draft || payload.published || DEFAULT_HOME);
      setContent(draft); setSavedDraft(draft); setPublished(normalizeHome(payload.published || DEFAULT_HOME));
    }).catch((error) => setNotice(error.message));
  }, []);
  if (!content) return <div className="workspace-page"><h2>Homepage builder</h2><p>{notice || 'Loading homepage content…'}</p></div>;
  const current = content.sections[selected];
  const descriptor = sectionMeta[selected];
  const changed = draftText(content) !== draftText(savedDraft);
  const liveChanged = draftText(content) !== draftText(published);
  const patchSection = (changes) => setContent((prev) => ({ ...prev, sections: { ...prev.sections, [selected]: { ...prev.sections[selected], ...changes } } }));
  const updateEntry = (list, index, patch) => patchSection({ [list]: current[list].map((entry, n) => n === index ? { ...entry, ...patch } : entry) });
  const swapEntry = (list, index, offset) => {
    const arr = [...current[list]], next = index + offset;
    if (next < 0 || next >= arr.length) return;
    [arr[index], arr[next]] = [arr[next], arr[index]];
    patchSection({ [list]: arr });
  };
  const changeSectionOrder = (id, step) => {
    const arr = [...content.order], idx = arr.indexOf(id), next = idx + step;
    if (idx < 0 || next < 0 || next >= arr.length) return;
    [arr[idx], arr[next]] = [arr[next], arr[idx]];
    setContent((prev) => ({ ...prev, order: arr }));
  };
  async function upload(file, onChange) {
    if (file.size > 45 * 1024 * 1024) { setNotice('Maximum upload size is 45 MB.'); return; }
    const body = new FormData(); body.append('file', file);
    try {
      const result = await api('/admin/home-builder/media', { method: 'POST', body, timeoutMs: 180000 });
      onChange(result.url);
      setNotice('Media uploaded. Publish the homepage to show it on the live site.');
    } catch (error) { setNotice(error.message); }
  }
  async function save(mode) {
    setBusy(mode); setNotice('');
    try {
      const endpoint = mode === 'publish' ? '/admin/home-builder/publish' : '/admin/home-builder/draft';
      const result = await api(endpoint, { method: mode === 'publish' ? 'POST' : 'PUT', body: { content } });
      const stored = normalizeHome(mode === 'publish' ? result.content : result.draft);
      setSavedDraft(stored);
      if (mode === 'publish') setPublished(stored);
      setNotice(mode === 'publish' ? 'Homepage published successfully. Customers will see the update on their next page load.' : 'Draft saved. Public homepage is unchanged.');
      return true;
    } catch (error) { setNotice(error.message); return false; }
    finally { setBusy(''); }
  }
  async function preview() {
    setBusy('preview');
    try {
      const result = await api('/admin/home-builder/draft', { method: 'PUT', body: { content } });
      setSavedDraft(normalizeHome(result.draft));
      window.open('/?home_preview=1', '_blank', 'noopener,noreferrer');
      setNotice('Preview uses your saved draft, not the live page.');
    } catch (error) { setNotice(error.message); }
    finally { setBusy(''); }
  }
  const list = repeatProps[selected];
  const entries = list ? current[list] || [] : [];
  return <div className="workspace-page home-builder">
    <div className="home-builder-header">
      <div><span className="home-builder-overline">TAX SAATHI · WEBSITE CMS</span><h1>Homepage builder</h1>
        <p>Manage every homepage section, carousel, call-to-action and media asset from Admin.</p></div>
      <div className="home-builder-actions">
        <button className="button light" type="button" disabled={Boolean(busy)} onClick={preview}>Preview draft</button>
        <button className="button light" type="button" disabled={Boolean(busy) || !changed} onClick={() => save('draft')}>{busy === 'draft' ? 'Saving…' : 'Save draft'}</button>
        <button className="button dark" type="button" disabled={Boolean(busy) || !liveChanged} onClick={() => save('publish')}>{busy === 'publish' ? 'Publishing…' : 'Publish live'}</button>
      </div>
    </div>
    {notice && <div role="status" className="home-builder-notice">{notice}</div>}
    <div className="home-builder-layout">
      <aside className="home-builder-sidebar" aria-label="Homepage sections">
        <div className="home-builder-side-title"><strong>Page sections</strong><span>Order · visibility</span></div>
        {content.order.map((id, index) => {
          const label = HOME_SECTIONS.find((item) => item[0] === id)?.[1] || id;
          const enabled = content.sections[id]?.enabled !== false;
          return <div className={'home-builder-navitem ' + (selected === id ? 'selected' : '')} key={id}>
            <button type="button" className="home-builder-select" onClick={() => setSelected(id)}>
              <span className="home-builder-section-number">{String(index + 1).padStart(2, '0')}</span>
              <span>{label}<small>{enabled ? 'Visible' : 'Hidden'}</small></span>
            </button>
            <div className="home-builder-order">
              <button type="button" aria-label={'Move ' + label + ' up'} disabled={!index} onClick={() => changeSectionOrder(id, -1)}>↑</button>
              <button type="button" aria-label={'Move ' + label + ' down'} disabled={index === content.order.length - 1} onClick={() => changeSectionOrder(id, 1)}>↓</button>
            </div>
          </div>;
        })}
        <p className="home-builder-side-note">Featured services, categories, FAQs and reviews still use the existing live database records.</p>
      </aside>
      <main className="home-builder-main">
        <div className="home-builder-section-top"><div><span className="home-builder-overline">{descriptor.eyebrow}</span><h2>{HOME_SECTIONS.find((item) => item[0] === selected)?.[1]}</h2>
          {descriptor.help && <p>{descriptor.help}</p>}</div>
          <label className="home-builder-toggle"><input type="checkbox" checked={current.enabled !== false} onChange={(e) => patchSection({ enabled: e.target.checked })} /> Show section</label>
        </div>
        <div className="home-builder-fields">
          {descriptor.fields.map(([key, label, type]) =>
            <ContentField key={key} field={key} label={label} type={type} value={current[key]}
              onChange={(value) => patchSection({ [key]: value })} upload={upload} />)}
          {selected === 'video' && <div className="home-builder-flags">
            {['autoplay', 'muted', 'loop'].map((field) => <label key={field}><input type="checkbox" checked={current[field] !== false} onChange={(e) => patchSection({ [field]: e.target.checked })} /> {field}</label>)}
          </div>}
          {selected === 'hero' && <label className="home-builder-toggle"><input type="checkbox" checked={current.brandBadge !== false} onChange={(e) => patchSection({ brandBadge: e.target.checked })} /> Show logo overlay</label>}
          {list && <section className="home-builder-repeat">
            <div className="home-builder-repeat-title"><h3>{selected === 'hero' ? 'Hero slides' : selected === 'editorial' ? 'Carousel highlights' : selected === 'trust' ? 'Trust points' : 'Journey steps'} <span>{entries.length}</span></h3>
              <button className="button light small" type="button" disabled={entries.length >= 20} onClick={() => patchSection({ [list]: [...entries, listItem(null, selected)] })}>+ Add {selected === 'hero' || selected === 'editorial' ? 'slide' : 'item'}</button></div>
            {entries.map((entry, index) => <details className="home-builder-entry" key={entry.id || index} open={index === entries.length - 1 && entries.length > 0}>
              <summary><span>{String(index + 1).padStart(2, '0')} · {entry.title || 'Untitled'}</span><span>{entry.enabled === false ? 'Disabled' : 'Edit'}</span></summary>
              <div className="home-builder-entry-body">
                <div className="home-builder-entry-actions">
                  {(selected === 'hero' || selected === 'editorial') && <label><input type="checkbox" checked={entry.enabled !== false} onChange={(e) => updateEntry(list, index, { enabled: e.target.checked })} /> Enabled</label>}
                  <button type="button" disabled={!index} onClick={() => swapEntry(list, index, -1)}>Move up</button>
                  <button type="button" disabled={index === entries.length - 1} onClick={() => swapEntry(list, index, 1)}>Move down</button>
                  <button type="button" className="home-builder-danger" disabled={entries.length < 2 && selected === 'hero'} onClick={() => patchSection({ [list]: entries.filter((_, n) => n !== index) })}>Remove</button>
                </div>
                {selected === 'hero' && <label className="home-builder-field">Media type
                  <select value={entry.mediaType || 'image'} onChange={(e) => updateEntry(list, index, { mediaType: e.target.value })}><option value="image">Image</option><option value="video">Video</option></select></label>}
                <div className="home-builder-fields">{slideFields[selected].map(([key, label, type]) =>
                  <ContentField key={key} field={key} label={label}
                    type={type === 'media' ? entry.mediaType === 'video' ? 'video' : 'image' : type}
                    value={entry[key]}
                    onChange={(value) => updateEntry(list, index, { [key]: value })} upload={upload} />)}</div>
              </div>
            </details>)}
          </section>}
        </div>
        <div className="home-builder-bottom">
          <button type="button" className="button light" onClick={() => { if (window.confirm('Discard unsaved edits and restore the last published homepage?')) { setContent(normalizeHome(published)); setNotice('Restored published content locally. Save or publish to keep this version.'); } }}>Revert to published</button>
          <span>{changed ? 'Unsaved edits' : liveChanged ? 'Draft differs from live' : 'All changes published'}</span>
          <button type="button" className="button dark" disabled={Boolean(busy)} onClick={() => save('publish')}>Publish live</button>
        </div>
      </main>
    </div>
  </div>;
}
