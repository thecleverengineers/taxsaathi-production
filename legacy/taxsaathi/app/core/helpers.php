<?php
declare(strict_types=1);

use App\Core\Container;
use App\Models\ActivityLog;
use App\Models\Setting;

function app(string $key): mixed
{
    /** @var Container $container */
    $container = $GLOBALS['app_container'];
    return $container->get($key);
}

function config(string $key, mixed $default = null): mixed
{
    $config = app('config');
    $segments = explode('.', $key);
    $value = $config;

    foreach ($segments as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }

    return $value;
}

function base_url(string $path = ''): string
{
    $base = rtrim((string) config('app.url', ''), '/');
    return $base . ($path !== '' ? '/' . ltrim($path, '/') : '');
}

function asset(string $path): string
{
    return base_url('assets/' . ltrim($path, '/'));
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function view(string $view, array $data = [], string $layout = 'layouts/frontend'): void
{
    $viewFile = __DIR__ . '/../views/' . $view . '.php';
    $layoutFile = __DIR__ . '/../views/' . $layout . '.php';

    if (!is_file($viewFile)) {
        throw new RuntimeException('View not found: ' . $view);
    }

    if (!is_file($layoutFile)) {
        throw new RuntimeException('Layout not found: ' . $layout);
    }

    extract($data, EXTR_SKIP);
    ob_start();
    require $viewFile;
    $content = ob_get_clean();

    require $layoutFile;
}

function redirect(string $path): never
{
    header('Location: ' . base_url($path));
    exit;
}

function input(string $key, mixed $default = null): mixed
{
    return $_POST[$key] ?? $_GET[$key] ?? $default;
}

function setting(string $key, string $default = ''): string
{
    return Setting::get($key, $default);
}

function csrf_token(): string
{
    $key = (string) config('security.csrf_key', '_csrf');
    if (empty($_SESSION[$key])) {
        $_SESSION[$key] = bin2hex(random_bytes(32));
    }
    return $_SESSION[$key];
}

function csrf_field(): string
{
    $key = (string) config('security.csrf_key', '_csrf');
    return '<input type="hidden" name="' . e($key) . '" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $key = (string) config('security.csrf_key', '_csrf');
    $sent = $_POST[$key] ?? '';
    $session = $_SESSION[$key] ?? '';
    if (!$sent || !$session || !hash_equals($session, $sent)) {
        http_response_code(419);
        exit('CSRF token mismatch.');
    }
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['_flash'][$key] = $message;
        return null;
    }

    $value = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $value;
}

function old(string $key, string $default = ''): string
{
    return e((string) ($_SESSION['_old'][$key] ?? $default));
}

function with_old(array $input): void
{
    $_SESSION['_old'] = $input;
}

function clear_old(): void
{
    unset($_SESSION['_old']);
}

function auth_user(): ?array
{
    return $_SESSION['auth_user'] ?? null;
}

function is_logged_in(): bool
{
    return !empty(auth_user());
}

function login_user(array $user): void
{
    unset($user['password_hash']);
    $_SESSION['auth_user'] = $user;

    $userId = (int) ($user['id'] ?? 0);
    if ($userId > 0) {
        $_SESSION['user_id'] = $userId;
        $_SESSION['auth_user_id'] = $userId;
    }
}

function logout_user(): void
{
    unset(
        $_SESSION['auth_user'],
        $_SESSION['auth_otp'],
        $_SESSION['user_id'],
        $_SESSION['auth_user_id']
    );
}

function require_auth(): void
{
    if (!is_logged_in()) {
        flash('error', 'Please login to continue.');
        redirect('auth');
    }
}

function require_guest(): void
{
    if (is_logged_in()) {
        redirect('dashboard');
    }
}

function can(string $permission): bool
{
    $user = auth_user();
    if (!$user) {
        return false;
    }

    $permissions = $user['permissions'] ?? [];
    return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
}

