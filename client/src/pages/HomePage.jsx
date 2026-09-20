import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api, assetUrl } from '../lib/api';
import { money } from '../lib/format';
import Icon from '../components/Icons';
import { PageLoader } from '../components/Ui';

const editorialSlides = [
  { eyebrow: 'Income Tax • GST • Compliance', title: 'Clear support for every compliance stage.', text: 'TaxSaathi brings service discovery, document collection, payment approvals, progress visibility, and final delivery into one structured experience.', image: '/legacy-assets/uploads/service-banners/banner_20260519070346_7c66d9c8b29e.jpg' },
  { eyebrow: 'Client experience', title: 'A digital tax experience built around clarity.', text: 'From choosing a service to receiving completed files, every stage is designed to feel simple, visible, and professional.', image: '/legacy-assets/uploads/service-banners/banner_20260519100545_3a39b21b4f20.jpg' },
  { eyebrow: 'Integrated workflow', title: 'Built for trust, speed, and scale.', text: 'Keep services, orders, uploads, reviews, and operational updates moving through one reliable workspace.', image: '/legacy-assets/uploads/service-banners/banner_20260519134532_535851726f9f.png' }
];

function LandingServiceCard({ service }) {
  const image = service.icon_url || service.category?.image_url;
  return (
    <article className="reference-service-card">
      <Link to={`/services/${service.slug}`} className="reference-service-image">
        {image ? <img src={assetUrl(image)} alt="" /> : <span>{String(service.title || 'TS').slice(0, 2)}</span>}
        <span className="reference-service-arrow"><Icon name="arrow" size={15} /></span>
      </Link>
      <div className="reference-service-body">
        <span className="reference-card-kicker">{service.category?.title || service.service_category || 'Tax services'}</span>
        <h3>{service.title}</h3>
        <p>{service.excerpt || 'A guided service workflow with clear requirements and professional delivery.'}</p>
        <div className="reference-service-meta">
          <strong>{money(service.filing_fee)}</strong>
          <Link to={`/services/${service.slug}`}>View service <Icon name="arrow" size={14} /></Link>
        </div>
      </div>
    </article>
  );
}

