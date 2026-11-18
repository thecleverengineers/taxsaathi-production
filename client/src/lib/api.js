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
  const { timeoutMs = 90000, ...requestOptions } = options;
  const headers = new Headers(requestOptions.headers || {});
  if (!headers.has('Accept')) headers.set('Accept', 'application/json');
  if (token) headers.set('Authorization', `Bearer ${token}`);
  if (requestOptions.body && !(requestOptions.body instanceof FormData) && !headers.has('Content-Type')) headers.set('Content-Type', 'application/json');

  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), timeoutMs);
  let abortListener;
  if (requestOptions.signal) {
    abortListener = () => controller.abort();
    if (requestOptions.signal.aborted) controller.abort();
    else requestOptions.signal.addEventListener('abort', abortListener, { once: true });
  }
  try {
    const response = await fetch(`${API_BASE}/api${path}`, {
      ...requestOptions,
      headers,
      signal: controller.signal,
      credentials: 'include',
      body: requestOptions.body && typeof requestOptions.body !== 'string' && !(requestOptions.body instanceof FormData)
        ? JSON.stringify(requestOptions.body)
        : requestOptions.body
    });
    const contentType = response.headers.get('content-type') || '';
    const payload = contentType.includes('application/json') ? await response.json() : await response.text();
    if (!response.ok || (payload && payload.ok === false)) {
      const error = new Error(
        (typeof payload === 'object' && payload?.message)
          || `Server returned HTTP ${response.status}. Please retry or contact support.`
      );
      error.status = response.status;
      throw error;
    }
    if (typeof payload === 'string' && payload.trim().startsWith('<!DOCTYPE html')) {
      throw new Error('The API returned a website page instead of data. Please contact support to check the server routing.');
    }
    return payload;
  } catch (error) {
    if (error?.name === 'AbortError' && !requestOptions.signal?.aborted) {
      throw new Error('The server took too long to respond. Your form is still here; check your Orders before retrying.');
    }
    if (error instanceof TypeError) {
      throw new Error('Unable to reach the Tax Saathi server. Check your connection and retry. If this continues, contact support.');
    }
    throw error;
  } finally {
    clearTimeout(timer);
    if (requestOptions.signal && abortListener) requestOptions.signal.removeEventListener('abort', abortListener);
  }
}

export async function downloadBlob(path) {
  const token = localStorage.getItem('taxsaathi_token');
  const response = await fetch(`${API_BASE}/api${path}`, { headers: token ? { Authorization: `Bearer ${token}` } : {}, credentials: 'include' });
  if (!response.ok) throw new Error('Download failed.');
  return response.blob();
}

export { API_BASE };
