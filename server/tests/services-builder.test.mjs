import test from 'node:test';
import assert from 'node:assert/strict';
import {
  SERVICES_PAGE_DRAFT_KEY, SERVICES_PAGE_PUBLISHED_KEY,
  parseServicesPage, validateServicesPage, saveServicesPage
} from '../lib/services-builder.mjs';
import { DEFAULT_SERVICES_PAGE, normalizeServicesPage, resolveServicesHero } from '../../client/src/lib/services-page-config.js';

test('default service catalogue keeps existing hero image and service cards visible', () => {
  const current = normalizeServicesPage(null);
  assert.equal(current.sections.hero.imageUrl, '/media/taxsaathi-services-consultation.webp');
  assert.equal(current.sections.cards.showImages, true);
  assert.equal(current.sections.categories.showImages, true);
  assert.equal(current.sections.browser.pageSize, 24);
});

test('category-specific hero overrides do not mutate shared hero image', () => {
  const config = normalizeServicesPage(DEFAULT_SERVICES_PAGE);
  config.categoryOverrides['income-tax-services'] = {
    enabled: true, title: 'Income Tax Services', description: 'Expert assistance', imageUrl: ''
  };
  const categoryHero = resolveServicesHero(config, { slug: 'income-tax-services' });
  assert.equal(categoryHero.title, 'Income Tax Services');
  assert.equal(categoryHero.imageUrl, DEFAULT_SERVICES_PAGE.sections.hero.imageUrl);
  assert.equal(resolveServicesHero(config, { slug: 'gst-services' }).title, DEFAULT_SERVICES_PAGE.sections.hero.title);
});

test('valid service page configuration preserves links, appearance controls and category overrides', () => {
  const value = validateServicesPage(normalizeServicesPage(DEFAULT_SERVICES_PAGE));
  assert.equal(value.sections.hero.buttonHref, '#browse-services');
  assert.equal(value.sections.cards.showImages, true);
  assert.equal(value.sections.browser.pageSize, 24);
  assert.equal(Object.keys(value.categoryOverrides).length, 0);
});

test('javascript links and unsafe image protocols cannot be published', () => {
  const config = normalizeServicesPage(DEFAULT_SERVICES_PAGE);
  config.sections.hero.buttonHref = 'javascript:alert(1)';
  assert.throws(() => validateServicesPage(config), /HTTPS URL/);
  config.sections.hero.buttonHref = '#browse-services';
  config.categoryOverrides['income-tax-services'] = { enabled: true, imageUrl: 'data:text/html,alert(1)' };
  assert.throws(() => validateServicesPage(config), /HTTPS URL/);
});

test('draft and published Services page settings are independent', async () => {
  const rows = [];
  const store = {
    async findOne(_collection, query) { return rows.find((item) => item.setting_key === query.setting_key) || null; },
    async insert(_collection, row) { const saved = { id: rows.length + 1, ...row }; rows.push(saved); return saved; },
    async update(_collection, query, changes) {
      const saved = rows.find((item) => item.id === query.id);
      Object.assign(saved, changes);
      return saved;
    }
  };
  const value = validateServicesPage(DEFAULT_SERVICES_PAGE);
  await saveServicesPage(store, SERVICES_PAGE_PUBLISHED_KEY, value);
  const changed = validateServicesPage({
    ...value, sections: { ...value.sections, hero: { ...value.sections.hero, title: 'New seasonal title' } }
  });
  await saveServicesPage(store, SERVICES_PAGE_DRAFT_KEY, changed);
  assert.equal(rows.length, 2);
  assert.equal(parseServicesPage(rows.find((item) => item.setting_key === SERVICES_PAGE_PUBLISHED_KEY).setting_value).sections.hero.title,
    DEFAULT_SERVICES_PAGE.sections.hero.title);
  assert.equal(parseServicesPage(rows.find((item) => item.setting_key === SERVICES_PAGE_DRAFT_KEY).setting_value).sections.hero.title,
    'New seasonal title');
});
