import { useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { api, assetUrl } from '../lib/api';
import { money } from '../lib/format';
import { DEFAULT_HOME, normalizeHome } from '../lib/home-config';
import Icon from '../components/Icons';
import './home-premium.css';
import './home-carousel.css';
import './home-dynamic.css';

function GoldArrow({ size = 17 }) { return <Icon name="arrow" size={size} />; }
function Action({ to = '/services', children, className = '' }) {
  return /^https?:\/\//i.test(to)
    ? <a href={to} className={className} target="_blank" rel="noopener noreferrer">{children}</a>
    : <Link to={to} className={className}>{children}</Link>;
}
function SectionHeading({ section: s, action = false }) {
  return <div className="ts-home-section-heading">
    <div><span className="ts-home-kicker">{s.eyebrow}</span><h2>{s.title}</h2>{s.description && <p>{s.description}</p>}</div>
    {action && s.actionLabel && <Action to={s.actionLink || '/services'} className="ts-home-text-link">{s.actionLabel} <GoldArrow /></Action>}
  </div>;
}
function ServiceCard({ service }) {
  const image = service.icon_url || service.category?.image_url;
  return <article className="ts-home-service-card">
    <Link to={'/services/' + service.slug} className="ts-home-service-cover" aria-label={'View ' + service.title}>
      {image ? <img src={assetUrl(image)} alt="" loading="lazy" /> : <span className="ts-home-service-fallback"><Icon name="briefcase" size={34} /></span>}
      <span className="ts-home-service-open"><GoldArrow /></span>
    </Link>
    <div className="ts-home-service-body">
      <span className="ts-home-service-type">{service.category?.title || service.service_category || 'Professional service'}</span>
      <h3><Link to={'/services/' + service.slug}>{service.title}</Link></h3>
      <p>{service.excerpt || 'Structured service delivery with clear requirements and transparent progress.'}</p>
      <div className="ts-home-service-bottom">
        <span className="ts-home-service-fee">{service.filing_fee == null ? 'Ask for pricing' : money(service.filing_fee)}</span>
        <Link to={'/services/' + service.slug}>Details <GoldArrow size={15} /></Link>
      </div>
    </div>
  </article>;
}
export default function DynamicHomePage() {
  const [data, setData] = useState({});
  const [heroIndex, setHeroIndex] = useState(0);
  const [editorialIndex, setEditorialIndex] = useState(0);
  const [heroPaused, setHeroPaused] = useState(false);
  const [loading, setLoading] = useState(true);
  const touchStart = useRef(null);
  const preview = new URLSearchParams(window.location.search).get('home_preview') === '1';
  useEffect(() => {
    let active = true;
    api('/public/home').then(async (payload) => {
      if (preview) {
        try {
          const cms = await api('/admin/home-builder');
          return { ...payload, homeContent: cms.draft || cms.published || DEFAULT_HOME, previewMode: true };
        } catch { /* Never expose drafts to unauthenticated users. */ }
      }
      return payload;
    }).then((payload) => { if (active) setData(payload || {}); })
      .catch(() => { if (active) setData({}); })
      .finally(() => { if (active) setLoading(false); });
    return () => { active = false; };
  }, [preview]);
  const home = normalizeHome(data.homeContent);
  const config = (name) => home.sections[name];
  const hero = config('hero');
  const mainSlides = (hero.slides || []).filter((slide) => slide.enabled !== false);
  const heroSlides = mainSlides.length ? mainSlides : DEFAULT_HOME.sections.hero.slides.slice(0, 1);
  const editorial = config('editorial');
  const highlights = (editorial.slides || []).filter((slide) => slide.enabled !== false);
  const editorialSlides = highlights.length ? highlights : DEFAULT_HOME.sections.editorial.slides.slice(0, 1);
  const chosenHeroIndex = heroIndex % heroSlides.length;
  const chosenEditorialIndex = editorialIndex % editorialSlides.length;
  const currentHero = heroSlides[chosenHeroIndex];
  const selected = editorialSlides[chosenEditorialIndex];
  const featuredServices = (data.featured?.length ? data.featured : data.services || []).slice(0, Math.min(12, Math.max(1, Number(config('services').limit) || 6)));
  const categories = (data.categories || []).slice(0, Math.min(12, Math.max(1, Number(config('categories').limit) || 8)));
  const testimonials = (data.testimonials || []).slice(0, Math.min(12, Math.max(1, Number(config('testimonials').limit) || 3)));
  const faqs = (data.faqs || []).slice(0, Math.min(12, Math.max(1, Number(config('faq').limit) || 6)));
  useEffect(() => {
    if (heroPaused || window.matchMedia?.('(prefers-reduced-motion: reduce)').matches || heroSlides.length < 2) return undefined;
    const timer = window.setInterval(() => setHeroIndex((index) => (index + 1) % heroSlides.length),
      Math.max(3000, Number(hero.autoplayMs) || 7200));
    return () => window.clearInterval(timer);
  }, [heroPaused, heroSlides.length, hero.autoplayMs]);
  useEffect(() => {
    if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches || editorialSlides.length < 2) return undefined;
    const timer = window.setInterval(() => setEditorialIndex((index) => (index + 1) % editorialSlides.length),
      Math.max(3000, Number(editorial.autoplayMs) || 6500));
    return () => window.clearInterval(timer);
  }, [editorialSlides.length, editorial.autoplayMs]);
  const moveHero = (delta) => setHeroIndex((index) => (index + delta + heroSlides.length) % heroSlides.length);
  const onTouchStart = (event) => { touchStart.current = event.changedTouches[0]?.clientX ?? null; };
  const onTouchEnd = (event) => {
    const start = touchStart.current; touchStart.current = null;
    if (start == null) return;
    const distance = (event.changedTouches[0]?.clientX ?? start) - start;
    if (Math.abs(distance) > 42) moveHero(distance > 0 ? -1 : 1);
  };
  const sections = {
    hero: <section className="ts-image-hero" aria-label="Tax Saathi services and guidance"
      onMouseEnter={() => setHeroPaused(true)} onMouseLeave={() => setHeroPaused(false)}
      onFocusCapture={() => setHeroPaused(true)} onBlurCapture={() => setHeroPaused(false)}
      onTouchStart={onTouchStart} onTouchEnd={onTouchEnd}>
      <div className="container-wide ts-image-hero-shell">
        <div className="ts-image-hero-media">
          {heroSlides.map((item, index) => item.mediaType === 'video' && item.mediaUrl
            ? <video key={item.id || index} src={assetUrl(item.mediaUrl)} className={'ts-image-hero-photo ts-image-hero-video' + (index === chosenHeroIndex ? ' is-active' : '')}
                autoPlay={index === chosenHeroIndex} muted loop playsInline preload="metadata" aria-hidden={index !== chosenHeroIndex} />
            : <div key={item.id || index} className={'ts-image-hero-photo' + (index === chosenHeroIndex ? ' is-active' : '')}
                role="img" aria-label={item.imageDescription || item.title} aria-hidden={index !== chosenHeroIndex}
                style={{ backgroundImage: item.mediaUrl ? 'url("' + assetUrl(item.mediaUrl) + '")' : undefined,
                  backgroundSize: item.mediaUrl ? 'cover' : undefined,
                  backgroundPosition: item.mediaUrl ? (item.mediaPosition || 'center') : (index * 25) + '% 50%' }} />)}
          {hero.brandBadge !== false && <span className="ts-image-hero-media-brand"><img src="/taxsaathi-logo.png" alt="" /> TAX SAATHI</span>}
        </div>
        <div className="ts-image-hero-copy">
          <span className="ts-image-hero-label">{currentHero.eyebrow}</span>
          <h1>{currentHero.title}<em>{currentHero.accent}</em></h1>
          <p className="ts-image-hero-description">{currentHero.description}</p>
          <div className="ts-image-hero-actions">
            {currentHero.primaryLabel && <Action to={currentHero.primaryLink || '/services'} className="ts-home-button ts-home-button-gold">{currentHero.primaryLabel} <GoldArrow size={18} /></Action>}
            {currentHero.secondaryLabel && <Action to={currentHero.secondaryLink || '/auth'} className="ts-home-button ts-home-button-ghost">{currentHero.secondaryLabel} <GoldArrow size={17} /></Action>}
          </div>
          <div className="ts-image-hero-nav" aria-label="Choose a hero slide">
            <button type="button" className="ts-image-hero-arrow" aria-label="Previous slide" onClick={() => moveHero(-1)}>‹</button>
            <div className="ts-image-hero-dots">
              {heroSlides.map((item, index) => <button type="button" key={item.id || index}
                className={'ts-image-hero-dot' + (index === chosenHeroIndex ? ' is-active' : '')}
                aria-label={'Show slide ' + (index + 1) + ': ' + item.title}
                aria-pressed={index === chosenHeroIndex} onClick={() => setHeroIndex(index)} />)}
            </div>
            <button type="button" className="ts-image-hero-arrow" aria-label="Next slide" onClick={() => moveHero(1)}>›</button>
            <span className="ts-image-hero-counter">{String(chosenHeroIndex + 1).padStart(2, '0')} / {String(heroSlides.length).padStart(2, '0')}</span>
          </div>
          <div className="ts-image-hero-assurances">
            <span><Icon name="shield" size={16} /> Secure records</span>
            <span><Icon name="check" size={16} /> Guided process</span>
            <span><Icon name="headphones" size={16} /> Human support</span>
          </div>
        </div>
      </div>
    </section>,
    trust: <section className="ts-home-trust container-wide" aria-label="What you can do with Tax Saathi">
      <div className="ts-home-trust-intro"><span className="ts-home-trust-rule" /><strong>{config('trust').title}</strong><span>{config('trust').description}</span></div>
      {(config('trust').items || []).map((item, index) => <div key={item.id || index}>
        <span className="ts-home-trust-icon"><Icon name={item.icon || 'shield'} size={20} /></span>
        <span><strong>{item.title}</strong><small>{item.description}</small></span>
      </div>)}
    </section>,
    video: <section className="ts-home-film" id="taxsaathi-intro-video">
      <div className="container-wide ts-home-film-layout">
        <div className="ts-home-film-content">
          <span className="ts-home-kicker ts-home-kicker-light">{config('video').eyebrow}</span>
          <h2>{config('video').title} <em>{config('video').accent}</em></h2>
          <p>{config('video').description}</p>
          <div className="ts-home-film-cta">
            <Action to={config('video').ctaLink || '/services'} className="ts-home-button ts-home-button-gold">{config('video').ctaLabel} <GoldArrow size={17} /></Action>
            <span><Icon name="shield" size={16} /> {config('video').supportingText}</span>
          </div>
        </div>
        <div className="ts-home-film-stage">
          <div className="ts-home-film-frame">
            <video className="ts-home-film-video" controls
              autoPlay={config('video').autoplay !== false}
              muted={config('video').muted !== false}
              loop={config('video').loop !== false} playsInline preload="metadata"
              poster={config('video').posterUrl ? assetUrl(config('video').posterUrl) : undefined}
              aria-label="Tax Saathi promotional film">
              <source src={assetUrl(config('video').mediaUrl || '/media/taxsaathi-intro.mp4')} />
              Your browser does not support video playback.
            </video>
          </div>
          <div className="ts-home-film-footer">
            <span><span aria-hidden="true">▶</span> {config('video').caption}</span>
            <span>{config('video').metadata}</span>
          </div>
        </div>
      </div>
    </section>,
    services: <section className="ts-home-section ts-home-services">
      <div className="container-wide">
        <SectionHeading section={config('services')} action />
        {featuredServices.length ? <div className="ts-home-services-grid">{featuredServices.map((service) => <ServiceCard key={service.id} service={service} />)}</div>
          : <div className="ts-home-empty"><Icon name="briefcase" size={28} />
            <h3>{loading ? 'Loading available services…' : 'Explore our service catalogue'}</h3>
            <p>{loading ? 'Fetching the latest service information.' : 'Browse the full range of tax and compliance support.'}</p>
            <Link className="ts-home-text-link" to="/services">Browse services <GoldArrow /></Link>
          </div>}
      </div>
    </section>,
    categories: categories.length > 0 ? <section className="ts-home-section ts-home-categories">
      <div className="container-wide">
        <SectionHeading section={config('categories')} />
        <div className="ts-home-categories-grid">
          {categories.map((category) => <Link key={category.id}
            to={'/services?category=' + encodeURIComponent(category.slug)} className="ts-home-category">
            {category.image_url ? <img src={assetUrl(category.image_url)} alt="" loading="lazy" />
              : <span className="ts-home-category-placeholder">{String(category.title || 'T').charAt(0)}</span>}
            <span className="ts-home-category-shade" />
            <span className="ts-home-category-copy"><strong>{category.title}</strong><small>Explore category <GoldArrow size={14} /></small></span>
          </Link>)}
        </div>
      </div>
    </section> : null,
    editorial: <section className="ts-home-section ts-home-editorial">
      <div className="container-wide ts-home-editorial-grid">
        <div className="ts-home-editorial-image">
          {selected.mediaUrl && <img src={assetUrl(selected.mediaUrl)} alt="" loading="lazy" />}
          <span className="ts-home-image-corner"><Icon name="shield" size={17} /> TAX SAATHI</span>
        </div>
        <div className="ts-home-editorial-copy">
          <span className="ts-home-kicker">{selected.eyebrow}</span>
          <h2>{selected.title}</h2>
          <p>{selected.description}</p>
          <Action to={editorial.ctaLink || '/register'} className="ts-home-button ts-home-button-navy">{editorial.ctaLabel} <GoldArrow /></Action>
          <div className="ts-home-slide-controls" aria-label="Highlights">
            {editorialSlides.map((item, index) => <button key={item.id || index} type="button"
              aria-label={'Show highlight ' + (index + 1)} aria-pressed={index === chosenEditorialIndex}
              className={index === chosenEditorialIndex ? 'active' : ''} onClick={() => setEditorialIndex(index)} />)}
          </div>
        </div>
      </div>
    </section>,
    workflow: <section className="ts-home-section ts-home-workflow">
      <div className="container-wide ts-home-workflow-grid">
        <div className="ts-home-workflow-copy">
          <span className="ts-home-kicker">{config('workflow').eyebrow}</span>
          <h2>{config('workflow').title} <em>{config('workflow').accent}</em></h2>
          <p>{config('workflow').description}</p>
          <div className="ts-home-steps">
            {(config('workflow').steps || []).map((item, index) => <div key={item.id || index}>
              <span>{String(index + 1).padStart(2, '0')}</span><div><strong>{item.title}</strong><p>{item.description}</p></div>
            </div>)}
          </div>
          <Action to={config('workflow').ctaLink || '/services'} className="ts-home-text-link">{config('workflow').ctaLabel} <GoldArrow /></Action>
        </div>
        <div className="ts-home-workflow-illustration" aria-label="Illustration of a sample workflow dashboard">
          <div className="ts-home-workflow-circle" />
          <div className="ts-home-workflow-card">
            <div className="ts-home-workflow-card-top"><img src="/taxsaathi-logo.png" alt="" /><span>EXAMPLE WORKFLOW</span><Icon name="more" size={19} /></div>
            <h3>Service application</h3><p>All stages, one clear view</p>
            <div className="ts-home-workflow-progress"><span /></div>
            <div className="ts-home-workflow-stages"><span><i /> Submitted</span><span><i /> In review</span><span><i /> Deliverable</span></div>
            <div className="ts-home-workflow-card-foot"><Icon name="shield" size={17} /> Secure document handling</div>
          </div>
          <div className="ts-home-workflow-float ts-home-workflow-float-a"><Icon name="check" size={18} /> Track each update</div>
          <div className="ts-home-workflow-float ts-home-workflow-float-b"><Icon name="file" size={18} /> Files in one place</div>
        </div>
      </div>
    </section>,
    testimonials: testimonials.length ? <section className="ts-home-section ts-home-testimonials">
      <div className="container-wide">
        <SectionHeading section={config('testimonials')} />
        <div className="ts-home-testimonial-grid">{testimonials.map((item) => <article className="ts-home-testimonial" key={item.id}>
          <div className="ts-home-stars" aria-label="Client testimonial">★★★★★</div>
          <blockquote>“{item.quote}”</blockquote>
          <div className="ts-home-testimonial-person"><span>{String(item.name || 'C').charAt(0)}</span>
            <div><strong>{item.name}</strong><small>{[item.designation, item.company].filter(Boolean).join(' · ')}</small></div>
          </div>
        </article>)}</div>
      </div>
    </section> : null,
    faq: faqs.length ? <section className="ts-home-section ts-home-faq">
      <div className="container-wide">
        <SectionHeading section={config('faq')} />
        <div className="ts-home-faq-grid">{faqs.map((item) => <details key={item.id}>
          <summary>{item.question}<Icon name="chevronDown" size={19} /></summary><p>{item.answer}</p>
        </details>)}</div>
      </div>
    </section> : null,
    cta: <section className="container-wide ts-home-final-cta">
      <span className="ts-home-cta-orbit" aria-hidden="true" />
      <div><span className="ts-home-kicker ts-home-kicker-light">{config('cta').eyebrow}</span>
        <h2>{config('cta').title} <em>{config('cta').accent}</em></h2>
        <p>{config('cta').description}</p></div>
      <div className="ts-home-cta-actions">
        {config('cta').primaryLabel && <Action className="ts-home-button ts-home-button-gold" to={config('cta').primaryLink || '/services'}>{config('cta').primaryLabel} <GoldArrow /></Action>}
        {config('cta').secondaryLabel && <Action className="ts-home-button ts-home-button-ghost" to={config('cta').secondaryLink || '/contact'}>{config('cta').secondaryLabel} <GoldArrow /></Action>}
      </div>
    </section>
  };
  return <div className="ts-home ts-home-dynamic">
    {data.previewMode && <div className="ts-home-preview-marker">ADMIN DRAFT PREVIEW — not published</div>}
    {home.order.map((id) => config(id)?.enabled !== false ? <div className="ts-home-dynamic-slot" key={id}>{sections[id]}</div> : null)}
  </div>;
}