function require_permission(string $permission): void
{
    require_auth();
    if (!can($permission)) {
        http_response_code(403);
        view('public/403', ['title' => 'Access denied', 'permission' => $permission], 'layouts/blank');
        exit;
    }
}

function current_user_is_client(): bool
{
    $user = auth_user();
    if (!is_array($user)) {
        return false;
    }

    $roleIds = array_map('intval', (array) ($user['role_ids'] ?? []));
    return in_array(5, $roleIds, true);
}

function require_client(): void
{
    require_auth();
    if (!current_user_is_client()) {
        flash('error', 'This area is available only for client accounts.');
        redirect('dashboard');
    }
}

function permissions_catalog(): array
{
    return [
        'Dashboard' => ['dashboard.view'],
        'Website' => ['website.manage', 'services.manage', 'faqs.manage', 'testimonials.manage'],
        'Orders' => ['orders.manage', 'orders.approve', 'docs.download_client', 'docs.upload_output'],
        'CRM' => ['leads.manage', 'clients.manage'],
        'Finance' => ['invoices.manage', 'payments.manage', 'reports.view'],
        'Settings' => ['staff.manage', 'calculators.manage', 'notifications.manage'],
        'Client' => ['client.portal'],
    ];
}

function normalize_phone(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    if (strlen($digits) === 10) {
        return '+91' . $digits;
    }
    if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
        return '+' . $digits;
    }
    if ($digits !== '' && !str_starts_with($phone, '+')) {
        return '+' . $digits;
    }
    return $phone;
}

function phone_digits(string $phone): string
{
    return preg_replace('/\D+/', '', $phone) ?? '';
}

function format_date(?string $value, string $format = 'd M Y'): string
{
    if (!$value) {
        return '-';
    }
    try {
        return (new DateTime($value))->format($format);
    } catch (Throwable) {
        return $value;
    }
}

function format_money(float|int|string|null $amount): string
{
    return '₹' . number_format((float) $amount, 2);
}

function storage_path(string $path = ''): string
{
    $base = rtrim((string) config('paths.storage', ''), '/');
    return $base . ($path !== '' ? '/' . ltrim($path, '/') : '');
}

function upload_path(string $path = ''): string
{
    $base = rtrim((string) config('paths.uploads', ''), '/');
    return $base . ($path !== '' ? '/' . ltrim($path, '/') : '');
}

function ensure_upload_directory(): void
{
    $dir = (string) config('paths.uploads', '');
    if ($dir !== '' && !is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
}

function unique_file_name(string $original): string
{
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    return date('YmdHis') . '_' . bin2hex(random_bytes(8)) . ($ext ? '.' . $ext : '');
}

function store_single_upload(array $file): ?array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }

    $stored = unique_file_name((string) $file['name']);
    $target = upload_path($stored);

    if (!move_uploaded_file((string) $file['tmp_name'], $target)) {
        throw new RuntimeException('Unable to move uploaded file.');
    }

    return [
        'original_name' => (string) $file['name'],
        'stored_name' => $stored,
        'mime_type' => mime_content_type($target) ?: 'application/octet-stream',
        'size_bytes' => (int) filesize($target),
    ];
}

function normalize_files_array(array $files): array
{
    $normalized = [];
    $count = count($files['name'] ?? []);
    for ($i = 0; $i < $count; $i++) {
        if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $normalized[] = [
            'name' => $files['name'][$i] ?? '',
            'type' => $files['type'][$i] ?? '',
            'tmp_name' => $files['tmp_name'][$i] ?? '',
            'error' => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
            'size' => $files['size'][$i] ?? 0,
        ];
    }
    return $normalized;
}

function render_status_badge(string $status): string
{
    $map = [
        'pending_review' => 'badge badge-warn',
        'approved' => 'badge badge-info',
        'work_in_progress' => 'badge badge-primary',
        'completed' => 'badge badge-success',
        'rejected' => 'badge badge-danger',
        'pending' => 'badge badge-warn',
        'verified' => 'badge badge-success',
        'unpaid' => 'badge badge-warn',
        'paid' => 'badge badge-success',
    ];
    $class = $map[$status] ?? 'badge';
    $label = ucwords(str_replace('_', ' ', $status));
    return '<span class="' . e($class) . '">' . e($label) . '</span>';
}

