<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\NotificationService;
use RuntimeException;

final class OrderWorkflowReminderController extends Controller
{
    private ?Database $database = null;

    private const DEFAULT_TURNAROUND_DAYS = 2;

    private const CLOSED_ORDER_STATUSES = [
        'completed',
        'approved',
        'cancelled',
        'canceled',
        'rejected',
        'refunded',
        'closed',
    ];

    private const PAID_PAYMENT_STATUSES = [
        'paid',
        'completed',
        'success',
        'captured',
        'received',
        'verified',
    ];

    public function run(): void
    {
        $this->guardCron();

        $slot = $this->resolveSlot();
        $force = (int) ($_GET['force'] ?? 0) === 1;

        $result = $this->sendPendingOrderWorkflowReminders($slot, $force);

        $this->json([
            'success' => true,
            'message' => 'Pending order workflow reminders processed.',
            'data' => $result,
        ]);
    }

    private function sendPendingOrderWorkflowReminders(string $slot, bool $force = false): array
    {
        $this->ensureReminderTables();

        $orders = $this->pendingOrders();

        $summary = [
            'slot' => $slot,
            'orders_checked' => count($orders),
            'notifications_created' => 0,
            'duplicates_skipped' => 0,
            'orders_without_recipients' => 0,
        ];

        foreach ($orders as $order) {
            $recipients = $this->recipientsForOrder($order);

            if ($recipients === []) {
                $summary['orders_without_recipients']++;
                continue;
            }

            foreach ($recipients as $recipient) {
                $created = $this->notifyRecipientForOrder($order, $recipient, $slot, $force);

                if ($created) {
                    $summary['notifications_created']++;
                } else {
                    $summary['duplicates_skipped']++;
                }
            }
        }

        return $summary;
    }

    private function notifyRecipientForOrder(array $order, array $recipient, string $slot, bool $force): bool
    {
        $orderId = (int) ($order['id'] ?? 0);
        $userId = (int) ($recipient['id'] ?? 0);

        if ($orderId <= 0 || $userId <= 0) {
            return false;
        }

        $today = date('Y-m-d');

        if (!$force && $this->reminderAlreadySent($orderId, $userId, $today, $slot)) {
            return false;
        }

        $workflow = $this->workflowStage($order);
        $tat = $this->turnaroundStatus($order);
        $roleLabel = $this->recipientRoleLabel((int) ($recipient['role_id'] ?? 0));

        $title = $this->professionalTitle($order, $workflow, $tat, $roleLabel);

        $message = sprintf(
            '%s | Client: %s | Service: %s | Order Status: %s | Client Uploads: %d | Upload Deliverables: %d | Payment Status: %s | Turn Around: %s',
            (string) ($order['order_no'] ?? 'Order'),
            (string) ($order['client_name'] ?? '-'),
            (string) ($order['service_title'] ?? '-'),
            $this->humanize((string) ($order['status'] ?? 'pending')),
            (int) ($order['client_upload_count'] ?? 0),
            (int) ($order['deliverable_count'] ?? 0),
            $this->humanize((string) ($order['payment_status'] ?? 'pending')),
            $tat['label']
        );

        $payload = [
            'order_id' => $orderId,
            'order_no' => (string) ($order['order_no'] ?? ''),
            'client_name' => (string) ($order['client_name'] ?? ''),
            'service_title' => (string) ($order['service_title'] ?? ''),
            'order_status' => (string) ($order['status'] ?? ''),
            'payment_status' => (string) ($order['payment_status'] ?? ''),
            'client_uploads' => (int) ($order['client_upload_count'] ?? 0),
            'deliverables' => (int) ($order['deliverable_count'] ?? 0),
            'turnaround_days' => (int) ($order['turnaround_days'] ?? self::DEFAULT_TURNAROUND_DAYS),
            'due_date' => $tat['due_date'],
            'tat_status' => $tat['status'],
            'workflow_stage' => $workflow['stage'],
            'recipient_role' => $roleLabel,
            'slot' => $slot,
        ];

        $this->insertInAppNotification(
            userId: $userId,
            title: $title,
            message: $message,
            kind: 'order',
            statusLabel: $tat['label'] . ' • ' . $workflow['label'],
            severity: $tat['severity'],
            url: base_url('admin/orders/show?id=' . $orderId),
            payload: $payload
        );

        $this->safeTriggerNotification('order_workflow_reminder', [
            'user_id' => $userId,
            'name' => (string) ($recipient['name'] ?? ''),
            'email' => (string) ($recipient['email'] ?? ''),
            'phone' => (string) ($recipient['phone'] ?? ''),
            'title' => $title,
            'message' => $message,
            'order_no' => (string) ($order['order_no'] ?? ''),
            'service' => (string) ($order['service_title'] ?? ''),
            'client' => (string) ($order['client_name'] ?? ''),
            'status' => (string) ($order['status'] ?? ''),
            'payment_status' => (string) ($order['payment_status'] ?? ''),
            'client_uploads' => (string) ((int) ($order['client_upload_count'] ?? 0)),
            'deliverables' => (string) ((int) ($order['deliverable_count'] ?? 0)),
            'turnaround' => $tat['label'],
            'url' => base_url('admin/orders/show?id=' . $orderId),
        ]);

        $this->logReminder($orderId, $userId, $today, $slot, $workflow['stage']);

        return true;
    }

