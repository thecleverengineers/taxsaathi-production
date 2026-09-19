<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class AdminServiceCategoryController extends Controller
{
    public function index(): void
    {
        require_permission('services.manage');

        $db = app('db');

        $search = trim((string) input('q', ''));
        $status = trim((string) input('status', ''));

        $where = [];
        $params = [];

        if ($search !== '') {
            $where[] = '(title LIKE :search OR slug LIKE :search OR description LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        if ($status === 'active') {
            $where[] = 'is_active = 1';
        }

        if ($status === 'inactive') {
            $where[] = 'is_active = 0';
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $categories = $db->fetchAll(
            "SELECT *
             FROM service_categories
             {$whereSql}
             ORDER BY sort_order ASC, id DESC",
            $params
        ) ?: [];

        $stats = [
            'total_categories'  => (int) $db->scalar('SELECT COUNT(*) FROM service_categories'),
            'active_categories' => (int) $db->scalar('SELECT COUNT(*) FROM service_categories WHERE is_active = 1'),
            'inactive_count'    => (int) $db->scalar('SELECT COUNT(*) FROM service_categories WHERE is_active = 0'),
        ];

        $this->view('admin/manage_service_categories', [
            'title'        => 'Manage Service Categories – Tax Saathi',
            'categories'   => $categories,
            'stats'        => $stats,
            'search'       => $search,
            'status'       => $status,
            'imageGallery' => $this->getCategoryImageGallery(),
        ], 'layouts/dashboard');
    }

    public function create(): void
    {
        require_permission('services.manage');

        $this->view('admin/manage_service_category_form', [
            'title'        => 'Create Service Category – Tax Saathi',
            'mode'         => 'create',
            'category'     => [
                'id'          => 0,
                'title'       => '',
                'slug'        => '',
                'image'       => '',
                'description' => '',
                'sort_order'  => 0,
                'is_active'   => 1,
            ],
            'imageGallery' => $this->getCategoryImageGallery(),
        ], 'layouts/dashboard');
    }

    public function edit(): void
    {
        require_permission('services.manage');

        $db = app('db');
        $id = (int) input('id', 0);

        if ($id <= 0) {
            flash('error', 'Invalid service category selected.');
            redirect('admin/service-categories');
            return;
        }

        $category = $db->fetch(
            'SELECT *
             FROM service_categories
             WHERE id = :id
             LIMIT 1',
            ['id' => $id]
        );

        if (!$category) {
            flash('error', 'Service category not found.');
            redirect('admin/service-categories');
            return;
        }

        $this->view('admin/manage_service_category_form', [
            'title'        => 'Edit Service Category – Tax Saathi',
            'mode'         => 'edit',
            'category'     => $category,
            'imageGallery' => $this->getCategoryImageGallery(),
        ], 'layouts/dashboard');
    }

    public function save(): void
    {
        require_permission('services.manage');
        verify_csrf();

        $db = app('db');

        $id = (int) input('id', 0);

        $title = trim((string) input('title', ''));
        $slug  = $this->slugify((string) input('slug', $title));

        if ($title === '') {
            flash('error', 'Category title is required.');
            redirect($id > 0 ? 'admin/service-categories/edit?id=' . $id : 'admin/service-categories/create');
            return;
        }

        if ($slug === '') {
            flash('error', 'Category slug is required.');
            redirect($id > 0 ? 'admin/service-categories/edit?id=' . $id : 'admin/service-categories/create');
            return;
        }

        if (mb_strlen($title) > 150) {
            flash('error', 'Category title must not exceed 150 characters.');
            redirect($id > 0 ? 'admin/service-categories/edit?id=' . $id : 'admin/service-categories/create');
            return;
        }

        if (mb_strlen($slug) > 180) {
            flash('error', 'Category slug must not exceed 180 characters.');
            redirect($id > 0 ? 'admin/service-categories/edit?id=' . $id : 'admin/service-categories/create');
            return;
        }

        $existingCategory = null;

        if ($id > 0) {
            $existingCategory = $db->fetch(
                'SELECT *
                 FROM service_categories
                 WHERE id = :id
                 LIMIT 1',
                ['id' => $id]
            );

            if (!$existingCategory) {
                flash('error', 'Service category not found.');
                redirect('admin/service-categories');
                return;
            }
        }

        $duplicate = $db->fetch(
            'SELECT id
             FROM service_categories
             WHERE slug = :slug
             AND id != :id
             LIMIT 1',
            [
                'slug' => $slug,
                'id'   => $id,
            ]
        );

        if ($duplicate) {
            flash('error', 'Category slug already exists.');
            redirect($id > 0 ? 'admin/service-categories/edit?id=' . $id : 'admin/service-categories/create');
            return;
        }

        $currentImage  = $this->normalizeCategoryImagePath((string) ($existingCategory['image'] ?? ''));
        $selectedImage = $this->normalizeCategoryImagePath((string) input('selected_image', ''));
        $manualImage   = trim((string) input('image_path', ''));
        $uploadedImage = $this->uploadCategoryImage('image_file');

        if (!empty($_POST['remove_image'])) {
            $this->deleteCategoryImage($currentImage ?? '');
            $currentImage = null;
        }

        $finalImage = $currentImage;

        if ($manualImage !== '') {
            $finalImage = $manualImage;
        }

        if ($selectedImage !== null) {
            $finalImage = $selectedImage;
        }

        if ($uploadedImage !== null) {
            if ($currentImage !== null && $uploadedImage !== $currentImage) {
                $this->deleteCategoryImage($currentImage);
            }

            $finalImage = $uploadedImage;
        }

        $payload = [
            'title'       => $title,
            'slug'        => $slug,
            'image'       => $this->nullIfEmpty((string) $finalImage),
            'description' => $this->nullIfEmpty((string) input('description', '')),
            'sort_order'  => (int) input('sort_order', 0),
            'is_active'   => $this->checkbox('is_active'),
            'updated_at'  => $this->now(),
        ];

        if ($id > 0) {
            $this->dbUpdateRow('service_categories', $payload, ['id' => $id]);

            flash('success', 'Service category updated successfully.');
            redirect('admin/service-categories/edit?id=' . $id);
            return;
        }

        $payload['created_at'] = $this->now();

        $newId = $this->dbInsertRow('service_categories', $payload);

        flash('success', 'Service category created successfully.');
        redirect('admin/service-categories/edit?id=' . $newId);
    }

    public function toggle(): void
    {
        require_permission('services.manage');
        verify_csrf();

        $id = (int) input('id', 0);

        if ($id <= 0) {
            flash('error', 'Invalid category.');
            redirect('admin/service-categories');
            return;
        }

        $category = app('db')->fetch(
            'SELECT id, is_active
             FROM service_categories
             WHERE id = :id
             LIMIT 1',
            ['id' => $id]
        );

        if (!$category) {
            flash('error', 'Service category not found.');
            redirect('admin/service-categories');
            return;
        }

        $this->dbUpdateRow('service_categories', [
            'is_active'  => ((int) ($category['is_active'] ?? 0) === 1) ? 0 : 1,
            'updated_at' => $this->now(),
        ], ['id' => $id]);

        flash('success', 'Category status updated.');
        redirect('admin/service-categories');
    }

    public function delete(): void
    {
        require_permission('services.manage');
        verify_csrf();

        $db = app('db');
        $id = (int) input('id', 0);

        if ($id <= 0) {
            flash('error', 'Invalid category.');
            redirect('admin/service-categories');
            return;
        }

        $category = $db->fetch(
            'SELECT *
             FROM service_categories
             WHERE id = :id
             LIMIT 1',
            ['id' => $id]
        );

        if (!$category) {
            flash('error', 'Service category not found.');
            redirect('admin/service-categories');
            return;
        }

        $this->deleteCategoryImage((string) ($category['image'] ?? ''));
        $this->dbDeleteRow('service_categories', ['id' => $id]);

        flash('success', 'Service category deleted successfully.');
        redirect('admin/service-categories');
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

    private function normalizeCategoryImagePath(string $path): ?string
    {
        $path = trim($path);

        if ($path === '') {
            return null;
        }

        if (
            preg_match('~^(https?:)?//~i', $path) === 1 ||
            str_starts_with($path, 'data:')
        ) {
            return $path;
        }

        $path = parse_url($path, PHP_URL_PATH) ?: $path;
        $path = '/' . ltrim($path, '/');

        if (!str_starts_with($path, '/uploads/service-categories/')) {
            return null;
        }

        return $path;
    }

    private function getCategoryImageGallery(): array
    {
        $projectRoot = dirname(__DIR__, 2);
        $absoluteDir = $projectRoot . '/public/uploads/service-categories';

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

            $gallery[] = '/uploads/service-categories/' . basename($file);
        }

        return array_values(array_unique($gallery));
    }

    private function uploadCategoryImage(string $fieldName): ?string
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
            flash('error', 'Category image upload failed.');
            return null;
        }

        $tmpPath = (string) ($file['tmp_name'] ?? '');

        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            flash('error', 'Invalid uploaded category image.');
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
            flash('error', 'Only JPG, PNG, WEBP, GIF, and SVG images are allowed.');
            return null;
        }

        $maxBytes = 4 * 1024 * 1024;

        if ((int) ($file['size'] ?? 0) > $maxBytes) {
            flash('error', 'Category image must be under 4MB.');
            return null;
        }

        if ($extension === 'svg') {
            $svgContent = (string) @file_get_contents($tmpPath);

            if ($svgContent === '' || stripos($svgContent, '<svg') === false) {
                flash('error', 'Invalid SVG image file.');
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
        $relativeDir = '/uploads/service-categories';
        $absoluteDir = $projectRoot . '/public' . $relativeDir;

        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0775, true) && !is_dir($absoluteDir)) {
            flash('error', 'Could not create category image upload directory.');
            return null;
        }

        $filename = 'category_' . date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $allowedExtensions[$extension];
        $absolutePath = $absoluteDir . '/' . $filename;

        if (!move_uploaded_file($tmpPath, $absolutePath)) {
            flash('error', 'Could not save uploaded category image.');
            return null;
        }

        return $relativeDir . '/' . $filename;
    }

    private function deleteCategoryImage(string $relativePath): void
    {
        $relativePath = trim($relativePath);

        if ($relativePath === '') {
            return;
        }

        if (
            preg_match('~^(https?:)?//~i', $relativePath) === 1 ||
            str_starts_with($relativePath, 'data:')
        ) {
            return;
        }

        $relativePath = parse_url($relativePath, PHP_URL_PATH) ?: $relativePath;
        $relativePath = '/' . ltrim($relativePath, '/');

        if (!str_starts_with($relativePath, '/uploads/service-categories/')) {
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