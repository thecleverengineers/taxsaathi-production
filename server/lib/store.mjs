import fs from 'node:fs';
import path from 'node:path';
import mongoose from 'mongoose';

const projectRoot = path.resolve(process.cwd());
const snapshotPath = path.resolve(projectRoot, 'server/data/legacy-snapshot.json');

function clone(value) {
  return value === undefined ? undefined : JSON.parse(JSON.stringify(value));
}

function normalizeId(value) {
  if (value === undefined || value === null || value === '') return null;
  const number = Number(value);
  return Number.isFinite(number) ? number : value;
}

function matchesValue(actual, expected) {
  if (expected === undefined) return true;
  if (expected === null) return actual === null || actual === undefined;
  if (Array.isArray(expected)) return expected.map(String).includes(String(actual));
  return String(actual ?? '') === String(expected);
}

function matches(document, filter = {}) {
  return Object.entries(filter).every(([key, expected]) => {
    if (key === '$or' && Array.isArray(expected)) {
      return expected.some((branch) => matches(document, branch));
    }
    if (key === '$and' && Array.isArray(expected)) {
      return expected.every((branch) => matches(document, branch));
    }
    if (expected && typeof expected === 'object' && !Array.isArray(expected)) {
      if ('$in' in expected && !expected.$in.some((item) => matchesValue(document[key], item))) return false;
      if ('$ne' in expected && matchesValue(document[key], expected.$ne)) return false;
      if ('$regex' in expected && !new RegExp(expected.$regex, expected.$options || 'i').test(String(document[key] ?? ''))) return false;
      if ('$gt' in expected && !(Number(document[key]) > Number(expected.$gt))) return false;
      if ('$lt' in expected && !(Number(document[key]) < Number(expected.$lt))) return false;
      if ('$gte' in expected && Number(document[key]) < Number(expected.$gte)) return false;
      if ('$lte' in expected && Number(document[key]) > Number(expected.$lte)) return false;
      return Object.keys(expected).some((item) => item.startsWith('$')) ? true : matchesValue(document[key], expected);
    }
    return matchesValue(document[key], expected);
  });
}

function sortRows(rows, sort = { id: -1 }) {
  const entries = Object.entries(sort || {});
  return rows.sort((left, right) => {
    for (const [key, direction] of entries) {
      const a = left[key] ?? '';
      const b = right[key] ?? '';
      if (a === b) continue;
      const comparison = String(a).localeCompare(String(b), undefined, {
        numeric: true,
        sensitivity: 'base'
      });
      return comparison * (Number(direction) >= 0 ? 1 : -1);
    }
    return 0;
  });
}

function nextId(rows) {
  return rows.reduce((highest, row) => Math.max(highest, Number(row.id) || 0), 0) + 1;
}

export class DataStore {
  constructor({ snapshot = snapshotPath } = {}) {
    this.snapshotPath = snapshot;
    this.mode = 'snapshot';
    this.database = null;
    this.snapshot = { tables: {}, schema: {} };
  }

  async connect() {
    const mongoUri = String(process.env.MONGODB_URI || '').trim();
    if (mongoUri) {
      try {
        await mongoose.connect(mongoUri, {
          serverSelectionTimeoutMS: Number(process.env.MONGO_TIMEOUT_MS || 10000),
          maxPoolSize: Number(process.env.MONGO_MAX_POOL_SIZE || 20),
          minPoolSize: Number(process.env.MONGO_MIN_POOL_SIZE || 0),
          retryWrites: true
        });
        this.database = mongoose.connection.db;
        this.mode = 'mongodb';
        await this.ensureIndexes();
        return this;
      } catch (error) {
        const allowSnapshot = String(process.env.MONGO_ALLOW_SNAPSHOT || '').toLowerCase() === 'true';
        if (process.env.NODE_ENV === 'production' && !allowSnapshot) {
          throw new Error(`MongoDB Atlas connection failed and snapshot fallback is disabled: ${error.message}`);
        }
        console.warn(`MongoDB unavailable; using the imported snapshot instead: ${error.message}`);
        await mongoose.disconnect().catch(() => {});
      }
    } else if (process.env.NODE_ENV === 'production' && String(process.env.MONGO_ALLOW_SNAPSHOT || '').toLowerCase() !== 'true') {
      throw new Error('MONGODB_URI is required when NODE_ENV=production.');
    }

    if (fs.existsSync(this.snapshotPath)) {
      try {
        this.snapshot = JSON.parse(fs.readFileSync(this.snapshotPath, 'utf8'));
      } catch (error) {
        console.warn(`Unable to read snapshot ${this.snapshotPath}: ${error.message}`);
      }
    }
    this.mode = 'snapshot';
    return this;
  }

