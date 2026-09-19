const API_BASE = import.meta.env.VITE_API_URL || '';

export function assetUrl(value) {
  const path = String(value || '');
  if (!path) return '';
  if (/^(https?:)?\/\//i.test(path) || path.startsWith('data:')) return path;
  if (path.startsWith('/legacy-assets/')) return `${API_BASE}${path}`;
  if (path.startsWith('/uploads/')) return `${API_BASE}/legacy-assets${path}`;
  return path;
}

export async function api(path, options = {}) {
  const token = localStorage.getItem('taxsaathi_token');
  const headers = new Headers(options.headers || {});
  if (!headers.has('Accept')) headers.set('Accept', 'application/json');
  if (token) headers.set('Authorization', `Bearer ${token}`);
  if (options.body && !(options.body instanceof FormData) && !headers.has('Content-Type')) headers.set('Content-Type', 'application/json');
  const response = await fetch(`${API_BASE}/api${path}`, { ...options, headers, credentials: 'include', body: options.body && typeof options.body !== 'string' && !(options.body instanceof FormData) ? JSON.stringify(options.body) : options.body });
  const contentType = response.headers.get('content-type') || '';
  const payload = contentType.includes('application/json') ? await response.json() : await response.text();
  if (!response.ok || (payload && payload.ok === false)) throw new Error(payload?.message || 'Request failed.');
  return payload;
}

export async function downloadBlob(path) {
  const token = localStorage.getItem('taxsaathi_token');
  const response = await fetch(`${API_BASE}/api${path}`, { headers: token ? { Authorization: `Bearer ${token}` } : {}, credentials: 'include' });
  if (!response.ok) throw new Error('Download failed.');
  return response.blob();
}

export { API_BASE };
