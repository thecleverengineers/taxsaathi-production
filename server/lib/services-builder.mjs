import { upsertHomeSetting } from './home-builder.mjs';

export const SERVICES_PAGE_DRAFT_KEY = 'services_page_draft_v1';
export const SERVICES_PAGE_PUBLISHED_KEY = 'services_page_published_v1';

const FIELDS = {
  hero: ['enabled','eyebrow','title','description','buttonLabel','buttonHref','imageUrl','imageAlt','imagePosition'],
  browser: ['enabled','title','categoryFallbackTitle','searchPlaceholder','filterAllLabel','loadingLabel','unavailableTitle','retryLabel','categoryEmptyTitle','resultsSuffix','resultEmptyTitle','resultEmptyDescription','previousLabel','nextLabel','pageSize','showSearch','showCategoryFilter','showResultsCount'],
  categories: ['enabled','showCount','showImages','sortMode','customOrder','displayMode'],
  cards: ['enabled','showImages','showCategory','showPrice','showFeatured','showContactButtons','showApplyButton','viewLabel','callLabel','whatsappLabel','applyLabel','featuredLabel','emptyFeeLabel'],
  footer: ['enabled','eyebrow','title','description','buttonLabel','buttonHref']
};

const TEXT_LIMIT = 800;
const SAFE_LINK = /^(?:https?:\/\/[^\\s]+|\/(?!\/)[^\\s]*|#(?:[a-z\\d_-]+)?)$/i;
const SAFE_MEDIA = /^(?:https:\/\/|\/(?!\/))/i;

function simpleString(value, limit = TEXT_LIMIT) {
  return String(value ?? '').trim().slice(0, limit);
}
function checkUrl(value, field, media = false) {
  const str = simpleString(value, 1500);
  if (str && !(media ? SAFE_MEDIA : SAFE_LINK).test(str)) {
    throw new Error(field + ' must be an HTTPS URL or relative site path.');
  }
  return str;
}
function validateSection(name, value) {
  if (!value || typeof value !== 'object' || Array.isArray(value)) throw new Error('Invalid ' + name + ' section.');
  const output = {};
  for (const field of FIELDS[name]) {
    if (!Object.hasOwn(value, field)) continue;
    const input = value[field];
    if (field === 'customOrder') {
      if (!Array.isArray(input) || input.length > 200) throw new Error('Invalid category order.');
      output.customOrder = [...new Set(input.map((x) => simpleString(x, 90)).filter((x) => /^[a-z0-9_-]+$/i.test(x)))];
    } else if (field === 'pageSize') {
      output.pageSize = Math.min(100, Math.max(1, Math.floor(Number(input) || 24)));
    } else if (/^(enabled|show\w+)$/.test(field) || ['showSearch','showCategoryFilter'].includes(field)) {
      output[field] = input === true;
    } else if (field === 'sortMode') {
      if (!['current','database','alphabetical','custom'].includes(input)) throw new Error('Invalid category sort order.');
      output[field] = input;
    } else if (field === 'displayMode') {
      if (!['grid','list'].includes(input)) throw new Error('Invalid category display layout.');
      output[field] = input;
    } else if (/^(?:.*Url|.*Href|buttonHref)$/.test(field)) {
      output[field] = checkUrl(input, field, /(?:imageUrl|imageHref)$/i.test(field));
    } else {
      output[field] = simpleString(input, field === 'description' ? 3000 : TEXT_LIMIT);
    }
  }
  return output;
}
export function parseServicesPage(value) {
  if (!value) return null;
  try {
    const parsed = JSON.parse(value);
    return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : null;
  } catch { return null; }
}
export function validateServicesPage(value) {
  if (!value || typeof value !== 'object' || Array.isArray(value)) throw new Error('Services page content is required.');
  const output = { sections: {}, categoryOverrides: {} };
  if (!value.sections || typeof value.sections !== 'object' || Array.isArray(value.sections)) throw new Error('Services page sections are required.');
  for (const section of Object.keys(FIELDS)) {
    if (value.sections[section]) output.sections[section] = validateSection(section, value.sections[section]);
  }
  if (value.categoryOverrides != null) {
    if (!value.categoryOverrides || typeof value.categoryOverrides !== 'object' || Array.isArray(value.categoryOverrides)) throw new Error('Invalid category customisation.');
    const items = Object.entries(value.categoryOverrides);
    if (items.length > 200) throw new Error('Too many category customisations.');
    for (const [slug, item] of items) {
      if (!/^[a-z0-9_-]{1,100}$/i.test(slug) || !item || typeof item !== 'object' || Array.isArray(item)) throw new Error('Invalid category customisation.');
      const permitted = ['enabled','eyebrow','title','description','imageUrl','imagePosition','browserTitle'];
      const cleaned = {};
      for (const field of permitted) {
        if (!(field in item)) continue;
        if (field === 'enabled') cleaned[field] = item[field] === true;
        else if (field === 'imageUrl') cleaned[field] = checkUrl(item[field], 'category imageUrl', true);
        else cleaned[field] = simpleString(item[field], field === 'description' ? 3000 : 800);
      }
      output.categoryOverrides[slug] = cleaned;
    }
  }
  const serialized = JSON.stringify(output);
  if (Buffer.byteLength(serialized, 'utf8') > 90000) throw new Error('Services page content is too large.');
  return output;
}
export async function saveServicesPage(store, key, content) {
  return upsertHomeSetting(store, key, content);
}
