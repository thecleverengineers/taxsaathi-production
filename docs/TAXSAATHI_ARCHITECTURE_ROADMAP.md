# Tax Saathi enterprise architecture roadmap

## Scope

Tax Saathi is being evolved from the migrated Core-PHP/MariaDB application into a multi-tenant TaxTech, ComplianceTech, professional-services, document, billing, CRM, workflow, and intelligence platform. This document records the current state and the dependency order for the migration. Existing legacy IDs, collections, uploaded assets, orders, services, users, payments, and notifications remain compatible.

## Current architecture audit

### Runtime and deployment

- React 19 + Vite SPA in `client/`.
- Express 5 API in `server/`.
- MongoDB Atlas is the production store; a JSON snapshot is available for local development only.
- `DataStore` provides a compatibility abstraction over MongoDB collections and the legacy snapshot.
- GridFS is used for new private uploads when MongoDB is available; migrated public assets remain under the legacy upload tree.
- Render runs the web service and a 15-minute workflow-reminder cron service.
- PM2 configuration is retained for VM deployments.
- Helmet, CORS, origin checks, rate limits, bcrypt, JWT, and OTP flows already exist.

### Existing frontend modules and routes

- Public catalogue: home, services, service detail, calculators, contact, legal pages.
- Authentication: login, registration with mobile OTP, forgot-password OTP, profile, password change.
- Individual workspace: dashboard, orders, order detail, payment, profile, support, notifications.
- Partner workspace: dashboard, orders, payment proof, profile, partner CRM surfaces.
- Admin/operations: users, services, partners, reports, payment settings, content, leads, applications, SEO, service catalogue details, roles, activity timeline, coupons, and workflow reminders.
- Executive surfaces: assigned order workflow, reports, payouts, notifications.

### Current backend domains and collections

The migration preserves the legacy collection names, including users, roles, user_roles, permission_catalog, clients, services, service_categories, service_requirements, service_benefits, service_types, service_reviews, service_banners, orders, order_documents, payments, invoices, coupons, coupon_redemptions, notifications, notification_user_reads, activity_logs, support_conversations, support_messages, leads, service_applications, partner_profiles, partner_orders, partner_leads, partner_tasks, partner_messages, partner_subscriptions, website_settings, tax_rules, FAQs, testimonials, SEO records, and migrated legacy tables.

The platform foundation now also reserves indexed collections for audit_logs, sessions, organizations, organization_members, subscriptions, approvals, and future backup_runs without changing existing records.

### Authentication and authorization

- JWTs are signed with a production-enforced secret length check and are accepted through a bearer header or HTTP-only cookie.
- Active user lookup and legacy `user_roles` assignments are checked on every authenticated request.
- Existing routes use role guards such as admin, manager, executive, partner, and client.
- Legacy roles can carry `permissions_json`.
- The new RBAC foundation normalizes permission keys, derives effective permissions across assigned roles, exposes safe permission metadata to the frontend, and provides server-side permission middleware. Admin retains an explicit full-access path for backward compatibility.
- The first protected operations now use permission checks: role management, password resets, and audit access.

### Existing service, document, billing, and notification implementation

- Services and categories are data-driven and editable through admin APIs.
- Required service documents can be attached during application/order creation.
- Order documents support uploads, reuploads, status/rejection feedback, final outputs, and protected downloads.
- Payments support manual proof and optional Razorpay integration; invoices and PDF output are connected to orders.
- Fast2SMS OTP delivery is implemented through environment-configured credentials.
- Notifications support in-app records, read state, SSE streaming, push-token registration, and workflow reminders.
- Support conversations and messages provide the current client/staff communication surface.

## Current gaps to close

1. Authorization is still role-heavy in many existing routes; all sensitive routes need migration to permission checks.
2. Corporate organizations, companies, branches, memberships, and tenant ownership are not yet a complete first-class domain.
3. Subscription plans and entitlements are not yet a complete corporate SaaS engine.
4. Orders are the current workflow primitive; they must evolve into a versioned Case Engine without breaking existing order IDs.
5. Workflow stages, checklists, tasks, approvals, notices, compliance obligations, and recurring cases need dedicated models and APIs.
6. Audit history is being introduced; more mutations must emit append-only audit events.
7. JWT sessions are not yet persisted for per-device listing/revocation and refresh rotation.
8. Upload validation currently relies mainly on declared MIME type and size; magic-byte validation, antivirus scanning, quarantine, and document intelligence are future hardening work.
9. Several endpoints load large result sets and calculate dashboard totals in application memory; aggregation, pagination, and indexes need to be expanded.
10. There is no queue abstraction for email, WhatsApp, push, OCR, exports, webhooks, or backups.
11. API responses are mostly legacy `{ ok, ... }` payloads and are not yet fully versioned under `/api/v1`.
12. Subscription, billing, refunds, retention, corporate analytics, reconciliation, AI search, and external accounting integrations remain roadmap work.

