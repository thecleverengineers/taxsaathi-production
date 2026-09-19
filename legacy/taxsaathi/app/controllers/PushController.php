<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\RolePushService;
use RuntimeException;
use Throwable;

/**
 * Authenticated realtime notification APIs.
 *
 * GET  /notifications             index
 * GET  /notifications/poll        poll
 * GET  /push/stream               stream (SSE)
 * GET  /push/config               config
 * GET  /firebase-messaging-sw.js  serviceWorker
 * POST /push/token/register       registerToken
 * POST /push/token/unregister     unregisterToken
 * POST /notifications/mark-read   markRead
 * POST /notifications/mark-unread markUnread
 * POST /notifications/mark-all-read markAllRead
 * POST /push/send                 send (Admin/Manager)
 * POST /push/test                 test
 */
final class PushController extends Controller
{
    private ?Database $database = null;

    private function db(): Database
    {
        if (!$this->database instanceof Database) {
            $this->database = new Database($this->databaseConfig());
        }

        return $this->database;
    }

    private function service(): RolePushService
    {
        return new RolePushService($this->db());
    }

    private function databaseConfig(): array
    {
        $config = [];

        if (function_exists('config')) {
            try {
                $raw = config('database');
                if (is_array($raw)) {
                    $config = $this->normalizeDatabaseConfig($raw);
                }
            } catch (Throwable $e) {
                $config = [];
            }
        }

        if ($config === []) {
            $configFile = dirname(__DIR__, 2) . '/config/database.php';
            if (is_file($configFile)) {
                $raw = require $configFile;
                if (is_array($raw)) {
                    $config = $this->normalizeDatabaseConfig($raw);
                }
            }
        }

        if ($config === []) {
            $env = static function (string $key, mixed $default = null): mixed {
                if (function_exists('env')) {
                    return env($key, $default);
                }
                $value = getenv($key);
                return $value !== false ? $value : $default;
            };

            $config = [
                'driver' => (string) $env('DB_DRIVER', 'mysql'),
                'host' => (string) $env('DB_HOST', '127.0.0.1'),
                'port' => (int) $env('DB_PORT', 3306),
                'database' => (string) $env('DB_DATABASE', 'taxsathi2'),
                'charset' => (string) $env('DB_CHARSET', 'utf8mb4'),
                'username' => (string) $env('DB_USERNAME', 'taxsathi2'),
                'password' => (string) $env('DB_PASSWORD', 'taxsathi2'),
            ];
        }

        if (($config['database'] ?? '') === '') {
            throw new RuntimeException('Database configuration is missing the database name.');
        }

        return $config;
    }

    private function normalizeDatabaseConfig(array $config): array
    {
        if (isset($config['connections']) && is_array($config['connections'])) {
            $default = (string) ($config['default'] ?? array_key_first($config['connections']));
            $connection = $config['connections'][$default] ?? [];
            if (is_array($connection) && $connection !== []) {
                $config = $connection;
            }
        }

        return [
            'driver' => (string) ($config['driver'] ?? 'mysql'),
            'host' => (string) ($config['host'] ?? '127.0.0.1'),
            'port' => (int) ($config['port'] ?? 3306),
            'database' => (string) ($config['database'] ?? 'taxsathi2'),
            'charset' => (string) ($config['charset'] ?? 'utf8mb4'),
            'username' => (string) ($config['username'] ?? 'taxsathi2'),
            'password' => (string) ($config['password'] ?? 'taxsathi2'),
        ];
    }

    public function index(): void
    {
        $userId = $this->requireAuthenticatedUser();
        $rows = $this->fetchNotifications($userId, 100, 0);
        $unread = $this->unreadCount($userId);

        $this->view('notifications/index', [
            'title' => 'Notifications – Tax Saathi',
            'notifications' => $rows,
            'unreadCount' => $unread,
        ], 'layouts/dashboard');
    }

