<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use RuntimeException;
use Throwable;

final class ServicesController extends Controller
{
    private function db(): Database
    {
        static $db = null;

        if ($db instanceof Database) {
            return $db;
        }

        $config = $this->loadConfig();

        if (!isset($config['db']) || !is_array($config['db'])) {
            throw new RuntimeException('Database configuration is missing.');
        }

        $db = new Database($config['db']);

        return $db;
    }

    private function loadConfig(): array
    {
        $candidates = [
            dirname(__DIR__, 2) . '/app/config/config.php',
            dirname(__DIR__, 2) . '/config/config.php',
        ];

        foreach ($candidates as $configFile) {
            if (is_file($configFile)) {
                $config = require $configFile;
                if (is_array($config)) {
                    return $config;
                }
            }
        }

        throw new RuntimeException('Config file not found or invalid.');
    }

    public function serviceDetails(): void
    {
        $db = $this->db();

        $identifier = trim((string) ($_GET['service'] ?? ''));

        if ($identifier === '') {
            http_response_code(404);
            $this->view('errors/404');
            return;
        }

        $service = $this->findServiceByIdOrSlug($db, $identifier);

        if (!$service) {
            http_response_code(404);
            $this->view('errors/404');
            return;
        }

        $serviceId = (int) ($service['id'] ?? 0);

        $types = $db->fetchAll(
            'SELECT * FROM service_types WHERE service_id = :service_id ORDER BY id ASC',
            ['service_id' => $serviceId]
        );

        $requirements = $db->fetchAll(
            'SELECT * FROM service_requirements WHERE service_id = :service_id ORDER BY id ASC',
            ['service_id' => $serviceId]
        );

        $benefits = $db->fetchAll(
            'SELECT * FROM service_benefits WHERE service_id = :service_id ORDER BY id ASC',
            ['service_id' => $serviceId]
        );

        $reviews = $db->fetchAll(
            'SELECT * FROM service_reviews WHERE service_id = :service_id ORDER BY id DESC',
            ['service_id' => $serviceId]
        );

        $applyErrors = is_array($_SESSION['service_apply_errors'] ?? null)
            ? $_SESSION['service_apply_errors']
            : [];

        $applyOld = is_array($_SESSION['service_apply_old'] ?? null)
            ? $_SESSION['service_apply_old']
            : [];

        $applySuccess = trim((string) ($_SESSION['service_apply_success'] ?? ''));

        $applyToast = is_array($_SESSION['service_apply_toast'] ?? null)
            ? $_SESSION['service_apply_toast']
            : [];

        $paymentIntent = is_array($_SESSION['service_payment_intent'] ?? null)
            ? $_SESSION['service_payment_intent']
            : [];

        $autoOpenPayment = (bool) ($_SESSION['service_auto_open_payment'] ?? false);

        if (!empty($paymentIntent)) {
            $intentServiceId = (int) ($paymentIntent['service_id'] ?? 0);
            $intentServiceSlug = trim((string) ($paymentIntent['service_slug'] ?? ''));

            $matchesCurrentService = $intentServiceId === $serviceId
                || ($intentServiceSlug !== '' && $intentServiceSlug === (string) ($service['slug'] ?? ''));

            if (!$matchesCurrentService) {
                $paymentIntent = [];
                $autoOpenPayment = false;
            }
        }

        unset(
            $_SESSION['service_apply_errors'],
            $_SESSION['service_apply_old'],
            $_SESSION['service_apply_success'],
            $_SESSION['service_apply_toast'],
            $_SESSION['service_auto_open_payment']
        );

        $applyDefaults = [
            'full_name' => '',
            'email'     => '',
            'mobile'    => '',
            'state'     => '',
        ];

        $states = $this->indianStates();

        $this->view('/home/service-details', compact(
            'service',
            'types',
            'requirements',
            'benefits',
            'reviews',
            'states',
            'applyErrors',
            'applyOld',
            'applyDefaults',
            'applySuccess',
            'applyToast',
            'paymentIntent',
            'autoOpenPayment'
        ));
    }

    public function submitServiceApplication(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            exit('Method Not Allowed');
        }

        if (function_exists('verify_csrf')) {
            verify_csrf();
        }

