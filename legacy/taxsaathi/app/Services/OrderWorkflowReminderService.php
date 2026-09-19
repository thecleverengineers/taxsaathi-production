<?php

declare(strict_types=1);

namespace App\Services;

use Throwable;

final class OrderWorkflowReminderService
{
    private mixed $db;
    private ?string $assignmentColumn = null;
    private ?string $turnaroundColumn = null;
    private ?array $adminManagerUserIds = null;

    public function __construct()
    {
        $this->db = app('db');
    }

    /**
     * Creates workflow reminder rows once per order/user/date/slot.
     * Run this from cron twice daily with slot=morning and slot=evening.
     */
    public function run(?string $slot = null): array
    {
        $slot = $this->normalizeSlot($slot ?: $this->currentSlot());
        $today = date('Y-m-d');
        $now = date('Y-m-d H:i:s');

        $orders = $this->fetchActiveOrders();

        $result = [
            'slot' => $slot,
            'date' => $today,
            'orders_checked' => count($orders),
            'reminders_created' => 0,
            'duplicates_skipped' => 0,
            'recipients_checked' => 0,
            'errors' => [],
        ];

        foreach ($orders as $order) {
            $stage = $this->inferWorkflowStage($order);

            if ($stage === 'completed') {
                continue;
            }

            $recipientIds = $this->recipientUserIdsForOrder($order);
            $result['recipients_checked'] += count($recipientIds);

            foreach ($recipientIds as $userId) {
                try {
                    if ($this->workflowLogExists((int) $order['id'], $userId, $today, $slot)) {
                        $result['duplicates_skipped']++;
                        continue;
                    }

                    $this->dbWrite(
                        "INSERT INTO order_workflow_reminder_logs
                            (order_id, user_id, reminder_date, reminder_slot, workflow_stage, created_at)
                         VALUES
                            (:order_id, :user_id, :reminder_date, :reminder_slot, :workflow_stage, :created_at)",
                        [
                            'order_id' => (int) $order['id'],
                            'user_id' => $userId,
                            'reminder_date' => $today,
                            'reminder_slot' => $slot,
                            'workflow_stage' => $stage,
                            'created_at' => $now,
                        ]
                    );

                    $result['reminders_created']++;
                    $this->dispatchPushIfSupported($userId, $order, $stage, $slot);
                } catch (Throwable $e) {
                    $result['errors'][] = [
                        'order_id' => (int) ($order['id'] ?? 0),
                        'user_id' => $userId,
                        'message' => $e->getMessage(),
                    ];
                }
            }
        }

        return $result;
    }

    /**
     * Virtual notification items consumed by the existing notification dropdown.
     */
    public function latestReminderItemsForUser(int $userId, int $limit = 20): array
    {
        if ($userId <= 0) {
            return [];
        }

        $limit = max(1, min(50, $limit));
        $turnaroundSelect = $this->serviceTurnaroundSelectExpression();

        try {
            $rows = $this->db->fetchAll(
                "SELECT
                    l.id AS reminder_id,
                    l.order_id,
                    l.reminder_date,
                    l.reminder_slot,
                    l.workflow_stage,
                    l.created_at AS reminder_created_at,
                    o.order_no,
                    o.status,
                    o.payment_status,
                    o.created_at,
                    o.updated_at,
                    o.approved_at,
                    {$this->assignmentSelectExpression()} AS assigned_user_id,
                    COALESCE(s.title, CONCAT('Service #', o.service_id)) AS service_title,
                    {$turnaroundSelect} AS turnaround_days,
                    (SELECT COUNT(*) FROM order_documents od WHERE od.order_id = o.id AND od.source = 'client') AS client_upload_count,
                    (SELECT COUNT(*) FROM order_documents od WHERE od.order_id = o.id AND od.source = 'payment_proof') AS payment_proof_count,
                    (SELECT COUNT(*) FROM order_documents od WHERE od.order_id = o.id AND (od.source IN ('admin_output', 'deliverable', 'delivery') OR od.is_client_visible = 1)) AS deliverable_count
                 FROM order_workflow_reminder_logs l
                 INNER JOIN orders o ON o.id = l.order_id
                 LEFT JOIN services s ON s.id = o.service_id
                 WHERE l.user_id = :user_id
                 ORDER BY l.created_at DESC, l.id DESC
                 LIMIT {$limit}",
                ['user_id' => $userId]
            ) ?: [];
        } catch (Throwable $e) {
            return [];
        }

        $items = [];

        foreach ($rows as $row) {
            $stage = (string) ($row['workflow_stage'] ?? $this->inferWorkflowStage($row));
            $timing = $this->turnaroundTiming($row);

            $items[] = [
                'uid' => 'workflow-reminder-' . (int) $row['reminder_id'],
                'kind' => 'workflow',
                'title' => $this->titleForStage($stage, $timing),
                'message' => $this->messageForOrder($row, $stage, $timing),
                'status_label' => $this->statusLabelForTiming($timing),
                'created_at' => (string) ($row['reminder_created_at'] ?? ''),
                'timestamp' => strtotime((string) ($row['reminder_created_at'] ?? '')) ?: 0,
                'severity' => ($timing['state'] ?? '') === 'overdue' || in_array($stage, ['payment_pending', 'approval_pending', 'assignment_pending'], true) ? 'high' : 'normal',
                'url' => $this->adminOrderDetailUrl((int) $row['order_id']),
            ];
        }

        return $items;
    }

