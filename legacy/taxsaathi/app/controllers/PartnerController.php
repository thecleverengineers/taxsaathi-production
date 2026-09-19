<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\Payment;
use RuntimeException;

/**
 * PartnerController
 *
 * Full partner module:
 * - partner dashboard
 * - partner-owned clients
 * - partner-owned orders
 * - partner invoice generation
 * - partner payment recording
 * - partner finance management
 * - partner subscription access
 * - partner profile management
 *
 * Ownership rule:
 * A partner can only access records where partner_id = current partner user id.
 */
final class PartnerController extends Controller
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

                $value = function_exists('getenv') ? getenv($key) : false;
                return $value !== false ? $value : $default;
            };

            $config = [
                'driver'   => (string) $env('DB_DRIVER', 'mysql'),
                'host'     => (string) $env('DB_HOST', '127.0.0.1'),
                'port'     => (int) $env('DB_PORT', 3306),
                'database' => (string) $env('DB_DATABASE', 'taxsathi2'),
                'charset'  => (string) $env('DB_CHARSET', 'utf8mb4'),
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
            'driver'   => (string) ($config['driver'] ?? 'mysql'),
            'host'     => (string) ($config['host'] ?? '127.0.0.1'),
            'port'     => (int) ($config['port'] ?? 3306),
            'database' => (string) ($config['database'] ?? 'taxsathi2'),
            'charset'  => (string) ($config['charset'] ?? 'utf8mb4'),
            'username' => (string) ($config['username'] ?? 'taxsathi2'),
            'password' => (string) ($config['password'] ?? 'taxsathi2'),
        ];
    }

    private function currentUser(): array
    {
        return function_exists('auth_user') ? (auth_user() ?: []) : ($_SESSION['user'] ?? $_SESSION['auth_user'] ?? []);
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


    /**
     * Strict partner RBAC.
     * Reads role mapping only from user_roles table.
     * No users.role_id, no session role_id, no role-name fallback.
     */
    private function currentUserRoleIds(): array
    {
        $userId = $this->currentUserId();

        if ($userId <= 0) {
            return [];
        }

        try {
            $rows = $this->db()->fetchAll(
                'SELECT ur.role_id
                 FROM user_roles ur
                 WHERE ur.user_id = :user_id
                 ORDER BY ur.role_id ASC',
                ['user_id' => $userId]
            ) ?: [];
        } catch (\Throwable $e) {
            error_log('Partner RBAC lookup failed: ' . $e->getMessage());
            return [];
        }

        $ids = [];
        foreach ($rows as $row) {
            $roleId = (int) ($row['role_id'] ?? 0);
            if ($roleId > 0) {
                $ids[$roleId] = $roleId;
            }
        }

        return array_values($ids);
    }

    private function currentUserHasRoleId(int $roleId): bool
    {
        if ($roleId <= 0) {
            return false;
        }

        return in_array($roleId, $this->currentUserRoleIds(), true);
    }

    private function isPartner(): bool
    {
        return $this->currentUserHasRoleId(4);
    }

    private function requirePartner(): void
    {
        if (!$this->isPartner()) {
            flash('error', 'Partner access required.');
            redirect('login');
            exit;
        }
    }

    private function partnerId(): int
    {
        $id = $this->currentUserId();

        if ($id <= 0) {
            flash('error', 'Please login again.');
            redirect('login');
        }

        return $id;
    }

    private function partnerSubscription(): array
    {
        $row = $this->db()->fetch(
            'SELECT *
             FROM partner_subscriptions
             WHERE partner_id = :partner_id
             ORDER BY id DESC
             LIMIT 1',
            ['partner_id' => $this->partnerId()]
        );

        if (is_array($row) && $row !== []) {
            return $row;
        }

        return [
            'partner_id' => $this->partnerId(),
            'plan_name' => 'Free',
            'status' => 'inactive',
            'starts_at' => null,
            'ends_at' => null,
            'orders_limit' => 0,
            'clients_limit' => 0,
        ];
    }

    private function hasActiveSubscription(): bool
    {
        $sub = $this->partnerSubscription();
        $status = strtolower((string) ($sub['status'] ?? ''));

        if (!in_array($status, ['active', 'trial', 'paid'], true)) {
            return false;
        }

        $endsAt = trim((string) ($sub['ends_at'] ?? ''));
        if ($endsAt !== '' && strtotime($endsAt) < time()) {
            return false;
        }

        return true;
    }

    private function requireSubscription(): void
    {
        if (!$this->hasActiveSubscription()) {
            flash('error', 'Your partner subscription is inactive. Please contact admin.');
            redirect('partner/subscription');
        }
    }

    private function requireActiveSubscription(): void
    {
        $this->requireSubscription();
    }

    private function ordersUsedInSubscriptionWindow(?array $subscription = null): int
    {
        $subscription = $subscription ?: $this->partnerSubscription();

        $sql = 'SELECT COUNT(*) AS total FROM orders WHERE partner_id = :partner_id';
        $params = ['partner_id' => $this->partnerId()];

        if (!empty($subscription['starts_at'])) {
            $sql .= ' AND created_at >= :starts_at';
            $params['starts_at'] = (string) $subscription['starts_at'];
        }

        if (!empty($subscription['ends_at'])) {
            $sql .= ' AND created_at <= :ends_at';
            $params['ends_at'] = (string) $subscription['ends_at'];
        }

        $row = $this->db()->fetch($sql, $params);

        return (int) ($row['total'] ?? 0);
    }

    private function requireOrderLimitAvailable(): void
    {
        $subscription = $this->partnerSubscription();

        if (!$this->hasActiveSubscription()) {
            flash('error', 'Your partner subscription is inactive. Please contact admin.');
            redirect('partner/subscription');
        }

        $limit = (int) ($subscription['orders_limit'] ?? 0);
        $used = $this->ordersUsedInSubscriptionWindow($subscription);

        if ($limit > 0 && $used >= $limit) {
            flash('error', 'Order limit reached for your subscription. Please contact admin to renew or upgrade.');
            redirect('partner/subscription');
        }
    }

    private function services(): array
    {
        return $this->db()->fetchAll(
            'SELECT id, title, filing_fee
             FROM services
             WHERE COALESCE(is_active, 1) = 1
             ORDER BY title ASC'
        ) ?: [];
    }

    private function service(int $id): ?array
    {
        $row = $this->db()->fetch(
            'SELECT id, title, filing_fee
             FROM services
             WHERE id = :id
             LIMIT 1',
            ['id' => $id]
        );

        return is_array($row) && $row !== [] ? $row : null;
    }

    private function nextOrderNo(): string
    {
        $prefix = 'PT-' . date('Ym') . '-';

        $row = $this->db()->fetch(
            'SELECT order_no
             FROM orders
             WHERE order_no LIKE :prefix
             ORDER BY id DESC
             LIMIT 1',
            ['prefix' => $prefix . '%']
        );

        $last = 0;
        if (is_array($row) && !empty($row['order_no']) && preg_match('/(\d+)$/', (string) $row['order_no'], $m)) {
            $last = (int) $m[1];
        }

        return $prefix . str_pad((string) ($last + 1), 4, '0', STR_PAD_LEFT);
    }

    private function nextInvoiceNo(): string
    {
        if (class_exists(Invoice::class) && method_exists(Invoice::class, 'nextInvoiceNo')) {
            return Invoice::nextInvoiceNo();
        }

        return 'PINV-' . date('Ym') . '-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function partnerClients(): array
    {
        return $this->db()->fetchAll(
            'SELECT id, name, email, phone, gst_number, company_name, city, created_at
             FROM clients
             WHERE partner_id = :partner_id
             ORDER BY id DESC',
            ['partner_id' => $this->partnerId()]
        ) ?: [];
    }

    private function partnerClient(int $clientId): ?array
    {
        if ($clientId <= 0) {
            return null;
        }

        $row = $this->db()->fetch(
            'SELECT id, name, email, phone, gst_number, company_name, city, created_at
             FROM clients
             WHERE id = :id AND partner_id = :partner_id
             LIMIT 1',
            [
                'id' => $clientId,
                'partner_id' => $this->partnerId(),
            ]
        );

        return is_array($row) && $row !== [] ? $row : null;
    }

    private function partnerOrders(array $filters = []): array
    {
        $sql = '
            SELECT
                o.*,
                COALESCE(NULLIF(c.company_name, ""), NULLIF(c.name, ""), "-") AS client_name,
                COALESCE(c.phone, "") AS client_phone,
                COALESCE(c.email, "") AS client_email,
                COALESCE(s.title, "-") AS service_title,
                COALESCE(i.invoice_no, "") AS invoice_no,
                COALESCE(i.status, "") AS invoice_status,
                COALESCE(i.total_amount, 0) AS invoice_total,
                COALESCE(i.paid_amount, 0) AS invoice_paid
            FROM orders o
            LEFT JOIN clients c ON c.id = o.client_id
            LEFT JOIN services s ON s.id = o.service_id
            LEFT JOIN invoices i ON i.order_id = o.id
            WHERE o.partner_id = :partner_id
        ';

        $params = ['partner_id' => $this->partnerId()];

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $sql .= ' AND o.status = :status';
            $params['status'] = $status;
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $sql .= ' AND (
                o.order_no LIKE :q
                OR COALESCE(c.name, "") LIKE :q
                OR COALESCE(c.company_name, "") LIKE :q
                OR COALESCE(c.phone, "") LIKE :q
                OR COALESCE(s.title, "") LIKE :q
            )';
            $params['q'] = '%' . $q . '%';
        }

        $sql .= ' ORDER BY o.id DESC';

        return $this->db()->fetchAll($sql, $params) ?: [];
    }

    private function partnerOrder(int $orderId): ?array
    {
        $row = $this->db()->fetch(
            'SELECT
                o.*,
                COALESCE(NULLIF(c.company_name, ""), NULLIF(c.name, ""), "-") AS client_name,
                COALESCE(c.name, "") AS client_person_name,
                COALESCE(c.phone, "") AS client_phone,
                COALESCE(c.email, "") AS client_email,
                COALESCE(c.gst_number, "") AS client_gst_number,
                COALESCE(c.company_name, "") AS client_company_name,
                COALESCE(c.city, "") AS client_city,
                COALESCE(s.title, "-") AS service_title
             FROM orders o
             LEFT JOIN clients c ON c.id = o.client_id
             LEFT JOIN services s ON s.id = o.service_id
             WHERE o.id = :id AND o.partner_id = :partner_id
             LIMIT 1',
            [
                'id' => $orderId,
                'partner_id' => $this->partnerId(),
            ]
        );

        return is_array($row) && $row !== [] ? $row : null;
    }

    public function dashboard(): void
    {
        $this->requirePartner();

        $partnerId = $this->partnerId();

        $stats = [
            'clients' => (int) ($this->db()->fetch('SELECT COUNT(*) AS total FROM clients WHERE partner_id = :partner_id', ['partner_id' => $partnerId])['total'] ?? 0),
            'orders' => (int) ($this->db()->fetch('SELECT COUNT(*) AS total FROM orders WHERE partner_id = :partner_id', ['partner_id' => $partnerId])['total'] ?? 0),
            'pending_orders' => (int) ($this->db()->fetch('SELECT COUNT(*) AS total FROM orders WHERE partner_id = :partner_id AND status IN ("pending_review", "work_in_progress")', ['partner_id' => $partnerId])['total'] ?? 0),
            'revenue' => (float) ($this->db()->fetch('SELECT COALESCE(SUM(p.amount), 0) AS total FROM payments p INNER JOIN orders o ON o.id = p.order_id WHERE o.partner_id = :partner_id', ['partner_id' => $partnerId])['total'] ?? 0),
            'due' => (float) ($this->db()->fetch('SELECT COALESCE(SUM(GREATEST(i.total_amount - i.paid_amount, 0)), 0) AS total FROM invoices i INNER JOIN orders o ON o.id = i.order_id WHERE o.partner_id = :partner_id', ['partner_id' => $partnerId])['total'] ?? 0),
        ];

        $this->view('partner/dashboard', [
            'title' => 'Partner Dashboard – Tax Saathi',
            'stats' => $stats,
            'subscription' => $this->partnerSubscription(),
            'recentOrders' => array_slice($this->partnerOrders(), 0, 8),
        ], 'layouts/partner');
    }

    public function clients(): void
    {
        $this->requirePartner();
        $this->requireSubscription();

        $this->view('partner/clients', [
            'title' => 'Partner Clients – Tax Saathi',
            'rows' => $this->partnerClients(),
        ], 'layouts/partner');
    }

    public function storeClient(): void
    {
        $this->requirePartner();
        $this->requireSubscription();
        verify_csrf();

        $name = trim((string) input('name'));
        $email = trim((string) input('email'));
        $phone = trim((string) input('phone'));
        $companyName = trim((string) input('company_name'));
        $gstNumber = trim((string) input('gst_number'));
        $city = trim((string) input('city'));

        if ($name === '' && $companyName === '') {
            flash('error', 'Please enter client name or company name.');
            redirect('partner/clients');
        }

        $now = date('Y-m-d H:i:s');

        $this->db()->execute(
            'INSERT INTO clients
            (partner_id, name, email, phone, company_name, gst_number, city, created_at, updated_at)
            VALUES
            (:partner_id, :name, :email, :phone, :company_name, :gst_number, :city, :created_at, :updated_at)',
            [
                'partner_id' => $this->partnerId(),
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'company_name' => $companyName,
                'gst_number' => $gstNumber,
                'city' => $city,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        flash('success', 'Client added successfully.');
        redirect('partner/clients');
    }

    public function updateClient(): void
    {
        $this->requirePartner();
        $this->requireSubscription();
        verify_csrf();

        $id = (int) input('id');
        $client = $this->partnerClient($id);

        if (!$client) {
            flash('error', 'Client not found or access denied.');
            redirect('partner/clients');
        }

        $this->db()->execute(
            'UPDATE clients
             SET name = :name,
                 email = :email,
                 phone = :phone,
                 company_name = :company_name,
                 gst_number = :gst_number,
                 city = :city,
                 updated_at = :updated_at
             WHERE id = :id AND partner_id = :partner_id',
            [
                'name' => trim((string) input('name')),
                'email' => trim((string) input('email')),
                'phone' => trim((string) input('phone')),
                'company_name' => trim((string) input('company_name')),
                'gst_number' => trim((string) input('gst_number')),
                'city' => trim((string) input('city')),
                'updated_at' => date('Y-m-d H:i:s'),
                'id' => $id,
                'partner_id' => $this->partnerId(),
            ]
        );

        flash('success', 'Client updated.');
        redirect('partner/clients');
    }

    public function deleteClient(): void
    {
        $this->requirePartner();
        $this->requireSubscription();
        verify_csrf();

        $id = (int) input('id');

        $client = $this->partnerClient($id);
        if (!$client) {
            flash('error', 'Client not found or access denied.');
            redirect('partner/clients');
        }

        $orders = $this->db()->fetch(
            'SELECT COUNT(*) AS total FROM orders WHERE client_id = :client_id AND partner_id = :partner_id',
            [
                'client_id' => $id,
                'partner_id' => $this->partnerId(),
            ]
        );

        if ((int) ($orders['total'] ?? 0) > 0) {
            flash('error', 'This client has orders. Delete or reassign orders before deleting client.');
            redirect('partner/clients');
        }

        $this->db()->execute(
            'DELETE FROM clients WHERE id = :id AND partner_id = :partner_id',
            [
                'id' => $id,
                'partner_id' => $this->partnerId(),
            ]
        );

        flash('success', 'Client deleted.');
        redirect('partner/clients');
    }

    public function orders(): void
    {
        $this->requirePartner();
        $this->requireSubscription();

        $filters = [
            'status' => trim((string) input('status')),
            'q' => trim((string) input('q')),
        ];

        $this->view('partner/orders', [
            'title' => 'Partner Orders – Tax Saathi',
            'rows' => $this->partnerOrders($filters),
            'clients' => $this->partnerClients(),
            'services' => $this->services(),
            'filters' => $filters,
            'subscription' => $this->partnerSubscription(),
            'ordersUsed' => $this->ordersUsedInSubscriptionWindow(),
        ], 'layouts/partner');
    }

    public function storeOrder(): void
    {
        $this->requirePartner();
        $this->requireOrderLimitAvailable();
        verify_csrf();

        $clientId = (int) input('client_id');
        $serviceId = (int) input('service_id');
        $client = $this->partnerClient($clientId);
        $service = $this->service($serviceId);

        if (!$client) {
            flash('error', 'Please choose one of your own clients.');
            redirect('partner/orders');
        }

        if (!$service) {
            flash('error', 'Please choose a valid service.');
            redirect('partner/orders');
        }

        $fee = (float) input('fee_amount', (float) ($service['filing_fee'] ?? 0));
        if ($fee <= 0) {
            $fee = (float) ($service['filing_fee'] ?? 0);
        }

        $status = trim((string) input('status', 'pending_review'));
        if (!in_array($status, ['pending_review', 'approved', 'work_in_progress', 'completed', 'rejected'], true)) {
            $status = 'pending_review';
        }

        $paymentStatus = trim((string) input('payment_status', 'pending'));
        if (!in_array($paymentStatus, ['pending', 'pending_review', 'verified', 'paid', 'partial', 'unpaid', 'failed'], true)) {
            $paymentStatus = 'pending';
        }

        $now = date('Y-m-d H:i:s');
        $orderNo = $this->nextOrderNo();

        $this->db()->execute(
            'INSERT INTO orders
            (
                order_no,
                partner_id,
                client_id,
                service_id,
                financial_year,
                fee_amount,
                status,
                payment_method,
                payment_status,
                payment_reference,
                notes,
                assigned_user_id,
                created_at,
                updated_at
            )
            VALUES
            (
                :order_no,
                :partner_id,
                :client_id,
                :service_id,
                :financial_year,
                :fee_amount,
                :status,
                :payment_method,
                :payment_status,
                :payment_reference,
                :notes,
                :assigned_user_id,
                :created_at,
                :updated_at
            )',
            [
                'order_no' => $orderNo,
                'partner_id' => $this->partnerId(),
                'client_id' => $clientId,
                'service_id' => $serviceId,
                'financial_year' => trim((string) input('financial_year', date('Y') . '-' . date('y', strtotime('+1 year')))),
                'fee_amount' => $fee,
                'status' => $status,
                'payment_method' => trim((string) input('payment_method', 'manual')),
                'payment_status' => $paymentStatus,
                'payment_reference' => trim((string) input('payment_reference')) ?: null,
                'notes' => trim((string) input('notes')) ?: null,
                'assigned_user_id' => $this->partnerId(),
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        flash('success', 'Order created successfully.');
        redirect('partner/orders');
    }

    public function showOrder(): void
    {
        $this->requirePartner();
        $this->requireSubscription();

        $order = $this->partnerOrder((int) input('id'));

        if (!$order) {
            flash('error', 'Order not found or access denied.');
            redirect('partner/orders');
        }

        $invoice = Invoice::findByOrder((int) $order['id']);

        $this->view('partner/order-show', [
            'title' => 'Partner Order ' . ($order['order_no'] ?? '') . ' – Tax Saathi',
            'order' => $order,
            'invoice' => $invoice,
            'payments' => Payment::forOrder((int) $order['id']),
        ], 'layouts/partner');
    }

    public function updateOrderStatus(): void
    {
        $this->requirePartner();
        $this->requireSubscription();
        verify_csrf();

        $order = $this->partnerOrder((int) input('id'));
        if (!$order) {
            flash('error', 'Order not found or access denied.');
            redirect('partner/orders');
        }

        $status = trim((string) input('status', 'pending_review'));
        if (!in_array($status, ['pending_review', 'approved', 'work_in_progress', 'completed', 'rejected'], true)) {
            flash('error', 'Invalid status.');
            redirect('partner/orders/show?id=' . (int) $order['id']);
        }

        $this->db()->execute(
            'UPDATE orders
             SET status = :status,
                 admin_notes = :admin_notes,
                 updated_at = :updated_at
             WHERE id = :id AND partner_id = :partner_id',
            [
                'status' => $status,
                'admin_notes' => trim((string) input('admin_notes', (string) ($order['admin_notes'] ?? ''))),
                'updated_at' => date('Y-m-d H:i:s'),
                'id' => (int) $order['id'],
                'partner_id' => $this->partnerId(),
            ]
        );

        flash('success', 'Order status updated.');
        redirect('partner/orders/show?id=' . (int) $order['id']);
    }

    public function deleteOrder(): void
    {
        $this->requirePartner();
        $this->requireSubscription();
        verify_csrf();

        $order = $this->partnerOrder((int) input('id'));

        if (!$order) {
            flash('error', 'Order not found or access denied.');
            redirect('partner/orders');
        }

        $orderId = (int) $order['id'];

        foreach ([
            ['DELETE FROM order_documents WHERE order_id = :order_id', ['order_id' => $orderId]],
            ['DELETE FROM payments WHERE order_id = :order_id', ['order_id' => $orderId]],
            ['DELETE FROM invoices WHERE order_id = :order_id', ['order_id' => $orderId]],
            ['DELETE FROM activity_logs WHERE order_id = :order_id', ['order_id' => $orderId]],
        ] as $statement) {
            try {
                $this->db()->execute($statement[0], $statement[1]);
            } catch (\Throwable $e) {
                error_log('Partner order related delete skipped: ' . $e->getMessage());
            }
        }

        $this->db()->execute(
            'DELETE FROM orders WHERE id = :id AND partner_id = :partner_id',
            [
                'id' => $orderId,
                'partner_id' => $this->partnerId(),
            ]
        );

        flash('success', 'Order deleted.');
        redirect('partner/orders');
    }

    public function generateInvoice(): void
    {
        $this->requirePartner();
        $this->requireSubscription();
        verify_csrf();

        $order = $this->partnerOrder((int) input('order_id'));
        if (!$order) {
            flash('error', 'Order not found or access denied.');
            redirect('partner/orders');
        }

        $existing = Invoice::findByOrder((int) $order['id']);

        $subtotal = (float) input('subtotal', (float) ($order['fee_amount'] ?? 0));
        $taxPercent = (float) input('tax_percent', 0);
        $taxAmount = round(($subtotal * $taxPercent) / 100, 2);
        $total = round($subtotal + $taxAmount, 2);
        $paid = (float) input('paid_amount', 0);
        $status = $paid >= $total && $total > 0 ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid');

        $payload = [
            'order_id' => (int) $order['id'],
            'partner_id' => $this->partnerId(),
            'client_id' => (int) ($order['client_id'] ?? 0),
            'invoice_no' => $existing['invoice_no'] ?? $this->nextInvoiceNo(),
            'issue_date' => (string) input('issue_date', date('Y-m-d')),
            'due_date' => (string) input('due_date', date('Y-m-d', strtotime('+7 days'))),
            'subtotal' => $subtotal,
            'tax_percent' => $taxPercent,
            'tax_amount' => $taxAmount,
            'total_amount' => $total,
            'paid_amount' => $paid,
            'status' => $status,
            'notes' => trim((string) input('notes')),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($existing) {
            Invoice::update((int) $existing['id'], $payload);
        } else {
            $payload['created_at'] = date('Y-m-d H:i:s');
            Invoice::insert($payload);
        }

        flash('success', 'Invoice generated successfully.');
        redirect('partner/orders/show?id=' . (int) $order['id']);
    }

    public function recordPayment(): void
    {
        $this->requirePartner();
        $this->requireSubscription();
        verify_csrf();

        $order = $this->partnerOrder((int) input('order_id'));
        if (!$order) {
            flash('error', 'Order not found or access denied.');
            redirect('partner/orders');
        }

        $invoice = Invoice::findByOrder((int) $order['id']);
        if (!$invoice) {
            flash('error', 'Generate invoice before recording payment.');
            redirect('partner/orders/show?id=' . (int) $order['id']);
        }

        $amount = (float) input('amount', 0);
        if ($amount <= 0) {
            flash('error', 'Enter valid payment amount.');
            redirect('partner/orders/show?id=' . (int) $order['id']);
        }

        Payment::insert([
            'invoice_id' => (int) $invoice['id'],
            'order_id' => (int) $order['id'],
            'partner_id' => $this->partnerId(),
            'amount' => $amount,
            'method' => trim((string) input('method', 'manual')),
            'reference_no' => trim((string) input('reference_no')),
            'notes' => trim((string) input('notes')),
            'received_by' => $this->partnerId(),
            'received_at' => (string) input('received_at', date('Y-m-d')),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $paid = array_reduce(
            Payment::forOrder((int) $order['id']),
            static fn (float $carry, array $row): float => $carry + (float) $row['amount'],
            0.0
        );

        $invoiceStatus = $paid >= (float) $invoice['total_amount'] ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid');

        Invoice::update((int) $invoice['id'], [
            'paid_amount' => $paid,
            'status' => $invoiceStatus,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->db()->execute(
            'UPDATE orders
             SET payment_status = :payment_status,
                 updated_at = :updated_at
             WHERE id = :id AND partner_id = :partner_id',
            [
                'payment_status' => $invoiceStatus === 'paid' ? 'verified' : $invoiceStatus,
                'updated_at' => date('Y-m-d H:i:s'),
                'id' => (int) $order['id'],
                'partner_id' => $this->partnerId(),
            ]
        );

        flash('success', 'Payment recorded.');
        redirect('partner/orders/show?id=' . (int) $order['id']);
    }

    public function finance(): void
    {
        $this->requirePartner();
        $this->requireSubscription();

        $partnerId = $this->partnerId();

        $summary = [
            'invoice_total' => (float) ($this->db()->fetch('SELECT COALESCE(SUM(total_amount), 0) AS total FROM invoices WHERE partner_id = :partner_id', ['partner_id' => $partnerId])['total'] ?? 0),
            'paid_total' => (float) ($this->db()->fetch('SELECT COALESCE(SUM(amount), 0) AS total FROM payments WHERE partner_id = :partner_id', ['partner_id' => $partnerId])['total'] ?? 0),
            'due_total' => (float) ($this->db()->fetch('SELECT COALESCE(SUM(GREATEST(total_amount - paid_amount, 0)), 0) AS total FROM invoices WHERE partner_id = :partner_id', ['partner_id' => $partnerId])['total'] ?? 0),
            'invoice_count' => (int) ($this->db()->fetch('SELECT COUNT(*) AS total FROM invoices WHERE partner_id = :partner_id', ['partner_id' => $partnerId])['total'] ?? 0),
        ];

        $payments = $this->db()->fetchAll(
            'SELECT
                p.*,
                o.order_no,
                COALESCE(c.company_name, c.name, "-") AS client_name
             FROM payments p
             INNER JOIN orders o ON o.id = p.order_id
             LEFT JOIN clients c ON c.id = o.client_id
             WHERE p.partner_id = :partner_id
             ORDER BY p.id DESC
             LIMIT 100',
            ['partner_id' => $partnerId]
        ) ?: [];

        $this->view('partner/finance', [
            'title' => 'Partner Finance – Tax Saathi',
            'summary' => $summary,
            'payments' => $payments,
        ], 'layouts/partner');
    }

    public function subscription(): void
    {
        $this->requirePartner();

        $this->view('partner/subscription', [
            'title' => 'Partner Subscription – Tax Saathi',
            'subscription' => $this->partnerSubscription(),
            'ordersUsed' => $this->ordersUsedInSubscriptionWindow(),
        ], 'layouts/partner');
    }

    public function subscribe(): void
    {
        $this->requirePartner();
        flash('error', 'Subscription activation is managed by admin only. You can request upgrade or renewal from the Subscription page.');
        redirect('partner/subscription');
    }

    public function profile(): void
    {
        $this->requirePartner();

        $user = $this->db()->fetch(
            'SELECT id, name, email, phone
             FROM users
             WHERE id = :id
             LIMIT 1',
            ['id' => $this->partnerId()]
        ) ?: $this->currentUser();

        $this->view('partner/profile', [
            'title' => 'Partner Profile – Tax Saathi',
            'user' => $user,
        ], 'layouts/partner');
    }

    public function updateProfile(): void
    {
        $this->requirePartner();
        verify_csrf();

        $this->db()->execute(
            'UPDATE users
             SET name = :name,
                 email = :email,
                 phone = :phone,
                 updated_at = :updated_at
             WHERE id = :id',
            [
                'name' => trim((string) input('name')),
                'email' => trim((string) input('email')),
                'phone' => trim((string) input('phone')),
                'updated_at' => date('Y-m-d H:i:s'),
                'id' => $this->partnerId(),
            ]
        );

        flash('success', 'Profile updated.');
        redirect('partner/profile');
    }

    private function logPartnerActivity(string $action, string $description, array $meta = []): void
    {
        try {
            $this->db()->execute(
                'INSERT INTO partner_activity_logs
                (partner_id, actor_id, action, description, meta_json, ip_address, user_agent, created_at)
                VALUES
                (:partner_id, :actor_id, :action, :description, :meta_json, :ip_address, :user_agent, :created_at)',
                [
                    'partner_id' => $this->partnerId(),
                    'actor_id' => $this->currentUserId(),
                    'action' => $action,
                    'description' => $description,
                    'meta_json' => json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                    'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                    'created_at' => date('Y-m-d H:i:s'),
                ]
            );
        } catch (\Throwable $e) {
            error_log('partner activity log failed: ' . $e->getMessage());
        }
    }

    private function notifyPartner(string $type, string $title, string $message, ?int $clientId = null, ?int $orderId = null): void
    {
        try {
            $this->db()->execute(
                'INSERT INTO partner_notifications
                (partner_id, type, title, message, client_id, order_id, is_read, created_at)
                VALUES
                (:partner_id, :type, :title, :message, :client_id, :order_id, 0, :created_at)',
                [
                    'partner_id' => $this->partnerId(),
                    'type' => $type,
                    'title' => $title,
                    'message' => $message,
                    'client_id' => $clientId,
                    'order_id' => $orderId,
                    'created_at' => date('Y-m-d H:i:s'),
                ]
            );
        } catch (\Throwable $e) {
            error_log('partner notification failed: ' . $e->getMessage());
        }
    }

    private function storeUploadedFile(string $field, string $folder): ?string
    {
        if (empty($_FILES[$field]) || !is_array($_FILES[$field]) || (int) ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }

        $tmp = (string) ($_FILES[$field]['tmp_name'] ?? '');
        $name = basename((string) ($_FILES[$field]['name'] ?? 'file'));
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'csv'];
        if (!in_array($ext, $allowed, true)) {
            flash('error', 'Invalid file type.');
            redirect($folder === 'profile' ? 'partner/profile' : 'partner/documents');
        }

        $relativeDir = 'uploads/partners/' . $this->partnerId() . '/' . trim($folder, '/');
        $absoluteDir = dirname(__DIR__, 2) . '/public/' . $relativeDir;
        if (!is_dir($absoluteDir)) {
            mkdir($absoluteDir, 0775, true);
        }

        $safe = preg_replace('/[^A-Za-z0-9._-]/', '-', pathinfo($name, PATHINFO_FILENAME)) ?: 'file';
        $filename = $safe . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
        $target = $absoluteDir . '/' . $filename;

        if (!move_uploaded_file($tmp, $target)) {
            flash('error', 'Unable to upload file.');
            redirect($folder === 'profile' ? 'partner/profile' : 'partner/documents');
        }

        return $relativeDir . '/' . $filename;
    }

    private function partnerStaffRows(): array
    {
        return $this->db()->fetchAll(
            'SELECT * FROM partner_staff WHERE partner_id = :partner_id ORDER BY id DESC',
            ['partner_id' => $this->partnerId()]
        ) ?: [];
    }

    public function invoices(): void
    {
        $this->requirePartner();
        $this->requireActiveSubscription();

        $rows = $this->db()->fetchAll(
            'SELECT i.*, o.order_no, COALESCE(c.company_name, c.name, "-") AS client_name
             FROM invoices i
             INNER JOIN orders o ON o.id = i.order_id AND o.partner_id = i.partner_id
             LEFT JOIN clients c ON c.id = i.client_id AND c.partner_id = i.partner_id
             WHERE i.partner_id = :partner_id
             ORDER BY i.id DESC',
            ['partner_id' => $this->partnerId()]
        ) ?: [];

        $this->view('partner/invoices', [
            'title' => 'Invoices – Partner',
            'rows' => $rows,
        ], 'layouts/partner');
    }

    public function payments(): void
    {
        $this->requirePartner();
        $this->requireActiveSubscription();

        $rows = $this->db()->fetchAll(
            'SELECT p.*, o.order_no, COALESCE(c.company_name, c.name, "-") AS client_name, i.invoice_no
             FROM payments p
             INNER JOIN orders o ON o.id = p.order_id AND o.partner_id = p.partner_id
             LEFT JOIN invoices i ON i.id = p.invoice_id AND i.partner_id = p.partner_id
             LEFT JOIN clients c ON c.id = o.client_id AND c.partner_id = o.partner_id
             WHERE p.partner_id = :partner_id
             ORDER BY p.id DESC',
            ['partner_id' => $this->partnerId()]
        ) ?: [];

        $this->view('partner/payments', [
            'title' => 'Payments – Partner',
            'rows' => $rows,
        ], 'layouts/partner');
    }

    public function documents(): void
    {
        $this->requirePartner();
        $this->requireActiveSubscription();

        $rows = $this->db()->fetchAll(
            'SELECT d.*, COALESCE(c.company_name, c.name, "-") AS client_name, o.order_no
             FROM partner_documents d
             LEFT JOIN clients c ON c.id = d.client_id AND c.partner_id = d.partner_id
             LEFT JOIN orders o ON o.id = d.order_id AND o.partner_id = d.partner_id
             WHERE d.partner_id = :partner_id
             ORDER BY d.id DESC',
            ['partner_id' => $this->partnerId()]
        ) ?: [];

        $this->view('partner/documents', [
            'title' => 'Documents – Partner',
            'rows' => $rows,
            'clients' => $this->partnerClients(),
            'orders' => $this->partnerOrders(),
        ], 'layouts/partner');
    }

    public function uploadDocument(): void
    {
        $this->requirePartner();
        $this->requireActiveSubscription();
        verify_csrf();

        $clientId = (int) input('client_id');
        $orderId = (int) input('order_id');
        if ($clientId > 0 && !$this->partnerClient($clientId)) {
            flash('error', 'Client not found or access denied.');
            redirect('partner/documents');
        }
        if ($orderId > 0 && !$this->partnerOrder($orderId)) {
            flash('error', 'Order not found or access denied.');
            redirect('partner/documents');
        }

        $filePath = $this->storeUploadedFile('document_file', 'documents');
        if (!$filePath) {
            flash('error', 'Please choose a document file.');
            redirect('partner/documents');
        }

        $this->db()->execute(
            'INSERT INTO partner_documents
            (partner_id, client_id, order_id, document_type, title, file_path, status, notes, uploaded_by, created_at, updated_at)
            VALUES
            (:partner_id, :client_id, :order_id, :document_type, :title, :file_path, :status, :notes, :uploaded_by, :created_at, :updated_at)',
            [
                'partner_id' => $this->partnerId(),
                'client_id' => $clientId ?: null,
                'order_id' => $orderId ?: null,
                'document_type' => trim((string) input('document_type', 'other')),
                'title' => trim((string) input('title', 'Document')),
                'file_path' => $filePath,
                'status' => 'received',
                'notes' => trim((string) input('notes')),
                'uploaded_by' => $this->currentUserId(),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]
        );

        $this->logPartnerActivity('document.uploaded', 'Document uploaded.', ['client_id' => $clientId, 'order_id' => $orderId]);
        flash('success', 'Document uploaded.');
        redirect('partner/documents');
    }

    public function updateDocumentStatus(): void
    {
        $this->requirePartner();
        $this->requireActiveSubscription();
        verify_csrf();

        $id = (int) input('id');
        $status = trim((string) input('status', 'received'));
        if (!in_array($status, ['required', 'requested', 'received', 'verified', 'rejected', 'completed'], true)) {
            $status = 'received';
        }

        $this->db()->execute(
            'UPDATE partner_documents SET status = :status, notes = :notes, updated_at = :updated_at WHERE id = :id AND partner_id = :partner_id',
            [
                'status' => $status,
                'notes' => trim((string) input('notes')),
                'updated_at' => date('Y-m-d H:i:s'),
                'id' => $id,
                'partner_id' => $this->partnerId(),
            ]
        );

        flash('success', 'Document status updated.');
        redirect('partner/documents');
    }

    public function leads(): void
    {
        $this->requirePartner();
        $this->requireActiveSubscription();
        $rows = $this->db()->fetchAll('SELECT * FROM partner_leads WHERE partner_id = :partner_id ORDER BY id DESC', ['partner_id' => $this->partnerId()]) ?: [];
        $this->view('partner/leads', ['title' => 'Leads – Partner', 'rows' => $rows], 'layouts/partner');
    }

    public function storeLead(): void
    {
        $this->requirePartner();
        $this->requireActiveSubscription();
        verify_csrf();

        $this->db()->execute(
            'INSERT INTO partner_leads
            (partner_id, name, email, phone, company_name, source, status, follow_up_date, notes, created_at, updated_at)
            VALUES
            (:partner_id, :name, :email, :phone, :company_name, :source, :status, :follow_up_date, :notes, :created_at, :updated_at)',
            [
                'partner_id' => $this->partnerId(),
                'name' => trim((string) input('name')),
                'email' => trim((string) input('email')),
                'phone' => trim((string) input('phone')),
                'company_name' => trim((string) input('company_name')),
                'source' => trim((string) input('source')),
                'status' => trim((string) input('status', 'new')),
                'follow_up_date' => trim((string) input('follow_up_date')) ?: null,
                'notes' => trim((string) input('notes')),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]
        );

        flash('success', 'Lead added.');
        redirect('partner/leads');
    }

    public function updateLead(): void
    {
        $this->requirePartner();
        $this->requireActiveSubscription();
        verify_csrf();
        $status = trim((string) input('status', 'new'));
        if (!in_array($status, ['new', 'contacted', 'interested', 'converted', 'closed'], true)) {
            $status = 'new';
        }
        $this->db()->execute(
            'UPDATE partner_leads SET status = :status, follow_up_date = :follow_up_date, notes = :notes, updated_at = :updated_at WHERE id = :id AND partner_id = :partner_id',
            [
                'status' => $status,
                'follow_up_date' => trim((string) input('follow_up_date')) ?: null,
                'notes' => trim((string) input('notes')),
                'updated_at' => date('Y-m-d H:i:s'),
                'id' => (int) input('id'),
                'partner_id' => $this->partnerId(),
            ]
        );
        flash('success', 'Lead updated.');
        redirect('partner/leads');
    }

    public function convertLead(): void
    {
        $this->requirePartner();
        $this->requireActiveSubscription();
        verify_csrf();
        $lead = $this->db()->fetch('SELECT * FROM partner_leads WHERE id = :id AND partner_id = :partner_id LIMIT 1', ['id' => (int) input('id'), 'partner_id' => $this->partnerId()]);
        if (!$lead) {
            flash('error', 'Lead not found.');
            redirect('partner/leads');
        }
        $this->db()->execute(
            'INSERT INTO clients (partner_id, name, email, phone, company_name, status, created_at, updated_at)
             VALUES (:partner_id, :name, :email, :phone, :company_name, "active", :created_at, :updated_at)',
            [
                'partner_id' => $this->partnerId(),
                'name' => (string) ($lead['name'] ?? ''),
                'email' => (string) ($lead['email'] ?? ''),
                'phone' => (string) ($lead['phone'] ?? ''),
                'company_name' => (string) ($lead['company_name'] ?? ''),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]
        );
        $this->db()->execute('UPDATE partner_leads SET status = "converted", updated_at = :updated_at WHERE id = :id AND partner_id = :partner_id', ['updated_at' => date('Y-m-d H:i:s'), 'id' => (int) $lead['id'], 'partner_id' => $this->partnerId()]);
        flash('success', 'Lead converted to client.');
        redirect('partner/clients');
    }

    public function tasks(): void
    {
        $this->requirePartner();
        $this->requireActiveSubscription();
        $rows = $this->db()->fetchAll(
            'SELECT t.*, COALESCE(c.company_name, c.name, "-") AS client_name, o.order_no
             FROM partner_tasks t
             LEFT JOIN clients c ON c.id = t.client_id AND c.partner_id = t.partner_id
             LEFT JOIN orders o ON o.id = t.order_id AND o.partner_id = t.partner_id
             WHERE t.partner_id = :partner_id
             ORDER BY FIELD(t.status, "pending", "in_progress", "completed", "cancelled"), t.due_date ASC, t.id DESC',
            ['partner_id' => $this->partnerId()]
        ) ?: [];
        $this->view('partner/tasks', ['title' => 'Tasks – Partner', 'rows' => $rows, 'clients' => $this->partnerClients(), 'orders' => $this->partnerOrders(), 'staff' => $this->partnerStaffRows()], 'layouts/partner');
    }

    public function storeTask(): void
    {
        $this->requirePartner();
        $this->requireActiveSubscription();
        verify_csrf();
        $this->db()->execute(
            'INSERT INTO partner_tasks (partner_id, client_id, order_id, assigned_staff_id, title, priority, status, due_date, notes, created_by, created_at, updated_at)
             VALUES (:partner_id, :client_id, :order_id, :assigned_staff_id, :title, :priority, "pending", :due_date, :notes, :created_by, :created_at, :updated_at)',
            [
                'partner_id' => $this->partnerId(),
                'client_id' => (int) input('client_id') ?: null,
                'order_id' => (int) input('order_id') ?: null,
                'assigned_staff_id' => (int) input('assigned_staff_id') ?: null,
                'title' => trim((string) input('title')),
                'priority' => trim((string) input('priority', 'medium')),
                'due_date' => trim((string) input('due_date')) ?: null,
                'notes' => trim((string) input('notes')),
                'created_by' => $this->currentUserId(),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]
        );
        flash('success', 'Task created.');
        redirect('partner/tasks');
    }

    public function updateTask(): void
    {
        $this->requirePartner();
        $this->requireActiveSubscription();
        verify_csrf();
        $status = trim((string) input('status', 'pending'));
        if (!in_array($status, ['pending', 'in_progress', 'completed', 'cancelled'], true)) {
            $status = 'pending';
        }
        $this->db()->execute(
            'UPDATE partner_tasks SET status = :status, priority = :priority, due_date = :due_date, notes = :notes, updated_at = :updated_at WHERE id = :id AND partner_id = :partner_id',
            [
                'status' => $status,
                'priority' => trim((string) input('priority', 'medium')),
                'due_date' => trim((string) input('due_date')) ?: null,
                'notes' => trim((string) input('notes')),
                'updated_at' => date('Y-m-d H:i:s'),
                'id' => (int) input('id'),
                'partner_id' => $this->partnerId(),
            ]
        );
        flash('success', 'Task updated.');
        redirect('partner/tasks');
    }

    public function reports(): void
    {
        $this->requirePartner();
        $this->requireActiveSubscription();
        $partnerId = $this->partnerId();
        $summary = [
            'clients' => (int) ($this->db()->fetch('SELECT COUNT(*) AS total FROM clients WHERE partner_id = :partner_id', ['partner_id' => $partnerId])['total'] ?? 0),
            'orders' => (int) ($this->db()->fetch('SELECT COUNT(*) AS total FROM orders WHERE partner_id = :partner_id', ['partner_id' => $partnerId])['total'] ?? 0),
            'completed' => (int) ($this->db()->fetch('SELECT COUNT(*) AS total FROM orders WHERE partner_id = :partner_id AND status = "completed"', ['partner_id' => $partnerId])['total'] ?? 0),
            'pending_docs' => (int) ($this->db()->fetch('SELECT COUNT(*) AS total FROM partner_documents WHERE partner_id = :partner_id AND status IN ("required", "requested")', ['partner_id' => $partnerId])['total'] ?? 0),
            'revenue' => (float) ($this->db()->fetch('SELECT COALESCE(SUM(amount),0) AS total FROM payments WHERE partner_id = :partner_id', ['partner_id' => $partnerId])['total'] ?? 0),
        ];
        $monthly = $this->db()->fetchAll('SELECT DATE_FORMAT(created_at, "%Y-%m") AS month, COUNT(*) AS orders_count, COALESCE(SUM(fee_amount), 0) AS order_value FROM orders WHERE partner_id = :partner_id GROUP BY DATE_FORMAT(created_at, "%Y-%m") ORDER BY month DESC LIMIT 12', ['partner_id' => $partnerId]) ?: [];
        $this->view('partner/reports', ['title' => 'Reports – Partner', 'summary' => $summary, 'monthly' => $monthly], 'layouts/partner');
    }

    public function exportReport(): void
    {
        $this->requirePartner();
        $this->requireActiveSubscription();
        $type = trim((string) input('type', 'orders'));
        $filename = 'partner-' . $type . '-' . date('Ymd-His') . '.csv';
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $out = fopen('php://output', 'w');
        if ($type === 'payments') {
            fputcsv($out, ['Date', 'Order No', 'Amount', 'Method', 'Reference']);
            $rows = $this->db()->fetchAll('SELECT p.*, o.order_no FROM payments p LEFT JOIN orders o ON o.id = p.order_id WHERE p.partner_id = :partner_id ORDER BY p.id DESC', ['partner_id' => $this->partnerId()]) ?: [];
            foreach ($rows as $row) {
                fputcsv($out, [$row['received_at'] ?? $row['created_at'] ?? '', $row['order_no'] ?? '', $row['amount'] ?? 0, $row['method'] ?? '', $row['reference_no'] ?? '']);
            }
        } else {
            fputcsv($out, ['Order No', 'Client', 'Status', 'Payment', 'Fee', 'Created']);
            foreach ($this->partnerOrders() as $row) {
                fputcsv($out, [$row['order_no'] ?? '', $row['client_name'] ?? '', $row['status'] ?? '', $row['payment_status'] ?? '', $row['fee_amount'] ?? 0, $row['created_at'] ?? '']);
            }
        }
        fclose($out);
        exit;
    }

    public function staff(): void
    {
        $this->requirePartner();
        $this->requireActiveSubscription();
        $this->view('partner/staff', ['title' => 'Staff – Partner', 'rows' => $this->partnerStaffRows()], 'layouts/partner');
    }

    public function storeStaff(): void
    {
        $this->requirePartner();
        $this->requireActiveSubscription();
        verify_csrf();
        $this->db()->execute(
            'INSERT INTO partner_staff (partner_id, name, email, phone, permissions_json, status, created_at, updated_at)
             VALUES (:partner_id, :name, :email, :phone, :permissions_json, :status, :created_at, :updated_at)',
            [
                'partner_id' => $this->partnerId(),
                'name' => trim((string) input('name')),
                'email' => trim((string) input('email')),
                'phone' => trim((string) input('phone')),
                'permissions_json' => json_encode((array) input('permissions', [])),
                'status' => trim((string) input('status', 'active')),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]
        );
        flash('success', 'Staff added.');
        redirect('partner/staff');
    }

    public function updateStaff(): void
    {
        $this->requirePartner();
        $this->requireActiveSubscription();
        verify_csrf();
        $this->db()->execute('UPDATE partner_staff SET status = :status, permissions_json = :permissions_json, updated_at = :updated_at WHERE id = :id AND partner_id = :partner_id', [
            'status' => trim((string) input('status', 'active')),
            'permissions_json' => json_encode((array) input('permissions', [])),
            'updated_at' => date('Y-m-d H:i:s'),
            'id' => (int) input('id'),
            'partner_id' => $this->partnerId(),
        ]);
        flash('success', 'Staff updated.');
        redirect('partner/staff');
    }

    public function portal(): void
    {
        $this->requirePartner();
        $this->requireActiveSubscription();
        $rows = $this->db()->fetchAll(
            'SELECT c.*, COALESCE(pa.status, "not_invited") AS portal_status, pa.last_invited_at, pa.last_login_at
             FROM clients c
             LEFT JOIN partner_client_portal_access pa ON pa.client_id = c.id AND pa.partner_id = c.partner_id
             WHERE c.partner_id = :partner_id
             ORDER BY c.id DESC',
            ['partner_id' => $this->partnerId()]
        ) ?: [];
        $this->view('partner/portal', ['title' => 'Client Portal Control – Partner', 'rows' => $rows], 'layouts/partner');
    }

    public function inviteClientPortal(): void
    {
        $this->requirePartner();
        $this->requireActiveSubscription();
        verify_csrf();
        $clientId = (int) input('client_id');
        if (!$this->partnerClient($clientId)) {
            flash('error', 'Client not found.');
            redirect('partner/portal');
        }
        $token = bin2hex(random_bytes(24));
        $this->db()->execute(
            'INSERT INTO partner_client_portal_access (partner_id, client_id, invite_token, status, last_invited_at, created_at, updated_at)
             VALUES (:partner_id, :client_id, :invite_token, "invited", :last_invited_at, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE invite_token = VALUES(invite_token), status = "invited", last_invited_at = VALUES(last_invited_at), updated_at = VALUES(updated_at)',
            ['partner_id' => $this->partnerId(), 'client_id' => $clientId, 'invite_token' => $token, 'last_invited_at' => date('Y-m-d H:i:s'), 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]
        );
        flash('success', 'Client portal invite generated.');
        redirect('partner/portal');
    }

    public function notifications(): void
    {
        $this->requirePartner();
        $rows = $this->db()->fetchAll('SELECT * FROM partner_notifications WHERE partner_id = :partner_id ORDER BY id DESC LIMIT 100', ['partner_id' => $this->partnerId()]) ?: [];
        $this->view('partner/notifications', ['title' => 'Notifications – Partner', 'rows' => $rows], 'layouts/partner');
    }

    public function activityLogs(): void
    {
        $this->requirePartner();
        $rows = $this->db()->fetchAll('SELECT * FROM partner_activity_logs WHERE partner_id = :partner_id ORDER BY id DESC LIMIT 200', ['partner_id' => $this->partnerId()]) ?: [];
        $this->view('partner/activity-logs', ['title' => 'Activity Logs – Partner', 'rows' => $rows], 'layouts/partner');
    }

    public function sendCommunication(): void
    {
        $this->requirePartner();
        $this->requireActiveSubscription();
        verify_csrf();
        $clientId = (int) input('client_id');
        $client = $this->partnerClient($clientId);
        if (!$client) {
            flash('error', 'Client not found.');
            redirect('partner/clients');
        }
        $channel = trim((string) input('channel', 'email'));
        $message = trim((string) input('message'));
        $subject = trim((string) input('subject', 'Tax Saathi Update'));
        $this->db()->execute(
            'INSERT INTO partner_messages (partner_id, client_id, channel, subject, message, status, created_at)
             VALUES (:partner_id, :client_id, :channel, :subject, :message, "queued", :created_at)',
            ['partner_id' => $this->partnerId(), 'client_id' => $clientId, 'channel' => $channel, 'subject' => $subject, 'message' => $message, 'created_at' => date('Y-m-d H:i:s')]
        );
        flash('success', 'Message saved to communication history.');
        redirect('partner/clients');
    }

    public function requestSubscription(): void
    {
        $this->requirePartner();
        verify_csrf();
        $this->db()->execute(
            'INSERT INTO partner_subscription_requests (partner_id, request_type, requested_plan, requested_orders_limit, message, status, created_at, updated_at)
             VALUES (:partner_id, :request_type, :requested_plan, :requested_orders_limit, :message, "pending", :created_at, :updated_at)',
            [
                'partner_id' => $this->partnerId(),
                'request_type' => trim((string) input('request_type', 'upgrade')),
                'requested_plan' => trim((string) input('requested_plan', 'Partner Pro')),
                'requested_orders_limit' => (int) input('requested_orders_limit', 0),
                'message' => trim((string) input('message')),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]
        );
        flash('success', 'Subscription request sent to admin.');
        redirect('partner/subscription');
    }

    public function updatePartnerBranding(): void
    {
        $this->requirePartner();
        verify_csrf();
        $logo = $this->storeUploadedFile('logo', 'profile');
        $existing = $this->db()->fetch('SELECT id, logo FROM partner_profiles WHERE partner_id = :partner_id LIMIT 1', ['partner_id' => $this->partnerId()]);
        $payload = [
            'partner_id' => $this->partnerId(),
            'business_name' => trim((string) input('business_name')),
            'gst_number' => trim((string) input('gst_number')),
            'pan_number' => trim((string) input('pan_number')),
            'address' => trim((string) input('address')),
            'city' => trim((string) input('city')),
            'state_name' => trim((string) input('state_name')),
            'bank_name' => trim((string) input('bank_name')),
            'bank_account_number' => trim((string) input('bank_account_number')),
            'bank_ifsc' => trim((string) input('bank_ifsc')),
            'upi_id' => trim((string) input('upi_id')),
            'invoice_footer' => trim((string) input('invoice_footer')),
            'whatsapp_signature' => trim((string) input('whatsapp_signature')),
            'logo' => $logo ?: ($existing['logo'] ?? null),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($existing) {
            $this->db()->execute(
                'UPDATE partner_profiles SET business_name=:business_name, gst_number=:gst_number, pan_number=:pan_number, address=:address, city=:city, state_name=:state_name, bank_name=:bank_name, bank_account_number=:bank_account_number, bank_ifsc=:bank_ifsc, upi_id=:upi_id, invoice_footer=:invoice_footer, whatsapp_signature=:whatsapp_signature, logo=:logo, updated_at=:updated_at WHERE partner_id=:partner_id',
                $payload
            );
        } else {
            $payload['created_at'] = date('Y-m-d H:i:s');
            $this->db()->execute(
                'INSERT INTO partner_profiles (partner_id, business_name, gst_number, pan_number, address, city, state_name, bank_name, bank_account_number, bank_ifsc, upi_id, invoice_footer, whatsapp_signature, logo, created_at, updated_at) VALUES (:partner_id, :business_name, :gst_number, :pan_number, :address, :city, :state_name, :bank_name, :bank_account_number, :bank_ifsc, :upi_id, :invoice_footer, :whatsapp_signature, :logo, :created_at, :updated_at)',
                $payload
            );
        }
        flash('success', 'Partner branding updated.');
        redirect('partner/profile');
    }

}