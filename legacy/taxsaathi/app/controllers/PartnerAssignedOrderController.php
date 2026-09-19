<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\NotificationService;
use App\Models\Invoice;
use App\Models\Payment;
use RuntimeException;
use Throwable;

final class PartnerAssignedOrderController extends Controller
{
    private ?Database $database = null;

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
            foreach (['database', 'db'] as $key) {
                try {
                    $raw = config($key);

                    if (is_array($raw) && $raw !== []) {
                        $config = $this->normalizeDatabaseConfig($raw);
                        break;
                    }
                } catch (Throwable $e) {
                    $config = [];
                }
            }
        }

        if ($config === []) {
            foreach ([
                dirname(__DIR__, 2) . '/config/database.php',
                dirname(__DIR__, 2) . '/app/config/database.php',
                dirname(__DIR__, 2) . '/config/config.php',
                dirname(__DIR__, 2) . '/app/config/config.php',
            ] as $file) {
                if (!is_file($file)) {
                    continue;
                }

                try {
                    $raw = require $file;

                    if (!is_array($raw)) {
                        continue;
                    }

                    if (isset($raw['database']) && is_array($raw['database'])) {
                        $config = $this->normalizeDatabaseConfig($raw['database']);
                        break;
                    }

                    if (isset($raw['db']) && is_array($raw['db'])) {
                        $config = $this->normalizeDatabaseConfig($raw['db']);
                        break;
                    }

                    if (isset($raw['connections']) || isset($raw['driver']) || isset($raw['host'])) {
                        $config = $this->normalizeDatabaseConfig($raw);
                        break;
                    }
                } catch (Throwable $e) {
                    $config = [];
                }
            }
        }

        if ($config === []) {
            $env = static function (string $key, mixed $default = null): mixed {
                if (function_exists('env')) {
                    return env($key, $default);
                }

                $value = function_exists('getenv') ? getenv($key) : false;

                return $value !== false ? $value : $default;
            };

            $config = [
                'driver'   => (string) $env('DB_DRIVER', 'mysql'),
                'host'     => (string) $env('DB_HOST', '127.0.0.1'),
                'port'     => (int) $env('DB_PORT', 3306),
                'database' => (string) $env('DB_DATABASE', $env('DB_NAME', 'taxsathi2')),
                'charset'  => (string) $env('DB_CHARSET', 'utf8mb4'),
                'username' => (string) $env('DB_USERNAME', $env('DB_USER', 'taxsathi2')),
                'password' => (string) $env('DB_PASSWORD', $env('DB_PASS', '')),
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
            'driver'   => (string) ($config['driver'] ?? $config['type'] ?? 'mysql'),
            'host'     => (string) ($config['host'] ?? $config['hostname'] ?? '127.0.0.1'),
            'port'     => (int) ($config['port'] ?? 3306),
            'database' => (string) ($config['database'] ?? $config['dbname'] ?? $config['name'] ?? ''),
            'charset'  => (string) ($config['charset'] ?? 'utf8mb4'),
            'username' => (string) ($config['username'] ?? $config['user'] ?? ''),
            'password' => (string) ($config['password'] ?? $config['pass'] ?? ''),
        ];
    }

    private function currentUser(): array
    {
        return function_exists('auth_user')
            ? (auth_user() ?: [])
            : ($_SESSION['user'] ?? $_SESSION['auth_user'] ?? []);
    }

    private function currentUserId(): int
    {
        $user = $this->currentUser();

        return (int) (
            $user['id']
            ?? $_SESSION['user_id']
            ?? $_SESSION['auth_user']['id']
            ?? $_SESSION['user']['id']
            ?? 0
        );
    }

    private function currentRoleId(): int
    {
        $userId = $this->currentUserId();
        if ($userId <= 0) {
            return 0;
        }

        try {
            $row = $this->db()->fetch(
                'SELECT ur.role_id
                 FROM user_roles ur
                 WHERE ur.user_id = :user_id
                 ORDER BY ur.role_id ASC
                 LIMIT 1',
                ['user_id' => $userId]
            );

            return (int) ($row['role_id'] ?? 0);
        } catch (Throwable $e) {
            return 0;
        }
    }

    private function roleSlugs(): array
    {
        $user = $this->currentUser();
        $slugs = [];

        $userId = $this->currentUserId();

        if ($userId > 0) {
            try {
                $rows = $this->db()->fetchAll(
                    'SELECT r.slug, r.name
                     FROM user_roles ur
                     INNER JOIN roles r ON r.id = ur.role_id
                     WHERE ur.user_id = :user_id',
                    ['user_id' => $userId]
                ) ?: [];

                foreach ($rows as $row) {
                    foreach ([$row['slug'] ?? null, $row['name'] ?? null] as $role) {
                        $role = strtolower(trim((string) $role));
                        if ($role !== '') {
                            $slugs[] = str_replace(' ', '-', $role);
                        }
                    }
                }
            } catch (Throwable $e) {
            }
        }

        return array_values(array_unique($slugs));
    }

    private function isPartner(): bool
    {
        return $this->currentRoleId() === 4
            || in_array('partner', $this->roleSlugs(), true)
            || in_array('partners', $this->roleSlugs(), true);
    }

    private function requirePartner(): void
    {
        if ($this->currentUserId() <= 0) {
            flash('error', 'Please login again.');
            redirect('auth');
        }

        if ($this->isPartner()) {
            return;
        }

        flash('error', 'Partner access required.');
        redirect('auth');
    }

    private function baseOrderSql(): string
    {
        return '
            SELECT
                o.id,
                o.order_no,
                o.client_id,
                o.service_id,
                o.financial_year,
                o.fee_amount,
                o.status,
                o.payment_method,
                o.payment_status,
                o.payment_reference,
                o.notes,
                o.admin_notes,
                o.completion_note,
                o.approved_by,
                o.assigned_user_id,
                o.approved_at,
                o.completed_at,
                o.created_at,
                o.updated_at,

                COALESCE(NULLIF(cd.name_as_per_pan, ""), NULLIF(cl.company_name, ""), NULLIF(cl.name, ""), NULLIF(cu.name, ""), "-") AS client_name,
                COALESCE(NULLIF(cd.email, ""), NULLIF(cl.email, ""), NULLIF(cu.email, ""), "") AS client_email,
                COALESCE(NULLIF(cd.mobile, ""), NULLIF(cl.phone, ""), NULLIF(cu.phone, ""), "-") AS client_phone,
                COALESCE(NULLIF(cd.pan_number, ""), "") AS client_pan_number,
                COALESCE(NULLIF(cd.name_as_per_pan, ""), "") AS customer_name_as_per_pan,
                COALESCE(NULLIF(cd.pan_number, ""), "") AS customer_pan_number,

                COALESCE(NULLIF(cd.submitted_by_name, ""), NULLIF(su.name, ""), "") AS submitted_by_name,
                COALESCE(NULLIF(cd.submitted_by_email, ""), NULLIF(su.email, ""), "") AS submitted_by_email,
                COALESCE(NULLIF(cd.submitted_by_phone, ""), NULLIF(su.phone, ""), "") AS submitted_by_phone,

                COALESCE(s.title, "-") AS service_title,
                COALESCE(s.slug, "") AS service_slug,
                COALESCE(s.turnaround_days, 0) AS service_turnaround_days,

                CASE
                    WHEN o.created_at IS NOT NULL AND COALESCE(s.turnaround_days, 0) > 0
                    THEN DATE(DATE_ADD(o.created_at, INTERVAL GREATEST(COALESCE(s.turnaround_days, 0), 0) DAY))
                    ELSE NULL
                END AS order_due_date,

                CASE
                    WHEN o.created_at IS NOT NULL AND COALESCE(s.turnaround_days, 0) > 0
                    THEN DATEDIFF(DATE(DATE_ADD(o.created_at, INTERVAL GREATEST(COALESCE(s.turnaround_days, 0), 0) DAY)), CURDATE())
                    ELSE NULL
                END AS order_due_days_left,

                COALESCE(au.name, "") AS assigned_user_name,
                COALESCE(au.email, "") AS assigned_user_email,

                (
                    SELECT COUNT(*)
                    FROM order_documents odc
                    WHERE odc.order_id = o.id
                      AND odc.source = "client"
                ) AS client_documents_count,

                (
                    SELECT COUNT(*)
                    FROM order_documents odo
                    WHERE odo.order_id = o.id
                      AND odo.source = "admin_output"
                ) AS output_documents_count,

                (
                    SELECT COUNT(*)
                    FROM order_documents odw
                    WHERE odw.order_id = o.id
                      AND odw.source = "client"
                      AND COALESCE(odw.document_status, "active") = "wrong"
                ) AS wrong_documents_count

            FROM orders o
            LEFT JOIN clients cl ON cl.id = o.client_id
            LEFT JOIN users cu ON cu.id = o.client_id
            LEFT JOIN customer_details cd ON cd.order_id = o.id
            LEFT JOIN users su ON su.id = cd.submitted_by_user_id
            LEFT JOIN services s ON s.id = o.service_id
            LEFT JOIN users au ON au.id = o.assigned_user_id
        ';
    }

    private function assignedOrders(): array
    {
        $sql = $this->baseOrderSql() . '
            WHERE o.assigned_user_id = :assigned_user_id
        ';

        $params = [
            'assigned_user_id' => $this->currentUserId(),
        ];

        $status = trim((string) input('status', ''));
        if ($status !== '') {
            $sql .= ' AND o.status = :status';
            $params['status'] = $status;
        }

        $paymentStatus = trim((string) input('payment_status', ''));
        if ($paymentStatus !== '') {
            $sql .= ' AND o.payment_status = :payment_status';
            $params['payment_status'] = $paymentStatus;
        }

        $q = trim((string) input('q', ''));
        if ($q !== '') {
            $sql .= '
                AND (
                    o.order_no LIKE :q
                    OR COALESCE(cd.name_as_per_pan, "") LIKE :q
                    OR COALESCE(cd.pan_number, "") LIKE :q
                    OR COALESCE(cd.mobile, "") LIKE :q
                    OR COALESCE(cd.email, "") LIKE :q
                    OR COALESCE(cl.company_name, "") LIKE :q
                    OR COALESCE(cl.name, "") LIKE :q
                    OR COALESCE(cu.name, "") LIKE :q
                    OR COALESCE(s.title, "") LIKE :q
                )
            ';
            $params['q'] = '%' . $q . '%';
        }

        $sql .= ' ORDER BY o.id DESC';

        return $this->db()->fetchAll($sql, $params) ?: [];
    }

    private function assignedOrder(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $row = $this->db()->fetch(
            $this->baseOrderSql() . '
             WHERE o.id = :id
               AND o.assigned_user_id = :assigned_user_id
             LIMIT 1',
            [
                'id' => $id,
                'assigned_user_id' => $this->currentUserId(),
            ]
        );

        return is_array($row) && $row !== [] ? $row : null;
    }

    private function orderDocuments(int $orderId, string $source): array
    {
        return $this->db()->fetchAll(
            'SELECT
                id,
                order_id,
                requirement_id,
                source,
                label,
                original_name,
                stored_name,
                mime_type,
                size_bytes,
                is_client_visible,
                uploaded_by,
                created_at,
                COALESCE(document_status, "active") AS document_status,
                wrong_reason,
                wrong_marked_by,
                wrong_marked_at,
                reuploaded_for_document_id,
                replaced_by_document_id
             FROM order_documents
             WHERE order_id = :order_id
               AND source = :source
             ORDER BY id DESC',
            [
                'order_id' => $orderId,
                'source' => $source,
            ]
        ) ?: [];
    }

    private function documentForPartner(int $documentId): ?array
    {
        if ($documentId <= 0) {
            return null;
        }

        $row = $this->db()->fetch(
            'SELECT
                d.*,
                COALESCE(d.document_status, "active") AS document_status,
                o.assigned_user_id,
                o.order_no,
                o.status AS order_status
             FROM order_documents d
             INNER JOIN orders o ON o.id = d.order_id
             WHERE d.id = :id
               AND o.assigned_user_id = :assigned_user_id
             LIMIT 1',
            [
                'id' => $documentId,
                'assigned_user_id' => $this->currentUserId(),
            ]
        );

        return is_array($row) && $row !== [] ? $row : null;
    }

    private function invoiceForOrder(int $orderId): array
    {
        if (class_exists(Invoice::class) && method_exists(Invoice::class, 'findByOrder')) {
            try {
                $invoice = Invoice::findByOrder($orderId);
                return is_array($invoice) ? $invoice : [];
            } catch (Throwable $e) {
                return [];
            }
        }

        try {
            $row = $this->db()->fetch(
                'SELECT * FROM invoices WHERE order_id = :order_id LIMIT 1',
                ['order_id' => $orderId]
            );

            return is_array($row) ? $row : [];
        } catch (Throwable $e) {
            return [];
        }
    }

    private function paymentsForOrder(int $orderId): array
    {
        if (class_exists(Payment::class) && method_exists(Payment::class, 'forOrder')) {
            try {
                $payments = Payment::forOrder($orderId);
                return is_array($payments) ? $payments : [];
            } catch (Throwable $e) {
                return [];
            }
        }

        try {
            return $this->db()->fetchAll(
                'SELECT * FROM payments WHERE order_id = :order_id ORDER BY id DESC',
                ['order_id' => $orderId]
            ) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    public function index(): void
    {
        $this->requirePartner();

        $rows = $this->assignedOrders();

        $stats = [
            'total' => count($rows),
            'pending' => 0,
            'work_in_progress' => 0,
            'completed' => 0,
            'clarification' => 0,
        ];

        foreach ($rows as $row) {
            $status = strtolower(trim((string) ($row['status'] ?? '')));

            if (in_array($status, ['submitted', 'submited', 'pending', 'pending_review'], true)) {
                $stats['pending']++;
            } elseif ($status === 'work_in_progress') {
                $stats['work_in_progress']++;
            } elseif ($status === 'completed') {
                $stats['completed']++;
            } elseif (in_array($status, ['clarification', 'pending_clarification'], true)) {
                $stats['clarification']++;
            }
        }

        $this->view('partner/assigned-orders', [
            'title' => 'Assigned Orders – Tax Saathi',
            'rows' => $rows,
            'stats' => $stats,
            'filters' => [
                'q' => input('q', ''),
                'status' => input('status', ''),
                'payment_status' => input('payment_status', ''),
            ],
        ], 'layouts/partner');
    }

    public function show(): void
    {
        $this->requirePartner();

        $order = $this->assignedOrder((int) input('id'));

        if (!$order) {
            flash('error', 'Assigned order not found or access denied.');
            redirect('partner/assigned-orders');
        }

        $orderId = (int) $order['id'];

        $this->view('partner/assigned-order-show', [
            'title' => 'Assigned Order ' . ($order['order_no'] ?? '') . ' – Tax Saathi',
            'order' => $order,
            'clientDocs' => $this->orderDocuments($orderId, 'client'),
            'outputDocs' => $this->orderDocuments($orderId, 'admin_output'),
            'paymentProofs' => $this->orderDocuments($orderId, 'payment_proof'),
            'invoice' => $this->invoiceForOrder($orderId),
            'payments' => $this->paymentsForOrder($orderId),
        ], 'layouts/partner');
    }

    public function updateStatus(): void
    {
        $this->requirePartner();
        verify_csrf();

        $order = $this->assignedOrder((int) input('id'));

        if (!$order) {
            flash('error', 'Assigned order not found or access denied.');
            redirect('partner/assigned-orders');
        }

        $status = strtolower(trim((string) input('status', 'work_in_progress')));
        $allowed = [
            'submitted',
            'pending_review',
            'clarification',
            'pending_clarification',
            'approved',
            'work_in_progress',
            'completed',
            'rejected',
        ];

        if (!in_array($status, $allowed, true)) {
            flash('error', 'Invalid status selected.');
            redirect('partner/assigned-orders-show?id=' . (int) $order['id']);
        }

        if ($status === 'pending_clarification') {
            $status = 'clarification';
        }

        $params = [
            'status' => $status,
            'admin_notes' => trim((string) input('admin_notes', (string) ($order['admin_notes'] ?? ''))),
            'completion_note' => trim((string) input('completion_note', (string) ($order['completion_note'] ?? ''))),
            'updated_at' => date('Y-m-d H:i:s'),
            'id' => (int) $order['id'],
            'assigned_user_id' => $this->currentUserId(),
        ];

        $sql = '
            UPDATE orders
            SET status = :status,
                admin_notes = :admin_notes,
                completion_note = :completion_note,
                updated_at = :updated_at
        ';

        if ($status === 'completed') {
            $sql .= ', completed_at = COALESCE(completed_at, :completed_at)';
            $params['completed_at'] = date('Y-m-d H:i:s');
        }

        $sql .= '
            WHERE id = :id
              AND assigned_user_id = :assigned_user_id
        ';

        $this->db()->execute($sql, $params);

        $this->activityLog((int) $order['id'], 'partner.assigned_order.status_updated', 'Partner updated assigned order status to ' . $status . '.');

        $this->safeTriggerNotification(
            $status === 'completed' ? 'order_completed' : 'order_in_progress',
            [
                'order_id' => (int) $order['id'],
                'order_no' => (string) ($order['order_no'] ?? ''),
                'name' => (string) ($order['client_name'] ?? ''),
                'phone' => (string) ($order['client_phone'] ?? ''),
                'service' => (string) ($order['service_title'] ?? ''),
            ]
        );

        flash('success', 'Order status updated successfully.');
        redirect('partner/assigned-orders-show?id=' . (int) $order['id'] . '#status');
    }

    public function uploadOutput(): void
    {
        $this->requirePartner();
        verify_csrf();

        $order = $this->assignedOrder((int) input('id'));

        if (!$order) {
            flash('error', 'Assigned order not found or access denied.');
            redirect('partner/assigned-orders');
        }

        if (empty($_FILES['documents'])) {
            flash('error', 'Please choose output document(s).');
            redirect('partner/assigned-orders-show?id=' . (int) $order['id'] . '#deliverables');
        }

        $uploadedCount = 0;
        $files = $this->normalizeFiles($_FILES['documents']);

        foreach ($files as $file) {
            $meta = $this->storeUpload($file, 'partner-output');

            if (!$meta) {
                continue;
            }

            $this->db()->execute(
                'INSERT INTO order_documents
                (
                    order_id,
                    requirement_id,
                    source,
                    label,
                    original_name,
                    stored_name,
                    mime_type,
                    size_bytes,
                    is_client_visible,
                    uploaded_by,
                    created_at
                )
                VALUES
                (
                    :order_id,
                    :requirement_id,
                    :source,
                    :label,
                    :original_name,
                    :stored_name,
                    :mime_type,
                    :size_bytes,
                    :is_client_visible,
                    :uploaded_by,
                    :created_at
                )',
                [
                    'order_id' => (int) $order['id'],
                    'requirement_id' => null,
                    'source' => 'admin_output',
                    'label' => trim((string) input('label', 'Completed File')),
                    'original_name' => $meta['original_name'],
                    'stored_name' => $meta['stored_name'],
                    'mime_type' => $meta['mime_type'],
                    'size_bytes' => $meta['size_bytes'],
                    'is_client_visible' => (int) input('is_client_visible', 1),
                    'uploaded_by' => $this->currentUserId(),
                    'created_at' => date('Y-m-d H:i:s'),
                ]
            );

            $uploadedCount++;
        }

        if ($uploadedCount <= 0) {
            flash('error', 'No valid files were uploaded. Allowed: PDF, image, DOC, DOCX, XLS, XLSX up to 10MB.');
            redirect('partner/assigned-orders-show?id=' . (int) $order['id'] . '#deliverables');
        }

        $this->db()->execute(
            'UPDATE orders
             SET status = "completed",
                 completed_at = COALESCE(completed_at, :completed_at),
                 updated_at = :updated_at
             WHERE id = :id
               AND assigned_user_id = :assigned_user_id',
            [
                'completed_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
                'id' => (int) $order['id'],
                'assigned_user_id' => $this->currentUserId(),
            ]
        );

        $this->activityLog((int) $order['id'], 'partner.assigned_order.output_uploaded', 'Partner uploaded output document(s).');

        $this->safeTriggerNotification('order_completed', [
            'order_id' => (int) $order['id'],
            'order_no' => (string) ($order['order_no'] ?? ''),
            'name' => (string) ($order['client_name'] ?? ''),
            'phone' => (string) ($order['client_phone'] ?? ''),
            'service' => (string) ($order['service_title'] ?? ''),
        ]);

        flash('success', $uploadedCount . ' output document(s) uploaded. Order marked completed.');
        redirect('partner/assigned-orders-show?id=' . (int) $order['id'] . '#deliverables');
    }

    public function markClientDocumentWrong(): void
    {
        $this->requirePartner();
        verify_csrf();

        $documentId = (int) input('document_id');
        $reason = trim((string) input('wrong_reason'));

        if ($documentId <= 0) {
            flash('error', 'Invalid document.');
            redirect('partner/assigned-orders');
        }

        if ($reason === '') {
            flash('error', 'Please enter reason for wrong document.');
            redirect((string) ($_SERVER['HTTP_REFERER'] ?? 'partner/assigned-orders'));
        }

        $document = $this->documentForPartner($documentId);

        if (!$document || (string) ($document['source'] ?? '') !== 'client') {
            flash('error', 'Client document not found or access denied.');
            redirect('partner/assigned-orders');
        }

        if ((string) ($document['document_status'] ?? 'active') === 'reuploaded') {
            flash('error', 'This document has already been replaced by the client.');
            redirect('partner/assigned-orders-show?id=' . (int) $document['order_id'] . '#client-documents');
        }

        $now = date('Y-m-d H:i:s');

        $this->db()->execute(
            'UPDATE order_documents
             SET document_status = "wrong",
                 wrong_reason = :wrong_reason,
                 wrong_marked_by = :wrong_marked_by,
                 wrong_marked_at = :wrong_marked_at,
                 is_client_visible = 1
             WHERE id = :id',
            [
                'wrong_reason' => $reason,
                'wrong_marked_by' => $this->currentUserId(),
                'wrong_marked_at' => $now,
                'id' => $documentId,
            ]
        );

        $this->db()->execute(
            'UPDATE orders
             SET status = "clarification",
                 updated_at = :updated_at
             WHERE id = :id
               AND assigned_user_id = :assigned_user_id',
            [
                'updated_at' => $now,
                'id' => (int) $document['order_id'],
                'assigned_user_id' => $this->currentUserId(),
            ]
        );

        $this->activityLog((int) $document['order_id'], 'partner.assigned_order.document_wrong', 'Partner marked client document as wrong.');

        $this->safeTriggerNotification('client_document_wrong', [
            'order_id' => (int) $document['order_id'],
            'order_no' => (string) ($document['order_no'] ?? ''),
            'document_id' => $documentId,
            'reason' => $reason,
        ]);

        flash('success', 'Document marked wrong. Client can now re-upload the correct document.');
        redirect('partner/assigned-orders-show?id=' . (int) $document['order_id'] . '#client-documents');
    }

    public function download(): void
    {
        $this->requirePartner();

        $document = $this->documentForPartner((int) input('document_id'));

        if (!$document) {
            http_response_code(404);
            exit('Document not found or access denied.');
        }

        $storedName = basename((string) ($document['stored_name'] ?? ''));

        if ($storedName === '') {
            http_response_code(404);
            exit('File name missing.');
        }

        $path = $this->uploadDirectory() . DIRECTORY_SEPARATOR . $storedName;

        if (!is_file($path)) {
            http_response_code(404);
            exit('File not found.');
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $downloadName = basename((string) ($document['original_name'] ?? $storedName));

        header('Content-Description: File Transfer');
        header('Content-Type: ' . ((string) ($document['mime_type'] ?? '') ?: 'application/octet-stream'));
        header('Content-Disposition: attachment; filename="' . $downloadName . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    private function normalizeFiles(array $file): array
    {
        if (is_array($file['name'] ?? null)) {
            $items = [];

            foreach (array_keys($file['name']) as $index) {
                $items[] = [
                    'name' => $file['name'][$index] ?? '',
                    'type' => $file['type'][$index] ?? '',
                    'tmp_name' => $file['tmp_name'][$index] ?? '',
                    'error' => $file['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                    'size' => $file['size'][$index] ?? 0,
                ];
            }

            return $items;
        }

        return [$file];
    }

    private function storeUpload(array $file, string $prefix): ?array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }

        if ((int) ($file['size'] ?? 0) > 10 * 1024 * 1024) {
            return null;
        }

        $originalName = basename((string) ($file['name'] ?? 'document'));
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx'];

        if (!in_array($extension, $allowed, true)) {
            return null;
        }

        $dir = $this->uploadDirectory();

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $storedName = $prefix . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(8)) . '.' . $extension;
        $target = $dir . DIRECTORY_SEPARATOR . $storedName;

        if (!move_uploaded_file((string) $file['tmp_name'], $target)) {
            return null;
        }

        return [
            'original_name' => $originalName,
            'stored_name' => $storedName,
            'mime_type' => (string) ($file['type'] ?? 'application/octet-stream'),
            'size_bytes' => (int) ($file['size'] ?? 0),
        ];
    }

    private function uploadDirectory(): string
    {
        if (function_exists('config')) {
            try {
                $configured = config('paths.uploads');

                if (is_string($configured) && trim($configured) !== '') {
                    return rtrim($configured, '/\\');
                }
            } catch (Throwable $e) {
            }
        }

        return dirname(__DIR__, 2) . '/storage/uploads/documents';
    }

    private function activityLog(int $orderId, string $event, string $message): void
    {
        if (!function_exists('activity_log')) {
            return;
        }

        try {
            activity_log($this->currentUserId(), $orderId, $event, $message);
        } catch (Throwable $e) {
            error_log('Partner assigned activity log failed: ' . $e->getMessage());
        }
    }

    private function safeTriggerNotification(string $event, array $payload = []): void
    {
        if (!class_exists(NotificationService::class) || !method_exists(NotificationService::class, 'trigger')) {
            return;
        }

        try {
            NotificationService::trigger($event, $payload);
        } catch (Throwable $e) {
            error_log('Partner assigned notification failed: ' . $e->getMessage());
        }
    }
}