    private function pendingOrders(): array
    {
        if (!$this->tableExists('orders')) {
            return [];
        }

        $orderColumns = $this->tableColumns('orders');
        $serviceColumns = $this->tableExists('services') ? $this->tableColumns('services') : [];

        $statusSelect = in_array('status', $orderColumns, true) ? 'o.status' : "'pending'";
        $paymentStatusSelect = in_array('payment_status', $orderColumns, true) ? 'o.payment_status' : "'pending'";
        $assignedSelect = in_array('assigned_user_id', $orderColumns, true) ? 'o.assigned_user_id' : '0';
        $createdSelect = in_array('created_at', $orderColumns, true) ? 'o.created_at' : 'NOW()';
        $updatedSelect = in_array('updated_at', $orderColumns, true) ? 'o.updated_at' : 'NULL';

        $serviceJoin = $this->tableExists('services') && in_array('service_id', $orderColumns, true)
            ? 'LEFT JOIN services s ON s.id = o.service_id'
            : '';

        $serviceTitleSelect = $serviceJoin !== '' && in_array('title', $serviceColumns, true)
            ? "COALESCE(s.title, 'Service')"
            : "'Service'";

        $turnaroundSelect = (string) self::DEFAULT_TURNAROUND_DAYS;

        foreach (['turnaround_days', 'turn_around_days', 'tat_days', 'delivery_days', 'processing_days'] as $column) {
            if ($serviceJoin !== '' && in_array($column, $serviceColumns, true)) {
                $turnaroundSelect = "COALESCE(NULLIF(s.`{$column}`, 0), " . self::DEFAULT_TURNAROUND_DAYS . ")";
                break;
            }
        }

        $dueDateSelect = 'NULL';
        foreach (['due_date', 'target_date', 'tat_due_date', 'expected_completion_date'] as $column) {
            if (in_array($column, $orderColumns, true)) {
                $dueDateSelect = "o.`{$column}`";
                break;
            }
        }

        $clientJoin = '';
        $clientNameSelect = "'Client'";
        $clientEmailSelect = "''";
        $clientPhoneSelect = "''";

        if ($this->tableExists('customer_details')) {
            $clientJoin .= ' LEFT JOIN customer_details cd ON cd.order_id = o.id ';
            $clientNameSelect = "COALESCE(NULLIF(cd.name_as_per_pan, ''), 'Client')";
            $clientEmailSelect = "COALESCE(NULLIF(cd.email, ''), '')";
            $clientPhoneSelect = "COALESCE(NULLIF(cd.mobile, ''), '')";
        }

        if ($this->tableExists('clients') && in_array('client_id', $orderColumns, true)) {
            $clientJoin .= ' LEFT JOIN clients cl ON cl.id = o.client_id ';
            $clientNameSelect = "COALESCE(NULLIF(cd.name_as_per_pan, ''), NULLIF(cl.company_name, ''), NULLIF(cl.name, ''), 'Client')";
            $clientEmailSelect = "COALESCE(NULLIF(cd.email, ''), NULLIF(cl.email, ''), '')";
            $clientPhoneSelect = "COALESCE(NULLIF(cd.mobile, ''), NULLIF(cl.phone, ''), '')";
        }

        $documentCounts = '0 AS client_upload_count, 0 AS deliverable_count';

        if ($this->tableExists('order_documents')) {
            $documentCounts = "
                COALESCE((
                    SELECT COUNT(*)
                    FROM order_documents od
                    WHERE od.order_id = o.id
                      AND od.source IN ('client', 'client_upload', 'customer')
                ), 0) AS client_upload_count,
                COALESCE((
                    SELECT COUNT(*)
                    FROM order_documents od
                    WHERE od.order_id = o.id
                      AND od.source IN ('admin_output', 'deliverable', 'output')
                ), 0) AS deliverable_count
            ";
        }

        $closedPlaceholders = [];
        $params = [];

        foreach (self::CLOSED_ORDER_STATUSES as $i => $status) {
            $key = 'closed_' . $i;
            $closedPlaceholders[] = ':' . $key;
            $params[$key] = $status;
        }

        $sql = "
            SELECT
                o.id,
                COALESCE(o.order_no, CONCAT('ORDER-', o.id)) AS order_no,
                {$statusSelect} AS status,
                {$paymentStatusSelect} AS payment_status,
                {$assignedSelect} AS assigned_user_id,
                {$createdSelect} AS created_at,
                {$updatedSelect} AS updated_at,
                {$dueDateSelect} AS due_date,
                {$serviceTitleSelect} AS service_title,
                {$turnaroundSelect} AS turnaround_days,
                {$clientNameSelect} AS client_name,
                {$clientEmailSelect} AS client_email,
                {$clientPhoneSelect} AS client_phone,
                {$documentCounts}
            FROM orders o
            {$serviceJoin}
            {$clientJoin}
            WHERE LOWER(COALESCE({$statusSelect}, 'pending')) NOT IN (" . implode(',', $closedPlaceholders) . ")
            ORDER BY
                CASE
                    WHEN {$dueDateSelect} IS NOT NULL THEN {$dueDateSelect}
                    ELSE {$createdSelect}
                END ASC,
                o.id ASC
            LIMIT 1000
        ";

        try {
            return $this->db()->fetchAll($sql, $params) ?: [];
        } catch (\Throwable $e) {
            error_log('Pending workflow reminder query failed: ' . $e->getMessage());
            return [];
        }
    }

