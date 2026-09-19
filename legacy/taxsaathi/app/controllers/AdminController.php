<?php
declare(strict_types=1);

namespace App\controllers;

use App\Core\Controller;
use App\Core\NotificationService;
use App\Models\Faq;
use App\Models\Lead;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\Role;
use App\Models\Service;
use App\Models\ServiceRequirement;
use App\Models\Setting;
use App\Models\TaxRule;
use App\Models\Testimonial;
use App\Models\User;

final class AdminController extends Controller
{
    public function content(): void
    {
        require_permission('website.manage');

        $this->view('admin/content', [
            'title' => 'Website & Service Control – Tax Saathi',
            'settings' => Setting::many([
                'site_name', 'site_logo', 'site_logo_alt', 'site_phone', 'site_email', 'site_whatsapp',
                'hero_heading', 'hero_subheading', 'hero_cta_text', 'hero_cta_link',
                'why_title', 'why_text', 'process_title', 'contact_address'
            ]),
            'services' => Service::all('sort_order ASC, id DESC'),
            'faqs' => Faq::all('sort_order ASC, id DESC'),
            'testimonials' => Testimonial::all('id DESC'),
        ], 'layouts/dashboard');
    }

    public function saveSettings(): void
    {
        require_permission('website.manage');
        verify_csrf();

        $currentLogo = trim((string) setting('site_logo', ''));
        $removeLogo = isset($_POST['remove_site_logo']) && (string) $_POST['remove_site_logo'] === '1';

        try {
            $uploadedLogo = $this->uploadWebsiteLogo('site_logo_file');
        } catch (\Throwable $exception) {
            flash('error', $exception->getMessage());
            redirect('admin/content');
            return;
        }

        foreach ((array) ($_POST['settings'] ?? []) as $key => $value) {
            $key = trim((string) $key);

            if ($key === '' || $key === 'site_logo') {
                continue;
            }

            Setting::set($key, trim((string) $value));
        }

        if ($uploadedLogo !== null) {
            if ($currentLogo !== '' && $currentLogo !== $uploadedLogo) {
                $this->deleteWebsiteLogo($currentLogo);
            }

            Setting::set('site_logo', $uploadedLogo);
        } elseif ($removeLogo) {
            if ($currentLogo !== '') {
                $this->deleteWebsiteLogo($currentLogo);
            }

            Setting::set('site_logo', '');
        }

        flash('success', 'Website settings updated.');
        redirect('admin/content');
    }

