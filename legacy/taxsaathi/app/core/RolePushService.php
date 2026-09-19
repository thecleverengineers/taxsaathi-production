<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * Role-aware notification dispatcher.
 *
 * notification_manager is the authoritative per-user inbox.
 * notifications is maintained as a compatibility mirror for older UI/code.
 * user_push_tokens stores one FCM token per user/device.
 */
final class RolePushService
{
    private const ALLOWED_RECIPIENT_TYPES = [
        'admin', 'manager', 'executive', 'client', 'partner', 'system',
    ];

    private const ALLOWED_SEVERITIES = [
        'normal', 'info', 'success', 'warning', 'high', 'critical',
    ];

    private Database $db;
    private array $roleCache = [];

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Dispatch one logical event to explicit users, roles and context owners.
     *
     * Useful payload keys:
     * - title, message/body, severity, status_label, url
     * - actor_user_id, user_id, user_ids, recipient_user_id, recipient_user_ids
     * - roles/role_slugs/audience
     * - order_id, partner_order_id, client_id, partner_id, executive_user_id
     * - source_table, source_id, type, data/payload
     * - include_actor, include_context_roles, mirror_legacy, send_push
     */
    public function dispatch(string $eventKey, array $payload = []): array
    {
        $eventKey = $this->cleanKey($eventKey, 'general');
        $actorUserId = (int) ($payload['actor_user_id'] ?? 0);
        $context = $this->hydrateContext($payload);
        $recipients = $this->resolveRecipients($eventKey, $context);

        if (!(bool) ($payload['include_actor'] ?? false) && $actorUserId > 0) {
            unset($recipients[$actorUserId]);
        }

        $title = trim((string) ($payload['title'] ?? $this->defaultTitle($eventKey, $context)));
        $message = trim((string) ($payload['message'] ?? $payload['body'] ?? $this->defaultMessage($eventKey, $context)));
        $severity = strtolower(trim((string) ($payload['severity'] ?? $this->defaultSeverity($eventKey))));
        $severity = in_array($severity, self::ALLOWED_SEVERITIES, true) ? $severity : 'normal';
        $statusLabel = trim((string) ($payload['status_label'] ?? $context['status_label'] ?? ''));
        $explicitUrl = trim((string) ($payload['url'] ?? ''));
        $url = $explicitUrl !== '' ? $explicitUrl : $this->defaultUrl($context);
        $type = $this->cleanKey((string) ($payload['type'] ?? 'notification'), 'notification');
        $sourceTable = trim((string) ($payload['source_table'] ?? $context['source_table'] ?? ''));
        $sourceId = (int) ($payload['source_id'] ?? $context['source_id'] ?? 0);
        $mirrorLegacy = !array_key_exists('mirror_legacy', $payload) || (bool) $payload['mirror_legacy'];
        $sendPush = !array_key_exists('send_push', $payload) || (bool) $payload['send_push'];

        if ($title === '') {
            $title = 'Tax Saathi notification';
        }

        $commonData = [
            'event_key' => $eventKey,
            'type' => $type,
            'order_id' => (int) ($context['order_id'] ?? 0),
            'partner_order_id' => (int) ($context['partner_order_id'] ?? 0),
            'client_id' => (int) ($context['client_id'] ?? 0),
            'partner_id' => (int) ($context['partner_id'] ?? 0),
            'executive_user_id' => (int) ($context['executive_user_id'] ?? 0),
            'order_no' => (string) ($context['order_no'] ?? ''),
            'source_table' => $sourceTable,
            'source_id' => $sourceId,
            'status_label' => $statusLabel,
            'url' => $url,
        ];

        $extraData = $payload['data'] ?? $payload['payload'] ?? [];
        if (!is_array($extraData)) {
            $extraData = ['value' => $extraData];
        }
        $data = array_merge($commonData, $extraData);

        $created = [];
        $duplicates = [];
        $pushResults = [];
        $errors = [];

        foreach ($recipients as $recipientUserId => $recipient) {
            try {
                $stateHash = $this->stateHash($eventKey, (int) $recipientUserId, [
                    'source_table' => $sourceTable,
                    'source_id' => $sourceId,
                    'order_id' => $commonData['order_id'],
                    'partner_order_id' => $commonData['partner_order_id'],
                    'status_label' => $statusLabel,
                    'title' => $title,
                    'message' => $message,
                    'dedupe_key' => (string) ($payload['dedupe_key'] ?? ''),
                ]);

                $existing = $this->findDuplicate(
                    (int) $recipientUserId,
                    $eventKey,
                    $stateHash,
                    $sourceTable,
                    $sourceId
                );

                if ($existing !== null) {
                    $duplicates[] = (int) $recipientUserId;
                    continue;
                }

                $uid = $this->newUid();
                $now = date('Y-m-d H:i:s');
                $recipientType = $this->normalizeRecipientType((string) ($recipient['recipient_type'] ?? 'system'));
                $recipientRoleId = (int) ($recipient['role_id'] ?? 0);
                $recipientRole = trim((string) ($recipient['role_slug'] ?? $recipient['role_name'] ?? $recipientType));
                $recipientUrl = $explicitUrl !== '' ? $explicitUrl : $this->urlForRecipient($recipientType, $context);
                $recipientData = $data;
                $recipientData['url'] = $recipientUrl;

                $this->db->execute(
                    'INSERT INTO notification_manager (
                        uid, recipient_user_id, recipient_role_id, recipient_role, recipient_type,
                        actor_user_id, client_id, partner_id, executive_user_id,
                        order_id, partner_order_id, order_no,
                        source_table, source_id, type, event_key,
                        title, message, severity, status_label, url,
                        state_hash, payload_json, is_read, read_at,
                        delivered_at, first_seen_at, created_at, updated_at
                     ) VALUES (
                        :uid, :recipient_user_id, :recipient_role_id, :recipient_role, :recipient_type,
                        :actor_user_id, :client_id, :partner_id, :executive_user_id,
                        :order_id, :partner_order_id, :order_no,
                        :source_table, :source_id, :type, :event_key,
                        :title, :message, :severity, :status_label, :url,
                        :state_hash, :payload_json, 0, NULL,
                        NULL, NULL, :created_at, :updated_at
                     )',
                    [
                        'uid' => $uid,
                        'recipient_user_id' => (int) $recipientUserId,
                        'recipient_role_id' => $recipientRoleId > 0 ? $recipientRoleId : null,
                        'recipient_role' => $recipientRole !== '' ? $recipientRole : null,
                        'recipient_type' => $recipientType,
                        'actor_user_id' => $actorUserId > 0 ? $actorUserId : null,
                        'client_id' => $commonData['client_id'] > 0 ? $commonData['client_id'] : null,
                        'partner_id' => $commonData['partner_id'] > 0 ? $commonData['partner_id'] : null,
                        'executive_user_id' => $commonData['executive_user_id'] > 0 ? $commonData['executive_user_id'] : null,
                        'order_id' => $commonData['order_id'] > 0 ? $commonData['order_id'] : null,
                        'partner_order_id' => $commonData['partner_order_id'] > 0 ? $commonData['partner_order_id'] : null,
                        'order_no' => $commonData['order_no'] !== '' ? $commonData['order_no'] : null,
                        'source_table' => $sourceTable !== '' ? $sourceTable : null,
                        'source_id' => $sourceId > 0 ? $sourceId : null,
                        'type' => $type,
                        'event_key' => $eventKey,
                        'title' => $title,
                        'message' => $message !== '' ? $message : null,
                        'severity' => $severity,
                        'status_label' => $statusLabel !== '' ? $statusLabel : null,
                        'url' => $recipientUrl !== '' ? $recipientUrl : null,
                        'state_hash' => $stateHash,
                        'payload_json' => $this->json($recipientData),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );

                if ($mirrorLegacy) {
                    $this->mirrorLegacyNotification(
                        $uid,
                        (int) $recipientUserId,
                        $title,
                        $message,
                        $type,
                        $statusLabel,
                        $severity,
                        $recipientUrl,
                        $recipientData,
                        $now
                    );
                }

                $created[] = [
                    'uid' => $uid,
                    'user_id' => (int) $recipientUserId,
                    'recipient_type' => $recipientType,
                ];

                if ($sendPush) {
                    $pushResults[(int) $recipientUserId] = $this->sendToUserDevices(
                        (int) $recipientUserId,
                        [
                            'uid' => $uid,
                            'title' => $title,
                            'message' => $message,
                            'severity' => $severity,
                            'url' => $recipientUrl,
                            'event_key' => $eventKey,
                            'data' => $recipientData,
                        ]
                    );
                }
            } catch (Throwable $e) {
                $errors[] = [
                    'user_id' => (int) $recipientUserId,
                    'message' => $e->getMessage(),
                ];
                error_log('RolePushService dispatch failed: ' . $e->getMessage());
            }
        }

        return [
            'event_key' => $eventKey,
            'recipient_count' => count($recipients),
            'created_count' => count($created),
            'duplicate_count' => count($duplicates),
            'created' => $created,
            'duplicates' => $duplicates,
            'push' => $pushResults,
            'errors' => $errors,
        ];
    }