    private function recipientsForOrder(array $order): array
    {
        if (!$this->tableExists('users')) {
            return [];
        }

        $userColumns = $this->tableColumns('users');

        $select = [
            'id',
            in_array('name', $userColumns, true) ? 'name' : "'' AS name",
            in_array('email', $userColumns, true) ? 'email' : "'' AS email",
            in_array('phone', $userColumns, true) ? 'phone' : (in_array('mobile', $userColumns, true) ? 'mobile AS phone' : "'' AS phone"),
            in_array('role_id', $userColumns, true) ? 'role_id' : '0 AS role_id',
        ];

        $where = 'WHERE role_id IN (1, 2)';
        if (in_array('is_active', $userColumns, true)) {
            $where .= ' AND COALESCE(is_active, 1) = 1';
        }

        $recipients = $this->db()->fetchAll(
            'SELECT ' . implode(', ', $select) . ' FROM users ' . $where,
            []
        ) ?: [];

        $assignedUserId = (int) ($order['assigned_user_id'] ?? 0);

        if ($assignedUserId > 0) {
            $assignedWhere = 'WHERE id = :id AND role_id = 3';

            if (in_array('is_active', $userColumns, true)) {
                $assignedWhere .= ' AND COALESCE(is_active, 1) = 1';
            }

            $assigned = $this->db()->fetch(
                'SELECT ' . implode(', ', $select) . ' FROM users ' . $assignedWhere . ' LIMIT 1',
                ['id' => $assignedUserId]
            );

            if (is_array($assigned) && $assigned !== []) {
                $recipients[] = $assigned;
            }
        }

        $deduped = [];

        foreach ($recipients as $recipient) {
            $id = (int) ($recipient['id'] ?? 0);

            if ($id <= 0) {
                continue;
            }

            $deduped[$id] = $recipient;
        }

        return array_values($deduped);
    }

