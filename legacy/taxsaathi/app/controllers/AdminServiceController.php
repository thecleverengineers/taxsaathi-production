<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class AdminServiceController extends Controller
{
    public function index(): void
    {
        require_permission('services.manage');

        $db = app('db');

        $services = $db->fetchAll(<<<'SQL'
SELECT
    s.*,
    c.title AS service_category_title,
    c.slug AS service_category_slug,
    c.image AS service_category_image,
    c.is_active AS service_category_is_active
FROM services s
INNER JOIN (
    SELECT MAX(id) AS keep_id
    FROM services
    GROUP BY COALESCE(
        NULLIF(LOWER(TRIM(title)), ''),
        NULLIF(LOWER(TRIM(slug)), ''),
        CONCAT('id:', id)
    )
) uniq ON uniq.keep_id = s.id
LEFT JOIN service_categories c ON c.id = s.service_category_id
ORDER BY s.sort_order ASC, s.id DESC
SQL
        ) ?: [];

        /*
         * Final duplicate guard for the listing page:
         * The SQL query already keeps one row per normalized title/slug, but this
         * PHP guard protects the dashboard if another DB driver/query returns old
         * imported duplicates or if titles differ only by spacing/case.
         */
        $services = $this->deduplicateServicesForListing($services);

        $stats = [
            'total_services'  => count($services),
            'active_services' => count(array_filter(
                $services,
                static fn(array $service): bool => (int) ($service['is_active'] ?? 0) === 1
            )),
            'featured_count'  => count(array_filter(
                $services,
                static fn(array $service): bool => (int) ($service['is_featured'] ?? 0) === 1
            )),
        ];

        $this->view('admin/manage_service', [
            'title'       => 'Manage Services – Tax Saathi',
            'stats'       => $stats,
            'services'    => $services,
            'categories'  => $this->getServiceCategories(),
            'iconGallery' => $this->getServiceIconGallery(),
        ], 'layouts/dashboard');
    }

    public function manage(): void
    {
        require_permission('services.manage');

        $db = app('db');
        $serviceId = (int) input('id', 0);
        $activeTab = $this->sanitizeTab((string) input('tab', 'edit-service'));

        if ($serviceId <= 0) {
            flash('error', 'Invalid service selected.');
            redirect('admin/services');
            return;
        }

        $service = $db->fetch(
            'SELECT
                s.*,
                c.title AS service_category_title,
                c.slug AS service_category_slug,
                c.image AS service_category_image,
                c.is_active AS service_category_is_active
             FROM services s
             LEFT JOIN service_categories c ON c.id = s.service_category_id
             WHERE s.id = :id
             LIMIT 1',
            ['id' => $serviceId]
        );

        if (!$service) {
            flash('error', 'Service not found.');
            redirect('admin/services');
            return;
        }

        $banners = $db->fetchAll(
            'SELECT * FROM service_banners WHERE service_id = :service_id ORDER BY sort_order ASC, id DESC',
            ['service_id' => $serviceId]
        );

        $benefits = $db->fetchAll(
            'SELECT * FROM service_benefits WHERE service_id = :service_id ORDER BY sort_order ASC, id DESC',
            ['service_id' => $serviceId]
        );

        $types = $db->fetchAll(
            'SELECT * FROM service_types WHERE service_id = :service_id ORDER BY sort_order ASC, id DESC',
            ['service_id' => $serviceId]
        );

        $requirements = $db->fetchAll(
            'SELECT * FROM service_requirements WHERE service_id = :service_id ORDER BY sort_order ASC, id DESC',
            ['service_id' => $serviceId]
        );

        $reviews = $db->fetchAll(
            'SELECT * FROM service_reviews WHERE service_id = :service_id ORDER BY sort_order ASC, id DESC',
            ['service_id' => $serviceId]
        );

        $this->view('admin/manage_service_details', [
            'title'        => 'Manage Service Details – Tax Saathi',
            'service'      => $service,
            'banners'      => $banners,
            'benefits'     => $benefits,
            'types'        => $types,
            'requirements' => $requirements,
            'reviews'      => $reviews,
            'activeTab'    => $activeTab,
            'categories'   => $this->getServiceCategories(),
            'iconGallery'  => $this->getServiceIconGallery(),
        ], 'layouts/dashboard');
    }

    public function saveService(): void
    {
        require_permission('services.manage');
        verify_csrf();

        $db = app('db');
        $id = (int) input('id', 0);

        $title = trim((string) input('title'));
        $slug  = $this->slugify((string) input('slug', $title));

        if ($title === '') {
            flash('error', 'Service title is required.');
            redirect('admin/services');
            return;
        }

        if ($slug === '') {
            flash('error', 'Service slug is required.');
            redirect('admin/services');
            return;
        }

        $existingService = null;
        if ($id > 0) {
            $existingService = $db->fetch(
                'SELECT id, icon, service_category_id
                 FROM services
                 WHERE id = :id
                 LIMIT 1',
                ['id' => $id]
            );

            if (!$existingService) {
                flash('error', 'Service not found.');
                redirect('admin/services');
                return;
            }
        }

        $duplicate = $db->fetch(
            'SELECT id FROM services WHERE slug = :slug AND id != :id LIMIT 1',
            ['slug' => $slug, 'id' => $id]
        );

        if ($duplicate) {
            flash('error', 'Service slug already exists.');
            redirect($id > 0 ? 'admin/services/manage?id=' . $id . '&tab=edit-service' : 'admin/services');
            return;
        }

        $rawCategoryId = input('service_category_id', null);
        $serviceCategoryId = null;

        if ($rawCategoryId !== null && $rawCategoryId !== '') {
            $candidateCategoryId = (int) $rawCategoryId;

            if ($candidateCategoryId <= 0) {
                flash('error', 'Please select a valid service category.');
                redirect($id > 0 ? 'admin/services/manage?id=' . $id . '&tab=edit-service' : 'admin/services');
                return;
            }

            if (!$this->serviceCategoryExists($candidateCategoryId)) {
                flash('error', 'Selected service category does not exist.');
                redirect($id > 0 ? 'admin/services/manage?id=' . $id . '&tab=edit-service' : 'admin/services');
                return;
            }

            $serviceCategoryId = $candidateCategoryId;
        } elseif ($id > 0) {
            $serviceCategoryId = isset($existingService['service_category_id'])
                ? (int) $existingService['service_category_id']
                : null;
        } else {
            flash('error', 'Please select a service category.');
            redirect('admin/services');
            return;
        }

        $titleDuplicate = $this->findDuplicateServiceTitle($title, $serviceCategoryId, $id);

        if ($titleDuplicate) {
            flash('error', 'This service already exists. Duplicate services are not allowed.');
            redirect($id > 0 ? 'admin/services/manage?id=' . $id . '&tab=edit-service' : 'admin/services');
            return;
        }

        $currentIcon  = $this->normalizeServiceIconPath((string) ($existingService['icon'] ?? ''));
        $selectedIcon = $this->normalizeServiceIconPath((string) input('selected_icon', ''));
        $uploadedIcon = $this->uploadServiceIcon('icon_file');

        $finalIcon = $uploadedIcon ?? $selectedIcon ?? $currentIcon;

        $payload = [
            'service_category_id' => $serviceCategoryId,
            'title'               => $title,
            'slug'                => $slug,
            'excerpt'             => $this->nullIfEmpty((string) input('excerpt')),
            'description'         => $this->nullIfEmpty((string) input('description')),
            'filing_fee'          => (float) input('filing_fee', 0),
            'turnaround_days'     => (int) input('turnaround_days', 0),
            'icon'                => $finalIcon,
            'sort_order'          => (int) input('sort_order', 1),
            'is_featured'         => $this->checkbox('is_featured'),
            'is_active'           => $this->checkbox('is_active'),
            'updated_at'          => $this->now(),
        ];

        if ($id > 0) {
            $this->dbUpdateRow('services', $payload, ['id' => $id]);
            flash('success', 'Service updated successfully.');
            $this->redirectToServiceTab($id, 'edit-service');
            return;
        }

        $payload['created_at'] = $this->now();
        $newId = $this->dbInsertRow('services', $payload);

        flash('success', 'Service created successfully.');
        $this->redirectToServiceTab($newId, 'edit-service');
    }

    public function deleteService(): void
    {
        require_permission('services.manage');
        verify_csrf();

        $db = app('db');
        $id = (int) input('id', 0);

        if ($id <= 0) {
            flash('error', 'Invalid service.');
            redirect('admin/services');
            return;
        }

        $banners = $db->fetchAll(
            'SELECT image FROM service_banners WHERE service_id = :service_id',
            ['service_id' => $id]
        );

        foreach ($banners as $banner) {
            $this->deleteUploadedFile((string) ($banner['image'] ?? ''));
        }

        $this->dbDeleteRow('service_banners', ['service_id' => $id]);
        $this->dbDeleteRow('service_benefits', ['service_id' => $id]);
        $this->dbDeleteRow('service_types', ['service_id' => $id]);
        $this->dbDeleteRow('service_requirements', ['service_id' => $id]);
        $this->dbDeleteRow('service_reviews', ['service_id' => $id]);
        $this->dbDeleteRow('services', ['id' => $id]);

        flash('success', 'Service and all related content deleted.');
        redirect('admin/services');
    }

    public function saveBanner(): void
    {
        require_permission('services.manage');
        verify_csrf();

        $id = (int) input('id', 0);
        $serviceId = (int) input('service_id', 0);

        $this->ensureServiceExists($serviceId);

        $backgroundType = trim((string) input('background_type', 'gradient'));
        if (!in_array($backgroundType, ['gradient', 'image', 'solid'], true)) {
            $backgroundType = 'gradient';
        }

        $title = trim((string) input('title'));
        if ($title === '') {
            flash('error', 'Banner title is required.');
            $this->redirectToServiceTab($serviceId, 'banners');
            return;
        }

        $existingImage = trim((string) input('existing_image', ''));
        $uploadedImage = $this->uploadBannerImage('image_file');

        if ($uploadedImage !== null && $existingImage !== '' && $uploadedImage !== $existingImage) {
            $this->deleteUploadedFile($existingImage);
        }

        $finalImage = $uploadedImage ?? ($existingImage !== '' ? $existingImage : null);

        $payload = [
            'service_id'       => $serviceId,
            'badge'            => $this->nullIfEmpty((string) input('badge')),
            'title'            => $title,
            'subtitle'         => $this->nullIfEmpty((string) input('subtitle')),
            'button_text'      => $this->nullIfEmpty((string) input('button_text')),
            'button_link'      => $this->nullIfEmpty((string) input('button_link')),
            'image'            => $finalImage,
            'background_type'  => $backgroundType,
            'background_value' => $this->nullIfEmpty((string) input('background_value')),
            'text_color'       => trim((string) input('text_color', '#ffffff')) ?: '#ffffff',
            'is_active'        => $this->checkbox('is_active'),
            'sort_order'       => (int) input('sort_order', 0),
            'updated_at'       => $this->now(),
        ];

        if ($id > 0) {
            $this->dbUpdateRow('service_banners', $payload, ['id' => $id]);
            flash('success', 'Banner updated.');
        } else {
            $payload['created_at'] = $this->now();
            $this->dbInsertRow('service_banners', $payload);
            flash('success', 'Banner added.');
        }

        $this->redirectToServiceTab($serviceId, 'banners');
    }

    public function deleteBanner(): void
    {
        require_permission('services.manage');
        verify_csrf();

        $db = app('db');
        $id = (int) input('id', 0);
        $serviceId = (int) input('service_id', 0);

        if ($id > 0) {
            $banner = $db->fetch('SELECT image FROM service_banners WHERE id = :id LIMIT 1', ['id' => $id]);
            if ($banner && !empty($banner['image'])) {
                $this->deleteUploadedFile((string) $banner['image']);
            }

            $this->dbDeleteRow('service_banners', ['id' => $id]);
            flash('success', 'Banner deleted.');
        }

        $this->redirectToServiceTab($serviceId, 'banners');
    }

    public function saveBenefit(): void
    {
        require_permission('services.manage');
        verify_csrf();

        $id = (int) input('id', 0);
        $serviceId = (int) input('service_id', 0);

        $this->ensureServiceExists($serviceId);

        $title = trim((string) input('title'));
        if ($title === '') {
            flash('error', 'Benefit title is required.');
            $this->redirectToServiceTab($serviceId, 'benefits');
            return;
        }

        $payload = [
            'service_id'  => $serviceId,
            'icon'        => $this->nullIfEmpty((string) input('icon')),
            'title'       => $title,
            'description' => $this->nullIfEmpty((string) input('description')),
            'sort_order'  => (int) input('sort_order', 0),
            'is_active'   => $this->checkbox('is_active'),
            'updated_at'  => $this->now(),
        ];

        if ($id > 0) {
            $this->dbUpdateRow('service_benefits', $payload, ['id' => $id]);
            flash('success', 'Benefit updated.');
        } else {
            $payload['created_at'] = $this->now();
            $this->dbInsertRow('service_benefits', $payload);
            flash('success', 'Benefit added.');
        }

        $this->redirectToServiceTab($serviceId, 'benefits');
    }

    public function deleteBenefit(): void
    {
        require_permission('services.manage');
        verify_csrf();

        $id = (int) input('id', 0);
        $serviceId = (int) input('service_id', 0);

        if ($id > 0) {
            $this->dbDeleteRow('service_benefits', ['id' => $id]);
            flash('success', 'Benefit deleted.');
        }

        $this->redirectToServiceTab($serviceId, 'benefits');
    }

    public function saveType(): void
    {
        require_permission('services.manage');
        verify_csrf();

        $id = (int) input('id', 0);
        $serviceId = (int) input('service_id', 0);

        $this->ensureServiceExists($serviceId);

        $title = trim((string) input('title'));
        if ($title === '') {
            flash('error', 'Type title is required.');
            $this->redirectToServiceTab($serviceId, 'types');
            return;
        }

        $payload = [
            'service_id'  => $serviceId,
            'icon'        => $this->nullIfEmpty((string) input('icon')),
            'title'       => $title,
            'description' => $this->nullIfEmpty((string) input('description')),
            'sort_order'  => (int) input('sort_order', 0),
            'is_active'   => $this->checkbox('is_active'),
            'updated_at'  => $this->now(),
        ];

        if ($id > 0) {
            $this->dbUpdateRow('service_types', $payload, ['id' => $id]);
            flash('success', 'Type updated.');
        } else {
            $payload['created_at'] = $this->now();
            $this->dbInsertRow('service_types', $payload);
            flash('success', 'Type added.');
        }

        $this->redirectToServiceTab($serviceId, 'types');
    }

    public function deleteType(): void
    {
        require_permission('services.manage');
        verify_csrf();

        $id = (int) input('id', 0);
        $serviceId = (int) input('service_id', 0);

        if ($id > 0) {
            $this->dbDeleteRow('service_types', ['id' => $id]);
            flash('success', 'Type deleted.');
        }

        $this->redirectToServiceTab($serviceId, 'types');
    }

    public function saveRequirement(): void
    {
        require_permission('services.manage');
        verify_csrf();

        $id = (int) input('id', 0);
        $serviceId = (int) input('service_id', 0);

        $this->ensureServiceExists($serviceId);

        $label = trim((string) input('label'));
        if ($label === '') {
            flash('error', 'Requirement label is required.');
            $this->redirectToServiceTab($serviceId, 'requirements');
            return;
        }

        $payload = [
            'service_id'      => $serviceId,
            'label'           => $label,
            'icon'            => $this->nullIfEmpty((string) input('icon')),
            'help_text'       => $this->nullIfEmpty((string) input('help_text')),
            'is_required'     => $this->checkbox('is_required'),
            'allow_multiple'  => $this->checkbox('allow_multiple'),
            'sort_order'      => (int) input('sort_order', 1),
            'is_active'       => $this->checkbox('is_active'),
            'updated_at'      => $this->now(),
        ];

        if ($id > 0) {
            $this->dbUpdateRow('service_requirements', $payload, ['id' => $id]);
            flash('success', 'Requirement updated.');
        } else {
            $payload['created_at'] = $this->now();
            $this->dbInsertRow('service_requirements', $payload);
            flash('success', 'Requirement added.');
        }

        $this->redirectToServiceTab($serviceId, 'requirements');
    }

    public function deleteRequirement(): void
    {
        require_permission('services.manage');
        verify_csrf();

        $id = (int) input('id', 0);
        $serviceId = (int) input('service_id', 0);

        if ($id > 0) {
            $this->dbDeleteRow('service_requirements', ['id' => $id]);
            flash('success', 'Requirement deleted.');
        }

        $this->redirectToServiceTab($serviceId, 'requirements');
    }

    public function saveReview(): void
    {
        require_permission('services.manage');
        verify_csrf();

        $id = (int) input('id', 0);
        $serviceId = (int) input('service_id', 0);

        $this->ensureServiceExists($serviceId);

        $name  = trim((string) input('name'));
        $quote = trim((string) input('quote'));

        if ($name === '' || $quote === '') {
            flash('error', 'Review name and quote are required.');
            $this->redirectToServiceTab($serviceId, 'reviews');
            return;
        }

        $rating = (int) input('rating', 5);
        if ($rating < 1) {
            $rating = 1;
        }
        if ($rating > 5) {
            $rating = 5;
        }

        $payload = [
            'service_id'  => $serviceId,
            'name'        => $name,
            'designation' => $this->nullIfEmpty((string) input('designation')),
            'company'     => $this->nullIfEmpty((string) input('company')),
            'rating'      => $rating,
            'quote'       => $quote,
            'sort_order'  => (int) input('sort_order', 0),
            'is_active'   => $this->checkbox('is_active'),
            'updated_at'  => $this->now(),
        ];

        if ($id > 0) {
            $this->dbUpdateRow('service_reviews', $payload, ['id' => $id]);
            flash('success', 'Review updated.');
        } else {
            $payload['created_at'] = $this->now();
            $this->dbInsertRow('service_reviews', $payload);
            flash('success', 'Review added.');
        }

        $this->redirectToServiceTab($serviceId, 'reviews');
    }

    public function deleteReview(): void
    {
        require_permission('services.manage');
        verify_csrf();

        $id = (int) input('id', 0);
        $serviceId = (int) input('service_id', 0);

        if ($id > 0) {
            $this->dbDeleteRow('service_reviews', ['id' => $id]);
            flash('success', 'Review deleted.');
        }

        $this->redirectToServiceTab($serviceId, 'reviews');
    }

    private function deduplicateServicesForListing(array $services): array
    {
        $uniqueServices = [];
        $seenKeys = [];

        foreach ($services as $service) {
            if (!is_array($service)) {
                continue;
            }

            $serviceId = (int) ($service['id'] ?? 0);
            $serviceKey = $this->serviceUniqueKey(
                (string) ($service['title'] ?? ''),
                (string) ($service['slug'] ?? ''),
                $serviceId
            );

            if ($serviceKey === '') {
                continue;
            }

            if (isset($seenKeys[$serviceKey])) {
                continue;
            }

            $seenKeys[$serviceKey] = true;
            $uniqueServices[] = $service;
        }

        return array_values($uniqueServices);
    }

    private function findDuplicateServiceTitle(string $title, ?int $serviceCategoryId = null, int $excludeServiceId = 0): ?array
    {
        unset($serviceCategoryId);

        $normalizedTitle = $this->normalizeLookupText($title);

        if ($normalizedTitle === '') {
            return null;
        }

        $duplicate = app('db')->fetch(
            'SELECT id
             FROM services
             WHERE LOWER(TRIM(title)) = :title
               AND id != :id
             LIMIT 1',
            [
                'title' => $normalizedTitle,
                'id'    => $excludeServiceId,
            ]
        );

        return is_array($duplicate) ? $duplicate : null;
    }

    private function serviceUniqueKey(string $title, string $slug = '', int $serviceId = 0): string
    {
        $normalizedTitle = $this->normalizeLookupText($title);

        if ($normalizedTitle !== '') {
            return 'title:' . $normalizedTitle;
        }

        $normalizedSlug = $this->normalizeLookupText($slug);

        if ($normalizedSlug !== '') {
            return 'slug:' . $normalizedSlug;
        }

        return $serviceId > 0 ? 'id:' . $serviceId : '';
    }

    private function normalizeLookupText(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return function_exists('mb_strtolower')
            ? mb_strtolower($value, 'UTF-8')
            : strtolower($value);
    }

    private function checkbox(string $key): int
    {
        return isset($_POST[$key]) ? 1 : 0;
    }

    private function nullIfEmpty(string $value): ?string
    {
        $value = trim($value);
        return $value === '' ? null : $value;
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    private function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/i', '-', $value) ?? '';
        return trim($value, '-');
    }

    private function sanitizeTab(string $tab): string
    {
        $allowed = ['edit-service', 'banners', 'benefits', 'types', 'requirements', 'reviews'];
        return in_array($tab, $allowed, true) ? $tab : 'edit-service';
    }

    private function redirectToServiceTab(int $serviceId, string $tab): void
    {
        redirect('admin/services/manage?id=' . $serviceId . '&tab=' . $this->sanitizeTab($tab));
    }

    private function ensureServiceExists(int $serviceId): void
    {
        if ($serviceId <= 0) {
            flash('error', 'Invalid service.');
            redirect('admin/services');
            exit;
        }

        $service = app('db')->fetch(
            'SELECT id FROM services WHERE id = :id LIMIT 1',
            ['id' => $serviceId]
        );

        if (!$service) {
            flash('error', 'Service not found.');
            redirect('admin/services');
            exit;
        }
    }

    private function serviceCategoryExists(int $categoryId): bool
    {
        if ($categoryId <= 0) {
            return false;
        }

        $row = app('db')->fetch(
            'SELECT id FROM service_categories WHERE id = :id LIMIT 1',
            ['id' => $categoryId]
        );

        return is_array($row) && !empty($row['id']);
    }

    private function getServiceCategories(): array
    {
        return app('db')->fetchAll(
            'SELECT id, title, slug, image, is_active, sort_order
             FROM service_categories
             ORDER BY sort_order ASC, title ASC'
        ) ?: [];
    }

    private function normalizeServiceIconPath(string $path): ?string
    {
        $path = trim($path);
        if ($path === '') {
            return null;
        }

        $path = parse_url($path, PHP_URL_PATH) ?: $path;
        $path = '/' . ltrim($path, '/');

        if (!str_starts_with($path, '/uploads/service-icons/')) {
            return null;
        }

        $projectRoot = dirname(__DIR__, 2);
        $absolutePath = $projectRoot . '/public' . $path;

        if (!is_file($absolutePath)) {
            return null;
        }

        $extension = strtolower((string) pathinfo($absolutePath, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'];

        return in_array($extension, $allowed, true) ? $path : null;
    }

    private function getServiceIconGallery(): array
    {
        $projectRoot = dirname(__DIR__, 2);
        $absoluteDir = $projectRoot . '/public/uploads/service-icons';

        if (!is_dir($absoluteDir)) {
            return [];
        }

        $files = glob($absoluteDir . '/*.{jpg,jpeg,png,webp,gif,svg,JPG,JPEG,PNG,WEBP,GIF,SVG}', GLOB_BRACE) ?: [];

        usort($files, static function (string $a, string $b): int {
            return (filemtime($b) ?: 0) <=> (filemtime($a) ?: 0);
        });

        $gallery = [];

        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }

            $gallery[] = '/uploads/service-icons/' . basename($file);
        }

        return array_values(array_unique($gallery));
    }

    private function uploadServiceIcon(string $fieldName): ?string
    {
        if (
            !isset($_FILES[$fieldName]) ||
            !is_array($_FILES[$fieldName]) ||
            (int) ($_FILES[$fieldName]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE
        ) {
            return null;
        }

        $file = $_FILES[$fieldName];

        if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            flash('error', 'Service icon upload failed.');
            return null;
        }

        $tmpPath = (string) ($file['tmp_name'] ?? '');
        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            flash('error', 'Invalid uploaded service icon.');
            return null;
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
            flash('error', 'Only JPG, PNG, WEBP, GIF, and SVG icons are allowed.');
            return null;
        }

        $maxBytes = 3 * 1024 * 1024;
        if ((int) ($file['size'] ?? 0) > $maxBytes) {
            flash('error', 'Service icon must be under 3MB.');
            return null;
        }

        if ($extension === 'svg') {
            $svgContent = (string) @file_get_contents($tmpPath);
            if ($svgContent === '' || stripos($svgContent, '<svg') === false) {
                flash('error', 'Invalid SVG icon file.');
                return null;
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
                flash('error', 'Invalid image file.');
                return null;
            }
        }

        $projectRoot = dirname(__DIR__, 2);
        $relativeDir = '/uploads/service-icons';
        $absoluteDir = $projectRoot . '/public' . $relativeDir;

        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0775, true) && !is_dir($absoluteDir)) {
            flash('error', 'Could not create service icon upload directory.');
            return null;
        }

        $filename = 'service_icon_' . date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $allowedExtensions[$extension];
        $absolutePath = $absoluteDir . '/' . $filename;

        if (!move_uploaded_file($tmpPath, $absolutePath)) {
            flash('error', 'Could not save uploaded service icon.');
            return null;
        }

        return $relativeDir . '/' . $filename;
    }

    private function uploadBannerImage(string $fieldName): ?string
    {
        if (
            !isset($_FILES[$fieldName]) ||
            !is_array($_FILES[$fieldName]) ||
            (int) ($_FILES[$fieldName]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE
        ) {
            return null;
        }

        $file = $_FILES[$fieldName];

        if ((int) $file['error'] !== UPLOAD_ERR_OK) {
            flash('error', 'Image upload failed.');
            return null;
        }

        $tmpPath = (string) ($file['tmp_name'] ?? '');
        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            flash('error', 'Invalid uploaded file.');
            return null;
        }

        $mime = (string) mime_content_type($tmpPath);
        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
        ];

        if (!isset($allowed[$mime])) {
            flash('error', 'Only JPG, PNG, WEBP, and GIF images are allowed.');
            return null;
        }

        $maxBytes = 5 * 1024 * 1024;
        if ((int) ($file['size'] ?? 0) > $maxBytes) {
            flash('error', 'Image must be under 5MB.');
            return null;
        }

        $projectRoot = dirname(__DIR__, 2);
        $relativeDir = '/uploads/service-banners';
        $absoluteDir = $projectRoot . '/public' . $relativeDir;

        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0775, true) && !is_dir($absoluteDir)) {
            flash('error', 'Could not create banner upload directory.');
            return null;
        }

        $filename = 'banner_' . date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
        $absolutePath = $absoluteDir . '/' . $filename;

        if (!move_uploaded_file($tmpPath, $absolutePath)) {
            flash('error', 'Could not save uploaded image.');
            return null;
        }

        return $relativeDir . '/' . $filename;
    }

    private function deleteUploadedFile(string $relativePath): void
    {
        $relativePath = trim($relativePath);
        if ($relativePath === '') {
            return;
        }

        $relativePath = parse_url($relativePath, PHP_URL_PATH) ?: $relativePath;
        $relativePath = '/' . ltrim($relativePath, '/');

        if (!str_starts_with($relativePath, '/uploads/service-banners/')) {
            return;
        }

        $projectRoot = dirname(__DIR__, 2);
        $absolutePath = $projectRoot . '/public' . $relativePath;

        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }

    private function dbWrite(string $sql, array $params = []): void
    {
        $db = app('db');

        if (method_exists($db, 'execute')) {
            $db->execute($sql, $params);
            return;
        }

        if (method_exists($db, 'statement')) {
            $db->statement($sql, $params);
            return;
        }

        if (method_exists($db, 'query')) {
            $db->query($sql, $params);
            return;
        }

        if (method_exists($db, 'pdo')) {
            $stmt = $db->pdo()->prepare($sql);
            $stmt->execute($params);
            return;
        }

        if (property_exists($db, 'pdo') && $db->pdo instanceof \PDO) {
            $stmt = $db->pdo->prepare($sql);
            $stmt->execute($params);
            return;
        }

        throw new \RuntimeException('Database write method not supported by App\Core\Database.');
    }

    private function dbInsertRow(string $table, array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_map(static fn(string $col): string => ':' . $col, $columns);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $this->dbWrite($sql, $data);

        $db = app('db');

        if (method_exists($db, 'lastInsertId')) {
            return (int) $db->lastInsertId();
        }

        if (method_exists($db, 'pdo')) {
            return (int) $db->pdo()->lastInsertId();
        }

        if (property_exists($db, 'pdo') && $db->pdo instanceof \PDO) {
            return (int) $db->pdo->lastInsertId();
        }

        if (method_exists($db, 'scalar')) {
            return (int) $db->scalar('SELECT LAST_INSERT_ID()');
        }

        return 0;
    }

    private function dbUpdateRow(string $table, array $data, array $where): void
    {
        $setParts = [];
        $params = [];

        foreach ($data as $column => $value) {
            $param = 'set_' . $column;
            $setParts[] = $column . ' = :' . $param;
            $params[$param] = $value;
        }

        $whereParts = [];
        foreach ($where as $column => $value) {
            $param = 'where_' . $column;
            $whereParts[] = $column . ' = :' . $param;
            $params[$param] = $value;
        }

        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s',
            $table,
            implode(', ', $setParts),
            implode(' AND ', $whereParts)
        );

        $this->dbWrite($sql, $params);
    }

    private function dbDeleteRow(string $table, array $where): void
    {
        $whereParts = [];
        $params = [];

        foreach ($where as $column => $value) {
            $param = 'where_' . $column;
            $whereParts[] = $column . ' = :' . $param;
            $params[$param] = $value;
        }

        $sql = sprintf(
            'DELETE FROM %s WHERE %s',
            $table,
            implode(' AND ', $whereParts)
        );

        $this->dbWrite($sql, $params);
    }
}