    /**
     * Resolve all recipients using explicit IDs, role slugs and order context.
     * Result is keyed by users.id to guarantee one inbox row per user.
     */
    public function resolveRecipients(string $eventKey, array $payload = []): array
    {
        $recipients = [];

        foreach ($this->extractUserIds($payload) as $userId) {
            $this->addUser($recipients, $userId);
        }

        $roles = $this->extractRoleSlugs($payload);
        if ($roles === [] && (bool) ($payload['include_context_roles'] ?? true)) {
            $roles = $this->defaultAudience($eventKey);
        }

        foreach ($roles as $roleSlug) {
            foreach ($this->usersForRole($roleSlug) as $user) {
                $this->addResolvedUser($recipients, $user);
            }
        }

        $orderId = (int) ($payload['order_id'] ?? 0);
        $partnerOrderId = (int) ($payload['partner_order_id'] ?? 0);
        $clientId = (int) ($payload['client_id'] ?? 0);
        $partnerId = (int) ($payload['partner_id'] ?? 0);
        $executiveUserId = (int) ($payload['executive_user_id'] ?? 0);

        if ($orderId > 0) {
            $order = $this->db->fetch(
                'SELECT id, order_no, client_id, partner_id, assigned_user_id
                 FROM orders
                 WHERE id = :id
                 LIMIT 1',
                ['id' => $orderId]
            );

            if (is_array($order)) {
                $clientId = $clientId > 0 ? $clientId : (int) ($order['client_id'] ?? 0);
                $partnerId = $partnerId > 0 ? $partnerId : (int) ($order['partner_id'] ?? 0);
                $executiveUserId = $executiveUserId > 0 ? $executiveUserId : (int) ($order['assigned_user_id'] ?? 0);
            }
        }

        if ($partnerOrderId > 0) {
            $partnerOrder = $this->db->fetch(
                'SELECT id, order_no, partner_id, accepted_order_id
                 FROM partner_orders
                 WHERE id = :id
                 LIMIT 1',
                ['id' => $partnerOrderId]
            );

            if (is_array($partnerOrder)) {
                $partnerId = $partnerId > 0 ? $partnerId : (int) ($partnerOrder['partner_id'] ?? 0);
                $acceptedOrderId = (int) ($partnerOrder['accepted_order_id'] ?? 0);

                if ($acceptedOrderId > 0 && $orderId <= 0) {
                    $acceptedOrder = $this->db->fetch(
                        'SELECT client_id, partner_id, assigned_user_id
                         FROM orders WHERE id = :id LIMIT 1',
                        ['id' => $acceptedOrderId]
                    );
                    if (is_array($acceptedOrder)) {
                        $clientId = (int) ($acceptedOrder['client_id'] ?? 0);
                        $partnerId = $partnerId > 0 ? $partnerId : (int) ($acceptedOrder['partner_id'] ?? 0);
                        $executiveUserId = (int) ($acceptedOrder['assigned_user_id'] ?? 0);
                    }
                }
            }
        }

        $contextTypes = $this->contextAudience($eventKey, $payload);

        if ($clientId > 0 && in_array('client', $contextTypes, true)) {
            foreach ($this->usersByColumn('client_id', $clientId, 'client') as $user) {
                $this->addResolvedUser($recipients, $user);
            }
        }

        if ($partnerId > 0 && in_array('partner', $contextTypes, true)) {
            foreach ($this->usersByColumn('partner_id', $partnerId, 'partner') as $user) {
                $this->addResolvedUser($recipients, $user);
            }
        }

        if ($executiveUserId > 0 && in_array('executive', $contextTypes, true)) {
            $this->addUser($recipients, $executiveUserId, 'executive');
        }

        return $recipients;
    }

