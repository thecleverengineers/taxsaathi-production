<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class AdminSeoKeywordController extends Controller
{
    public function index(): void
    {
        require_permission('website.manage');

        $db = app('db');

        $search = trim((string) input('q', ''));
        $targetType = trim((string) input('target_type', ''));
        $status = trim((string) input('status', ''));

        $where = [];
        $params = [];

        if ($search !== '') {
            $where[] = '(k.keyword LIKE :search OR k.keyword_slug LIKE :search OR k.target_slug LIKE :search OR k.meta_title LIKE :search OR k.meta_description LIKE :search OR s.title LIKE :search OR c.title LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        if ($targetType !== '' && in_array($targetType, ['global', 'page', 'service', 'service_category'], true)) {
            $where[] = 'k.target_type = :target_type';
            $params['target_type'] = $targetType;
        }

        if ($status !== '' && in_array($status, ['0', '1'], true)) {
            $where[] = 'k.is_active = :is_active';
            $params['is_active'] = (int) $status;
        }

        $whereSql = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

        $keywords = $db->fetchAll(
            "SELECT
                k.*,
                s.title AS service_title,
                s.slug AS service_slug,
                c.title AS category_title,
                c.slug AS category_slug
             FROM seo_keywords k
             LEFT JOIN services s
                ON k.target_type = 'service'
                AND k.target_id = s.id
             LEFT JOIN service_categories c
                ON k.target_type = 'service_category'
                AND k.target_id = c.id
             {$whereSql}
             ORDER BY k.is_active DESC, k.is_primary DESC, k.sort_order ASC, k.id DESC",
            $params
        ) ?: [];

        $stats = [
            'total_keywords' => (int) $db->scalar('SELECT COUNT(*) FROM seo_keywords'),
            'active_keywords' => (int) $db->scalar('SELECT COUNT(*) FROM seo_keywords WHERE is_active = 1'),
            'primary_keywords' => (int) $db->scalar('SELECT COUNT(*) FROM seo_keywords WHERE is_primary = 1 AND is_active = 1'),
            'service_keywords' => (int) $db->scalar("SELECT COUNT(*) FROM seo_keywords WHERE target_type = 'service' AND is_active = 1"),
        ];

        $this->view('admin/seo_keywords', [
            'title' => 'SEO Keyword Management – Tax Saathi',
            'keywords' => $keywords,
            'services' => $this->getServices(),
            'categories' => $this->getServiceCategories(),
            'stats' => $stats,
            'filters' => [
                'q' => $search,
                'target_type' => $targetType,
                'status' => $status,
            ],
        ], 'layouts/dashboard');
    }

    public function save(): void
    {
        require_permission('website.manage');
        verify_csrf();

        $id = (int) input('id', 0);
        $keywordsRaw = trim((string) input('keyword', ''));

        if ($keywordsRaw === '') {
            flash('error', 'Please enter at least one SEO keyword.');
            redirect('admin/seo-keywords');
            return;
        }

        $target = $this->resolveTarget();
        $keywordType = $this->sanitizeKeywordType((string) input('keyword_type', 'secondary'));
        $sortOrder = (int) input('sort_order', 1);
        $isPrimary = $this->checkbox('is_primary');
        $isActive = $this->checkbox('is_active');

        $metaTitle = $this->nullIfEmpty((string) input('meta_title', '')) ?? $target['default_meta_title'];
        $metaDescription = $this->nullIfEmpty((string) input('meta_description', '')) ?? $target['default_meta_description'];
        $canonicalUrl = $this->nullIfEmpty((string) input('canonical_url', '')) ?? $target['default_canonical_url'];

        if ($id > 0) {
            $keyword = $this->normalizeKeyword($keywordsRaw);

            if ($keyword === '') {
                flash('error', 'Invalid SEO keyword.');
                redirect('admin/seo-keywords');
                return;
            }

            $keywordSlug = $this->slugify($keyword);

            if ($this->keywordExists($keywordSlug, $target['target_type'], $target['target_id'], $target['page_path'], $id)) {
                flash('error', 'This keyword already exists for the selected target.');
                redirect('admin/seo-keywords');
                return;
            }

            $payload = [
                'keyword' => $keyword,
                'keyword_slug' => $keywordSlug,
                'keyword_type' => $keywordType,
                'target_type' => $target['target_type'],
                'target_id' => $target['target_id'],
                'target_slug' => $target['target_slug'],
                'page_path' => $target['page_path'],
                'meta_title' => $metaTitle,
                'meta_description' => $metaDescription,
                'canonical_url' => $canonicalUrl,
                'is_primary' => $isPrimary,
                'is_active' => $isActive,
                'sort_order' => $sortOrder,
                'updated_at' => $this->now(),
            ];

            $this->dbUpdateRow('seo_keywords', $payload, ['id' => $id]);

            if ($isPrimary === 1) {
                $this->unsetOtherPrimaryKeywords($id, $target['target_type'], $target['target_id'], $target['page_path']);
            }

            flash('success', 'SEO keyword updated successfully.');
            redirect('admin/seo-keywords');
            return;
        }

        $keywords = $this->splitKeywords($keywordsRaw);

        if ($keywords === []) {
            flash('error', 'Please enter valid SEO keywords.');
            redirect('admin/seo-keywords');
            return;
        }

        $created = 0;
        $skipped = 0;

        foreach ($keywords as $index => $keyword) {
            $keywordSlug = $this->slugify($keyword);

            if ($keywordSlug === '' || $this->keywordExists($keywordSlug, $target['target_type'], $target['target_id'], $target['page_path'])) {
                $skipped++;
                continue;
            }

            $payload = [
                'keyword' => $keyword,
                'keyword_slug' => $keywordSlug,
                'keyword_type' => $index === 0 && $isPrimary === 1 ? 'primary' : $keywordType,
                'target_type' => $target['target_type'],
                'target_id' => $target['target_id'],
                'target_slug' => $target['target_slug'],
                'page_path' => $target['page_path'],
                'meta_title' => $metaTitle,
                'meta_description' => $metaDescription,
                'canonical_url' => $canonicalUrl,
                'is_primary' => $index === 0 ? $isPrimary : 0,
                'is_active' => $isActive,
                'sort_order' => $sortOrder + $index,
                'created_at' => $this->now(),
                'updated_at' => $this->now(),
            ];

            $newId = $this->dbInsertRow('seo_keywords', $payload);

            if ($payload['is_primary'] === 1) {
                $this->unsetOtherPrimaryKeywords($newId, $target['target_type'], $target['target_id'], $target['page_path']);
            }

            $created++;
        }

        flash('success', 'SEO keywords saved. Created: ' . $created . '. Skipped duplicate: ' . $skipped . '.');
        redirect('admin/seo-keywords');
    }

    public function toggle(): void
    {
        require_permission('website.manage');
        verify_csrf();

        $db = app('db');
        $id = (int) input('id', 0);

        if ($id <= 0) {
            flash('error', 'Invalid keyword selected.');
            redirect('admin/seo-keywords');
            return;
        }

        $keyword = $db->fetch('SELECT id, is_active FROM seo_keywords WHERE id = :id LIMIT 1', ['id' => $id]);

        if (!$keyword) {
            flash('error', 'SEO keyword not found.');
            redirect('admin/seo-keywords');
            return;
        }

        $this->dbUpdateRow('seo_keywords', [
            'is_active' => (int) $keyword['is_active'] === 1 ? 0 : 1,
            'updated_at' => $this->now(),
        ], ['id' => $id]);

        flash('success', 'SEO keyword status updated.');
        redirect('admin/seo-keywords');
    }

    public function delete(): void
    {
        require_permission('website.manage');
        verify_csrf();

        $id = (int) input('id', 0);

        if ($id <= 0) {
            flash('error', 'Invalid keyword selected.');
            redirect('admin/seo-keywords');
            return;
        }

        $this->dbDeleteRow('seo_keywords', ['id' => $id]);

        flash('success', 'SEO keyword deleted.');
        redirect('admin/seo-keywords');
    }

    public function generateFromServices(): void
    {
        require_permission('website.manage');
        verify_csrf();

        $services = $this->getServices(true);

        $created = 0;
        $skipped = 0;

        foreach ($services as $service) {
            $target = [
                'target_type' => 'service',
                'target_id' => (int) $service['id'],
                'target_slug' => (string) $service['slug'],
                'page_path' => null,
                'default_meta_title' => $this->makeServiceMetaTitle($service),
                'default_meta_description' => $this->makeServiceMetaDescription($service),
                'default_canonical_url' => '/services/' . ltrim((string) $service['slug'], '/'),
            ];

            $keywords = $this->keywordsFromService($service);

            foreach ($keywords as $index => $keyword) {
                $keywordSlug = $this->slugify($keyword);

                if ($keywordSlug === '' || $this->keywordExists($keywordSlug, 'service', (int) $service['id'], null)) {
                    $skipped++;
                    continue;
                }

                $payload = [
                    'keyword' => $keyword,
                    'keyword_slug' => $keywordSlug,
                    'keyword_type' => $index === 0 ? 'primary' : ($index >= 3 ? 'long_tail' : 'secondary'),
                    'target_type' => 'service',
                    'target_id' => (int) $service['id'],
                    'target_slug' => (string) $service['slug'],
                    'page_path' => null,
                    'meta_title' => $target['default_meta_title'],
                    'meta_description' => $target['default_meta_description'],
                    'canonical_url' => $target['default_canonical_url'],
                    'is_primary' => $index === 0 ? 1 : 0,
                    'is_active' => 1,
                    'sort_order' => $index + 1,
                    'created_at' => $this->now(),
                    'updated_at' => $this->now(),
                ];

                $newId = $this->dbInsertRow('seo_keywords', $payload);

                if ($payload['is_primary'] === 1) {
                    $this->unsetOtherPrimaryKeywords($newId, 'service', (int) $service['id'], null);
                }

                $created++;
            }
        }

        flash('success', 'Generated SEO keywords from active services. Created: ' . $created . '. Existing skipped: ' . $skipped . '.');
        redirect('admin/seo-keywords?target_type=service&status=1');
    }

    private function resolveTarget(): array
    {
        $targetType = trim((string) input('target_type', 'global'));

        if (!in_array($targetType, ['global', 'page', 'service', 'service_category'], true)) {
            $targetType = 'global';
        }

        $siteName = trim((string) setting('site_name', 'Tax Saathi'));
        $targetId = null;
        $targetSlug = null;
        $pagePath = null;
        $defaultMetaTitle = $siteName . ' | Tax Filing, GST, ROC & Compliance Services';
        $defaultMetaDescription = 'Tax Saathi provides online tax filing, GST, ROC, registration, compliance and accounting services.';
        $defaultCanonicalUrl = '/';

        if ($targetType === 'service') {
            $serviceId = (int) input('service_id', 0);
            $service = app('db')->fetch(
                'SELECT id, title, slug, excerpt, description, service_category FROM services WHERE id = :id LIMIT 1',
                ['id' => $serviceId]
            );

            if (!$service) {
                flash('error', 'Please select a valid service.');
                redirect('admin/seo-keywords');
                exit;
            }

            $targetId = (int) $service['id'];
            $targetSlug = (string) $service['slug'];
            $defaultMetaTitle = $this->makeServiceMetaTitle($service);
            $defaultMetaDescription = $this->makeServiceMetaDescription($service);
            $defaultCanonicalUrl = '/services/' . ltrim((string) $service['slug'], '/');
        }

        if ($targetType === 'service_category') {
            $categoryId = (int) input('category_id', 0);
            $category = app('db')->fetch(
                'SELECT id, title, slug, description FROM service_categories WHERE id = :id LIMIT 1',
                ['id' => $categoryId]
            );

            if (!$category) {
                flash('error', 'Please select a valid service category.');
                redirect('admin/seo-keywords');
                exit;
            }

            $targetId = (int) $category['id'];
            $targetSlug = (string) $category['slug'];
            $defaultMetaTitle = trim((string) $category['title']) . ' | ' . $siteName;
            $defaultMetaDescription = $this->limitText((string) ($category['description'] ?? ''), 155)
                ?: 'Explore ' . trim((string) $category['title']) . ' services with ' . $siteName . '.';
            $defaultCanonicalUrl = '/service-category?category=' . urlencode((string) $category['slug']);
        }

        if ($targetType === 'page') {
            $pagePath = '/' . ltrim(trim((string) input('page_path', '/')), '/');
            $targetSlug = $this->slugify($pagePath);
            $pageName = ucwords(str_replace(['-', '_', '/'], ' ', trim($pagePath, '/'))) ?: 'Home';
            $defaultMetaTitle = $pageName . ' | ' . $siteName;
            $defaultMetaDescription = $pageName . ' page of ' . $siteName . '.';
            $defaultCanonicalUrl = $pagePath;
        }

        return [
            'target_type' => $targetType,
            'target_id' => $targetId,
            'target_slug' => $targetSlug,
            'page_path' => $pagePath,
            'default_meta_title' => $defaultMetaTitle,
            'default_meta_description' => $defaultMetaDescription,
            'default_canonical_url' => $defaultCanonicalUrl,
        ];
    }

    private function getServices(bool $activeOnly = false): array
    {
        $where = $activeOnly ? 'WHERE is_active = 1' : '';

        return app('db')->fetchAll(
            "SELECT id, title, slug, excerpt, description, service_category, service_category_id, is_active, sort_order
             FROM services
             {$where}
             ORDER BY is_active DESC, sort_order ASC, title ASC"
        ) ?: [];
    }

    private function getServiceCategories(): array
    {
        return app('db')->fetchAll(
            'SELECT id, title, slug, description, is_active, sort_order
             FROM service_categories
             ORDER BY is_active DESC, sort_order ASC, title ASC'
        ) ?: [];
    }

    private function keywordsFromService(array $service): array
    {
        $title = $this->normalizeKeyword((string) ($service['title'] ?? ''));
        $slugAsKeyword = $this->normalizeKeyword(str_replace('-', ' ', (string) ($service['slug'] ?? '')));
        $category = $this->normalizeKeyword((string) ($service['service_category'] ?? ''));

        $keywords = [
            $title,
            $slugAsKeyword,
            $title . ' online',
            $title . ' service',
            $title . ' in India',
            $title . ' Tax Saathi',
        ];

        if ($category !== '') {
            $keywords[] = $category . ' ' . $title;
            $keywords[] = $title . ' under ' . $category;
        }

        $clean = [];

        foreach ($keywords as $keyword) {
            $keyword = $this->normalizeKeyword($keyword);
            if ($keyword === '') {
                continue;
            }

            $key = $this->slugify($keyword);
            $clean[$key] = $keyword;
        }

        return array_values($clean);
    }

    private function makeServiceMetaTitle(array $service): string
    {
        $siteName = trim((string) setting('site_name', 'Tax Saathi'));
        $title = trim((string) ($service['title'] ?? 'Service'));

        return $this->limitText($title . ' Online | ' . $siteName, 60);
    }

    private function makeServiceMetaDescription(array $service): string
    {
        $title = trim((string) ($service['title'] ?? 'Service'));
        $excerpt = trim((string) ($service['excerpt'] ?? ''));
        $category = trim((string) ($service['service_category'] ?? ''));

        if ($excerpt !== '') {
            return $this->limitText($excerpt . ' Apply online with Tax Saathi.', 155);
        }

        return $this->limitText($title . ($category !== '' ? ' under ' . $category : '') . ' with online assistance, document checklist and filing support by Tax Saathi.', 155);
    }

    private function keywordExists(string $keywordSlug, string $targetType, ?int $targetId, ?string $pagePath, int $exceptId = 0): bool
    {
        $row = app('db')->fetch(
            "SELECT id
             FROM seo_keywords
             WHERE keyword_slug = :keyword_slug
               AND target_type = :target_type
               AND COALESCE(target_id, 0) = :target_id
               AND COALESCE(page_path, '') = :page_path
               AND id != :except_id
             LIMIT 1",
            [
                'keyword_slug' => $keywordSlug,
                'target_type' => $targetType,
                'target_id' => (int) ($targetId ?? 0),
                'page_path' => (string) ($pagePath ?? ''),
                'except_id' => $exceptId,
            ]
        );

        return is_array($row) && !empty($row['id']);
    }

    private function unsetOtherPrimaryKeywords(int $currentId, string $targetType, ?int $targetId, ?string $pagePath): void
    {
        $this->dbWrite(
            "UPDATE seo_keywords
             SET is_primary = 0, updated_at = :updated_at
             WHERE id != :id
               AND target_type = :target_type
               AND COALESCE(target_id, 0) = :target_id
               AND COALESCE(page_path, '') = :page_path",
            [
                'updated_at' => $this->now(),
                'id' => $currentId,
                'target_type' => $targetType,
                'target_id' => (int) ($targetId ?? 0),
                'page_path' => (string) ($pagePath ?? ''),
            ]
        );
    }

    private function splitKeywords(string $keywordsRaw): array
    {
        $parts = preg_split('/[\r\n,]+/', $keywordsRaw) ?: [];
        $keywords = [];

        foreach ($parts as $part) {
            $keyword = $this->normalizeKeyword((string) $part);
            if ($keyword === '') {
                continue;
            }

            $keywords[$this->slugify($keyword)] = $keyword;
        }

        return array_values($keywords);
    }

    private function normalizeKeyword(string $keyword): string
    {
        $keyword = strip_tags($keyword);
        $keyword = preg_replace('/\s+/', ' ', $keyword) ?? '';
        return trim($keyword);
    }

    private function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/i', '-', $value) ?? '';
        return trim($value, '-');
    }

    private function sanitizeKeywordType(string $type): string
    {
        return in_array($type, ['primary', 'secondary', 'long_tail', 'local', 'semantic'], true)
            ? $type
            : 'secondary';
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

    private function limitText(string $value, int $limit): string
    {
        $value = trim(preg_replace('/\s+/', ' ', strip_tags($value)) ?? '');

        if ($value === '') {
            return '';
        }

        if (function_exists('mb_strlen') && mb_strlen($value) > $limit) {
            return rtrim(mb_substr($value, 0, $limit - 1)) . '…';
        }

        if (!function_exists('mb_strlen') && strlen($value) > $limit) {
            return rtrim(substr($value, 0, $limit - 1)) . '…';
        }

        return $value;
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
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
