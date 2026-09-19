<?php

declare(strict_types=1);

/**
 * NotificationManagerController
 * -----------------------------------------------------------------------------
 * Production notification system for Tax Saathi.
 *
 * What this controller does:
 * - Stores every notification in one table: notification_manager.
 * - Serves JSON for badge/dropdown/notification center.
 * - Marks read/unread from the same table.
 * - Generates idempotent notifications from a per-minute cron without token.
 * - Applies role rules:
 *   Client    => own order updates only.
 *   Executive => assigned orders only.
 *   Manager   => all order/payment/document/TAT reminders.
 *   Admin     => all manager alerts + account creation + partner order alerts.
 */
final class NotificationManagerController
{
    private const TABLE = 'notification_manager';
    private const DEFAULT_LIMIT = 15;
    private const MAX_LIMIT = 100;

    /**
     * Full notification center page.
     */
    public function index(): void
    {
        if (!$this->isLoggedIn()) {
            $this->redirect($this->baseUrl('auth'));
            return;
        }

        $this->ensureTable();
        $user = $this->currentUser();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $filter = trim((string) ($_GET['filter'] ?? 'all'));
        $limit = 30;
        $offset = ($page - 1) * $limit;
        $data = $this->getUserNotifications((int) $user['id'], $limit, $offset, $filter);

        if (function_exists('view')) {
            echo view('notifications/index', [
                'title' => 'Notification Center',
                'items' => $data['items'],
                'summary' => $data['summary'],
                'page' => $page,
                'filter' => $filter,
            ]);
            return;
        }

        $this->renderFallbackCenter($data['items'], $data['summary'], $page, $filter);
    }

    /**
     * Dropdown + badge feed. Default limit is 15.
     */
    public function feed(): void
    {
        if (!$this->isLoggedIn()) {
            $this->json([
                'ok' => false,
                'message' => 'Unauthorized',
                'unread_count' => 0,
                'items' => [],
                'summary' => ['unread' => 0, 'total' => 0],
            ], 401);
            return;
        }

        $this->ensureTable();
        $user = $this->currentUser();
        $limit = min(self::MAX_LIMIT, max(1, (int) ($_GET['limit'] ?? self::DEFAULT_LIMIT)));
        $afterId = max(0, (int) ($_GET['after_id'] ?? 0));
        $filter = trim((string) ($_GET['filter'] ?? 'all'));
        $data = $this->getUserNotifications((int) $user['id'], $limit, 0, $filter, $afterId);

        $this->json([
            'ok' => true,
            'engine' => 'notification-manager-v1',
            'generated_at' => date('Y-m-d H:i:s'),
            'unread_count' => $data['summary']['unread'],
            'latest_id' => $data['summary']['latest_id'],
            'items' => $data['items'],
            'summary' => $data['summary'],
            'see_more_url' => $this->baseUrl('notifications'),
        ]);
    }

    /**
     * Lightweight count endpoint for badge-only refresh.
     */
    public function count(): void
    {
        if (!$this->isLoggedIn()) {
            $this->json(['ok' => false, 'unread_count' => 0], 401);
            return;
        }

        $this->ensureTable();
        $userId = (int) $this->currentUser()['id'];
        $unread = $this->scalar(
            'SELECT COUNT(*) FROM `' . self::TABLE . '` WHERE recipient_user_id = :uid AND is_read = 0',
            ['uid' => $userId]
        );
        $latestId = $this->scalar(
            'SELECT COALESCE(MAX(id), 0) FROM `' . self::TABLE . '` WHERE recipient_user_id = :uid',
            ['uid' => $userId]
        );

        $this->json(['ok' => true, 'unread_count' => $unread, 'latest_id' => $latestId]);
    }

    public function markRead(): void
    {
        $this->markReadState(true);
    }

    public function markUnread(): void
    {
        $this->markReadState(false);
    }

    public function markAllRead(): void
    {
        if (!$this->isLoggedIn()) {
            $this->json(['ok' => false, 'message' => 'Unauthorized'], 401);
            return;
        }

        $this->ensureTable();
        $userId = (int) $this->currentUser()['id'];
        $this->execute(
            'UPDATE `' . self::TABLE . '` SET is_read = 1, read_at = NOW(), updated_at = NOW() WHERE recipient_user_id = :uid AND is_read = 0',
            ['uid' => $userId]
        );

        $this->json(['ok' => true, 'message' => 'All notifications marked as read.', 'unread_count' => 0]);
    }

    /**
     * Mark as read and redirect to notification URL.
     */
    public function open(): void
    {
        if (!$this->isLoggedIn()) {
            $this->redirect($this->baseUrl('auth'));
            return;
        }

        $this->ensureTable();
        $id = max(0, (int) ($_GET['id'] ?? 0));
        $userId = (int) $this->currentUser()['id'];
        $row = $this->fetchOne(
            'SELECT id, url FROM `' . self::TABLE . '` WHERE id = :id AND recipient_user_id = :uid LIMIT 1',
            ['id' => $id, 'uid' => $userId]
        );

        if (!$row) {
            $this->redirect($this->baseUrl('notifications'));
            return;
        }

        $this->execute(
            'UPDATE `' . self::TABLE . '` SET is_read = 1, read_at = COALESCE(read_at, NOW()), updated_at = NOW() WHERE id = :id AND recipient_user_id = :uid',
            ['id' => $id, 'uid' => $userId]
        );

        $url = trim((string) ($row['url'] ?? '')) ?: $this->baseUrl('notifications');
        $this->redirect($url);
    }

    /**
     * Per-minute cron endpoint without token.
     * aaPanel cron command example:
     * curl -fsS "https://yourdomain.com/notifications/cron" >/dev/null 2>&1
     */
    public function cron(): void
    {
        // Token check intentionally removed as requested.
        // Keep this route away from public navigation; aaPanel can call it every minute.
        $this->ensureTable();

        $stats = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'orders_checked' => 0,
            'partner_orders_checked' => 0,
            'accounts_checked' => 0,
            'legacy_checked' => 0,
            'legacy_imported' => 0,
            'legacy_skipped' => 0,
            'errors' => [],
        ];

        try {
            $this->importExistingNotifications($stats);
        } catch (Throwable $e) {
            $stats['errors'][] = 'legacy_import: ' . $e->getMessage();
            error_log('NotificationManager cron legacy import failed: ' . $e->getMessage());
        }

        try {
            $this->generateOrderNotifications($stats);
        } catch (Throwable $e) {
            $stats['errors'][] = 'orders: ' . $e->getMessage();
            error_log('NotificationManager cron orders failed: ' . $e->getMessage());
        }

        try {
            $this->generateAccountNotifications($stats);
        } catch (Throwable $e) {
            $stats['errors'][] = 'accounts: ' . $e->getMessage();
            error_log('NotificationManager cron accounts failed: ' . $e->getMessage());
        }

        try {
            $this->generatePartnerOrderNotifications($stats);
        } catch (Throwable $e) {
            $stats['errors'][] = 'partner_orders: ' . $e->getMessage();
            error_log('NotificationManager cron partner orders failed: ' . $e->getMessage());
        }

