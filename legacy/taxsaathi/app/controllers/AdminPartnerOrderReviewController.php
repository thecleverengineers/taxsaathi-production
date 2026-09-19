<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use RuntimeException;
use Throwable;

final class AdminPartnerOrderReviewController extends Controller
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
            foreach (['db', 'database'] as $configKey) {
                try {
                    $raw = config($configKey);

                    if (is_array($raw) && $raw !== []) {
                        $config = $this->normalizeDatabaseConfig($raw);
                        break;
                    }
                } catch (Throwable $e) {
                    $config = [];
                }
            }
        }

        foreach ([
            dirname(__DIR__) . '/config/config.php',
            dirname(__DIR__) . '/config/database.php',
            dirname(__DIR__, 2) . '/app/config/config.php',
            dirname(__DIR__, 2) . '/app/config/database.php',
            dirname(__DIR__, 2) . '/config/config.php',
            dirname(__DIR__, 2) . '/config/database.php',
        ] as $configFile) {
            if ($config !== [] || !is_file($configFile)) {
                continue;
            }

            try {
                $raw = require $configFile;

                if (!is_array($raw)) {
                    continue;
                }

                if (isset($raw['db']) && is_array($raw['db'])) {
                    $config = $this->normalizeDatabaseConfig($raw['db']);
                    break;
                }

                if (isset($raw['database']) && is_array($raw['database'])) {
                    $config = $this->normalizeDatabaseConfig($raw['database']);
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

    private function requireAdmin(): void
    {
        $user = $this->currentUser();
        $roleIds = array_map('intval', (array) ($user['role_ids'] ?? []));

        if (array_intersect([1, 2], $roleIds) !== []) {
            return;
        }

        if (function_exists('can')) {
            try {
                if (can('partner_orders.manage') || can('partners.manage') || can('orders.manage')) {
                    return;
                }
            } catch (Throwable $e) {
            }
        }

        flash('error', 'Admin access required.');
        redirect('auth');
    }

    public function index(): void
    {
        $this->requireAdmin();
        $this->ensurePartnerOrderAcceptColumns();

        $rows = $this->db()->fetchAll(
            'SELECT
                po.*,
                u.name AS partner_name,
                u.email AS partner_email,
                s.title AS service_title,
                o.order_no AS accepted_order_no,
                o.status AS accepted_order_status
             FROM partner_orders po
             INNER JOIN users u ON u.id = po.partner_id
             INNER JOIN services s ON s.id = po.service_id
             LEFT JOIN orders o ON o.id = po.accepted_order_id
             ORDER BY po.id DESC'
        ) ?: [];

        $this->view('admin/partner-orders', [
            'title' => 'Partner Orders – Tax Saathi',
            'rows' => $rows,
        ], 'layouts/dashboard');
    }

    public function show(): void
    {
        $this->requireAdmin();
        $this->ensurePartnerOrderAcceptColumns();

        $orderId = (int) input('id');

        $order = $this->db()->fetch(
            'SELECT
                po.*,
                u.name AS partner_name,
                u.email AS partner_email,
                u.phone AS partner_phone,
                s.title AS service_title,
                s.slug AS service_slug,
                o.order_no AS accepted_order_no,
                o.status AS accepted_order_status,
                o.payment_status AS accepted_payment_status
             FROM partner_orders po
             INNER JOIN users u ON u.id = po.partner_id
             INNER JOIN services s ON s.id = po.service_id
             LEFT JOIN orders o ON o.id = po.accepted_order_id
             WHERE po.id = :id
             LIMIT 1',
            ['id' => $orderId]
        );

        if (!$order) {
            flash('error', 'Partner order not found.');
            redirect('admin/partner-orders');
        }

        $documents = $this->db()->fetchAll(
            'SELECT *
             FROM partner_order_documents
             WHERE partner_order_id = :order_id
             ORDER BY id ASC',
            ['order_id' => $orderId]
        ) ?: [];

        $this->view('admin/partner-order-show', [
            'title' => 'Partner Order Review – Tax Saathi',
            'order' => $order,
            'documents' => $documents,
        ], 'layouts/dashboard');
    }

    public function accept(): void
    {
        $this->requireAdmin();
        verify_csrf();

        $this->ensurePartnerOrderAcceptColumns();

        $partnerOrderId = (int) input('id');

        $partnerOrder = $this->db()->fetch(
            'SELECT
                po.*,
                u.name AS partner_name,
                u.email AS partner_email,
                u.phone AS partner_phone,
                s.title AS service_title,
                s.slug AS service_slug
             FROM partner_orders po
             INNER JOIN users u ON u.id = po.partner_id
             INNER JOIN services s ON s.id = po.service_id
             WHERE po.id = :id
             LIMIT 1',
            ['id' => $partnerOrderId]
        );

        if (!$partnerOrder) {
            flash('error', 'Partner order not found.');
            redirect('admin/partner-orders');
        }

        $existingAcceptedOrderId = (int) ($partnerOrder['accepted_order_id'] ?? 0);

        if ($existingAcceptedOrderId > 0) {
            $existingOrder = $this->db()->fetch(
                'SELECT id, order_no FROM orders WHERE id = :id LIMIT 1',
                ['id' => $existingAcceptedOrderId]
            );

            if ($existingOrder) {
                flash('warning', 'This partner order was already accepted as order ' . (string) ($existingOrder['order_no'] ?? ''));
                redirect('admin/orders/show?id=' . $existingAcceptedOrderId);
            }
        }

        $partnerId = (int) ($partnerOrder['partner_id'] ?? 0);

        if ($partnerId <= 0) {
            flash('error', 'Invalid partner user.');
            redirect('admin/partner-orders/show?id=' . $partnerOrderId);
        }

        $now = date('Y-m-d H:i:s');
        $normalOrderNo = $this->nextNormalOrderNo();

        $feeAmount = round((float) ($partnerOrder['payable_amount'] ?? $partnerOrder['filing_fee'] ?? 0), 2);
        $paymentReference = trim((string) ($partnerOrder['payment_reference'] ?? ''));
        $adminNotes = trim((string) input('admin_notes', 'Accepted from partner order ' . (string) ($partnerOrder['order_no'] ?? '')));

        /*
         * Specific user assignment:
         * client_id        = partner user id
         * assigned_user_id = partner user id
         * partner_id       = partner user id
         *
         * This makes the accepted order visible under:
         * /partner/assigned-orders
         */
        $this->db()->execute(
            'INSERT INTO orders
            (
                order_no,
                client_id,
                service_id,
                financial_year,
                fee_amount,
                status,
                payment_method,
                payment_status,
                payment_reference,
                notes,
                admin_notes,
                approved_by,
                assigned_user_id,
                approved_at,
                created_at,
                updated_at,
                partner_id
            )
            VALUES
            (
                :order_no,
                :client_id,
                :service_id,
                :financial_year,
                :fee_amount,
                :status,
                :payment_method,
                :payment_status,
                :payment_reference,
                :notes,
                :admin_notes,
                :approved_by,
                :assigned_user_id,
                :approved_at,
                :created_at,
                :updated_at,
                :partner_id
            )',
            [
                'order_no' => $normalOrderNo,
                'client_id' => $partnerId,
                'service_id' => (int) ($partnerOrder['service_id'] ?? 0),
                'financial_year' => (string) ($partnerOrder['financial_year'] ?? ''),
                'fee_amount' => $feeAmount,
                'status' => 'work_in_progress',
                'payment_method' => 'upi',
                'payment_status' => 'verified',
                'payment_reference' => $paymentReference !== '' ? $paymentReference : null,
                'notes' => trim((string) ($partnerOrder['notes'] ?? '')),
                'admin_notes' => $adminNotes,
                'approved_by' => $this->currentUserId() > 0 ? $this->currentUserId() : null,
                'assigned_user_id' => $partnerId,
                'approved_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
                'partner_id' => $partnerId,
            ]
        );

        $created = $this->db()->fetch(
            'SELECT id, order_no
             FROM orders
             WHERE order_no = :order_no
             ORDER BY id DESC
             LIMIT 1',
            ['order_no' => $normalOrderNo]
        );

        $normalOrderId = (int) ($created['id'] ?? 0);

        if ($normalOrderId <= 0) {
            flash('error', 'Partner order accepted but normal order could not be created.');
            redirect('admin/partner-orders/show?id=' . $partnerOrderId);
        }

        $copiedDocuments = $this->copyPartnerDocumentsToOrder($partnerOrderId, $normalOrderId, $partnerId);
        $copiedPaymentProof = $this->copyPartnerPaymentProofToOrder($partnerOrder, $normalOrderId, $partnerId);

        $this->db()->execute(
            'UPDATE partner_orders
             SET payment_status = :payment_status,
                 filing_status = :filing_status,
                 order_status = :order_status,
                 admin_notes = :admin_notes,
                 approved_by = :approved_by,
                 approved_at = :approved_at,
                 accepted_order_id = :accepted_order_id,
                 accepted_by = :accepted_by,
                 accepted_at = :accepted_at,
                 updated_at = :updated_at
             WHERE id = :id',
            [
                'payment_status' => 'approved',
                'filing_status' => 'in_progress',
                'order_status' => 'approved',
                'admin_notes' => $adminNotes,
                'approved_by' => $this->currentUserId() > 0 ? $this->currentUserId() : null,
                'approved_at' => $now,
                'accepted_order_id' => $normalOrderId,
                'accepted_by' => $this->currentUserId() > 0 ? $this->currentUserId() : null,
                'accepted_at' => $now,
                'updated_at' => $now,
                'id' => $partnerOrderId,
            ]
        );

        $this->logActivity(
            $normalOrderId,
            'partner_order.accepted',
            'Partner order accepted and converted to assigned order.',
            [
                'partner_order_id' => $partnerOrderId,
                'partner_order_no' => (string) ($partnerOrder['order_no'] ?? ''),
                'normal_order_no' => $normalOrderNo,
                'partner_id' => $partnerId,
                'copied_documents' => $copiedDocuments,
                'copied_payment_proof' => $copiedPaymentProof,
            ]
        );

        flash('success', 'Partner order accepted. Created assigned order ' . $normalOrderNo . ' for partner.');
        redirect('admin/orders/show?id=' . $normalOrderId);
    }

    /*
     * Old method name kept for old routes/forms.
     */
    public function updateStatus(): void
    {
        $this->accept();
    }

    private function ensurePartnerOrderAcceptColumns(): void
    {
        $columns = $this->tableColumnsSafe('partner_orders');

        $required = [
            'accepted_order_id' => 'INT(10) UNSIGNED NULL',
            'accepted_by' => 'INT(10) UNSIGNED NULL',
            'accepted_at' => 'DATETIME NULL',
        ];

        foreach ($required as $column => $definition) {
            if (in_array($column, $columns, true)) {
                continue;
            }

            try {
                $this->db()->execute('ALTER TABLE partner_orders ADD COLUMN `' . $column . '` ' . $definition);
                $columns[] = $column;
            } catch (Throwable $e) {
                error_log('partner_orders alter skipped for ' . $column . ': ' . $e->getMessage());
            }
        }
    }

    private function tableColumnsSafe(string $table): array
    {
        $table = str_replace('`', '', $table);

        try {
            $rows = $this->db()->fetchAll('SHOW COLUMNS FROM `' . $table . '`') ?: [];
        } catch (Throwable $e) {
            return [];
        }

        $columns = [];

        foreach ($rows as $row) {
            $field = (string) ($row['Field'] ?? '');

            if ($field !== '') {
                $columns[] = $field;
            }
        }

        return $columns;
    }

    private function nextNormalOrderNo(): string
    {
        $prefix = 'TSO-' . date('Y') . '-';

        $row = $this->db()->fetch(
            'SELECT order_no
             FROM orders
             WHERE order_no LIKE :prefix
             ORDER BY id DESC
             LIMIT 1',
            ['prefix' => $prefix . '%']
        );

        $last = 0;

        if (is_array($row) && !empty($row['order_no']) && preg_match('/(\d+)$/', (string) $row['order_no'], $match)) {
            $last = (int) $match[1];
        }

        return $prefix . str_pad((string) ($last + 1), 5, '0', STR_PAD_LEFT);
    }

    private function copyPartnerDocumentsToOrder(int $partnerOrderId, int $normalOrderId, int $partnerId): int
    {
        $partnerDocs = $this->db()->fetchAll(
            'SELECT *
             FROM partner_order_documents
             WHERE partner_order_id = :partner_order_id
             ORDER BY id ASC',
            ['partner_order_id' => $partnerOrderId]
        ) ?: [];

        $copied = 0;

        foreach ($partnerDocs as $doc) {
            $originalName = (string) ($doc['original_name'] ?? 'document');
            $copiedFile = $this->copyPartnerFileToNormalOrderStorage(
                (string) ($doc['file_path'] ?? ''),
                $normalOrderId,
                $originalName
            );

            if ($copiedFile === '') {
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
                    created_at,
                    document_status
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
                    :created_at,
                    :document_status
                )',
                [
                    'order_id' => $normalOrderId,
                    'requirement_id' => (int) ($doc['service_requirement_id'] ?? $doc['requirement_id'] ?? 0) > 0
                        ? (int) ($doc['service_requirement_id'] ?? $doc['requirement_id'])
                        : null,
                    'source' => 'client',
                    'label' => (string) ($doc['title'] ?? $doc['label'] ?? 'Partner Uploaded Document'),
                    'original_name' => $originalName,
                    'stored_name' => $copiedFile,
                    'mime_type' => (string) ($doc['mime_type'] ?? $this->guessMimeType((string) ($doc['file_path'] ?? ''))),
                    'size_bytes' => (int) ($doc['file_size'] ?? $doc['size_bytes'] ?? $this->safeFileSize((string) ($doc['file_path'] ?? ''))),
                    'is_client_visible' => 0,
                    'uploaded_by' => $partnerId,
                    'created_at' => date('Y-m-d H:i:s'),
                    'document_status' => 'active',
                ]
            );

            $copied++;
        }

        return $copied;
    }

    private function copyPartnerPaymentProofToOrder(array $partnerOrder, int $normalOrderId, int $partnerId): bool
    {
        $paymentProofPath = trim((string) ($partnerOrder['payment_proof'] ?? ''));

        if ($paymentProofPath === '') {
            return false;
        }

        $copiedFile = $this->copyPartnerFileToNormalOrderStorage(
            $paymentProofPath,
            $normalOrderId,
            basename($paymentProofPath)
        );

        if ($copiedFile === '') {
            return false;
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
                created_at,
                document_status
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
                :created_at,
                :document_status
            )',
            [
                'order_id' => $normalOrderId,
                'requirement_id' => null,
                'source' => 'payment_proof',
                'label' => 'Partner UPI Payment Proof',
                'original_name' => basename($paymentProofPath),
                'stored_name' => $copiedFile,
                'mime_type' => $this->guessMimeType($paymentProofPath),
                'size_bytes' => $this->safeFileSize($paymentProofPath),
                'is_client_visible' => 0,
                'uploaded_by' => $partnerId,
                'created_at' => date('Y-m-d H:i:s'),
                'document_status' => 'active',
            ]
        );

        return true;
    }

    private function copyPartnerFileToNormalOrderStorage(string $relativePath, int $normalOrderId, string $originalName): string
    {
        $relativePath = trim($relativePath);

        if ($relativePath === '') {
            return '';
        }

        $source = $this->absolutePathFromStoredPath($relativePath);

        if (!is_file($source)) {
            return '';
        }

        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if ($extension === '') {
            $extension = strtolower(pathinfo($source, PATHINFO_EXTENSION)) ?: 'dat';
        }

        $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx'];

        if (!in_array($extension, $allowed, true)) {
            $extension = 'dat';
        }

        $destinationRelativeDir = 'orders/' . $normalOrderId;
        $destinationDir = dirname(__DIR__, 2) . '/storage/uploads/documents/' . $destinationRelativeDir;

        if (!is_dir($destinationDir)) {
            mkdir($destinationDir, 0775, true);
        }

        $safeName = date('YmdHis') . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
        $destination = $destinationDir . '/' . $safeName;

        if (!copy($source, $destination)) {
            return '';
        }

        return $destinationRelativeDir . '/' . $safeName;
    }

    private function absolutePathFromStoredPath(string $path): string
    {
        $path = trim($path);

        if ($path === '') {
            return '';
        }

        if (is_file($path)) {
            return $path;
        }

        $root = dirname(__DIR__, 2);

        $clean = ltrim($path, '/');

        $candidates = [
            $root . '/' . $clean,
            $root . '/public/' . $clean,
            $root . '/storage/uploads/documents/' . $clean,
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return $root . '/' . $clean;
    }

    private function guessMimeType(string $relativePath): string
    {
        $path = $this->absolutePathFromStoredPath($relativePath);

        if (is_file($path) && function_exists('mime_content_type')) {
            $mime = mime_content_type($path);

            if (is_string($mime) && $mime !== '') {
                return $mime;
            }
        }

        $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));

        return match ($extension) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            default => 'application/octet-stream',
        };
    }

    private function safeFileSize(string $relativePath): int
    {
        $path = $this->absolutePathFromStoredPath($relativePath);

        return is_file($path) ? (int) filesize($path) : 0;
    }

    private function logActivity(int $orderId, string $action, string $description, array $meta = []): void
    {
        try {
            $this->db()->execute(
                'INSERT INTO activity_logs
                (
                    user_id,
                    order_id,
                    action,
                    description,
                    meta_json,
                    created_at
                )
                VALUES
                (
                    :user_id,
                    :order_id,
                    :action,
                    :description,
                    :meta_json,
                    :created_at
                )',
                [
                    'user_id' => $this->currentUserId() > 0 ? $this->currentUserId() : null,
                    'order_id' => $orderId,
                    'action' => $action,
                    'description' => $description,
                    'meta_json' => json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'created_at' => date('Y-m-d H:i:s'),
                ]
            );
        } catch (Throwable $e) {
            error_log('Partner order accept activity log failed: ' . $e->getMessage());
        }
    }
}