## Twelve-layer application architecture

The target dependency direction is inward and explicit. A layer may call the layer immediately below it through a narrow interface; it must not reach around the domain or persistence boundary.

1. **Presentation layer** — React pages, reusable UI components, responsive layouts, accessibility, loading/error/empty states.
2. **Experience orchestration layer** — route composition, role/permission-aware navigation, page state, client caching, optimistic UI, and deep links.
3. **API transport layer** — Express routers, request parsing, consistent response envelopes, pagination, validation mapping, and HTTP error handling.
4. **Identity and authentication layer** — password hashing, OTP, access tokens, secure cookies, session rotation, MFA readiness, and account recovery.
5. **Authorization and tenant-policy layer** — permission evaluation, organization membership, subscription entitlement checks, resource ownership, and sensitive-action confirmation.
6. **Application-service layer** — use cases such as create case, assign executive, request documents, issue invoice, approve filing, and close case.
7. **Domain and workflow layer** — Case Engine, configurable stages, SLA rules, approvals, notices, compliance obligations, and immutable transitions.
8. **Automation and job layer** — event triggers, conditions, actions, reminders, escalations, queue workers, scheduled reports, OCR, and webhook retries.
9. **Repository/data-access layer** — typed repository contracts, scoped queries, pagination, search filters, and transaction boundaries.
10. **Persistence layer** — MongoDB collections, indexes, migrations, GridFS/object storage metadata, snapshots, and immutable financial snapshots.
11. **Integration layer** — Fast2SMS, email, WhatsApp, push, Razorpay, OCR/AI providers, accounting platforms, and external filing APIs.
12. **Observability and operations layer** — structured logs, metrics, traces, audit logs, security alerts, health checks, backup/restore, deployment safety, and incident controls.

The current flat modules remain the compatibility surface during migration. New code should be placed under `server/layers/` or an equivalent domain module and should use the existing `DataStore` through repository boundaries. Existing routes are migrated incrementally rather than duplicated.

## Implementation roadmap

### Critical

- Complete permission enforcement on all admin, executive, billing, document, and export mutations.
- Add persistent sessions, refresh-token rotation, session termination, login-attempt tracking, and security alerts.
- Add organization and membership scope helpers, then backfill corporate ownership safely.
- Harden file uploads with extension/MIME/magic-byte checks, quarantine, private delivery, and size/rate limits.
- Add append-only audit events to all sensitive mutations.
- Add pagination and indexed query paths to high-volume endpoints.

### Foundation

- Organization, company, branch, registration, financial-year, and membership models.
- Configurable roles, permissions, service entitlements, and subscription plan limits.
- Case Engine backed by existing orders for backward-compatible migration.
- Requirement/document vault with versioning, verification, tags, expiry, and case links.
- Task, comment, approval, notification-preference, and timeline services.

### Advanced

- Corporate compliance calendar and recurring obligations.
- Notice management, SLA/escalation rules, executive workload, Client 360, and CRM pipeline.
- Billing profiles, immutable invoice snapshots, subscriptions, renewals, grace periods, refunds, and usage metering.
- Universal search, advanced filters, exports, drill-down analytics, and operational command center.

### Ultra Advanced

- No-code workflow builder and automation rules.
- GST and bank reconciliation exception workspaces.
- Multi-channel communication center with delivery logs and template variables.
- Webhooks, API keys, OAuth scopes, rate limits, IP restrictions, and delivery retries.
- White-label configuration and organization-level branding.

### Enterprise, AI, Security, and DevOps

- AI document extraction with confidence and human review.
- Role-scoped natural-language search and executive/admin/corporate copilots.
- AI-generated summaries with source references and consequential-action approvals.
- Security center, MFA, anomaly detection, malware scanning, encrypted backups, integrity checks, restore approvals, and disaster-recovery drills.
- Queue workers, structured logs, metrics, tracing, deployment canaries, migration checks, and automated health verification.

## Data safety rules

- Never delete or replace production data as part of a feature deployment.
- Preserve legacy IDs and upload references while adding stable modern IDs.
- Keep uploads, GridFS, backups, secrets, and runtime configuration outside replaceable build directories.
- Use organization scope in every corporate query before returning a record.
- Keep historical invoice and billing-profile snapshots immutable.
- Require explicit authorization and audit records for restore, export, role changes, billing changes, and destructive actions.