        $db = $this->db();

        $serviceId = (int) ($_POST['service_id'] ?? 0);
        $serviceSlug = trim((string) ($_POST['service_slug'] ?? ''));
        $returnUrl = trim((string) ($_POST['return_url'] ?? ''));
        $honeypot = trim((string) ($_POST['website'] ?? ''));

        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $mobile = preg_replace('/\D+/', '', (string) ($_POST['mobile'] ?? ''));
        $state = trim((string) ($_POST['state'] ?? ''));

        $old = [
            'full_name' => $fullName,
            'email'     => $email,
            'mobile'    => $mobile,
            'state'     => $state,
        ];

        $errors = [];

        if ($honeypot !== '') {
            $this->redirectBack($returnUrl !== '' ? $returnUrl : base_url('services'));
        }

        $service = null;

        if ($serviceId > 0) {
            $service = $this->findServiceByIdOrSlug($db, (string) $serviceId);
        }

        if (!$service && $serviceSlug !== '') {
            $service = $this->findServiceByIdOrSlug($db, $serviceSlug);
        }

        if (!$service) {
            $errors['general'] = 'Selected service was not found.';
        }

        if ($fullName === '' || mb_strlen($fullName) < 2) {
            $errors['full_name'] = 'Please enter a valid name.';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        }

        if (!preg_match('/^[6-9][0-9]{9}$/', $mobile)) {
            $errors['mobile'] = 'Enter a valid 10-digit mobile number without 0 or +91.';
        }

        if (!in_array($state, $this->indianStates(), true)) {
            $errors['state'] = 'Please select a valid state.';
        }

        if (!empty($errors)) {
            $_SESSION['service_apply_errors'] = $errors;
            $_SESSION['service_apply_old'] = $old;
            $_SESSION['service_apply_toast'] = [
                'icon'  => 'error',
                'title' => 'Please correct the form and try again.',
            ];
            $this->redirectBack($returnUrl !== '' ? $returnUrl : base_url('services'));
        }

        $serviceReturnUrl = $this->serviceDetailsUrl($service);
        $serviceFee = (float) ($service['filing_fee'] ?? 0);
        $amountPaise = (int) round(max(0, $serviceFee) * 100);
        $initialStatus = $amountPaise > 0 ? 'pending_payment' : 'new';

