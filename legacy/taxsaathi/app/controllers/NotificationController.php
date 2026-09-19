<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\NotificationTemplate;
use App\Models\Setting;

final class NotificationController extends Controller
{
    private const ADMIN_ROLE_IDS = [1];
    private const MANAGER_ROLE_IDS = [2];
    private const EXECUTIVE_ROLE_IDS = [3];
    private const PARTNER_ROLE_IDS = [4];
    private const CLIENT_ROLE_IDS = [5];

    private const ADMIN_ROLE_KEYS = ['admin', 'super-admin', 'superadmin', 'administrator'];
    private const MANAGER_ROLE_KEYS = ['manager'];
    private const EXECUTIVE_ROLE_KEYS = ['executive', 'staff'];
    private const PARTNER_ROLE_KEYS = ['partner', 'partners'];
    private const CLIENT_ROLE_KEYS = ['client', 'user', 'customer'];

    public function index(): void
    {
        require_permission('notifications.manage');

        $settingKeys = [
            'email_enabled',
            'email_from',
            'notify_default_email',
            'whatsapp_enabled',
            'whatsapp_api_url',
            'whatsapp_api_token',
            'notify_default_phone',
        ];

        $logs = $this->db()->fetchAll(
            'SELECT id, user_id, order_id, action, description, meta_json, created_at
             FROM activity_logs
             ORDER BY id DESC
             LIMIT 30'
        ) ?: [];

        $this->view('admin/notifications/index', [
            'title' => 'Notifications – Tax Saathi',
            'settings' => Setting::many($settingKeys),
            'templates' => NotificationTemplate::all('event_key ASC'),
            'logs' => $logs,
        ], 'layouts/dashboard');
    }

    public function saveSettings(): void
    {
        require_permission('notifications.manage');
        verify_csrf();

        foreach ($_POST as $key => $value) {
            if ($key === '_csrf') {
                continue;
            }

            Setting::set((string) $key, trim((string) $value));
        }

        flash('success', 'Notification channel settings updated.');
        redirect('admin/notifications');
    }

