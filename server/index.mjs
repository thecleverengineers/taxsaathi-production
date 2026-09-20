import path from 'node:path';
import fs from 'node:fs';
import { fileURLToPath } from 'node:url';
import express from 'express';
import cors from 'cors';
import helmet from 'helmet';
import morgan from 'morgan';
import cookieParser from 'cookie-parser';
import dotenv from 'dotenv';
import apiRouter from './routes/api.mjs';
import { DataStore } from './lib/store.mjs';

dotenv.config({ path: path.resolve(process.cwd(), '.env') });

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const projectRoot = path.resolve(__dirname, '..');
const clientDist = path.resolve(projectRoot, 'client/dist');
const clientIndexPath = path.join(clientDist, 'index.html');
function prepareClientIndex(index) {
  const entry = index.match(/<script type="module" crossorigin src="([^"]+)"><\/script>/)?.[1];
  if (!entry) return index;
  const bootstrap = `<script data-cfasync="false">
    (() => {
      const loadApplication = () => import(${JSON.stringify(entry)}).catch((error) => console.error('TaxSaathi bootstrap failed.', error));
      if (!('serviceWorker' in navigator)) return loadApplication();
      navigator.serviceWorker.getRegistrations().then(async (registrations) => {
        if (!registrations.length) return loadApplication();
        await Promise.all(registrations.map((registration) => registration.unregister()));
        window.location.reload();
      }).catch(loadApplication);
    })();
  </script>`;
  return index.replace(/<script type="module" crossorigin src="[^"]+"><\/script>/, bootstrap);
}
const clientIndex = fs.existsSync(clientIndexPath) ? prepareClientIndex(fs.readFileSync(clientIndexPath, 'utf8')) : null;
const legacyPublic = path.resolve(projectRoot, 'legacy/taxsaathi/public');
const legacyStorage = path.resolve(projectRoot, 'legacy/taxsaathi/storage');
const port = Number(process.env.PORT || 4001);
const host = process.env.HOST || '0.0.0.0';
const publicUrl = process.env.PUBLIC_URL || process.env.RENDER_EXTERNAL_URL || `http://127.0.0.1:${port}`;
const allowedOrigins = String(process.env.CORS_ORIGIN || publicUrl)
  .split(',')
  .map((value) => value.trim())
  .concat(['https://taxsaathi.in', 'https://www.taxsaathi.in'])
  .filter(Boolean)
  .filter((value, index, values) => values.indexOf(value) === index);

const store = await new DataStore().connect();
const app = express();
app.locals.store = store;
app.locals.publicUrl = publicUrl;

app.disable('x-powered-by');
app.set('trust proxy', 1);
app.use(helmet({ contentSecurityPolicy: false }));
app.use(cors({
  origin(origin, callback) {
    if (!origin || allowedOrigins.includes('*') || allowedOrigins.includes(origin)) return callback(null, true);
    return callback(new Error('Origin is not allowed by CORS.'));
  },
  credentials: true
}));
app.use(express.json({ limit: '4mb' }));
app.use(express.urlencoded({ extended: true, limit: '4mb' }));
app.use(cookieParser());
app.use(morgan(process.env.NODE_ENV === 'production' ? 'combined' : 'dev'));

app.use((request, response, next) => {
  if (['GET', 'HEAD', 'OPTIONS'].includes(request.method)) return next();
  const origin = request.headers.origin;
  const hasSessionCookie = Boolean(request.cookies?.taxsaathi_token);
  if (hasSessionCookie && origin && !allowedOrigins.includes('*') && !allowedOrigins.includes(origin)) {
    return response.status(403).json({ ok: false, message: 'Request origin is not allowed.' });
  }
  return next();
});

app.use('/legacy-assets/uploads', express.static(path.join(legacyPublic, 'uploads'), { maxAge: '7d' }));
app.use('/payment-assets', express.static(path.join(projectRoot, 'server/storage/payment'), { maxAge: '1d' }));
app.use('/api', apiRouter);

app.get('/sitemap.xml', async (_request, response) => {
  const services = await store.find('services', { is_active: 1 }, { sort: { id: 1 } });
  const base = publicUrl;
  const urls = [
    '/', '/services', '/about', '/contact', '/tax-calculators',
    ...services.map((service) => `/services/${service.slug}`)
  ];
  response.type('application/xml').send(
    `<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">${urls.map((url) => `<url><loc>${base}${url}</loc></url>`).join('')}</urlset>`
  );
});

if (fs.existsSync(clientDist)) {
  app.use(express.static(clientDist, { index: false, maxAge: '1h' }));
  app.get(/^(?!\/api(?:\/|$)|\/legacy-assets(?:\/|$)|\/payment-assets(?:\/|$)|\/sitemap\.xml$).*/, (_request, response) => {
    response.set('Cache-Control', 'no-store, no-cache, must-revalidate, proxy-revalidate');
    if (clientIndex) return response.type('html').send(clientIndex);
    return response.sendFile(clientIndexPath);
  });
} else {
  app.get('/', (_request, response) => {
    response.type('text/plain').send('TaxSaathi API is running. Build the client with npm run build.');
  });
}

app.use((error, _request, response, _next) => {
  console.error(error);
  if (response.headersSent) return;
  const message = process.env.NODE_ENV === 'production' ? 'Internal server error.' : (error.message || 'Internal server error.');
  response.status(error.status || 500).json({ ok: false, message });
});

const server = app.listen(port, host, () => {
  console.log(`TaxSaathi MERN listening on http://${host}:${port}`);
  console.log(`Configured public URL: ${publicUrl}`);
  console.log(`Data mode: ${store.mode}`);
});

function shutdown(signal) {
  console.log(`${signal} received. Shutting down TaxSaathi.`);
  server.close(async () => {
    await store.close().catch(() => {});
    process.exit(0);
  });
}

process.on('SIGINT', () => shutdown('SIGINT'));
process.on('SIGTERM', () => shutdown('SIGTERM'));