        try {
            $db->execute(
                'INSERT INTO service_applications
                (
                    service_id,
                    service_slug,
                    service_title,
                    full_name,
                    email,
                    mobile,
                    state_name,
                    ip_address,
                    user_agent,
                    status,
                    admin_email_sent,
                    user_email_sent,
                    created_at,
                    updated_at
                )
                VALUES
                (
                    :service_id,
                    :service_slug,
                    :service_title,
                    :full_name,
                    :email,
                    :mobile,
                    :state_name,
                    :ip_address,
                    :user_agent,
                    :status,
                    :admin_email_sent,
                    :user_email_sent,
                    NOW(),
                    NOW()
                )',
                [
                    'service_id'       => (int) ($service['id'] ?? 0),
                    'service_slug'     => (string) ($service['slug'] ?? ''),
                    'service_title'    => (string) ($service['title'] ?? ''),
                    'full_name'        => $fullName,
                    'email'            => $email,
                    'mobile'           => $mobile,
                    'state_name'       => $state,
                    'ip_address'       => $this->clientIp(),
                    'user_agent'       => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
                    'status'           => $initialStatus,
                    'admin_email_sent' => 0,
                    'user_email_sent'  => 0,
                ]
            );

            $applicationId = (int) $db->lastInsertId();

            $userEmailSent = false;
            $adminEmailSent = false;

            try {
                $config = $this->loadConfig();

                $safeName = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');
                $safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
                $safeMobile = htmlspecialchars($mobile, ENT_QUOTES, 'UTF-8');
                $safeState = htmlspecialchars($state, ENT_QUOTES, 'UTF-8');
                $safeService = htmlspecialchars((string) ($service['title'] ?? 'Service'), ENT_QUOTES, 'UTF-8');

                $appName = (string) ($config['app']['name'] ?? 'Tax Saathi');
                $adminEmail = (string) ($config['mail']['admin_email'] ?? 'admin@taxsaathi.in');

                if (function_exists('send_smtp_mail')) {
                    $userSubject = 'Thank you for applying for ' . (string) ($service['title'] ?? 'our service');
                    $userHtml = '
                        <div style="font-family:Arial,sans-serif;font-size:15px;line-height:1.7;color:#111827;">
                            <p>Dear ' . $safeName . ',</p>
                            <p>Thank you for applying for <strong>' . $safeService . '</strong>.</p>
                            <p>We have received your request successfully.</p>
                            <p>Please complete your payment to continue the process.</p>
                            <p><strong>Submitted details:</strong><br>
                            Name: ' . $safeName . '<br>
                            Email: ' . $safeEmail . '<br>
                            Mobile: ' . $safeMobile . '<br>
                            State: ' . $safeState . '</p>
                            <p>Regards,<br>' . htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') . '</p>
                        </div>
                    ';
                    $userText = "Dear {$fullName},\n\nThank you for applying for " . (string) ($service['title'] ?? 'our service') . ".\nPlease complete your payment to continue the process.\n\nRegards,\n{$appName}";

                    $userEmailSent = send_smtp_mail($email, $fullName, $userSubject, $userHtml, $userText);

                    $adminSubject = 'New Service Application #' . $applicationId . ' - ' . (string) ($service['title'] ?? 'Service');
                    $adminHtml = '
                        <div style="font-family:Arial,sans-serif;font-size:15px;line-height:1.7;color:#111827;">
                            <h2 style="margin:0 0 12px;">New Service Application Received</h2>
                            <p><strong>Application ID:</strong> ' . $applicationId . '</p>
                            <p><strong>Service:</strong> ' . $safeService . '</p>
                            <p><strong>Name:</strong> ' . $safeName . '</p>
                            <p><strong>Email:</strong> ' . $safeEmail . '</p>
                            <p><strong>Mobile:</strong> ' . $safeMobile . '</p>
                            <p><strong>State:</strong> ' . $safeState . '</p>
                            <p><strong>Status:</strong> ' . htmlspecialchars($initialStatus, ENT_QUOTES, 'UTF-8') . '</p>
                        </div>
                    ';
                    $adminText = "New Service Application Received\n\nApplication ID: {$applicationId}\nService: " . (string) ($service['title'] ?? 'Service') . "\nName: {$fullName}\nEmail: {$email}\nMobile: {$mobile}\nState: {$state}\nStatus: {$initialStatus}";

                    $adminEmailSent = send_smtp_mail($adminEmail, 'Admin', $adminSubject, $adminHtml, $adminText);
                }
            } catch (Throwable $e) {
                error_log('Service application email error: ' . $e->getMessage());
            }

            $db->execute(
                'UPDATE service_applications
                 SET admin_email_sent = :admin_email_sent,
                     user_email_sent = :user_email_sent,
                     updated_at = NOW()
                 WHERE id = :id',
                [
                    'admin_email_sent' => $adminEmailSent ? 1 : 0,
                    'user_email_sent'  => $userEmailSent ? 1 : 0,
                    'id'               => $applicationId,
                ]
            );

            $_SESSION['service_apply_success'] = $amountPaise > 0
                ? 'Application submitted successfully. Please complete your payment below.'
                : 'Application submitted successfully. Thank you.';

            $_SESSION['service_apply_toast'] = [
                'icon'  => 'success',
                'title' => $amountPaise > 0
                    ? 'Application saved. Opening payment.'
                    : 'Application submitted successfully.',
            ];

            if ($amountPaise > 0) {
                $_SESSION['service_payment_intent'] = $this->buildPaymentIntent(
                    [
                        'id'            => $applicationId,
                        'service_id'    => (int) ($service['id'] ?? 0),
                        'service_slug'  => (string) ($service['slug'] ?? ''),
                        'service_title' => (string) ($service['title'] ?? ''),
                        'full_name'     => $fullName,
                        'email'         => $email,
                        'mobile'        => $mobile,
                        'state_name'    => $state,
                    ],
                    $service
                );

                $_SESSION['service_auto_open_payment'] = true;

                $this->redirectBack($this->withFragment($serviceReturnUrl, 'payment-section'));
            }

