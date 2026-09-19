import { Link } from 'react-router-dom';
import Icon from './Icons';
import { dateLabel, money, phoneHref, statusLabel, statusTone, whatsappHref } from '../lib/format';

export function PageLoader({ label = 'Loading…' }) {
  return <div className="page-loader"><span className="spinner" />{label}</div>;
}

export function PageIntro({ eyebrow, title, text, actions }) {
  return <div className="page-intro"><div><span className="eyebrow">{eyebrow}</span><h2>{title}</h2>{text && <p>{text}</p>}</div>{actions && <div className="page-intro-actions">{actions}</div>}</div>;
}

export function StatusBadge({ value }) {
  return <span className={`status-badge ${statusTone(value)}`}><i />{statusLabel(value)}</span>;
}

export function StatCard({ label, value, icon = 'activity', tone = 'indigo', note }) {
  return <div className={`stat-card tone-${tone}`}><div className="stat-card-top"><span className="stat-icon"><Icon name={icon} size={19} /></span><span className="stat-label">{label}</span></div><strong>{value}</strong>{note && <small>{note}</small>}</div>;
}

export function EmptyState({ icon = 'folder', title = 'Nothing here yet.', text = '', action }) {
  return <div className="empty-state"><span className="empty-icon"><Icon name={icon} size={24} /></span><h3>{title}</h3>{text && <p>{text}</p>}{action}</div>;
}

export function ServiceCard({ service, contact = {} }) {
  const icon = service.icon_url || service.category?.image_url;
  const callHref = phoneHref(contact.phone);
  const whatsapp = whatsappHref(contact.whatsapp || contact.phone, `${service.title || 'This service'} - I would like to know more.`);
  return <article className="service-card">
    <Link to={`/services/${service.slug}`} className="service-card-main">
      <div className="service-card-icon">{icon ? <img src={icon} alt="" /> : <span>{String(service.title || 'TS').split(/\s+/).slice(0, 2).map((part) => part[0]).join('')}</span>}</div>
      <div className="service-card-meta"><span>{service.category?.title || service.service_category || 'Tax services'}</span>{service.is_featured && <b>Featured</b>}</div>
      <h3>{service.title}</h3>
      <p>{service.excerpt || 'Professional support with a clear digital workflow.'}</p>
    </Link>
    <div className="service-card-bottom">
      <div className="service-card-price-row"><strong>{money(service.filing_fee)}</strong><Link className="service-card-view" to={`/services/${service.slug}`}>View service <Icon name="arrow" size={15} /></Link></div>
      <div className="service-card-actions">
        <a className="service-card-action call" href={callHref || '/contact'}><Icon name="phone" size={13} /> Call us</a>
        <a className="service-card-action whatsapp" href={whatsapp || '/contact'} target={whatsapp ? '_blank' : undefined} rel={whatsapp ? 'noreferrer' : undefined}><Icon name="chat" size={13} /> WhatsApp</a>
        <Link className="service-card-action apply" to={`/services/${service.slug}/apply`}>Apply for this Service <Icon name="arrow" size={13} /></Link>
      </div>
    </div>
  </article>;
}

export function OrderRow({ order, href = `/orders/${order.id}` }) {
  return <Link to={href} className="order-row"><div className="order-main"><span className="order-number">{order.order_no || `#${order.id}`}</span><strong>{order.service?.title || 'Tax service'}</strong><small>{order.client?.name || `Client #${order.client_id}`} · {dateLabel(order.created_at)}</small></div><div className="order-side"><strong>{money(order.payable_amount || order.fee_amount)}</strong><StatusBadge value={order.status} /></div><Icon name="chevronRight" size={17} className="row-chevron" /></Link>;
}

export function Table({ columns, rows, empty = 'No records found.' }) {
  return <div className="table-scroll"><table className="data-table"><thead><tr>{columns.map((column) => <th key={column.key}>{column.label}</th>)}</tr></thead><tbody>{rows.length ? rows.map((row, index) => <tr key={row.id || index}>{columns.map((column) => <td key={column.key}>{column.render ? column.render(row) : row[column.key]}</td>)}</tr>) : <tr><td colSpan={columns.length}><EmptyState title={empty} /></td></tr>}</tbody></table></div>;
}
