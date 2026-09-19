import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { api, assetUrl } from '../lib/api';
import { useAuth } from '../components/AuthContext';
import Icon from '../components/Icons';
import { PageLoader, ServiceCard } from '../components/Ui';
import { money, phoneHref, whatsappHref } from '../lib/format';

export default function ServiceDetailPage() {
  const { slug } = useParams();
  const [searchParams] = useSearchParams();
  const { user } = useAuth();
  const navigate = useNavigate();
  const [data, setData] = useState(null);
  const [site, setSite] = useState({});
  const [form, setForm] = useState({ full_name: '', email: '', mobile: '', state_name: '' });
  const [message, setMessage] = useState('');
  const serviceIdentifier = slug || searchParams.get('slug') || searchParams.get('service') || '';
  useEffect(() => { api(`/public/services/${serviceIdentifier}`).then(setData).catch(() => setData({ service: null })); }, [serviceIdentifier]);
  useEffect(() => { api('/meta').then((payload) => setSite(payload.settings || {})).catch(() => {}); }, []);
  if (!data) return <PageLoader label="Loading service…" />;
  if (!data.service) return <div className="empty-state large"><h3>Service not found.</h3><Link className="button dark" to="/services">Back to services</Link></div>;
  const service = data.service;
  async function submit(event) {
    event.preventDefault(); setMessage('');
    try { await api('/public/applications', { method: 'POST', body: { ...form, service: service.slug } }); setMessage('Application submitted. Our team will contact you shortly.'); setForm({ full_name: '', email: '', mobile: '', state_name: '' }); } catch (error) { setMessage(error.message); }
  }
  const callHref = phoneHref(site.site_phone);
  const whatsapp = whatsappHref(site.site_whatsapp || site.site_phone, `${service.title} - I would like to know more.`);
  return <>
    <section className="detail-hero"><div className="container-wide detail-hero-grid"><div><Link to="/services" className="back-link"><Icon name="chevronLeft" size={15} /> All services</Link><span className="eyebrow">{service.category?.title || 'Tax & compliance'}</span><h1>{service.title}</h1><p>{service.excerpt || 'Professional filing and compliance support with an organized digital experience.'}</p><div className="detail-price"><strong>{money(service.filing_fee)}</strong><span>Starting fee · {service.turnaround_days || 7} working days</span></div><div className="hero-actions service-detail-actions"><a href={callHref || '/contact'} className="button detail-call"><Icon name="phone" size={15} /> Call us</a><a href={whatsapp || '/contact'} className="button detail-whatsapp" target={whatsapp ? '_blank' : undefined} rel={whatsapp ? 'noreferrer' : undefined}><Icon name="chat" size={15} /> WhatsApp</a><Link to={`/services/${service.slug}/apply`} className="button primary">Apply for this Service <Icon name="arrow" size={16} /></Link>{user && <button className="button ghost" onClick={() => navigate('/client/orders')}>View my orders</button>}</div></div><div className="detail-art">{service.banners?.[0]?.image_url ? <img src={assetUrl(service.banners[0].image_url)} alt="" /> : service.icon_url ? <img src={assetUrl(service.icon_url)} alt="" /> : <div className="detail-art-placeholder"><Icon name="briefcase" size={48} /></div>}<div className="detail-art-overlay"><Icon name="shield" size={18} /><span>Structured, trackable, professional.</span></div></div></div></section>
    <section className="section container-wide detail-body"><div className="detail-main"><article className="content-card"><span className="eyebrow">About this service</span><div className="rich-text" dangerouslySetInnerHTML={{ __html: service.description || '<p>Our team helps you complete this service with clear guidance, document support, and visible progress updates.</p>' }} /></article>{service.types?.length > 0 && <article className="content-card"><span className="eyebrow">What is included</span><div className="detail-type-grid">{service.types.map((item) => <div key={item.id}><span className="mini-icon"><Icon name="check" size={15} /></span><div><strong>{item.title}</strong><p>{item.description}</p></div></div>)}</div></article>}{service.requirements?.length > 0 && <article className="content-card"><span className="eyebrow">Documents and information</span><div className="requirements-list">{service.requirements.map((item) => <div key={item.id}><span><Icon name="file" size={16} />{item.label}</span>{item.is_required ? <b>Required</b> : <small>Optional</small>}</div>)}</div></article>}</div><aside className="detail-side" id="apply"><form className="apply-card" onSubmit={submit}><span className="eyebrow">Start an application</span><h2>Let’s get this moving.</h2><p>Share your contact details and the team will guide the next step.</p>{message && <div className={`form-message ${message.includes('submitted') ? 'success' : 'danger'}`}>{message}</div>}<label>Full name<input required value={form.full_name} onChange={(event) => setForm({ ...form, full_name: event.target.value })} /></label><label>Email<input type="email" required value={form.email} onChange={(event) => setForm({ ...form, email: event.target.value })} /></label><label>Mobile<input required value={form.mobile} onChange={(event) => setForm({ ...form, mobile: event.target.value })} /></label><label>State<input value={form.state_name} onChange={(event) => setForm({ ...form, state_name: event.target.value })} /></label><button className="button primary full" type="submit">Submit application <Icon name="arrow" size={16} /></button><small className="form-footnote">By continuing, you agree to be contacted about this service.</small></form>{service.reviews?.length > 0 && <div className="side-review"><div className="rating">★★★★★</div><p>“{service.reviews[0].quote}”</p><strong>{service.reviews[0].name}</strong><small>{service.reviews[0].designation}</small></div>}</aside></section>
  </>;
}