    private function workflowStage(array $order): array
    {
        $clientUploads = (int) ($order['client_upload_count'] ?? 0);
        $deliverables = (int) ($order['deliverable_count'] ?? 0);
        $paymentStatus = strtolower(trim((string) ($order['payment_status'] ?? '')));
        $orderStatus = strtolower(trim((string) ($order['status'] ?? '')));

        if ($clientUploads <= 0) {
            return [
                'stage' => 'client_upload_pending',
                'label' => 'Client Upload Pending',
            ];
        }

        if (!in_array($paymentStatus, self::PAID_PAYMENT_STATUSES, true)) {
            return [
                'stage' => 'payment_pending',
                'label' => 'Payment Pending',
            ];
        }

        if (in_array($orderStatus, ['pending', 'pending_review', 'processing', 'work_in_progress'], true) && $deliverables <= 0) {
            return [
                'stage' => 'deliverables_pending',
                'label' => 'Deliverables Pending',
            ];
        }

        if ($deliverables > 0) {
            return [
                'stage' => 'final_review_pending',
                'label' => 'Final Review Pending',
            ];
        }

        return [
            'stage' => 'workflow_action_pending',
            'label' => 'Workflow Action Pending',
        ];
    }

    private function turnaroundStatus(array $order): array
    {
        $turnaroundDays = max(1, (int) ($order['turnaround_days'] ?? self::DEFAULT_TURNAROUND_DAYS));
        $createdAt = trim((string) ($order['created_at'] ?? 'now'));
        $dueDateRaw = trim((string) ($order['due_date'] ?? ''));

        try {
            if ($dueDateRaw !== '') {
                $due = new \DateTimeImmutable($dueDateRaw);
            } else {
                $base = new \DateTimeImmutable($createdAt !== '' ? $createdAt : 'now');
                $due = $base->modify('+' . $turnaroundDays . ' days');
            }
        } catch (\Throwable $e) {
            $due = (new \DateTimeImmutable('now'))->modify('+' . $turnaroundDays . ' days');
        }

        $today = new \DateTimeImmutable(date('Y-m-d'));
        $dueDay = new \DateTimeImmutable($due->format('Y-m-d'));

        $daysRemaining = (int) $today->diff($dueDay)->format('%r%a');

        if ($daysRemaining < 0) {
            return [
                'status' => 'overdue',
                'label' => 'TAT Breached by ' . abs($daysRemaining) . ' day(s)',
                'severity' => 'high',
                'due_date' => $due->format('Y-m-d'),
                'days_remaining' => $daysRemaining,
            ];
        }

        if ($daysRemaining === 0) {
            return [
                'status' => 'due_today',
                'label' => 'Due Today',
                'severity' => 'high',
                'due_date' => $due->format('Y-m-d'),
                'days_remaining' => 0,
            ];
        }

        if ($daysRemaining === 1) {
            return [
                'status' => 'due_tomorrow',
                'label' => 'Due Tomorrow',
                'severity' => 'normal',
                'due_date' => $due->format('Y-m-d'),
                'days_remaining' => 1,
            ];
        }

        return [
            'status' => 'on_track',
            'label' => $daysRemaining . ' day(s) left',
            'severity' => 'normal',
            'due_date' => $due->format('Y-m-d'),
            'days_remaining' => $daysRemaining,
        ];
    }

