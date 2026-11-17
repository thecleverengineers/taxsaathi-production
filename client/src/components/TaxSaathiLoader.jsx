/**
 * Shared branded loading state for authentication and route-level data requests.
 * The uploaded logo is static; only the surrounding blue-and-gold ring rotates.
 */
export default function TaxSaathiLoader({
  label = 'Loading your workspace…',
  variant = 'page',
  size = 'default'
}) {
  const mode = variant === 'screen' ? 'screen' : 'page';

  return (
    <div
      className={`ts-loader ts-loader--${mode} ts-loader--${size}`}
      role="status"
      aria-live="polite"
      aria-busy="true"
    >
      <span className="ts-loader__emblem" aria-hidden="true">
        <svg className="ts-loader__ring" viewBox="0 0 180 180" focusable="false">
          <defs>
            <linearGradient id="ts-gold-ring" x1="0" y1="0" x2="1" y2="1">
              <stop offset="0%" stopColor="#fae49b" />
              <stop offset="48%" stopColor="#c29433" />
              <stop offset="100%" stopColor="#f6d46a" />
            </linearGradient>
          </defs>
          <circle cx="90" cy="90" r="80" fill="none" stroke="#e0e7f0" strokeWidth="7" />
          <circle cx="90" cy="90" r="80" fill="none" stroke="#173872"
            strokeWidth="8" strokeLinecap="round" strokeDasharray="216 503" />
          <circle cx="90" cy="90" r="80" fill="none" stroke="url(#ts-gold-ring)"
            strokeWidth="8" strokeLinecap="round" strokeDasharray="116 503" strokeDashoffset="-232" />
        </svg>
        <img
          className="ts-loader__logo"
          src="/taxsaathi-logo.png"
          alt=""
          width="133"
          height="133"
          decoding="async"
        />
      </span>
      <span className="ts-loader__message">{label}</span>
    </div>
  );
}
