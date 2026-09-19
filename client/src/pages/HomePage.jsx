import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api, assetUrl } from '../lib/api';
import Icon from '../components/Icons';
import { PageLoader, ServiceCard } from '../components/Ui';

const fallbackSlides = [
  { eyebrow: 'Income Tax • GST • Compliance', title: 'File smarter. Track everything. Deliver faster.', text: 'TaxSaathi brings service discovery, document collection, payment approvals, progress visibility, and final delivery into one structured experience.', image: '/legacy-assets/uploads/service-banners/banner_20260519070346_7c66d9c8b29e.jpg' },
  { eyebrow: 'Client experience', title: 'A digital tax experience built around clarity.', text: 'From choosing a service to receiving completed files, every stage is designed to feel simple, visible, and professional.', image: '/legacy-assets/uploads/service-banners/banner_20260519100545_3a39b21b4f20.jpg' },
  { eyebrow: 'Integrated workflow', title: 'Built for trust, speed, and scale.', text: 'Keep services, orders, uploads, reviews, and operational updates moving through one reliable workspace.', image: '/legacy-assets/uploads/service-banners/banner_20260519134532_535851726f9f.png' }
];

export default function HomePage() {
  const [data, setData] = useState(null);
  const [slide, setSlide] = useState(0);
  useEffect(() => { api('/public/home').then(setData).catch(() => setData({ services: [], featured: [], categories: [], faqs: [], testimonials: [], settings: {} })); }, []);
  useEffect(() => { const timer = setInterval(() => setSlide((current) => (current + 1) % fallbackSlides.length), 6000); return () => clearInterval(timer); }, []);
  if (!data) return <PageLoader label="Loading TaxSaathi…" />;
  const settings = data.settings || {};
  const heroSlides = fallbackSlides.map((item, index) => ({ ...item, title: settings[`hero_slide_${index + 1}_title`] || item.title, text: settings[`hero_slide_${index + 1}_text`] || item.text, eyebrow: settings[`hero_slide_${index + 1}_eyebrow`] || item.eyebrow }));
  const current = heroSlides[slide];
  return <>
    <section className="home-hero container-wide">
      <div className="hero-slide" style={{ backgroundImage: `linear-gradient(90deg, rgba(5,13,30,.94) 0%, rgba(5,13,30,.76) 40%, rgba(5,13,30,.42) 100%), url(${assetUrl(current.image)})` }}>
        <div className="hero-copy"><span className="hero-eyebrow"><Icon name="sparkles" size={13} />{current.eyebrow}</span><h1>{current.title}</h1><p>{current.text}</p><div className="hero-actions"><Link to="/services" className="button primary">Explore services <Icon name="arrow" size={16} /></Link><Link to="/tax-calculators" className="button ghost">Use calculators</Link></div><div className="hero-chips"><span><Icon name="userCircle" size={14} /> Client-centric</span><span><Icon name="zap" size={14} /> Efficient workflow</span><span><Icon name="trending" size={14} /> Scalable model</span></div></div>
        <div className="hero-proof"><div className="hero-proof-card"><span className="eyebrow">Why clients choose TaxSaathi</span><h3>A refined service experience built for confidence.</h3><div className="proof-grid"><div><Icon name="shield" size={18} /><strong>Protected</strong><small>Document-aware workflow</small></div><div><Icon name="arrow" size={18} /><strong>Visible</strong><small>Track every stage</small></div><div><Icon name="sparkles" size={18} /><strong>Polished</strong><small>Professional delivery</small></div></div></div></div>
        <div className="hero-dots">{heroSlides.map((item, index) => <button key={item.eyebrow} className={slide === index ? 'active' : ''} onClick={() => setSlide(index)} aria-label={`Slide ${index + 1}`} />)}</div>
      </div>
    </section>

    <section className="trust-strip"><div className="container-wide trust-grid"><div><strong>One structured place</strong><span>for service, payment, documents, and delivery.</span></div><div><Icon name="check" size={18} /><span>Clear workflow</span></div><div><Icon name="shield" size={18} /><span>Secure records</span></div><div><Icon name="headphones" size={18} /><span>Human support</span></div></div></section>

    <section className="section container-wide"><div className="section-heading"><div><span className="eyebrow">Popular services</span><h2>Professional support for every compliance stage.</h2><p>Choose from the services already configured in your TaxSaathi workspace.</p></div><Link to="/services" className="text-link">View all services <Icon name="arrow" size={15} /></Link></div><div className="service-grid">{(data.featured?.length ? data.featured : data.services).slice(0, 6).map((service) => <ServiceCard service={service} contact={{ phone: settings.site_phone, whatsapp: settings.site_whatsapp }} key={service.id} />)}</div></section>

    <section className="section light-section"><div className="container-wide"><div className="section-heading center"><div><span className="eyebrow">Service categories</span><h2>Find the right starting point.</h2><p>Browse the categories carried over from the original TaxSaathi site.</p></div></div><div className="category-grid">{(data.categories || []).slice(0, 8).map((category) => <Link to={`/services?category=${category.slug}`} className="category-card" key={category.id}>{category.image_url ? <img src={assetUrl(category.image_url)} alt="" /> : <span className="category-letter">{category.title.slice(0, 1)}</span>}<span><strong>{category.title}</strong><small>Explore services <Icon name="arrow" size={13} /></small></span></Link>)}</div></div></section>

    <section className="section container-wide process-section"><div className="process-visual"><div className="process-orbit orbit-a" /><div className="process-orbit orbit-b" /><div className="process-center"><Icon name="shield" size={32} /><strong>TaxSaathi</strong><span>Clarity at every step</span></div><div className="process-node node-a"><Icon name="search" size={18} /><span>Choose</span></div><div className="process-node node-b"><Icon name="upload" size={18} /><span>Share</span></div><div className="process-node node-c"><Icon name="activity" size={18} /><span>Track</span></div><div className="process-node node-d"><Icon name="file" size={18} /><span>Receive</span></div></div><div className="process-copy"><span className="eyebrow">A smoother process</span><h2>Everything important stays visible.</h2><p>Tax Saathi combines service discovery, orders, document collection, payment confirmation, progress updates, and completed file delivery into one connected workflow.</p><div className="step-list"><div><b>01</b><span><strong>Choose a service</strong><small>See pricing, turnaround, benefits, and requirements.</small></span></div><div><b>02</b><span><strong>Submit details securely</strong><small>Keep client information and uploads attached to the right order.</small></span></div><div><b>03</b><span><strong>Track until delivery</strong><small>Follow approvals, work in progress, and completed outputs.</small></span></div></div><Link to="/auth" className="button dark">Start your workspace <Icon name="arrow" size={16} /></Link></div></section>

    <section className="section light-section"><div className="container-wide"><div className="section-heading"><div><span className="eyebrow">Client voices</span><h2>Designed to feel organized from the first click.</h2></div></div><div className="testimonial-grid">{(data.testimonials || []).map((item) => <article className="testimonial-card" key={item.id}><div className="rating">★★★★★</div><p>“{item.quote}”</p><div className="testimonial-person"><span>{item.name?.slice(0, 1) || 'C'}</span><div><strong>{item.name}</strong><small>{[item.designation, item.company].filter(Boolean).join(' · ')}</small></div></div></article>)}</div></div></section>

    <section className="section container-wide faq-section"><div className="section-heading center"><div><span className="eyebrow">Questions</span><h2>Clear answers before you begin.</h2></div></div><div className="faq-grid">{(data.faqs || []).map((item) => <details key={item.id}><summary>{item.question}<Icon name="chevronDown" size={17} /></summary><p>{item.answer}</p></details>)}</div></section>

    <section className="cta-banner container-wide"><div><span className="eyebrow">Ready when you are</span><h2>Move from uncertainty to a clear next step.</h2><p>Explore the configured services or sign in to follow an existing application.</p></div><div className="hero-actions"><Link to="/services" className="button light">Explore services <Icon name="arrow" size={16} /></Link><Link to="/contact" className="button outline-light">Talk to the team</Link></div></section>
  </>;
}
