<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use PDO;
use RuntimeException;
use Throwable;

class PaymentMethodController extends Controller
{
    private ?PDO $pdo = null;

    /**
     * Compatibility method for routers that call PaymentMethodController::show().
     * GET /payment-method
     */
    public function show(): void
    {
        $this->index();
    }

    /**
     * Compatibility method for routers that call PaymentMethodController::create().
     */
    public function create(): void
    {
        $this->index();
    }

    /**
     * Compatibility method for routers that call PaymentMethodController::save()
     * or use a generic POST handler name.
     */
    public function save(): void
    {
        $this->store();
    }

    /**
     * GET /payment-method?id=123
     * Also supports ?application_id=123.
     */
    public function index(): void
    {
        $applicationId = (int) ($_GET['application_id'] ?? $_GET['id'] ?? ($_SESSION['service_application_id'] ?? 0));

        if ($applicationId <= 0) {
            flash('error', 'Application not found. Please submit the application again.');
            redirect(base_url('services'));
            return;
        }

        $application = $this->findApplication($applicationId);

        if (!$application) {
            flash('error', 'Application not found.');
            redirect(base_url('services'));
            return;
        }

        $service = $this->findServiceForApplication($application);
        $paymentConfig = $this->paymentConfig();

        $this->view('public/payment-method', [
            'title' => 'Payment Method',
            'application' => $application,
            'service' => $service,
            'errors' => $_SESSION['_payment_errors'] ?? [],
            'old' => $_SESSION['_payment_old'] ?? [],
            'paymentSettings' => [
                'upi_id' => (string) ($paymentConfig['upi_id'] ?? ''),
                'upi_qr' => (string) ($paymentConfig['upi_qr'] ?? ''),
                'bank_name' => (string) ($paymentConfig['bank_name'] ?? ''),
                'ifsc' => (string) ($paymentConfig['bank_ifsc'] ?? $paymentConfig['ifsc'] ?? ''),
                'account_number' => (string) ($paymentConfig['bank_account_number'] ?? $paymentConfig['account_number'] ?? ''),

                'razorpay_key_id' => (string) ($paymentConfig['key_id'] ?? ''),
                'razorpay_create_order_url' => base_url('razorpay/create-order'),
                'razorpay_currency' => (string) ($paymentConfig['currency'] ?? 'INR'),
                'razorpay_company_name' => (string) ($paymentConfig['company_name'] ?? 'Tax Saathi'),
                'razorpay_theme_color' => (string) ($paymentConfig['theme_color'] ?? '#3852B4'),
            ],
        ]);

        unset($_SESSION['_payment_errors'], $_SESSION['_payment_old']);
    }

    /**
     * POST /payment-method
     *
     * Manual methods:
     * - bank_transfer
     * - upi
     * - cash_on_delivery
     *
     * Online:
     * - razorpay
     */
    public function store(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            redirect(base_url('services'));
            return;
        }

        verify_csrf();

        $applicationId = (int) ($_POST['application_id'] ?? 0);
        $paymentMethod = strtolower(trim((string) ($_POST['payment_method'] ?? '')));

        if ($paymentMethod === 'cash') {
            $paymentMethod = 'cash_on_delivery';
        }

        $errors = [];

        if ($applicationId <= 0) {
            $errors['general'] = 'Invalid application.';
        }

        if (!in_array($paymentMethod, ['upi', 'bank_transfer', 'cash_on_delivery', 'razorpay'], true)) {
            $errors['payment_method'] = 'Please select a valid payment method.';
        }

        $application = $applicationId > 0 ? $this->findApplication($applicationId) : null;

        if (!$application) {
            $errors['general'] = 'Application not found.';
        }

        $service = $application ? $this->findServiceForApplication($application) : [];
        $serviceSlug = trim((string) ($service['slug'] ?? $application['service_slug'] ?? $_POST['service_slug'] ?? ''));

        if ($errors !== []) {
            $_SESSION['_payment_errors'] = $errors;
            $_SESSION['_payment_old'] = $_POST;
            redirect(base_url('payment-method?application_id=' . $applicationId));
            return;
        }

        if ($paymentMethod === 'razorpay') {
            $verified = $this->verifyRazorpaySignature($_POST);

            if (!$verified) {
                $_SESSION['_payment_errors'] = [
                    'general' => 'Razorpay payment verification failed. Please try again.',
                ];
                $_SESSION['_payment_old'] = $_POST;

                redirect(base_url('payment-method?application_id=' . $applicationId));
                return;
            }
        }

