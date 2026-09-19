<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;
use Throwable;

/**
 * Compatibility facade for existing NotificationService::trigger(...) calls.
 *
 * If your project already has a NotificationService class, merge the trigger(),
 * pushService(), normalizePushEvent() and database helper methods instead of
 * deleting unrelated email/WhatsApp methods.
 */
final class NotificationService
{
    private static ?Database $database = null;

    public static function trigger(string $event, array $payload = []): array
    {
        try {
            $normalized = self::normalizePushEvent($event, $payload);
            return self::pushService()->dispatch($normalized['event_key'], $normalized['payload']);
        } catch (Throwable $e) {
            error_log('NotificationService push trigger failed: ' . $e->getMessage());
            return [
                'event_key' => $event,
                'recipient_count' => 0,
                'created_count' => 0,
                'errors' => [['message' => $e->getMessage()]],
            ];
        }
    }

    public static function toUser(int $userId, string $title, string $message, array $payload = []): array
    {
        return self::pushService()->dispatch((string) ($payload['event_key'] ?? 'manual.user'), array_merge($payload, [
            'recipient_user_id' => $userId,
            'include_context_roles' => false,
            'title' => $title,
            'message' => $message,
        ]));
    }

    public static function toRoles(array|string $roles, string $title, string $message, array $payload = []): array
    {
        return self::pushService()->dispatch((string) ($payload['event_key'] ?? 'manual.roles'), array_merge($payload, [
            'roles' => is_array($roles) ? $roles : [$roles],
            'include_context_roles' => false,
            'title' => $title,
            'message' => $message,
        ]));
    }

    private static function pushService(): RolePushService
    {
        return new RolePushService(self::db());
    }

    private static function normalizePushEvent(string $event, array $payload): array
    {
        $original = strtolower(trim($event));
        $eventKey = match ($original) {
            'client_document_wrong' => 'document.wrong',
            'client_document_replaced' => 'document.reuploaded',
            'client_document_uploaded' => 'document.uploaded',
            'order_documents_unlocked' => 'order.documents.unlocked',
            'order_in_progress' => 'order.status.changed',
            'order_completed' => 'order.completed',
            'order_placed' => 'order.created',
            'invoice_saved' => 'invoice.updated',
            'payment_submitted' => 'payment.submitted',
            'payment_verified' => 'payment.verified',
            default => str_replace('_', '.', $original !== '' ? $original : 'general'),
        };

        $orderId = (int) ($payload['order_id'] ?? 0);
        $orderNo = trim((string) ($payload['order_no'] ?? ''));

        if ($orderId <= 0 && $orderNo !== '') {
            try {
                $row = self::db()->fetch(
                    'SELECT id FROM orders WHERE order_no = :order_no LIMIT 1',
                    ['order_no' => $orderNo]
                );
                $orderId = (int) ($row['id'] ?? 0);
            } catch (Throwable $e) {
                error_log('NotificationService order lookup skipped: ' . $e->getMessage());
            }
        }

        $documentLabel = trim((string) ($payload['document_label'] ?? 'Document'));
        $reason = trim((string) ($payload['reason'] ?? ''));
        $service = trim((string) ($payload['service'] ?? ''));
        $status = trim((string) ($payload['status'] ?? ''));

        [$title, $message, $severity, $statusLabel] = match ($original) {
            'client_document_wrong' => [
                'Document correction required' . ($orderNo !== '' ? ' · ' . $orderNo : ''),
                $documentLabel . ' was marked incorrect.' . ($reason !== '' ? ' Reason: ' . $reason : ' Please upload the correct document.'),
                'warning',
                'clarification',
            ],
            'client_document_replaced' => [
                'Corrected document uploaded' . ($orderNo !== '' ? ' · ' . $orderNo : ''),
                'The client uploaded a corrected file for ' . $documentLabel . '.',
                'info',
                'pending_review',
            ],
            'client_document_uploaded' => [
                'New client document uploaded' . ($orderNo !== '' ? ' · ' . $orderNo : ''),
                $documentLabel . ' was uploaded for review.',
                'info',
                'pending_review',
            ],
            'order_documents_unlocked' => [
                'Documents ready for review' . ($orderNo !== '' ? ' · ' . $orderNo : ''),
                'Client documents are now available for staff review.',
                'info',
                'pending_review',
            ],
            'order_in_progress' => [
                'Your order is now in progress' . ($orderNo !== '' ? ' · ' . $orderNo : ''),
                ($service !== '' ? $service . ' is' : 'Your service is') . ' being processed by our team.',
                'info',
                'work_in_progress',
            ],
            'order_completed' => [
                'Order completed' . ($orderNo !== '' ? ' · ' . $orderNo : ''),
                ($service !== '' ? $service . ' has' : 'Your service has') . ' been completed.',
                'success',
                'completed',
            ],
            'order_placed' => [
                'New order placed' . ($orderNo !== '' ? ' · ' . $orderNo : ''),
                ($service !== '' ? $service : 'A service order') . ' has been submitted.',
                'info',
                (string) ($payload['payment_status'] ?? 'pending_review'),
            ],
            'invoice_saved' => [
                'Invoice updated' . ($orderNo !== '' ? ' · ' . $orderNo : ''),
                'The invoice for ' . ($service !== '' ? $service : 'this order') . ' was updated.',
                'info',
                (string) ($payload['invoice_status'] ?? 'updated'),
            ],
            default => [
                trim((string) ($payload['title'] ?? ucwords(str_replace(['.', '_'], ' ', $eventKey)))) ?: 'Tax Saathi notification',
                trim((string) ($payload['message'] ?? $payload['body'] ?? 'There is a new update on your account.')),
                (string) ($payload['severity'] ?? 'info'),
                (string) ($payload['status_label'] ?? $status),
            ],
        };

        $actorUserId = 0;
        if (function_exists('auth_user')) {
            try {
                $user = auth_user();
                if (is_array($user)) {
                    $actorUserId = (int) ($user['id'] ?? 0);
                }
            } catch (Throwable $e) {
            }
        }
        if ($actorUserId <= 0) {
            $actorUserId = (int) (
                $_SESSION['user_id']
                ?? $_SESSION['auth_user_id']
                ?? $_SESSION['auth_user']['id']
                ?? $_SESSION['user']['id']
                ?? 0
            );
        }

        // Leave URL empty unless the caller explicitly supplied one.
        // RolePushService then creates the correct destination for each recipient type.
        $url = trim((string) ($payload['url'] ?? ''));

        return [
            'event_key' => $eventKey,
            'payload' => array_merge($payload, [
                'actor_user_id' => $actorUserId,
                'include_actor' => array_key_exists('include_actor', $payload)
                    ? (bool) $payload['include_actor']
                    : in_array($original, ['order_placed', 'partner_order_created'], true),
                'order_id' => $orderId,
                'order_no' => $orderNo,
                'title' => $title,
                'message' => $message,
                'severity' => $severity,
                'status_label' => $statusLabel,
                'url' => $url,
                'source_table' => (string) ($payload['source_table'] ?? ($orderId > 0 ? 'orders' : 'system')),
                'source_id' => (int) ($payload['source_id'] ?? $orderId),
                'data' => array_merge(is_array($payload['data'] ?? null) ? $payload['data'] : [], [
                    'original_event' => $original,
                    'document_id' => (int) ($payload['document_id'] ?? 0),
                    'document_label' => $documentLabel,
                    'reason' => $reason,
                    'old_original_name' => (string) ($payload['old_original_name'] ?? ''),
                    'new_original_name' => (string) ($payload['new_original_name'] ?? ''),
                ]),
            ]),
        ];
    }