    /**
     * Send a stored notification to every active device belonging to a user.
     */
    public function sendToUserDevices(int $userId, array $notification): array
    {
        if ($userId <= 0) {
            return ['sent' => 0, 'failed' => 0, 'skipped' => 1, 'devices' => []];
        }

        $tokens = $this->db->fetchAll(
            'SELECT id, fcm_token, device_key, platform
             FROM user_push_tokens
             WHERE user_id = :user_id
               AND is_active = 1
             ORDER BY last_seen_at DESC, id DESC',
            ['user_id' => $userId]
        ) ?: [];

        if ($tokens === []) {
            return ['sent' => 0, 'failed' => 0, 'skipped' => 1, 'devices' => []];
        }

        $credentials = $this->firebaseCredentials();
        if ($credentials === null) {
            return [
                'sent' => 0,
                'failed' => 0,
                'skipped' => count($tokens),
                'reason' => 'Firebase service account is not configured.',
                'devices' => [],
            ];
        }

        $accessToken = $this->firebaseAccessToken($credentials);
        if ($accessToken === null) {
            return [
                'sent' => 0,
                'failed' => count($tokens),
                'skipped' => 0,
                'reason' => 'Unable to obtain Firebase OAuth access token.',
                'devices' => [],
            ];
        }

        $projectId = trim((string) ($credentials['project_id'] ?? getenv('FIREBASE_PROJECT_ID') ?: ''));
        if ($projectId === '') {
            return [
                'sent' => 0,
                'failed' => count($tokens),
                'skipped' => 0,
                'reason' => 'Firebase project_id is missing.',
                'devices' => [],
            ];
        }

        $summary = ['sent' => 0, 'failed' => 0, 'skipped' => 0, 'devices' => []];

        foreach ($tokens as $tokenRow) {
            $token = trim((string) ($tokenRow['fcm_token'] ?? ''));
            if ($token === '') {
                $summary['skipped']++;
                continue;
            }

            $response = $this->sendFcmHttpV1($projectId, $accessToken, $token, $notification);
            $summary['devices'][] = [
                'device_key' => (string) ($tokenRow['device_key'] ?? ''),
                'platform' => (string) ($tokenRow['platform'] ?? ''),
                'ok' => (bool) ($response['ok'] ?? false),
                'status' => (int) ($response['status'] ?? 0),
            ];

            if ((bool) ($response['ok'] ?? false)) {
                $summary['sent']++;
                $this->logDelivery('push', 'user:' . $userId . ':' . (string) ($tokenRow['device_key'] ?? 'device'), (string) ($notification['event_key'] ?? 'general'), $notification, 'sent', (string) ($response['body'] ?? ''));
                continue;
            }

            $summary['failed']++;
            $body = (string) ($response['body'] ?? '');
            $this->logDelivery('push', 'user:' . $userId . ':' . (string) ($tokenRow['device_key'] ?? 'device'), (string) ($notification['event_key'] ?? 'general'), $notification, 'failed', $body);

            if ($this->isInvalidFcmToken($body, (int) ($response['status'] ?? 0))) {
                $this->db->execute(
                    'UPDATE user_push_tokens
                     SET is_active = 0, updated_at = :updated_at
                     WHERE id = :id',
                    [
                        'updated_at' => date('Y-m-d H:i:s'),
                        'id' => (int) ($tokenRow['id'] ?? 0),
                    ]
                );
            }
        }

        return $summary;
    }

