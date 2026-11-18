import test from 'node:test';
import assert from 'node:assert/strict';
import { HOME_DRAFT_KEY, HOME_PUBLISHED_KEY, validateHomeBuilder, parseHomeBuilder, upsertHomeSetting } from '../lib/home-builder.mjs';

function sample() {
  return { order: ['hero', 'services'], sections: {
    hero: { enabled: true, slides: [{ id: 'one', enabled: true, title: 'Tax Saathi', mediaUrl: '/api/public/home/media/abc', primaryLink: '/services' }] },
    services: { enabled: true, title: 'Our services', limit: 6 }
  } };
}

test('valid homepage content is safe and ordered', () => {
  const content = validateHomeBuilder(sample());
  assert.equal(content.order[0], 'hero');
  assert.equal(content.order[1], 'services');
  assert.equal(content.sections.hero.slides[0].title, 'Tax Saathi');
  assert.equal(content.order.length, 10);
});

test('unsafe external protocols and missing enabled slides are rejected', () => {
  const unsafe = sample();
  unsafe.sections.hero.slides[0].primaryLink = 'javascript:alert(1)';
  assert.throws(() => validateHomeBuilder(unsafe), /HTTPS URLs/);
  const empty = sample();
  empty.sections.hero.slides[0].enabled = false;
  assert.throws(() => validateHomeBuilder(empty), /at least one enabled/);
});

test('published and draft keys remain independent', async () => {
  const rows = [];
  const store = {
    async findOne(_collection, query) { return rows.find((row) => row.setting_key === query.setting_key) || null; },
    async insert(_collection, document) { const row = { id: rows.length + 1, ...document }; rows.push(row); return row; },
    async update(_collection, query, changes) { const row = rows.find((item) => item.id === query.id); Object.assign(row, changes); return row; }
  };
  await upsertHomeSetting(store, HOME_DRAFT_KEY, validateHomeBuilder(sample()));
  assert.equal(rows.length, 1);
  assert.equal(parseHomeBuilder(rows[0].setting_value).sections.hero.slides[0].title, 'Tax Saathi');
  await upsertHomeSetting(store, HOME_PUBLISHED_KEY, validateHomeBuilder(sample()));
  assert.equal(rows.length, 2);
  await upsertHomeSetting(store, HOME_DRAFT_KEY, validateHomeBuilder(sample()));
  assert.equal(rows.length, 2);
  assert.equal(rows.find((row) => row.setting_key === HOME_PUBLISHED_KEY).setting_key, HOME_PUBLISHED_KEY);
});
