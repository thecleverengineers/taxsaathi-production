// Content is stored as JSON in website_settings: draft and published are separate.
export const HOME_DRAFT_KEY = 'home_builder_draft_v1';
export const HOME_PUBLISHED_KEY = 'home_builder_published_v1';
export const HOME_SECTIONS = ['hero','trust','video','services','categories','editorial','workflow','testimonials','faq','cta'];
const MAX_BYTES = 100_000;
function sanitize(value, depth = 0, key = '') {
  if (depth > 7) throw new Error('Homepage configuration is too deeply nested.');
  if (value == null) return null;
  if (typeof value === 'boolean') return value;
  if (typeof value === 'number') return Number.isFinite(value) ? Math.min(Math.max(value, 0), 100000) : 0;
  if (typeof value === 'string') {
    const clean = value.slice(0, 5000).trim();
    if (/(?:url|link)$/i.test(key) && clean && !/^(?:https?:\/\/|\/(?!\/))/i.test(clean))
      throw new Error('Media and button links must be HTTPS URLs or site-relative paths.');
    return clean;
  }
  if (Array.isArray(value)) {
    if (value.length > 30) throw new Error('A section cannot contain more than 30 entries.');
    return value.map((item) => sanitize(item, depth + 1));
  }
  if (typeof value !== 'object') throw new Error('Unsupported homepage content.');
  const output = {};
  for (const [name, item] of Object.entries(value)) {
    if (!/^[a-zA-Z][a-zA-Z0-9_]*$/.test(name) || ['constructor','prototype','__proto__'].includes(name)) continue;
    output[name] = sanitize(item, depth + 1, name);
  }
  return output;
}
export function parseHomeBuilder(value) {
  if (!value) return null;
  try { const data = JSON.parse(value); return data && typeof data === 'object' && !Array.isArray(data) ? data : null; }
  catch { return null; }
}
export function validateHomeBuilder(value) {
  if (!value || typeof value !== 'object' || Array.isArray(value)) throw new Error('Invalid homepage configuration.');
  if (!value.sections || typeof value.sections !== 'object' || Array.isArray(value.sections)) throw new Error('Sections are required.');
  const sections = {};
  for (const id of HOME_SECTIONS) {
    if (value.sections[id] == null) continue;
    const part = value.sections[id];
    if (!part || typeof part !== 'object' || Array.isArray(part)) throw new Error('Invalid homepage section: ' + id);
    sections[id] = sanitize(part, 0);
  }
  const order = Array.isArray(value.order)
    ? [...new Set(value.order.filter((id) => HOME_SECTIONS.includes(id)))]
    : HOME_SECTIONS;
  const clean = { order: [...order, ...HOME_SECTIONS.filter((id) => !order.includes(id))], sections };
  if (Buffer.byteLength(JSON.stringify(clean), 'utf8') > MAX_BYTES) throw new Error('Homepage content is too large.');
  if (sections.hero && (!Array.isArray(sections.hero.slides) || sections.hero.slides.filter((x) => x?.enabled !== false).length === 0))
    throw new Error('The hero must contain at least one enabled slide.');
  return clean;
}
export async function upsertHomeSetting(store, key, config) {
  const value = JSON.stringify(config);
  const now = new Date().toISOString();
  const existing = await store.findOne('website_settings', { setting_key: key });
  if (existing) return store.update('website_settings', { id: existing.id }, { setting_value: value, updated_at: now });
  return store.insert('website_settings', { setting_key: key, setting_value: value, created_at: now, updated_at: now });
}
