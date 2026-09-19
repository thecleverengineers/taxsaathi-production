const buckets = new Map();

function clientKey(request) {
  const forwarded = String(request.headers['x-forwarded-for'] || '').split(',')[0].trim();
  return forwarded || request.ip || request.socket.remoteAddress || 'unknown';
}

export function rateLimit({ windowMs = 60_000, max = 60, key = clientKey, message = 'Too many requests. Please try again later.' } = {}) {
  return (request, response, next) => {
    const now = Date.now();
    const bucketKey = `${key(request)}:${request.path}`;
    const current = buckets.get(bucketKey);
    if (!current || current.resetAt <= now) {
      buckets.set(bucketKey, { count: 1, resetAt: now + windowMs });
      response.setHeader('RateLimit-Limit', String(max));
      response.setHeader('RateLimit-Remaining', String(Math.max(0, max - 1)));
      return next();
    }
    current.count += 1;
    response.setHeader('RateLimit-Limit', String(max));
    response.setHeader('RateLimit-Remaining', String(Math.max(0, max - current.count)));
    response.setHeader('RateLimit-Reset', String(Math.ceil((current.resetAt - now) / 1000)));
    if (current.count > max) return response.status(429).json({ ok: false, message });
    return next();
  };
}

export function clearRateLimitState() {
  buckets.clear();
}