        $paymentReference = $paymentMethod === 'razorpay'
            ? trim((string) ($_POST['razorpay_payment_id'] ?? ''))
            : ($paymentMethod === 'cash_on_delivery'
                ? 'COD'
                : trim((string) ($_POST['payment_reference'] ?? '')));

        $paymentStatus = $paymentMethod === 'razorpay' ? 'paid' : 'pending_payment';

        $this->updateApplicationPayment($applicationId, [
            'payment_method' => $paymentMethod,
            'payment_status' => $paymentStatus,
            'payment_reference' => $paymentReference,
            'razorpay_payment_id' => trim((string) ($_POST['razorpay_payment_id'] ?? '')),
            'razorpay_order_id' => trim((string) ($_POST['razorpay_order_id'] ?? '')),
            'razorpay_signature' => trim((string) ($_POST['razorpay_signature'] ?? '')),
            'paid_at' => $paymentMethod === 'razorpay' ? date('Y-m-d H:i:s') : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        if ($paymentMethod === 'razorpay') {
            flash('success', 'Payment successful. Your application has been updated.');
            redirect(base_url('client/orders'));
            return;
        }

        /*
         * Manual payment selection is saved.
         * Redirect user to service-order page where payment proof/documents can be uploaded.
         */
        $query = [
            'service' => $serviceSlug,
            'application_id' => $applicationId,
            'payment_method' => $paymentMethod,
        ];

        flash(
            'success',
            $paymentMethod === 'cash_on_delivery'
                ? 'Cash on Delivery (Pay Later) selected. Please confirm the request and required documents.'
                : 'Payment method selected. Please upload payment proof and required documents.'
        );
        redirect(base_url('service-order?' . http_build_query($query)));
    }

    private function findApplication(int $id): ?array
    {
        $pdo = $this->db();
        $stmt = $pdo->prepare('SELECT * FROM service_applications WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function findServiceForApplication(array $application): array
    {
        $pdo = $this->db();

        $serviceId = (int) ($application['service_id'] ?? 0);
        $serviceSlug = trim((string) ($application['service_slug'] ?? ''));

        if ($serviceId > 0) {
            $stmt = $pdo->prepare('SELECT * FROM services WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $serviceId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (is_array($row)) {
                return $row;
            }
        }

        if ($serviceSlug !== '') {
            $stmt = $pdo->prepare('SELECT * FROM services WHERE slug = :slug LIMIT 1');
            $stmt->execute(['slug' => $serviceSlug]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (is_array($row)) {
                return $row;
            }
        }

        return [
            'id' => (int) ($application['service_id'] ?? 0),
            'title' => (string) ($application['service_title'] ?? 'Service'),
            'slug' => $serviceSlug,
            'filing_fee' => (float) ($application['service_fee'] ?? 0),
        ];
    }

    private function updateApplicationPayment(int $applicationId, array $data): void
    {
        $pdo = $this->db();
        $columns = $this->tableColumns('service_applications');

        $allowed = [];
        foreach ($data as $key => $value) {
            if (in_array($key, $columns, true)) {
                $allowed[$key] = $value;
            }
        }

        if ($allowed === []) {
            return;
        }

        $sets = [];
        $params = ['id' => $applicationId];

        foreach ($allowed as $key => $value) {
            $sets[] = "`{$key}` = :{$key}";
            $params[$key] = $value;
        }

        $sql = 'UPDATE service_applications SET ' . implode(', ', $sets) . ' WHERE id = :id';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }

    private function verifyRazorpaySignature(array $post): bool
    {
        $paymentId = trim((string) ($post['razorpay_payment_id'] ?? ''));
        $orderId = trim((string) ($post['razorpay_order_id'] ?? ''));
        $signature = trim((string) ($post['razorpay_signature'] ?? ''));

        if ($paymentId === '' || $orderId === '' || $signature === '') {
            return false;
        }

        $config = $this->paymentConfig();
        $secret = trim((string) ($config['key_secret'] ?? ''));

        if ($secret === '') {
            return false;
        }

        $generated = hash_hmac('sha256', $orderId . '|' . $paymentId, $secret);

        return hash_equals($generated, $signature);
    }

    private function tableColumns(string $table): array
    {
        try {
            $stmt = $this->db()->query('DESCRIBE `' . str_replace('`', '', $table) . '`');
            $columns = [];

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $columns[] = (string) ($row['Field'] ?? '');
            }

            return array_values(array_filter($columns));
        } catch (Throwable $e) {
            return [];
        }
    }

    private function paymentConfig(): array
    {
        $env = $this->loadDotEnv();

        $config = [];

        if (function_exists('config')) {
            try {
                $tmp = config('razorpay');
                if (is_array($tmp)) {
                    $config = $tmp;
                }
            } catch (Throwable $e) {
            }
        }

        if ($config === [] && defined('BASE_PATH') && is_file(BASE_PATH . '/config/razorpay.php')) {
            $tmp = require BASE_PATH . '/config/razorpay.php';
            if (is_array($tmp)) {
                $config = $tmp;
            }
        }

        $defaults = [
            'key_id' => $this->envValue($env, ['RAZORPAY_KEY_ID', 'RAZORPAY_KEY'], ''),
            'key_secret' => $this->envValue($env, ['RAZORPAY_KEY_SECRET', 'RAZORPAY_SECRET'], ''),
            'currency' => $this->envValue($env, ['RAZORPAY_CURRENCY'], 'INR'),
            'company_name' => $this->envValue($env, ['RAZORPAY_COMPANY_NAME'], 'Tax Saathi'),
            'theme_color' => $this->envValue($env, ['RAZORPAY_THEME_COLOR'], '#3852B4'),

            'upi_id' => $this->envValue($env, ['UPI_ID'], 'taxsaathi@upi'),
            'upi_qr' => $this->envValue($env, ['UPI_QR'], ''),
            'bank_name' => $this->envValue($env, ['BANK_NAME'], 'Add Bank Name'),
            'bank_ifsc' => $this->envValue($env, ['BANK_IFSC'], 'ADDIFSC0000'),
            'bank_account_number' => $this->envValue($env, ['BANK_ACCOUNT_NUMBER'], '000000000000'),
        ];

        foreach ($config as $key => $value) {
            if (trim((string) $value) !== '') {
                $defaults[$key] = $value;
            }
        }

        return $defaults;
    }

    private function loadDotEnv(): array
    {
        $paths = [];

        if (defined('BASE_PATH')) {
            $basePath = rtrim((string) BASE_PATH, '/');
            $paths[] = $basePath . '/.env';
            $paths[] = dirname($basePath) . '/.env';
            $paths[] = $basePath . '/app/.env';
            $paths[] = $basePath . '/config/.env';
        }

        $paths[] = dirname(__DIR__, 2) . '/.env';
        $paths[] = dirname(__DIR__, 3) . '/.env';
        $paths[] = dirname(__DIR__) . '/.env';

        $values = [];

        foreach (array_unique($paths) as $path) {
            if (!is_file($path) || !is_readable($path)) {
                continue;
            }

            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (!is_array($lines)) {
                continue;
            }

            foreach ($lines as $line) {
                $line = trim($line);

                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                    continue;
                }

                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                if ($key === '') {
                    continue;
                }

                if (
                    (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                    (str_starts_with($value, "'") && str_ends_with($value, "'"))
                ) {
                    $value = substr($value, 1, -1);
                }

                $values[$key] = $value;

                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }

            if ($values !== []) {
                break;
            }
        }

        return $values;
    }

    private function envValue(array $env, array $keys, string $default = ''): string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $env) && trim((string) $env[$key]) !== '') {
                return trim((string) $env[$key]);
            }

            if (array_key_exists($key, $_ENV) && trim((string) $_ENV[$key]) !== '') {
                return trim((string) $_ENV[$key]);
            }

            if (array_key_exists($key, $_SERVER) && trim((string) $_SERVER[$key]) !== '') {
                return trim((string) $_SERVER[$key]);
            }

            if (function_exists('getenv')) {
                $value = \getenv($key);

                if ($value !== false && trim((string) $value) !== '') {
                    return trim((string) $value);
                }
            }
        }