  async ensureIndexes() {
    if (!this.isMongo) return;
    const indexes = {
      users: [
        { key: { email: 1 }, name: 'users_email' },
        { key: { phone: 1 }, name: 'users_phone' },
        { key: { role_id: 1 }, name: 'users_role_id' }
      ],
      user_roles: [
        { key: { user_id: 1, role_id: 1 }, name: 'user_roles_user_role' },
        { key: { role_id: 1 }, name: 'user_roles_role' }
      ],
      roles: [{ key: { slug: 1 }, name: 'roles_slug' }],
      services: [
        { key: { slug: 1 }, name: 'services_slug' },
        { key: { is_active: 1, sort_order: 1 }, name: 'services_public_order' }
      ],
      service_requirements: [{ key: { service_id: 1, sort_order: 1 }, name: 'service_requirements_service' }],
      orders: [
        { key: { client_id: 1, id: -1 }, name: 'orders_client_recent' },
        { key: { assigned_user_id: 1, id: -1 }, name: 'orders_assignee_recent' },
        { key: { partner_id: 1, id: -1 }, name: 'orders_partner_recent' },
        { key: { status: 1, payment_status: 1 }, name: 'orders_workflow_status' }
      ],
      order_documents: [{ key: { order_id: 1, id: -1 }, name: 'order_documents_order_recent' }],
      payments: [{ key: { order_id: 1, status: 1, id: -1 }, name: 'payments_order_status' }],
      invoices: [{ key: { order_id: 1 }, name: 'invoices_order' }],
      notifications: [{ key: { user_id: 1, id: -1 }, name: 'notifications_user_recent' }],
      support_conversations: [{ key: { requester_user_id: 1, id: -1 }, name: 'support_requester_recent' }],
      support_messages: [{ key: { conversation_id: 1, id: 1 }, name: 'support_messages_conversation' }],
      leads: [{ key: { status: 1, created_at: -1 }, name: 'leads_status_recent' }],
      service_applications: [{ key: { status: 1, created_at: -1 }, name: 'applications_status_recent' }]
    };
    for (const [collectionName, collectionIndexes] of Object.entries(indexes)) {
      await this.database.collection(collectionName).createIndexes(collectionIndexes).catch((error) => {
        console.warn(`Unable to create indexes for ${collectionName}: ${error.message}`);
      });
    }
  }

  get isMongo() {
    return this.mode === 'mongodb' && Boolean(this.database);
  }

  tableNames() {
    if (this.isMongo) return null;
    return Object.keys(this.snapshot.tables || {});
  }

  async find(table, filter = {}, options = {}) {
    if (this.isMongo) {
      let cursor = this.database.collection(table).find(filter);
      if (options.sort) cursor = cursor.sort(options.sort);
      if (options.skip) cursor = cursor.skip(Number(options.skip));
      if (options.limit) cursor = cursor.limit(Number(options.limit));
      return cursor.toArray();
    }

    let rows = clone(this.snapshot.tables?.[table] || []).filter((row) => matches(row, filter));
    rows = sortRows(rows, options.sort || { id: -1 });
    if (options.skip) rows = rows.slice(Number(options.skip));
    if (options.limit) rows = rows.slice(0, Number(options.limit));
    return rows;
  }

  async findOne(table, filter = {}, options = {}) {
    if (this.isMongo) return this.database.collection(table).findOne(filter, options);
    return (await this.find(table, filter, { ...options, limit: 1 }))[0] || null;
  }