    private function hydrateContext(array $payload): array
    {
        $context = $payload;
        $orderId = (int) ($payload['order_id'] ?? 0);
        $partnerOrderId = (int) ($payload['partner_order_id'] ?? 0);

        if ($orderId > 0) {
            try {
                $row = $this->db->fetch(
                    'SELECT id, order_no, client_id, partner_id, assigned_user_id, status, payment_status
                     FROM orders WHERE id = :id LIMIT 1',
                    ['id' => $orderId]
                );
                if (is_array($row)) {
                    $context['order_id'] = $orderId;
                    $context['order_no'] = (string) ($payload['order_no'] ?? $row['order_no'] ?? '');
                    $context['client_id'] = (int) ($payload['client_id'] ?? $row['client_id'] ?? 0);
                    $context['partner_id'] = (int) ($payload['partner_id'] ?? $row['partner_id'] ?? 0);
                    $context['executive_user_id'] = (int) ($payload['executive_user_id'] ?? $row['assigned_user_id'] ?? 0);
                    $context['status_label'] = (string) ($payload['status_label'] ?? $row['status'] ?? '');
                    $context['source_table'] = (string) ($payload['source_table'] ?? 'orders');
                    $context['source_id'] = (int) ($payload['source_id'] ?? $orderId);
                }
            } catch (Throwable $e) {
                error_log('RolePushService order context lookup failed: ' . $e->getMessage());
            }
        }

        if ($partnerOrderId > 0) {
            try {
                $row = $this->db->fetch(
                    'SELECT id, order_no, partner_id, accepted_order_id, order_status, payment_status
                     FROM partner_orders WHERE id = :id LIMIT 1',
                    ['id' => $partnerOrderId]
                );
                if (is_array($row)) {
                    $context['partner_order_id'] = $partnerOrderId;
                    $context['order_no'] = (string) ($payload['order_no'] ?? $row['order_no'] ?? '');
                    $context['partner_id'] = (int) ($payload['partner_id'] ?? $row['partner_id'] ?? 0);
                    $context['status_label'] = (string) ($payload['status_label'] ?? $row['order_status'] ?? '');
                    $context['source_table'] = (string) ($payload['source_table'] ?? 'partner_orders');
                    $context['source_id'] = (int) ($payload['source_id'] ?? $partnerOrderId);
                }
            } catch (Throwable $e) {
                error_log('RolePushService partner order context lookup failed: ' . $e->getMessage());
            }
        }

        return $context;
    }

