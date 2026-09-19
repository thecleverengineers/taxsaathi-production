import fs from 'node:fs/promises';
import path from 'node:path';
import dotenv from 'dotenv';
import { DataStore } from '../lib/store.mjs';
import { saveUpload } from '../lib/file-store.mjs';

dotenv.config({ path: path.resolve(process.cwd(), '.env') });

const projectRoot = path.resolve(process.cwd());
const legacyRoot = path.resolve(projectRoot, 'legacy/taxsaathi');
const references = [
  ['order_documents', 'stored_name', 'documents'],
  ['payments', 'proof_file', 'payment'],
  ['orders', 'payment_proof', 'payment'],
  ['partner_orders', 'payment_proof', 'payment'],
  ['partner_order_documents', 'file_path', 'documents'],
  ['partner_documents', 'file_path', 'documents']
];

function relativeCandidates(reference) {
  const raw = String(reference || '').trim().replace(/^\/+/, '');
  if (!raw || raw.startsWith('gridfs:') || /^[a-f\d]{24}$/i.test(raw)) return [];
  if (raw.startsWith('storage/')) return [path.resolve(legacyRoot, raw.slice('storage/'.length))];
  if (raw.startsWith('uploads/')) return [path.resolve(legacyRoot, 'public', raw)];
  return [
    path.resolve(legacyRoot, 'storage', raw),
    path.resolve(legacyRoot, 'public', 'uploads', raw),
    path.resolve(legacyRoot, 'public', raw)
  ];
}

function safeWithinLegacy(filePath) {
  return filePath === legacyRoot || filePath.startsWith(`${legacyRoot}${path.sep}`);
}

async function locate(reference) {
  for (const candidate of relativeCandidates(reference)) {
    if (!safeWithinLegacy(candidate)) continue;
    try {
      const stat = await fs.stat(candidate);
      if (stat.isFile()) return candidate;
    } catch {
      // Try the next legacy path convention.
    }
  }
  return null;
}

const store = await new DataStore().connect();
if (!store.isMongo) {
  await store.close();
  throw new Error('MongoDB Atlas must be connected before migrating files to GridFS.');
}

const migrated = new Map();
let updatedRows = 0;
let missing = 0;

async function migrateReference(reference, folder, metadata) {
  const raw = String(reference || '').trim();
  if (!raw || raw.startsWith('gridfs:') || migrated.has(raw)) return migrated.get(raw) || null;
  const localPath = await locate(raw);
  if (!localPath) {
    missing += 1;
    return null;
  }
  const buffer = await fs.readFile(localPath);
  const stored = await saveUpload(store, {
    buffer,
    originalname: path.basename(localPath),
    mimetype: 'application/octet-stream',
    size: buffer.length
  }, { folder, metadata });
  migrated.set(raw, stored);
  return stored;
}

for (const [table, field, folder] of references) {
  const rows = await store.find(table, {}, { sort: { id: 1 } });
  for (const row of rows) {
    const stored = await migrateReference(row[field], folder, { source_table: table, source_id: row.id });
    if (!stored) continue;
    await store.update(table, { id: row.id }, {
      [field]: stored.stored_name,
      file_id: stored.file_id,
      storage: stored.storage,
      migrated_at: new Date().toISOString()
    });
    updatedRows += 1;
  }
}

const setting = await store.findOne('website_settings', { setting_key: 'payment_qr_code' });
if (setting?.setting_value && !String(setting.setting_value).includes('/api/files/public/')) {
  const stored = await migrateReference(setting.setting_value, 'payment', { kind: 'payment-qr', public: true, source_table: 'website_settings', source_id: setting.id });
  if (stored?.file_id) {
    await store.update('website_settings', { id: setting.id }, { setting_value: `/api/files/public/${stored.file_id}`, updated_at: new Date().toISOString() });
    updatedRows += 1;
  }
}

console.log(JSON.stringify({ ok: true, migrated_files: migrated.size, updated_rows: updatedRows, missing_files: missing }));
await store.close();