    public function storeTemplate(): void
    {
        require_permission('notifications.manage');
        verify_csrf();

        NotificationTemplate::insert([
            'event_key' => trim((string) input('event_key')),
            'label' => trim((string) input('label')),
            'subject' => trim((string) input('subject')),
            'email_body' => trim((string) input('email_body')),
            'whatsapp_body' => trim((string) input('whatsapp_body')),
            'is_enabled' => (int) input('is_enabled', 1),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        flash('success', 'Notification template saved.');
        redirect('admin/notifications');
    }

    public function deleteTemplate(): void
    {
        require_permission('notifications.manage');
        verify_csrf();

        NotificationTemplate::delete((int) input('id'));

        flash('success', 'Notification template removed.');
        redirect('admin/notifications');
    }

    /**
     * Creates an activity_logs row for the currently authenticated user.
     * This is useful for testing the toast inbox without any other source.
     */
    public function sendTest(): void
    {
        require_permission('notifications.manage');
        verify_csrf();

        $context = $this->notificationContext();
        $userId = (int) ($context['user_id'] ?? 0);
        $user = function_exists('auth_user') ? (auth_user() ?: []) : [];

        $this->writeActivity(
            $userId > 0 ? $userId : null,
            null,
            'system.notification.test',
            'Toast notification test created by ' . trim((string) ($user['name'] ?? 'Admin')) . '.',
            ['source' => 'activity_logs', 'toast_only' => true]
        );

        flash('success', 'Test activity created.');
        redirect('admin/notifications');
    }

    /**
     * Diagnostic endpoint compatible with existing notifications/health routes.
     */
    public function health(): void
    {
        require_auth();

        try {
            $context = $this->notificationContext();
            $count = $this->visibleCount($context);

            $this->jsonResponse([
                'ok' => true,
                'source_table' => 'activity_logs',
                'delivery' => 'activity_logs_only_toast',
                'current_user_id' => (int) ($context['user_id'] ?? 0),
                'role_ids' => $context['role_ids'] ?? [],
                'role_keys' => $context['role_keys'] ?? [],
                'scope_mode' => (string) ($context['scope_mode'] ?? 'none'),
                'matched_count' => $count,
            ]);
        } catch (\Throwable $e) {
            error_log('Activity notification health failed: ' . $e->getMessage());

            $this->jsonResponse([
                'ok' => false,
                'message' => $e->getMessage(),
                'source_table' => 'activity_logs',
            ], 500);
        }
    }

    /**
     * Reads notification content only from activity_logs.
     * Admin/Manager: every row.
     * Executive/Partner/Client: only rows where activity_logs.user_id matches
     * the authenticated session user ID.
     */
    public function poll(): void
    {
        require_auth();

        try {
            $context = $this->notificationContext();
            $userId = (int) ($context['user_id'] ?? 0);

            if ($userId <= 0) {
                $this->jsonResponse([
                    'ok' => false,
                    'message' => 'Authenticated session user ID is missing.',
                    'source_table' => 'activity_logs',
                ], 401);
            }

            $limit = max(5, min(50, (int) ($_GET['limit'] ?? 30)));
            $sinceId = max(0, (int) ($_GET['since_id'] ?? 0));

            $rows = $this->visibleActivities($context, $limit, 0, false);
            $newRows = $sinceId > 0
                ? $this->visibleActivities($context, 20, $sinceId, true)
                : [];

            $readIds = $this->sessionReadIds($userId);

            $items = array_map(
                fn (array $row): array => $this->presentActivity($row, $context, $readIds),
                $rows
            );

            $newItems = array_map(
                fn (array $row): array => $this->presentActivity($row, $context, $readIds),
                $newRows
            );

            $latestId = 0;
            foreach (array_merge($rows, $newRows) as $row) {
                $latestId = max($latestId, (int) ($row['id'] ?? 0));
            }

            $this->jsonResponse([
                'ok' => true,
                'items' => $items,
                'new_items' => $newItems,
                'unread_count' => $this->unreadCount($context, $readIds),
                'latest_id' => $latestId,
                'matched_count' => $this->visibleCount($context),
                'current_user_id' => $userId,
                'role_ids' => $context['role_ids'] ?? [],
                'role_keys' => $context['role_keys'] ?? [],
                'poll_interval_ms' => 12000,
                'delivery' => 'activity_logs_only_toast',
                'source_table' => 'activity_logs',
                'scope_mode' => (string) ($context['scope_mode'] ?? 'none'),
            ]);
        } catch (\Throwable $e) {
            error_log('Activity notification poll failed: ' . $e->getMessage());

            $this->jsonResponse([
                'ok' => false,
                'message' => $e->getMessage(),
                'source_table' => 'activity_logs',
                'delivery' => 'activity_logs_only_toast',
            ], 500);
        }
    }

    public function markRead(): void
    {
        require_auth();
        verify_csrf();

        try {
            $context = $this->notificationContext();
            $userId = (int) ($context['user_id'] ?? 0);
            $activityId = (int) input('id');

            if ($activityId <= 0 || !$this->activityIsVisible($activityId, $context)) {
                $this->jsonResponse([
                    'ok' => false,
                    'message' => 'Notification not found or access denied.',
                ], 404);
            }

            $readIds = $this->sessionReadIds($userId);
            $readIds[$activityId] = time();
            $this->saveSessionReadIds($userId, $readIds);

            $this->jsonResponse([
                'ok' => true,
                'id' => $activityId,
                'unread_count' => $this->unreadCount($context, $readIds),
            ]);
        } catch (\Throwable $e) {
            $this->jsonResponse([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function markUnread(): void
    {
        require_auth();
        verify_csrf();

        try {
            $context = $this->notificationContext();
            $userId = (int) ($context['user_id'] ?? 0);
            $activityId = (int) input('id');

            if ($activityId <= 0 || !$this->activityIsVisible($activityId, $context)) {
                $this->jsonResponse([
                    'ok' => false,
                    'message' => 'Notification not found or access denied.',
                ], 404);
            }

            $readIds = $this->sessionReadIds($userId);
            unset($readIds[$activityId]);
            $this->saveSessionReadIds($userId, $readIds);

            $this->jsonResponse([
                'ok' => true,
                'id' => $activityId,
                'unread_count' => $this->unreadCount($context, $readIds),
            ]);
        } catch (\Throwable $e) {
            $this->jsonResponse([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function markAllRead(): void
    {
        require_auth();
        verify_csrf();

        try {
            $context = $this->notificationContext();
            $userId = (int) ($context['user_id'] ?? 0);
            $rows = $this->visibleActivities($context, 100, 0, false);
            $readIds = $this->sessionReadIds($userId);

            foreach ($rows as $row) {
                $activityId = (int) ($row['id'] ?? 0);
                if ($activityId > 0) {
                    $readIds[$activityId] = time();
                }
            }

            $this->saveSessionReadIds($userId, $readIds);

            $this->jsonResponse([
                'ok' => true,
                'unread_count' => $this->unreadCount($context, $readIds),
            ]);
        } catch (\Throwable $e) {
            $this->jsonResponse([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    private function db(): object
    {
        return app('db');
    }

    private function jsonResponse(array $payload, int $statusCode = 200): never
    {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        http_response_code($statusCode);
        header_remove('Location');
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function authenticatedUserId(): int
    {
        $authUser = function_exists('auth_user') ? (auth_user() ?: []) : [];

        $userId = (int) (
            $authUser['id']
            ?? $_SESSION['user_id']
            ?? $_SESSION['auth_user']['id']
            ?? $_SESSION['user']['id']
            ?? 0
        );

        if ($userId <= 0 && function_exists('rbac_current_user_id')) {
            $userId = (int) rbac_current_user_id();
        }

        return $userId;
    }

    private function notificationContext(): array
    {
        $userId = $this->authenticatedUserId();

        if ($userId <= 0) {
            throw new \RuntimeException('Unable to resolve the logged-in session user ID.');
        }

        $roles = $this->db()->fetchAll(
            'SELECT r.id, r.slug, r.name
             FROM user_roles ur
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE ur.user_id = :user_id
             ORDER BY r.id ASC',
            ['user_id' => $userId]
        ) ?: [];

        $roleIds = [];
        $roleKeys = [];

        foreach ($roles as $role) {
            $roleId = (int) ($role['id'] ?? 0);
            if ($roleId > 0) {
                $roleIds[$roleId] = $roleId;
            }

            foreach (['slug', 'name'] as $field) {
                $key = $this->normalizeRoleKey((string) ($role[$field] ?? ''));
                if ($key !== '') {
                    $roleKeys[$key] = $key;
                }
            }
        }

        $roleIds = array_values($roleIds);
        $roleKeys = array_values($roleKeys);

        $isAdmin = $this->hasAnyRole($roleIds, $roleKeys, self::ADMIN_ROLE_IDS, self::ADMIN_ROLE_KEYS);
        $isManager = $this->hasAnyRole($roleIds, $roleKeys, self::MANAGER_ROLE_IDS, self::MANAGER_ROLE_KEYS);
        $isExecutive = $this->hasAnyRole($roleIds, $roleKeys, self::EXECUTIVE_ROLE_IDS, self::EXECUTIVE_ROLE_KEYS);
        $isPartner = $this->hasAnyRole($roleIds, $roleKeys, self::PARTNER_ROLE_IDS, self::PARTNER_ROLE_KEYS);
        $isClient = $this->hasAnyRole($roleIds, $roleKeys, self::CLIENT_ROLE_IDS, self::CLIENT_ROLE_KEYS);

        if (!$isAdmin && !$isManager && !$isExecutive && !$isPartner && !$isClient) {
            throw new \RuntimeException('The logged-in user has no supported notification role in user_roles.');
        }

        return [
            'user_id' => $userId,
            'role_ids' => $roleIds,
            'role_keys' => $roleKeys,
            'is_admin' => $isAdmin,
            'is_manager' => $isManager,
            'is_executive' => $isExecutive,
            'is_partner' => $isPartner,
            'is_client' => $isClient,
            'scope_mode' => ($isAdmin || $isManager)
                ? 'all_activity_logs'
                : 'activity_logs_user_id_match',
        ];
    }

    private function hasAnyRole(
        array $currentRoleIds,
        array $currentRoleKeys,
        array $expectedRoleIds,
        array $expectedRoleKeys
    ): bool {
        return array_intersect($currentRoleIds, $expectedRoleIds) !== []
            || array_intersect($currentRoleKeys, $expectedRoleKeys) !== [];
    }

    private function normalizeRoleKey(string $value): string
    {
        $value = strtolower(trim($value));
        $value = str_replace([' ', '_'], '-', $value);
        $value = preg_replace('/[^a-z0-9-]+/', '', $value) ?: '';

        return trim($value, '-');
    }

    private function scopeSql(array $context): array
    {
        if (!empty($context['is_admin']) || !empty($context['is_manager'])) {
            return ['1 = 1', []];
        }

        $userId = (int) ($context['user_id'] ?? 0);
        if ($userId <= 0) {
            return ['0 = 1', []];
        }

        return [
            'al.user_id = :session_user_id',
            ['session_user_id' => $userId],
        ];
    }

    private function visibleActivities(
        array $context,
        int $limit,
        int $minimumId = 0,
        bool $ascending = false
    ): array {
        [$scopeSql, $params] = $this->scopeSql($context);

        $minimumSql = '';
        if ($minimumId > 0) {
            $minimumSql = ' AND al.id > :minimum_id';
            $params['minimum_id'] = $minimumId;
        }

        $direction = $ascending ? 'ASC' : 'DESC';
        $limit = max(1, min(100, $limit));

        return $this->db()->fetchAll(
            'SELECT
                al.id,
                al.user_id,
                al.order_id,
                al.action,
                al.description,
                al.meta_json,
                al.created_at
             FROM activity_logs al
             WHERE (' . $scopeSql . ')' . $minimumSql . '
             ORDER BY al.id ' . $direction . '
             LIMIT ' . $limit,
            $params
        ) ?: [];
    }

    private function visibleCount(array $context): int
    {
        [$scopeSql, $params] = $this->scopeSql($context);

        $row = $this->db()->fetch(
            'SELECT COUNT(*) AS total
             FROM activity_logs al
             WHERE (' . $scopeSql . ')',
            $params
        );

        return max(0, (int) ($row['total'] ?? 0));
    }

    private function unreadCount(array $context, array $readIds): int
    {
        $rows = $this->visibleActivities($context, 100, 0, false);
        $count = 0;

        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0 && !isset($readIds[$id])) {
                $count++;
            }
        }

        return $count;
    }

    private function activityIsVisible(int $activityId, array $context): bool
    {
        [$scopeSql, $params] = $this->scopeSql($context);
        $params['activity_id'] = $activityId;

        $row = $this->db()->fetch(
            'SELECT al.id
             FROM activity_logs al
             WHERE al.id = :activity_id
               AND (' . $scopeSql . ')
             LIMIT 1',
            $params
        );

        return is_array($row) && (int) ($row['id'] ?? 0) === $activityId;
    }

    private function sessionReadIds(int $userId): array
    {
        $stored = $_SESSION['activity_notification_reads'][$userId] ?? [];

        if (!is_array($stored)) {
            return [];
        }

        $clean = [];
        foreach ($stored as $id => $timestamp) {
            $id = (int) $id;
            if ($id > 0) {
                $clean[$id] = (int) $timestamp;
            }
        }

        return $clean;
    }

    private function saveSessionReadIds(int $userId, array $readIds): void
    {
        if (!isset($_SESSION['activity_notification_reads']) || !is_array($_SESSION['activity_notification_reads'])) {
            $_SESSION['activity_notification_reads'] = [];
        }

        if (count($readIds) > 500) {
            asort($readIds);
            $readIds = array_slice($readIds, -500, null, true);
        }

        $_SESSION['activity_notification_reads'][$userId] = $readIds;
    }

    private function presentActivity(array $row, array $context, array $readIds): array
    {
        $id = (int) ($row['id'] ?? 0);
        $action = trim((string) ($row['action'] ?? 'activity.updated'));
        $description = trim((string) ($row['description'] ?? 'Activity updated.'));
        $orderId = (int) ($row['order_id'] ?? 0);
        $meta = json_decode((string) ($row['meta_json'] ?? ''), true);
        $meta = is_array($meta) ? $meta : [];
        $category = $this->activityCategory($action);

        return [
            'id' => $id,
            'user_id' => (int) ($row['user_id'] ?? 0),
            'order_id' => $orderId,
            'action' => $action,
            'title' => trim((string) ($meta['title'] ?? '')) ?: $this->humanizeAction($action),
            'message' => $description !== '' ? $description : $this->humanizeAction($action),
            'category' => ucfirst($category),
            'tone' => $this->activityTone($action, $meta),
            'icon' => $this->activityIcon($category, $action),
            'order_no' => trim((string) ($meta['order_no'] ?? '')),
            'url' => $this->activityUrl($orderId, $context),
            'is_read' => isset($readIds[$id]),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'time_ago' => $this->timeAgo((string) ($row['created_at'] ?? '')),
        ];
    }

    private function activityCategory(string $action): string
    {
        $prefix = strtolower((string) strtok($action, '.'));

        return match ($prefix) {
            'order', 'application' => 'application',
            'document', 'client_document' => 'document',
            'payment' => 'payment',
            'invoice' => 'invoice',
            'assignment' => 'assignment',
            'partner', 'partner_order' => 'partner',
            'client' => 'client',
            'auth' => 'account',
            'system', 'settings', 'notification' => 'system',
            default => 'activity',
        };
    }

    private function activityTone(string $action, array $meta): string
    {
        $value = strtolower($action . ' ' . json_encode($meta));

        return match (true) {
            str_contains($value, 'wrong'),
            str_contains($value, 'reject'),
            str_contains($value, 'fail'),
            str_contains($value, 'cancel') => 'danger',
            str_contains($value, 'complete'),
            str_contains($value, 'approved'),
            str_contains($value, 'verified'),
            str_contains($value, 'paid'),
            str_contains($value, 'registered') => 'success',
            str_contains($value, 'pending'),
            str_contains($value, 'waiting'),
            str_contains($value, 'clarification') => 'amber',
            str_contains($value, 'assign') => 'violet',
            default => 'info',
        };
    }

    private function activityIcon(string $category, string $action): string
    {
        return match ($category) {
            'application' => '📋',
            'document' => str_contains(strtolower($action), 'wrong') ? '⚠️' : '📄',
            'payment' => '₹',
            'invoice' => '🧾',
            'assignment' => '👤',
            'partner' => '🤝',
            'client' => '👥',
            'account' => '🔐',
            'system' => '⚙️',
            default => '🔔',
        };
    }

    private function activityUrl(int $orderId, array $context): string
    {
        if ($orderId <= 0) {
            if (!empty($context['is_admin']) || !empty($context['is_manager'])) {
                return base_url('admin/dashboard');
            }

            if (!empty($context['is_executive'])) {
                return base_url('executive/dashboard');
            }

            if (!empty($context['is_partner'])) {
                return base_url('partner/dashboard');
            }

            return base_url('client/dashboard');
        }

        if (!empty($context['is_admin']) || !empty($context['is_manager']) || !empty($context['is_executive'])) {
            return base_url('admin/orders/show?id=' . $orderId);
        }

        if (!empty($context['is_partner'])) {
            return base_url('partner/orders/view?id=' . $orderId);
        }

        return base_url('client/orders/view?id=' . $orderId);
    }

    private function humanizeAction(string $action): string
    {
        $label = str_replace(['.', '_', '-'], ' ', $action);
        $label = preg_replace('/\s+/', ' ', $label) ?: $label;

        return ucwords(trim($label)) ?: 'Activity Updated';
    }

    private function timeAgo(string $dateTime): string
    {
        $timestamp = strtotime($dateTime);

        if ($timestamp === false) {
            return 'Just now';
        }

        $seconds = max(0, time() - $timestamp);

        return match (true) {
            $seconds < 60 => 'Just now',
            $seconds < 3600 => (int) floor($seconds / 60) . 'm ago',
            $seconds < 86400 => (int) floor($seconds / 3600) . 'h ago',
            $seconds < 604800 => (int) floor($seconds / 86400) . 'd ago',
            default => date('d M Y', $timestamp),
        };
    }

    private function writeActivity(
        ?int $userId,
        ?int $orderId,
        string $action,
        string $description,
        array $meta = []
    ): void {
        if (function_exists('activity_log')) {
            activity_log($userId, $orderId, $action, $description, $meta);
            return;
        }

        $this->db()->query(
            'INSERT INTO activity_logs
                (user_id, order_id, action, description, meta_json, created_at)
             VALUES
                (:user_id, :order_id, :action, :description, :meta_json, :created_at)',
            [
                'user_id' => $userId,
                'order_id' => $orderId,
                'action' => $action,
                'description' => $description,
                'meta_json' => json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => date('Y-m-d H:i:s'),
            ]
        );
    }
}