  async count(table, filter = {}) {
    if (this.isMongo) return this.database.collection(table).countDocuments(filter);
    return (this.snapshot.tables?.[table] || []).filter((row) => matches(row, filter)).length;
  }

  async insert(table, document) {
    const payload = { ...clone(document), _legacy_table: table };
    if (this.isMongo) {
      if (payload.id === undefined || payload.id === null) {
        const counters = this.database.collection('_taxsaathi_counters');
        const existingCounter = await counters.findOne({ _id: table });
        if (!existingCounter) {
          const latest = await this.database
            .collection(table)
            .find({ id: { $type: 'number' } })
            .sort({ id: -1 })
            .limit(1)
            .next();
          await counters.updateOne(
            { _id: table },
            { $setOnInsert: { seq: Number(latest?.id || 0), updated_at: new Date() } },
            { upsert: true }
          );
        }
        const next = await counters.findOneAndUpdate(
          { _id: table },
          { $inc: { seq: 1 }, $set: { updated_at: new Date() } },
          { returnDocument: 'after' }
        );
        payload.id = Number((next && (next.value || next).seq) || 1);
      }
      const result = await this.database.collection(table).insertOne(payload);
      return { ...payload, _id: result.insertedId };
    }

    if (payload.id === undefined || payload.id === null) {
      const rows = this.snapshot.tables?.[table] || [];
      payload.id = nextId(rows);
    }

    this.snapshot.tables[table] ||= [];
    this.snapshot.tables[table].push(payload);
    return clone(payload);
  }

  async upsert(table, filter, document) {
    if (this.isMongo) {
      const payload = { ...clone(document), _legacy_table: table };
      await this.database.collection(table).updateOne(filter, { $set: payload }, { upsert: true });
      return this.findOne(table, filter);
    }

    const rows = this.snapshot.tables[table] ||= [];
    const index = rows.findIndex((row) => matches(row, filter));
    if (index >= 0) rows[index] = { ...rows[index], ...clone(document), _legacy_table: table };
    else rows.push({ ...clone(document), _legacy_table: table });
    return clone(index >= 0 ? rows[index] : rows.at(-1));
  }

  async update(table, filter, changes) {
    if (this.isMongo) {
      await this.database.collection(table).updateOne(filter, { $set: changes });
      return this.findOne(table, filter);
    }

    const rows = this.snapshot.tables?.[table] || [];
    const index = rows.findIndex((row) => matches(row, filter));
    if (index < 0) return null;
    rows[index] = { ...rows[index], ...clone(changes) };
    return clone(rows[index]);
  }

  async updateMany(table, filter, changes) {
    if (this.isMongo) {
      return this.database.collection(table).updateMany(filter, { $set: changes });
    }

    const rows = this.snapshot.tables?.[table] || [];
    let modifiedCount = 0;
    rows.forEach((row, index) => {
      if (matches(row, filter)) {
        rows[index] = { ...row, ...clone(changes) };
        modifiedCount += 1;
      }
    });
    return { modifiedCount };
  }

  async remove(table, filter) {
    if (this.isMongo) return this.database.collection(table).deleteOne(filter);
    const rows = this.snapshot.tables?.[table] || [];
    const index = rows.findIndex((row) => matches(row, filter));
    if (index < 0) return { deletedCount: 0 };
    rows.splice(index, 1);
    return { deletedCount: 1 };
  }

  async stats() {
    if (this.isMongo) {
      const collections = await this.database.listCollections({}, { nameOnly: true }).toArray();
      const counts = {};
      for (const collection of collections) {
        if (collection.name === '_taxsaathi_counters' || collection.name.startsWith('fs.')) continue;
        counts[collection.name] = await this.database.collection(collection.name).countDocuments();
      }
      return counts;
    }
    return Object.fromEntries(
      Object.entries(this.snapshot.tables || {}).map(([table, rows]) => [table, rows.length])
    );
  }

  async close() {
    if (mongoose.connection.readyState !== 0) await mongoose.disconnect();
  }
}

export { normalizeId, clone, matches };
