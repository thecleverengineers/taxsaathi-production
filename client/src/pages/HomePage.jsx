import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api, assetUrl } from '../lib/api';
import { money } from '../lib/format';
import Icon from '../components/Icons';
import './home-premium.css';

const editorialSlides = [
  {
    eyebrow: 'Income Tax · GST · Compliance',
    title: 'Professional guidance at every stage.',
    text: 'Explore a service, share your documents, track the work, and receive approved deliverables in one connected Tax Saathi workspace.',
    image: '/legacy-assets/uploads/service-banners/banner_20260519070346_7c66d9c8b29e.jpg'
  },
  {
    eyebrow: 'Your client workspace',
    title: 'A clearer view of what comes next.',
    text: 'Stay informed about submissions, document requests, payments, and progress without losing track of the details.',
    image: '/legacy-assets/uploads/service-banners/banner_20260519100545_3a39b21b4f20.jpg'
  },
  {
    eyebrow: 'Connected service delivery',
    title: 'From the first enquiry to final delivery.',
    text: 'Keep each service request organised with updates, secure files, and a straightforward review process.',
    image: '/legacy-assets/uploads/service-banners/banner_20260519134532_535851726f9f.png'
  }
];

const emptyHomeData = { services: [], featured: [], categories: [], faqs: [], testimonials: [], settings: {} };

function GoldArrow({ size = 17 }) {
  return <Icon name="arrow" size={size} />;
}

function ServiceCard({ service }) {
  const image = service.icon_url || service.category?.image_url;
  return (
    <article className="ts-home-service-card">
      <Link to={'/services/' + service.slug} className="ts-home-service-cover" aria-label={'View ' + service.title}>
        {image ? <img src={assetUrl(image)} alt="" loading="lazy" /> :
          <span className="ts-home-service-fallback"><Icon name="briefcase" size={34} /></span>}
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
    </article>
  );
}

function HeroVisual() {
  return (
    <div className="ts-home-hero-visual">
      <div className="ts-home-halo ts-home-halo-outer" aria-hidden="true" />
      <div className="ts-home-halo ts-home-halo-inner" aria-hidden="true" />
      <div className="ts-home-logo-plinth">
        <img src="/taxsaathi-logo.png" alt="Tax Saathi logo — Less Stress, More Success" />
      </div>
      <div className="ts-home-hero-script">Less Stress, More Success.</div>
      <div className="ts-home-floating ts-home-floating-top" aria-hidden="true">
        <span className="ts-home-floating-icon"><Icon name="shield" size={19} /></span>
        <span><strong>Secure client workspace</strong><small>Documents and service records</small></span>
      </div>
      <div className="ts-home-floating ts-home-floating-bottom" aria-hidden="true">
        <div className="ts-home-mini-bars"><i /><i /><i /><i /><i /></div>
        <span><strong>Clear progress</strong><small>From request to delivery</small></span>
      </div>
      <div className="ts-home-hero-shine" aria-hidden="true" />
    </div>
  );
}

function SectionHeading({ eyebrow, title, description, action, href }) {
  return (
    <div className="ts-home-section-heading">
      <div><span className="ts-home-kicker">{eyebrow}</span><h2>{title}</h2>{description && <p>{description}</p>}</div>
      {action && <Link to={href} className="ts-home-text-link">{action} <GoldArrow /></Link>}
    </div>
  );
}

