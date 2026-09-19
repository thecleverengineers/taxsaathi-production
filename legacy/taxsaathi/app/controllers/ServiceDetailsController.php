<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use RuntimeException;
use Throwable;

final class ServiceDetailsController extends Controller
{
    private function db(): Database
    {
        static $db = null;

        if ($db instanceof Database) {
            return $db;
        }

        $dbConfig = config('db', []);

        if (!is_array($dbConfig) || $dbConfig === []) {
            throw new RuntimeException('Database configuration is missing.');
        }

        $db = new Database($dbConfig);

        return $db;
    }

    public function show(): void
    {
        $db = $this->db();

        $identifier = trim((string) ($_GET['service'] ?? ''));

        if ($identifier === '') {
            http_response_code(404);
            $this->view('public/404', ['title' => 'Service not found'], 'layouts/blank');
            return;
        }

        $service = $this->findServiceByIdOrSlug($db, $identifier);

        if (!$service) {
            http_response_code(404);
            $this->view('public/404', ['title' => 'Service not found'], 'layouts/blank');
            return;
        }

        $serviceId = (int) ($service['id'] ?? 0);

        $serviceBanner = $db->fetch(
            'SELECT *
             FROM service_banners
             WHERE service_id = :service_id
               AND is_active = 1
             ORDER BY sort_order ASC, id DESC
             LIMIT 1',
            ['service_id' => $serviceId]
        ) ?: [];

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

        $applyDefaults = is_array($_SESSION['service_apply_defaults'] ?? null)
            ? $_SESSION['service_apply_defaults']
            : [];

        $applySuccess = trim((string) ($_SESSION['service_apply_success'] ?? ''));

        unset(
            $_SESSION['service_apply_errors'],
            $_SESSION['service_apply_old'],
            $_SESSION['service_apply_defaults'],
            $_SESSION['service_apply_success']
        );

        if ($applyDefaults === []) {
            $applyDefaults = [
                'full_name' => '',
                'email'     => '',
                'mobile'    => '',
                'state'     => '',
            ];
        }

        $states = $this->indianStates();

        $this->view('public/service-details', compact(
            'service',
            'serviceBanner',
            'types',
            'requirements',
            'benefits',
            'reviews',
            'states',
            'applyErrors',
            'applyOld',
            'applyDefaults',
            'applySuccess'
        ));
    }

    public function submit(): void
    {
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            http_response_code(405);
            exit('Method Not Allowed');
        }

        verify_csrf();

        $db = $this->db();

        $serviceId = (int) ($_POST['service_id'] ?? 0);
        $serviceSlug = trim((string) ($_POST['service_slug'] ?? ''));
        $serviceTitlePosted = trim((string) ($_POST['service_title'] ?? ''));
        $returnUrl = trim((string) ($_POST['return_url'] ?? ''));
        $redirectAfter = trim((string) ($_POST['redirect_after'] ?? 'payment-method'));
        $nextStep = trim((string) ($_POST['next_step'] ?? 'payment'));
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

        $_SESSION['service_apply_old'] = $old;
        $_SESSION['service_apply_defaults'] = $old;

        if ($honeypot !== '') {
            $this->redirectBack($returnUrl);
        }

        $service = null;

        if ($serviceId > 0) {
            $service = $this->findServiceByIdOrSlug($db, (string) $serviceId);
        }

        if (!$service && $serviceSlug !== '') {
            $service = $this->findServiceByIdOrSlug($db, $serviceSlug);
        }

        $errors = $this->validateApplicationInput($service, $fullName, $email, $mobile, $state);