    public function countTodayRemindersForUser(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        try {
            return (int) ($this->db->scalar(
                "SELECT COUNT(*)
                   FROM order_workflow_reminder_logs
                  WHERE user_id = :user_id
                    AND reminder_date = :today",
                [
                    'user_id' => $userId,
                    'today' => date('Y-m-d'),
                ]
            ) ?? 0);
        } catch (Throwable $e) {
            return 0;
        }
    }

    private function fetchActiveOrders(): array
    {
        $turnaroundSelect = $this->serviceTurnaroundSelectExpression();

        try {
            return $this->db->fetchAll(
                "SELECT
                    o.id,
                    o.order_no,
                    o.client_id,
                    o.service_id,
                    o.status,
                    o.payment_status,
                    o.created_at,
                    o.updated_at,
                    o.approved_at,
                    o.completed_at,
                    {$this->assignmentSelectExpression()} AS assigned_user_id,
                    COALESCE(s.title, CONCAT('Service #', o.service_id)) AS service_title,
                    {$turnaroundSelect} AS turnaround_days,
                    (SELECT COUNT(*) FROM order_documents od WHERE od.order_id = o.id AND od.source = 'client') AS client_upload_count,
                    (SELECT COUNT(*) FROM order_documents od WHERE od.order_id = o.id AND od.source = 'payment_proof') AS payment_proof_count,
                    (SELECT COUNT(*) FROM order_documents od WHERE od.order_id = o.id AND (od.source IN ('admin_output', 'deliverable', 'delivery') OR od.is_client_visible = 1)) AS deliverable_count
                 FROM orders o
                 LEFT JOIN services s ON s.id = o.service_id
                 WHERE LOWER(COALESCE(o.status, '')) NOT IN ('completed', 'complete', 'cancelled', 'canceled', 'rejected', 'closed', 'delivered')
                 ORDER BY o.created_at ASC, o.id ASC
                 LIMIT 500"
            ) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    private function recipientUserIdsForOrder(array $order): array
    {
        $ids = $this->adminManagerUserIds();
        $assignedId = (int) ($order['assigned_user_id'] ?? 0);

        if ($assignedId > 0 && $this->isExecutiveUser($assignedId)) {
            $ids[] = $assignedId;
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        sort($ids);

        return $ids;
    }

    private function adminManagerUserIds(): array
    {
        if ($this->adminManagerUserIds !== null) {
            return $this->adminManagerUserIds;
        }

        $activeSql = $this->activeUserSqlCondition();

        try {
            $rows = $this->db->fetchAll(
                "SELECT id FROM users WHERE role_id IN (1, 2) {$activeSql}"
            ) ?: [];
        } catch (Throwable $e) {
            $rows = [];
        }

        $this->adminManagerUserIds = array_values(array_unique(array_map(
            static fn (array $row): int => (int) ($row['id'] ?? 0),
            $rows
        )));

        return $this->adminManagerUserIds;
    }

    private function isExecutiveUser(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $activeSql = $this->activeUserSqlCondition();

        try {
            return (int) ($this->db->scalar(
                "SELECT COUNT(*) FROM users WHERE id = :id AND role_id = 3 {$activeSql}",
                ['id' => $userId]
            ) ?? 0) > 0;
        } catch (Throwable $e) {
            return false;
        }
    }

    private function activeUserSqlCondition(): string
    {
        foreach (['is_active', 'active', 'status'] as $column) {
            if (!$this->tableHasColumn('users', $column)) {
                continue;
            }

            if ($column === 'status') {
                return " AND LOWER(COALESCE(status, 'active')) IN ('active', 'enabled', '1')";
            }

            return " AND COALESCE(`{$column}`, 1) = 1";
        }

        return '';
    }

    private function inferWorkflowStage(array $order): string
    {
        $status = strtolower(trim((string) ($order['status'] ?? '')));
        $paymentStatus = strtolower(trim((string) ($order['payment_status'] ?? '')));
        $assignedId = (int) ($order['assigned_user_id'] ?? 0);
        $deliverableCount = (int) ($order['deliverable_count'] ?? 0);

        if (in_array($status, ['completed', 'complete', 'closed', 'delivered'], true)) {
            return 'completed';
        }

        if (!$this->isPaymentDone($paymentStatus)) {
            return 'payment_pending';
        }

        if (in_array($status, ['submitted', 'pending_review', 'review', 'under_review'], true) || trim((string) ($order['approved_at'] ?? '')) === '') {
            return 'approval_pending';
        }

        if ($assignedId <= 0) {
            return 'assignment_pending';
        }

        if ($deliverableCount <= 0) {
            return 'executive_work_pending';
        }

        return 'completion_pending';
    }

    private function isPaymentDone(string $paymentStatus): bool
    {
        return in_array($paymentStatus, [
            'paid', 'verified', 'success', 'successful', 'completed', 'captured', 'settled', 'approved', 'received',
        ], true);
    }

    private function turnaroundTiming(array $order): array
    {
        $turnaroundDays = max(1, (int) ($order['turnaround_days'] ?? 3));
        $anchor = trim((string) ($order['approved_at'] ?? ''));

        if ($anchor === '') {
            $anchor = trim((string) ($order['created_at'] ?? ''));
        }

        $anchorTs = strtotime($anchor) ?: time();
        $dueTs = strtotime('+' . $turnaroundDays . ' days', $anchorTs) ?: $anchorTs;
        $todayMidnight = strtotime(date('Y-m-d 00:00:00')) ?: time();
        $dueMidnight = strtotime(date('Y-m-d 00:00:00', $dueTs)) ?: $dueTs;
        $daysLeft = (int) floor(($dueMidnight - $todayMidnight) / 86400);

        $state = 'on_track';
        if ($daysLeft < 0) {
            $state = 'overdue';
        } elseif ($daysLeft === 0) {
            $state = 'due_today';
        } elseif ($daysLeft === 1) {
            $state = 'due_tomorrow';
        }

        return [
            'turnaround_days' => $turnaroundDays,
            'anchor' => date('Y-m-d', $anchorTs),
            'due_date' => date('Y-m-d', $dueTs),
            'days_left' => $daysLeft,
            'state' => $state,
        ];
    }

    private function titleForStage(string $stage, array $timing): string
    {
        $prefix = match ((string) ($timing['state'] ?? 'on_track')) {
            'overdue' => 'Overdue: ',
            'due_today' => 'Due Today: ',
            'due_tomorrow' => 'Due Tomorrow: ',
            default => '',
        };

        return $prefix . match ($stage) {
            'payment_pending' => 'Payment Verification Required',
            'approval_pending' => 'Order Approval Required',
            'assignment_pending' => 'Executive Assignment Required',
            'executive_work_pending' => 'Workflow Progress Reminder',
            'completion_pending' => 'Deliverable Completion Pending',
            default => 'Order Workflow Reminder',
        };
    }

    private function messageForOrder(array $order, string $stage, array $timing): string
    {
        $orderNo = trim((string) ($order['order_no'] ?? ('#' . (int) ($order['id'] ?? $order['order_id'] ?? 0))));
        $service = trim((string) ($order['service_title'] ?? 'Service'));
        $status = strtoupper((string) ($order['status'] ?? 'pending'));
        $payment = strtoupper((string) ($order['payment_status'] ?? 'pending'));
        $clientUploads = (int) ($order['client_upload_count'] ?? 0);
        $deliverables = (int) ($order['deliverable_count'] ?? 0);
        $tat = (int) ($timing['turnaround_days'] ?? 3);
        $due = (string) ($timing['due_date'] ?? '');
        $daysLeft = (int) ($timing['days_left'] ?? 0);

        $deadlineText = $daysLeft < 0
            ? abs($daysLeft) . ' day(s) overdue'
            : ($daysLeft === 0 ? 'due today' : $daysLeft . ' day(s) left');

        $nextAction = match ($stage) {
            'payment_pending' => 'Verify payment or follow up for payment proof.',
            'approval_pending' => 'Review documents and approve the order.',
            'assignment_pending' => 'Assign this order to an executive.',
            'executive_work_pending' => 'Executive should update progress and upload deliverables.',
            'completion_pending' => 'Review deliverables and mark the order completed.',
            default => 'Review current order progress.',
        };

        return "{$orderNo} • {$service} • Status: {$status} • Payment: {$payment} • Client uploads: {$clientUploads} • Deliverables: {$deliverables} • TAT: {$tat} day(s), due {$due} ({$deadlineText}). {$nextAction}";
    }

    private function statusLabelForTiming(array $timing): string
    {
        return match ((string) ($timing['state'] ?? 'on_track')) {
            'overdue' => 'OVERDUE',
            'due_today' => 'DUE TODAY',
            'due_tomorrow' => 'DUE TOMORROW',
            default => 'ON TRACK',
        };
    }

    private function workflowLogExists(int $orderId, int $userId, string $date, string $slot): bool
    {
        try {
            return (int) ($this->db->scalar(
                "SELECT COUNT(*)
                   FROM order_workflow_reminder_logs
                  WHERE order_id = :order_id
                    AND user_id = :user_id
                    AND reminder_date = :reminder_date
                    AND reminder_slot = :reminder_slot",
                [
                    'order_id' => $orderId,
                    'user_id' => $userId,
                    'reminder_date' => $date,
                    'reminder_slot' => $slot,
                ]
            ) ?? 0) > 0;
        } catch (Throwable $e) {
            return false;
        }
    }

    private function normalizeSlot(string $slot): string
    {
        $slot = strtolower(trim($slot));
        return in_array($slot, ['morning', 'evening'], true) ? $slot : $this->currentSlot();
    }

    private function currentSlot(): string
    {
        return ((int) date('G')) < 14 ? 'morning' : 'evening';
    }

    private function dispatchPushIfSupported(int $userId, array $order, string $stage, string $slot): void
    {
        try {
            if (!class_exists(FirebasePushService::class)) {
                return;
            }

            $service = new FirebasePushService();
            $timing = $this->turnaroundTiming($order);
            $title = $this->titleForStage($stage, $timing);
            $body = $this->messageForOrder($order, $stage, $timing);
            $url = $this->adminOrderDetailUrl((int) ($order['id'] ?? 0));

            foreach (['sendToUser', 'sendUserNotification', 'sendToUserId', 'notifyUser'] as $method) {
                if (method_exists($service, $method)) {
                    $service->{$method}($userId, $title, $body, [
                        'url' => $url,
                        'tag' => 'workflow-' . (int) ($order['id'] ?? 0) . '-' . $slot,
                        'kind' => 'workflow',
                    ]);
                    return;
                }
            }
        } catch (Throwable $e) {
            // Browser/SSE dropdown reminders still work even if push is not supported.
        }
    }

    private function assignmentSelectExpression(): string
    {
        $column = $this->resolveOrderAssignmentColumn();
        return $column !== null ? "o.`{$column}`" : 'NULL';
    }

    private function resolveOrderAssignmentColumn(): ?string
    {
        if ($this->assignmentColumn !== null) {
            return $this->assignmentColumn;
        }

        foreach (['assigned_user_id', 'assigned_to', 'assigned_staff_id', 'staff_id', 'handler_id'] as $column) {
            if ($this->tableHasColumn('orders', $column)) {
                $this->assignmentColumn = $column;
                return $this->assignmentColumn;
            }
        }

        return null;
    }

    private function serviceTurnaroundSelectExpression(): string
    {
        $column = $this->resolveTurnaroundColumn();

        if ($column !== null) {
            return "COALESCE(NULLIF(s.`{$column}`, 0), 3)";
        }

        return '3';
    }

    private function resolveTurnaroundColumn(): ?string
    {
        if ($this->turnaroundColumn !== null) {
            return $this->turnaroundColumn;
        }

        foreach (['turnaround_days', 'turn_around_days', 'tat_days', 'duration_days', 'processing_days'] as $column) {
            if ($this->tableHasColumn('services', $column)) {
                $this->turnaroundColumn = $column;
                return $this->turnaroundColumn;
            }
        }

        return null;
    }

    private function tableHasColumn(string $table, string $column): bool
    {
        try {
            $row = $this->db->fetch("SHOW COLUMNS FROM `{$table}` LIKE :column", ['column' => $column]);
            return is_array($row) && !empty($row);
        } catch (Throwable $e) {
            return false;
        }
    }

    private function adminOrderDetailUrl(int $orderId): string
    {
        return function_exists('base_url')
            ? base_url('admin/orders/show?id=' . $orderId)
            : '/admin/orders/show?id=' . $orderId;
    }

    private function dbWrite(string $sql, array $params = []): void
    {
        if (method_exists($this->db, 'execute')) {
            $this->db->execute($sql, $params);
            return;
        }

        if (method_exists($this->db, 'statement')) {
            $this->db->statement($sql, $params);
            return;
        }

        if (method_exists($this->db, 'query')) {
            $this->db->query($sql, $params);
            return;
        }

        if (method_exists($this->db, 'pdo')) {
            $stmt = $this->db->pdo()->prepare($sql);
            $stmt->execute($params);
            return;
        }

        if (property_exists($this->db, 'pdo') && $this->db->pdo instanceof \PDO) {
            $stmt = $this->db->pdo->prepare($sql);
            $stmt->execute($params);
            return;
        }

        throw new \RuntimeException('Database write method not supported.');
    }
}