        $this->json([
            'ok' => empty($stats['errors']),
            'engine' => 'notification-manager-v1',
            'generated_at' => date('Y-m-d H:i:s'),
            'stats' => $stats,
        ], empty($stats['errors']) ? 200 : 207);
    }

    public function health(): void
    {
        $this->ensureTable();
        $this->json([
            'ok' => true,
            'engine' => 'notification-manager-v1',
            'time' => date('Y-m-d H:i:s'),
            'tables' => [
                'notification_manager' => $this->tableExists(self::TABLE),
                'orders' => $this->tableExists('orders'),
                'order_documents' => $this->tableExists('order_documents'),
                'users' => $this->tableExists('users'),
                'partners' => $this->tableExists('partners'),
                'partner_orders' => $this->tableExists('partner_orders'),
                'partner_order_documents' => $this->tableExists('partner_order_documents'),
                'legacy_notifications' => $this->tableExists('notifications'),
                'activity_logs' => $this->tableExists('activity_logs'),
                'order_workflow_reminder_logs' => $this->tableExists('order_workflow_reminder_logs'),
            ],
        ]);
    }

    private function markReadState(bool $read): void
    {
        if (!$this->isLoggedIn()) {
            $this->json(['ok' => false, 'message' => 'Unauthorized'], 401);
            return;
        }

        $this->ensureTable();
        $body = $this->requestData();
        $id = max(0, (int) ($body['id'] ?? $_POST['id'] ?? $_GET['id'] ?? 0));
        $userId = (int) $this->currentUser()['id'];

        if ($id <= 0) {
            $this->json(['ok' => false, 'message' => 'Missing notification id.'], 422);
            return;
        }

        $this->execute(
            'UPDATE `' . self::TABLE . '` SET is_read = :read, read_at = ' . ($read ? 'COALESCE(read_at, NOW())' : 'NULL') . ', updated_at = NOW() WHERE id = :id AND recipient_user_id = :uid',
            ['read' => $read ? 1 : 0, 'id' => $id, 'uid' => $userId]
        );

        $unread = $this->scalar(
            'SELECT COUNT(*) FROM `' . self::TABLE . '` WHERE recipient_user_id = :uid AND is_read = 0',
            ['uid' => $userId]
        );

        $this->json([
            'ok' => true,
            'message' => $read ? 'Notification marked as read.' : 'Notification marked as unread.',
            'id' => $id,
            'is_read' => $read,
            'unread_count' => $unread,
        ]);
    }

    private function getUserNotifications(int $userId, int $limit, int $offset = 0, string $filter = 'all', int $afterId = 0): array
    {
        $where = ['recipient_user_id = :uid'];
        $params = ['uid' => $userId];

        if ($filter === 'unread') {
            $where[] = 'is_read = 0';
        } elseif ($filter === 'read') {
            $where[] = 'is_read = 1';
        }

        if ($afterId > 0) {
            $where[] = 'id > :after_id';
            $params['after_id'] = $afterId;
        }

        $whereSql = implode(' AND ', $where);
        $rows = $this->fetchAll(
            'SELECT * FROM `' . self::TABLE . '` WHERE ' . $whereSql . ' ORDER BY id DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset,
            $params
        );

        $items = array_map(fn(array $row): array => $this->formatNotification($row), $rows);

        $summary = [
            'total' => $this->scalar('SELECT COUNT(*) FROM `' . self::TABLE . '` WHERE recipient_user_id = :uid', ['uid' => $userId]),
            'unread' => $this->scalar('SELECT COUNT(*) FROM `' . self::TABLE . '` WHERE recipient_user_id = :uid AND is_read = 0', ['uid' => $userId]),
            'critical' => $this->scalar('SELECT COUNT(*) FROM `' . self::TABLE . '` WHERE recipient_user_id = :uid AND is_read = 0 AND severity = :severity', ['uid' => $userId, 'severity' => 'critical']),
            'latest_id' => $this->scalar('SELECT COALESCE(MAX(id), 0) FROM `' . self::TABLE . '` WHERE recipient_user_id = :uid', ['uid' => $userId]),
        ];

        return ['items' => $items, 'summary' => $summary];
    }

    private function formatNotification(array $row): array
    {
        $payload = json_decode((string) ($row['payload_json'] ?? '{}'), true);
        if (!is_array($payload)) {
            $payload = [];
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'uid' => (string) ($row['uid'] ?? ''),
            'type' => (string) ($row['type'] ?? 'notification'),
            'event_key' => (string) ($row['event_key'] ?? 'general'),
            'title' => (string) ($row['title'] ?? 'Notification'),
            'message' => (string) ($row['message'] ?? ''),
            'severity' => (string) ($row['severity'] ?? 'normal'),
            'status_label' => (string) ($row['status_label'] ?? ''),
            'url' => (string) ($row['url'] ?? $this->baseUrl('notifications')),
            'open_url' => $this->baseUrl('notifications/open?id=' . (int) ($row['id'] ?? 0)),
            'is_read' => (bool) ((int) ($row['is_read'] ?? 0)),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
            'read_at' => (string) ($row['read_at'] ?? ''),
            'order_id' => isset($row['order_id']) ? (int) $row['order_id'] : null,
            'order_no' => (string) ($row['order_no'] ?? ''),
            'payload' => $payload,
        ];
    }

    /**
     * Order notifications for clients, executives, managers and admins.
     */
    private function generateOrderNotifications(array &$stats): void
    {
        if (!$this->tableExists('orders')) {
            return;
        }

        $orders = $this->fetchAll('SELECT * FROM `orders` ORDER BY COALESCE(updated_at, created_at) DESC, id DESC LIMIT 500');
        $admins = $this->usersByRole('admin');
        $managers = $this->usersByRole('manager');
        $today = date('Ymd');

        foreach ($orders as $order) {
            $stats['orders_checked']++;

            $orderId = (int) ($order['id'] ?? 0);
            if ($orderId <= 0) {
                continue;
            }

            $orderStatus = $this->normalizeStatus((string) ($order['status'] ?? 'submitted'));
            if (in_array($orderStatus, ['cancelled', 'canceled', 'deleted'], true)) {
                continue;
            }

            $paymentStatus = $this->normalizeStatus((string) ($order['payment_status'] ?? 'pending'));
            $orderNo = $this->orderNo($order);
            $orderUrl = $this->baseUrl('admin/orders/show?id=' . $orderId);
            $clientId = (int) ($order['client_id'] ?? 0);
            $assignedUserId = (int) ($order['assigned_user_id'] ?? $order['executive_id'] ?? 0);
            $documentStats = $this->documentStats($orderId, 'order_documents');
            $clientRecipients = $this->clientRecipients($clientId);
            $executiveRecipients = $assignedUserId > 0 ? $this->usersByIds([$assignedUserId], 'executive') : [];
            $turnaround = $this->turnaroundInfo($order);
            $paymentNeedsAction = in_array($paymentStatus, ['waiting_for_payment', 'pending', 'pending_review', 'unpaid', 'partial', 'partially_paid', 'failed'], true);
            $pendingOrSubmitted = in_array($orderStatus, ['pending_review', 'submitted', 'pending'], true);
            $withinTurnaroundWindow = !$turnaround['completed'] && $turnaround['days_elapsed'] <= $turnaround['turnaround_days'];
            $missingDocs = $documentStats['total'] <= 0 || $documentStats['client'] <= 0;
            $payload = $this->orderPayload($order, $documentStats, $turnaround);

            /** Client: own orders only. */
            foreach ($clientRecipients as $recipient) {
                $this->upsertNotification($stats, [
                    'recipient' => $recipient,
                    'recipient_type' => 'client',
                    'client_id' => $clientId,
                    'order_id' => $orderId,
                    'order_no' => $orderNo,
                    'source_table' => 'orders',
                    'source_id' => $orderId,
                    'type' => 'client_order_status',
                    'event_key' => 'client_order_status',
                    'stable_key' => 'client-status-' . $orderId . '-' . $orderStatus,
                    'title' => 'Order Status Updated',
                    'message' => $orderNo . ' status is now ' . $this->human($orderStatus) . '.',
                    'severity' => $orderStatus === 'completed' ? 'success' : 'info',
                    'status_label' => $this->human($orderStatus),
                    'url' => $this->baseUrl('client/orders/show?id=' . $orderId),
                    'payload' => $payload,
                ]);

                if ($missingDocs) {
                    $this->upsertNotification($stats, [
                        'recipient' => $recipient,
                        'recipient_type' => 'client',
                        'client_id' => $clientId,
                        'order_id' => $orderId,
                        'order_no' => $orderNo,
                        'source_table' => 'order_documents',
                        'source_id' => $orderId,
                        'type' => 'client_documents_missing',
                        'event_key' => 'documents_missing',
                        'stable_key' => 'client-docs-missing-' . $orderId,
                        'title' => 'Documents Required for Your Order',
                        'message' => $orderNo . ' has missing required documents. Please upload the pending files.',
                        'severity' => 'high',
                        'status_label' => 'DOCS MISSING',
                        'url' => $this->baseUrl('client/orders/show?id=' . $orderId),
                        'payload' => $payload,
                    ]);
                }

                $clientNoteHash = sha1(json_encode([
                    'notes' => (string) ($order['notes'] ?? ''),
                    'admin_notes' => (string) ($order['admin_notes'] ?? ''),
                    'completion_note' => (string) ($order['completion_note'] ?? ''),
                    'approved_by' => (string) ($order['approved_by'] ?? ''),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

                if ($clientNoteHash !== sha1(json_encode(['notes' => '', 'admin_notes' => '', 'completion_note' => '', 'approved_by' => '']))) {
                    $this->upsertNotification($stats, [
                        'recipient' => $recipient,
                        'recipient_type' => 'client',
                        'client_id' => $clientId,
                        'order_id' => $orderId,
                        'order_no' => $orderNo,
                        'source_table' => 'orders',
                        'source_id' => $orderId,
                        'type' => 'client_order_note',
                        'event_key' => 'client_order_note',
                        'stable_key' => 'client-note-' . $orderId . '-' . $clientNoteHash,
                        'title' => $orderStatus === 'completed' ? 'Completion Note Added' : 'Order Note Updated',
                        'message' => $this->compactMessage([
                            (string) ($order['completion_note'] ?? ''),
                            (string) ($order['admin_notes'] ?? ''),
                            (string) ($order['notes'] ?? ''),
                            (string) ($order['approved_by'] ?? '') !== '' ? 'Approved by: ' . (string) $order['approved_by'] : '',
                        ]),
                        'severity' => $orderStatus === 'completed' ? 'success' : 'info',
                        'status_label' => 'ORDER NOTE',
                        'url' => $this->baseUrl('client/orders/show?id=' . $orderId),
                        'payload' => $payload,
                    ]);
                }
            }

            /** Executive: only assigned orders. */
            foreach ($executiveRecipients as $recipient) {
                $this->upsertNotification($stats, [
                    'recipient' => $recipient,
                    'recipient_type' => 'executive',
                    'client_id' => $clientId,
                    'executive_user_id' => $assignedUserId,
                    'order_id' => $orderId,
                    'order_no' => $orderNo,
                    'source_table' => 'orders',
                    'source_id' => $orderId,
                    'type' => 'executive_assigned_order_status',
                    'event_key' => 'assigned_order_status',
                    'stable_key' => 'executive-status-' . $orderId . '-' . $orderStatus,
                    'title' => 'Assigned Order Status',
                    'message' => $orderNo . ' is assigned to you. Status: ' . $this->human($orderStatus) . '.',
                    'severity' => in_array($turnaround['due_state'], ['overdue', 'due_today'], true) ? 'critical' : 'info',
                    'status_label' => $this->human($orderStatus),
                    'url' => $orderUrl,
                    'payload' => $payload,
                ]);

                $this->upsertNotification($stats, [
                    'recipient' => $recipient,
                    'recipient_type' => 'executive',
                    'client_id' => $clientId,
                    'executive_user_id' => $assignedUserId,
                    'order_id' => $orderId,
                    'order_no' => $orderNo,
                    'source_table' => 'orders',
                    'source_id' => $orderId,
                    'type' => 'executive_payment_status',
                    'event_key' => 'payment_status',
                    'stable_key' => 'executive-payment-' . $orderId . '-' . $paymentStatus,
                    'title' => 'Assigned Order Payment Status',
                    'message' => $orderNo . ' payment status: ' . $this->human($paymentStatus) . '.',
                    'severity' => $paymentNeedsAction ? 'high' : 'success',
                    'status_label' => $this->human($paymentStatus),
                    'url' => $orderUrl,
                    'payload' => $payload,
                ]);

                if ($missingDocs) {
                    $this->upsertNotification($stats, [
                        'recipient' => $recipient,
                        'recipient_type' => 'executive',
                        'client_id' => $clientId,
                        'executive_user_id' => $assignedUserId,
                        'order_id' => $orderId,
                        'order_no' => $orderNo,
                        'source_table' => 'order_documents',
                        'source_id' => $orderId,
                        'type' => 'executive_documents_missing',
                        'event_key' => 'documents_missing',
                        'stable_key' => 'executive-docs-missing-' . $orderId,
                        'title' => 'Assigned Order Documents Missing',
                        'message' => $orderNo . ' has missing client documents. Follow up before processing.',
                        'severity' => 'critical',
                        'status_label' => 'DOCS MISSING',
                        'url' => $orderUrl,
                        'payload' => $payload,
                    ]);
                }
            }

            /** Manager + Admin: all orders. */
            foreach ([['type' => 'manager', 'users' => $managers], ['type' => 'admin', 'users' => $admins]] as $bucket) {
                foreach ($bucket['users'] as $recipient) {
                    $recipientType = $bucket['type'];

                    $this->upsertNotification($stats, [
                        'recipient' => $recipient,
                        'recipient_type' => $recipientType,
                        'client_id' => $clientId,
                        'executive_user_id' => $assignedUserId,
                        'order_id' => $orderId,
                        'order_no' => $orderNo,
                        'source_table' => 'orders',
                        'source_id' => $orderId,
                        'type' => 'new_order',
                        'event_key' => 'new_order',
                        'stable_key' => $recipientType . '-new-order-' . $orderId,
                        'title' => 'New Order Received',
                        'message' => $orderNo . ' requires review. Status: ' . $this->human($orderStatus) . '.',
                        'severity' => $pendingOrSubmitted ? 'high' : 'info',
                        'status_label' => 'NEW ORDER',
                        'url' => $orderUrl,
                        'payload' => $payload,
                    ]);

                    $this->upsertNotification($stats, [
                        'recipient' => $recipient,
                        'recipient_type' => $recipientType,
                        'client_id' => $clientId,
                        'executive_user_id' => $assignedUserId,
                        'order_id' => $orderId,
                        'order_no' => $orderNo,
                        'source_table' => 'orders',
                        'source_id' => $orderId,
                        'type' => 'order_status',
                        'event_key' => 'order_status',
                        'stable_key' => $recipientType . '-order-status-' . $orderId . '-' . $orderStatus,
                        'title' => 'Order Status Monitor',
                        'message' => $orderNo . ' status: ' . $this->human($orderStatus) . '.',
                        'severity' => in_array($turnaround['due_state'], ['overdue', 'due_today'], true) ? 'critical' : 'info',
                        'status_label' => $this->human($orderStatus),
                        'url' => $orderUrl,
                        'payload' => $payload,
                    ]);

                    $this->upsertNotification($stats, [
                        'recipient' => $recipient,
                        'recipient_type' => $recipientType,
                        'client_id' => $clientId,
                        'executive_user_id' => $assignedUserId,
                        'order_id' => $orderId,
                        'order_no' => $orderNo,
                        'source_table' => 'orders',
                        'source_id' => $orderId,
                        'type' => 'payment_status',
                        'event_key' => 'payment_status',
                        'stable_key' => $recipientType . '-payment-' . $orderId . '-' . $paymentStatus,
                        'title' => 'Payment Status Monitor',
                        'message' => $orderNo . ' payment status: ' . $this->human($paymentStatus) . '.',
                        'severity' => $paymentNeedsAction ? 'high' : 'success',
                        'status_label' => $this->human($paymentStatus),
                        'url' => $orderUrl,
                        'payload' => $payload,
                    ]);

                    if ($missingDocs) {
                        $this->upsertNotification($stats, [
                            'recipient' => $recipient,
                            'recipient_type' => $recipientType,
                            'client_id' => $clientId,
                            'executive_user_id' => $assignedUserId,
                            'order_id' => $orderId,
                            'order_no' => $orderNo,
                            'source_table' => 'order_documents',
                            'source_id' => $orderId,
                            'type' => 'order_documents_missing',
                            'event_key' => 'documents_missing',
                            'stable_key' => $recipientType . '-docs-missing-' . $orderId,
                            'title' => 'Order Documents Missing',
                            'message' => $orderNo . ' has missing documents for this order ID.',
                            'severity' => 'critical',
                            'status_label' => 'DOCS MISSING',
                            'url' => $orderUrl,
                            'payload' => $payload,
                        ]);
                    }

                    if ($pendingOrSubmitted && $paymentNeedsAction && $withinTurnaroundWindow) {
                        $this->upsertNotification($stats, [
                            'recipient' => $recipient,
                            'recipient_type' => $recipientType,
                            'client_id' => $clientId,
                            'executive_user_id' => $assignedUserId,
                            'order_id' => $orderId,
                            'order_no' => $orderNo,
                            'source_table' => 'orders',
                            'source_id' => $orderId,
                            'type' => 'daily_turnaround_reminder',
                            'event_key' => 'turnaround_payment_reminder',
                            'stable_key' => $recipientType . '-tat-reminder-' . $orderId . '-' . $today,
                            'title' => 'Daily Turnaround Reminder',
                            'message' => $orderNo . ' is ' . $this->human($orderStatus) . ' and payment is ' . $this->human($paymentStatus) . '. TAT: ' . $turnaround['turnaround_days'] . ' day(s), due state: ' . $this->human($turnaround['due_state']) . '.',
                            'severity' => in_array($turnaround['due_state'], ['overdue', 'due_today'], true) ? 'critical' : 'high',
                            'status_label' => 'TAT REMINDER',
                            'url' => $orderUrl,
                            'payload' => $payload,
                        ]);
                    }
                }
            }
        }
    }

    /**
     * Admin-only account creation notifications.
     */
    private function generateAccountNotifications(array &$stats): void
    {
        if (!$this->tableExists('users')) {
            return;
        }

        $admins = $this->usersByRole('admin');
        if ($admins === []) {
            return;
        }

        $createdColumn = $this->columnExists('users', 'created_at') ? 'created_at' : 'NOW() AS created_at';
        $nameColumn = $this->columnExists('users', 'name') ? 'name' : ($this->columnExists('users', 'full_name') ? 'full_name' : 'email');
        $emailColumn = $this->columnExists('users', 'email') ? 'email' : "'' AS email";
        $roleColumn = $this->columnExists('users', 'role_id') ? 'role_id' : '0 AS role_id';
        $rows = $this->fetchAll("SELECT id, {$nameColumn} AS display_name, {$emailColumn}, {$roleColumn}, {$createdColumn} FROM users ORDER BY id DESC LIMIT 250");

        foreach ($rows as $account) {
            $stats['accounts_checked']++;
            $accountId = (int) ($account['id'] ?? 0);
            if ($accountId <= 0) {
                continue;
            }

            $payload = [
                'account_id' => $accountId,
                'name' => (string) ($account['display_name'] ?? 'User'),
                'email' => (string) ($account['email'] ?? ''),
                'role_id' => (int) ($account['role_id'] ?? 0),
                'created_at' => (string) ($account['created_at'] ?? ''),
            ];

            foreach ($admins as $admin) {
                $this->upsertNotification($stats, [
                    'recipient' => $admin,
                    'recipient_type' => 'admin',
                    'actor_user_id' => $accountId,
                    'source_table' => 'users',
                    'source_id' => $accountId,
                    'type' => 'account_created',
                    'event_key' => 'account_created',
                    'stable_key' => 'admin-account-created-' . $accountId,
                    'title' => 'New Account Created',
                    'message' => trim((string) ($account['display_name'] ?? 'User')) . (((string) ($account['email'] ?? '')) !== '' ? ' • ' . (string) $account['email'] : ''),
                    'severity' => 'info',
                    'status_label' => 'ACCOUNT',
                    'url' => $this->baseUrl('admin/users'),
                    'payload' => $payload,
                ]);
            }
        }
    }

    /**
     * Admin partner order notifications. Supports partner_orders table when present,
     * and also supports normal orders.partner_id when partner_orders does not exist.
     */
    private function generatePartnerOrderNotifications(array &$stats): void
    {
        $admins = $this->usersByRole('admin');
        if ($admins === []) {
            return;
        }

        if ($this->tableExists('partner_orders')) {
            $rows = $this->fetchAll('SELECT * FROM `partner_orders` ORDER BY COALESCE(updated_at, created_at) DESC, id DESC LIMIT 300');
            foreach ($rows as $row) {
                $stats['partner_orders_checked']++;
                $partnerOrderId = (int) ($row['id'] ?? 0);
                if ($partnerOrderId <= 0) {
                    continue;
                }

                $partnerId = (int) ($row['partner_id'] ?? 0);
                $orderNo = trim((string) ($row['order_no'] ?? $row['partner_order_no'] ?? '')) ?: ('Partner Order #' . $partnerOrderId);
                $status = $this->normalizeStatus((string) ($row['status'] ?? 'submitted'));
                $docStats = $this->documentStats($partnerOrderId, 'partner_order_documents');
                $missingDocs = $docStats['total'] <= 0;
                $payload = [
                    'partner_order_id' => $partnerOrderId,
                    'partner_id' => $partnerId,
                    'order_no' => $orderNo,
                    'status' => $status,
                    'documents' => $docStats,
                ];

                foreach ($admins as $admin) {
                    $this->upsertNotification($stats, [
                        'recipient' => $admin,
                        'recipient_type' => 'admin',
                        'partner_id' => $partnerId,
                        'partner_order_id' => $partnerOrderId,
                        'order_no' => $orderNo,
                        'source_table' => 'partner_orders',
                        'source_id' => $partnerOrderId,
                        'type' => 'partner_order',
                        'event_key' => 'partner_order',
                        'stable_key' => 'admin-partner-order-' . $partnerOrderId . '-' . $status,
                        'title' => 'Partner Order Notification',
                        'message' => $orderNo . ' status: ' . $this->human($status) . '.',
                        'severity' => in_array($status, ['submitted', 'pending', 'pending_review'], true) ? 'high' : 'info',
                        'status_label' => 'PARTNER ORDER',
                        'url' => $this->baseUrl('admin/partner-orders'),
                        'payload' => $payload,
                    ]);

                    if ($missingDocs) {
                        $this->upsertNotification($stats, [
                            'recipient' => $admin,
                            'recipient_type' => 'admin',
                            'partner_id' => $partnerId,
                            'partner_order_id' => $partnerOrderId,
                            'order_no' => $orderNo,
                            'source_table' => 'partner_order_documents',
                            'source_id' => $partnerOrderId,
                            'type' => 'partner_order_documents_missing',
                            'event_key' => 'partner_documents_missing',
                            'stable_key' => 'admin-partner-docs-missing-' . $partnerOrderId,
                            'title' => 'Partner Order Documents Missing',
                            'message' => $orderNo . ' has no partner order documents uploaded.',
                            'severity' => 'critical',
                            'status_label' => 'PARTNER DOCS',
                            'url' => $this->baseUrl('admin/partner-orders'),
                            'payload' => $payload,
                        ]);
                    }
                }
            }
            return;
        }

        if ($this->tableExists('orders') && $this->columnExists('orders', 'partner_id')) {
            $rows = $this->fetchAll('SELECT * FROM `orders` WHERE partner_id IS NOT NULL AND partner_id > 0 ORDER BY COALESCE(updated_at, created_at) DESC, id DESC LIMIT 300');
            foreach ($rows as $row) {
                $stats['partner_orders_checked']++;
                $orderId = (int) ($row['id'] ?? 0);
                $partnerId = (int) ($row['partner_id'] ?? 0);
                $orderNo = $this->orderNo($row);
                $status = $this->normalizeStatus((string) ($row['status'] ?? 'submitted'));
                $docStats = $this->documentStats($orderId, 'order_documents');
                $missingDocs = $docStats['total'] <= 0;
                $payload = $this->orderPayload($row, $docStats, $this->turnaroundInfo($row));
                $payload['partner_id'] = $partnerId;

                foreach ($admins as $admin) {
                    $this->upsertNotification($stats, [
                        'recipient' => $admin,
                        'recipient_type' => 'admin',
                        'partner_id' => $partnerId,
                        'order_id' => $orderId,
                        'order_no' => $orderNo,
                        'source_table' => 'orders',
                        'source_id' => $orderId,
                        'type' => 'partner_order',
                        'event_key' => 'partner_order',
                        'stable_key' => 'admin-partner-normal-order-' . $orderId . '-' . $status,
                        'title' => 'Partner Order Notification',
                        'message' => $orderNo . ' from partner #' . $partnerId . ' status: ' . $this->human($status) . '.',
                        'severity' => in_array($status, ['submitted', 'pending', 'pending_review'], true) ? 'high' : 'info',
                        'status_label' => 'PARTNER ORDER',
                        'url' => $this->baseUrl('admin/orders/show?id=' . $orderId),
                        'payload' => $payload,
                    ]);

                    if ($missingDocs) {
                        $this->upsertNotification($stats, [
                            'recipient' => $admin,
                            'recipient_type' => 'admin',
                            'partner_id' => $partnerId,
                            'order_id' => $orderId,
                            'order_no' => $orderNo,
                            'source_table' => 'order_documents',
                            'source_id' => $orderId,
                            'type' => 'partner_order_documents_missing',
                            'event_key' => 'partner_documents_missing',
                            'stable_key' => 'admin-partner-normal-docs-missing-' . $orderId,
                            'title' => 'Partner Order Documents Missing',
                            'message' => $orderNo . ' from partner #' . $partnerId . ' has missing documents.',
                            'severity' => 'critical',
                            'status_label' => 'PARTNER DOCS',
                            'url' => $this->baseUrl('admin/orders/show?id=' . $orderId),
                            'payload' => $payload,
                        ]);
                    }
                }
            }
        }
    }

    /**
     * Imports old/stored notification sources into notification_manager on every cron run.
     * It is idempotent: each legacy row gets a stable UID per recipient, so cron can run
     * every minute without creating duplicates.
     */
    private function importExistingNotifications(array &$stats): void
    {
        $this->importLegacyNotificationsTable($stats);
        $this->importLegacyWorkflowReminderLogs($stats);
        $this->importLegacyActivityLogs($stats);
        $this->importLegacyPartnerActivityLogs($stats);
        $this->importLegacyNotificationLogs($stats);
    }

    private function importLegacyNotificationsTable(array &$stats): void
    {
        if (!$this->tableExists('notifications')) {
            return;
        }

        $rows = $this->fetchAll('SELECT * FROM `notifications` ORDER BY COALESCE(updated_at, created_at) ASC, id ASC LIMIT 5000');
        foreach ($rows as $row) {
            $stats['legacy_checked']++;
            $legacyId = (int) ($row['id'] ?? 0);
            if ($legacyId <= 0) {
                $stats['legacy_skipped']++;
                continue;
            }

            $legacyUid = trim((string) ($row['uid'] ?? ''));
            $payload = $this->legacyPayload($row);
            $recipients = $this->legacyRecipientsForRow($row, 'notifications');
            if ($recipients === []) {
                $stats['legacy_skipped']++;
                continue;
            }

            $type = trim((string) ($row['type'] ?? $row['kind'] ?? 'legacy_notification')) ?: 'legacy_notification';
            $eventKey = trim((string) ($row['event_key'] ?? $row['kind'] ?? $type)) ?: $type;
            $title = trim((string) ($row['title'] ?? 'Notification')) ?: 'Notification';
            $message = trim((string) ($row['message'] ?? $row['body'] ?? $row['description'] ?? '')) ?: $title;
            $severity = $this->safeSeverity((string) ($row['severity'] ?? 'info'));
            $statusLabel = trim((string) ($row['status_label'] ?? $row['label'] ?? 'NOTICE')) ?: 'NOTICE';
            $url = trim((string) ($row['url'] ?? $row['link'] ?? '')) ?: $this->baseUrl('notifications');
            $orderId = (int) ($row['order_id'] ?? ($payload['order_id'] ?? 0));
            $partnerOrderId = (int) ($row['partner_order_id'] ?? ($payload['partner_order_id'] ?? 0));
            $clientId = (int) ($row['client_id'] ?? ($payload['client_id'] ?? 0));
            $partnerId = (int) ($row['partner_id'] ?? ($payload['partner_id'] ?? 0));
            $sourceId = (int) ($row['source_id'] ?? $legacyId);
            $createdAt = $this->safeDate((string) ($row['created_at'] ?? ''));
            $updatedAt = $this->safeDate((string) ($row['updated_at'] ?? $row['created_at'] ?? ''));

            foreach ($recipients as $recipient) {
                $read = $this->legacyReadState($legacyUid, (int) ($recipient['id'] ?? 0), (bool) ((int) ($row['is_read'] ?? 0)));
                $this->upsertImportedNotification($stats, [
                    'recipient' => $recipient,
                    'recipient_type' => $this->recipientTypeFromUser($recipient),
                    'client_id' => $clientId > 0 ? $clientId : null,
                    'partner_id' => $partnerId > 0 ? $partnerId : null,
                    'order_id' => $orderId > 0 ? $orderId : null,
                    'partner_order_id' => $partnerOrderId > 0 ? $partnerOrderId : null,
                    'order_no' => (string) ($row['order_no'] ?? ($payload['order_no'] ?? '')),
                    'source_table' => 'notifications',
                    'source_id' => $sourceId,
                    'type' => $type,
                    'event_key' => $eventKey,
                    'legacy_key' => $legacyUid !== '' ? 'notifications-uid-' . $legacyUid : 'notifications-id-' . $legacyId,
                    'title' => $title,
                    'message' => $message,
                    'severity' => $severity,
                    'status_label' => $statusLabel,
                    'url' => $url,
                    'payload' => array_merge($payload, [
                        'legacy_table' => 'notifications',
                        'legacy_id' => $legacyId,
                        'legacy_uid' => $legacyUid,
                    ]),
                    'is_read' => $read['is_read'],
                    'read_at' => $read['read_at'],
                    'created_at' => $createdAt,
                    'updated_at' => $updatedAt,
                ]);
            }
        }
    }

    private function importLegacyWorkflowReminderLogs(array &$stats): void
    {
        if (!$this->tableExists('order_workflow_reminder_logs')) {
            return;
        }

        $rows = $this->fetchAll('SELECT * FROM `order_workflow_reminder_logs` ORDER BY COALESCE(created_at, id) ASC LIMIT 3000');
        foreach ($rows as $row) {
            $stats['legacy_checked']++;
            $legacyId = (int) ($row['id'] ?? 0);
            $orderId = (int) ($row['order_id'] ?? 0);
            if ($legacyId <= 0 || $orderId <= 0) {
                $stats['legacy_skipped']++;
                continue;
            }

            $recipients = [];
            $userId = (int) ($row['user_id'] ?? 0);
            if ($userId > 0) {
                $recipients = $this->usersByIds([$userId], 'user');
            }
            if ($recipients === []) {
                $order = $this->fetchOne('SELECT * FROM `orders` WHERE id = :id LIMIT 1', ['id' => $orderId]) ?: ['id' => $orderId];
                $recipients = $this->recipientsForOrder($order, true);
            }

            $createdAt = $this->safeDate((string) ($row['created_at'] ?? ''));
            $stage = $this->human((string) ($row['workflow_stage'] ?? 'Workflow'));
            foreach ($recipients as $recipient) {
                $this->upsertImportedNotification($stats, [
                    'recipient' => $recipient,
                    'recipient_type' => $this->recipientTypeFromUser($recipient),
                    'order_id' => $orderId,
                    'source_table' => 'order_workflow_reminder_logs',
                    'source_id' => $legacyId,
                    'type' => 'ai_workflow_reminder',
                    'event_key' => 'legacy_workflow_reminder',
                    'legacy_key' => 'workflow-reminder-log-' . $legacyId,
                    'title' => 'AI Auto Workflow Reminder Sent',
                    'message' => 'Order #' . $orderId . ' • ' . $stage . ' • Slot: ' . $this->human((string) ($row['reminder_slot'] ?? '')),
                    'severity' => 'high',
                    'status_label' => 'AI REMINDER',
                    'url' => $this->baseUrl('admin/orders/show?id=' . $orderId),
                    'payload' => array_merge($row, ['legacy_table' => 'order_workflow_reminder_logs', 'legacy_id' => $legacyId]),
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);
            }
        }
    }

    private function importLegacyActivityLogs(array &$stats): void
    {
        if (!$this->tableExists('activity_logs')) {
            return;
        }

        $rows = $this->fetchAll('SELECT * FROM `activity_logs` ORDER BY COALESCE(created_at, id) ASC LIMIT 3000');
        foreach ($rows as $row) {
            $stats['legacy_checked']++;
            $legacyId = (int) ($row['id'] ?? 0);
            if ($legacyId <= 0) {
                $stats['legacy_skipped']++;
                continue;
            }

            $orderId = (int) ($row['order_id'] ?? 0);
            $recipients = $orderId > 0
                ? $this->recipientsForOrder($this->fetchOne('SELECT * FROM `orders` WHERE id = :id LIMIT 1', ['id' => $orderId]) ?: ['id' => $orderId], true)
                : array_merge($this->usersByRole('admin'), $this->usersByRole('manager'));

            if ($recipients === []) {
                $stats['legacy_skipped']++;
                continue;
            }

            $createdAt = $this->safeDate((string) ($row['created_at'] ?? ''));
            foreach ($recipients as $recipient) {
                $this->upsertImportedNotification($stats, [
                    'recipient' => $recipient,
                    'recipient_type' => $this->recipientTypeFromUser($recipient),
                    'order_id' => $orderId > 0 ? $orderId : null,
                    'source_table' => 'activity_logs',
                    'source_id' => $legacyId,
                    'type' => 'work_progress',
                    'event_key' => 'legacy_activity_log',
                    'legacy_key' => 'activity-log-' . $legacyId,
                    'title' => $this->human((string) ($row['action'] ?? 'Activity')),
                    'message' => trim((string) ($row['description'] ?? 'Work progress updated.')) ?: 'Work progress updated.',
                    'severity' => 'info',
                    'status_label' => 'ACTIVITY',
                    'url' => $orderId > 0 ? $this->baseUrl('admin/orders/show?id=' . $orderId) : $this->baseUrl('notifications'),
                    'payload' => array_merge($this->legacyPayload($row), ['legacy_table' => 'activity_logs', 'legacy_id' => $legacyId]),
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);
            }
        }
    }

    private function importLegacyPartnerActivityLogs(array &$stats): void
    {
        if (!$this->tableExists('partner_activity_logs')) {
            return;
        }

        $admins = $this->usersByRole('admin');
        if ($admins === []) {
            return;
        }

        $rows = $this->fetchAll('SELECT * FROM `partner_activity_logs` ORDER BY COALESCE(created_at, id) ASC LIMIT 2000');
        foreach ($rows as $row) {
            $stats['legacy_checked']++;
            $legacyId = (int) ($row['id'] ?? 0);
            if ($legacyId <= 0) {
                $stats['legacy_skipped']++;
                continue;
            }

            $createdAt = $this->safeDate((string) ($row['created_at'] ?? ''));
            foreach ($admins as $admin) {
                $this->upsertImportedNotification($stats, [
                    'recipient' => $admin,
                    'recipient_type' => 'admin',
                    'partner_id' => (int) ($row['partner_id'] ?? 0) ?: null,
                    'source_table' => 'partner_activity_logs',
                    'source_id' => $legacyId,
                    'type' => 'partner_activity',
                    'event_key' => 'legacy_partner_activity',
                    'legacy_key' => 'partner-activity-log-' . $legacyId,
                    'title' => 'Partner Activity',
                    'message' => $this->human((string) ($row['action'] ?? 'Activity')) . ' • ' . trim((string) ($row['description'] ?? '')),
                    'severity' => 'info',
                    'status_label' => 'PARTNER',
                    'url' => $this->baseUrl('admin/partners'),
                    'payload' => array_merge($row, ['legacy_table' => 'partner_activity_logs', 'legacy_id' => $legacyId]),
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);
            }
        }
    }

    private function importLegacyNotificationLogs(array &$stats): void
    {
        if (!$this->tableExists('notification_logs')) {
            return;
        }

        $admins = $this->usersByRole('admin');
        if ($admins === []) {
            return;
        }

        $rows = $this->fetchAll("SELECT * FROM `notification_logs` ORDER BY COALESCE(created_at, id) ASC LIMIT 2000");
        foreach ($rows as $row) {
            $stats['legacy_checked']++;
            $legacyId = (int) ($row['id'] ?? 0);
            if ($legacyId <= 0) {
                $stats['legacy_skipped']++;
                continue;
            }

            $status = $this->normalizeStatus((string) ($row['status'] ?? 'logged'));
            $createdAt = $this->safeDate((string) ($row['created_at'] ?? ''));
            foreach ($admins as $admin) {
                $this->upsertImportedNotification($stats, [
                    'recipient' => $admin,
                    'recipient_type' => 'admin',
                    'source_table' => 'notification_logs',
                    'source_id' => $legacyId,
                    'type' => in_array($status, ['sent', 'success', 'delivered'], true) ? 'notification_delivery_log' : 'failed_notification',
                    'event_key' => 'legacy_notification_log',
                    'legacy_key' => 'notification-log-' . $legacyId,
                    'title' => in_array($status, ['sent', 'success', 'delivered'], true) ? 'Notification Delivery Log' : 'Notification Delivery Issue',
                    'message' => trim((string) ($row['channel'] ?? '')) . ' • ' . trim((string) ($row['recipient'] ?? '')) . ' • ' . $this->human($status),
                    'severity' => in_array($status, ['sent', 'success', 'delivered'], true) ? 'info' : 'critical',
                    'status_label' => 'DELIVERY',
                    'url' => $this->baseUrl('notifications'),
                    'payload' => array_merge($row, ['legacy_table' => 'notification_logs', 'legacy_id' => $legacyId]),
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);
            }
        }
    }

    private function upsertImportedNotification(array &$stats, array $data): void
    {
        $recipient = $data['recipient'] ?? null;
        if (!is_array($recipient) || (int) ($recipient['id'] ?? 0) <= 0) {
            $stats['legacy_skipped']++;
            return;
        }

        $recipientUserId = (int) $recipient['id'];
        $recipientRoleId = (int) ($recipient['role_id'] ?? 0);
        $recipientRole = (string) ($recipient['role_name'] ?? $data['recipient_type'] ?? 'user');
        $recipientType = $this->normalizeRecipientType((string) ($data['recipient_type'] ?? $recipientRole));
        $legacyKey = (string) ($data['legacy_key'] ?? (($data['source_table'] ?? 'legacy') . '-' . ($data['source_id'] ?? '0')));
        $uid = sha1($recipientUserId . '|legacy-import|' . $legacyKey);

        if ($this->fetchOne('SELECT id FROM `' . self::TABLE . '` WHERE uid = :uid LIMIT 1', ['uid' => $uid])) {
            $stats['legacy_skipped']++;
            return;
        }

        $payload = is_array($data['payload'] ?? null) ? $data['payload'] : [];
        $stateHash = sha1(json_encode([
            'title' => (string) ($data['title'] ?? ''),
            'message' => (string) ($data['message'] ?? ''),
            'severity' => (string) ($data['severity'] ?? 'normal'),
            'status_label' => (string) ($data['status_label'] ?? ''),
            'payload' => $payload,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $createdAt = $this->safeDate((string) ($data['created_at'] ?? ''));
        $updatedAt = $this->safeDate((string) ($data['updated_at'] ?? $createdAt));
        $isRead = !empty($data['is_read']) ? 1 : 0;
        $readAt = $isRead ? $this->safeNullableDate((string) ($data['read_at'] ?? '')) : null;

        $this->execute(
            'INSERT INTO `' . self::TABLE . '`
            (`uid`, `recipient_user_id`, `recipient_role_id`, `recipient_role`, `recipient_type`, `actor_user_id`, `client_id`, `partner_id`, `executive_user_id`, `order_id`, `partner_order_id`, `order_no`, `source_table`, `source_id`, `type`, `event_key`, `title`, `message`, `severity`, `status_label`, `url`, `state_hash`, `payload_json`, `is_read`, `read_at`, `created_at`, `updated_at`)
            VALUES
            (:uid, :recipient_user_id, :recipient_role_id, :recipient_role, :recipient_type, :actor_user_id, :client_id, :partner_id, :executive_user_id, :order_id, :partner_order_id, :order_no, :source_table, :source_id, :type, :event_key, :title, :message, :severity, :status_label, :url, :state_hash, :payload_json, :is_read, :read_at, :created_at, :updated_at)',
            [
                'uid' => $uid,
                'recipient_user_id' => $recipientUserId,
                'recipient_role_id' => $recipientRoleId > 0 ? $recipientRoleId : null,
                'recipient_role' => $recipientRole,
                'recipient_type' => $recipientType,
                'actor_user_id' => isset($data['actor_user_id']) ? (int) $data['actor_user_id'] : null,
                'client_id' => isset($data['client_id']) ? (int) $data['client_id'] : null,
                'partner_id' => isset($data['partner_id']) ? (int) $data['partner_id'] : null,
                'executive_user_id' => isset($data['executive_user_id']) ? (int) $data['executive_user_id'] : null,
                'order_id' => isset($data['order_id']) ? (int) $data['order_id'] : null,
                'partner_order_id' => isset($data['partner_order_id']) ? (int) $data['partner_order_id'] : null,
                'order_no' => (string) ($data['order_no'] ?? ''),
                'source_table' => (string) ($data['source_table'] ?? 'legacy'),
                'source_id' => isset($data['source_id']) ? (int) $data['source_id'] : null,
                'type' => (string) ($data['type'] ?? 'legacy_notification'),
                'event_key' => (string) ($data['event_key'] ?? 'legacy_import'),
                'title' => mb_substr((string) ($data['title'] ?? 'Notification'), 0, 255),
                'message' => (string) ($data['message'] ?? ''),
                'severity' => $this->safeSeverity((string) ($data['severity'] ?? 'normal')),
                'status_label' => mb_substr((string) ($data['status_label'] ?? ''), 0, 80),
                'url' => (string) ($data['url'] ?? $this->baseUrl('notifications')),
                'state_hash' => $stateHash,
                'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
                'is_read' => $isRead,
                'read_at' => $readAt,
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ]
        );

        $stats['legacy_imported']++;
        $stats['created']++;
    }

    private function legacyRecipientsForRow(array $row, string $sourceTable): array
    {
        $ids = [];
        foreach (['recipient_user_id', 'user_id', 'recipient_id', 'target_user_id'] as $column) {
            if (isset($row[$column]) && (int) $row[$column] > 0) {
                $ids[] = (int) $row[$column];
            }
        }

        if ($ids !== []) {
            return $this->uniqueUsers($this->usersByIds($ids, 'user'));
        }

        $clientId = (int) ($row['client_id'] ?? 0);
        if ($clientId > 0) {
            $clients = $this->clientRecipients($clientId);
            if ($clients !== []) {
                return $this->uniqueUsers($clients);
            }
        }

        $partnerId = (int) ($row['partner_id'] ?? 0);
        if ($partnerId > 0) {
            $partners = $this->partnerRecipients($partnerId);
            if ($partners !== []) {
                return $this->uniqueUsers($partners);
            }
        }

        $orderId = (int) ($row['order_id'] ?? 0);
        if ($orderId > 0 && $this->tableExists('orders')) {
            $order = $this->fetchOne('SELECT * FROM `orders` WHERE id = :id LIMIT 1', ['id' => $orderId]);
            if ($order) {
                return $this->recipientsForOrder($order, true);
            }
        }

        $kind = $this->normalizeStatus((string) ($row['kind'] ?? $row['type'] ?? $row['event_key'] ?? ''));
        if (str_contains($kind, 'account') || str_contains($kind, 'partner') || str_contains($kind, 'admin')) {
            return $this->uniqueUsers($this->usersByRole('admin'));
        }

        return $this->uniqueUsers(array_merge($this->usersByRole('admin'), $this->usersByRole('manager')));
    }

    private function recipientsForOrder(array $order, bool $includeAdminManager): array
    {
        $recipients = [];
        $clientId = (int) ($order['client_id'] ?? 0);
        if ($clientId > 0) {
            $recipients = array_merge($recipients, $this->clientRecipients($clientId));
        }

        $assignedUserId = (int) ($order['assigned_user_id'] ?? $order['executive_id'] ?? 0);
        if ($assignedUserId > 0) {
            $recipients = array_merge($recipients, $this->usersByIds([$assignedUserId], 'executive'));
        }

        if ($includeAdminManager) {
            $recipients = array_merge($recipients, $this->usersByRole('admin'), $this->usersByRole('manager'));
        }

        return $this->uniqueUsers($recipients);
    }

    private function partnerRecipients(int $partnerId): array
    {
        if ($partnerId <= 0 || !$this->tableExists('users')) {
            return [];
        }

        $conditions = [];
        $params = ['partner_id' => $partnerId];
        if ($this->columnExists('users', 'partner_id')) {
            $conditions[] = 'partner_id = :partner_id';
        }
        $conditions[] = 'id = :partner_id';

        $emailExpr = $this->columnExists('users', 'email') ? 'email' : "'' AS email";
        $nameExpr = $this->columnExists('users', 'name') ? 'name' : ($this->columnExists('users', 'full_name') ? 'full_name' : 'id');
        $roleIdExpr = $this->columnExists('users', 'role_id') ? 'role_id' : '0 AS role_id';
        $rows = $this->fetchAll("SELECT id, {$nameExpr} AS name, {$emailExpr}, {$roleIdExpr} FROM users WHERE (" . implode(' OR ', $conditions) . ")", $params);

        return array_map(static function (array $row): array {
            $row['role_name'] = 'partner';
            return $row;
        }, $rows);
    }

    private function uniqueUsers(array $users): array
    {
        $seen = [];
        $out = [];
        foreach ($users as $user) {
            $id = (int) ($user['id'] ?? 0);
            if ($id <= 0 || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $out[] = $user;
        }
        return $out;
    }

    private function recipientTypeFromUser(array $user): string
    {
        $roleName = strtolower(str_replace([' ', '_'], '-', (string) ($user['role_name'] ?? $user['role'] ?? $user['role_slug'] ?? '')));
        $roleId = (int) ($user['role_id'] ?? 0);

        if ($roleId === 1 || in_array($roleName, ['admin', 'administrator', 'super-admin', 'superadmin'], true)) {
            return 'admin';
        }
        if ($roleId === 2 || $roleName === 'manager') {
            return 'manager';
        }
        if ($roleId === 3 || in_array($roleName, ['executive', 'staff'], true)) {
            return 'executive';
        }
        if ($roleId === 4 || in_array($roleName, ['partner', 'partners'], true)) {
            return 'partner';
        }
        return 'client';
    }

    private function normalizeRecipientType(string $type): string
    {
        $type = strtolower(str_replace([' ', '_'], '-', trim($type)));
        return match ($type) {
            'admin', 'administrator', 'super-admin', 'superadmin' => 'admin',
            'manager' => 'manager',
            'executive', 'staff' => 'executive',
            'partner', 'partners' => 'partner',
            'system' => 'system',
            default => 'client',
        };
    }

    private function legacyPayload(array $row): array
    {
        foreach (['payload_json', 'data_json', 'payload', 'meta_json', 'meta'] as $column) {
            if (!isset($row[$column])) {
                continue;
            }
            $decoded = json_decode((string) $row[$column], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return [];
    }

    private function legacyReadState(string $legacyUid, int $recipientUserId, bool $defaultRead): array
    {
        $state = ['is_read' => $defaultRead, 'read_at' => null];
        if ($legacyUid === '' || $recipientUserId <= 0 || !$this->tableExists('notification_user_reads')) {
            return $state;
        }

        $row = $this->fetchOne(
            'SELECT is_read, read_at, updated_at FROM `notification_user_reads` WHERE user_id = :user_id AND notification_uid = :uid LIMIT 1',
            ['user_id' => $recipientUserId, 'uid' => $legacyUid]
        );
        if (!$row) {
            return $state;
        }

        $state['is_read'] = (bool) ((int) ($row['is_read'] ?? 0));
        $state['read_at'] = $this->safeNullableDate((string) ($row['read_at'] ?? $row['updated_at'] ?? ''));
        return $state;
    }

    private function safeDate(string $value): string
    {
        $ts = strtotime(trim($value));
        if (!$ts) {
            return date('Y-m-d H:i:s');
        }
        return date('Y-m-d H:i:s', $ts);
    }

    private function safeNullableDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $ts = strtotime($value);
        return $ts ? date('Y-m-d H:i:s', $ts) : null;
    }

    private function upsertNotification(array &$stats, array $data): void
    {
        $recipient = $data['recipient'] ?? null;
        if (!is_array($recipient) || (int) ($recipient['id'] ?? 0) <= 0) {
            $stats['skipped']++;
            return;
        }

        $recipientUserId = (int) $recipient['id'];
        $recipientRoleId = (int) ($recipient['role_id'] ?? 0);
        $recipientRole = (string) ($recipient['role_name'] ?? $data['recipient_type'] ?? 'user');
        $payload = is_array($data['payload'] ?? null) ? $data['payload'] : [];
        $stateHash = sha1(json_encode([
            'title' => (string) ($data['title'] ?? ''),
            'message' => (string) ($data['message'] ?? ''),
            'severity' => (string) ($data['severity'] ?? 'normal'),
            'status_label' => (string) ($data['status_label'] ?? ''),
            'payload' => $payload,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $stableKey = (string) ($data['stable_key'] ?? $data['event_key'] ?? 'general');
        $uid = sha1($recipientUserId . '|' . $stableKey);
        $existing = $this->fetchOne('SELECT id, state_hash FROM `' . self::TABLE . '` WHERE uid = :uid LIMIT 1', ['uid' => $uid]);

        $params = [
            'uid' => $uid,
            'recipient_user_id' => $recipientUserId,
            'recipient_role_id' => $recipientRoleId > 0 ? $recipientRoleId : null,
            'recipient_role' => $recipientRole,
            'recipient_type' => (string) ($data['recipient_type'] ?? 'client'),
            'actor_user_id' => isset($data['actor_user_id']) ? (int) $data['actor_user_id'] : null,
            'client_id' => isset($data['client_id']) ? (int) $data['client_id'] : null,
            'partner_id' => isset($data['partner_id']) ? (int) $data['partner_id'] : null,
            'executive_user_id' => isset($data['executive_user_id']) ? (int) $data['executive_user_id'] : null,
            'order_id' => isset($data['order_id']) ? (int) $data['order_id'] : null,
            'partner_order_id' => isset($data['partner_order_id']) ? (int) $data['partner_order_id'] : null,
            'order_no' => (string) ($data['order_no'] ?? ''),
            'source_table' => (string) ($data['source_table'] ?? ''),
            'source_id' => isset($data['source_id']) ? (int) $data['source_id'] : null,
            'type' => (string) ($data['type'] ?? 'notification'),
            'event_key' => (string) ($data['event_key'] ?? 'general'),
            'title' => mb_substr((string) ($data['title'] ?? 'Notification'), 0, 255),
            'message' => (string) ($data['message'] ?? ''),
            'severity' => $this->safeSeverity((string) ($data['severity'] ?? 'normal')),
            'status_label' => mb_substr((string) ($data['status_label'] ?? ''), 0, 80),
            'url' => (string) ($data['url'] ?? $this->baseUrl('notifications')),
            'state_hash' => $stateHash,
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
        ];

        if (!$existing) {
            $this->execute(
                'INSERT INTO `' . self::TABLE . '`
                (`uid`, `recipient_user_id`, `recipient_role_id`, `recipient_role`, `recipient_type`, `actor_user_id`, `client_id`, `partner_id`, `executive_user_id`, `order_id`, `partner_order_id`, `order_no`, `source_table`, `source_id`, `type`, `event_key`, `title`, `message`, `severity`, `status_label`, `url`, `state_hash`, `payload_json`, `is_read`, `created_at`, `updated_at`)
                VALUES
                (:uid, :recipient_user_id, :recipient_role_id, :recipient_role, :recipient_type, :actor_user_id, :client_id, :partner_id, :executive_user_id, :order_id, :partner_order_id, :order_no, :source_table, :source_id, :type, :event_key, :title, :message, :severity, :status_label, :url, :state_hash, :payload_json, 0, NOW(), NOW())',
                $params
            );
            $stats['created']++;
            return;
        }

        if ((string) ($existing['state_hash'] ?? '') !== $stateHash) {
            $params['id'] = (int) $existing['id'];
            $this->execute(
                'UPDATE `' . self::TABLE . '`
                 SET title = :title, message = :message, severity = :severity, status_label = :status_label, url = :url,
                     state_hash = :state_hash, payload_json = :payload_json, is_read = 0, read_at = NULL, updated_at = NOW()
                 WHERE id = :id',
                [
                    'title' => $params['title'],
                    'message' => $params['message'],
                    'severity' => $params['severity'],
                    'status_label' => $params['status_label'],
                    'url' => $params['url'],
                    'state_hash' => $params['state_hash'],
                    'payload_json' => $params['payload_json'],
                    'id' => $params['id'],
                ]
            );
            $stats['updated']++;
            return;
        }

        $stats['skipped']++;
    }

    private function orderPayload(array $order, array $documentStats, array $turnaround): array
    {
        return [
            'order_id' => (int) ($order['id'] ?? 0),
            'order_no' => $this->orderNo($order),
            'client_id' => (int) ($order['client_id'] ?? 0),
            'assigned_user_id' => (int) ($order['assigned_user_id'] ?? $order['executive_id'] ?? 0),
            'status' => $this->normalizeStatus((string) ($order['status'] ?? 'submitted')),
            'payment_status' => $this->normalizeStatus((string) ($order['payment_status'] ?? 'pending')),
            'notes' => (string) ($order['notes'] ?? ''),
            'admin_notes' => (string) ($order['admin_notes'] ?? ''),
            'completion_note' => (string) ($order['completion_note'] ?? ''),
            'approved_by' => (string) ($order['approved_by'] ?? ''),
            'approved_at' => (string) ($order['approved_at'] ?? ''),
            'created_at' => (string) ($order['created_at'] ?? ''),
            'updated_at' => (string) ($order['updated_at'] ?? ''),
            'documents' => $documentStats,
            'turnaround' => $turnaround,
        ];
    }

    private function documentStats(int $ownerId, string $table): array
    {
        $stats = ['total' => 0, 'client' => 0, 'payment' => 0, 'deliverable' => 0];
        if ($ownerId <= 0 || !$this->tableExists($table)) {
            return $stats;
        }

        $foreignKey = $table === 'partner_order_documents' ? 'partner_order_id' : 'order_id';
        if (!$this->columnExists($table, $foreignKey)) {
            $foreignKey = 'order_id';
        }

        $stats['total'] = $this->scalar("SELECT COUNT(*) FROM `{$table}` WHERE `{$foreignKey}` = :id", ['id' => $ownerId]);

        if ($this->columnExists($table, 'source')) {
            $stats['client'] = $this->scalar("SELECT COUNT(*) FROM `{$table}` WHERE `{$foreignKey}` = :id AND source IN ('client','customer','user','client_upload','client_document')", ['id' => $ownerId]);
            $stats['payment'] = $this->scalar("SELECT COUNT(*) FROM `{$table}` WHERE `{$foreignKey}` = :id AND source IN ('payment','payment_proof','proof')", ['id' => $ownerId]);
            $stats['deliverable'] = $this->scalar("SELECT COUNT(*) FROM `{$table}` WHERE `{$foreignKey}` = :id AND source IN ('admin_output','deliverable','deliverables','delivery','output')", ['id' => $ownerId]);
        } else {
            $stats['client'] = $stats['total'];
        }

        return $stats;
    }

    private function turnaroundInfo(array $order): array
    {
        $status = $this->normalizeStatus((string) ($order['status'] ?? 'submitted'));
        $completed = in_array($status, ['completed', 'closed', 'rejected', 'cancelled', 'canceled'], true);
        $turnaroundDays = 3;

        foreach (['turnaround_days', 'turn_around_days', 'tat_days', 'processing_days'] as $column) {
            if (isset($order[$column]) && (int) $order[$column] > 0) {
                $turnaroundDays = (int) $order[$column];
                break;
            }
        }

        $turnaroundDays = max(1, min(365, $turnaroundDays));
        $start = trim((string) ($order['approved_at'] ?? '')) ?: trim((string) ($order['created_at'] ?? '')) ?: date('Y-m-d H:i:s');
        $startTs = strtotime($start) ?: time();
        $dueTs = strtotime('+' . $turnaroundDays . ' days', $startTs) ?: time();
        $remaining = $dueTs - time();
        $daysElapsed = max(0, (int) floor((time() - $startTs) / 86400));
        $daysLeft = (int) ceil($remaining / 86400);

        if ($completed) {
            $dueState = 'completed';
            $daysLeft = 0;
        } elseif ($remaining < 0) {
            $dueState = 'overdue';
            $daysLeft = -max(1, (int) ceil(abs($remaining) / 86400));
        } elseif ($remaining <= 86400) {
            $dueState = 'due_today';
            $daysLeft = 0;
        } elseif ($remaining <= 172800) {
            $dueState = 'due_tomorrow';
            $daysLeft = 1;
        } else {
            $dueState = 'normal';
            $daysLeft = max(2, $daysLeft);
        }

        return [
            'turnaround_days' => $turnaroundDays,
            'work_started_at' => date('Y-m-d H:i:s', $startTs),
            'due_at' => date('Y-m-d H:i:s', $dueTs),
            'days_elapsed' => $daysElapsed,
            'days_left' => $daysLeft,
            'due_state' => $dueState,
            'completed' => $completed,
        ];
    }

    private function usersByRole(string $role): array
    {
        if (!$this->tableExists('users')) {
            return [];
        }

        $roleIds = match ($role) {
            'admin' => [1],
            'manager' => [2],
            'executive' => [3],
            'partner' => [4],
            default => [],
        };

        $conditions = [];
        $params = [];
        if ($this->columnExists('users', 'role_id') && $roleIds !== []) {
            $placeholders = [];
            foreach ($roleIds as $i => $id) {
                $key = 'role_' . $i;
                $placeholders[] = ':' . $key;
                $params[$key] = $id;
            }
            $conditions[] = 'role_id IN (' . implode(',', $placeholders) . ')';
        }

        foreach (['role', 'role_name', 'role_slug', 'slug'] as $column) {
            if ($this->columnExists('users', $column)) {
                $conditions[] = "LOWER(REPLACE(REPLACE(`{$column}`, ' ', '-'), '_', '-')) = :role_key";
                $params['role_key'] = $role;
            }
        }

        if ($conditions === []) {
            return [];
        }

        $emailExpr = $this->columnExists('users', 'email') ? 'email' : "'' AS email";
        $nameExpr = $this->columnExists('users', 'name') ? 'name' : ($this->columnExists('users', 'full_name') ? 'full_name' : 'id');
        $roleIdExpr = $this->columnExists('users', 'role_id') ? 'role_id' : '0 AS role_id';

        $rows = $this->fetchAll(
            "SELECT id, {$nameExpr} AS name, {$emailExpr}, {$roleIdExpr} FROM users WHERE (" . implode(' OR ', $conditions) . ") ORDER BY id ASC",
            $params
        );

        return array_map(static function (array $row) use ($role): array {
            $row['role_name'] = $role;
            return $row;
        }, $rows);
    }

    private function usersByIds(array $ids, string $roleName = 'user'): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));
        if ($ids === [] || !$this->tableExists('users')) {
            return [];
        }

        $params = [];
        $placeholders = [];
        foreach ($ids as $i => $id) {
            $key = 'id_' . $i;
            $placeholders[] = ':' . $key;
            $params[$key] = $id;
        }

        $emailExpr = $this->columnExists('users', 'email') ? 'email' : "'' AS email";
        $nameExpr = $this->columnExists('users', 'name') ? 'name' : ($this->columnExists('users', 'full_name') ? 'full_name' : 'id');
        $roleIdExpr = $this->columnExists('users', 'role_id') ? 'role_id' : '0 AS role_id';
        $rows = $this->fetchAll("SELECT id, {$nameExpr} AS name, {$emailExpr}, {$roleIdExpr} FROM users WHERE id IN (" . implode(',', $placeholders) . ")", $params);

        return array_map(static function (array $row) use ($roleName): array {
            $row['role_name'] = $roleName;
            return $row;
        }, $rows);
    }

    private function clientRecipients(int $clientId): array
    {
        if ($clientId <= 0 || !$this->tableExists('users')) {
            return [];
        }

        $conditions = ['id = :client_id'];
        $params = ['client_id' => $clientId];
        if ($this->columnExists('users', 'client_id')) {
            $conditions[] = 'client_id = :client_id';
        }

        $emailExpr = $this->columnExists('users', 'email') ? 'email' : "'' AS email";
        $nameExpr = $this->columnExists('users', 'name') ? 'name' : ($this->columnExists('users', 'full_name') ? 'full_name' : 'id');
        $roleIdExpr = $this->columnExists('users', 'role_id') ? 'role_id' : '0 AS role_id';
        $rows = $this->fetchAll("SELECT id, {$nameExpr} AS name, {$emailExpr}, {$roleIdExpr} FROM users WHERE (" . implode(' OR ', $conditions) . ")", $params);

        return array_map(static function (array $row): array {
            $row['role_name'] = 'client';
            return $row;
        }, $rows);
    }

    private function ensureTable(): void
    {
        $this->execute("CREATE TABLE IF NOT EXISTS `notification_manager` (
          `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `uid` VARCHAR(190) NOT NULL,
          `recipient_user_id` BIGINT UNSIGNED NOT NULL,
          `recipient_role_id` INT UNSIGNED NULL,
          `recipient_role` VARCHAR(50) NULL,
          `recipient_type` ENUM('admin','manager','executive','client','partner','system') NOT NULL DEFAULT 'client',
          `actor_user_id` BIGINT UNSIGNED NULL,
          `client_id` BIGINT UNSIGNED NULL,
          `partner_id` BIGINT UNSIGNED NULL,
          `executive_user_id` BIGINT UNSIGNED NULL,
          `order_id` BIGINT UNSIGNED NULL,
          `partner_order_id` BIGINT UNSIGNED NULL,
          `order_no` VARCHAR(120) NULL,
          `source_table` VARCHAR(80) NULL,
          `source_id` BIGINT UNSIGNED NULL,
          `type` VARCHAR(80) NOT NULL DEFAULT 'notification',
          `event_key` VARCHAR(120) NOT NULL DEFAULT 'general',
          `title` VARCHAR(255) NOT NULL,
          `message` TEXT NULL,
          `severity` ENUM('normal','info','success','warning','high','critical') NOT NULL DEFAULT 'normal',
          `status_label` VARCHAR(80) NULL,
          `url` VARCHAR(500) NULL,
          `state_hash` CHAR(40) NOT NULL,
          `payload_json` LONGTEXT NULL,
          `is_read` TINYINT(1) NOT NULL DEFAULT 0,
          `read_at` DATETIME NULL,
          `delivered_at` DATETIME NULL,
          `first_seen_at` DATETIME NULL,
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_notification_manager_uid` (`uid`),
          KEY `idx_notification_manager_recipient_read` (`recipient_user_id`, `is_read`, `created_at`),
          KEY `idx_notification_manager_recipient_created` (`recipient_user_id`, `created_at`),
          KEY `idx_notification_manager_order` (`order_id`),
          KEY `idx_notification_manager_partner_order` (`partner_order_id`),
          KEY `idx_notification_manager_type` (`type`),
          KEY `idx_notification_manager_event_key` (`event_key`),
          KEY `idx_notification_manager_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    private function dbObject(): mixed
    {
        try {
            return function_exists('app') ? app('db') : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    private function pdo(): ?PDO
    {
        $db = $this->dbObject();
        if ($db instanceof PDO) {
            return $db;
        }

        foreach (['pdo', 'connection', 'conn', 'db', 'dbh'] as $property) {
            try {
                if (is_object($db) && isset($db->{$property}) && $db->{$property} instanceof PDO) {
                    return $db->{$property};
                }
            } catch (Throwable $e) {
            }
        }

        foreach (['pdo', 'getPdo', 'getConnection', 'connection'] as $method) {
            try {
                if (is_object($db) && method_exists($db, $method)) {
                    $candidate = $db->{$method}();
                    if ($candidate instanceof PDO) {
                        return $candidate;
                    }
                }
            } catch (Throwable $e) {
            }
        }

        return null;
    }

    private function fetchAll(string $sql, array $params = []): array
    {
        try {
            $db = $this->dbObject();
            if ($db && method_exists($db, 'fetchAll')) {
                $rows = $db->fetchAll($sql, $params);
                return is_array($rows) ? $rows : [];
            }

            $pdo = $this->pdo();
            if ($pdo instanceof PDO) {
                $statement = $pdo->prepare($sql);
                $this->bindValues($statement, $params);
                $statement->execute();
                $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
                return is_array($rows) ? $rows : [];
            }
        } catch (Throwable $e) {
            error_log('NotificationManager fetchAll failed: ' . $e->getMessage() . ' SQL: ' . $sql);
        }

        return [];
    }

    private function fetchOne(string $sql, array $params = []): ?array
    {
        $rows = $this->fetchAll($sql, $params);
        return $rows[0] ?? null;
    }

    private function scalar(string $sql, array $params = []): int
    {
        $row = $this->fetchOne($sql, $params);
        if (!$row) {
            return 0;
        }
        return (int) reset($row);
    }

    private function execute(string $sql, array $params = []): bool
    {
        try {
            $db = $this->dbObject();
            foreach (['execute', 'statement'] as $method) {
                if ($db && method_exists($db, $method)) {
                    $db->{$method}($sql, $params);
                    return true;
                }
            }

            $pdo = $this->pdo();
            if ($pdo instanceof PDO) {
                $statement = $pdo->prepare($sql);
                $this->bindValues($statement, $params);
                return (bool) $statement->execute();
            }
        } catch (Throwable $e) {
            error_log('NotificationManager execute failed: ' . $e->getMessage() . ' SQL: ' . $sql);
        }

        return false;
    }

    private function bindValues(PDOStatement $statement, array $params): void
    {
        foreach ($params as $key => $value) {
            $param = is_int($key) ? $key + 1 : ':' . ltrim((string) $key, ':');
            $type = is_int($value) ? PDO::PARAM_INT : ($value === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $statement->bindValue($param, $value, $type);
        }
    }

    private function tableExists(string $table): bool
    {
        static $cache = [];
        $table = preg_replace('/[^A-Za-z0-9_]/', '', $table) ?: '';
        if ($table === '') {
            return false;
        }
        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }
        $row = $this->fetchOne('SHOW TABLES LIKE :table_name', ['table_name' => $table]);
        return $cache[$table] = is_array($row) && $row !== [];
    }

    private function columnExists(string $table, string $column): bool
    {
        static $cache = [];
        $table = preg_replace('/[^A-Za-z0-9_]/', '', $table) ?: '';
        $column = preg_replace('/[^A-Za-z0-9_]/', '', $column) ?: '';
        if ($table === '' || $column === '') {
            return false;
        }
        $key = $table . '.' . $column;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }
        $row = $this->fetchOne("SHOW COLUMNS FROM `{$table}` LIKE :column_name", ['column_name' => $column]);
        return $cache[$key] = is_array($row) && $row !== [];
    }

    private function currentUser(): array
    {
        try {
            if (function_exists('auth_user')) {
                $user = auth_user();
                if (is_array($user) && !empty($user['id'])) {
                    return $user;
                }
            }
        } catch (Throwable $e) {
        }

        if (is_array($_SESSION['auth_user'] ?? null)) {
            return $_SESSION['auth_user'];
        }
        if (is_array($_SESSION['user'] ?? null)) {
            return $_SESSION['user'];
        }

        return ['id' => (int) ($_SESSION['user_id'] ?? 0)];
    }

    private function isLoggedIn(): bool
    {
        try {
            if (function_exists('is_logged_in')) {
                return (bool) is_logged_in();
            }
        } catch (Throwable $e) {
        }
        return !empty($_SESSION['user_id']) || !empty($_SESSION['auth_user']) || !empty($_SESSION['user']);
    }

    private function requestData(): array
    {
        $data = $_POST;
        $raw = file_get_contents('php://input') ?: '';
        $json = json_decode($raw, true);
        if (is_array($json)) {
            $data = array_merge($data, $json);
        }
        return $data;
    }

    private function json(array $payload, int $status = 200): void
    {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    private function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }

    private function baseUrl(string $path = ''): string
    {
        try {
            if (function_exists('base_url')) {
                return base_url($path);
            }
        } catch (Throwable $e) {
        }
        return '/' . ltrim($path, '/');
    }

    private function normalizeStatus(string $value): string
    {
        $value = strtolower(trim($value));
        $value = str_replace(['-', ' '], '_', $value);
        $value = preg_replace('/_+/', '_', $value) ?: $value;
        return trim($value, '_') ?: 'pending';
    }

    private function human(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return 'Pending';
        }
        return ucwords(str_replace(['_', '-'], ' ', $value));
    }

    private function safeSeverity(string $severity): string
    {
        return in_array($severity, ['normal', 'info', 'success', 'warning', 'high', 'critical'], true) ? $severity : 'normal';
    }

    private function orderNo(array $order): string
    {
        return trim((string) ($order['order_no'] ?? $order['order_number'] ?? '')) ?: ('Order #' . (int) ($order['id'] ?? 0));
    }

    private function compactMessage(array $parts): string
    {
        $message = trim(implode(' • ', array_values(array_filter(array_map(static fn($v): string => trim((string) $v), $parts)))));
        if ($message === '') {
            return 'Order details updated.';
        }
        return mb_substr($message, 0, 500);
    }

    private function renderFallbackCenter(array $items, array $summary, int $page, string $filter): void
    {
        $safe = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Notification Center</title>';
        echo '<style>body{font-family:Inter,Arial,sans-serif;background:#f4f7fb;margin:0;padding:24px}.wrap{max-width:980px;margin:auto}.card{background:#fff;border:1px solid #e6ebf2;border-radius:18px;padding:18px;margin-bottom:12px;box-shadow:0 10px 25px rgba(15,23,42,.06)}.unread{background:linear-gradient(135deg,#eef2f7,#fff 45%,#e5e7eb);border-color:#cbd5e1}.meta{color:#64748b;font-size:13px}.badge{display:inline-flex;border-radius:999px;background:#0f172a;color:#fff;padding:5px 10px;font-size:12px}.actions a{margin-right:10px}</style>';
        echo '</head><body><div class="wrap"><h1>Notification Center</h1><p>Unread: ' . (int) ($summary['unread'] ?? 0) . ' / Total: ' . (int) ($summary['total'] ?? 0) . '</p>';
        echo '<p class="actions"><a href="' . $safe($this->baseUrl('notifications?filter=all')) . '">All</a><a href="' . $safe($this->baseUrl('notifications?filter=unread')) . '">Unread</a><a href="' . $safe($this->baseUrl('notifications?filter=read')) . '">Read</a></p>';
        foreach ($items as $item) {
            echo '<div class="card ' . (empty($item['is_read']) ? 'unread' : '') . '">';
            echo '<span class="badge">' . $safe($item['status_label'] ?: $item['severity']) . '</span>';
            echo '<h3><a href="' . $safe($item['open_url']) . '">' . $safe($item['title']) . '</a></h3>';
            echo '<p>' . $safe($item['message']) . '</p><p class="meta">' . $safe($item['created_at']) . ' • ' . (empty($item['is_read']) ? 'Unread' : 'Read') . '</p>';
            echo '</div>';
        }
        echo '<p><a href="' . $safe($this->baseUrl('notifications?page=' . ($page + 1) . '&filter=' . urlencode($filter))) . '">Next Page</a></p>';
        echo '</div></body></html>';
    }
}