    public function manage(): void
    {
        $userId = $this->requireAuthenticatedUser();
        $this->requireAdminOrManager($userId);

        $roles = $this->db()->fetchAll(
            'SELECT id, name, slug
             FROM roles
             WHERE LOWER(COALESCE(slug, "")) IN ("admin", "administrator", "manager", "executive", "staff", "partner", "partners", "client", "customer")
             ORDER BY id ASC'
        ) ?: [];

        $users = $this->db()->fetchAll(
            'SELECT
                u.id,
                u.name,
                u.email,
                u.phone,
                u.client_id,
                u.partner_id,
                GROUP_CONCAT(DISTINCT r.name ORDER BY r.id SEPARATOR ", ") AS role_names,
                GROUP_CONCAT(DISTINCT r.slug ORDER BY r.id SEPARATOR ",") AS role_slugs
             FROM users u
             LEFT JOIN user_roles ur ON ur.user_id = u.id
             LEFT JOIN roles r ON r.id = ur.role_id
             WHERE COALESCE(u.is_active, 1) = 1
             GROUP BY u.id, u.name, u.email, u.phone, u.client_id, u.partner_id
             ORDER BY u.name ASC, u.id ASC'
        ) ?: [];

        $this->view('notifications/manage', [
            'title' => 'Push Notification Manager – Tax Saathi',
            'roles' => $roles,
            'users' => $users,
        ], 'layouts/dashboard');
    }