        if ($errors !== []) {
            $_SESSION['service_apply_errors'] = $errors;
            $this->redirectBack($returnUrl);
        }

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
                    status,
                    admin_email_sent,
                    user_email_sent,
                    ip_address,
                    user_agent,
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
                    :status,
                    :admin_email_sent,
                    :user_email_sent,
                    :ip_address,
                    :user_agent,
                    NOW(),
                    NOW()
                )',
                [
                    'service_id'       => (int) ($service['id'] ?? 0),
                    'service_slug'     => (string) ($service['slug'] ?? ''),
                    'service_title'    => (string) ($service['title'] ?? $serviceTitlePosted),
                    'full_name'        => $fullName,
                    'email'            => $email,
                    'mobile'           => $mobile,
                    'state_name'       => $state,
                    'status'           => 'new',
                    'admin_email_sent' => 0,
                    'user_email_sent'  => 0,
                    'ip_address'       => $this->clientIp(),
                    'user_agent'       => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
                ]
            );

            $applicationId = (int) $db->lastInsertId();

            [$userEmailSent, $adminEmailSent] = $this->sendApplicationEmails(
                $applicationId,
                $service ?? [],
                $fullName,
                $email,
                $mobile,
                $state
            );

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

            unset(
                $_SESSION['service_apply_errors'],
                $_SESSION['service_apply_old'],
                $_SESSION['service_apply_defaults']
            );

            $_SESSION['service_apply_success'] = 'Application submitted successfully.';

            $this->redirectToPayment(
                $redirectAfter,
                $applicationId,
                (string) ($service['slug'] ?? ''),
                $nextStep
            );
        } catch (Throwable $e) {
            error_log('Service application error: ' . $e->getMessage());

            $_SESSION['service_apply_errors'] = [
                'general' => 'Unable to submit your application right now. Please try again.',
            ];

            $this->redirectBack($returnUrl);
        }
    }

    private function validateApplicationInput(
        ?array $service,
        string $fullName,
        string $email,
        string $mobile,
        string $state
    ): array {
        $errors = [];

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

        return $errors;
    }

 private function sendApplicationEmails(
    int $applicationId,
    array $service,
    string $fullName,
    string $email,
    string $mobile,
    string $state
): array {
    $serviceTitle = trim((string) ($service['title'] ?? 'Service'));
    $appName = trim((string) config('app.name', 'Tax Saathi'));
    $appUrl = rtrim((string) config('app.url', ''), '/');

    $adminEmail = trim((string) config('mail.admin_email', 'thecleverengineers@gmail.com'));
    $adminName = trim((string) config('mail.admin_name', 'The Clever Engineers'));

    $safeName = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');
    $safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
    $safeMobile = htmlspecialchars($mobile, ENT_QUOTES, 'UTF-8');
    $safeState = htmlspecialchars($state, ENT_QUOTES, 'UTF-8');
    $safeService = htmlspecialchars($serviceTitle, ENT_QUOTES, 'UTF-8');
    $safeAppName = htmlspecialchars($appName, ENT_QUOTES, 'UTF-8');
    $safeAppUrl = htmlspecialchars($appUrl, ENT_QUOTES, 'UTF-8');

    $userEmailSent = false;
    $adminEmailSent = false;

    if (!function_exists('send_smtp_mail')) {
        error_log('send_smtp_mail() helper not found.');
        return [$userEmailSent, $adminEmailSent];
    }

    $emailShellStart = '
        <div style="margin:0;padding:0;background-color:#f3f6fb;">
            <div style="max-width:680px;margin:0 auto;padding:32px 16px;">
                <div style="background:#ffffff;border:1px solid #e5e7eb;border-radius:18px;overflow:hidden;box-shadow:0 10px 30px rgba(15,23,42,0.06);">
    ';

    $emailShellEnd = '
                </div>
                <div style="padding:16px 8px 0 8px;text-align:center;font-family:Arial,sans-serif;font-size:12px;line-height:1.7;color:#94a3b8;">
                    This is an automated email from ' . $safeAppName . '.
                </div>
            </div>
        </div>
    ';

    try {
        $userSubject = 'Application Received - ' . $serviceTitle;

        $userHtml = $emailShellStart . '
            <div style="background:linear-gradient(135deg,#0f172a 0%,#1e3a8a 100%);padding:28px 32px;">
                <div style="font-family:Arial,sans-serif;font-size:12px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:#cbd5e1;">
                    ' . $safeAppName . '
                </div>
                <div style="margin-top:10px;font-family:Arial,sans-serif;font-size:28px;font-weight:700;line-height:1.3;color:#ffffff;">
                    Application Received
                </div>
                <div style="margin-top:8px;font-family:Arial,sans-serif;font-size:14px;line-height:1.7;color:#dbeafe;">
                    Thank you for choosing our service.
                </div>
            </div>

            <div style="padding:32px;">
                <p style="margin:0 0 16px 0;font-family:Arial,sans-serif;font-size:15px;line-height:1.8;color:#334155;">
                    Dear <strong>' . $safeName . '</strong>,
                </p>

                <p style="margin:0 0 16px 0;font-family:Arial,sans-serif;font-size:15px;line-height:1.8;color:#334155;">
                    We have successfully received your application for <strong>' . $safeService . '</strong>.
                    Our team will review your request and get in touch with you shortly.
                </p>

                <div style="margin:24px 0;border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;">
                    <div style="background:#f8fafc;padding:14px 18px;font-family:Arial,sans-serif;font-size:14px;font-weight:700;color:#0f172a;border-bottom:1px solid #e2e8f0;">
                        Submitted Details
                    </div>

                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
                        <tr>
                            <td style="padding:14px 18px;font-family:Arial,sans-serif;font-size:14px;color:#64748b;width:180px;border-bottom:1px solid #f1f5f9;">Application ID</td>
                            <td style="padding:14px 18px;font-family:Arial,sans-serif;font-size:14px;color:#0f172a;font-weight:600;border-bottom:1px solid #f1f5f9;">#' . $applicationId . '</td>
                        </tr>
                        <tr>
                            <td style="padding:14px 18px;font-family:Arial,sans-serif;font-size:14px;color:#64748b;width:180px;border-bottom:1px solid #f1f5f9;">Service</td>
                            <td style="padding:14px 18px;font-family:Arial,sans-serif;font-size:14px;color:#0f172a;font-weight:600;border-bottom:1px solid #f1f5f9;">' . $safeService . '</td>
                        </tr>
                        <tr>
                            <td style="padding:14px 18px;font-family:Arial,sans-serif;font-size:14px;color:#64748b;width:180px;border-bottom:1px solid #f1f5f9;">Full Name</td>
                            <td style="padding:14px 18px;font-family:Arial,sans-serif;font-size:14px;color:#0f172a;font-weight:600;border-bottom:1px solid #f1f5f9;">' . $safeName . '</td>
                        </tr>
                        <tr>
                            <td style="padding:14px 18px;font-family:Arial,sans-serif;font-size:14px;color:#64748b;width:180px;border-bottom:1px solid #f1f5f9;">Email</td>
                            <td style="padding:14px 18px;font-family:Arial,sans-serif;font-size:14px;color:#0f172a;font-weight:600;border-bottom:1px solid #f1f5f9;">' . $safeEmail . '</td>
                        </tr>
                        <tr>
                            <td style="padding:14px 18px;font-family:Arial,sans-serif;font-size:14px;color:#64748b;width:180px;border-bottom:1px solid #f1f5f9;">Mobile</td>
                            <td style="padding:14px 18px;font-family:Arial,sans-serif;font-size:14px;color:#0f172a;font-weight:600;border-bottom:1px solid #f1f5f9;">' . $safeMobile . '</td>
                        </tr>
                        <tr>
                            <td style="padding:14px 18px;font-family:Arial,sans-serif;font-size:14px;color:#64748b;width:180px;">State</td>
                            <td style="padding:14px 18px;font-family:Arial,sans-serif;font-size:14px;color:#0f172a;font-weight:600;">' . $safeState . '</td>
                        </tr>
                    </table>
                </div>

                <p style="margin:0 0 18px 0;font-family:Arial,sans-serif;font-size:14px;line-height:1.8;color:#475569;">
                    Please keep this application ID handy for future communication.
                </p>

                ' . ($appUrl !== '' ? '
                <div style="margin-top:28px;">
                    <a href="' . $safeAppUrl . '" style="display:inline-block;background:#0f172a;color:#ffffff;text-decoration:none;font-family:Arial,sans-serif;font-size:14px;font-weight:700;padding:12px 22px;border-radius:10px;">
                        Visit Website
                    </a>
                </div>' : '') . '

                <div style="margin-top:30px;padding-top:22px;border-top:1px solid #e2e8f0;">
                    <p style="margin:0;font-family:Arial,sans-serif;font-size:14px;line-height:1.8;color:#475569;">
                        Regards,<br>
                        <strong style="color:#0f172a;">' . $safeAppName . ' Team</strong>
                    </p>
                </div>
            </div>
        ' . $emailShellEnd;

        $userText = "Dear {$fullName},\n\n"
            . "We have successfully received your application for {$serviceTitle}.\n\n"
            . "Application ID: #{$applicationId}\n"
            . "Service: {$serviceTitle}\n"
            . "Full Name: {$fullName}\n"
            . "Email: {$email}\n"
            . "Mobile: {$mobile}\n"
            . "State: {$state}\n\n"
            . "Our team will contact you shortly.\n\n"
            . "Regards,\n{$appName} Team";

        $userEmailSent = send_smtp_mail(
            $email,
            $fullName,
            $userSubject,
            $userHtml,
            $userText
        );
    } catch (Throwable $e) {
        error_log('User email send failed: ' . $e->getMessage());
    }

    try {
        $adminSubject = 'New Service Application Received - #' . $applicationId . ' - ' . $serviceTitle;

        $adminHtml = $emailShellStart . '
            <div style="background:linear-gradient(135deg,#111827 0%,#1d4ed8 100%);padding:28px 32px;">
                <div style="font-family:Arial,sans-serif;font-size:12px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:#cbd5e1;">
                    New Application Alert
                </div>
                <div style="margin-top:10px;font-family:Arial,sans-serif;font-size:28px;font-weight:700;line-height:1.3;color:#ffffff;">
                    Service Application Submitted
                </div>
                <div style="margin-top:8px;font-family:Arial,sans-serif;font-size:14px;line-height:1.7;color:#dbeafe;">
                    A new enquiry/application has been received through the website.
                </div>
            </div>

            <div style="padding:32px;">
                <p style="margin:0 0 18px 0;font-family:Arial,sans-serif;font-size:15px;line-height:1.8;color:#334155;">
                    Hello,
                </p>

                <p style="margin:0 0 20px 0;font-family:Arial,sans-serif;font-size:15px;line-height:1.8;color:#334155;">
                    A new service application has been submitted. The applicant details are provided below.
                </p>

                <div style="margin:24px 0;border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;">
                    <div style="background:#f8fafc;padding:14px 18px;font-family:Arial,sans-serif;font-size:14px;font-weight:700;color:#0f172a;border-bottom:1px solid #e2e8f0;">
                        Application Information
                    </div>

                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
                        <tr>
                            <td style="padding:14px 18px;font-family:Arial,sans-serif;font-size:14px;color:#64748b;width:180px;border-bottom:1px solid #f1f5f9;">Application ID</td>
                            <td style="padding:14px 18px;font-family:Arial,sans-serif;font-size:14px;color:#0f172a;font-weight:600;border-bottom:1px solid #f1f5f9;">#' . $applicationId . '</td>
                        </tr>
                        <tr>
                            <td style="padding:14px 18px;font-family:Arial,sans-serif;font-size:14px;color:#64748b;width:180px;border-bottom:1px solid #f1f5f9;">Service</td>
                            <td style="padding:14px 18px;font-family:Arial,sans-serif;font-size:14px;color:#0f172a;font-weight:600;border-bottom:1px solid #f1f5f9;">' . $safeService . '</td>
                        </tr>
                        <tr>
                            <td style="padding:14px 18px;font-family:Arial,sans-serif;font-size:14px;color:#64748b;width:180px;border-bottom:1px solid #f1f5f9;">Applicant Name</td>
                            <td style="padding:14px 18px;font-family:Arial,sans-serif;font-size:14px;color:#0f172a;font-weight:600;border-bottom:1px solid #f1f5f9;">' . $safeName . '</td>
                        </tr>
                        <tr>
                            <td style="padding:14px 18px;font-family:Arial,sans-serif;font-size:14px;color:#64748b;width:180px;border-bottom:1px solid #f1f5f9;">Email Address</td>
                            <td style="padding:14px 18px;font-family:Arial,sans-serif;font-size:14px;color:#0f172a;font-weight:600;border-bottom:1px solid #f1f5f9;">' . $safeEmail . '</td>
                        </tr>
                        <tr>
                            <td style="padding:14px 18px;font-family:Arial,sans-serif;font-size:14px;color:#64748b;width:180px;border-bottom:1px solid #f1f5f9;">Mobile Number</td>
                            <td style="padding:14px 18px;font-family:Arial,sans-serif;font-size:14px;color:#0f172a;font-weight:600;border-bottom:1px solid #f1f5f9;">' . $safeMobile . '</td>
                        </tr>
                        <tr>
                            <td style="padding:14px 18px;font-family:Arial,sans-serif;font-size:14px;color:#64748b;width:180px;">State</td>
                            <td style="padding:14px 18px;font-family:Arial,sans-serif;font-size:14px;color:#0f172a;font-weight:600;">' . $safeState . '</td>
                        </tr>
                    </table>
                </div>

                <div style="margin-top:30px;padding:18px 20px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:12px;">
                    <p style="margin:0;font-family:Arial,sans-serif;font-size:13px;line-height:1.8;color:#1e3a8a;">
                        Recommended action: contact the applicant promptly and update the application status in the admin panel.
                    </p>
                </div>

                <div style="margin-top:30px;padding-top:22px;border-top:1px solid #e2e8f0;">
                    <p style="margin:0;font-family:Arial,sans-serif;font-size:14px;line-height:1.8;color:#475569;">
                        Sent from <strong style="color:#0f172a;">' . $safeAppName . '</strong>
                    </p>
                </div>
            </div>
        ' . $emailShellEnd;

        $adminText = "New Service Application Received\n\n"
            . "Application ID: #{$applicationId}\n"
            . "Service: {$serviceTitle}\n"
            . "Applicant Name: {$fullName}\n"
            . "Email Address: {$email}\n"
            . "Mobile Number: {$mobile}\n"
            . "State: {$state}\n\n"
            . "Recommended action: contact the applicant and update the application status.";

        $adminEmailSent = send_smtp_mail(
            $adminEmail,
            $adminName,
            $adminSubject,
            $adminHtml,
            $adminText
        );
    } catch (Throwable $e) {
        error_log('Admin email send failed: ' . $e->getMessage());
    }

    return [$userEmailSent, $adminEmailSent];
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

    private function redirectToPayment(
        string $redirectAfter,
        int $applicationId,
        string $serviceSlug,
        string $nextStep
    ): void {
        $redirectPath = $redirectAfter !== '' ? ltrim($redirectAfter, '/') : 'payment-method';

        $query = [
            'application_id' => $applicationId,
            'next_step'      => $nextStep !== '' ? $nextStep : 'payment',
        ];

        if ($serviceSlug !== '') {
            $query['service'] = $serviceSlug;
        }

        header('Location: ' . base_url($redirectPath . '?' . http_build_query($query)));
        exit;
    }

    private function redirectBack(string $returnUrl): void
    {
        $target = $returnUrl !== '' ? $returnUrl : base_url('services');
        header('Location: ' . $target);
        exit;
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
}