import fs from 'node:fs/promises';
import path from 'node:path';

const root = path.resolve(process.cwd());
const required = [
  'package.json',
  'package-lock.json',
  'client/package.json',
  'client/package-lock.json',
  'server/index.mjs',
  'server/routes/api.mjs',
  'render.yaml'
];
const missing = [];
for (const file of required) {
  try { await fs.access(path.join(root, file)); } catch { missing.push(file); }
}
if (missing.length) {
  console.error(JSON.stringify({ ok: false, missing }));
  process.exit(1);
}
const packageJson = JSON.parse(await fs.readFile(path.join(root, 'package.json'), 'utf8'));
const clientPackage = JSON.parse(await fs.readFile(path.join(root, 'client/package.json'), 'utf8'));
const report = {
  ok: true,
  node: process.version,
  server_start: packageJson.scripts?.start,
  client_build: clientPackage.scripts?.build,
  render_blueprint: true,
  snapshot: false,
  note: 'Run npm run snapshot locally when the SQL dump is available.'
};
console.log(JSON.stringify(report));
