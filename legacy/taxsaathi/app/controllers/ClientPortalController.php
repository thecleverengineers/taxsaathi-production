<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\NotificationService;
use RuntimeException;
use Throwable;

/**
 * Client portal secured exclusively by user_roles.role_id = 5.
 *
 * Identity model:
 * authenticated users.id -> user_roles.user_id -> role_id 5
 * authenticated users.id -> users.client_id -> clients.id -> orders.client_id
 *
 * No authorization decision is made from users.role_id, a session role value,
 * a role name in the session, or a permission fallback.
 */
final class ClientPortalController extends Controller
{
    private const CLIENT_ROLE_ID = 5;
    private const PARTNER_ROLE_ID = 4;
    private const MAX_UPLOAD_BYTES = 10 * 1024 * 1024;
    private const ALLOWED_UPLOAD_EXTENSIONS = [
        'pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx',
    ];

    private ?Database $database = null;
    private ?array $clientContextCache = null;
    private ?array $portalContextCache = null;

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

    /**
     * Returns the authenticated users row and resolves stale/missing session IDs
     * by matching the authenticated email or phone against users.
     */
    private function authenticatedUser(): ?array
    {
        $auth = [];

        if (function_exists('auth_user')) {
            try {
                $candidate = auth_user();
                if (is_array($candidate)) {
                    $auth = $candidate;
                }
            } catch (Throwable $e) {
                $auth = [];
            }
        }

        if ($auth === [] && is_array($_SESSION['auth_user'] ?? null)) {
            $auth = $_SESSION['auth_user'];
        }

        if ($auth === [] && is_array($_SESSION['user'] ?? null)) {
            $auth = $_SESSION['user'];
        }

        $candidateIds = [
            (int) ($auth['id'] ?? 0),
            (int) ($_SESSION['user_id'] ?? 0),
            (int) ($_SESSION['auth_user_id'] ?? 0),
            (int) ($_SESSION['auth_user']['id'] ?? 0),
            (int) ($_SESSION['user']['id'] ?? 0),
        ];

        foreach (array_values(array_unique(array_filter($candidateIds))) as $candidateId) {
            $row = $this->db()->fetch(
                'SELECT id, client_id, name, email, phone, is_active
                 FROM users
                 WHERE id = :id
                 LIMIT 1',
                ['id' => $candidateId]
            );

            if (is_array($row) && (int) ($row['id'] ?? 0) > 0) {
                return $this->synchronizeAuthenticatedSession($row);
            }
        }

        $email = trim((string) (
            $auth['email']
            ?? $_SESSION['auth_user']['email']
            ?? $_SESSION['user']['email']
            ?? ''
        ));

        if ($email !== '') {
            $row = $this->db()->fetch(
                'SELECT id, client_id, name, email, phone, is_active
                 FROM users
                 WHERE LOWER(email) = LOWER(:email)
                 ORDER BY id DESC
                 LIMIT 1',
                ['email' => $email]
            );

            if (is_array($row) && (int) ($row['id'] ?? 0) > 0) {
                return $this->synchronizeAuthenticatedSession($row);
            }
        }

        $phone = trim((string) (
            $auth['phone']
            ?? $auth['mobile']
            ?? $_SESSION['auth_user']['phone']
            ?? $_SESSION['auth_user']['mobile']
            ?? $_SESSION['user']['phone']
            ?? $_SESSION['user']['mobile']
            ?? ''
        ));

        if ($phone !== '') {
            $normalizedPhone = $this->normalizePhone($phone);
            $row = $this->db()->fetch(
                'SELECT id, client_id, name, email, phone, is_active
                 FROM users
                 WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(phone, ""), "+", ""), " ", ""), "-", ""), "(", ""), ")", "") = :phone
                    OR RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(phone, ""), "+", ""), " ", ""), "-", ""), "(", ""), ")", ""), 10) = RIGHT(:phone_last10, 10)
                 ORDER BY id DESC
                 LIMIT 1',
                [
                    'phone' => $normalizedPhone,
                    'phone_last10' => $normalizedPhone,
                ]
            );

            if (is_array($row) && (int) ($row['id'] ?? 0) > 0) {
                return $this->synchronizeAuthenticatedSession($row);
            }
        }

        return null;
    }

    private function synchronizeAuthenticatedSession(array $user): array
    {
        $userId = (int) ($user['id'] ?? 0);

        if ($userId > 0) {
            $_SESSION['user_id'] = $userId;
            $_SESSION['auth_user_id'] = $userId;

            if (!is_array($_SESSION['auth_user'] ?? null)) {
                $_SESSION['auth_user'] = [];
            }
            if (!is_array($_SESSION['user'] ?? null)) {
                $_SESSION['user'] = [];
            }

            foreach (['id', 'client_id', 'name', 'email', 'phone'] as $key) {
                $_SESSION['auth_user'][$key] = $user[$key] ?? null;
                $_SESSION['user'][$key] = $user[$key] ?? null;
            }
        }

        return $user;
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? '';
    }

    private function userHasClientRole(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $row = $this->db()->fetch(
            'SELECT ur.user_id
             FROM user_roles ur
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE ur.user_id = :user_id
               AND ur.role_id = :role_id
               AND LOWER(COALESCE(r.slug, "")) = "client"
             LIMIT 1',
            [
                'user_id' => $userId,
                'role_id' => self::CLIENT_ROLE_ID,
            ]
        );

        return is_array($row) && (int) ($row['user_id'] ?? 0) === $userId;
    }

    private function userHasRoleId(int $userId, int $roleId): bool
    {
        if ($userId <= 0 || $roleId <= 0) {
            return false;
        }

        $row = $this->db()->fetch(
            'SELECT user_id
             FROM user_roles
             WHERE user_id = :user_id
               AND role_id = :role_id
             LIMIT 1',
            [
                'user_id' => $userId,
                'role_id' => $roleId,
            ]
        );

        return is_array($row) && (int) ($row['user_id'] ?? 0) === $userId;
    }

    /**
     * Shared order-page context.
     *
     * Client ownership is orders.client_id = users.client_id for normal
     * client orders. Partner ownership is orders.partner_id = users.id.
     * A user with both roles can see both of those explicitly owned sets.
     */
    private function requirePortalContext(): array
    {
        if ($this->portalContextCache !== null) {
            return $this->portalContextCache;
        }

        if (function_exists('require_auth')) {
            require_auth();
        }

        $user = $this->authenticatedUser();
        if (!is_array($user) || (int) ($user['id'] ?? 0) <= 0) {
            if (function_exists('flash')) {
                flash('error', 'Please sign in to access your orders.');
            }
            redirect('auth');
            exit;
        }

        $userId = (int) $user['id'];

        if ((int) ($user['is_active'] ?? 1) !== 1) {
            $this->denyClientPortal('This account is inactive. Please contact support.', 403);
        }

        $isClient = $this->userHasRoleId($userId, self::CLIENT_ROLE_ID);
        $isPartner = $this->userHasRoleId($userId, self::PARTNER_ROLE_ID);

        if (!$isClient && !$isPartner) {
            $this->denyClientPortal(
                'This area is available only to accounts assigned the Client or Partner role.',
                403
            );
        }

        $clientId = $isClient ? $this->resolveClientId($user) : 0;

        if ($isClient && !$isPartner && $clientId <= 0) {
            $this->denyClientPortal(
                'Your Client role is active, but this user is not linked to a client profile.',
                409
            );
        }

        $this->portalContextCache = [
            'user_id' => $userId,
            'client_id' => $clientId,
            'partner_id' => $isPartner ? $userId : 0,
            'is_client' => $isClient,
            'is_partner' => $isPartner,
            'portal_role' => $isClient && $isPartner
                ? 'mixed'
                : ($isPartner ? 'partner' : 'client'),
            'user' => $user,
        ];

        return $this->portalContextCache;
    }

    /**
     * Resolves users.client_id to the actual clients.id used by orders.client_id.
     * Missing links are repaired only when an existing clients row matches the
     * authenticated user's email or normalized phone.
     */
    private function resolveClientId(array $user): int
    {
        $userId = (int) ($user['id'] ?? 0);
        $clientId = (int) ($user['client_id'] ?? 0);

        if ($clientId > 0 && $this->clientExists($clientId)) {
            return $clientId;
        }

        $email = trim((string) ($user['email'] ?? ''));
        if ($email !== '') {
            $client = $this->db()->fetch(
                'SELECT id
                 FROM clients
                 WHERE LOWER(email) = LOWER(:email)
                 ORDER BY id DESC
                 LIMIT 1',
                ['email' => $email]
            );

            $clientId = (int) ($client['id'] ?? 0);
            if ($clientId > 0) {
                $this->linkUserToClient($userId, $clientId);
                return $clientId;
            }
        }

        $phone = $this->normalizePhone((string) ($user['phone'] ?? ''));
        if ($phone !== '') {
            $client = $this->db()->fetch(
                'SELECT id
                 FROM clients
                 WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(phone, ""), "+", ""), " ", ""), "-", ""), "(", ""), ")", "") = :phone
                    OR RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(phone, ""), "+", ""), " ", ""), "-", ""), "(", ""), ")", ""), 10) = RIGHT(:phone_last10, 10)
                 ORDER BY id DESC
                 LIMIT 1',
                [
                    'phone' => $phone,
                    'phone_last10' => $phone,
                ]
            );

            $clientId = (int) ($client['id'] ?? 0);
            if ($clientId > 0) {
                $this->linkUserToClient($userId, $clientId);
                return $clientId;
            }
        }

        return 0;
    }

    private function clientExists(int $clientId): bool
    {
        if ($clientId <= 0) {
            return false;
        }

        $row = $this->db()->fetch(
            'SELECT id FROM clients WHERE id = :id LIMIT 1',
            ['id' => $clientId]
        );

        return is_array($row) && (int) ($row['id'] ?? 0) === $clientId;
    }

    private function linkUserToClient(int $userId, int $clientId): void
    {
        if ($userId <= 0 || $clientId <= 0) {
            return;
        }

        $this->db()->execute(
            'UPDATE users
             SET client_id = :client_id,
                 updated_at = :updated_at
             WHERE id = :id',
            [
                'client_id' => $clientId,
                'updated_at' => date('Y-m-d H:i:s'),
                'id' => $userId,
            ]
        );

        $_SESSION['auth_user']['client_id'] = $clientId;
        $_SESSION['user']['client_id'] = $clientId;
    }

    /**
     * @return array{user_id:int,client_id:int,user:array}
     */
    private function requireClientContext(): array
    {
        if ($this->clientContextCache !== null) {
            return $this->clientContextCache;
        }

        if (function_exists('require_auth')) {
            require_auth();
        }

        $user = $this->authenticatedUser();
        if (!is_array($user) || (int) ($user['id'] ?? 0) <= 0) {
            if (function_exists('flash')) {
                flash('error', 'Please sign in to access the client portal.');
            }
            if (function_exists('redirect')) {
                redirect('auth');
            }
            header('Location: ' . (function_exists('base_url') ? base_url('auth') : '/auth'));
            exit;
        }

        $userId = (int) $user['id'];

        if ((int) ($user['is_active'] ?? 1) !== 1) {
            $this->denyClientPortal('This account is inactive. Please contact support.', 403);
        }

        if (!$this->userHasClientRole($userId)) {
            $this->denyClientPortal(
                'This area is available only to accounts assigned the Client role in the user_roles table.',
                403
            );
        }

        $clientId = $this->resolveClientId($user);
        if ($clientId <= 0) {
            $this->denyClientPortal(
                'Your Client role is active, but this user is not linked to a client profile. Link users.client_id to the matching clients.id.',
                409
            );
        }

        $this->clientContextCache = [
            'user_id' => $userId,
            'client_id' => $clientId,
            'user' => $user,
        ];

        return $this->clientContextCache;
    }

    private function denyClientPortal(string $message, int $status = 403): never
    {
        http_response_code($status);
        $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        $dashboardUrl = function_exists('base_url') ? base_url('dashboard') : '/dashboard';
        $safeDashboardUrl = htmlspecialchars($dashboardUrl, ENT_QUOTES, 'UTF-8');
        $title = $status === 409 ? 'Client profile not linked' : 'Client access required';
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');

        echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>' . $status . ' - ' . $safeTitle . '</title>'
            . '<style>body{margin:0;font-family:Inter,Arial,sans-serif;background:#f8fafc;color:#0f172a;display:grid;min-height:100vh;place-items:center;padding:24px;box-sizing:border-box}.card{width:min(560px,100%);background:#fff;border:1px solid #e2e8f0;border-radius:24px;padding:36px;box-shadow:0 22px 60px rgba(15,23,42,.09);text-align:center}.code{font-size:48px;font-weight:900;letter-spacing:-.05em}.title{font-size:22px;font-weight:800;margin:8px 0}.message{color:#64748b;line-height:1.7}.btn{display:inline-flex;margin-top:24px;padding:12px 20px;background:#0f172a;color:#fff;text-decoration:none;border-radius:14px;font-weight:700}</style>'
            . '</head><body><main class="card"><div class="code">' . $status . '</div>'
            . '<div class="title">' . $safeTitle . '</div><p class="message">' . $safeMessage . '</p>'
            . '<a class="btn" href="' . $safeDashboardUrl . '">Back to Dashboard</a></main></body></html>';
        exit;
    }

    private function portalOrders(array $context): array
    {
        $conditions = [];
        $params = [];
        $portalTypeSelect = "'client' AS portal_order_type";

        if (!empty($context['is_partner']) && (int) ($context['partner_id'] ?? 0) > 0) {
            $conditions[] = 'o.partner_id = :portal_partner_id';
            $params['portal_partner_id'] = (int) $context['partner_id'];
            $portalTypeSelect = !empty($context['is_client'])
                ? 'CASE WHEN o.partner_id = :portal_partner_type_id THEN "partner" ELSE "client" END AS portal_order_type'
                : '"partner" AS portal_order_type';

            if (!empty($context['is_client'])) {
                $params['portal_partner_type_id'] = (int) $context['partner_id'];
            }
        }

        if (!empty($context['is_client']) && (int) ($context['client_id'] ?? 0) > 0) {
            $conditions[] = '(o.client_id = :portal_client_id AND (o.partner_id IS NULL OR o.partner_id = 0))';
            $params['portal_client_id'] = (int) $context['client_id'];
        }

        if ($conditions === []) {
            return [];
        }

        return $this->db()->fetchAll(
            'SELECT
                o.id,
                o.order_no,
                o.partner_id,
                o.client_id,
                o.service_id,
                o.financial_year,
                o.fee_amount,
                COALESCE(o.gross_fee_amount, o.fee_amount) AS gross_fee_amount,
                COALESCE(o.payable_amount, o.fee_amount) AS payable_amount,
                COALESCE(o.coupon_code, "") AS coupon_code,
                o.status,
                o.status AS order_status,
                o.payment_method,
                o.payment_status,
                o.payment_reference,
                o.created_at,
                o.updated_at,
                COALESCE(s.title, "Service") AS service_title,
                COALESCE(i.invoice_no, "") AS invoice_no,
                COALESCE(i.total_amount, o.fee_amount) AS invoice_total,
                COALESCE(i.paid_amount, 0) AS invoice_paid,
                COALESCE(i.status, "") AS invoice_status,
                ' . $portalTypeSelect . '
             FROM orders o
             LEFT JOIN services s ON s.id = o.service_id
             LEFT JOIN invoices i ON i.order_id = o.id
             WHERE (' . implode(' OR ', $conditions) . ')
             ORDER BY COALESCE(o.created_at, o.updated_at) DESC, o.id DESC',
            $params
        ) ?: [];
    }

    private function portalOrder(int $orderId, array $context): ?array
    {
        if ($orderId <= 0) {
            return null;
        }

        $conditions = [];
        $params = ['order_id' => $orderId];

        if (!empty($context['is_partner']) && (int) ($context['partner_id'] ?? 0) > 0) {
            $conditions[] = 'o.partner_id = :portal_partner_id';
            $params['portal_partner_id'] = (int) $context['partner_id'];
        }

        if (!empty($context['is_client']) && (int) ($context['client_id'] ?? 0) > 0) {
            $conditions[] = '(o.client_id = :portal_client_id AND (o.partner_id IS NULL OR o.partner_id = 0))';
            $params['portal_client_id'] = (int) $context['client_id'];
        }

        if ($conditions === []) {
            return null;
        }

        $row = $this->db()->fetch(
            'SELECT
                o.*,
                COALESCE(o.gross_fee_amount, o.fee_amount) AS gross_fee_amount,
                COALESCE(o.payable_amount, o.fee_amount) AS payable_amount,
                COALESCE(o.coupon_code, "") AS coupon_code,
                COALESCE(s.title, "Service") AS service_title,
                COALESCE(s.slug, "") AS service_slug,
                COALESCE(c.name, "Client") AS client_name,
                COALESCE(c.company_name, "") AS client_company,
                COALESCE(c.email, "") AS client_email,
                COALESCE(c.phone, "") AS client_phone,
                COALESCE(c.gst_number, "") AS client_gst_number,
                COALESCE(c.pan_number, "") AS client_pan_number,
                COALESCE(c.city, "") AS client_city
             FROM orders o
             LEFT JOIN services s ON s.id = o.service_id
             LEFT JOIN clients c ON c.id = o.client_id
             WHERE o.id = :order_id
               AND (' . implode(' OR ', $conditions) . ')
             LIMIT 1',
            $params
        );

        return is_array($row) && $row !== [] ? $row : null;
    }

    private function partnerPortalCustomerDetails(int $orderId, int $partnerId): array
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

    private function partnerPortalDocuments(int $orderId, int $partnerId): array
    {
        return $this->db()->fetchAll(
            'SELECT
                d.id,
                d.order_id,
                d.requirement_id,
                d.source,
                d.label,
                d.original_name,
                d.stored_name,
                d.mime_type,
                d.size_bytes,
                COALESCE(d.document_status, "active") AS document_status,
                d.wrong_reason,
                d.wrong_marked_at,
                d.created_at
             FROM order_documents d
             INNER JOIN orders o ON o.id = d.order_id
             WHERE d.order_id = :order_id
               AND o.partner_id = :partner_id
               AND d.source IN ("client", "partner")
             ORDER BY d.id ASC',
            [
                'order_id' => $orderId,
                'partner_id' => $partnerId,
            ]
        ) ?: [];
    }

    private function partnerPortalDeliveredDocuments(int $orderId, int $partnerId): array
    {
        return $this->db()->fetchAll(
            'SELECT
                d.id,
                d.order_id,
                d.source,
                d.original_name,
                d.stored_name,
                d.mime_type,
                d.size_bytes,
                d.created_at
             FROM order_documents d
             INNER JOIN orders o ON o.id = d.order_id
             WHERE d.order_id = :order_id
               AND o.partner_id = :partner_id
               AND d.is_client_visible = 1
               AND d.source NOT IN ("client", "partner", "payment_proof")
               AND COALESCE(d.document_status, "active") <> "wrong"
             ORDER BY d.id DESC',
            [
                'order_id' => $orderId,
                'partner_id' => $partnerId,
            ]
        ) ?: [];
    }

    private function partnerPortalPaymentProofs(int $orderId, int $partnerId): array
    {
        return $this->db()->fetchAll(
            'SELECT
                d.id,
                d.order_id,
                d.original_name,
                d.stored_name,
                d.mime_type,
                d.size_bytes,
                d.created_at
             FROM order_documents d
             INNER JOIN orders o ON o.id = d.order_id
             WHERE d.order_id = :order_id
               AND o.partner_id = :partner_id
               AND d.source = "payment_proof"
             ORDER BY d.id DESC',
            [
                'order_id' => $orderId,
                'partner_id' => $partnerId,
            ]
        ) ?: [];
    }

    private function partnerPortalInvoice(int $orderId, int $partnerId): ?array
    {
        $row = $this->db()->fetch(
            'SELECT i.*
             FROM invoices i
             INNER JOIN orders o ON o.id = i.order_id
             WHERE i.order_id = :order_id
               AND o.partner_id = :partner_id
             ORDER BY i.id DESC
             LIMIT 1',
            [
                'order_id' => $orderId,
                'partner_id' => $partnerId,
            ]
        );

        return is_array($row) && $row !== [] ? $row : null;
    }

    private function partnerPortalPayments(int $orderId, int $partnerId): array
    {
        return $this->db()->fetchAll(
            'SELECT
                p.id,
                p.invoice_id,
                p.order_id,
                p.amount,
                p.method,
                p.reference_no,
                p.reference_no AS reference,
                p.notes,
                p.received_at,
                p.created_at,
                "verified" AS status
             FROM payments p
             INNER JOIN orders o ON o.id = p.order_id
             WHERE p.order_id = :order_id
               AND o.partner_id = :partner_id
             ORDER BY COALESCE(p.received_at, DATE(p.created_at)) DESC, p.id DESC',
            [
                'order_id' => $orderId,
                'partner_id' => $partnerId,
            ]
        ) ?: [];
    }

    private function partnerPortalActivity(int $orderId, int $partnerId): array
    {
        return $this->db()->fetchAll(
            'SELECT
                a.id,
                a.user_id,
                a.order_id,
                a.action,
                a.description,
                a.description AS message,
                a.meta_json,
                a.created_at
             FROM activity_logs a
             INNER JOIN orders o ON o.id = a.order_id
             WHERE a.order_id = :order_id
               AND o.partner_id = :partner_id
             ORDER BY a.id DESC
             LIMIT 200',
            [
                'order_id' => $orderId,
                'partner_id' => $partnerId,
            ]
        ) ?: [];
    }

    private function partnerOwnedOrderRequest(int $orderId): bool
    {
        if ($orderId <= 0) {
            return false;
        }

        $context = $this->requirePortalContext();
        if (empty($context['is_partner']) || (int) ($context['partner_id'] ?? 0) <= 0) {
            return false;
        }

        $row = $this->db()->fetch(
            'SELECT id
             FROM orders
             WHERE id = :order_id
               AND partner_id = :partner_id
             LIMIT 1',
            [
                'order_id' => $orderId,
                'partner_id' => (int) $context['partner_id'],
            ]
        );

        return is_array($row) && (int) ($row['id'] ?? 0) === $orderId;
    }

    private function partnerOwnedDocumentRequest(int $documentId): bool
    {
        if ($documentId <= 0) {
            return false;
        }

        $context = $this->requirePortalContext();
        if (empty($context['is_partner']) || (int) ($context['partner_id'] ?? 0) <= 0) {
            return false;
        }

        $row = $this->db()->fetch(
            'SELECT d.id
             FROM order_documents d
             INNER JOIN orders o ON o.id = d.order_id
             WHERE d.id = :document_id
               AND o.partner_id = :partner_id
             LIMIT 1',
            [
                'document_id' => $documentId,
                'partner_id' => (int) $context['partner_id'],
            ]
        );

        return is_array($row) && (int) ($row['id'] ?? 0) === $documentId;
    }

    private function clientOrders(int $clientId): array
    {
        return $this->db()->fetchAll(
            'SELECT
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
                o.created_at,
                o.updated_at,
                COALESCE(s.title, "Service") AS service_title,
                COALESCE(i.invoice_no, "") AS invoice_no,
                COALESCE(i.total_amount, o.fee_amount) AS invoice_total,
                COALESCE(i.paid_amount, 0) AS invoice_paid,
                COALESCE(i.status, "") AS invoice_status
             FROM orders o
             LEFT JOIN services s ON s.id = o.service_id
             LEFT JOIN invoices i ON i.order_id = o.id
             WHERE o.client_id = :client_id
               AND (o.partner_id IS NULL OR o.partner_id = 0)
             ORDER BY COALESCE(o.created_at, o.updated_at) DESC, o.id DESC',
            ['client_id' => $clientId]
        ) ?: [];
    }

    private function ownedOrder(int $orderId, int $clientId): ?array
    {
        if ($orderId <= 0 || $clientId <= 0) {
            return null;
        }

        $row = $this->db()->fetch(
            'SELECT
                o.*,
                COALESCE(s.title, "Service") AS service_title,
                COALESCE(s.slug, "") AS service_slug,
                COALESCE(c.name, "Client") AS client_name,
                COALESCE(c.company_name, "") AS client_company,
                COALESCE(c.email, "") AS client_email,
                COALESCE(c.phone, "") AS client_phone,
                COALESCE(c.gst_number, "") AS client_gst_number,
                COALESCE(c.pan_number, "") AS client_pan_number,
                COALESCE(c.city, "") AS client_city
             FROM orders o
             LEFT JOIN services s ON s.id = o.service_id
             INNER JOIN clients c ON c.id = o.client_id
             WHERE o.id = :order_id
               AND o.client_id = :client_id
               AND (o.partner_id IS NULL OR o.partner_id = 0)
             LIMIT 1',
            [
                'order_id' => $orderId,
                'client_id' => $clientId,
            ]
        );

        return is_array($row) && $row !== [] ? $row : null;
    }

    private function customerDetailsForOwnedOrder(int $orderId, int $clientId): array
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
               AND o.client_id = :client_id
               AND (o.partner_id IS NULL OR o.partner_id = 0)
             LIMIT 1',
            [
                'order_id' => $orderId,
                'client_id' => $clientId,
            ]
        );

        return is_array($row) ? $row : [];
    }

    private function orderCanBeEdited(array $order): bool
    {
        $status = strtolower(trim((string) ($order['status'] ?? '')));

        return !in_array($status, [
            'approved',
            'work_in_progress',
            'in_progress',
            'completed',
            'cancelled',
        ], true);
    }

    private function saveClientOrderCustomerDetails(
        array $context,
        array $order,
        string $nameAsPerPan,
        string $panNumber,
        string $mobile,
        string $email,
        string $now
    ): void {
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
                    'client_id' => (int) ($context['client_id'] ?? 0),
                    'user_id' => (int) ($context['user_id'] ?? 0),
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

        $user = is_array($context['user'] ?? null) ? $context['user'] : [];
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
                "client_order_edit",
                :created_at,
                :updated_at
            )',
            [
                'order_id' => (int) ($order['id'] ?? 0),
                'client_id' => (int) ($context['client_id'] ?? 0),
                'user_id' => (int) ($context['user_id'] ?? 0),
                'service_id' => (int) ($order['service_id'] ?? 0),
                'name_as_per_pan' => $nameAsPerPan,
                'pan_number' => $panNumber,
                'mobile' => $mobile,
                'email' => $email,
                'submitted_by_user_id' => (int) ($context['user_id'] ?? 0),
                'submitted_by_name' => (string) ($user['name'] ?? ''),
                'submitted_by_email' => (string) ($user['email'] ?? ''),
                'submitted_by_phone' => (string) ($user['phone'] ?? ''),
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    private function documentsForOwnedOrder(int $orderId, int $clientId, string $source): array
    {
        return $this->db()->fetchAll(
            'SELECT
                d.id,
                d.order_id,
                d.requirement_id,
                d.source,
                d.label,
                d.original_name,
                d.stored_name,
                d.mime_type,
                d.size_bytes,
                d.is_client_visible,
                d.uploaded_by,
                d.created_at,
                COALESCE(d.document_status, "active") AS document_status,
                d.wrong_reason,
                d.wrong_marked_by,
                d.wrong_marked_at,
                d.reuploaded_for_document_id,
                d.replaced_by_document_id
             FROM order_documents d
             INNER JOIN orders o ON o.id = d.order_id
             WHERE d.order_id = :order_id
               AND o.client_id = :client_id
               AND (o.partner_id IS NULL OR o.partner_id = 0)
               AND d.source = :source
             ORDER BY
                CASE COALESCE(d.document_status, "active")
                    WHEN "wrong" THEN 1
                    WHEN "active" THEN 2
                    WHEN "reuploaded" THEN 3
                    ELSE 4
                END,
                d.id DESC',
            [
                'order_id' => $orderId,
                'client_id' => $clientId,
                'source' => $source,
            ]
        ) ?: [];
    }

    private function deliveredDocumentsForOwnedOrder(int $orderId, int $clientId): array
    {
        return $this->db()->fetchAll(
            'SELECT
                d.id,
                d.order_id,
                d.source,
                d.label,
                d.original_name,
                d.stored_name,
                d.mime_type,
                d.size_bytes,
                d.created_at
             FROM order_documents d
             INNER JOIN orders o ON o.id = d.order_id
             WHERE d.order_id = :order_id
               AND o.client_id = :client_id
               AND (o.partner_id IS NULL OR o.partner_id = 0)
               AND d.is_client_visible = 1
               AND d.source NOT IN ("client", "payment_proof")
               AND COALESCE(d.document_status, "active") <> "wrong"
             ORDER BY d.id DESC',
            [
                'order_id' => $orderId,
                'client_id' => $clientId,
            ]
        ) ?: [];
    }

    private function wrongDocumentsForOwnedOrder(int $orderId, int $clientId): array
    {
        return $this->db()->fetchAll(
            'SELECT
                d.id,
                d.order_id,
                d.requirement_id,
                d.label,
                d.original_name,
                d.stored_name,
                d.mime_type,
                d.size_bytes,
                d.created_at,
                d.wrong_reason,
                d.wrong_marked_at,
                COALESCE(d.document_status, "active") AS document_status
             FROM order_documents d
             INNER JOIN orders o ON o.id = d.order_id
             WHERE d.order_id = :order_id
               AND o.client_id = :client_id
               AND (o.partner_id IS NULL OR o.partner_id = 0)
               AND d.source = "client"
               AND COALESCE(d.document_status, "active") = "wrong"
             ORDER BY d.wrong_marked_at DESC, d.id DESC',
            [
                'order_id' => $orderId,
                'client_id' => $clientId,
            ]
        ) ?: [];
    }

    private function invoiceForOwnedOrder(int $orderId, int $clientId): ?array
    {
        $row = $this->db()->fetch(
            'SELECT i.*
             FROM invoices i
             INNER JOIN orders o ON o.id = i.order_id
             WHERE i.order_id = :order_id
               AND o.client_id = :client_id
               AND (o.partner_id IS NULL OR o.partner_id = 0)
             ORDER BY i.id DESC
             LIMIT 1',
            [
                'order_id' => $orderId,
                'client_id' => $clientId,
            ]
        );

        return is_array($row) && $row !== [] ? $row : null;
    }

    private function paymentsForOwnedOrder(int $orderId, int $clientId): array
    {
        return $this->db()->fetchAll(
            'SELECT
                p.id,
                p.invoice_id,
                p.order_id,
                p.amount,
                p.method,
                p.reference_no,
                p.reference_no AS reference,
                p.notes,
                p.received_at,
                p.created_at,
                "verified" AS status
             FROM payments p
             INNER JOIN orders o ON o.id = p.order_id
             WHERE p.order_id = :order_id
               AND o.client_id = :client_id
               AND (o.partner_id IS NULL OR o.partner_id = 0)
             ORDER BY COALESCE(p.received_at, DATE(p.created_at)) DESC, p.id DESC',
            [
                'order_id' => $orderId,
                'client_id' => $clientId,
            ]
        ) ?: [];
    }

    private function activityForOwnedOrder(int $orderId, int $clientId): array
    {
        return $this->db()->fetchAll(
            'SELECT
                a.id,
                a.user_id,
                a.order_id,
                a.action,
                a.description,
                a.description AS message,
                a.meta_json,
                a.created_at
             FROM activity_logs a
             INNER JOIN orders o ON o.id = a.order_id
             WHERE a.order_id = :order_id
               AND o.client_id = :client_id
               AND (o.partner_id IS NULL OR o.partner_id = 0)
             ORDER BY a.id DESC
             LIMIT 200',
            [
                'order_id' => $orderId,
                'client_id' => $clientId,
            ]
        ) ?: [];
    }

    private function clientOrderViewUrl(int $orderId): string
    {
        return 'client/orders/view?id=' . $orderId;
    }

    /**
     * Accept both the current form field (order_id) and the legacy field
     * (id). Ownership is still verified by ownedOrder() afterwards.
     */
    private function requestOrderId(): int
    {
        $orderId = (int) input('order_id', 0);

        return $orderId > 0 ? $orderId : (int) input('id', 0);
    }

    /**
     * Legacy aliases. The canonical order pages now live under /client for
     * both clients and partners.
     */
    public function legacyPartnerOrders(): void
    {
        redirect('client/orders');
        exit;
    }

    public function legacyPartnerOrderShow(): void
    {
        $orderId = $this->requestOrderId();
        redirect($orderId > 0 ? 'client/orders/view?id=' . $orderId : 'client/orders');
        exit;
    }

    public function index(): void
    {
        $context = $this->requirePortalContext();

        $this->view('public/myapplication', [
            'title' => 'My Applications – Tax Saathi',
            'heading' => 'My Applications',
            'description' => $context['portal_role'] === 'partner'
                ? 'Orders submitted through your partner profile are shown here.'
                : ($context['portal_role'] === 'mixed'
                    ? 'Orders linked to your client profile and submitted through your partner profile are shown here.'
                    : 'Only applications linked to your client profile are shown here.'),
            'rows' => $this->portalOrders($context),
            'portalRole' => $context['portal_role'],
            'isPartnerUser' => !empty($context['is_partner']),
            'ordersHomeUrl' => 'client/orders',
            'viewPath' => 'client/orders/view',
            'newOrderUrl' => !empty($context['is_partner']) && empty($context['is_client'])
                ? 'partner/services'
                : 'services',
            'clientRoleVerified' => !empty($context['is_client']),
            'partnerRoleVerified' => !empty($context['is_partner']),
        ], 'layouts/dashboard');
    }

    public function show(): void
    {
        $context = $this->requirePortalContext();
        $orderId = $this->requestOrderId();
        $order = $this->portalOrder($orderId, $context);

        if (!$order) {
            if (function_exists('flash')) {
                flash('error', 'Order not found or you do not have access to it.');
            }
            redirect('client/orders');
            exit;
        }

        $isPartnerOrder = (int) ($order['partner_id'] ?? 0) > 0
            && (int) ($order['partner_id'] ?? 0) === (int) ($context['partner_id'] ?? 0);

        if ($isPartnerOrder) {
            $partnerDocuments = $this->partnerPortalDocuments($orderId, (int) $context['partner_id']);
            $wrongDocs = array_values(array_filter(
                $partnerDocuments,
                static fn (array $document): bool => strtolower(trim((string) ($document['document_status'] ?? 'active'))) === 'wrong'
            ));

            $this->view('client/order-show', [
                'title' => 'Order ' . ($order['order_no'] ?? '') . ' – Tax Saathi',
                'order' => $order,
                'customerDetails' => $this->partnerPortalCustomerDetails($orderId, (int) $context['partner_id']),
                'submittedDocs' => $partnerDocuments,
                'paymentProofs' => $this->partnerPortalPaymentProofs($orderId, (int) $context['partner_id']),
                'deliveredDocs' => $this->partnerPortalDeliveredDocuments($orderId, (int) $context['partner_id']),
                'invoice' => $this->partnerPortalInvoice($orderId, (int) $context['partner_id']),
                'payments' => $this->partnerPortalPayments($orderId, (int) $context['partner_id']),
                'activity' => $this->partnerPortalActivity($orderId, (int) $context['partner_id']),
                'wrongDocs' => $wrongDocs,
                'canEditOrder' => $this->orderCanBeEdited($order),
                'canResubmitOrder' => $this->orderCanBeEdited($order) && $wrongDocs === [],
                'portalRole' => 'partner',
                'isPartnerOrder' => true,
                'clientRoleVerified' => !empty($context['is_client']),
                'partnerRoleVerified' => true,
            ], 'layouts/dashboard');
            return;
        }

        // Fetch every client document for this owned order directly from order_documents.
        // The view derives correction cards from document_status = wrong, so it never
        // depends on a second query being passed correctly.
        $submittedDocs = $this->documentsForOwnedOrder($orderId, $context['client_id'], 'client');
        $wrongDocs = array_values(array_filter(
            $submittedDocs,
            static fn (array $document): bool => strtolower(trim((string) ($document['document_status'] ?? 'active'))) === 'wrong'
        ));

        $this->view('client/order-show', [
            'title' => 'Order ' . ($order['order_no'] ?? '') . ' – Tax Saathi',
            'order' => $order,
            'customerDetails' => $this->customerDetailsForOwnedOrder($orderId, $context['client_id']),
            'submittedDocs' => $submittedDocs,
            'paymentProofs' => $this->documentsForOwnedOrder($orderId, $context['client_id'], 'payment_proof'),
            'deliveredDocs' => $this->deliveredDocumentsForOwnedOrder($orderId, $context['client_id']),
            'invoice' => $this->invoiceForOwnedOrder($orderId, $context['client_id']),
            'payments' => $this->paymentsForOwnedOrder($orderId, $context['client_id']),
            'activity' => $this->activityForOwnedOrder($orderId, $context['client_id']),
            'wrongDocs' => $wrongDocs,
            'canEditOrder' => $this->orderCanBeEdited($order),
            'canResubmitOrder' => $this->orderCanBeEdited($order) && $wrongDocs === [],
            'portalRole' => 'client',
            'isPartnerOrder' => false,
            'clientRoleVerified' => true,
            'partnerRoleVerified' => !empty($context['is_partner']),
        ], 'layouts/dashboard');
    }

    public function update(): void
    {
        $requestedOrderId = $this->requestOrderId();
        if ($this->partnerOwnedOrderRequest($requestedOrderId)) {
            (new PartnerOrderController())->update();
            return;
        }

        $context = $this->requireClientContext();
        verify_csrf();

        $orderId = $requestedOrderId;
        $order = $this->ownedOrder($orderId, $context['client_id']);

        if (!$order) {
            flash('error', 'Order not found or you do not have access to it.');
            redirect('client/orders');
            exit;
        }

        if (!$this->orderCanBeEdited($order)) {
            flash('error', 'This order can no longer be edited because processing has started or it is already completed.');
            redirect($this->clientOrderViewUrl($orderId));
            exit;
        }

        $nameAsPerPan = trim((string) input('name_as_per_pan', ''));
        $panNumber = strtoupper(trim((string) input('pan_number', '')));
        $mobile = trim((string) input('mobile', ''));
        $email = trim((string) input('email', ''));
        $financialYear = trim((string) input('financial_year', ''));
        $notes = trim((string) input('notes', ''));

        if ($nameAsPerPan === '') {
            flash('error', 'Please enter the name as per PAN.');
            redirect($this->clientOrderViewUrl($orderId) . '#edit-order');
            exit;
        }

        if ($panNumber === '' || !preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/', $panNumber)) {
            flash('error', 'Please enter a valid PAN number.');
            redirect($this->clientOrderViewUrl($orderId) . '#edit-order');
            exit;
        }

        if ($mobile === '') {
            flash('error', 'Please enter a mobile number.');
            redirect($this->clientOrderViewUrl($orderId) . '#edit-order');
            exit;
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Please enter a valid email address.');
            redirect($this->clientOrderViewUrl($orderId) . '#edit-order');
            exit;
        }

        if (function_exists('mb_substr')) {
            $nameAsPerPan = mb_substr($nameAsPerPan, 0, 190);
            $panNumber = mb_substr($panNumber, 0, 20);
            $mobile = mb_substr($mobile, 0, 30);
            $email = mb_substr($email, 0, 190);
            $financialYear = mb_substr($financialYear, 0, 255);
            $notes = mb_substr($notes, 0, 5000);
        } else {
            $nameAsPerPan = substr($nameAsPerPan, 0, 190);
            $panNumber = substr($panNumber, 0, 20);
            $mobile = substr($mobile, 0, 30);
            $email = substr($email, 0, 190);
            $financialYear = substr($financialYear, 0, 255);
            $notes = substr($notes, 0, 5000);
        }

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
                   AND client_id = :client_id
                   AND (partner_id IS NULL OR partner_id = 0)
                 LIMIT 1',
                [
                    'financial_year' => $financialYearValue,
                    'notes' => $notes !== '' ? $notes : null,
                    'updated_at' => $now,
                    'order_id' => $orderId,
                    'client_id' => $context['client_id'],
                ]
            );

            $this->saveClientOrderCustomerDetails(
                $context,
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
                error_log('Client order edit rollback failed: ' . $rollbackError->getMessage());
            }

            error_log('Client order edit failed: ' . $e->getMessage());
            flash('error', 'The order changes could not be saved. Please try again.');
            redirect($this->clientOrderViewUrl($orderId) . '#edit-order');
            exit;
        }

        if (function_exists('activity_log')) {
            activity_log(
                $context['user_id'],
                $orderId,
                'client_order.updated_by_owner',
                'Client updated their own order details before resubmission.',
                [
                    'financial_year' => $financialYearValue,
                    'customer_name_updated' => true,
                ]
            );
        }

        flash('success', 'Order details updated successfully. Review the information and resubmit the order.');
        redirect($this->clientOrderViewUrl($orderId) . '#edit-order');
        exit;
    }

    public function resubmit(): void
    {
        $requestedOrderId = $this->requestOrderId();
        if ($this->partnerOwnedOrderRequest($requestedOrderId)) {
            (new PartnerOrderController())->resubmit();
            return;
        }

        $context = $this->requireClientContext();
        verify_csrf();

        $orderId = $requestedOrderId;
        $order = $this->ownedOrder($orderId, $context['client_id']);

        if (!$order) {
            flash('error', 'Order not found or you do not have access to it.');
            redirect('client/orders');
            exit;
        }

        if (!$this->orderCanBeEdited($order)) {
            flash('error', 'This order cannot be resubmitted after processing has started or it is completed.');
            redirect($this->clientOrderViewUrl($orderId));
            exit;
        }

        $wrongDocuments = $this->db()->fetch(
            'SELECT COUNT(*) AS total
             FROM order_documents d
             INNER JOIN orders o ON o.id = d.order_id
             WHERE d.order_id = :order_id
               AND o.client_id = :client_id
               AND (o.partner_id IS NULL OR o.partner_id = 0)
               AND d.source = "client"
               AND LOWER(COALESCE(d.document_status, "active")) = "wrong"',
            [
                'order_id' => $orderId,
                'client_id' => $context['client_id'],
            ]
        );

        if ((int) ($wrongDocuments['total'] ?? 0) > 0) {
            flash('error', 'Please re-upload every document marked as wrong before resubmitting this order.');
            redirect($this->clientOrderViewUrl($orderId) . '#my-documents');
            exit;
        }

        $now = date('Y-m-d H:i:s');
        $this->db()->execute(
            'UPDATE orders
             SET status = "pending_review",
                 updated_at = :updated_at
             WHERE id = :order_id
               AND client_id = :client_id
               AND (partner_id IS NULL OR partner_id = 0)
             LIMIT 1',
            [
                'updated_at' => $now,
                'order_id' => $orderId,
                'client_id' => $context['client_id'],
            ]
        );

        if (function_exists('activity_log')) {
            activity_log(
                $context['user_id'],
                $orderId,
                'client_order.resubmitted',
                'Client resubmitted their own order for review.',
                ['status' => 'pending_review']
            );
        }

        $this->safeTriggerNotification('client_order_resubmitted', [
            'order_id' => $orderId,
            'order_no' => (string) ($order['order_no'] ?? ''),
        ]);

        flash('success', 'Order resubmitted successfully. Our team will review it shortly.');
        redirect($this->clientOrderViewUrl($orderId));
        exit;
    }

    public function reuploadDocument(): void
    {
        $requestedDocumentId = (int) input('document_id', 0);
        if ($this->partnerOwnedDocumentRequest($requestedDocumentId)) {
            (new PartnerOrderController())->reuploadDocument();
            return;
        }

        $context = $this->requireClientContext();
        verify_csrf();

        $wrongDocumentId = $requestedDocumentId;
        if ($wrongDocumentId <= 0) {
            flash('error', 'Invalid document replacement request.');
            redirect('client/orders');
            exit;
        }

        if (empty($_FILES['document']) || !is_array($_FILES['document'])) {
            flash('error', 'Please choose the corrected document.');
            redirect('client/orders');
            exit;
        }

        $meta = $this->storeClientCorrectionUpload($_FILES['document']);
        if (!$meta) {
            flash('error', 'Upload failed. Use PDF, JPG, PNG, WEBP, DOC, DOCX, XLS or XLSX under 10 MB.');
            redirect('client/orders');
            exit;
        }

        $wrongDocument = null;
        $replacementId = 0;
        $orderId = 0;
        $now = date('Y-m-d H:i:s');

        try {
            $this->db()->execute('START TRANSACTION');

            // Lock and validate the exact rejected document. Ownership is enforced
            // through orders.client_id and only source=client rows can be replaced.
            $wrongDocument = $this->db()->fetch(
                'SELECT
                    d.id,
                    d.order_id,
                    d.requirement_id,
                    d.label,
                    d.source,
                    d.original_name AS old_original_name,
                    d.stored_name AS old_stored_name,
                    d.is_client_visible,
                    COALESCE(d.document_status, "active") AS document_status,
                    d.wrong_reason,
                    d.replaced_by_document_id,
                    o.order_no,
                    o.status AS order_status
                 FROM order_documents d
                 INNER JOIN orders o ON o.id = d.order_id
                 WHERE d.id = :document_id
                   AND o.client_id = :client_id
                   AND (o.partner_id IS NULL OR o.partner_id = 0)
                   AND d.source = "client"
                   AND LOWER(COALESCE(d.document_status, "active")) = "wrong"
                 LIMIT 1
                 FOR UPDATE',
                [
                    'document_id' => $wrongDocumentId,
                    'client_id' => $context['client_id'],
                ]
            );

            if (!is_array($wrongDocument) || $wrongDocument === []) {
                throw new RuntimeException('This document is no longer available for replacement.');
            }

            $orderId = (int) ($wrongDocument['order_id'] ?? 0);

            if (!$this->orderCanBeEdited(['status' => (string) ($wrongDocument['order_status'] ?? '')])) {
                throw new RuntimeException('This order is no longer accepting document replacements.');
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

            // Keep the rejected row for audit history and insert the corrected file
            // as a new active row linked to that exact rejected document.
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
                    "client",
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
                    'requirement_id' => (int) ($wrongDocument['requirement_id'] ?? 0) > 0
                        ? (int) $wrongDocument['requirement_id']
                        : null,
                    'label' => trim((string) ($wrongDocument['label'] ?? '')) ?: 'Corrected Document',
                    'original_name' => (string) $meta['original_name'],
                    'stored_name' => (string) $meta['stored_name'],
                    'mime_type' => (string) $meta['mime_type'],
                    'size_bytes' => (int) $meta['size_bytes'],
                    'is_client_visible' => (int) ($wrongDocument['is_client_visible'] ?? 0),
                    'uploaded_by' => $context['user_id'],
                    'created_at' => $now,
                    'reuploaded_for_document_id' => $wrongDocumentId,
                ]
            );

            $replacement = $this->db()->fetch(
                'SELECT id
                 FROM order_documents
                 WHERE order_id = :order_id
                   AND source = "client"
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
                   AND source = "client"
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
                   AND source = "client"
                   AND LOWER(COALESCE(document_status, "active")) = "wrong"',
                ['order_id' => $orderId]
            );

            $remainingWrong = (int) ($remaining['total'] ?? 0);
            $nextStatus = $remainingWrong > 0 ? 'clarification' : 'pending_review';

            $this->db()->execute(
                'UPDATE orders
                 SET status = :status,
                     updated_at = :updated_at
                 WHERE id = :order_id
                   AND client_id = :client_id
                   AND (partner_id IS NULL OR partner_id = 0)',
                [
                    'status' => $nextStatus,
                    'updated_at' => $now,
                    'order_id' => $orderId,
                    'client_id' => $context['client_id'],
                ]
            );

            $this->db()->execute('COMMIT');
        } catch (Throwable $e) {
            try {
                $this->db()->execute('ROLLBACK');
            } catch (Throwable $rollbackError) {
                error_log('Document correction rollback failed: ' . $rollbackError->getMessage());
            }

            $newPath = $this->resolveUploadPath((string) ($meta['stored_name'] ?? ''));
            if (is_file($newPath)) {
                @unlink($newPath);
            }

            error_log('Client document correction failed: ' . $e->getMessage());
            flash('error', $e instanceof RuntimeException
                ? $e->getMessage()
                : 'The corrected document could not be saved. Please try again.');

            redirect($orderId > 0 ? $this->clientOrderViewUrl($orderId) : 'client/orders');
            exit;
        }

        if (function_exists('activity_log')) {
            activity_log(
                $context['user_id'],
                $orderId,
                'client_document.reuploaded',
                'Client uploaded a corrected replacement for a rejected document.',
                [
                    'wrong_document_id' => $wrongDocumentId,
                    'replacement_document_id' => $replacementId,
                    'wrong_reason' => (string) ($wrongDocument['wrong_reason'] ?? ''),
                    'old_original_name' => (string) ($wrongDocument['old_original_name'] ?? ''),
                    'new_original_name' => (string) $meta['original_name'],
                ]
            );
        }

        $this->safeTriggerNotification('client_document_reuploaded', [
            'order_id' => $orderId,
            'order_no' => (string) ($wrongDocument['order_no'] ?? ''),
            'wrong_document_id' => $wrongDocumentId,
            'replacement_document_id' => $replacementId,
            'new_original_name' => (string) $meta['original_name'],
        ]);

        flash('success', 'Corrected document uploaded successfully.');
        redirect($this->clientOrderViewUrl($orderId));
        exit;
    }

    public function uploadOrderDocument(): void
    {
        $requestedOrderId = $this->requestOrderId();
        if ($this->partnerOwnedOrderRequest($requestedOrderId)) {
            (new PartnerOrderController())->uploadDocument();
            return;
        }

        $context = $this->requireClientContext();
        verify_csrf();

        $orderId = $requestedOrderId;
        $label = trim((string) input('label', ''));
        $order = $this->ownedOrder($orderId, $context['client_id']);

        if (!$order) {
            flash('error', 'Order not found or you do not have access to it.');
            redirect('client/orders');
            exit;
        }

        if (!$this->orderCanBeEdited($order)) {
            flash('error', 'This order is no longer accepting owner uploads.');
            redirect($this->clientOrderViewUrl($orderId));
            exit;
        }

        if ($label === '') {
            flash('error', 'Please enter a document name.');
            redirect($this->clientOrderViewUrl($orderId));
            exit;
        }

        if (function_exists('mb_substr')) {
            $label = mb_substr($label, 0, 190);
        } else {
            $label = substr($label, 0, 190);
        }

        if (empty($_FILES['document']) || !is_array($_FILES['document'])) {
            flash('error', 'Please choose a document to upload.');
            redirect($this->clientOrderViewUrl($orderId));
            exit;
        }

        $meta = $this->storeClientCorrectionUpload($_FILES['document']);
        if (!$meta) {
            flash('error', 'Upload failed. Use PDF, JPG, PNG, WEBP, DOC, DOCX, XLS or XLSX under 10 MB.');
            redirect($this->clientOrderViewUrl($orderId));
            exit;
        }

        $now = date('Y-m-d H:i:s');

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
                    "active",
                    NULL,
                    NULL,
                    NULL,
                    NULL,
                    NULL
                )',
                [
                    'order_id' => $orderId,
                    'label' => $label,
                    'original_name' => (string) $meta['original_name'],
                    'stored_name' => (string) $meta['stored_name'],
                    'mime_type' => (string) $meta['mime_type'],
                    'size_bytes' => (int) $meta['size_bytes'],
                    'uploaded_by' => $context['user_id'],
                    'created_at' => $now,
                ]
            );

            $this->db()->execute(
                'UPDATE orders
                 SET updated_at = :updated_at
                 WHERE id = :order_id
                   AND client_id = :client_id
                   AND (partner_id IS NULL OR partner_id = 0)',
                [
                    'updated_at' => $now,
                    'order_id' => $orderId,
                    'client_id' => $context['client_id'],
                ]
            );
        } catch (Throwable $e) {
            $newPath = $this->resolveUploadPath((string) ($meta['stored_name'] ?? ''));
            if (is_file($newPath)) {
                @unlink($newPath);
            }

            error_log('Client individual document upload failed: ' . $e->getMessage());
            flash('error', 'The document could not be saved. Please try again.');
            redirect($this->clientOrderViewUrl($orderId));
            exit;
        }

        if (function_exists('activity_log')) {
            activity_log(
                $context['user_id'],
                $orderId,
                'client_document.uploaded',
                'Client uploaded an additional document for the order.',
                [
                    'label' => $label,
                    'original_name' => (string) $meta['original_name'],
                ]
            );
        }

        $this->safeTriggerNotification('client_document_uploaded', [
            'order_id' => $orderId,
            'order_no' => (string) ($order['order_no'] ?? ''),
            'label' => $label,
            'original_name' => (string) $meta['original_name'],
        ]);

        flash('success', 'Document uploaded successfully.');
        redirect($this->clientOrderViewUrl($orderId));
        exit;
    }

    public function download(): void
    {
        $requestedDocumentId = (int) input('document_id', 0);
        if ($this->partnerOwnedDocumentRequest($requestedDocumentId)) {
            (new PartnerOrderController())->download();
            return;
        }

        $context = $this->requireClientContext();
        $documentId = $requestedDocumentId;

        $document = $this->db()->fetch(
            'SELECT
                d.id,
                d.order_id,
                d.source,
                d.original_name,
                d.stored_name,
                d.mime_type,
                d.size_bytes
             FROM order_documents d
             INNER JOIN orders o ON o.id = d.order_id
             WHERE d.id = :document_id
               AND o.client_id = :client_id
               AND (o.partner_id IS NULL OR o.partner_id = 0)
               AND d.is_client_visible = 1
               AND d.source NOT IN ("client", "payment_proof")
               AND COALESCE(d.document_status, "active") <> "wrong"
             LIMIT 1',
            [
                'document_id' => $documentId,
                'client_id' => $context['client_id'],
            ]
        );

        if (!is_array($document) || $document === []) {
            http_response_code(404);
            exit('Document not found or access denied.');
        }

        $path = $this->resolveUploadPath((string) $document['stored_name']);
        if (!is_file($path)) {
            http_response_code(404);
            exit('File not found.');
        }

        $downloadName = basename((string) ($document['original_name'] ?? 'document'));
        $mimeType = trim((string) ($document['mime_type'] ?? '')) ?: 'application/octet-stream';

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Description: File Transfer');
        header('Content-Type: ' . $mimeType);
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $downloadName) . '"');
        header('Content-Length: ' . (string) filesize($path));
        header('Cache-Control: private, no-store, max-age=0');
        readfile($path);
        exit;
    }

    public function invoicePreview(): void
    {
        $this->streamOwnedInvoice((int) input('id', 0), 'inline');
    }

    public function invoiceDownload(): void
    {
        $this->streamOwnedInvoice((int) input('id', 0), 'attachment');
    }

    private function streamOwnedInvoice(int $orderId, string $disposition): never
    {
        $context = $this->requireClientContext();
        $order = $this->ownedOrder($orderId, $context['client_id']);

        if (!$order) {
            http_response_code(404);
            exit('Invoice not found or access denied.');
        }

        $invoice = $this->invoiceForOwnedOrder($orderId, $context['client_id'])
            ?? $this->invoiceDefaults($order);
        $fileName = $this->invoiceFileName($order, $invoice);

        if (class_exists('\\Dompdf\\Dompdf')) {
            $this->streamInvoiceWithDompdf($order, $invoice, $fileName, $disposition);
        }

        $pdf = $this->buildSimpleInvoicePdf($order, $invoice);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/pdf');
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: ' . ($disposition === 'attachment' ? 'attachment' : 'inline') . '; filename="' . $fileName . '"');
        header('Content-Length: ' . strlen($pdf));
        header('Cache-Control: private, no-store, max-age=0');
        header('Pragma: public');
        echo $pdf;
        exit;
    }

    private function invoiceDefaults(array $order): array
    {
        $amount = (float) ($order['fee_amount'] ?? 0);
        $isPaid = in_array(strtolower((string) ($order['payment_status'] ?? '')), ['paid', 'verified', 'success'], true);

        return [
            'id' => 0,
            'order_id' => (int) ($order['id'] ?? 0),
            'client_id' => (int) ($order['client_id'] ?? 0),
            'invoice_no' => 'INV-' . preg_replace('/[^A-Za-z0-9\-]/', '', (string) ($order['order_no'] ?? $order['id'] ?? time())),
            'issue_date' => date('Y-m-d', strtotime((string) ($order['created_at'] ?? 'now'))),
            'due_date' => date('Y-m-d', strtotime((string) ($order['created_at'] ?? 'now'))),
            'subtotal' => $amount,
            'tax_percent' => 0,
            'tax_amount' => 0,
            'total_amount' => $amount,
            'paid_amount' => $isPaid ? $amount : 0,
            'status' => $isPaid ? 'paid' : (string) ($order['payment_status'] ?? 'pending'),
            'notes' => '',
        ];
    }

    private function invoiceFileName(array $order, array $invoice): string
    {
        $invoiceNo = trim((string) ($invoice['invoice_no'] ?? ''));
        $orderNo = trim((string) ($order['order_no'] ?? $order['id'] ?? ''));
        $base = $invoiceNo !== '' ? $invoiceNo : ('invoice-' . $orderNo);
        $base = preg_replace('/[^A-Za-z0-9\-_]/', '-', $base) ?: 'invoice';

        return strtolower($base) . '.pdf';
    }

    private function streamInvoiceWithDompdf(array $order, array $invoice, string $fileName, string $disposition): never
    {
        $optionsClass = '\\Dompdf\\Options';
        $dompdfClass = '\\Dompdf\\Dompdf';
        $options = class_exists($optionsClass) ? new $optionsClass() : null;

        if ($options && method_exists($options, 'set')) {
            $options->set('isRemoteEnabled', true);
            $options->set('defaultFont', 'DejaVu Sans');
        }

        $dompdf = $options ? new $dompdfClass($options) : new $dompdfClass();
        $dompdf->loadHtml($this->invoiceHtml($order, $invoice), 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $dompdf->stream($fileName, ['Attachment' => $disposition === 'attachment']);
        exit;
    }

    private function invoiceHtml(array $order, array $invoice): string
    {
        $e = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        $subtotal = (float) ($invoice['subtotal'] ?? $order['fee_amount'] ?? 0);
        $taxPercent = (float) ($invoice['tax_percent'] ?? 0);
        $taxAmount = (float) ($invoice['tax_amount'] ?? 0);
        $total = (float) ($invoice['total_amount'] ?? $order['fee_amount'] ?? 0);
        $paid = (float) ($invoice['paid_amount'] ?? 0);
        $balance = max(0, $total - $paid);
        $clientName = trim((string) ($order['client_company'] ?? '')) ?: (string) ($order['client_name'] ?? 'Client');

        return '<!doctype html><html><head><meta charset="utf-8"><style>'
            . 'body{font-family:DejaVu Sans,Arial,sans-serif;color:#0f172a;font-size:12px;margin:34px}.header{background:#0f172a;color:#fff;padding:24px;border-radius:14px}.brand{font-size:25px;font-weight:800}.muted{color:#64748b}.grid{width:100%;margin-top:26px}.grid td{width:50%;vertical-align:top}.box{border:1px solid #e2e8f0;border-radius:12px;padding:16px}.items{width:100%;border-collapse:collapse;margin-top:24px}.items th{background:#f1f5f9;text-align:left;padding:11px}.items td{padding:12px 11px;border-bottom:1px solid #e2e8f0}.totals{width:48%;margin-left:auto;margin-top:22px;border-collapse:collapse}.totals td{padding:7px}.total{font-size:15px;font-weight:800;border-top:2px solid #0f172a}.status{display:inline-block;padding:6px 10px;border-radius:999px;background:#ecfdf5;color:#047857;font-weight:700}.footer{margin-top:35px;padding-top:16px;border-top:1px solid #e2e8f0;color:#64748b;font-size:10px}</style></head><body>'
            . '<div class="header"><div class="brand">Tax Saathi</div><div style="margin-top:5px;color:#cbd5e1">Tax and compliance services invoice</div></div>'
            . '<table class="grid"><tr><td style="padding-right:10px"><div class="box"><strong>Invoice</strong><br><br>Invoice No: ' . $e($invoice['invoice_no'] ?? '-') . '<br>Order No: ' . $e($order['order_no'] ?? '-') . '<br>Issue Date: ' . $e($invoice['issue_date'] ?? '-') . '<br>Due Date: ' . $e($invoice['due_date'] ?? '-') . '</div></td>'
            . '<td style="padding-left:10px"><div class="box"><strong>Billed To</strong><br><br>' . $e($clientName) . '<br>' . $e($order['client_email'] ?? '') . '<br>' . $e($order['client_phone'] ?? '') . '<br>' . $e($order['client_city'] ?? '') . '</div></td></tr></table>'
            . '<table class="items"><thead><tr><th>Service</th><th>Financial Year</th><th style="text-align:right">Amount</th></tr></thead><tbody><tr><td>' . $e($order['service_title'] ?? 'Service') . '</td><td>' . $e($order['financial_year'] ?? '-') . '</td><td style="text-align:right">INR ' . number_format($subtotal, 2) . '</td></tr></tbody></table>'
            . '<table class="totals"><tr><td>Subtotal</td><td style="text-align:right">INR ' . number_format($subtotal, 2) . '</td></tr><tr><td>Tax (' . number_format($taxPercent, 2) . '%)</td><td style="text-align:right">INR ' . number_format($taxAmount, 2) . '</td></tr><tr class="total"><td>Total</td><td style="text-align:right">INR ' . number_format($total, 2) . '</td></tr><tr><td>Paid</td><td style="text-align:right">INR ' . number_format($paid, 2) . '</td></tr><tr><td>Balance</td><td style="text-align:right">INR ' . number_format($balance, 2) . '</td></tr></table>'
            . '<div style="margin-top:24px"><span class="status">' . $e(ucwords(str_replace('_', ' ', (string) ($invoice['status'] ?? 'pending')))) . '</span></div>'
            . '<div class="footer">This is a computer-generated invoice. Quote the order number for any support request.</div></body></html>';
    }

    private function buildSimpleInvoicePdf(array $order, array $invoice): string
    {
        $subtotal = (float) ($invoice['subtotal'] ?? $order['fee_amount'] ?? 0);
        $taxPercent = (float) ($invoice['tax_percent'] ?? 0);
        $taxAmount = (float) ($invoice['tax_amount'] ?? 0);
        $total = (float) ($invoice['total_amount'] ?? $order['fee_amount'] ?? 0);
        $paid = (float) ($invoice['paid_amount'] ?? 0);
        $balance = max(0, $total - $paid);

        $content = '';
        $text = function (float $x, float $y, int $size, string $value, string $color = '0 0 0') use (&$content): void {
            $content .= $color . ' rg BT /F1 ' . $size . ' Tf '
                . number_format($x, 2, '.', '') . ' '
                . number_format($y, 2, '.', '') . ' Td ('
                . $this->pdfEscape($value) . ") Tj ET\n";
        };

        $content .= "0.06 0.09 0.16 rg 0 770 595 72 re f\n";
        $text(50, 805, 22, 'Tax Saathi Invoice', '1 1 1');
        $text(50, 785, 10, 'Professional tax and compliance services', '0.80 0.85 0.92');
        $text(50, 735, 16, 'Invoice Details');
        $text(50, 710, 11, 'Invoice No: ' . (string) ($invoice['invoice_no'] ?? '-'));
        $text(50, 692, 11, 'Order No: ' . (string) ($order['order_no'] ?? '-'));
        $text(50, 674, 11, 'Issue Date: ' . (string) ($invoice['issue_date'] ?? '-'));
        $text(50, 656, 11, 'Due Date: ' . (string) ($invoice['due_date'] ?? '-'));
        $text(330, 735, 16, 'Billed To');
        $text(330, 710, 11, (string) ($order['client_name'] ?? '-'));
        $text(330, 692, 11, (string) ($order['client_email'] ?? ''));
        $text(330, 674, 11, (string) ($order['client_phone'] ?? ''));
        $content .= "0.90 0.93 0.96 rg 50 610 495 1 re f\n";
        $text(50, 585, 12, 'Service');
        $text(300, 585, 12, 'Financial Year');
        $text(430, 585, 12, 'Amount');
        $text(50, 558, 11, (string) ($order['service_title'] ?? '-'));
        $text(300, 558, 11, (string) ($order['financial_year'] ?? '-'));
        $text(430, 558, 11, 'INR ' . number_format($subtotal, 2));
        $content .= "0.90 0.93 0.96 rg 50 535 495 1 re f\n";
        $text(330, 500, 11, 'Subtotal: INR ' . number_format($subtotal, 2));
        $text(330, 480, 11, 'Tax (' . number_format($taxPercent, 2) . '%): INR ' . number_format($taxAmount, 2));
        $text(330, 455, 14, 'Total: INR ' . number_format($total, 2));
        $text(330, 432, 11, 'Paid: INR ' . number_format($paid, 2));
        $text(330, 412, 11, 'Balance: INR ' . number_format($balance, 2));
        $text(50, 375, 12, 'Payment Status: ' . (string) ($invoice['status'] ?? $order['payment_status'] ?? 'pending'));
        $text(50, 340, 10, 'Computer generated invoice. Quote your order number for support.', '0.39 0.45 0.55');

        return $this->makePdfDocument($content);
    }

    private function makePdfDocument(string $content): string
    {
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Length ' . strlen($content) . ">>\nstream\n" . $content . "endstream",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $number = $index + 1;
            $pdf .= $number . " 0 obj\n" . $object . "\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= str_pad((string) $offsets[$i], 10, '0', STR_PAD_LEFT) . " 00000 n \n";
        }

        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n" . $xrefOffset . "\n%%EOF";

        return $pdf;
    }

    private function pdfEscape(string $value): string
    {
        $value = preg_replace('/[^\x20-\x7E]/', ' ', $value) ?? '';
        $value = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);

        return function_exists('mb_substr') ? mb_substr($value, 0, 95) : substr($value, 0, 95);
    }

    private function normalizeUploadedDocumentMeta(array $meta, array $file): ?array
    {
        $originalName = (string) ($meta['original_name'] ?? $meta['originalName'] ?? $file['name'] ?? 'document');
        $storedName = (string) ($meta['stored_name'] ?? $meta['storedName'] ?? $meta['filename'] ?? $meta['file_name'] ?? $meta['name'] ?? '');

        if ($storedName === '' && !empty($meta['path'])) {
            $storedName = basename((string) $meta['path']);
        }

        if ($storedName === '') {
            return null;
        }

        return [
            'original_name' => basename($originalName),
            'stored_name' => $storedName,
            'mime_type' => (string) ($meta['mime_type'] ?? $meta['mime'] ?? $meta['type'] ?? $file['type'] ?? 'application/octet-stream'),
            'size_bytes' => (int) ($meta['size_bytes'] ?? $meta['size'] ?? $file['size'] ?? 0),
        ];
    }

    private function storeClientCorrectionUpload(array $file): ?array
    {
        if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }

        if (function_exists('store_single_upload')) {
            try {
                $meta = store_single_upload($file);
                if (is_array($meta) && $meta !== []) {
                    return $this->normalizeUploadedDocumentMeta($meta, $file);
                }
            } catch (Throwable $e) {
                error_log('store_single_upload failed for client correction: ' . $e->getMessage());
            }
        }

        return $this->fallbackClientCorrectionUpload($file);
    }

    private function fallbackClientCorrectionUpload(array $file): ?array
    {
        $originalName = basename((string) ($file['name'] ?? 'document'));
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $size = (int) ($file['size'] ?? 0);

        if (!in_array($extension, self::ALLOWED_UPLOAD_EXTENSIONS, true)) {
            return null;
        }

        if ($size <= 0 || $size > self::MAX_UPLOAD_BYTES) {
            return null;
        }

        $uploadDir = dirname(__DIR__, 2) . '/storage/uploads/documents';
        if (function_exists('config')) {
            try {
                $configured = config('paths.uploads');
                if (is_string($configured) && trim($configured) !== '') {
                    $uploadDir = rtrim($configured, '/\\');
                }
            } catch (Throwable $e) {
            }
        }

        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            return null;
        }

        $storedName = 'client-correction-' . date('YmdHis') . '-' . bin2hex(random_bytes(8)) . '.' . $extension;
        $target = $uploadDir . DIRECTORY_SEPARATOR . $storedName;

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
            }
        }

        return [
            'original_name' => $originalName,
            'stored_name' => $storedName,
            'mime_type' => $mimeType,
            'size_bytes' => $size,
        ];
    }

    private function resolveUploadPath(string $storedName): string
    {
        if (function_exists('upload_path')) {
            try {
                $path = upload_path($storedName);
                if (is_string($path) && $path !== '') {
                    return $path;
                }
            } catch (Throwable $e) {
            }
        }

        return dirname(__DIR__, 2) . '/storage/uploads/documents/' . ltrim($storedName, '/\\');
    }

    private function safeTriggerNotification(string $event, array $payload = []): void
    {
        if (!class_exists(NotificationService::class) || !method_exists(NotificationService::class, 'trigger')) {
            return;
        }

        try {
            NotificationService::trigger($event, $payload);
        } catch (Throwable $e) {
            error_log('Notification trigger failed: ' . $e->getMessage());
        }
    }
}