export default function HomePage() {
  const [data, setData] = useState(emptyHomeData);
  const [slide, setSlide] = useState(0);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    let active = true;
    api('/public/home')
      .then((payload) => { if (active) setData(payload && typeof payload === 'object' ? payload : emptyHomeData); })
      .catch(() => { if (active) setData(emptyHomeData); })
      .finally(() => { if (active) setIsLoading(false); });
    return () => { active = false; };
  }, []);

  useEffect(() => {
    if (typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    const timer = setInterval(() => setSlide((current) => (current + 1) % editorialSlides.length), 6500);
    return () => clearInterval(timer);
  }, []);

  const settings = data.settings || {};
  const slides = editorialSlides.map((item, index) => ({
    ...item,
    title: settings['hero_slide_' + (index + 1) + '_title'] || item.title,
    text: settings['hero_slide_' + (index + 1) + '_text'] || item.text,
    eyebrow: settings['hero_slide_' + (index + 1) + '_eyebrow'] || item.eyebrow
  }));
  const featuredServices = (data.featured?.length ? data.featured : data.services || []).slice(0, 6);
  const categories = (data.categories || []).slice(0, 8);
  const testimonials = (data.testimonials || []).slice(0, 3);
  const faqs = (data.faqs || []).slice(0, 6);
  const selected = slides[slide];

  return (
    <div className="ts-home">
      <section className="ts-home-hero">
        <div className="ts-home-hero-ring" aria-hidden="true" />
        <div className="container-wide ts-home-hero-grid">
          <div className="ts-home-hero-content">
            <span className="ts-home-kicker ts-home-kicker-light"><span className="ts-home-kicker-dot" /> TAX · GST · COMPLIANCE · ADVISORY</span>
            <h1>Less stress.<br /><em>More success.</em></h1>
            <p>Taxes and compliance, made simpler. Discover the right service, manage your paperwork, and stay informed at every step with Tax Saathi.</p>
            <div className="ts-home-hero-actions">
              <Link to="/services" className="ts-home-button ts-home-button-gold">Explore Services <GoldArrow size={19} /></Link>
              <Link to="/auth" className="ts-home-button ts-home-button-ghost">My Workspace <GoldArrow size={18} /></Link>
            </div>
            <a href="#taxsaathi-intro-video" className="ts-home-hero-video-link" aria-label="Watch the TaxSaathi introduction video">
              <span aria-hidden="true">▶</span> Watch our 10-second film
            </a>
            <div className="ts-home-hero-assurances">
              <span><Icon name="shield" size={17} /> Secure records</span>
              <span><Icon name="check" size={17} /> Guided process</span>
              <span><Icon name="headphones" size={17} /> Human support</span>
            </div>
          </div>
          <HeroVisual />
        </div>
        <div className="ts-home-hero-base" aria-hidden="true" />
      </section>

      <section className="ts-home-trust container-wide" aria-label="What you can do with Tax Saathi">
        <div className="ts-home-trust-intro"><span className="ts-home-trust-rule" /><strong>One connected workspace</strong><span>For your tax and compliance journey.</span></div>
        <div><span className="ts-home-trust-icon"><Icon name="file" size={20} /></span><span><strong>Organised paperwork</strong><small>Keep information together</small></span></div>
        <div><span className="ts-home-trust-icon"><Icon name="activity" size={20} /></span><span><strong>Visible progress</strong><small>Follow each stage</small></span></div>
        <div><span className="ts-home-trust-icon"><Icon name="shield" size={20} /></span><span><strong>Protected access</strong><small>Role-aware workspaces</small></span></div>
      </section>


      <section className="ts-home-film" id="taxsaathi-intro-video" aria-labelledby="ts-home-film-title">
        <div className="container-wide ts-home-film-layout">
          <div className="ts-home-film-content">
            <span className="ts-home-kicker ts-home-kicker-light">SEE TAX SAATHI IN ACTION</span>
            <h2 id="ts-home-film-title">Filing made simple. <em>Life made easier.</em></h2>
            <p>From tax-filing stress to expert support and a clearer path forward. Discover Tax Saathi in this short film.</p>
            <div className="ts-home-film-cta">
              <Link to="/services" className="ts-home-button ts-home-button-gold">Find your service <GoldArrow size={17} /></Link>
              <span><Icon name="shield" size={16} /> Professional tax & compliance support</span>
            </div>
          </div>
          <div className="ts-home-film-stage">
            <div className="ts-home-film-frame">
              <video
                className="ts-home-film-video"
                controls
                autoPlay
                muted
                loop
                playsInline
                preload="metadata"
                aria-label="Tax Saathi promotional film about income tax return filing"
              >
                <source src="https://d2ol7oe51mr4n9.cloudfront.net/user_35jIl5jZWKG5RRMETOQUSKTvh7o/8a5c3b32-66b6-452d-acc9-b9253f1e7e1b.mp4" type="video/mp4" />
                Your browser does not support video playback.
              </video>
            </div>
            <div className="ts-home-film-footer">
              <span><span aria-hidden="true">▶</span> WATCH THE TAX SAATHI FILM</span>
              <span>00:10 · FULL HD · UNMUTE TO HEAR AUDIO</span>
            </div>
          </div>
        </div>
      </section>

      <section className="ts-home-section ts-home-services">
        <div className="container-wide">
          <SectionHeading eyebrow="OUR SERVICES" title="Expert help, without the complexity."
            description="Explore available tax, filing, and business compliance services. Select a service to review details and requirements."
            action="All services" href="/services" />
          {featuredServices.length > 0 ? (
            <div className="ts-home-services-grid">
              {featuredServices.map((service) => <ServiceCard key={service.id} service={service} />)}
            </div>
          ) : (
            <div className="ts-home-empty">
              <Icon name="briefcase" size={28} />
              <h3>{isLoading ? 'Loading available services…' : 'Explore our service catalogue'}</h3>
              <p>{isLoading ? 'Fetching the latest service information.' : 'Browse the full range of tax and compliance support.'}</p>
              <Link className="ts-home-text-link" to="/services">Browse services <GoldArrow /></Link>
            </div>
          )}
        </div>
      </section>

      {categories.length > 0 && <section className="ts-home-section ts-home-categories">
        <div className="container-wide">
          <SectionHeading eyebrow="FIND YOUR SERVICE" title="A starting point for every need."
            description="Choose a category to see the services available to you." />
          <div className="ts-home-categories-grid">
            {categories.map((category) => <Link to={'/services?category=' + encodeURIComponent(category.slug)}
              className="ts-home-category" key={category.id}>
              {category.image_url ? <img loading="lazy" src={assetUrl(category.image_url)} alt="" /> :
                <span className="ts-home-category-placeholder">{String(category.title || 'T').charAt(0)}</span>}
              <span className="ts-home-category-shade" />
              <span className="ts-home-category-copy"><strong>{category.title}</strong><small>Explore category <GoldArrow size={14} /></small></span>
            </Link>)}
          </div>
        </div>
      </section>}

      <section className="ts-home-section ts-home-editorial">
        <div className="container-wide ts-home-editorial-grid">
          <div className="ts-home-editorial-image">
            <img src={assetUrl(selected.image)} alt="" loading="lazy" />
            <span className="ts-home-image-corner"><Icon name="shield" size={17} /> TAX SAATHI</span>
          </div>
          <div className="ts-home-editorial-copy">
            <span className="ts-home-kicker">{selected.eyebrow}</span>
            <h2>{selected.title}</h2>
            <p>{selected.text}</p>
            <Link to="/register" className="ts-home-button ts-home-button-navy">Get Started <GoldArrow /></Link>
            <div className="ts-home-slide-controls" aria-label="Highlights">
              {slides.map((item, index) => <button key={index} type="button"
                aria-label={'Show highlight ' + (index + 1)}
                aria-pressed={index === slide} className={index === slide ? 'active' : ''}
                onClick={() => setSlide(index)} />)}
            </div>
          </div>
        </div>
      </section>

      <section className="ts-home-section ts-home-workflow">
        <div className="container-wide ts-home-workflow-grid">
          <div className="ts-home-workflow-copy">
            <span className="ts-home-kicker">HOW IT WORKS</span>
            <h2>From request to result, <em>clearly.</em></h2>
            <p>Find the right service and manage the stages of your application in one place.</p>
            <div className="ts-home-steps">
              <div><span>01</span><div><strong>Choose the right service</strong><p>Review the scope, requirements, and available pricing.</p></div></div>
              <div><span>02</span><div><strong>Share details securely</strong><p>Submit your application and required documents.</p></div></div>
              <div><span>03</span><div><strong>Follow progress and delivery</strong><p>Stay informed through review, approval, and completion.</p></div></div>
            </div>
            <Link to="/services" className="ts-home-text-link">Explore our services <GoldArrow /></Link>
          </div>
          <div className="ts-home-workflow-illustration" aria-label="Illustration of a sample workflow dashboard">
            <div className="ts-home-workflow-circle" />
            <div className="ts-home-workflow-card">
              <div className="ts-home-workflow-card-top"><img src="/taxsaathi-logo.png" alt="" /><span>EXAMPLE WORKFLOW</span><Icon name="more" size={19} /></div>
              <h3>Service application</h3>
              <p>All stages, one clear view</p>
              <div className="ts-home-workflow-progress"><span /></div>
              <div className="ts-home-workflow-stages"><span><i /> Submitted</span><span><i /> In review</span><span><i /> Deliverable</span></div>
              <div className="ts-home-workflow-card-foot"><Icon name="shield" size={17} /> Secure document handling</div>
            </div>
            <div className="ts-home-workflow-float ts-home-workflow-float-a"><Icon name="check" size={18} /> Track each update</div>
            <div className="ts-home-workflow-float ts-home-workflow-float-b"><Icon name="file" size={18} /> Files in one place</div>
          </div>
        </div>
      </section>

      {testimonials.length > 0 && <section className="ts-home-section ts-home-testimonials">
        <div className="container-wide">
          <SectionHeading eyebrow="CLIENT EXPERIENCES" title="People behind the paperwork." />
          <div className="ts-home-testimonial-grid">
            {testimonials.map((item) => <article className="ts-home-testimonial" key={item.id}>
              <div className="ts-home-stars" aria-label="Client testimonial">★★★★★</div>
              <blockquote>“{item.quote}”</blockquote>
              <div className="ts-home-testimonial-person"><span>{String(item.name || 'C').charAt(0)}</span>
                <div><strong>{item.name}</strong><small>{[item.designation, item.company].filter(Boolean).join(' · ')}</small></div></div>
            </article>)}
          </div>
        </div>
      </section>}

      {faqs.length > 0 && <section className="ts-home-section ts-home-faq">
        <div className="container-wide">
          <SectionHeading eyebrow="GOOD TO KNOW" title="Questions, answered." />
          <div className="ts-home-faq-grid">
            {faqs.map((item) => <details key={item.id}>
              <summary>{item.question}<Icon name="chevronDown" size={19} /></summary><p>{item.answer}</p>
            </details>)}
          </div>
        </div>
      </section>}

      <section className="container-wide ts-home-final-cta">
        <span className="ts-home-cta-orbit" aria-hidden="true" />
        <div><span className="ts-home-kicker ts-home-kicker-light">TAKE THE NEXT STEP</span>
          <h2>Less stress starts <em>here.</em></h2>
          <p>Discover services or speak to the Tax Saathi team about your requirements.</p></div>
        <div className="ts-home-cta-actions">
          <Link className="ts-home-button ts-home-button-gold" to="/services">Explore Services <GoldArrow /></Link>
          <Link className="ts-home-button ts-home-button-ghost" to="/contact">Contact Us <GoldArrow /></Link>
        </div>
      </section>
    </div>
  );
}
