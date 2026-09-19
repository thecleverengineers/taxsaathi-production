import fs from 'node:fs/promises';
import path from 'node:path';
import process from 'node:process';
import mongoose from 'mongoose';
import dotenv from 'dotenv';

dotenv.config({ path: path.resolve(process.cwd(), '.env') });

const projectRoot = path.resolve(process.cwd());
const defaultSqlPath = path.resolve(
  projectRoot,
  process.env.SQL_DUMP || 'upload/taxsathi2.sql'
);
const snapshotPath = path.resolve(projectRoot, 'server/data/legacy-snapshot.json');
const reportPath = path.resolve(projectRoot, 'server/data/migration-report.json');

function splitTopLevel(input, separator = ',') {
  const parts = [];
  let start = 0;
  let depth = 0;
  let quoted = false;
  let escaped = false;

  for (let index = 0; index < input.length; index += 1) {
    const character = input[index];

    if (quoted) {
      if (escaped) {
        escaped = false;
      } else if (character === '\\') {
        escaped = true;
      } else if (character === "'") {
        if (input[index + 1] === "'") {
          index += 1;
        } else {
          quoted = false;
        }
      }
      continue;
    }

    if (character === "'") {
      quoted = true;
    } else if (character === '(') {
      depth += 1;
    } else if (character === ')') {
      depth -= 1;
    } else if (character === separator && depth === 0) {
      parts.push(input.slice(start, index).trim());
      start = index + 1;
    }
  }

  parts.push(input.slice(start).trim());
  return parts.filter((part) => part !== '');
}

function splitStatements(input) {
  const statements = [];
  let start = 0;
  let quoted = false;
  let escaped = false;

  for (let index = 0; index < input.length; index += 1) {
    const character = input[index];

    if (quoted) {
      if (escaped) {
        escaped = false;
      } else if (character === '\\') {
        escaped = true;
      } else if (character === "'") {
        if (input[index + 1] === "'") {
          index += 1;
        } else {
          quoted = false;
        }
      }
    } else if (character === "'") {
      quoted = true;
    } else if (character === ';') {
      statements.push(input.slice(start, index + 1));
      start = index + 1;
    }
  }

  return statements;
}

function parseSqlValue(raw) {
  const value = raw.trim();

  if (/^null$/i.test(value)) {
    return null;
  }

  if (value.startsWith("'") && value.endsWith("'")) {
    return value
      .slice(1, -1)
      .replaceAll('\\0', '\u0000')
      .replaceAll('\\n', '\n')
      .replaceAll('\\r', '\r')
      .replaceAll('\\t', '\t')
      .replaceAll('\\b', '\b')
      .replaceAll('\\Z', '\u001a')
      .replaceAll("\\'", "'")
      .replaceAll('\\\\', '\\')
      .replaceAll("''", "'");
  }

  if (/^0x[0-9a-f]+$/i.test(value)) {
    return value;
  }

  if (/^-?\d+$/.test(value)) {
    return Number(value);
  }

  if (/^-?(?:\d+\.\d*|\d*\.\d+)$/.test(value)) {
    return Number(value);
  }

  return value;
}

function parseRows(valuesSql) {
  const rows = [];
  let rowStart = -1;
  let depth = 0;
  let quoted = false;
  let escaped = false;

  for (let index = 0; index < valuesSql.length; index += 1) {
    const character = valuesSql[index];

    if (quoted) {
      if (escaped) {
        escaped = false;
      } else if (character === '\\') {
        escaped = true;
      } else if (character === "'") {
        if (valuesSql[index + 1] === "'") {
          index += 1;
        } else {
          quoted = false;
        }
      }
      continue;
    }

    if (character === "'") {
      quoted = true;
    } else if (character === '(') {
      if (depth === 0) {
        rowStart = index + 1;
      }
      depth += 1;
    } else if (character === ')') {
      depth -= 1;
      if (depth === 0 && rowStart >= 0) {
        rows.push(
          splitTopLevel(valuesSql.slice(rowStart, index)).map(parseSqlValue)
        );
        rowStart = -1;
      }
    }
  }

  return rows;
}

function parseSchema(sql) {
  const schema = {};

  for (const match of sql.matchAll(
    /CREATE TABLE `([^`]+)`\s*\(([\s\S]*?)\) ENGINE=/g
  )) {
    const [, table, body] = match;
    const columns = [];

    for (const line of body.split('\n')) {
      const column = line.match(/^\s*`([^`]+)`\s+([^\s,]+)/);
      if (column) {
        columns.push({ name: column[1], sqlType: column[2] });
      }
    }

    schema[table] = { columns };
  }

  return schema;
}