    private function extractUserIds(array $payload): array
    {
        $ids = [];
        foreach (['recipient_user_id', 'user_id', 'executive_user_id'] as $key) {
            $value = (int) ($payload[$key] ?? 0);
            if ($value > 0) {
                $ids[$value] = $value;
            }
        }

        foreach (['recipient_user_ids', 'user_ids'] as $key) {
            $values = $payload[$key] ?? [];
            if (is_string($values)) {
                $values = preg_split('/[\s,]+/', $values, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            }
            if (!is_array($values)) {
                continue;
            }
            foreach ($values as $value) {
                $id = (int) $value;
                if ($id > 0) {
                    $ids[$id] = $id;
                }
            }
        }

        return array_values($ids);
    }

    private function extractRoleSlugs(array $payload): array
    {
        $roles = $payload['roles'] ?? $payload['role_slugs'] ?? $payload['audience'] ?? [];
        if (is_string($roles)) {
            $roles = preg_split('/[\s,]+/', $roles, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }
        if (!is_array($roles)) {
            return [];
        }

        $clean = [];
        foreach ($roles as $role) {
            $role = $this->normalizeRole((string) $role);
            if ($role !== '') {
                $clean[$role] = $role;
            }
        }
        return array_values($clean);
    }

    private function defaultAudience(string $eventKey): array
    {
        $eventKey = strtolower($eventKey);

        if (str_starts_with($eventKey, 'partner.')) {
            return ['admin', 'manager'];
        }

        return match (true) {
            str_contains($eventKey, 'created'),
            str_contains($eventKey, 'submitted'),
            str_contains($eventKey, 'reuploaded'),
            str_contains($eventKey, 'uploaded') => ['admin', 'manager'],
            str_contains($eventKey, 'assigned') => ['admin', 'manager'],
            str_contains($eventKey, 'critical'),
            str_contains($eventKey, 'overdue') => ['admin', 'manager'],
            default => [],
        };
    }

    private function contextAudience(string $eventKey, array $payload): array
    {
        if (isset($payload['context_audience'])) {
            $value = $payload['context_audience'];
            if (is_string($value)) {
                $value = preg_split('/[\s,]+/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            }
            if (is_array($value)) {
                return array_values(array_unique(array_filter(array_map(
                    fn (mixed $item): string => $this->normalizeRole((string) $item),
                    $value
                ))));
            }
        }

        $eventKey = strtolower($eventKey);

        return match (true) {
            str_contains($eventKey, 'document.wrong') => ['client', 'partner', 'executive'],
            str_contains($eventKey, 'document.reuploaded'),
            str_contains($eventKey, 'document.uploaded') => ['partner', 'executive'],
            str_contains($eventKey, 'assigned') => ['executive'],
            str_contains($eventKey, 'payment.submitted') => ['partner', 'executive'],
            str_contains($eventKey, 'payment.verified'),
            str_contains($eventKey, 'payment.paid') => ['client', 'partner', 'executive'],
            str_contains($eventKey, 'status'),
            str_contains($eventKey, 'approved'),
            str_contains($eventKey, 'completed'),
            str_contains($eventKey, 'rejected'),
            str_contains($eventKey, 'clarification') => ['client', 'partner', 'executive'],
            str_contains($eventKey, 'partner.order') => ['partner'],
            str_contains($eventKey, 'order.created') => ['client', 'partner'],
            default => ['client', 'partner', 'executive'],
        };
    }

    private function usersForRole(string $roleSlug): array
    {
        $roleSlug = $this->normalizeRole($roleSlug);
        if ($roleSlug === '') {
            return [];
        }

        if (isset($this->roleCache[$roleSlug])) {
            return $this->roleCache[$roleSlug];
        }

        $aliases = match ($roleSlug) {
            'admin' => ['admin', 'administrator'],
            'manager' => ['manager'],
            'executive' => ['executive', 'staff'],
            'partner' => ['partner', 'partners'],
            'client' => ['client', 'customer'],
            default => [$roleSlug],
        };

        $params = [];
        $slugPlaceholders = [];
        $namePlaceholders = [];
        foreach ($aliases as $index => $alias) {
            $slugKey = 'role_slug_' . $index;
            $nameKey = 'role_name_' . $index;
            $params[$slugKey] = $alias;
            $params[$nameKey] = $alias;
            $slugPlaceholders[] = ':' . $slugKey;
            $namePlaceholders[] = ':' . $nameKey;
        }

        $rows = $this->db->fetchAll(
            'SELECT DISTINCT
                u.id,
                u.client_id,
                u.partner_id,
                r.id AS role_id,
                r.name AS role_name,
                r.slug AS role_slug
             FROM users u
             INNER JOIN user_roles ur ON ur.user_id = u.id
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE COALESCE(u.is_active, 1) = 1
               AND (
                    LOWER(COALESCE(r.slug, "")) IN (' . implode(', ', $slugPlaceholders) . ')
                    OR LOWER(REPLACE(COALESCE(r.name, ""), " ", "-")) IN (' . implode(', ', $namePlaceholders) . ')
               )
             ORDER BY u.id ASC',
            $params
        ) ?: [];

        foreach ($rows as &$row) {
            $row['recipient_type'] = $roleSlug;
        }
        unset($row);

        $this->roleCache[$roleSlug] = $rows;
        return $rows;
    }

    private function usersByColumn(string $column, int $value, string $roleSlug): array
    {
        if (!in_array($column, ['client_id', 'partner_id'], true) || $value <= 0) {
            return [];
        }

        return $this->db->fetchAll(
            'SELECT
                u.id,
                u.client_id,
                u.partner_id,
                r.id AS role_id,
                r.name AS role_name,
                r.slug AS role_slug,
                :recipient_type AS recipient_type
             FROM users u
             LEFT JOIN user_roles ur ON ur.user_id = u.id
             LEFT JOIN roles r ON r.id = ur.role_id
             WHERE u.' . $column . ' = :value
               AND COALESCE(u.is_active, 1) = 1
             ORDER BY
                CASE WHEN LOWER(COALESCE(r.slug, "")) = :preferred_role THEN 0 ELSE 1 END,
                u.id ASC',
            [
                'recipient_type' => $roleSlug,
                'value' => $value,
                'preferred_role' => $roleSlug,
            ]
        ) ?: [];
    }

    private function addUser(array &$recipients, int $userId, string $fallbackType = 'system'): void
    {
        if ($userId <= 0 || isset($recipients[$userId])) {
            return;
        }

        $row = $this->db->fetch(
            'SELECT
                u.id,
                u.client_id,
                u.partner_id,
                r.id AS role_id,
                r.name AS role_name,
                r.slug AS role_slug
             FROM users u
             LEFT JOIN user_roles ur ON ur.user_id = u.id
             LEFT JOIN roles r ON r.id = ur.role_id
             WHERE u.id = :id
               AND COALESCE(u.is_active, 1) = 1
             ORDER BY
                CASE LOWER(COALESCE(r.slug, ""))
                    WHEN "admin" THEN 1
                    WHEN "manager" THEN 2
                    WHEN "executive" THEN 3
                    WHEN "partner" THEN 4
                    WHEN "client" THEN 5
                    ELSE 9
                END
             LIMIT 1',
            ['id' => $userId]
        );

        if (!is_array($row)) {
            return;
        }

        $row['recipient_type'] = $this->recipientTypeFromRole((string) ($row['role_slug'] ?? $fallbackType), $fallbackType);
        $this->addResolvedUser($recipients, $row);
    }

    private function addResolvedUser(array &$recipients, array $user): void
    {
        $userId = (int) ($user['id'] ?? 0);
        if ($userId <= 0) {
            return;
        }

        $candidateType = $this->recipientTypeFromRole(
            (string) ($user['role_slug'] ?? $user['recipient_type'] ?? ''),
            (string) ($user['recipient_type'] ?? 'system')
        );

        if (!isset($recipients[$userId])) {
            $user['recipient_type'] = $candidateType;
            $recipients[$userId] = $user;
            return;
        }

        // Prefer the most privileged/precise role when a multi-role user is matched repeatedly.
        $rank = ['admin' => 1, 'manager' => 2, 'executive' => 3, 'partner' => 4, 'client' => 5, 'system' => 9];
        $currentType = (string) ($recipients[$userId]['recipient_type'] ?? 'system');
        if (($rank[$candidateType] ?? 9) < ($rank[$currentType] ?? 9)) {
            $user['recipient_type'] = $candidateType;
            $recipients[$userId] = $user;
        }
    }

    private function findDuplicate(int $userId, string $eventKey, string $stateHash, string $sourceTable, int $sourceId): ?array
    {
        $sql = 'SELECT id, uid
                FROM notification_manager
                WHERE recipient_user_id = :recipient_user_id
                  AND event_key = :event_key
                  AND state_hash = :state_hash';
        $params = [
            'recipient_user_id' => $userId,
            'event_key' => $eventKey,
            'state_hash' => $stateHash,
        ];

        if ($sourceTable !== '') {
            $sql .= ' AND source_table = :source_table';
            $params['source_table'] = $sourceTable;
        }
        if ($sourceId > 0) {
            $sql .= ' AND source_id = :source_id';
            $params['source_id'] = $sourceId;
        }

        $sql .= ' AND created_at >= DATE_SUB(NOW(), INTERVAL 60 SECOND) LIMIT 1';
        $row = $this->db->fetch($sql, $params);
        return is_array($row) && $row !== [] ? $row : null;
    }

    private function mirrorLegacyNotification(
        string $uid,
        int $userId,
        string $title,
        string $message,
        string $kind,
        string $statusLabel,
        string $severity,
        string $url,
        array $data,
        string $now
    ): void {
        try {
            $this->db->execute(
                'INSERT INTO notifications (
                    uid, user_id, title, message, body, kind, status_label,
                    severity, url, data_json, payload, is_read, read_at,
                    created_at, updated_at
                 ) VALUES (
                    :uid, :user_id, :title, :message, :body, :kind, :status_label,
                    :severity, :url, :data_json, :payload, 0, NULL,
                    :created_at, :updated_at
                 )',
                [
                    'uid' => $uid,
                    'user_id' => $userId,
                    'title' => $title,
                    'message' => $message !== '' ? $message : null,
                    'body' => $message !== '' ? $message : null,
                    'kind' => $kind,
                    'status_label' => $statusLabel !== '' ? $statusLabel : null,
                    'severity' => $severity,
                    'url' => $url !== '' ? $url : null,
                    'data_json' => $this->json($data),
                    'payload' => $this->json($data),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        } catch (Throwable $e) {
            // Compatibility mirror must never block the authoritative notification.
            error_log('Legacy notification mirror skipped: ' . $e->getMessage());
        }
    }

    private function firebaseCredentials(): ?array
    {
        $inline = trim((string) (getenv('FIREBASE_SERVICE_ACCOUNT_JSON') ?: ''));
        if ($inline !== '') {
            $decoded = json_decode($inline, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $path = trim((string) (
            getenv('FIREBASE_SERVICE_ACCOUNT_PATH')
            ?: getenv('GOOGLE_APPLICATION_CREDENTIALS')
            ?: ''
        ));

        if ($path !== '' && is_file($path) && is_readable($path)) {
            $decoded = json_decode((string) file_get_contents($path), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private function firebaseAccessToken(array $credentials): ?string
    {
        $cachePath = dirname(__DIR__, 2) . '/storage/cache/fcm-oauth-token.json';
        $credentialClientEmail = trim((string) ($credentials['client_email'] ?? ''));
        $credentialProjectId = trim((string) ($credentials['project_id'] ?? ''));
        if (is_file($cachePath)) {
            $cache = json_decode((string) file_get_contents($cachePath), true);
            if (is_array($cache)
                && !empty($cache['access_token'])
                && (string) ($cache['client_email'] ?? '') === $credentialClientEmail
                && (string) ($cache['project_id'] ?? '') === $credentialProjectId
                && (int) ($cache['expires_at'] ?? 0) > time() + 120
            ) {
                return (string) $cache['access_token'];
            }
        }

        $clientEmail = $credentialClientEmail;
        $privateKey = (string) ($credentials['private_key'] ?? '');
        $tokenUri = trim((string) ($credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token'));

        if ($clientEmail === '' || $privateKey === '' || !function_exists('openssl_sign')) {
            return null;
        }

        $now = time();
        $header = $this->base64Url($this->json(['alg' => 'RS256', 'typ' => 'JWT']));
        $claims = $this->base64Url($this->json([
            'iss' => $clientEmail,
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => $tokenUri,
            'iat' => $now,
            'exp' => $now + 3600,
        ]));
        $unsigned = $header . '.' . $claims;
        $signature = '';

        if (!openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            return null;
        }

        $assertion = $unsigned . '.' . $this->base64Url($signature);
        $response = $this->httpRequest(
            $tokenUri,
            'POST',
            http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ]),
            ['Content-Type: application/x-www-form-urlencoded']
        );

        if (!(bool) ($response['ok'] ?? false)) {
            error_log('Firebase OAuth token request failed: ' . (string) ($response['body'] ?? ''));
            return null;
        }

        $decoded = json_decode((string) ($response['body'] ?? ''), true);
        $accessToken = is_array($decoded) ? trim((string) ($decoded['access_token'] ?? '')) : '';
        if ($accessToken === '') {
            return null;
        }

        $expiresIn = max(300, (int) ($decoded['expires_in'] ?? 3600));
        $dir = dirname($cachePath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents($cachePath, $this->json([
            'access_token' => $accessToken,
            'client_email' => $clientEmail,
            'project_id' => $credentialProjectId,
            'expires_at' => time() + $expiresIn,
        ]), LOCK_EX);
        @chmod($cachePath, 0600);

        return $accessToken;
    }

    private function sendFcmHttpV1(string $projectId, string $accessToken, string $token, array $notification): array
    {
        $url = 'https://fcm.googleapis.com/v1/projects/' . rawurlencode($projectId) . '/messages:send';
        $targetUrl = trim((string) ($notification['url'] ?? ''));
        $data = $notification['data'] ?? [];
        if (!is_array($data)) {
            $data = [];
        }

        $fcmData = [];
        foreach (array_merge($data, [
            'uid' => (string) ($notification['uid'] ?? ''),
            'title' => (string) ($notification['title'] ?? 'Tax Saathi'),
            'message' => (string) ($notification['message'] ?? ''),
            'event_key' => (string) ($notification['event_key'] ?? 'general'),
            'severity' => (string) ($notification['severity'] ?? 'normal'),
            'icon' => (string) (getenv('FIREBASE_NOTIFICATION_ICON') ?: '/favicon.ico'),
            'badge' => (string) (getenv('FIREBASE_NOTIFICATION_BADGE') ?: '/favicon.ico'),
            'url' => $targetUrl,
        ]) as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $fcmData[(string) $key] = (string) ($value ?? '');
            } else {
                $fcmData[(string) $key] = $this->json($value);
            }
        }

        // Data-only payload: the service worker renders exactly one background notification.
        $message = [
            'token' => $token,
            'data' => $fcmData,
            'webpush' => [
                'headers' => ['Urgency' => $this->fcmUrgency((string) ($notification['severity'] ?? 'normal'))],
            ],
        ];

        if ($targetUrl !== '') {
            $message['webpush']['fcm_options'] = ['link' => $targetUrl];
        }

        return $this->httpRequest(
            $url,
            'POST',
            $this->json(['message' => $message]),
            [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json; charset=UTF-8',
            ]
        );
    }

    private function httpRequest(string $url, string $method, string $body, array $headers): array
    {
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'status' => 0, 'body' => 'cURL extension is unavailable.'];
        }

        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        $responseBody = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($responseBody === false) {
            return ['ok' => false, 'status' => $status, 'body' => $error];
        }

        return [
            'ok' => $status >= 200 && $status < 300,
            'status' => $status,
            'body' => (string) $responseBody,
        ];
    }

    private function isInvalidFcmToken(string $body, int $status): bool
    {
        if ($status === 404 || $status === 410) {
            return true;
        }
        $upper = strtoupper($body);
        return str_contains($upper, 'UNREGISTERED')
            || str_contains($upper, 'REGISTRATION-TOKEN-NOT-REGISTERED')
            || str_contains($upper, 'INVALID_ARGUMENT');
    }

    private function logDelivery(string $channel, string $recipient, string $templateKey, array $payload, string $status, string $responseBody): void
    {
        try {
            $this->db->execute(
                'INSERT INTO notification_logs (
                    channel, recipient, template_key, payload_json,
                    status, response_body, created_at
                 ) VALUES (
                    :channel, :recipient, :template_key, :payload_json,
                    :status, :response_body, :created_at
                 )',
                [
                    'channel' => substr($channel, 0, 30),
                    'recipient' => substr($recipient, 0, 190),
                    'template_key' => substr($templateKey, 0, 120),
                    'payload_json' => $this->json($payload),
                    'status' => substr($status, 0, 40),
                    'response_body' => $responseBody,
                    'created_at' => date('Y-m-d H:i:s'),
                ]
            );
        } catch (Throwable $e) {
            error_log('Push delivery log failed: ' . $e->getMessage());
        }
    }

    private function defaultTitle(string $eventKey, array $context): string
    {
        $orderNo = trim((string) ($context['order_no'] ?? ''));
        $suffix = $orderNo !== '' ? ' · ' . $orderNo : '';
        $label = ucwords(str_replace(['.', '_', '-'], ' ', $eventKey));
        return ($label !== '' ? $label : 'Notification') . $suffix;
    }

    private function defaultMessage(string $eventKey, array $context): string
    {
        $orderNo = trim((string) ($context['order_no'] ?? ''));
        $status = trim((string) ($context['status_label'] ?? ''));
        $message = 'There is a new update';
        if ($orderNo !== '') {
            $message .= ' for order ' . $orderNo;
        }
        if ($status !== '') {
            $message .= '. Current status: ' . ucwords(str_replace('_', ' ', $status));
        }
        return $message . '.';
    }

    private function defaultSeverity(string $eventKey): string
    {
        $eventKey = strtolower($eventKey);
        return match (true) {
            str_contains($eventKey, 'wrong'),
            str_contains($eventKey, 'rejected'),
            str_contains($eventKey, 'failed'),
            str_contains($eventKey, 'overdue') => 'warning',
            str_contains($eventKey, 'completed'),
            str_contains($eventKey, 'approved'),
            str_contains($eventKey, 'verified'),
            str_contains($eventKey, 'paid') => 'success',
            str_contains($eventKey, 'critical') => 'critical',
            default => 'info',
        };
    }

    private function urlForRecipient(string $recipientType, array $context): string
    {
        $orderId = (int) ($context['order_id'] ?? 0);
        $partnerOrderId = (int) ($context['partner_order_id'] ?? 0);

        if ($partnerOrderId > 0) {
            return match ($recipientType) {
                'admin', 'manager', 'executive' => $this->appUrl('admin/partner-orders/show?id=' . $partnerOrderId),
                'partner' => $this->appUrl('partner/orders/view?id=' . $partnerOrderId),
                default => $this->appUrl('notifications'),
            };
        }

        if ($orderId > 0) {
            return match ($recipientType) {
                'admin', 'manager', 'executive' => $this->appUrl('admin/orders/show?id=' . $orderId),
                'partner' => $this->appUrl('partner/orders'),
                'client' => $this->appUrl('client/orders/view?id=' . $orderId),
                default => $this->appUrl('notifications'),
            };
        }

        return $this->appUrl('notifications');
    }

    private function defaultUrl(array $context): string
    {
        $orderId = (int) ($context['order_id'] ?? 0);
        $partnerOrderId = (int) ($context['partner_order_id'] ?? 0);
        if ($partnerOrderId > 0) {
            return $this->appUrl('partner/orders/view?id=' . $partnerOrderId);
        }
        if ($orderId > 0) {
            return $this->appUrl('client/orders/view?id=' . $orderId);
        }
        return $this->appUrl('notifications');
    }

    private function appUrl(string $path): string
    {
        return function_exists('base_url')
            ? base_url(ltrim($path, '/'))
            : '/' . ltrim($path, '/');
    }

    private function stateHash(string $eventKey, int $userId, array $state): string
    {
        ksort($state);
        return sha1($eventKey . '|' . $userId . '|' . $this->json($state));
    }

    private function newUid(): string
    {
        try {
            return 'pn_' . date('YmdHis') . '_' . bin2hex(random_bytes(10));
        } catch (Throwable $e) {
            return 'pn_' . date('YmdHis') . '_' . str_replace('.', '', uniqid('', true));
        }
    }

    private function normalizeRecipientType(string $type): string
    {
        $type = $this->normalizeRole($type);
        return in_array($type, self::ALLOWED_RECIPIENT_TYPES, true) ? $type : 'system';
    }

    private function recipientTypeFromRole(string $role, string $fallback = 'system'): string
    {
        $role = $this->normalizeRole($role);
        return match ($role) {
            'administrator' => 'admin',
            'staff' => 'executive',
            'partners' => 'partner',
            'customer' => 'client',
            'admin', 'manager', 'executive', 'partner', 'client' => $role,
            default => $this->normalizeRecipientType($fallback),
        };
    }

    private function normalizeRole(string $role): string
    {
        $role = strtolower(trim($role));
        $role = str_replace([' ', '_'], '-', $role);
        return trim((string) preg_replace('/[^a-z0-9-]/', '', $role), '-');
    }

    private function cleanKey(string $key, string $fallback): string
    {
        $key = strtolower(trim($key));
        $key = str_replace([' ', '/'], ['.', '.'], $key);
        $key = preg_replace('/[^a-z0-9._-]+/', '', $key) ?: '';
        return trim($key, '._-') !== '' ? trim($key, '._-') : $fallback;
    }

    private function fcmUrgency(string $severity): string
    {
        return in_array(strtolower($severity), ['high', 'critical', 'warning'], true) ? 'high' : 'normal';
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function json(mixed $value): string
    {
        return (string) json_encode(
            $value,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );
    }
}