            unset($_SESSION['service_payment_intent'], $_SESSION['service_auto_open_payment']);

            $this->redirectBack($this->withFragment($serviceReturnUrl, 'apply-now'));
        } catch (Throwable $e) {
            error_log('Service application insert error: ' . $e->getMessage());

            $_SESSION['service_apply_errors'] = [
                'general' => 'Unable to submit your application right now. Please try again.',
            ];
            $_SESSION['service_apply_old'] = $old;
            $_SESSION['service_apply_toast'] = [
                'icon'  => 'error',
                'title' => 'Unable to submit application right now.',
            ];

            $this->redirectBack($returnUrl !== '' ? $returnUrl : base_url('services'));
        }
    }

    public function createApplicationPaymentOrder(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['success' => false, 'message' => 'Method Not Allowed'], 405);
        }

        if (function_exists('verify_csrf')) {
            verify_csrf();
        }

        try {
            $db = $this->db();
            $config = $this->loadConfig();

            $applicationId = (int) ($_POST['application_id'] ?? 0);

            if ($applicationId <= 0) {
                $this->json(['success' => false, 'message' => 'Invalid application.'], 422);
            }

            $application = $this->findApplicationById($db, $applicationId);

            if (!$application) {
                $this->json(['success' => false, 'message' => 'Application not found.'], 404);
            }

            $service = $this->findServiceByIdOrSlug($db, (string) ($application['service_id'] ?? 0));

            if (!$service) {
                $this->json(['success' => false, 'message' => 'Service not found.'], 404);
            }

            $amount = (float) ($service['filing_fee'] ?? 0);
            $amountPaise = (int) round(max(0, $amount) * 100);

            if ($amountPaise < 100) {
                $this->json(['success' => false, 'message' => 'Payment amount must be at least ₹1.'], 422);
            }

            [$keyId, $keySecret] = $this->razorpayCredentials($config);

            $order = $this->createRazorpayOrder(
                $keyId,
                $keySecret,
                $amountPaise,
                'INR',
                'svcapp_' . $applicationId . '_' . time(),
                [
                    'application_id' => (string) $applicationId,
                    'service_title'  => (string) ($service['title'] ?? 'Service'),
                    'service_slug'   => (string) ($application['service_slug'] ?? ''),
                ]
            );

            $db->execute(
                'UPDATE service_applications
                 SET status = :status,
                     updated_at = NOW()
                 WHERE id = :id',
                [
                    'status' => 'payment_initiated',
                    'id'     => $applicationId,
                ]
            );

            $_SESSION['service_payment_intent'] = $this->buildPaymentIntent($application, $service);

            $this->json([
                'success'        => true,
                'application_id' => $applicationId,
                'key'            => $keyId,
                'order_id'       => (string) ($order['id'] ?? ''),
                'amount'         => $amountPaise,
                'currency'       => 'INR',
                'name'           => (string) ($config['app']['name'] ?? 'Tax Saathi'),
                'description'    => 'Payment for ' . (string) ($service['title'] ?? 'Service'),
                'prefill'        => [
                    'name'    => (string) ($application['full_name'] ?? ''),
                    'email'   => (string) ($application['email'] ?? ''),
                    'contact' => (string) ($application['mobile'] ?? ''),
                ],
                'notes'          => [
                    'application_id' => (string) $applicationId,
                    'service_title'  => (string) ($service['title'] ?? 'Service'),
                ],
            ]);
        } catch (Throwable $e) {
            error_log('Razorpay order creation error: ' . $e->getMessage());

            $this->json([
                'success' => false,
                'message' => 'Unable to start payment right now. Please try again.',
            ], 500);
        }
    }

    public function verifyApplicationPayment(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            exit('Method Not Allowed');
        }

        if (function_exists('verify_csrf')) {
            verify_csrf();
        }

        $applicationId = (int) ($_POST['application_id'] ?? 0);
        $paymentId = trim((string) ($_POST['razorpay_payment_id'] ?? ''));
        $orderId = trim((string) ($_POST['razorpay_order_id'] ?? ''));
        $signature = trim((string) ($_POST['razorpay_signature'] ?? ''));

        $redirectUrl = base_url('services');

        try {
            $db = $this->db();
            $config = $this->loadConfig();

            $application = $this->findApplicationById($db, $applicationId);

            if (!$application || $paymentId === '' || $orderId === '' || $signature === '') {
                throw new RuntimeException('Invalid payment verification payload.');
            }

            $redirectUrl = $this->withFragment(
                $this->serviceDetailsUrlFromApplication($application),
                'payment-section'
            );

            [, $keySecret] = $this->razorpayCredentials($config);

            $expectedSignature = hash_hmac('sha256', $orderId . '|' . $paymentId, $keySecret);

            if (!hash_equals($expectedSignature, $signature)) {
                throw new RuntimeException('Razorpay signature verification failed.');
            }

            $db->execute(
                'UPDATE service_applications
                 SET status = :status,
                     updated_at = NOW()
                 WHERE id = :id',
                [
                    'status' => 'paid',
                    'id'     => $applicationId,
                ]
            );

            unset($_SESSION['service_payment_intent'], $_SESSION['service_auto_open_payment']);

            $_SESSION['service_apply_success'] = 'Payment received successfully. Your application is now confirmed.';
            $_SESSION['service_apply_toast'] = [
                'icon'  => 'success',
                'title' => 'Payment successful.',
            ];

            $this->redirectBack($redirectUrl);
        } catch (Throwable $e) {
            error_log('Razorpay verification error: ' . $e->getMessage());

            $_SESSION['service_apply_errors'] = [
                'general' => 'Payment verification failed. Please try the payment again.',
            ];
            $_SESSION['service_apply_toast'] = [
                'icon'  => 'error',
                'title' => 'Payment verification failed.',
            ];
            $_SESSION['service_auto_open_payment'] = false;

            try {
                $db = $this->db();
                $application = $this->findApplicationById($db, $applicationId);

                if ($application) {
                    $service = $this->findServiceByIdOrSlug($db, (string) ($application['service_id'] ?? 0));
                    if ($service) {
                        $_SESSION['service_payment_intent'] = $this->buildPaymentIntent($application, $service);
                        $redirectUrl = $this->withFragment(
                            $this->serviceDetailsUrlFromApplication($application),
                            'payment-section'
                        );
                    }
                }
            } catch (Throwable $inner) {
                error_log('Service payment recovery error: ' . $inner->getMessage());
            }

            $this->redirectBack($redirectUrl);
        }
    }

    private function createRazorpayOrder(
        string $keyId,
        string $keySecret,
        int $amount,
        string $currency,
        string $receipt,
        array $notes = []
    ): array {
        $payload = json_encode([
            'amount'          => $amount,
            'currency'        => $currency,
            'receipt'         => $receipt,
            'payment_capture' => 1,
            'notes'           => $notes,
        ], JSON_UNESCAPED_SLASHES);

        if (!is_string($payload)) {
            throw new RuntimeException('Failed to encode Razorpay payload.');
        }

        $ch = curl_init('https://api.razorpay.com/v1/orders');

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_USERPWD        => $keyId . ':' . $keySecret,
            CURLOPT_TIMEOUT        => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        curl_close($ch);

        if (!is_string($response) || $response === '') {
            throw new RuntimeException('Empty Razorpay response. ' . $curlError);
        }

        $decoded = json_decode($response, true);

        if (!is_array($decoded) || $httpCode >= 400 || empty($decoded['id'])) {
            $message = is_array($decoded) && isset($decoded['error']['description'])
                ? (string) $decoded['error']['description']
                : 'Unable to create Razorpay order.';

            throw new RuntimeException($message);
        }

        return $decoded;
    }

    private function razorpayCredentials(array $config): array
    {
        $keyId = trim((string) (
            $config['payment']['razorpay']['key_id']
            ?? $config['payment']['razorpay_key_id']
            ?? $config['razorpay']['key_id']
            ?? $config['razorpay_key_id']
            ?? ''
        ));

        $keySecret = trim((string) (
            $config['payment']['razorpay']['key_secret']
            ?? $config['payment']['razorpay_key_secret']
            ?? $config['razorpay']['key_secret']
            ?? $config['razorpay_key_secret']
            ?? ''
        ));

        if ($keyId === '' || $keySecret === '') {
            throw new RuntimeException('Razorpay credentials are missing in config.');
        }

        return [$keyId, $keySecret];
    }

    private function buildPaymentIntent(array $application, array $service): array
    {
        $amount = (float) ($service['filing_fee'] ?? 0);

        return [
            'application_id' => (int) ($application['id'] ?? 0),
            'service_id'     => (int) ($application['service_id'] ?? ($service['id'] ?? 0)),
            'service_slug'   => (string) ($application['service_slug'] ?? ($service['slug'] ?? '')),
            'service_title'  => (string) ($application['service_title'] ?? ($service['title'] ?? 'Service')),
            'full_name'      => (string) ($application['full_name'] ?? ''),
            'email'          => (string) ($application['email'] ?? ''),
            'mobile'         => (string) ($application['mobile'] ?? ''),
            'state_name'     => (string) ($application['state_name'] ?? ''),
            'amount'         => $amount,
            'amount_paise'   => (int) round(max(0, $amount) * 100),
            'currency'       => 'INR',
        ];
    }

    private function findApplicationById(Database $db, int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        return $db->fetch(
            'SELECT * FROM service_applications WHERE id = :id LIMIT 1',
            ['id' => $id]
        );
    }

    private function findServiceByIdOrSlug(Database $db, string $identifier): ?array
    {
        if ($identifier === '') {
            return null;
        }

        if (ctype_digit($identifier)) {
            return $db->fetch(
                'SELECT * FROM services WHERE id = :id LIMIT 1',
                ['id' => (int) $identifier]
            );
        }

        return $db->fetch(
            'SELECT * FROM services WHERE slug = :slug LIMIT 1',
            ['slug' => $identifier]
        );
    }

    private function serviceDetailsUrl(array $service): string
    {
        $key = trim((string) ($service['slug'] ?? ''));

        if ($key === '') {
            $key = (string) ((int) ($service['id'] ?? 0));
        }

        return base_url('service-details?service=' . urlencode($key));
    }

    private function serviceDetailsUrlFromApplication(array $application): string
    {
        $key = trim((string) ($application['service_slug'] ?? ''));

        if ($key === '') {
            $key = (string) ((int) ($application['service_id'] ?? 0));
        }

        return base_url('service-details?service=' . urlencode($key));
    }

    private function withFragment(string $url, string $fragment): string
    {
        $url = preg_replace('/#.*$/', '', $url) ?? $url;
        return rtrim($url, '#') . '#' . ltrim($fragment, '#');
    }

    private function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function indianStates(): array
    {
        return [
            'Andaman and Nicobar Islands',
            'Andhra Pradesh',
            'Arunachal Pradesh',
            'Assam',
            'Bihar',
            'Chandigarh',
            'Chhattisgarh',
            'Dadra and Nagar Haveli and Daman and Diu',
            'Delhi',
            'Goa',
            'Gujarat',
            'Haryana',
            'Himachal Pradesh',
            'Jammu and Kashmir',
            'Jharkhand',
            'Karnataka',
            'Kerala',
            'Ladakh',
            'Lakshadweep',
            'Madhya Pradesh',
            'Maharashtra',
            'Manipur',
            'Meghalaya',
            'Mizoram',
            'Nagaland',
            'Odisha',
            'Puducherry',
            'Punjab',
            'Rajasthan',
            'Sikkim',
            'Tamil Nadu',
            'Telangana',
            'Tripura',
            'Uttar Pradesh',
            'Uttarakhand',
            'West Bengal',
        ];
    }

    private function clientIp(): ?string
    {
        $keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];

        foreach ($keys as $key) {
            $value = trim((string) ($_SERVER[$key] ?? ''));

            if ($value === '') {
                continue;
            }

            if ($key === 'HTTP_X_FORWARDED_FOR') {
                $parts = explode(',', $value);
                $value = trim((string) ($parts[0] ?? ''));
            }

            return substr($value, 0, 45);
        }

        return null;
    }

    private function redirectBack(string $returnUrl): void
    {
        $target = $returnUrl !== '' ? $returnUrl : base_url('services');
        header('Location: ' . $target);
        exit;
    }
}