function activity_log(?int $userId, ?int $orderId, string $action, string $description, array $meta = []): void
{
    try {
        ActivityLog::insert([
            'user_id' => $userId,
            'order_id' => $orderId,
            'action' => $action,
            'description' => $description,
            'meta_json' => json_encode($meta, JSON_UNESCAPED_UNICODE),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    } catch (Throwable) {
    }
}

/*
|--------------------------------------------------------------------------
| SMTP Mail Helpers
|--------------------------------------------------------------------------
*/

function smtp_settings(): array
{
    $mailConfig = config('mail', []);

    $smtp = is_array($mailConfig['smtp'] ?? null) ? $mailConfig['smtp'] : [];

    return [
        'from_email' => trim((string) ($mailConfig['from_email'] ?? 'emailer@taxsaathi.in')),
        'from_name'  => trim((string) ($mailConfig['from_name'] ?? 'Tax Saathi')),

        'smtp_host'  => trim((string) ($smtp['host'] ?? 'smtp.mailer91.com')),
        'smtp_port'  => (int) ($smtp['port'] ?? 587),
        'smtp_user'  => trim((string) ($smtp['username'] ?? 'emailer@taxsaathi.in')),
        'smtp_pass'  => (string) ($smtp['password'] ?? 'ZED7Elr00WnUXa3p'),
        'smtp_secure'=> strtolower(trim((string) ($smtp['encryption'] ?? 'tls'))),
        'timeout'    => (int) ($smtp['timeout'] ?? 30),
    ];
}

function smtp_mail_clean_header(string $value): string
{
    return trim(str_replace(["\r", "\n"], '', $value));
}

function smtp_mail_encode_header(string $value): string
{
    $value = smtp_mail_clean_header($value);

    if ($value === '') {
        return '';
    }

    return '=?UTF-8?B?' . base64_encode($value) . '?=';
}

function smtp_mail_format_address(string $email, string $name = ''): string
{
    $email = smtp_mail_clean_header($email);
    $name  = smtp_mail_clean_header($name);

    if ($name === '') {
        return '<' . $email . '>';
    }

    return smtp_mail_encode_header($name) . ' <' . $email . '>';
}

function smtp_mail_read_response($socket): array
{
    $response = '';

    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;

        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
    }

    $code = (int) substr($response, 0, 3);

    return [$code, trim($response)];
}

function smtp_mail_expect($socket, array $expectedCodes): void
{
    [$code, $message] = smtp_mail_read_response($socket);

    if (!in_array($code, $expectedCodes, true)) {
        throw new RuntimeException('SMTP error: ' . $message);
    }
}

function smtp_mail_command($socket, string $command, array $expectedCodes): void
{
    fwrite($socket, $command . "\r\n");
    smtp_mail_expect($socket, $expectedCodes);
}

function smtp_mail_normalize_body(string $body): string
{
    $body = str_replace(["\r\n", "\r"], "\n", $body);
    $body = preg_replace("/^\./m", '..', $body) ?? $body;
    return str_replace("\n", "\r\n", $body);
}

function smtp_mail_build_message(
    string $fromEmail,
    string $fromName,
    string $toEmail,
    string $toName,
    string $subject,
    string $htmlBody,
    string $textBody = ''
): string {
    $subject = smtp_mail_clean_header($subject);

    if ($textBody === '') {
        $textBody = trim(strip_tags($htmlBody));
    }

    $boundary = 'b1_' . bin2hex(random_bytes(12));

    $headers = [
        'Date: ' . date(DATE_RFC2822),
        'From: ' . smtp_mail_format_address($fromEmail, $fromName),
        'To: ' . smtp_mail_format_address($toEmail, $toName),
        'Subject: ' . smtp_mail_encode_header($subject),
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        'X-Mailer: TaxSaathi SMTP Mailer',
    ];

    $textPart = quoted_printable_encode(smtp_mail_normalize_body($textBody));
    $htmlPart = quoted_printable_encode(smtp_mail_normalize_body($htmlBody));

    $message = implode("\r\n", $headers) . "\r\n\r\n";

    $message .= '--' . $boundary . "\r\n";
    $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
    $message .= $textPart . "\r\n\r\n";

    $message .= '--' . $boundary . "\r\n";
    $message .= "Content-Type: text/html; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
    $message .= $htmlPart . "\r\n\r\n";

    $message .= '--' . $boundary . "--\r\n";

    return $message;
}

function send_smtp_mail(
    string $toEmail,
    string $toName,
    string $subject,
    string $htmlBody,
    string $textBody = ''
): bool {
    $settings = smtp_settings();

    $host = $settings['smtp_host'];
    $port = (int) $settings['smtp_port'];
    $username = $settings['smtp_user'];
    $password = $settings['smtp_pass'];
    $secure = $settings['smtp_secure'];
    $timeout = (int) $settings['timeout'];
    $fromEmail = $settings['from_email'];
    $fromName = $settings['from_name'];

    if (
        $host === '' ||
        $port <= 0 ||
        $username === '' ||
        $password === '' ||
        !filter_var($toEmail, FILTER_VALIDATE_EMAIL) ||
        !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)
    ) {
        error_log('SMTP configuration is invalid.');
        return false;
    }

    $transport = 'tcp://' . $host . ':' . $port;

    $context = stream_context_create([
        'ssl' => [
            'verify_peer'       => true,
            'verify_peer_name'  => true,
            'allow_self_signed' => false,
        ],
    ]);

    $socket = @stream_socket_client(
        $transport,
        $errno,
        $errstr,
        $timeout,
        STREAM_CLIENT_CONNECT,
        $context
    );

    if (!is_resource($socket)) {
        error_log('SMTP connect failed: ' . $errstr . ' (' . $errno . ')');
        return false;
    }

    stream_set_timeout($socket, $timeout);

    try {
        smtp_mail_expect($socket, [220]);

        $localHost = $_SERVER['SERVER_NAME'] ?? gethostname() ?: 'localhost';

        smtp_mail_command($socket, 'EHLO ' . $localHost, [250]);

        if ($secure === 'tls') {
            smtp_mail_command($socket, 'STARTTLS', [220]);

            $tlsEnabled = stream_socket_enable_crypto(
                $socket,
                true,
                STREAM_CRYPTO_METHOD_TLS_CLIENT
            );

            if ($tlsEnabled !== true) {
                throw new RuntimeException('Unable to start TLS encryption.');
            }

            smtp_mail_command($socket, 'EHLO ' . $localHost, [250]);
        }

        smtp_mail_command($socket, 'AUTH LOGIN', [334]);
        smtp_mail_command($socket, base64_encode($username), [334]);
        smtp_mail_command($socket, base64_encode($password), [235]);

        smtp_mail_command($socket, 'MAIL FROM:<' . smtp_mail_clean_header($fromEmail) . '>', [250]);
        smtp_mail_command($socket, 'RCPT TO:<' . smtp_mail_clean_header($toEmail) . '>', [250, 251]);
        smtp_mail_command($socket, 'DATA', [354]);

        $message = smtp_mail_build_message(
            $fromEmail,
            $fromName,
            $toEmail,
            $toName,
            $subject,
            $htmlBody,
            $textBody
        );

        fwrite($socket, $message . "\r\n.\r\n");
        smtp_mail_expect($socket, [250]);

        smtp_mail_command($socket, 'QUIT', [221]);
        fclose($socket);

        return true;
    } catch (Throwable $e) {
        error_log('SMTP send failed: ' . $e->getMessage());

        if (is_resource($socket)) {
            fclose($socket);
        }

        return false;
    }
}