    private static function db(): Database
    {
        if (!self::$database instanceof Database) {
            self::$database = new Database(self::databaseConfig());
        }
        return self::$database;
    }

    private static function databaseConfig(): array
    {
        $config = [];
        if (function_exists('config')) {
            try {
                $raw = config('database');
                if (is_array($raw)) {
                    $config = self::normalizeDatabaseConfig($raw);
                }
            } catch (Throwable $e) {
            }
        }

        if ($config === []) {
            $file = dirname(__DIR__, 2) . '/config/database.php';
            if (is_file($file)) {
                $raw = require $file;
                if (is_array($raw)) {
                    $config = self::normalizeDatabaseConfig($raw);
                }
            }
        }

        if ($config === []) {
            $env = static fn (string $key, mixed $default = null): mixed => function_exists('env')
                ? env($key, $default)
                : (getenv($key) !== false ? getenv($key) : $default);
            $config = [
                'driver' => (string) $env('DB_DRIVER', 'mysql'),
                'host' => (string) $env('DB_HOST', '127.0.0.1'),
                'port' => (int) $env('DB_PORT', 3306),
                'database' => (string) $env('DB_DATABASE', 'taxsathi2'),
                'charset' => (string) $env('DB_CHARSET', 'utf8mb4'),
                'username' => (string) $env('DB_USERNAME', 'taxsathi2'),
                'password' => (string) $env('DB_PASSWORD', ''),
            ];
        }

        if (($config['database'] ?? '') === '') {
            throw new RuntimeException('Database configuration is missing the database name.');
        }
        return $config;
    }

    private static function normalizeDatabaseConfig(array $config): array
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
            'password' => (string) ($config['password'] ?? ''),
        ];
    }
}
