<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use RuntimeException;
use Throwable;

final class PartnerOrderController extends Controller
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
                } catch (\Throwable $e) {
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
            } catch (\Throwable $e) {
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

    private function requirePartner(): void
    {
        if ($this->currentUserHasRoleId(4)) {
            return;
        }

        flash('error', 'Partner access required.');
        redirect('auth');
        exit;
    }

    private function partnerId(): int
    {
        $id = $this->currentUserId();

        if ($id <= 0) {
            flash('error', 'Please login again.');
            redirect('auth');
        }

        return $id;
    }

    /**
     * Resolve the canonical clients.id linked to the authenticated partner.
     *
     * Partner-submitted orders are stored in orders, whose client_id remains
     * mandatory for compatibility with the client/admin workflow. The
     * partner account itself is kept in orders.partner_id, so this lookup
     * never replaces the partner ownership boundary.
     */
    private function partnerClientId(): int
    {
        $partnerId = $this->partnerId();
        $user = $this->db()->fetch(
            'SELECT id, client_id, name, phone, email
             FROM users
             WHERE id = :id
             LIMIT 1',
            ['id' => $partnerId]
        );

        if (!$user) {
            throw new RuntimeException('Authenticated partner account was not found.');
        }

        $linkedClientId = (int) ($user['client_id'] ?? 0);

        if ($linkedClientId > 0) {
            $linkedClient = $this->db()->fetch(
                'SELECT id FROM clients WHERE id = :id LIMIT 1',
                ['id' => $linkedClientId]
            );

            if ($linkedClient) {
                return $linkedClientId;
            }
        }

        $email = trim((string) ($user['email'] ?? ''));
        $phone = trim((string) ($user['phone'] ?? ''));
        $client = null;

        if ($email !== '') {
            $client = $this->db()->fetch(
                'SELECT id FROM clients WHERE email = :email ORDER BY id ASC LIMIT 1',
                ['email' => $email]
            );
        }

        if (!$client && $phone !== '') {
            $client = $this->db()->fetch(
                'SELECT id FROM clients WHERE phone = :phone ORDER BY id ASC LIMIT 1',
                ['phone' => $phone]
            );
        }

        if ($client) {
            $clientId = (int) ($client['id'] ?? 0);
        } else {
            $clientPhone = $phone !== '' ? $phone : 'partner-' . $partnerId;
            $this->db()->execute(
                'INSERT INTO clients
                    (name, phone, email, client_type, status, created_at, updated_at)
                 VALUES
                    (:name, :phone, :email, "individual", "active", :created_at, :updated_at)',
                [
                    'name' => trim((string) ($user['name'] ?? 'Partner')) ?: 'Partner',
                    'phone' => $clientPhone,
                    'email' => $email !== '' ? $email : null,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]
            );
            $clientId = $this->db()->lastInsertId();
        }

        if ($clientId <= 0) {
            throw new RuntimeException('Unable to link a client profile to this partner account.');
        }

        $this->db()->execute(
            'UPDATE users SET client_id = :client_id, updated_at = :updated_at WHERE id = :id LIMIT 1',
            [
                'client_id' => $clientId,
                'updated_at' => date('Y-m-d H:i:s'),
                'id' => $partnerId,
            ]
        );

        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        foreach (['user', 'auth_user'] as $sessionKey) {
            if (is_array($_SESSION[$sessionKey] ?? null)) {
                $_SESSION[$sessionKey]['client_id'] = $clientId;
            }
        }

        return $clientId;
    }

    private function nextOrderNo(): string
    {
        return 'TMP-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
    }

    private function servicesList(): array
    {
        return $this->db()->fetchAll(
            'SELECT id, title, slug, excerpt, COALESCE(filing_fee, 0) AS filing_fee, COALESCE(turnaround_days, 0) AS turnaround_days
             FROM services
             WHERE COALESCE(is_active, 1) = 1
             ORDER BY COALESCE(sort_order, 0) ASC, title ASC'
        ) ?: [];
    }

    private function service(int $id): ?array
    {
        $row = $this->db()->fetch(
            'SELECT id, title, slug, excerpt, description, COALESCE(filing_fee, 0) AS filing_fee, COALESCE(turnaround_days, 0) AS turnaround_days
             FROM services
             WHERE id = :id AND COALESCE(is_active, 1) = 1
             LIMIT 1',
            ['id' => $id]
        );

        return is_array($row) && $row !== [] ? $row : null;
    }

    private function serviceRequirements(int $serviceId): array
    {
        if ($serviceId <= 0) {
            return [];
        }

        return $this->db()->fetchAll(
            'SELECT
                id,
                service_id,
                label,
                icon,
                help_text,
                is_required,
                allow_multiple,
                sort_order,
                is_active,
                created_at,
                updated_at
             FROM service_requirements
             WHERE service_id = :service_id
               AND COALESCE(is_active, 1) = 1
             ORDER BY COALESCE(sort_order, 1) ASC, id ASC',
            ['service_id' => $serviceId]
        ) ?: [];
    }

    private function ordersList(): array
    {
        return $this->db()->fetchAll(
            'SELECT
                o.id,
                o.order_no,
                o.partner_id,
                o.client_id,
                o.service_id,
                o.financial_year,
                COALESCE(o.gross_fee_amount, o.fee_amount) AS filing_fee,
                o.fee_amount,
                o.coupon_id,
                o.coupon_code,
                o.coupon_discount_type,
                o.coupon_discount_value,
                COALESCE(o.coupon_discount_amount, 0) AS coupon_discount_amount,
                COALESCE(o.payable_amount, o.fee_amount) AS payable_amount,
                o.payment_method,
                o.payment_reference,
                o.payment_status,
                o.status AS order_status,
                CASE
                    WHEN o.status = "completed" THEN "completed"
                    WHEN o.status IN ("work_in_progress", "approved") THEN "in_progress"
                    WHEN o.payment_status = "pending_review" THEN "payment_under_review"
                    WHEN o.payment_status = "pending" THEN "pending_payment"
                    ELSE o.status
                END AS filing_status,
                o.notes,
                o.admin_notes,
                o.created_at,
                o.updated_at,
                s.title AS service_title,
                s.slug AS service_slug
             FROM orders o
             INNER JOIN services s ON s.id = o.service_id
             WHERE o.partner_id = :partner_id
             ORDER BY o.id DESC',
            ['partner_id' => $this->partnerId()]
        ) ?: [];
    }

    private function partnerOrder(int $orderId): ?array
    {
        $row = $this->db()->fetch(
            'SELECT
                o.*,
                COALESCE(o.gross_fee_amount, o.fee_amount) AS filing_fee,
                COALESCE(o.coupon_discount_amount, 0) AS coupon_discount_amount,
                COALESCE(o.payable_amount, o.fee_amount) AS payable_amount,
                o.status AS order_status,
                CASE
                    WHEN o.status = "completed" THEN "completed"
                    WHEN o.status IN ("work_in_progress", "approved") THEN "in_progress"
                    WHEN o.payment_status = "pending_review" THEN "payment_under_review"
                    WHEN o.payment_status = "pending" THEN "pending_payment"
                    ELSE o.status
                END AS filing_status,
                s.title AS service_title,
                s.slug AS service_slug
             FROM orders o
             INNER JOIN services s ON s.id = o.service_id
             WHERE o.id = :id AND o.partner_id = :partner_id
             LIMIT 1',
            [
                'id' => $orderId,
                'partner_id' => $this->partnerId(),
            ]
        );

        return is_array($row) && $row !== [] ? $row : null;
    }

    private function customerDetailsForPartnerOrder(int $orderId, int $partnerId): array
    {
        $row = $this->db()->fetch(
            'SELECT
                cd.id,
                cd.order_id,
                cd.name_as_per_pan,
                cd.pan_number,
                cd.mobile,
                cd.email,
                cd.submitted_by_user_id,
                cd.submitted_by_name,
                cd.source
             FROM customer_details cd
             INNER JOIN orders o ON o.id = cd.order_id
             WHERE cd.order_id = :order_id
               AND o.partner_id = :partner_id
             LIMIT 1',
            [
                'order_id' => $orderId,
                'partner_id' => $partnerId,
            ]
        );

        return is_array($row) ? $row : [];
    }

    private function orderCanBeEdited(array $order): bool
    {
        $status = strtolower(trim((string) ($order['status'] ?? $order['order_status'] ?? '')));

        return !in_array($status, [
            'approved',
            'work_in_progress',
            'in_progress',
            'completed',
            'cancelled',
        ], true);
    }

    private function updatePartnerOrderCustomerDetails(
        array $order,
        string $nameAsPerPan,
        string $panNumber,
        string $mobile,
        string $email,
        string $now
    ): void {
        $partnerId = $this->partnerId();
        $existing = $this->db()->fetch(
            'SELECT id
             FROM customer_details
             WHERE order_id = :order_id
             LIMIT 1',
            ['order_id' => (int) ($order['id'] ?? 0)]
        );

        if (is_array($existing) && (int) ($existing['id'] ?? 0) > 0) {
            $this->db()->execute(
                'UPDATE customer_details
                 SET client_id = :client_id,
                     user_id = :user_id,
                     service_id = :service_id,
                     name_as_per_pan = :name_as_per_pan,
                     pan_number = :pan_number,
                     mobile = :mobile,
                     email = :email,
                     updated_at = :updated_at
                 WHERE id = :id
                 LIMIT 1',
                [
                    'client_id' => (int) ($order['client_id'] ?? 0),
                    'user_id' => $partnerId,
                    'service_id' => (int) ($order['service_id'] ?? 0),
                    'name_as_per_pan' => $nameAsPerPan,
                    'pan_number' => $panNumber,
                    'mobile' => $mobile,
                    'email' => $email,
                    'updated_at' => $now,
                    'id' => (int) $existing['id'],
                ]
            );

            return;
        }

        $partner = $this->currentUser();
        $this->db()->execute(
            'INSERT INTO customer_details
            (
                order_id,
                client_id,
                user_id,
                service_id,
                name_as_per_pan,
                pan_number,
                mobile,
                email,
                submitted_by_user_id,
                submitted_by_name,
                submitted_by_email,
                submitted_by_phone,
                source,
                created_at,
                updated_at
            )
            VALUES
            (
                :order_id,
                :client_id,
                :user_id,
                :service_id,
                :name_as_per_pan,
                :pan_number,
                :mobile,
                :email,
                :submitted_by_user_id,
                :submitted_by_name,
                :submitted_by_email,
                :submitted_by_phone,
                "partner_order_edit",
                :created_at,
                :updated_at
            )',
            [
                'order_id' => (int) ($order['id'] ?? 0),
                'client_id' => (int) ($order['client_id'] ?? 0),
                'user_id' => $partnerId,
                'service_id' => (int) ($order['service_id'] ?? 0),
                'name_as_per_pan' => $nameAsPerPan,
                'pan_number' => $panNumber,
                'mobile' => $mobile,
                'email' => $email,
                'submitted_by_user_id' => $partnerId,
                'submitted_by_name' => (string) ($partner['name'] ?? ''),
                'submitted_by_email' => (string) ($partner['email'] ?? ''),
                'submitted_by_phone' => (string) ($partner['phone'] ?? ''),
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    private function orderDocuments(int $orderId): array
    {
        return $this->db()->fetchAll(
            'SELECT
                d.id,
                d.order_id AS partner_order_id,
                o.partner_id,
                d.requirement_id AS service_requirement_id,
                d.label AS title,
                d.original_name,
                d.stored_name AS file_path,
                d.mime_type,
                d.size_bytes AS file_size,
                COALESCE(d.document_status, "active") AS status,
                COALESCE(d.document_status, "active") AS document_status,
                d.wrong_reason,
                d.wrong_marked_at,
                d.reuploaded_for_document_id,
                d.replaced_by_document_id,
                d.wrong_reason AS admin_notes,
                d.created_at,
                d.created_at AS updated_at
             FROM order_documents d
             INNER JOIN orders o ON o.id = d.order_id
             WHERE d.order_id = :order_id
               AND o.partner_id = :partner_id
               AND d.source IN ("client", "partner")
             ORDER BY d.id ASC',
            [
                'order_id' => $orderId,
                'partner_id' => $this->partnerId(),
            ]
        ) ?: [];
    }

    private function validateCoupon(string $code, int $serviceId, float $subtotal): array
    {
        $code = strtoupper(trim($code));

        if ($code === '') {
            return ['valid' => false, 'message' => '', 'coupon' => null, 'discount_amount' => 0.0];
        }

        $coupon = $this->db()->fetch(
            'SELECT * FROM partner_coupons WHERE UPPER(code) = :code AND is_active = 1 LIMIT 1',
            ['code' => $code]
        );

        if (!$coupon) {
            return ['valid' => false, 'message' => 'Invalid coupon code.', 'coupon' => null, 'discount_amount' => 0.0];
        }

        $today = date('Y-m-d');

        if (!empty($coupon['starts_at']) && substr((string) $coupon['starts_at'], 0, 10) > $today) {
            return ['valid' => false, 'message' => 'Coupon is not active yet.', 'coupon' => null, 'discount_amount' => 0.0];
        }

        if (!empty($coupon['expires_at']) && substr((string) $coupon['expires_at'], 0, 10) < $today) {
            return ['valid' => false, 'message' => 'Coupon has expired.', 'coupon' => null, 'discount_amount' => 0.0];
        }

        if ((int) ($coupon['service_id'] ?? 0) > 0 && (int) $coupon['service_id'] !== $serviceId) {
            return ['valid' => false, 'message' => 'Coupon is not valid for this service.', 'coupon' => null, 'discount_amount' => 0.0];
        }

        $usageLimit = (int) ($coupon['usage_limit'] ?? 0);
        if ($usageLimit > 0) {
            $used = $this->db()->fetch(
                'SELECT COUNT(*) AS total FROM partner_coupon_redemptions WHERE coupon_id = :coupon_id',
                ['coupon_id' => (int) $coupon['id']]
            );

            if ((int) ($used['total'] ?? 0) >= $usageLimit) {
                return ['valid' => false, 'message' => 'Coupon usage limit reached.', 'coupon' => null, 'discount_amount' => 0.0];
            }
        }

        $partnerUsageLimit = (int) ($coupon['partner_usage_limit'] ?? 0);
        if ($partnerUsageLimit > 0) {
            $usedByPartner = $this->db()->fetch(
                'SELECT COUNT(*) AS total FROM partner_coupon_redemptions WHERE coupon_id = :coupon_id AND partner_id = :partner_id',
                [
                    'coupon_id' => (int) $coupon['id'],
                    'partner_id' => $this->partnerId(),
                ]
            );

            if ((int) ($usedByPartner['total'] ?? 0) >= $partnerUsageLimit) {
                return ['valid' => false, 'message' => 'You have already used this coupon.', 'coupon' => null, 'discount_amount' => 0.0];
            }
        }

        if ((float) ($coupon['min_order_amount'] ?? 0) > 0 && $subtotal < (float) $coupon['min_order_amount']) {
            return ['valid' => false, 'message' => 'Order amount is below the coupon minimum.', 'coupon' => null, 'discount_amount' => 0.0];
        }

        $discountType = strtolower((string) ($coupon['discount_type'] ?? 'percent'));
        $discountValue = max(0, (float) ($coupon['discount_value'] ?? 0));

        $discountAmount = $discountType === 'flat'
            ? $discountValue
            : round(($subtotal * $discountValue) / 100, 2);

        $maxDiscount = (float) ($coupon['max_discount_amount'] ?? 0);
        if ($maxDiscount > 0) {
            $discountAmount = min($discountAmount, $maxDiscount);
        }

        $discountAmount = min($discountAmount, $subtotal);

        return [
            'valid' => true,
            'message' => 'Coupon applied successfully.',
            'coupon' => $coupon,
            'discount_amount' => round($discountAmount, 2),
        ];
    }

    public function dashboard(): void
    {
        $this->requirePartner();

        $partnerId = $this->partnerId();

        $stats = [
            'orders' => (int) ($this->db()->fetch('SELECT COUNT(*) AS total FROM orders WHERE partner_id = :partner_id', ['partner_id' => $partnerId])['total'] ?? 0),
            'waiting_for_payment' => (int) ($this->db()->fetch('SELECT COUNT(*) AS total FROM orders WHERE partner_id = :partner_id AND payment_status = "pending"', ['partner_id' => $partnerId])['total'] ?? 0),
            'pending_review' => (int) ($this->db()->fetch('SELECT COUNT(*) AS total FROM orders WHERE partner_id = :partner_id AND payment_status = "pending_review"', ['partner_id' => $partnerId])['total'] ?? 0),
            'completed' => (int) ($this->db()->fetch('SELECT COUNT(*) AS total FROM orders WHERE partner_id = :partner_id AND status = "completed"', ['partner_id' => $partnerId])['total'] ?? 0),
        ];

        $this->view('partner/dashboard', [
            'title' => 'Partner Dashboard – Tax Saathi',
            'stats' => $stats,
            'recentOrders' => array_slice($this->ordersList(), 0, 8),
            'isPartnerUser' => true,
            'partnerRoleId' => 4,
        ], 'layouts/partner');
    }

    public function services(): void
    {
        $this->requirePartner();

        $this->view('partner/services', [
            'title' => 'Select Service – Tax Saathi',
            'services' => $this->servicesList(),
            'isPartnerUser' => true,
            'partnerRoleId' => 4,
        ], 'layouts/partner');
    }

    public function orders(): void
    {
        $this->requirePartner();

        $this->view('public/myapplication', [
            'title' => 'My Applications – Tax Saathi',
            'heading' => 'My Applications',
            'description' => 'Orders submitted through your partner profile are shown here.',
            'rows' => $this->ordersList(),
            'portalRole' => 'partner',
            'isPartnerUser' => true,
            'partnerRoleId' => 4,
            'ordersHomeUrl' => 'partner/orders',
            'viewPath' => 'partner/orders/show',
            'newOrderUrl' => 'partner/services',
        ], 'layouts/partner');
    }

    public function create(): void
    {
        $this->requirePartner();

        $serviceId = (int) input('service_id');
        $service = $this->service($serviceId);

        if (!$service) {
            flash('error', 'Please choose a valid service.');
            redirect('partner/services');
        }

        $couponCode = strtoupper(trim((string) input('coupon_code')));
        $couponResult = $this->validateCoupon($couponCode, $serviceId, (float) $service['filing_fee']);

        $this->view('partner/order-create', [
            'title' => 'Place Order – Tax Saathi',
            'service' => $service,
            'requirements' => $this->serviceRequirements($serviceId),
            'couponCode' => $couponCode,
            'couponResult' => $couponResult,
            'isPartnerUser' => true,
            'partnerRoleId' => 4,
        ], 'layouts/partner');
    }

    public function store(): void
    {
        $this->requirePartner();
        verify_csrf();

        $serviceId = (int) input('service_id');
        $service = $this->service($serviceId);

        if (!$service) {
            flash('error', 'Please choose a valid service.');
            redirect('partner/services');
        }

        $requirements = $this->serviceRequirements($serviceId);
        $missingRequiredDocuments = $this->missingRequiredDocumentLabels($requirements);

        if ($missingRequiredDocuments !== []) {
            flash('error', 'Please upload required document(s): ' . implode(', ', $missingRequiredDocuments));
            redirect('partner/orders/create?service_id=' . $serviceId . '&coupon_code=' . urlencode((string) input('coupon_code', '')));
        }

        $partnerId = $this->partnerId();
        $clientId = $this->partnerClientId();
        $filingFee = round((float) ($service['filing_fee'] ?? 0), 2);
        $couponCode = strtoupper(trim((string) input('coupon_code')));
        $couponResult = $this->validateCoupon($couponCode, $serviceId, $filingFee);

        if ($couponCode !== '' && !$couponResult['valid']) {
            flash('error', (string) $couponResult['message']);
            redirect('partner/orders/create?service_id=' . $serviceId . '&coupon_code=' . urlencode($couponCode));
        }

        $coupon = $couponResult['coupon'];
        $couponDiscountAmount = round((float) ($couponResult['discount_amount'] ?? 0), 2);
        $payableAmount = max(0, round($filingFee - $couponDiscountAmount, 2));
        $paymentMethod = strtolower(trim((string) input('payment_method', 'upi')));

        if (in_array($paymentMethod, ['bank', 'bank-transfer', 'bank transfer'], true)) {
            $paymentMethod = 'bank_transfer';
        } elseif (in_array($paymentMethod, ['cash', 'cod', 'cash-on-delivery'], true)) {
            $paymentMethod = 'cash_on_delivery';
        } elseif ($paymentMethod !== 'bank_transfer' && $paymentMethod !== 'cash_on_delivery') {
            $paymentMethod = 'upi';
        }

        $now = date('Y-m-d H:i:s');

        $this->db()->execute(
            'INSERT INTO orders
            (
                order_no, client_id, partner_id, service_id, financial_year, fee_amount,
                gross_fee_amount,
                coupon_id, coupon_code, coupon_discount_type, coupon_discount_value,
                coupon_discount_amount, payable_amount, payment_method, payment_status,
                status, notes, created_at, updated_at
            )
            VALUES
            (
                :order_no, :client_id, :partner_id, :service_id, :financial_year, :fee_amount,
                :gross_fee_amount,
                :coupon_id, :coupon_code, :coupon_discount_type, :coupon_discount_value,
                :coupon_discount_amount, :payable_amount, :payment_method, :payment_status,
                :status, :notes, :created_at, :updated_at
            )',
            [
                'order_no' => $this->nextOrderNo(),
                'client_id' => $clientId,
                'partner_id' => $partnerId,
                'service_id' => $serviceId,
                'financial_year' => trim((string) input('financial_year', date('Y') . '-' . date('y', strtotime('+1 year')))),
                // orders.fee_amount is the amount actually payable. Keep the
                // original service fee separately for partner reporting.
                'fee_amount' => $payableAmount,
                'gross_fee_amount' => $filingFee,
                'coupon_id' => $coupon ? (int) $coupon['id'] : null,
                'coupon_code' => $coupon ? (string) $coupon['code'] : null,
                'coupon_discount_type' => $coupon ? (string) $coupon['discount_type'] : null,
                'coupon_discount_value' => $coupon ? (float) $coupon['discount_value'] : 0,
                'coupon_discount_amount' => $couponDiscountAmount,
                'payable_amount' => $payableAmount,
                'payment_method' => $paymentMethod,
                'payment_status' => 'pending',
                'status' => 'submitted',
                'notes' => trim((string) input('notes')),
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $orderId = $this->db()->lastInsertId();

        if ($orderId <= 0) {
            flash('error', 'Order created but could not load payment page.');
            redirect('partner/orders');
        }

        $this->db()->execute(
            'UPDATE orders
             SET order_no = :order_no, updated_at = :updated_at
             WHERE id = :id
             LIMIT 1',
            [
                'order_no' => 'TSO-' . date('Y') . '-' . str_pad((string) $orderId, 5, '0', STR_PAD_LEFT),
                'updated_at' => $now,
                'id' => $orderId,
            ]
        );

        if ($coupon) {
            $this->db()->execute(
                'INSERT INTO partner_coupon_redemptions
                (coupon_id, partner_id, partner_order_id, order_id, code, discount_amount, created_at)
                VALUES
                (:coupon_id, :partner_id, NULL, :order_id, :code, :discount_amount, :created_at)',
                [
                    'coupon_id' => (int) $coupon['id'],
                    'partner_id' => $partnerId,
                    'order_id' => $orderId,
                    'code' => (string) $coupon['code'],
                    'discount_amount' => $couponDiscountAmount,
                    'created_at' => $now,
                ]
            );
        }

        $this->handleRequirementUploads($orderId, $requirements);
        $this->savePartnerOrderCustomerDetails($orderId, $clientId, $serviceId, $partnerId);

        flash('success', 'Order placed. Continue with your selected payment method.');
        redirect('partner/orders/payment?order_id=' . $orderId);
    }

    /**
     * Keep the submitter identity in the same customer_details stream used by
     * client orders, so admin/order screens can validate it through users.id.
     */
    private function savePartnerOrderCustomerDetails(
        int $orderId,
        int $clientId,
        int $serviceId,
        int $partnerId
    ): void {
        try {
            $client = $this->db()->fetch(
                'SELECT name, phone, email, pan_number
                 FROM clients
                 WHERE id = :id
                 LIMIT 1',
                ['id' => $clientId]
            ) ?: [];
            $partner = $this->db()->fetch(
                'SELECT name, phone, email
                 FROM users
                 WHERE id = :id
                 LIMIT 1',
                ['id' => $partnerId]
            ) ?: [];
            $now = date('Y-m-d H:i:s');
            $existing = $this->db()->fetch(
                'SELECT id FROM customer_details WHERE order_id = :order_id LIMIT 1',
                ['order_id' => $orderId]
            );

            $params = [
                'client_id' => $clientId,
                'user_id' => $partnerId,
                'service_id' => $serviceId,
                'name_as_per_pan' => (string) ($client['name'] ?? $partner['name'] ?? ''),
                'pan_number' => strtoupper((string) ($client['pan_number'] ?? '')),
                'mobile' => (string) ($client['phone'] ?? $partner['phone'] ?? ''),
                'email' => (string) ($client['email'] ?? $partner['email'] ?? ''),
                'submitted_by_user_id' => $partnerId,
                'submitted_by_name' => (string) ($partner['name'] ?? ''),
                'submitted_by_email' => (string) ($partner['email'] ?? ''),
                'submitted_by_phone' => (string) ($partner['phone'] ?? ''),
                'source' => 'partner_order',
                'updated_at' => $now,
                'order_id' => $orderId,
            ];

            if ($existing) {
                $updateParams = $params;
                unset($updateParams['order_id']);

                $this->db()->execute(
                    'UPDATE customer_details
                     SET client_id = :client_id,
                         user_id = :user_id,
                         service_id = :service_id,
                         name_as_per_pan = :name_as_per_pan,
                         pan_number = :pan_number,
                         mobile = :mobile,
                         email = :email,
                         submitted_by_user_id = :submitted_by_user_id,
                         submitted_by_name = :submitted_by_name,
                         submitted_by_email = :submitted_by_email,
                         submitted_by_phone = :submitted_by_phone,
                         source = :source,
                         updated_at = :updated_at
                     WHERE id = :id
                     LIMIT 1',
                    array_merge($updateParams, ['id' => (int) $existing['id']])
                );
                return;
            }

            $this->db()->execute(
                'INSERT INTO customer_details
                (
                    order_id, client_id, user_id, service_id, name_as_per_pan,
                    pan_number, mobile, email, submitted_by_user_id,
                    submitted_by_name, submitted_by_email, submitted_by_phone,
                    source, created_at, updated_at
                )
                VALUES
                (
                    :order_id, :client_id, :user_id, :service_id, :name_as_per_pan,
                    :pan_number, :mobile, :email, :submitted_by_user_id,
                    :submitted_by_name, :submitted_by_email, :submitted_by_phone,
                    :source, :created_at, :updated_at
                )',
                array_merge($params, ['created_at' => $now])
            );
        } catch (\Throwable $e) {
            // The canonical order must remain usable even if an older
            // installation has not yet created customer_details.
            error_log('Partner customer details save skipped: ' . $e->getMessage());
        }
    }

    private function missingRequiredDocumentLabels(array $requirements): array
    {
        $missing = [];

        foreach ($requirements as $requirement) {
            $requirementId = (int) ($requirement['id'] ?? 0);

            if ($requirementId <= 0) {
                continue;
            }

            if ((int) ($requirement['is_required'] ?? 1) !== 1) {
                continue;
            }

            if (!$this->hasUploadedRequirementFile($requirementId)) {
                $missing[] = (string) ($requirement['label'] ?? ('Requirement #' . $requirementId));
            }
        }

        return $missing;
    }

    private function hasUploadedRequirementFile(int $requirementId): bool
    {
        $inputName = 'requirement_' . $requirementId;

        if (empty($_FILES[$inputName]) || !is_array($_FILES[$inputName])) {
            return false;
        }

        foreach ($this->normalizeUploadedFiles($_FILES[$inputName]) as $file) {
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && !empty($file['tmp_name'])) {
                return true;
            }
        }

        return false;
    }

    private function normalizeUploadedFiles(array $file): array
    {
        if (is_array($file['name'] ?? null)) {
            $normalized = [];

            foreach (array_keys($file['name']) as $index) {
                $normalized[] = [
                    'name' => $file['name'][$index] ?? '',
                    'type' => $file['type'][$index] ?? '',
                    'tmp_name' => $file['tmp_name'][$index] ?? '',
                    'error' => $file['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                    'size' => $file['size'][$index] ?? 0,
                ];
            }

            return $normalized;
        }

        return [$file];
    }

    private function handleRequirementUploads(int $orderId, array $requirements): void
    {
        if ($requirements === []) {
            return;
        }

        $partnerId = $this->partnerId();
        $uploadBase = dirname(__DIR__, 2) . '/storage/uploads/orders/' . $orderId;

        if (!is_dir($uploadBase)) {
            mkdir($uploadBase, 0775, true);
        }

        foreach ($requirements as $requirement) {
            $requirementId = (int) ($requirement['id'] ?? 0);

            if ($requirementId <= 0) {
                continue;
            }

            $inputName = 'requirement_' . $requirementId;

            if (empty($_FILES[$inputName]) || !is_array($_FILES[$inputName])) {
                continue;
            }

            $allowMultiple = (int) ($requirement['allow_multiple'] ?? 0) === 1;
            $requirementLabel = (string) ($requirement['label'] ?? ('Requirement #' . $requirementId));
            $uploadedForRequirement = 0;

            foreach ($this->normalizeUploadedFiles($_FILES[$inputName]) as $file) {
                if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                    continue;
                }

                if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    continue;
                }

                $originalName = basename((string) ($file['name'] ?? 'document'));
                $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx'];

                if (!in_array($extension, $allowed, true)) {
                    continue;
                }

                if ((int) ($file['size'] ?? 0) > 10 * 1024 * 1024) {
                    continue;
                }

                $safeName = 'req-' . $requirementId . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $extension;
                $target = $uploadBase . '/' . $safeName;

                if (!move_uploaded_file((string) $file['tmp_name'], $target)) {
                    continue;
                }

                $relativePath = 'orders/' . $orderId . '/' . $safeName;

                $this->db()->execute(
                    'INSERT INTO order_documents
                    (
                        order_id,
                        requirement_id,
                        source,
                        label,
                        original_name,
                        mime_type,
                        stored_name,
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
                        "client",
                        :label,
                        :original_name,
                        :mime_type,
                        :stored_name,
                        :size_bytes,
                        0,
                        :uploaded_by,
                        :created_at,
                        "active"
                    )',
                    [
                        'order_id' => $orderId,
                        'requirement_id' => $requirementId,
                        'label' => $requirementLabel,
                        'original_name' => $originalName,
                        'mime_type' => (string) ($file['type'] ?? ''),
                        'stored_name' => $relativePath,
                        'size_bytes' => (int) ($file['size'] ?? 0),
                        'uploaded_by' => $partnerId,
                        'created_at' => date('Y-m-d H:i:s'),
                    ]
                );

                $uploadedForRequirement++;

                if (!$allowMultiple && $uploadedForRequirement >= 1) {
                    break;
                }
            }
        }
    }

    private function storePartnerOwnerUpload(array $file, int $orderId): ?array
    {
        if ($orderId <= 0 || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }

        $originalName = basename((string) ($file['name'] ?? 'document'));
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $size = (int) ($file['size'] ?? 0);
        $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx'];

        if (!in_array($extension, $allowed, true) || $size <= 0 || $size > 10 * 1024 * 1024) {
            return null;
        }

        $uploadBase = dirname(__DIR__, 2) . '/storage/uploads/orders/' . $orderId;
        if (!is_dir($uploadBase) && !mkdir($uploadBase, 0775, true) && !is_dir($uploadBase)) {
            return null;
        }

        $safeName = 'owner-' . date('YmdHis') . '-' . bin2hex(random_bytes(8)) . '.' . $extension;
        $target = $uploadBase . DIRECTORY_SEPARATOR . $safeName;

        if (!move_uploaded_file((string) ($file['tmp_name'] ?? ''), $target)) {
            return null;
        }

        $mimeType = (string) ($file['type'] ?? 'application/octet-stream');
        if (class_exists('finfo')) {
            try {
                $finfo = new \finfo(FILEINFO_MIME_TYPE);
                $detected = $finfo->file($target);
                if (is_string($detected) && $detected !== '') {
                    $mimeType = $detected;
                }
            } catch (Throwable $e) {
                error_log('Partner owner upload MIME detection failed: ' . $e->getMessage());
            }
        }

        return [
            'original_name' => $originalName,
            'stored_name' => 'orders/' . $orderId . '/' . $safeName,
            'mime_type' => $mimeType,
            'size_bytes' => $size,
            'absolute_path' => $target,
        ];
    }

    public function update(): void
    {
        $this->requirePartner();
        verify_csrf();

        $orderId = (int) input('order_id', 0);
        $order = $this->partnerOrder($orderId);

        if (!$order) {
            flash('error', 'Order not found or access denied.');
            redirect('partner/orders');
            return;
        }

        if (!$this->orderCanBeEdited($order)) {
            flash('error', 'This order can no longer be edited because processing has started or it is already completed.');
            redirect('partner/orders/show?id=' . $orderId);
            return;
        }

        $nameAsPerPan = trim((string) input('name_as_per_pan', ''));
        $panNumber = strtoupper(trim((string) input('pan_number', '')));
        $mobile = trim((string) input('mobile', ''));
        $email = trim((string) input('email', ''));
        $financialYear = trim((string) input('financial_year', ''));
        $notes = trim((string) input('notes', ''));

        if ($nameAsPerPan === '') {
            flash('error', 'Please enter the name as per PAN.');
            redirect('partner/orders/show?id=' . $orderId . '#edit-order');
            return;
        }

        if ($panNumber === '' || !preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/', $panNumber)) {
            flash('error', 'Please enter a valid PAN number.');
            redirect('partner/orders/show?id=' . $orderId . '#edit-order');
            return;
        }

        if ($mobile === '') {
            flash('error', 'Please enter a mobile number.');
            redirect('partner/orders/show?id=' . $orderId . '#edit-order');
            return;
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Please enter a valid email address.');
            redirect('partner/orders/show?id=' . $orderId . '#edit-order');
            return;
        }

        $nameAsPerPan = function_exists('mb_substr') ? mb_substr($nameAsPerPan, 0, 190) : substr($nameAsPerPan, 0, 190);
        $panNumber = function_exists('mb_substr') ? mb_substr($panNumber, 0, 20) : substr($panNumber, 0, 20);
        $mobile = function_exists('mb_substr') ? mb_substr($mobile, 0, 30) : substr($mobile, 0, 30);
        $email = function_exists('mb_substr') ? mb_substr($email, 0, 190) : substr($email, 0, 190);
        $financialYear = function_exists('mb_substr') ? mb_substr($financialYear, 0, 255) : substr($financialYear, 0, 255);
        $notes = function_exists('mb_substr') ? mb_substr($notes, 0, 5000) : substr($notes, 0, 5000);
        $financialYearValue = $financialYear !== ''
            ? $financialYear
            : trim((string) ($order['financial_year'] ?? ''));
        $now = date('Y-m-d H:i:s');

        try {
            $this->db()->execute('START TRANSACTION');

            $this->db()->execute(
                'UPDATE orders
                 SET financial_year = :financial_year,
                     notes = :notes,
                     updated_at = :updated_at
                 WHERE id = :order_id
                   AND partner_id = :partner_id
                 LIMIT 1',
                [
                    'financial_year' => $financialYearValue,
                    'notes' => $notes !== '' ? $notes : null,
                    'updated_at' => $now,
                    'order_id' => $orderId,
                    'partner_id' => $this->partnerId(),
                ]
            );

            $this->updatePartnerOrderCustomerDetails(
                $order,
                $nameAsPerPan,
                $panNumber,
                $mobile,
                $email,
                $now
            );

            $this->db()->execute('COMMIT');
        } catch (Throwable $e) {
            try {
                $this->db()->execute('ROLLBACK');
            } catch (Throwable $rollbackError) {
                error_log('Partner order edit rollback failed: ' . $rollbackError->getMessage());
            }

            error_log('Partner order edit failed: ' . $e->getMessage());
            flash('error', 'The order changes could not be saved. Please try again.');
            redirect('partner/orders/show?id=' . $orderId . '#edit-order');
            return;
        }

        activity_log(
            $this->currentUserId(),
            $orderId,
            'partner_order.updated_by_owner',
            'Partner updated their own order details before resubmission.',
            [
                'financial_year' => $financialYearValue,
                'customer_name_updated' => true,
            ]
        );

        flash('success', 'Order details updated successfully. Review the information and resubmit the order.');
        redirect('partner/orders/show?id=' . $orderId . '#edit-order');
    }

    public function reuploadDocument(): void
    {
        $this->requirePartner();
        verify_csrf();

        $wrongDocumentId = (int) input('document_id', 0);
        if ($wrongDocumentId <= 0 || empty($_FILES['document']) || !is_array($_FILES['document'])) {
            flash('error', 'Please choose a valid corrected document.');
            redirect('partner/orders');
            return;
        }

        $partnerId = $this->partnerId();
        $wrongDocument = $this->db()->fetch(
            'SELECT
                d.id,
                d.order_id,
                d.requirement_id,
                d.source,
                d.label,
                d.original_name AS old_original_name,
                d.is_client_visible,
                d.wrong_reason,
                o.order_no,
                o.status AS order_status
             FROM order_documents d
             INNER JOIN orders o ON o.id = d.order_id
             WHERE d.id = :document_id
               AND o.partner_id = :partner_id
               AND d.source IN ("client", "partner")
               AND LOWER(COALESCE(d.document_status, "active")) = "wrong"
             LIMIT 1',
            [
                'document_id' => $wrongDocumentId,
                'partner_id' => $partnerId,
            ]
        );

        if (!is_array($wrongDocument) || $wrongDocument === []) {
            flash('error', 'This document is not available for replacement.');
            redirect('partner/orders');
            return;
        }

        $orderId = (int) ($wrongDocument['order_id'] ?? 0);
        if (!$this->orderCanBeEdited(['status' => (string) ($wrongDocument['order_status'] ?? '')])) {
            flash('error', 'This order is no longer accepting document replacements.');
            redirect('partner/orders/show?id=' . $orderId);
            return;
        }

        $meta = $this->storePartnerOwnerUpload($_FILES['document'], $orderId);
        if (!$meta) {
            flash('error', 'Upload failed. Use PDF, JPG, PNG, WEBP, DOC, DOCX, XLS or XLSX under 10 MB.');
            redirect('partner/orders/show?id=' . $orderId . '#my-documents');
            return;
        }

        $replacementId = 0;
        $now = date('Y-m-d H:i:s');

        try {
            $this->db()->execute('START TRANSACTION');

            $locked = $this->db()->fetch(
                'SELECT id, order_id, requirement_id, source, label, is_client_visible, wrong_reason
                 FROM order_documents
                 WHERE id = :document_id
                   AND LOWER(COALESCE(document_status, "active")) = "wrong"
                 LIMIT 1
                 FOR UPDATE',
                ['document_id' => $wrongDocumentId]
            );

            if (!is_array($locked) || (int) ($locked['order_id'] ?? 0) !== $orderId) {
                throw new RuntimeException('This document is no longer available for replacement.');
            }

            $existingReplacement = $this->db()->fetch(
                'SELECT id
                 FROM order_documents
                 WHERE reuploaded_for_document_id = :document_id
                 ORDER BY id DESC
                 LIMIT 1
                 FOR UPDATE',
                ['document_id' => $wrongDocumentId]
            );

            if (is_array($existingReplacement) && (int) ($existingReplacement['id'] ?? 0) > 0) {
                throw new RuntimeException('A corrected file has already been uploaded for this document.');
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
                    document_status,
                    wrong_reason,
                    wrong_marked_by,
                    wrong_marked_at,
                    reuploaded_for_document_id,
                    replaced_by_document_id
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
                    "active",
                    NULL,
                    NULL,
                    NULL,
                    :reuploaded_for_document_id,
                    NULL
                )',
                [
                    'order_id' => $orderId,
                    'requirement_id' => (int) ($locked['requirement_id'] ?? 0) > 0 ? (int) $locked['requirement_id'] : null,
                    'source' => (string) ($locked['source'] ?? 'client'),
                    'label' => trim((string) ($locked['label'] ?? '')) ?: 'Corrected Document',
                    'original_name' => (string) $meta['original_name'],
                    'stored_name' => (string) $meta['stored_name'],
                    'mime_type' => (string) $meta['mime_type'],
                    'size_bytes' => (int) $meta['size_bytes'],
                    'is_client_visible' => (int) ($locked['is_client_visible'] ?? 0),
                    'uploaded_by' => $partnerId,
                    'created_at' => $now,
                    'reuploaded_for_document_id' => $wrongDocumentId,
                ]
            );

            $replacement = $this->db()->fetch(
                'SELECT id
                 FROM order_documents
                 WHERE order_id = :order_id
                   AND stored_name = :stored_name
                   AND reuploaded_for_document_id = :document_id
                 ORDER BY id DESC
                 LIMIT 1',
                [
                    'order_id' => $orderId,
                    'stored_name' => (string) $meta['stored_name'],
                    'document_id' => $wrongDocumentId,
                ]
            );
            $replacementId = (int) ($replacement['id'] ?? 0);

            if ($replacementId <= 0) {
                throw new RuntimeException('The corrected document could not be linked.');
            }

            $this->db()->execute(
                'UPDATE order_documents
                 SET document_status = "reuploaded",
                     replaced_by_document_id = :replacement_id
                 WHERE id = :document_id
                   AND order_id = :order_id
                   AND LOWER(COALESCE(document_status, "active")) = "wrong"',
                [
                    'replacement_id' => $replacementId,
                    'document_id' => $wrongDocumentId,
                    'order_id' => $orderId,
                ]
            );

            $remaining = $this->db()->fetch(
                'SELECT COUNT(*) AS total
                 FROM order_documents
                 WHERE order_id = :order_id
                   AND source IN ("client", "partner")
                   AND LOWER(COALESCE(document_status, "active")) = "wrong"',
                ['order_id' => $orderId]
            );

            $this->db()->execute(
                'UPDATE orders
                 SET status = :status,
                     updated_at = :updated_at
                 WHERE id = :order_id
                   AND partner_id = :partner_id
                 LIMIT 1',
                [
                    'status' => (int) ($remaining['total'] ?? 0) > 0 ? 'clarification' : 'pending_review',
                    'updated_at' => $now,
                    'order_id' => $orderId,
                    'partner_id' => $partnerId,
                ]
            );

            $this->db()->execute('COMMIT');
        } catch (Throwable $e) {
            try {
                $this->db()->execute('ROLLBACK');
            } catch (Throwable $rollbackError) {
                error_log('Partner document correction rollback failed: ' . $rollbackError->getMessage());
            }

            if (!empty($meta['absolute_path']) && is_file((string) $meta['absolute_path'])) {
                @unlink((string) $meta['absolute_path']);
            }

            error_log('Partner document correction failed: ' . $e->getMessage());
            flash('error', $e instanceof RuntimeException ? $e->getMessage() : 'The corrected document could not be saved.');
            redirect('partner/orders/show?id=' . $orderId . '#my-documents');
            return;
        }

        activity_log(
            $partnerId,
            $orderId,
            'partner_document.reuploaded',
            'Partner uploaded a corrected replacement for a rejected document.',
            [
                'wrong_document_id' => $wrongDocumentId,
                'replacement_document_id' => $replacementId,
                'wrong_reason' => (string) ($wrongDocument['wrong_reason'] ?? ''),
                'old_original_name' => (string) ($wrongDocument['old_original_name'] ?? ''),
                'new_original_name' => (string) $meta['original_name'],
            ]
        );

        flash('success', 'Corrected document uploaded successfully.');
        redirect('partner/orders/show?id=' . $orderId . '#my-documents');
    }

    public function uploadDocument(): void
    {
        $this->requirePartner();
        verify_csrf();

        $orderId = (int) input('order_id', 0);
        $label = trim((string) input('label', ''));
        $order = $this->partnerOrder($orderId);

        if (!$order) {
            flash('error', 'Order not found or access denied.');
            redirect('partner/orders');
            return;
        }

        if (!$this->orderCanBeEdited($order)) {
            flash('error', 'This order is no longer accepting owner uploads.');
            redirect('partner/orders/show?id=' . $orderId);
            return;
        }

        if ($label === '' || empty($_FILES['document']) || !is_array($_FILES['document'])) {
            flash('error', 'Please provide a document name and choose a file.');
            redirect('partner/orders/show?id=' . $orderId . '#my-documents');
            return;
        }

        $label = function_exists('mb_substr') ? mb_substr($label, 0, 190) : substr($label, 0, 190);
        $meta = $this->storePartnerOwnerUpload($_FILES['document'], $orderId);

        if (!$meta) {
            flash('error', 'Upload failed. Use PDF, JPG, PNG, WEBP, DOC, DOCX, XLS or XLSX under 10 MB.');
            redirect('partner/orders/show?id=' . $orderId . '#my-documents');
            return;
        }

        try {
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
                    NULL,
                    "client",
                    :label,
                    :original_name,
                    :stored_name,
                    :mime_type,
                    :size_bytes,
                    0,
                    :uploaded_by,
                    :created_at,
                    "active"
                )',
                [
                    'order_id' => $orderId,
                    'label' => $label,
                    'original_name' => (string) $meta['original_name'],
                    'stored_name' => (string) $meta['stored_name'],
                    'mime_type' => (string) $meta['mime_type'],
                    'size_bytes' => (int) $meta['size_bytes'],
                    'uploaded_by' => $this->partnerId(),
                    'created_at' => date('Y-m-d H:i:s'),
                ]
            );
        } catch (Throwable $e) {
            if (!empty($meta['absolute_path']) && is_file((string) $meta['absolute_path'])) {
                @unlink((string) $meta['absolute_path']);
            }

            error_log('Partner individual document upload failed: ' . $e->getMessage());
            flash('error', 'The document could not be saved. Please try again.');
            redirect('partner/orders/show?id=' . $orderId . '#my-documents');
            return;
        }

        $now = date('Y-m-d H:i:s');
        $this->db()->execute(
            'UPDATE orders
             SET updated_at = :updated_at
             WHERE id = :order_id
               AND partner_id = :partner_id
             LIMIT 1',
            [
                'updated_at' => $now,
                'order_id' => $orderId,
                'partner_id' => $this->partnerId(),
            ]
        );

        activity_log(
            $this->partnerId(),
            $orderId,
            'partner_document.uploaded',
            'Partner uploaded an additional document for the order.',
            [
                'label' => $label,
                'original_name' => (string) $meta['original_name'],
            ]
        );

        flash('success', 'Document uploaded successfully.');
        redirect('partner/orders/show?id=' . $orderId . '#my-documents');
    }

    public function resubmit(): void
    {
        $this->requirePartner();
        verify_csrf();

        $orderId = (int) input('order_id', 0);
        $partnerId = $this->partnerId();
        $order = $this->partnerOrder($orderId);

        if (!$order) {
            flash('error', 'Order not found or access denied.');
            redirect('partner/orders');
            return;
        }

        if (!$this->orderCanBeEdited($order)) {
            flash('error', 'This order cannot be resubmitted after processing has started or it is completed.');
            redirect('partner/orders/show?id=' . $orderId);
            return;
        }

        $wrongDocuments = $this->db()->fetch(
            'SELECT COUNT(*) AS total
             FROM order_documents d
             INNER JOIN orders o ON o.id = d.order_id
             WHERE d.order_id = :order_id
               AND o.partner_id = :partner_id
               AND d.source IN ("client", "partner")
               AND LOWER(COALESCE(d.document_status, "active")) = "wrong"',
            [
                'order_id' => $orderId,
                'partner_id' => $partnerId,
            ]
        );

        if ((int) ($wrongDocuments['total'] ?? 0) > 0) {
            flash('error', 'Please re-upload every document marked as wrong before resubmitting this order.');
            redirect('partner/orders/show?id=' . $orderId . '#my-documents');
            return;
        }

        $this->db()->execute(
            'UPDATE orders
             SET status = "pending_review",
                 updated_at = :updated_at
             WHERE id = :order_id
               AND partner_id = :partner_id
             LIMIT 1',
            [
                'updated_at' => date('Y-m-d H:i:s'),
                'order_id' => $orderId,
                'partner_id' => $partnerId,
            ]
        );

        activity_log(
            $partnerId,
            $orderId,
            'partner_order.resubmitted',
            'Partner resubmitted their own order for review.',
            ['status' => 'pending_review']
        );

        flash('success', 'Order resubmitted successfully. Our team will review it shortly.');
        redirect('partner/orders/show?id=' . $orderId);
    }

    public function show(): void
    {
        $this->requirePartner();

        $order = $this->partnerOrder((int) input('id'));
        if (!$order) {
            flash('error', 'Order not found or access denied.');
            redirect('partner/orders');
        }

        $documents = $this->orderDocuments((int) $order['id']);
        $wrongDocs = array_values(array_filter(
            $documents,
            static fn (array $document): bool => strtolower(trim((string) ($document['document_status'] ?? $document['status'] ?? 'active'))) === 'wrong'
        ));

        $this->view('partner/order-show', [
            'title' => 'Partner Order – Tax Saathi',
            'order' => $order,
            'customerDetails' => $this->customerDetailsForPartnerOrder((int) $order['id'], $this->partnerId()),
            'documents' => $documents,
            'wrongDocs' => $wrongDocs,
            'canEditOrder' => $this->orderCanBeEdited($order),
            'canResubmitOrder' => $this->orderCanBeEdited($order) && $wrongDocs === [],
            'isPartnerUser' => true,
            'partnerRoleId' => 4,
        ], 'layouts/partner');
    }

    public function download(): void
    {
        $this->requirePartner();

        $documentId = (int) input('document_id', 0);
        $document = $this->db()->fetch(
            'SELECT
                d.original_name,
                d.stored_name,
                d.mime_type,
                d.size_bytes
             FROM order_documents d
             INNER JOIN orders o ON o.id = d.order_id
             WHERE d.id = :document_id
               AND o.partner_id = :partner_id
               AND d.source IN ("client", "partner")
             LIMIT 1',
            [
                'document_id' => $documentId,
                'partner_id' => $this->partnerId(),
            ]
        );

        if (!$document) {
            http_response_code(404);
            echo 'Document not found.';
            return;
        }

        $storedName = ltrim((string) ($document['stored_name'] ?? ''), '/\\');
        $projectRoot = dirname(__DIR__, 2);
        $storageRoot = realpath($projectRoot . '/storage/uploads');

        if (!$storageRoot || $storedName === '') {
            http_response_code(404);
            echo 'Document file not found.';
            return;
        }

        $path = str_starts_with($storedName, 'storage/uploads/')
            ? $projectRoot . '/' . $storedName
            : $storageRoot . '/' . $storedName;
        $realPath = realpath($path);
        $storagePrefix = rtrim($storageRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        if (!$realPath || !str_starts_with($realPath, $storagePrefix) || !is_file($realPath)) {
            http_response_code(404);
            echo 'Document file not found.';
            return;
        }

        $downloadName = basename((string) ($document['original_name'] ?? basename($realPath)));
        $mimeType = trim((string) ($document['mime_type'] ?? '')) ?: 'application/octet-stream';

        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . (string) filesize($realPath));
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $downloadName) . '"');
        readfile($realPath);
        exit;
    }
}