export default function HomePage() {
  const [data, setData] = useState(null);
  const [slide, setSlide] = useState(0);

  useEffect(() => {
    api('/public/home')
      .then(setData)
      .catch(() => setData({ services: [], featured: [], categories: [], faqs: [], testimonials: [], settings: {} }));
  }, []);

  useEffect(() => {
    const timer = setInterval(() => setSlide((current) => (current + 1) % editorialSlides.length), 6000);
    return () => clearInterval(timer);
  }, []);

  if (!data) return <PageLoader label="Loading TaxSaathi…" />;

  const settings = data.settings || {};
  const slides = editorialSlides.map((item, index) => ({
    ...item,
    title: settings[`hero_slide_${index + 1}_title`] || item.title,
    text: settings[`hero_slide_${index + 1}_text`] || item.text,
    eyebrow: settings[`hero_slide_${index + 1}_eyebrow`] || item.eyebrow
  }));
  const current = slides[slide];
  const nextImage = slides[(slide + 1) % slides.length].image;
  const services = (data.featured?.length ? data.featured : data.services || []).slice(0, 6);
  const categories = (data.categories || []).slice(0, 8);

  return (
    <div className="reference-home">
      <section className="reference-trust container-wide">
        <div><strong>One structured place</strong><span>for service, payment, documents, and delivery.</span></div>
        <div><Icon name="check" size={18} /><span>Clear workflow</span></div>
        <div><Icon name="shield" size={18} /><span>Secure records</span></div>
        <div><Icon name="headphones" size={18} /><span>Human support</span></div>
      </section>

      <section className="reference-intro container-wide">
        <div>
          <span className="reference-kicker">TaxSaathi workspace</span>
          <h1>Make every compliance decision feel <em>clear.</em></h1>
        </div>
        <div className="reference-intro-copy">
          <p>Professional tax and compliance services arranged around the way people actually work: choose confidently, share securely, and always know what happens next.</p>
          <Link to="/services" className="reference-text-button">Explore services <Icon name="arrow" size={15} /></Link>
        </div>
      </section>

      <section className="reference-collage container-wide">
        <div className="reference-collage-main">
          <img src={assetUrl(current.image)} alt="TaxSaathi service workflow" />
          <span className="reference-image-label">{current.eyebrow}</span>
        </div>
        <div className="reference-collage-note">
          <span className="reference-kicker">Built around your next step</span>
          <h2>{current.title}</h2>
          <p>{current.text}</p>
          <Link to="/auth" className="reference-orange-button">Open your workspace <Icon name="arrow" size={15} /></Link>
        </div>
        <div className="reference-collage-side">
          <img src={assetUrl(nextImage)} alt="TaxSaathi service support" />
          <div><strong>Every stage stays visible.</strong><span>From first enquiry to final delivery.</span></div>
        </div>
        <div className="reference-collage-dots" aria-label="Editorial highlights">
          {slides.map((item, index) => <button key={item.eyebrow} className={slide === index ? 'active' : ''} onClick={() => setSlide(index)} aria-label={`Show highlight ${index + 1}`} />)}
        </div>
      </section>

      <section className="reference-section reference-services-section">
        <div className="container-wide">
          <div className="reference-section-head">
            <div><span className="reference-kicker">Popular services</span><h2>Support that keeps work moving.</h2></div>
            <Link to="/services" className="reference-text-button">View all services <Icon name="arrow" size={15} /></Link>
          </div>
          <div className="reference-service-grid">
            {services.map((service) => <LandingServiceCard service={service} key={service.id} />)}
          </div>
        </div>
      </section>

      <section className="reference-category-section container-wide">
        <div className="reference-section-head">
          <div><span className="reference-kicker">Service categories</span><h2>Find the right starting point.</h2></div>
          <p>Explore the categories already configured in your TaxSaathi workspace.</p>
        </div>
        <div className="reference-category-grid">
          {categories.map((category) => <Link to={`/services?category=${category.slug}`} className="reference-category-card" key={category.id}>
            {category.image_url ? <img src={assetUrl(category.image_url)} alt="" /> : <span className="reference-category-letter">{category.title.slice(0, 1)}</span>}
            <span className="reference-category-overlay"><strong>{category.title}</strong><small>Explore services <Icon name="arrow" size={13} /></small></span>
          </Link>)}
        </div>
      </section>

      <section className="reference-workflow container-wide">
        <div className="reference-workflow-art">
          <div className="reference-dashboard-card">
            <div className="reference-dashboard-top"><span>Application overview</span><Icon name="more" size={16} /></div>
            <strong>ITR filing</strong><small>Individual return · FY 2025–26</small>
            <div className="reference-progress"><span /></div>
            <div className="reference-dashboard-foot"><span>In review</span><b>68%</b></div>
          </div>
          <div className="reference-workflow-chip chip-top"><Icon name="check" size={14} /> Documents verified</div>
          <div className="reference-workflow-chip chip-bottom"><Icon name="shield" size={14} /> Secure client records</div>
        </div>
        <div className="reference-workflow-copy">
          <span className="reference-kicker">A smoother process</span>
          <h2>Everything important stays visible.</h2>
          <p>TaxSaathi combines service discovery, orders, document collection, payment confirmation, progress updates, and completed file delivery into one connected workflow.</p>
          <div className="reference-steps">
            <div><b>01</b><span><strong>Choose a service</strong><small>See pricing, turnaround, benefits, and requirements.</small></span></div>
            <div><b>02</b><span><strong>Submit details securely</strong><small>Keep client information and uploads attached to the right order.</small></span></div>
            <div><b>03</b><span><strong>Track until delivery</strong><small>Follow approvals, work in progress, and completed outputs.</small></span></div>
          </div>
          <Link to="/auth" className="reference-dark-button">Start your workspace <Icon name="arrow" size={15} /></Link>
        </div>
      </section>

      <section className="reference-metrics">
        <div className="container-wide reference-metrics-grid">
          <div><strong>25k<sup>+</sup></strong><span>Tax and compliance actions completed</span></div>
          <div><strong>99<sup>%</sup></strong><span>Structured workflow visibility for clients</span></div>
          <div><strong>87<sup>%</sup></strong><span>Customers supported with confidence</span></div>
        </div>
      </section>

      <section className="reference-testimonial-section container-wide">
        <div className="reference-section-head"><div><span className="reference-kicker">Client voices</span><h2>Designed to feel organized from the first click.</h2></div></div>
        <div className="reference-testimonial-grid">
          {(data.testimonials || []).slice(0, 3).map((item) => <article className="reference-testimonial" key={item.id}>
            <div className="reference-rating">★★★★★</div><p>“{item.quote}”</p>
            <div className="reference-person"><span>{item.name?.slice(0, 1) || 'C'}</span><div><strong>{item.name}</strong><small>{[item.designation, item.company].filter(Boolean).join(' · ')}</small></div></div>
          </article>)}
        </div>
      </section>

      <section className="reference-faq-section container-wide">
        <div className="reference-section-head"><div><span className="reference-kicker">Questions</span><h2>Clear answers before you begin.</h2></div></div>
        <div className="faq-grid">{(data.faqs || []).map((item) => <details key={item.id}><summary>{item.question}<Icon name="chevronDown" size={17} /></summary><p>{item.answer}</p></details>)}</div>
      </section>

      <section className="cta-banner reference-cta container-wide">
        <div><span className="reference-kicker">Ready when you are</span><h2>Move from uncertainty to a clear next step.</h2><p>Explore the configured services or sign in to follow an existing application.</p></div>
        <div className="hero-actions"><Link to="/services" className="reference-orange-button">Explore services <Icon name="arrow" size={15} /></Link><Link to="/contact" className="reference-outline-button">Talk to the team</Link></div>
      </section>
    </div>
  );
}