        return $default;
    }

    private function db(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        /*
         * 1) Try DB connection already available on the parent Controller.
         *    Many custom MVC controllers keep the connection as $this->db.
         */
        foreach (['db', 'pdo', 'database', 'conn', 'connection'] as $property) {
            if (property_exists($this, $property)) {
                try {
                    $pdo = $this->pdoFromValue($this->{$property});
                    if ($pdo instanceof PDO) {
                        return $this->pdo = $pdo;
                    }
                } catch (Throwable $e) {
                }
            }
        }

        /*
         * 2) Try global helper functions if your bootstrap exposes one.
         */
        foreach (['\\db', '\\database', '\\pdo'] as $function) {
            if (function_exists($function)) {
                try {
                    $pdo = $function();
                    $pdo = $this->pdoFromValue($pdo);
                    if ($pdo instanceof PDO) {
                        return $this->pdo = $pdo;
                    }
                } catch (Throwable $e) {
                }
            }
        }

        /*
         * 3) Try common Database classes safely.
         *    Supports both static and instance-based Database classes.
         */
        foreach (['\\App\\Core\\Database', '\\Core\\Database', '\\Database', '\\App\\Database'] as $class) {
            $pdo = $this->pdoFromDatabaseClass($class);
            if ($pdo instanceof PDO) {
                return $this->pdo = $pdo;
            }
        }

        /*
         * 4) Load app/config/config.php or config/database.php if the bootstrap
         *    has not already defined DB constants.
         */
        $config = $this->loadDatabaseConfig();

        $pdo = $this->pdoFromConfig($config);
        if ($pdo instanceof PDO) {
            return $this->pdo = $pdo;
        }

        /*
         * 5) Final fallback from constants / environment.
         */
        $host = $this->configValue($config, ['host', 'db_host', 'DB_HOST'], defined('DB_HOST') ? DB_HOST : (function_exists('getenv') ? (\getenv('DB_HOST') ?: '') : ($_ENV['DB_HOST'] ?? $_SERVER['DB_HOST'] ?? '')));
        $database = $this->configValue($config, ['database', 'dbname', 'db_name', 'name', 'DB_NAME', 'DB_DATABASE'], defined('DB_NAME') ? DB_NAME : (defined('DB_DATABASE') ? DB_DATABASE : ((function_exists('getenv') ? (\getenv('DB_NAME') ?: '') : ($_ENV['DB_NAME'] ?? $_SERVER['DB_NAME'] ?? '')) ?: (function_exists('getenv') ? (\getenv('DB_DATABASE') ?: '') : ($_ENV['DB_DATABASE'] ?? $_SERVER['DB_DATABASE'] ?? '')))));
        $username = $this->configValue($config, ['username', 'user', 'db_user', 'DB_USER', 'DB_USERNAME'], defined('DB_USER') ? DB_USER : (defined('DB_USERNAME') ? DB_USERNAME : ((function_exists('getenv') ? (\getenv('DB_USER') ?: '') : ($_ENV['DB_USER'] ?? $_SERVER['DB_USER'] ?? '')) ?: (function_exists('getenv') ? (\getenv('DB_USERNAME') ?: '') : ($_ENV['DB_USERNAME'] ?? $_SERVER['DB_USERNAME'] ?? '')))));
        $password = $this->configValue($config, ['password', 'pass', 'db_pass', 'DB_PASS', 'DB_PASSWORD'], defined('DB_PASS') ? DB_PASS : (defined('DB_PASSWORD') ? DB_PASSWORD : ((function_exists('getenv') ? (\getenv('DB_PASS') ?: '') : ($_ENV['DB_PASS'] ?? $_SERVER['DB_PASS'] ?? '')) ?: (function_exists('getenv') ? (\getenv('DB_PASSWORD') ?: '') : ($_ENV['DB_PASSWORD'] ?? $_SERVER['DB_PASSWORD'] ?? '')))));
        $port = $this->configValue($config, ['port', 'db_port', 'DB_PORT'], defined('DB_PORT') ? DB_PORT : ((function_exists('getenv') ? (\getenv('DB_PORT') ?: '3306') : ($_ENV['DB_PORT'] ?? $_SERVER['DB_PORT'] ?? '3306')) ?: '3306'));
        $charset = $this->configValue($config, ['charset', 'DB_CHARSET'], defined('DB_CHARSET') ? DB_CHARSET : ((function_exists('getenv') ? (\getenv('DB_CHARSET') ?: 'utf8mb4') : ($_ENV['DB_CHARSET'] ?? $_SERVER['DB_CHARSET'] ?? 'utf8mb4')) ?: 'utf8mb4'));

        if ($host !== '' && $database !== '' && $username !== '') {
            $dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $database . ';charset=' . $charset;

            return $this->pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        }

        throw new RuntimeException(
            'Database connection not available. Check app/config/config.php or define DB_HOST, DB_NAME, DB_USER, DB_PASS.'
        );
    }

    private function pdoFromDatabaseClass(string $class): ?PDO
    {
        if (!class_exists($class)) {
            return null;
        }

        foreach (['getConnection', 'connection', 'connect', 'db', 'pdo', 'getPdo'] as $method) {
            if (!method_exists($class, $method)) {
                continue;
            }

            try {
                $reflection = new \ReflectionMethod($class, $method);

                if ($reflection->isStatic()) {
                    $pdo = $class::$method();
                    $pdo = $this->pdoFromValue($pdo);

                    if ($pdo instanceof PDO) {
                        return $pdo;
                    }
                }
            } catch (Throwable $e) {
            }
        }

        if (method_exists($class, 'getInstance')) {
            try {
                $reflection = new \ReflectionMethod($class, 'getInstance');

                if ($reflection->isStatic()) {
                    $instance = $class::getInstance();
                    $pdo = $this->pdoFromValue($instance);

                    if ($pdo instanceof PDO) {
                        return $pdo;
                    }
                }
            } catch (Throwable $e) {
            }
        }

        try {
            $reflectionClass = new \ReflectionClass($class);
            $constructor = $reflectionClass->getConstructor();

            if ($constructor === null || $constructor->getNumberOfRequiredParameters() === 0) {
                $instance = $reflectionClass->newInstance();
                $pdo = $this->pdoFromValue($instance);

                if ($pdo instanceof PDO) {
                    return $pdo;
                }
            }
        } catch (Throwable $e) {
        }

        return null;
    }

    private function pdoFromValue(mixed $value): ?PDO
    {
        if ($value instanceof PDO) {
            return $value;
        }

        if (!is_object($value)) {
            return null;
        }

        foreach (['getConnection', 'connection', 'connect', 'db', 'pdo', 'getPdo'] as $method) {
            if (!method_exists($value, $method)) {
                continue;
            }

            try {
                $pdo = $value->{$method}();

                if ($pdo instanceof PDO) {
                    return $pdo;
                }
            } catch (Throwable $e) {
            }
        }

        foreach (['pdo', 'db', 'connection', 'conn'] as $property) {
            if (!property_exists($value, $property)) {
                continue;
            }

            try {
                $pdo = $value->{$property};

                if ($pdo instanceof PDO) {
                    return $pdo;
                }
            } catch (Throwable $e) {
            }
        }

        return null;
    }

    private function loadDatabaseConfig(): array
    {
        $paths = [];

        if (defined('BASE_PATH')) {
            $paths[] = rtrim((string) BASE_PATH, '/') . '/config/config.php';
            $paths[] = rtrim((string) BASE_PATH, '/') . '/config/database.php';
            $paths[] = rtrim((string) BASE_PATH, '/') . '/app/config/config.php';
            $paths[] = rtrim((string) BASE_PATH, '/') . '/app/config/database.php';
        }

        $paths[] = dirname(__DIR__) . '/config/config.php';
        $paths[] = dirname(__DIR__) . '/config/database.php';
        $paths[] = dirname(__DIR__, 2) . '/config/config.php';
        $paths[] = dirname(__DIR__, 2) . '/config/database.php';
        $paths[] = dirname(__DIR__, 2) . '/app/config/config.php';
        $paths[] = dirname(__DIR__, 2) . '/app/config/database.php';

        foreach (array_unique($paths) as $path) {
            if (!is_file($path)) {
                continue;
            }

            try {
                $config = require $path;

                if (is_array($config)) {
                    if (isset($config['database']) && is_array($config['database'])) {
                        return $config['database'];
                    }

                    if (isset($config['db']) && is_array($config['db'])) {
                        return $config['db'];
                    }

                    return $config;
                }

                /*
                 * Some config files only define constants and return true/null.
                 * After requiring it, constants are available for fallback below.
                 */
                return [];
            } catch (Throwable $e) {
            }
        }

        return [];
    }

    private function pdoFromConfig(array $config): ?PDO
    {
        $host = $this->configValue($config, ['host', 'db_host', 'DB_HOST']);
        $database = $this->configValue($config, ['database', 'dbname', 'db_name', 'name', 'DB_NAME', 'DB_DATABASE']);
        $username = $this->configValue($config, ['username', 'user', 'db_user', 'DB_USER', 'DB_USERNAME']);
        $password = $this->configValue($config, ['password', 'pass', 'db_pass', 'DB_PASS', 'DB_PASSWORD']);
        $port = $this->configValue($config, ['port', 'db_port', 'DB_PORT'], '3306');
        $charset = $this->configValue($config, ['charset', 'DB_CHARSET'], 'utf8mb4');

        if ($host === '' || $database === '' || $username === '') {
            return null;
        }

        $dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $database . ';charset=' . $charset;

        return new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    private function configValue(array $config, array $keys, mixed $default = ''): string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $config) && trim((string) $config[$key]) !== '') {
                return trim((string) $config[$key]);
            }
        }

        return trim((string) $default);
    }
}