function parseSqlDump(sql) {
  const schema = parseSchema(sql);
  const tables = Object.fromEntries(
    Object.keys(schema).map((table) => [table, []])
  );

  for (const statement of splitStatements(sql)) {
    const insert = statement.match(
      /INSERT INTO `([^`]+)`\s*\(([^]*?)\)\s*VALUES\s*([\s\S]*);\s*$/
    );

    if (!insert) {
      continue;
    }

    const [, table, rawColumns, rawValues] = insert;
    const columns = splitTopLevel(rawColumns).map((column) =>
      column.replace(/^`|`$/g, '')
    );
    const target = tables[table] || (tables[table] = []);

    for (const row of parseRows(rawValues)) {
      const document = {};
      columns.forEach((column, index) => {
        document[column] = row[index] ?? null;
      });
      document._legacy_table = table;
      target.push(document);
    }
  }

  return { schema, tables };
}

function rowCount(tables) {
  return Object.values(tables).reduce((total, rows) => total + rows.length, 0);
}

async function writeSnapshot(parsed, sourcePath) {
  const payload = {
    format: 'taxsaathi-legacy-snapshot-v1',
    source: path.relative(projectRoot, sourcePath),
    generated_at: new Date().toISOString(),
    table_count: Object.keys(parsed.tables).length,
    row_count: rowCount(parsed.tables),
    schema: parsed.schema,
    tables: parsed.tables
  };

  await fs.mkdir(path.dirname(snapshotPath), { recursive: true });
  await fs.writeFile(snapshotPath, `${JSON.stringify(payload)}\n`, 'utf8');
  return payload;
}

async function importIntoMongo(parsed, replaceExisting) {
  if (!process.env.MONGODB_URI) {
    throw new Error('MONGODB_URI is required for a MongoDB import.');
  }

  await mongoose.connect(process.env.MONGODB_URI, {
    serverSelectionTimeoutMS: 10000
  });

  const database = mongoose.connection.db;
  const tableReports = [];

  try {
    for (const [table, rows] of Object.entries(parsed.tables)) {
      const collection = database.collection(table);

      if (replaceExisting) {
        await collection.deleteMany({});
      }

      const operations = rows
        .filter((row) => row.id !== undefined && row.id !== null)
        .map((row) => ({
          updateOne: {
            filter: { id: row.id },
            update: { $set: row },
            upsert: true
          }
        }));

      for (let index = 0; index < operations.length; index += 500) {
        const batch = operations.slice(index, index + 500);
        if (batch.length > 0) {
          await collection.bulkWrite(batch, { ordered: false });
        }
      }

      tableReports.push({ table, rows: rows.length, collection });
    }
  } finally {
    await mongoose.disconnect();
  }

  return tableReports.map(({ table, rows }) => ({ table, rows }));
}

async function main() {
  const replaceExisting = process.argv.includes('--replace');
  const snapshotOnly = process.argv.includes('--snapshot');
  let sqlPath = defaultSqlPath;
  try {
    await fs.access(sqlPath);
  } catch {
    const knownArchivePath = path.resolve(projectRoot, 'upload/taxsathi2.sql');
    if (path.resolve(sqlPath) !== knownArchivePath) {
      await fs.access(knownArchivePath);
      sqlPath = knownArchivePath;
      console.warn(`Configured SQL_DUMP was not found; using ${path.relative(projectRoot, sqlPath)}.`);
    } else {
      throw new Error(`SQL dump not found at ${sqlPath}. Set SQL_DUMP to the supplied dump path.`);
    }
  }
  const sql = await fs.readFile(sqlPath, 'utf8');
  const parsed = parseSqlDump(sql);
  const snapshot = await writeSnapshot(parsed, sqlPath);
  let mongo = null;

  if (!snapshotOnly && process.env.MONGODB_URI) {
    mongo = await importIntoMongo(parsed, replaceExisting);
  }

  const report = {
    source: path.relative(projectRoot, sqlPath),
    generated_at: new Date().toISOString(),
    table_count: Object.keys(parsed.tables).length,
    row_count: rowCount(parsed.tables),
    snapshot: path.relative(projectRoot, snapshotPath),
    mongodb_imported: Array.isArray(mongo),
    replace_existing: replaceExisting,
    tables: Object.fromEntries(
      Object.entries(parsed.tables).map(([table, rows]) => [table, rows.length])
    )
  };

  if (Array.isArray(mongo)) {
    report.mongodb_tables = Object.fromEntries(
      mongo.map(({ table, rows }) => [table, rows])
    );
  }

  await fs.writeFile(reportPath, `${JSON.stringify(report, null, 2)}\n`, 'utf8');
  console.log(
    `Parsed ${report.table_count} tables and ${report.row_count} rows. Snapshot: ${report.snapshot}`
  );
  if (report.mongodb_imported) {
    console.log(
      `MongoDB import completed${replaceExisting ? ' with replacement' : ' with upserts'}.`
    );
  } else {
    console.log(
      'MongoDB import not run. Set MONGODB_URI and run npm run migrate when MongoDB is available.'
    );
  }
}

main().catch((error) => {
  console.error(error instanceof Error ? error.message : error);
  process.exitCode = 1;
});

export { parseSqlDump };