    public function poll(): void
    {
        $userId = $this->requireAuthenticatedUser(true);
        $limit = min(50, max(1, (int) ($_GET['limit'] ?? 15)));
        $afterId = max(0, (int) ($_GET['after_id'] ?? 0));
        $rows = $this->fetchNotifications($userId, $limit, $afterId);
        $latestId = 0;
        $ids = [];

        foreach ($rows as $row) {
            $latestId = max($latestId, (int) ($row['id'] ?? 0));
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        if ($ids !== []) {
            $this->markDelivered($userId, $ids);
        }

        $this->json([
            'ok' => true,
            'items' => array_map(fn (array $row): array => $this->serializeNotification($row), $rows),
            'unread_count' => $this->unreadCount($userId),
            'latest_id' => $latestId,
            'server_time' => date(DATE_ATOM),
        ]);
    }

    /**
     * Lightweight Server-Sent Events stream. It exits after ~25 seconds so
     * reverse proxies/PHP-FPM workers are not held forever; EventSource reconnects.
     */
    public function stream(): void
    {
        $userId = $this->requireAuthenticatedUser(true);
        $lastId = max(
            0,
            (int) ($_GET['after_id'] ?? 0),
            (int) ($_SERVER['HTTP_LAST_EVENT_ID'] ?? 0)
        );

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        @set_time_limit(30);
        @ini_set('zlib.output_compression', '0');
        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('X-Accel-Buffering: no');
        header('Connection: keep-alive');

        echo "retry: 3000\n\n";
        $this->flushOutput();

        $started = microtime(true);
        $lastHeartbeat = 0.0;

        while ((microtime(true) - $started) < 25) {
            if (connection_aborted()) {
                break;
            }

            $rows = $this->fetchNotifications($userId, 20, $lastId);
            $deliveredIds = [];

            foreach ($rows as $row) {
                $id = (int) ($row['id'] ?? 0);
                if ($id <= $lastId) {
                    continue;
                }

                $lastId = $id;
                $deliveredIds[] = $id;
                echo 'id: ' . $id . "\n";
                echo "event: notification\n";
                echo 'data: ' . $this->encodeJson($this->serializeNotification($row)) . "\n\n";
            }

            if ($deliveredIds !== []) {
                $this->markDelivered($userId, $deliveredIds);
                echo "event: unread\n";
                echo 'data: ' . $this->encodeJson(['count' => $this->unreadCount($userId)]) . "\n\n";
                $this->flushOutput();
            }

            if ((microtime(true) - $lastHeartbeat) >= 10) {
                $lastHeartbeat = microtime(true);
                echo ': heartbeat ' . date(DATE_ATOM) . "\n\n";
                $this->flushOutput();
            }

            usleep(900000);
        }

        echo "event: reconnect\n";
        echo 'data: ' . $this->encodeJson(['after_id' => $lastId]) . "\n\n";
        $this->flushOutput();
        exit;
    }

    public function config(): void
    {
        $this->requireAuthenticatedUser(true);
        $firebase = $this->firebaseWebConfig();

        $this->json([
            'ok' => true,
            'firebase_enabled' => $firebase['enabled'],
            'firebase' => $firebase['config'],
            'vapid_key' => $firebase['vapid_key'],
            'service_worker_url' => $this->url('firebase-messaging-sw.js'),
            'sse_enabled' => true,
            'poll_interval_ms' => 15000,
        ]);
    }

    /** Serve a same-origin Firebase messaging service worker with public config only. */
    public function serviceWorker(): void
    {
        $firebase = $this->firebaseWebConfig();
        header('Content-Type: application/javascript; charset=utf-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Service-Worker-Allowed: /');

        if (!$firebase['enabled']) {
            echo "self.addEventListener('push', function(event){\n"
                . "  var data={}; try{data=event.data?event.data.json():{};}catch(e){}\n"
                . "  var n=data.notification||data; var title=n.title||'Tax Saathi';\n"
                . "  event.waitUntil(self.registration.showNotification(title,{body:n.body||n.message||'',data:data.data||data,icon:'/favicon.ico'}));\n"
                . "});\n"
                . "self.addEventListener('notificationclick',function(event){event.notification.close();var u=(event.notification.data&&event.notification.data.url)||'/notifications';event.waitUntil(clients.openWindow(u));});\n";
            exit;
        }

        $configJson = $this->encodeJson($firebase['config']);
        echo "importScripts('https://www.gstatic.com/firebasejs/10.13.2/firebase-app-compat.js');\n";
        echo "importScripts('https://www.gstatic.com/firebasejs/10.13.2/firebase-messaging-compat.js');\n";
        echo 'firebase.initializeApp(' . $configJson . ");\n";
        echo "const messaging=firebase.messaging();\n";
        echo "messaging.onBackgroundMessage(function(payload){\n"
            . "  const n=payload.notification||{}; const d=payload.data||{};\n"
            . "  const title=n.title||d.title||'Tax Saathi';\n"
            . "  self.registration.showNotification(title,{body:n.body||d.message||'',icon:d.icon||'/favicon.ico',badge:d.badge||'/favicon.ico',tag:d.uid||undefined,renotify:true,data:d});\n"
            . "});\n";
        echo "self.addEventListener('notificationclick',function(event){event.notification.close();const d=event.notification.data||{};const url=d.url||'/notifications';event.waitUntil(clients.matchAll({type:'window',includeUncontrolled:true}).then(function(list){for(const c of list){if('focus' in c){c.navigate(url);return c.focus();}}return clients.openWindow(url);}));});\n";
        exit;
    }

    public function registerToken(): void
    {
        $userId = $this->requireAuthenticatedUser(true);
        $this->requirePostAndCsrf();
        $input = $this->requestData();

        $token = trim((string) ($input['fcm_token'] ?? $input['token'] ?? ''));
        $deviceKey = trim((string) ($input['device_key'] ?? ''));
        $platform = strtolower(trim((string) ($input['platform'] ?? 'web')));

        if ($token === '' || strlen($token) > 255) {
            $this->json(['ok' => false, 'message' => 'A valid FCM token is required.'], 422);
        }

        if ($deviceKey === '') {
            $deviceKey = hash('sha256', $userId . '|' . ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . substr($token, 0, 32));
        }
        $deviceKey = substr(preg_replace('/[^a-zA-Z0-9._:-]/', '', $deviceKey) ?: hash('sha256', $deviceKey), 0, 100);
        $platform = in_array($platform, ['web', 'android', 'ios', 'desktop'], true) ? $platform : 'web';
        $now = date('Y-m-d H:i:s');

        try {
            // A browser token can move to another authenticated account after logout/login.
            // Remove any stale ownership before applying the per-user/device upsert.
            $this->db()->execute(
                'DELETE FROM user_push_tokens
                 WHERE fcm_token = :fcm_token
                   AND (user_id <> :user_id OR device_key <> :device_key)',
                [
                    'fcm_token' => $token,
                    'user_id' => $userId,
                    'device_key' => $deviceKey,
                ]
            );

            $this->db()->execute(
                'INSERT INTO user_push_tokens (
                    user_id, fcm_token, device_key, platform, user_agent,
                    is_active, last_seen_at, created_at, updated_at
                 ) VALUES (
                    :user_id, :fcm_token, :device_key, :platform, :user_agent,
                    1, :last_seen_at, :created_at, :updated_at
                 )
                 ON DUPLICATE KEY UPDATE
                    user_id = VALUES(user_id),
                    fcm_token = VALUES(fcm_token),
                    device_key = VALUES(device_key),
                    platform = VALUES(platform),
                    user_agent = VALUES(user_agent),
                    is_active = 1,
                    last_seen_at = VALUES(last_seen_at),
                    updated_at = VALUES(updated_at)',
                [
                    'user_id' => $userId,
                    'fcm_token' => $token,
                    'device_key' => $deviceKey,
                    'platform' => $platform,
                    'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                    'last_seen_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        } catch (Throwable $e) {
            error_log('Push token registration failed: ' . $e->getMessage());
            $this->json(['ok' => false, 'message' => 'Unable to register this device.'], 500);
        }

        $this->json([
            'ok' => true,
            'message' => 'Push notifications enabled for this device.',
            'device_key' => $deviceKey,
        ]);
    }

    public function unregisterToken(): void
    {
        $userId = $this->requireAuthenticatedUser(true);
        $this->requirePostAndCsrf();
        $input = $this->requestData();
        $deviceKey = trim((string) ($input['device_key'] ?? ''));
        $token = trim((string) ($input['fcm_token'] ?? $input['token'] ?? ''));

        if ($deviceKey === '' && $token === '') {
            $this->json(['ok' => false, 'message' => 'Device key or token is required.'], 422);
        }

        $sql = 'UPDATE user_push_tokens
                SET is_active = 0, updated_at = :updated_at
                WHERE user_id = :user_id';
        $params = ['updated_at' => date('Y-m-d H:i:s'), 'user_id' => $userId];

        if ($deviceKey !== '') {
            $sql .= ' AND device_key = :device_key';
            $params['device_key'] = $deviceKey;
        } else {
            $sql .= ' AND fcm_token = :fcm_token';
            $params['fcm_token'] = $token;
        }

        $this->db()->execute($sql, $params);
        $this->json(['ok' => true, 'message' => 'Push notifications disabled for this device.']);
    }

    public function markRead(): void
    {
        $this->changeReadState(true);
    }

    public function markUnread(): void
    {
        $this->changeReadState(false);
    }

    public function markAllRead(): void
    {
        $userId = $this->requireAuthenticatedUser(true);
        $this->requirePostAndCsrf();
        $now = date('Y-m-d H:i:s');

        $this->db()->execute(
            'UPDATE notification_manager
             SET is_read = 1, read_at = :read_at, updated_at = :updated_at
             WHERE recipient_user_id = :user_id
               AND is_read = 0',
            ['read_at' => $now, 'updated_at' => $now, 'user_id' => $userId]
        );

        try {
            $this->db()->execute(
                'UPDATE notifications
                 SET is_read = 1, read_at = :read_at, updated_at = :updated_at
                 WHERE user_id = :user_id
                   AND is_read = 0',
                ['read_at' => $now, 'updated_at' => $now, 'user_id' => $userId]
            );
        } catch (Throwable $e) {
            error_log('Legacy mark all read skipped: ' . $e->getMessage());
        }

        $this->json(['ok' => true, 'unread_count' => 0]);
    }

    /** Admin/Manager custom notification composer endpoint. */
    public function send(): void
    {
        $actorUserId = $this->requireAuthenticatedUser(true);
        $this->requirePostAndCsrf();
        $this->requireAdminOrManager($actorUserId);
        $input = $this->requestData();

        $title = trim((string) ($input['title'] ?? ''));
        $message = trim((string) ($input['message'] ?? $input['body'] ?? ''));
        if ($title === '' || $message === '') {
            $this->json(['ok' => false, 'message' => 'Title and message are required.'], 422);
        }

        $payload = [
            'actor_user_id' => $actorUserId,
            'title' => $title,
            'message' => $message,
            'severity' => (string) ($input['severity'] ?? 'info'),
            'status_label' => (string) ($input['status_label'] ?? ''),
            'url' => (string) ($input['url'] ?? '/notifications'),
            'roles' => $input['roles'] ?? $input['role_slugs'] ?? [],
            'user_ids' => $input['user_ids'] ?? [],
            'order_id' => (int) ($input['order_id'] ?? 0),
            'partner_order_id' => (int) ($input['partner_order_id'] ?? 0),
            'client_id' => (int) ($input['client_id'] ?? 0),
            'partner_id' => (int) ($input['partner_id'] ?? 0),
            'executive_user_id' => (int) ($input['executive_user_id'] ?? 0),
            'include_actor' => (bool) ($input['include_actor'] ?? false),
            'context_audience' => $input['context_audience'] ?? [],
            'include_context_roles' => (bool) ($input['include_context_roles'] ?? false),
            'type' => 'manual',
            'source_table' => 'users',
            'source_id' => $actorUserId,
            'data' => ['sent_by' => $actorUserId],
            'dedupe_key' => bin2hex(random_bytes(8)),
        ];

        if (($payload['roles'] === [] || $payload['roles'] === '')
            && ($payload['user_ids'] === [] || $payload['user_ids'] === '')
            && $payload['order_id'] <= 0
            && $payload['partner_order_id'] <= 0
            && $payload['client_id'] <= 0
            && $payload['partner_id'] <= 0
            && $payload['executive_user_id'] <= 0
        ) {
            $this->json(['ok' => false, 'message' => 'Choose at least one role, user or order context.'], 422);
        }

        $result = $this->service()->dispatch((string) ($input['event_key'] ?? 'manual.broadcast'), $payload);
        $this->json(['ok' => true, 'result' => $result]);
    }

    public function test(): void
    {
        $userId = $this->requireAuthenticatedUser(true);
        $this->requirePostAndCsrf();
        $result = $this->service()->dispatch('push.test', [
            'recipient_user_id' => $userId,
            'include_actor' => true,
            'include_context_roles' => false,
            'title' => 'Push notifications are working',
            'message' => 'This test was delivered to your Tax Saathi account.',
            'severity' => 'success',
            'url' => '/notifications',
            'type' => 'test',
            'dedupe_key' => bin2hex(random_bytes(8)),
        ]);

        $this->json(['ok' => true, 'result' => $result]);
    }

    public function health(): void
    {
        $userId = $this->requireAuthenticatedUser(true);
        $this->requireAdminOrManager($userId);
        $firebase = $this->firebaseWebConfig();
        $counts = $this->db()->fetch(
            'SELECT
                (SELECT COUNT(*) FROM notification_manager) AS notifications,
                (SELECT COUNT(*) FROM notification_manager WHERE is_read = 0) AS unread,
                (SELECT COUNT(*) FROM user_push_tokens WHERE is_active = 1) AS active_tokens'
        ) ?: [];

        $serviceAccountConfigured = trim((string) (
            getenv('FIREBASE_SERVICE_ACCOUNT_JSON')
            ?: getenv('FIREBASE_SERVICE_ACCOUNT_PATH')
            ?: getenv('GOOGLE_APPLICATION_CREDENTIALS')
            ?: ''
        )) !== '';

        $this->json([
            'ok' => true,
            'database' => $counts,
            'firebase_web_configured' => $firebase['enabled'],
            'firebase_server_configured' => $serviceAccountConfigured,
            'server_time' => date(DATE_ATOM),
        ]);
    }

    private function changeReadState(bool $read): void
    {
        $userId = $this->requireAuthenticatedUser(true);
        $this->requirePostAndCsrf();
        $input = $this->requestData();
        $uid = trim((string) ($input['uid'] ?? $input['notification_uid'] ?? ''));
        $id = (int) ($input['id'] ?? 0);

        if ($uid === '' && $id <= 0) {
            $this->json(['ok' => false, 'message' => 'Notification ID is required.'], 422);
        }

        $now = date('Y-m-d H:i:s');
        $sql = 'UPDATE notification_manager
                SET is_read = :is_read,
                    read_at = :read_at,
                    updated_at = :updated_at
                WHERE recipient_user_id = :user_id';
        $params = [
            'is_read' => $read ? 1 : 0,
            'read_at' => $read ? $now : null,
            'updated_at' => $now,
            'user_id' => $userId,
        ];

        if ($uid !== '') {
            $sql .= ' AND uid = :uid';
            $params['uid'] = $uid;
        } else {
            $sql .= ' AND id = :id';
            $params['id'] = $id;
        }

        $this->db()->execute($sql, $params);

        if ($uid !== '') {
            try {
                $this->db()->execute(
                    'UPDATE notifications
                     SET is_read = :is_read, read_at = :read_at, updated_at = :updated_at
                     WHERE user_id = :user_id AND uid = :uid',
                    [
                        'is_read' => $read ? 1 : 0,
                        'read_at' => $read ? $now : null,
                        'updated_at' => $now,
                        'user_id' => $userId,
                        'uid' => $uid,
                    ]
                );
            } catch (Throwable $e) {
                error_log('Legacy read state skipped: ' . $e->getMessage());
            }
        }

        $this->json([
            'ok' => true,
            'is_read' => $read,
            'unread_count' => $this->unreadCount($userId),
        ]);
    }

    private function fetchNotifications(int $userId, int $limit, int $afterId): array
    {
        $limit = min(100, max(1, $limit));
        $sql = 'SELECT
                    id, uid, recipient_user_id, recipient_type, recipient_role,
                    actor_user_id, client_id, partner_id, executive_user_id,
                    order_id, partner_order_id, order_no,
                    source_table, source_id, type, event_key,
                    title, message, severity, status_label, url,
                    payload_json, is_read, read_at, delivered_at,
                    first_seen_at, created_at, updated_at
                FROM notification_manager
                WHERE recipient_user_id = :user_id';
        $params = ['user_id' => $userId];

        if ($afterId > 0) {
            $sql .= ' AND id > :after_id';
            $params['after_id'] = $afterId;
            $sql .= ' ORDER BY id ASC';
        } else {
            $sql .= ' ORDER BY id DESC';
        }

        $sql .= ' LIMIT ' . $limit;
        return $this->db()->fetchAll($sql, $params) ?: [];
    }

    private function unreadCount(int $userId): int
    {
        $row = $this->db()->fetch(
            'SELECT COUNT(*) AS total
             FROM notification_manager
             WHERE recipient_user_id = :user_id
               AND is_read = 0',
            ['user_id' => $userId]
        );
        return (int) ($row['total'] ?? 0);
    }

    private function markDelivered(int $userId, array $ids): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        $this->db()->execute(
            'UPDATE notification_manager
             SET delivered_at = COALESCE(delivered_at, :delivered_at),
                 first_seen_at = COALESCE(first_seen_at, :first_seen_at),
                 updated_at = :updated_at
             WHERE recipient_user_id = :user_id
               AND id IN (' . implode(',', $ids) . ')',
            [
                'delivered_at' => $now,
                'first_seen_at' => $now,
                'updated_at' => $now,
                'user_id' => $userId,
            ]
        );
    }

    private function serializeNotification(array $row): array
    {
        $payload = json_decode((string) ($row['payload_json'] ?? ''), true);
        if (!is_array($payload)) {
            $payload = [];
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'uid' => (string) ($row['uid'] ?? ''),
            'title' => (string) ($row['title'] ?? 'Notification'),
            'message' => (string) ($row['message'] ?? ''),
            'severity' => (string) ($row['severity'] ?? 'normal'),
            'status_label' => (string) ($row['status_label'] ?? ''),
            'url' => (string) ($row['url'] ?? ''),
            'type' => (string) ($row['type'] ?? 'notification'),
            'event_key' => (string) ($row['event_key'] ?? 'general'),
            'recipient_type' => (string) ($row['recipient_type'] ?? ''),
            'recipient_role' => (string) ($row['recipient_role'] ?? ''),
            'order_id' => (int) ($row['order_id'] ?? 0),
            'partner_order_id' => (int) ($row['partner_order_id'] ?? 0),
            'order_no' => (string) ($row['order_no'] ?? ''),
            'is_read' => (bool) ($row['is_read'] ?? false),
            'read_at' => $row['read_at'] ?? null,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'payload' => $payload,
        ];
    }

    private function requireAuthenticatedUser(bool $json = false): int
    {
        if (function_exists('require_auth')) {
            require_auth();
        }

        $userId = $this->currentUserId();
        if ($userId > 0) {
            return $userId;
        }

        if ($json) {
            $this->json(['ok' => false, 'message' => 'Authentication required.'], 401);
        }

        if (function_exists('redirect')) {
            redirect('auth');
        }
        header('Location: ' . $this->url('auth'));
        exit;
    }

    private function currentUserId(): int
    {
        $user = [];
        if (function_exists('auth_user')) {
            try {
                $candidate = auth_user();
                if (is_array($candidate)) {
                    $user = $candidate;
                }
            } catch (Throwable $e) {
            }
        }

        return (int) (
            $user['id']
            ?? $_SESSION['user_id']
            ?? $_SESSION['auth_user_id']
            ?? $_SESSION['auth_user']['id']
            ?? $_SESSION['user']['id']
            ?? 0
        );
    }

    private function requireAdminOrManager(int $userId): void
    {
        $row = $this->db()->fetch(
            'SELECT ur.user_id
             FROM user_roles ur
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE ur.user_id = :user_id
               AND (
                    LOWER(COALESCE(r.slug, "")) IN ("admin", "administrator", "manager")
                    OR LOWER(REPLACE(COALESCE(r.name, ""), " ", "-")) IN ("admin", "administrator", "manager")
               )
             LIMIT 1',
            ['user_id' => $userId]
        );

        if (!is_array($row)) {
            $this->json(['ok' => false, 'message' => 'Admin or Manager access required.'], 403);
        }
    }

    private function requirePostAndCsrf(): void
    {
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            $this->json(['ok' => false, 'message' => 'Method not allowed.'], 405);
        }

        if (function_exists('verify_csrf')) {
            verify_csrf();
        }
    }

