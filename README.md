# TaxSaathi — React, Node.js, MongoDB Atlas

This repository is the production migration of the supplied TaxSaathi Core-PHP/MariaDB application. The React SPA and Express API preserve the legacy IDs and collection names while adding a MongoDB Atlas runtime, GridFS-backed uploads, role-aware workspaces, payment/document workflows, notifications, support, reporting, partner operations, and Render deployment configuration.

## What was migrated

- 57 legacy tables and 4,773 SQL rows are parsed by `server/scripts/migrate-sql-to-mongo.mjs`.
- Legacy numeric IDs and table names are retained so order, client, service, document, payment, notification, partner, and role relationships remain traceable.
- A local snapshot can be generated at `server/data/legacy-snapshot.json` for development. Production refuses to silently fall back to an in-memory snapshot unless `MONGO_ALLOW_SNAPSHOT=true`.
- New documents and payment proofs are stored in MongoDB GridFS when Atlas is connected. Existing public assets remain under the legacy public upload tree; private legacy storage is not exposed as a public static directory.

## Local setup

```bash
cp .env.example .env
npm install
npm --prefix client install
npm run snapshot
npm run build
npm start
```

Open `http://127.0.0.1:4001`.

Sign in with the email address and password stored for the account. Imported users without a `password_hash` must have a password set before they can sign in. New registrations and password resets require a mobile OTP sent through Fast2SMS.

## MongoDB import

Set `MONGODB_URI` in `.env`, then run:

```bash
npm run migrate
```

The default import is upsert-based. To intentionally replace the contents of the imported collections:

```bash
npm run migrate:replace
```

The replacement flag deletes the target MongoDB collections before importing; use it only against the intended database. The supplied SQL dump is intentionally ignored by Git because it contains production data; keep it local or in a secure migration bucket.

To move existing legacy files into Atlas GridFS after importing the tables:

```bash
npm run migrate:files
```

The command is idempotent and records the GridFS file ID on migrated rows.

## Render deployment

`render.yaml` provisions a Node web service and a 15-minute workflow-reminder cron job. MongoDB Atlas remains the database provider. Before applying the Blueprint, add the repository to GitHub, connect GitHub to Render, and fill the variables marked `sync: false` in the Render dashboard. Do not paste the archived `.env` or legacy PHP config files into GitHub.

## Local PM2 (optional)

Copy the project and the extracted `legacy/` asset directory to the server, install Node.js 20+, MongoDB, and PM2, configure `.env`, then run:

```bash
npm install -g pm2
npm install
npm --prefix client install
npm run migrate
npm run build
npm run pm2:start
pm2 save
pm2 startup
```

The included `ecosystem.config.cjs` can run the service locally or on a VM. Set `PUBLIC_URL` to the real HTTPS domain instead of the old IP before using it.

## Main API areas

- `/api/public/*` — home, services, calculators, contact, applications
- `/api/auth/*` — email/password sign-in, mobile OTP registration/password reset, and session
- `/api/dashboard` — role-aware dashboard data
- `/api/orders/*` — orders, workflow status, assignment, payments, uploads, invoice PDF
- `/api/partner/*` — partner order and dashboard data
- `/api/notifications/*` — notification feed, read state, SSE stream
- `/api/support/*` — conversations and messages
- `/api/admin/*` — users, services, settings, collections, reports, CSV export
- `/api/admin/content/*`, `/api/admin/seo/*`, `/api/admin/roles/*`, `/api/admin/leads/*`, `/api/admin/workflow/*` — migrated content, SEO, RBAC, lead, and workflow administration

The React client includes public pages, client portal, admin/manager dashboard, executive payout view, partner workspace, support inbox, notifications, order detail workflow, and the responsive mobile bottom navigation used by the legacy dashboard design.
