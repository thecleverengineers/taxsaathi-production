// Canonical dependency map for the twelve-layer migration. Existing legacy
// routes can adopt these contracts incrementally without breaking their URLs.
export const applicationLayers = Object.freeze([
  { number: 1, name: 'presentation', responsibility: 'React pages, components, responsive UX', dependsOn: [] },
  { number: 2, name: 'experience', responsibility: 'Routing, page orchestration, client state, permission-aware navigation', dependsOn: ['presentation'] },
  { number: 3, name: 'transport', responsibility: 'HTTP routes, validation mapping, response envelopes', dependsOn: ['experience'] },
  { number: 4, name: 'identity', responsibility: 'Authentication, OTP, sessions, account recovery', dependsOn: [] },
  { number: 5, name: 'policy', responsibility: 'RBAC, organization scope, entitlements, sensitive-action policy', dependsOn: ['identity'] },
  { number: 6, name: 'application', responsibility: 'Use cases and transaction orchestration', dependsOn: ['policy'] },
  { number: 7, name: 'domain', responsibility: 'Cases, services, workflow stages, approvals, compliance rules', dependsOn: ['application'] },
  { number: 8, name: 'automation', responsibility: 'Events, queues, reminders, escalations, scheduled work', dependsOn: ['domain'] },
  { number: 9, name: 'repositories', responsibility: 'Scoped data access, pagination, search, repository contracts', dependsOn: ['policy'] },
  { number: 10, name: 'persistence', responsibility: 'MongoDB, indexes, GridFS, migrations, snapshots', dependsOn: ['repositories'] },
  { number: 11, name: 'integrations', responsibility: 'Fast2SMS, email, WhatsApp, payments, AI, webhooks', dependsOn: ['application'] },
  { number: 12, name: 'operations', responsibility: 'Audit, logs, metrics, backups, health, deployment controls', dependsOn: [] }
]);

export function layerByName(name) {
  return applicationLayers.find((layer) => layer.name === String(name || '').toLowerCase()) || null;
}