    private function requestData(): array
    {
        $data = $_POST;
        $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
        if (str_contains($contentType, 'application/json')) {
            $decoded = json_decode((string) file_get_contents('php://input'), true);
            if (is_array($decoded)) {
                $data = array_merge($data, $decoded);
            }
        }
        return is_array($data) ? $data : [];
    }

    private function firebaseWebConfig(): array
    {
        $config = [
            'apiKey' => trim((string) (getenv('FIREBASE_WEB_API_KEY') ?: '')),
            'authDomain' => trim((string) (getenv('FIREBASE_AUTH_DOMAIN') ?: '')),
            'projectId' => trim((string) (getenv('FIREBASE_PROJECT_ID') ?: '')),
            'storageBucket' => trim((string) (getenv('FIREBASE_STORAGE_BUCKET') ?: '')),
            'messagingSenderId' => trim((string) (getenv('FIREBASE_MESSAGING_SENDER_ID') ?: '')),
            'appId' => trim((string) (getenv('FIREBASE_APP_ID') ?: '')),
        ];
        $measurementId = trim((string) (getenv('FIREBASE_MEASUREMENT_ID') ?: ''));
        if ($measurementId !== '') {
            $config['measurementId'] = $measurementId;
        }

        $enabled = $config['apiKey'] !== ''
            && $config['projectId'] !== ''
            && $config['messagingSenderId'] !== ''
            && $config['appId'] !== '';

        return [
            'enabled' => $enabled,
            'config' => $enabled ? $config : [],
            'vapid_key' => trim((string) (getenv('FIREBASE_VAPID_PUBLIC_KEY') ?: '')),
        ];
    }

    private function url(string $path): string
    {
        return function_exists('base_url') ? base_url($path) : '/' . ltrim($path, '/');
    }

    private function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        echo $this->encodeJson($payload);
        exit;
    }

    private function encodeJson(mixed $payload): string
    {
        return (string) json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );
    }

    private function flushOutput(): void
    {
        if (function_exists('ob_get_level')) {
            while (ob_get_level() > 0) {
                @ob_end_flush();
            }
        }
        @flush();
    }
}