    private function professionalTitle(array $order, array $workflow, array $tat, string $roleLabel): string
    {
        $orderNo = (string) ($order['order_no'] ?? 'Order');

        if ($roleLabel === 'Admin') {
            return $tat['label'] . ': ' . $workflow['label'] . ' – ' . $orderNo;
        }

        if ($roleLabel === 'Manager') {
            return 'Manager Review Required: ' . $workflow['label'] . ' – ' . $orderNo;
        }

        if ($roleLabel === 'Executive') {
            return 'Action Required: ' . $workflow['label'] . ' – ' . $orderNo;
        }

        return 'Workflow Reminder: ' . $workflow['label'] . ' – ' . $orderNo;
    }

    private function insertInAppNotification(
        int $userId,
        string $title,
        string $message,
        string $kind,
        string $statusLabel,
        string $severity,
        string $url,
        array $payload
    ): void {
        $this->ensureNotificationsTable();

        $columns = $this->tableColumns('notifications');

        $uid = 'owr_' . sha1($userId . '|' . $title . '|' . date('Y-m-d H:i:s') . '|' . random_int(1000, 999999));

        $data = [
            'uid' => $uid,
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'body' => $message,
            'kind' => $kind,
            'status_label' => $statusLabel,
            'severity' => $severity,
            'url' => $url,
            'data_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'is_read' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $allowed = [];

        foreach ($data as $column => $value) {
            if (in_array($column, $columns, true)) {
                $allowed[$column] = $value;
            }
        }

        if ($allowed === []) {
            return;
        }

        $columnSql = implode(', ', array_map(static fn (string $column): string => "`{$column}`", array_keys($allowed)));
        $valueSql = implode(', ', array_map(static fn (string $column): string => ":{$column}", array_keys($allowed)));

        try {
            $this->db()->execute(
                "INSERT INTO notifications ({$columnSql}) VALUES ({$valueSql})",
                $allowed
            );
        } catch (\Throwable $e) {
            error_log('In-app notification insert failed: ' . $e->getMessage());
        }
    }

    private function reminderAlreadySent(int $orderId, int $userId, string $date, string $slot): bool
    {
        $row = $this->db()->fetch(
            'SELECT id
             FROM order_workflow_reminder_logs
             WHERE order_id = :order_id
               AND user_id = :user_id
               AND reminder_date = :reminder_date
               AND reminder_slot = :reminder_slot
             LIMIT 1',
            [
                'order_id' => $orderId,
                'user_id' => $userId,
                'reminder_date' => $date,
                'reminder_slot' => $slot,
            ]
        );

        return is_array($row) && $row !== [];
    }

    private function logReminder(int $orderId, int $userId, string $date, string $slot, string $stage): void
    {
        try {
            $this->db()->execute(
                'INSERT INTO order_workflow_reminder_logs
                (
                    order_id,
                    user_id,
                    reminder_date,
                    reminder_slot,
                    workflow_stage,
                    created_at
                )
                VALUES
                (
                    :order_id,
                    :user_id,
                    :reminder_date,
                    :reminder_slot,
                    :workflow_stage,
                    :created_at
                )',
                [
                    'order_id' => $orderId,
                    'user_id' => $userId,
                    'reminder_date' => $date,
                    'reminder_slot' => $slot,
                    'workflow_stage' => $stage,
                    'created_at' => date('Y-m-d H:i:s'),
                ]
            );
        } catch (\Throwable $e) {
            error_log('Order workflow reminder log failed: ' . $e->getMessage());
        }
    }

    private function safeTriggerNotification(string $event, array $payload = []): void
    {
        if (!class_exists(NotificationService::class)) {
            return;
        }

        if (!method_exists(NotificationService::class, 'trigger')) {
            return;
        }

        try {
            NotificationService::trigger($event, $payload);
        } catch (\Throwable $e) {
            error_log('Workflow notification trigger failed: ' . $e->getMessage());
        }
    }

    private function ensureReminderTables(): void
    {
        $this->db()->execute(
            'CREATE TABLE IF NOT EXISTS order_workflow_reminder_logs (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                order_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                reminder_date DATE NOT NULL,
                reminder_slot VARCHAR(20) NOT NULL,
                workflow_stage VARCHAR(80) NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_order_user_date_slot (order_id, user_id, reminder_date, reminder_slot),
                KEY idx_order_id (order_id),
                KEY idx_user_id (user_id),
                KEY idx_reminder_date (reminder_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->ensureNotificationsTable();
    }

    private function ensureNotificationsTable(): void
    {
        $this->db()->execute(
            'CREATE TABLE IF NOT EXISTS notifications (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                uid VARCHAR(80) NOT NULL,
                user_id BIGINT UNSIGNED NULL,
                title VARCHAR(255) NOT NULL,
                message TEXT NULL,
                body TEXT NULL,
                kind VARCHAR(50) NOT NULL DEFAULT "notification",
                status_label VARCHAR(120) NULL,
                severity VARCHAR(30) NOT NULL DEFAULT "normal",
                url VARCHAR(600) NULL,
                data_json LONGTEXT NULL,
                payload LONGTEXT NULL,
                is_read TINYINT(1) NOT NULL DEFAULT 0,
                read_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_uid (uid),
                KEY idx_user_read_created (user_id, is_read, created_at),
                KEY idx_kind_created (kind, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    private function resolveSlot(): string
    {
        $slot = strtolower(trim((string) ($_GET['slot'] ?? '')));

        if (in_array($slot, ['morning', 'evening'], true)) {
            return $slot;
        }

        $hour = (int) date('G');

        return $hour < 14 ? 'morning' : 'evening';
    }

    private function recipientRoleLabel(int $roleId): string
    {
        return match ($roleId) {
            1 => 'Admin',
            2 => 'Manager',
            3 => 'Executive',
            default => 'User',
        };
    }

    private function humanize(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '-';
        }

        return ucwords(str_replace(['_', '-'], ' ', $value));
    }

    private function guardCron(): void
    {
        $configuredToken = '';

        if (function_exists('env')) {
            $configuredToken = trim((string) env('ORDER_WORKFLOW_REMINDER_TOKEN', ''));
        }

        if ($configuredToken === '' && function_exists('setting')) {
            $configuredToken = trim((string) setting('order_workflow_reminder_token', ''));
        }

        if ($configuredToken === '') {
            return;
        }

        $givenToken = trim((string) ($_GET['token'] ?? ''));

        if (!hash_equals($configuredToken, $givenToken)) {
            http_response_code(403);
            $this->json([
                'success' => false,
                'message' => 'Invalid cron token.',
            ]);
            exit;
        }
    }

    private function tableExists(string $table): bool
    {
        try {
            $row = $this->db()->fetch(
                'SELECT COUNT(*) AS total
                 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :table',
                ['table' => $table]
            );

            return (int) ($row['total'] ?? 0) > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function tableColumns(string $table): array
    {
        try {
            $rows = $this->db()->fetchAll(
                'SELECT COLUMN_NAME
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :table',
                ['table' => $table]
            ) ?: [];

            return array_map(static fn (array $row): string => (string) $row['COLUMN_NAME'], $rows);
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function db(): Database
    {
        if (!$this->database instanceof Database) {
            $this->database = new Database($this->databaseConfig());
        }

        return $this->database;
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
            } catch (\Throwable $e) {
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

    private function json(array $payload): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}