    public function saveService(): void
    {
        require_permission('services.manage');
        verify_csrf();

        $id = (int) input('id', 0);
        $payload = [
            'title' => trim((string) input('title')),
            'slug' => trim((string) input('slug')),
            'excerpt' => trim((string) input('excerpt')),
            'description' => trim((string) input('description')),
            'filing_fee' => (float) input('filing_fee', 0),
            'turnaround_days' => (int) input('turnaround_days', 0),
            'icon' => trim((string) input('icon', 'TS')),
            'sort_order' => (int) input('sort_order', 1),
            'is_featured' => (int) input('is_featured', 0),
            'is_active' => (int) input('is_active', 1),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($id > 0) {
            Service::update($id, $payload);
            flash('success', 'Service updated.');
        } else {
            $payload['created_at'] = date('Y-m-d H:i:s');
            Service::insert($payload);
            flash('success', 'Service created.');
        }

        redirect('admin/content');
    }

    public function deleteService(): void
    {
        require_permission('services.manage');
        verify_csrf();
        Service::delete((int) input('id'));
        flash('success', 'Service deleted.');
        redirect('admin/content');
    }

    public function saveRequirement(): void
    {
        require_permission('services.manage');
        verify_csrf();

        $id = (int) input('id', 0);
        $payload = [
            'service_id' => (int) input('service_id'),
            'label' => trim((string) input('label')),
            'help_text' => trim((string) input('help_text')),
            'is_required' => (int) input('is_required', 1),
            'allow_multiple' => (int) input('allow_multiple', 0),
            'sort_order' => (int) input('sort_order', 1),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($id > 0) {
            ServiceRequirement::update($id, $payload);
            flash('success', 'Requirement updated.');
        } else {
            $payload['created_at'] = date('Y-m-d H:i:s');
            ServiceRequirement::insert($payload);
            flash('success', 'Requirement added.');
        }

        redirect('admin/content');
    }

    public function deleteRequirement(): void
    {
        require_permission('services.manage');
        verify_csrf();
        ServiceRequirement::delete((int) input('id'));
        flash('success', 'Requirement removed.');
        redirect('admin/content');
    }

    public function saveFaq(): void
    {
        require_permission('faqs.manage');
        verify_csrf();

        $id = (int) input('id', 0);
        $payload = [
            'question' => trim((string) input('question')),
            'answer' => trim((string) input('answer')),
            'sort_order' => (int) input('sort_order', 1),
            'is_active' => (int) input('is_active', 1),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($id > 0) {
            Faq::update($id, $payload);
            flash('success', 'FAQ updated.');
        } else {
            $payload['created_at'] = date('Y-m-d H:i:s');
            Faq::insert($payload);
            flash('success', 'FAQ added.');
        }

        redirect('admin/content');
    }

    public function deleteFaq(): void
    {
        require_permission('faqs.manage');
        verify_csrf();
        Faq::delete((int) input('id'));
        flash('success', 'FAQ removed.');
        redirect('admin/content');
    }

    public function saveTestimonial(): void
    {
        require_permission('testimonials.manage');
        verify_csrf();

        $id = (int) input('id', 0);
        $payload = [
            'name' => trim((string) input('name')),
            'designation' => trim((string) input('designation')),
            'company' => trim((string) input('company')),
            'rating' => (int) input('rating', 5),
            'quote' => trim((string) input('quote')),
            'is_active' => (int) input('is_active', 1),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($id > 0) {
            Testimonial::update($id, $payload);
            flash('success', 'Testimonial updated.');
        } else {
            $payload['created_at'] = date('Y-m-d H:i:s');
            Testimonial::insert($payload);
            flash('success', 'Testimonial added.');
        }

        redirect('admin/content');
    }

    public function deleteTestimonial(): void
    {
        require_permission('testimonials.manage');
        verify_csrf();
        Testimonial::delete((int) input('id'));
        flash('success', 'Testimonial removed.');
        redirect('admin/content');
    }

    public function staff(): void
    {
        require_permission('staff.manage');

        $this->view('admin/staff', [
            'title' => 'Staff & Roles – Tax Saathi',
            'roles' => Role::allOrdered(),
            'users' => User::allWithRoles(),
        ], 'layouts/dashboard');
    }

    public function saveRole(): void
    {
        require_permission('staff.manage');
        verify_csrf();

        $id = (int) input('id', 0);
        $permissions = (array) input('permissions', []);
        $payload = [
            'name' => trim((string) input('name')),
            'slug' => trim((string) input('slug')),
            'permissions_json' => json_encode(array_values($permissions), JSON_UNESCAPED_UNICODE),
            'is_system' => (int) input('is_system', 0),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($id > 0) {
            Role::update($id, $payload);
            flash('success', 'Role updated.');
        } else {
            $payload['created_at'] = date('Y-m-d H:i:s');
            Role::insert($payload);
            flash('success', 'Role created.');
        }

        redirect('admin/staff');
    }

    public function saveUser(): void
    {
        require_permission('staff.manage');
        verify_csrf();

        $id = (int) input('id', 0);
        $phone = normalize_phone(trim((string) input('phone')));
        $payload = [
            'role_id' => (int) input('role_id'),
            'client_id' => null,
            'name' => trim((string) input('name')),
            'phone' => $phone,
            'email' => trim((string) input('email')) ?: null,
            'password_hash' => null,
            'is_phone_verified' => (int) input('is_phone_verified', 1),
            'is_active' => (int) input('is_active', 1),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($id > 0) {
            User::update($id, $payload);
            flash('success', 'User updated.');
        } else {
            $payload['last_login_at'] = null;
            $payload['created_at'] = date('Y-m-d H:i:s');
            User::insert($payload);
            flash('success', 'User created.');
        }

        redirect('admin/staff');
    }

    public function toggleUser(): void
    {
        require_permission('staff.manage');
        verify_csrf();

        $user = User::find((int) input('id'));
        if (!$user) {
            flash('error', 'User not found.');
            redirect('admin/staff');
        }

        User::update((int) $user['id'], [
            'is_active' => (int) $user['is_active'] === 1 ? 0 : 1,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        flash('success', 'User status updated.');
        redirect('admin/staff');
    }

    public function calculators(): void
    {
        require_permission('calculators.manage');

        $this->view('admin/calculators', [
            'title' => 'Tax Calculator Settings – Tax Saathi',
            'settings' => Setting::many([
                'calc_standard_deduction_new', 'calc_standard_deduction_old', 'calc_rebate_threshold_new',
                'calc_rebate_threshold_old', 'calc_rebate_amount_new', 'calc_rebate_amount_old', 'calc_cess_percent', 'invoice_gst_percent'
            ]),
            'rules' => TaxRule::grouped(),
        ], 'layouts/dashboard');
    }

    public function saveCalcSettings(): void
    {
        require_permission('calculators.manage');
        verify_csrf();

        foreach ((array) ($_POST['settings'] ?? []) as $key => $value) {
            Setting::set((string) $key, trim((string) $value));
        }

        flash('success', 'Calculator settings updated.');
        redirect('admin/calculators');
    }

    public function saveTaxRule(): void
    {
        require_permission('calculators.manage');
        verify_csrf();

        $id = (int) input('id', 0);
        $payload = [
            'financial_year' => trim((string) input('financial_year')),
            'regime' => trim((string) input('regime')),
            'income_from' => (float) input('income_from', 0),
            'income_to' => input('income_to') !== '' ? (float) input('income_to') : null,
            'rate_percent' => (float) input('rate_percent', 0),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($id > 0) {
            TaxRule::update($id, $payload);
            flash('success', 'Tax rule updated.');
        } else {
            $payload['created_at'] = date('Y-m-d H:i:s');
            TaxRule::insert($payload);
            flash('success', 'Tax rule created.');
        }

        redirect('admin/calculators');
    }

    public function deleteTaxRule(): void
    {
        require_permission('calculators.manage');
        verify_csrf();
        TaxRule::delete((int) input('id'));
        flash('success', 'Tax rule removed.');
        redirect('admin/calculators');
    }

    public function notifications(): void
    {
        require_permission('notifications.manage');

        $this->view('admin/notifications', [
            'title' => 'Notification Settings – Tax Saathi',
            'settings' => Setting::many([
                'email_enabled', 'email_from', 'whatsapp_enabled', 'whatsapp_api_url', 'whatsapp_api_token',
                'whatsapp_sender_number', 'whatsapp_otp_template_name', 'whatsapp_otp_template_id',
                'whatsapp_otp_template_lang', 'whatsapp_otp_message_id'
            ]),
            'templates' => NotificationTemplate::all('id ASC'),
            'logs' => NotificationLog::latest(30),
        ], 'layouts/dashboard');
    }

    public function saveNotificationSettings(): void
    {
        require_permission('notifications.manage');
        verify_csrf();

        foreach ((array) ($_POST['settings'] ?? []) as $key => $value) {
            Setting::set((string) $key, trim((string) $value));
        }

        flash('success', 'Notification settings saved.');
        redirect('admin/notifications');
    }

    public function saveNotificationTemplate(): void
    {
        require_permission('notifications.manage');
        verify_csrf();

        $id = (int) input('id', 0);
        $payload = [
            'event_key' => trim((string) input('event_key')),
            'label' => trim((string) input('label')),
            'subject' => trim((string) input('subject')),
            'email_body' => trim((string) input('email_body')),
            'whatsapp_body' => trim((string) input('whatsapp_body')),
            'is_enabled' => (int) input('is_enabled', 1),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($id > 0) {
            NotificationTemplate::update($id, $payload);
            flash('success', 'Notification template updated.');
        } else {
            $payload['created_at'] = date('Y-m-d H:i:s');
            NotificationTemplate::insert($payload);
            flash('success', 'Notification template created.');
        }

        redirect('admin/notifications');
    }

    public function sendTestOtp(): void
    {
        require_permission('notifications.manage');
        verify_csrf();

        $phone = normalize_phone(trim((string) input('phone')));
        if ($phone === '') {
            flash('error', 'Please enter a phone number.');
            redirect('admin/notifications');
        }

        $otp = (string) random_int(100000, 999999);
        $result = NotificationService::sendOtp($phone, $otp, ['name' => 'Test User']);
        $message = $result['message'] ?? 'Request sent.';
        if (!empty($result['debug_otp'])) {
            $message .= ' Development OTP: ' . $result['debug_otp'];
        }

        flash(($result['ok'] ?? false) ? 'success' : 'warning', (string) $message);
        redirect('admin/notifications');
    }

    public function reports(): void
    {
        require_permission('reports.view');

        $db = app('db');
        $totals = [
            'total_revenue' => (float) $db->scalar('SELECT COALESCE(SUM(paid_amount),0) FROM invoices'),
            'pending_orders' => (int) $db->scalar('SELECT COUNT(*) FROM orders WHERE status = "pending_review"'),
            'completed_orders' => (int) $db->scalar('SELECT COUNT(*) FROM orders WHERE status = "completed"'),
            'new_leads' => (int) $db->scalar('SELECT COUNT(*) FROM leads WHERE status = "new"'),
        ];

        $monthly = $db->fetchAll(
            'SELECT DATE_FORMAT(created_at, "%Y-%m") AS period, COUNT(*) AS total_orders, COALESCE(SUM(fee_amount),0) AS order_value
             FROM orders
             GROUP BY DATE_FORMAT(created_at, "%Y-%m")
             ORDER BY period DESC
             LIMIT 12'
        );

        $this->view('admin/reports', [
            'title' => 'Reports – Tax Saathi',
            'totals' => $totals,
            'monthly' => $monthly,
            'leads' => Lead::latestDetailed(10),
        ], 'layouts/dashboard');
    }

    private function uploadWebsiteLogo(string $fieldName): ?string
    {
        if (
            !isset($_FILES[$fieldName])
            || !is_array($_FILES[$fieldName])
            || (int) ($_FILES[$fieldName]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE
        ) {
            return null;
        }

        $file = $_FILES[$fieldName];
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Logo upload failed. Please try again.');
        }

        $tmpPath = (string) ($file['tmp_name'] ?? '');
        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            throw new \RuntimeException('Invalid uploaded logo file.');
        }

        $size = (int) ($file['size'] ?? 0);
        $maxBytes = 3 * 1024 * 1024;

        if ($size <= 0) {
            throw new \RuntimeException('Uploaded logo file is empty.');
        }

        if ($size > $maxBytes) {
            throw new \RuntimeException('Logo must be under 3MB.');
        }

        $originalName = strtolower((string) ($file['name'] ?? ''));
        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));

        $allowedExtensions = [
            'jpg'  => 'jpg',
            'jpeg' => 'jpg',
            'png'  => 'png',
            'webp' => 'webp',
            'gif'  => 'gif',
            'svg'  => 'svg',
        ];

        if (!isset($allowedExtensions[$extension])) {
            throw new \RuntimeException('Only JPG, PNG, WEBP, GIF, and SVG logos are allowed.');
        }

        if ($extension === 'svg') {
            $svgContent = (string) @file_get_contents($tmpPath);
            $lowerSvg = strtolower($svgContent);

            if ($svgContent === '' || stripos($svgContent, '<svg') === false) {
                throw new \RuntimeException('Invalid SVG logo file.');
            }

            $blockedSvgPatterns = ['<script', 'javascript:', 'onload=', 'onerror=', '<foreignobject'];
            foreach ($blockedSvgPatterns as $pattern) {
                if (str_contains($lowerSvg, $pattern)) {
                    throw new \RuntimeException('Unsafe SVG logo file. Please upload a clean SVG or PNG.');
                }
            }
        } else {
            $mime = (string) mime_content_type($tmpPath);
            $allowedMimes = [
                'image/jpeg',
                'image/png',
                'image/webp',
                'image/gif',
            ];

            if (!in_array($mime, $allowedMimes, true)) {
                throw new \RuntimeException('Invalid logo image file.');
            }
        }

        $projectRoot = dirname(__DIR__, 2);
        $relativeDir = '/uploads/site';
        $absoluteDir = $projectRoot . '/public' . $relativeDir;

        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0775, true) && !is_dir($absoluteDir)) {
            throw new \RuntimeException('Could not create logo upload directory.');
        }

        $filename = 'site_logo_' . date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $allowedExtensions[$extension];
        $absolutePath = $absoluteDir . '/' . $filename;

        if (!move_uploaded_file($tmpPath, $absolutePath)) {
            throw new \RuntimeException('Could not save uploaded logo.');
        }

        return $relativeDir . '/' . $filename;
    }

    private function deleteWebsiteLogo(string $relativePath): void
    {
        $relativePath = trim($relativePath);
        if ($relativePath === '') {
            return;
        }

        $relativePath = (string) (parse_url($relativePath, PHP_URL_PATH) ?: $relativePath);
        $relativePath = '/' . ltrim($relativePath, '/');

        if (!str_starts_with($relativePath, '/uploads/site/')) {
            return;
        }

        $projectRoot = dirname(__DIR__, 2);
        $absolutePath = $projectRoot . '/public' . $relativePath;

        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }

}