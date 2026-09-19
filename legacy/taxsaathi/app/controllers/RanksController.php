<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class RanksController extends Controller
{
    public function index(): void
    {
        $this->authorizeGrowRanking();

        $db = app('db');

        $search = trim((string) input('q', ''));
        $status = trim((string) input('status', ''));
        $priority = trim((string) input('priority', ''));
        $targetType = trim((string) input('target_type', ''));

        $where = [];
        $params = [];

        if ($search !== '') {
            $where[] = '(g.keyword LIKE :search OR g.keyword_slug LIKE :search OR g.target_url LIKE :search OR g.target_slug LIKE :search OR s.title LIKE :search OR c.title LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        if ($status !== '' && in_array($status, ['new', 'monitoring', 'improving', 'declining', 'won', 'paused'], true)) {
            $where[] = 'g.status = :status';
            $params['status'] = $status;
        }

        if ($priority !== '' && in_array($priority, ['low', 'medium', 'high', 'critical'], true)) {
            $where[] = 'g.priority = :priority';
            $params['priority'] = $priority;
        }

        if ($targetType !== '' && in_array($targetType, ['service', 'service_category', 'page', 'custom'], true)) {
            $where[] = 'g.target_type = :target_type';
            $params['target_type'] = $targetType;
        }

        $whereSql = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

        $rankings = $db->fetchAll(
            "SELECT
                g.*,
                s.title AS service_title,
                s.slug AS service_slug,
                c.title AS category_title,
                c.slug AS category_slug,
                (
                    SELECT COUNT(*)
                    FROM grow_ranking_tasks t
                    WHERE t.ranking_keyword_id = g.id
                      AND t.status != 'done'
                ) AS open_task_count,
                (
                    SELECT COUNT(*)
                    FROM grow_ranking_logs l
                    WHERE l.ranking_keyword_id = g.id
                ) AS log_count
             FROM grow_ranking_keywords g
             LEFT JOIN services s
                ON g.target_type = 'service'
                AND g.target_id = s.id
             LEFT JOIN service_categories c
                ON g.target_type = 'service_category'
                AND g.target_id = c.id
             {$whereSql}
             ORDER BY
                g.is_active DESC,
                FIELD(g.priority, 'critical', 'high', 'medium', 'low'),
                COALESCE(g.current_rank, 9999) ASC,
                g.id DESC",
            $params
        ) ?: [];

        $rankingIds = array_map(static fn(array $row): int => (int) ($row['id'] ?? 0), $rankings);
        $logsByKeyword = $this->getLogsByKeyword($rankingIds);
        $tasksByKeyword = $this->getTasksByKeyword($rankingIds);

        $stats = [
            'total_keywords' => (int) $db->scalar('SELECT COUNT(*) FROM grow_ranking_keywords'),
            'active_keywords' => (int) $db->scalar('SELECT COUNT(*) FROM grow_ranking_keywords WHERE is_active = 1'),
            'top_10' => (int) $db->scalar('SELECT COUNT(*) FROM grow_ranking_keywords WHERE is_active = 1 AND current_rank BETWEEN 1 AND 10'),
            'improving' => (int) $db->scalar("SELECT COUNT(*) FROM grow_ranking_keywords WHERE is_active = 1 AND status = 'improving'"),
            'declining' => (int) $db->scalar("SELECT COUNT(*) FROM grow_ranking_keywords WHERE is_active = 1 AND status = 'declining'"),
            'open_tasks' => (int) $db->scalar("SELECT COUNT(*) FROM grow_ranking_tasks WHERE status != 'done'"),
            'average_rank' => (float) ($db->scalar('SELECT AVG(current_rank) FROM grow_ranking_keywords WHERE is_active = 1 AND current_rank IS NOT NULL') ?: 0),
        ];

        $this->view('admin/manage_rank', [
            'title' => 'Grow Ranking – Tax Saathi',
            'rankings' => $rankings,
            'logsByKeyword' => $logsByKeyword,
            'tasksByKeyword' => $tasksByKeyword,
            'services' => $this->getServicesForTargeting(),
            'categories' => $this->getCategoriesForTargeting(),
            'stats' => $stats,
            'filters' => [
                'q' => $search,
                'status' => $status,
                'priority' => $priority,
                'target_type' => $targetType,
            ],
        ], 'layouts/dashboard');
    }

    public function saveKeyword(): void
    {
        $this->authorizeGrowRanking();
        verify_csrf();

        $id = (int) input('id', 0);
        $keyword = $this->normalizeText((string) input('keyword', ''));

        if ($keyword === '') {
            flash('error', 'Ranking keyword is required.');
            redirect('admin/ranks');
            return;
        }

        $keywordSlug = $this->slugify($keyword);
        $target = $this->resolveTargetForRanking();
        $currentRank = $this->nullableRank(input('current_rank', null));
        $previousRank = $this->nullableRank(input('previous_rank', null));
        $bestRank = $this->nullableRank(input('best_rank', null));

        if ($bestRank === null) {
            $bestRank = $currentRank;
        }

        if ($this->keywordTargetExists($keywordSlug, $target['target_url'], (string) input('device', 'desktop'), (string) input('location', 'India'), $id)) {
            flash('error', 'This keyword is already being tracked for the same URL, location, and device.');
            redirect('admin/ranks');
            return;
        }

        $payload = [
            'keyword' => $keyword,
            'keyword_slug' => $keywordSlug,
            'target_type' => $target['target_type'],
            'target_id' => $target['target_id'],
            'target_slug' => $target['target_slug'],
            'target_url' => $target['target_url'],
            'search_engine' => $this->sanitizeSearchEngine((string) input('search_engine', 'Google')),
            'location' => $this->normalizeText((string) input('location', 'India')) ?: 'India',
            'device' => $this->sanitizeDevice((string) input('device', 'desktop')),
            'current_rank' => $currentRank,
            'previous_rank' => $previousRank,
            'best_rank' => $bestRank,
            'search_volume' => max(0, (int) input('search_volume', 0)),
            'keyword_difficulty' => min(100, max(0, (int) input('keyword_difficulty', 0))),
            'priority' => $this->sanitizePriority((string) input('priority', 'medium')),
            'status' => $this->sanitizeRankingStatus((string) input('status', 'monitoring')),
            'notes' => $this->nullIfEmpty((string) input('notes', '')),
            'is_active' => $this->checkbox('is_active'),
            'updated_at' => $this->now(),
        ];

        if ($currentRank !== null) {
            $payload['last_checked_at'] = $this->now();
        }

        if ($id > 0) {
            $existing = app('db')->fetch('SELECT id FROM grow_ranking_keywords WHERE id = :id LIMIT 1', ['id' => $id]);

            if (!$existing) {
                flash('error', 'Ranking keyword not found.');
                redirect('admin/ranks');
                return;
            }

            $this->dbUpdateRow('grow_ranking_keywords', $payload, ['id' => $id]);
            flash('success', 'Ranking keyword updated successfully.');
            redirect('admin/ranks');
            return;
        }

        $payload['created_at'] = $this->now();
        $newId = $this->dbInsertRow('grow_ranking_keywords', $payload);

        if ($currentRank !== null) {
            $this->dbInsertRow('grow_ranking_logs', [
                'ranking_keyword_id' => $newId,
                'rank_position' => $currentRank,
                'search_engine' => $payload['search_engine'],
                'location' => $payload['location'],
                'device' => $payload['device'],
                'checked_at' => $this->now(),
                'notes' => 'Initial rank entry',
                'created_at' => $this->now(),
            ]);
        }

        flash('success', 'Ranking keyword created successfully.');
        redirect('admin/ranks');
    }

    public function toggleKeyword(): void
    {
        $this->authorizeGrowRanking();
        verify_csrf();

        $id = (int) input('id', 0);

        if ($id <= 0) {
            flash('error', 'Invalid ranking keyword selected.');
            redirect('admin/ranks');
            return;
        }

        $row = app('db')->fetch('SELECT id, is_active FROM grow_ranking_keywords WHERE id = :id LIMIT 1', ['id' => $id]);

        if (!$row) {
            flash('error', 'Ranking keyword not found.');
            redirect('admin/ranks');
            return;
        }

        $this->dbUpdateRow('grow_ranking_keywords', [
            'is_active' => (int) $row['is_active'] === 1 ? 0 : 1,
            'updated_at' => $this->now(),
        ], ['id' => $id]);

        flash('success', 'Ranking keyword status updated.');
        redirect('admin/ranks');
    }

    public function deleteKeyword(): void
    {
        $this->authorizeGrowRanking();
        verify_csrf();

        $id = (int) input('id', 0);

        if ($id <= 0) {
            flash('error', 'Invalid ranking keyword selected.');
            redirect('admin/ranks');
            return;
        }

        $this->dbDeleteRow('grow_ranking_keywords', ['id' => $id]);

        flash('success', 'Ranking keyword and related logs/tasks deleted.');
        redirect('admin/ranks');
    }

    public function saveLog(): void
    {
        $this->authorizeGrowRanking();
        verify_csrf();

        $keywordId = (int) input('ranking_keyword_id', 0);
        $rank = $this->nullableRank(input('rank_position', null));

        if ($keywordId <= 0) {
            flash('error', 'Invalid ranking keyword selected.');
            redirect('admin/ranks');
            return;
        }

        $keyword = app('db')->fetch('SELECT * FROM grow_ranking_keywords WHERE id = :id LIMIT 1', ['id' => $keywordId]);

        if (!$keyword) {
            flash('error', 'Ranking keyword not found.');
            redirect('admin/ranks');
            return;
        }

        $checkedAt = trim((string) input('checked_at', ''));
        $checkedAt = $checkedAt !== '' ? date('Y-m-d H:i:s', strtotime($checkedAt)) : $this->now();

        $this->dbInsertRow('grow_ranking_logs', [
            'ranking_keyword_id' => $keywordId,
            'rank_position' => $rank,
            'search_engine' => $this->sanitizeSearchEngine((string) input('search_engine', $keyword['search_engine'] ?? 'Google')),
            'location' => $this->normalizeText((string) input('location', $keyword['location'] ?? 'India')) ?: 'India',
            'device' => $this->sanitizeDevice((string) input('device', $keyword['device'] ?? 'desktop')),
            'checked_at' => $checkedAt,
            'notes' => $this->nullIfEmpty((string) input('notes', '')),
            'created_at' => $this->now(),
        ]);

        $currentRank = isset($keyword['current_rank']) ? $this->nullableRank($keyword['current_rank']) : null;
        $bestRank = isset($keyword['best_rank']) ? $this->nullableRank($keyword['best_rank']) : null;
        $newBest = $bestRank;

        if ($rank !== null && ($newBest === null || $rank < $newBest)) {
            $newBest = $rank;
        }

        $newStatus = $this->calculateRankingStatus($rank, $currentRank);

        $this->dbUpdateRow('grow_ranking_keywords', [
            'previous_rank' => $currentRank,
            'current_rank' => $rank,
            'best_rank' => $newBest,
            'status' => $newStatus,
            'last_checked_at' => $checkedAt,
            'updated_at' => $this->now(),
        ], ['id' => $keywordId]);

        flash('success', 'Rank log added and current position updated.');
        redirect('admin/ranks');
    }

    public function deleteLog(): void
    {
        $this->authorizeGrowRanking();
        verify_csrf();

        $id = (int) input('id', 0);

        if ($id > 0) {
            $this->dbDeleteRow('grow_ranking_logs', ['id' => $id]);
            flash('success', 'Ranking log deleted.');
        }

        redirect('admin/ranks');
    }

    public function saveTask(): void
    {
        $this->authorizeGrowRanking();
        verify_csrf();

        $id = (int) input('id', 0);
        $title = $this->normalizeText((string) input('task_title', ''));

        if ($title === '') {
            flash('error', 'Task title is required.');
            redirect('admin/ranks');
            return;
        }

        $keywordId = (int) input('ranking_keyword_id', 0);

        if ($keywordId <= 0) {
            $keywordId = null;
        }

        $payload = [
            'ranking_keyword_id' => $keywordId,
            'task_title' => $title,
            'task_type' => $this->sanitizeTaskType((string) input('task_type', 'on_page')),
            'priority' => $this->sanitizePriority((string) input('priority', 'medium')),
            'status' => $this->sanitizeTaskStatus((string) input('status', 'todo')),
            'due_date' => $this->nullIfEmpty((string) input('due_date', '')),
            'assigned_to' => $this->nullIfEmpty((string) input('assigned_to', '')),
            'notes' => $this->nullIfEmpty((string) input('notes', '')),
            'updated_at' => $this->now(),
        ];

        if ($id > 0) {
            $this->dbUpdateRow('grow_ranking_tasks', $payload, ['id' => $id]);
            flash('success', 'Growth task updated.');
        } else {
            $payload['created_at'] = $this->now();
            $this->dbInsertRow('grow_ranking_tasks', $payload);
            flash('success', 'Growth task created.');
        }

        redirect('admin/ranks');
    }

    public function deleteTask(): void
    {
        $this->authorizeGrowRanking();
        verify_csrf();

        $id = (int) input('id', 0);

        if ($id > 0) {
            $this->dbDeleteRow('grow_ranking_tasks', ['id' => $id]);
            flash('success', 'Growth task deleted.');
        }

        redirect('admin/ranks');
    }

    public function generateFromServices(): void
    {
        $this->authorizeGrowRanking();
        verify_csrf();

        $services = $this->getServicesForTargeting(true);
        $created = 0;
        $skipped = 0;

        foreach ($services as $service) {
            $title = $this->normalizeText((string) ($service['title'] ?? ''));
            $slug = trim((string) ($service['slug'] ?? ''));

            if ($title === '' || $slug === '') {
                continue;
            }

            $keywords = array_values(array_unique(array_filter([
                $title,
                str_replace('-', ' ', $slug),
                $title . ' online',
                $title . ' service',
                $title . ' in India',
            ])));

            foreach ($keywords as $index => $keyword) {
                $keyword = $this->normalizeText($keyword);
                $keywordSlug = $this->slugify($keyword);
                $targetUrl = '/services/' . ltrim($slug, '/');

                if ($keywordSlug === '' || $this->keywordTargetExists($keywordSlug, $targetUrl, 'desktop', 'India')) {
                    $skipped++;
                    continue;
                }

                $this->dbInsertRow('grow_ranking_keywords', [
                    'keyword' => $keyword,
                    'keyword_slug' => $keywordSlug,
                    'target_type' => 'service',
                    'target_id' => (int) $service['id'],
                    'target_slug' => $slug,
                    'target_url' => $targetUrl,
                    'search_engine' => 'Google',
                    'location' => 'India',
                    'device' => 'desktop',
                    'current_rank' => null,
                    'previous_rank' => null,
                    'best_rank' => null,
                    'search_volume' => 0,
                    'keyword_difficulty' => 0,
                    'priority' => ((int) ($service['is_featured'] ?? 0) === 1 || $index === 0) ? 'high' : 'medium',
                    'status' => 'new',
                    'notes' => 'Generated from active service: ' . $title,
                    'is_active' => 1,
                    'last_checked_at' => null,
                    'created_at' => $this->now(),
                    'updated_at' => $this->now(),
                ]);

                $created++;
            }
        }

        flash('success', 'Generated ranking tracker keywords from active services. Created: ' . $created . '. Existing skipped: ' . $skipped . '.');
        redirect('admin/ranks');
    }

    private function resolveTargetForRanking(): array
    {
        $targetType = trim((string) input('target_type', 'custom'));

        if (!in_array($targetType, ['service', 'service_category', 'page', 'custom'], true)) {
            $targetType = 'custom';
        }

        $targetId = null;
        $targetSlug = null;
        $targetUrl = trim((string) input('target_url', ''));

        if ($targetType === 'service') {
            $serviceId = (int) input('service_id', 0);
            $service = app('db')->fetch('SELECT id, title, slug FROM services WHERE id = :id LIMIT 1', ['id' => $serviceId]);

            if (!$service) {
                flash('error', 'Please select a valid service target.');
                redirect('admin/ranks');
                exit;
            }

            $targetId = (int) $service['id'];
            $targetSlug = (string) $service['slug'];
            $targetUrl = '/services/' . ltrim((string) $service['slug'], '/');
        }

        if ($targetType === 'service_category') {
            $categoryId = (int) input('category_id', 0);
            $category = app('db')->fetch('SELECT id, title, slug FROM service_categories WHERE id = :id LIMIT 1', ['id' => $categoryId]);

            if (!$category) {
                flash('error', 'Please select a valid category target.');
                redirect('admin/ranks');
                exit;
            }

            $targetId = (int) $category['id'];
            $targetSlug = (string) $category['slug'];
            $targetUrl = '/service-category?category=' . urlencode((string) $category['slug']);
        }

        if ($targetType === 'page') {
            $targetUrl = '/' . ltrim($targetUrl !== '' ? $targetUrl : (string) input('page_path', '/'), '/');
            $targetSlug = $this->slugify($targetUrl);
        }

        if ($targetUrl === '') {
            flash('error', 'Target URL is required.');
            redirect('admin/ranks');
            exit;
        }

        return [
            'target_type' => $targetType,
            'target_id' => $targetId,
            'target_slug' => $targetSlug,
            'target_url' => $targetUrl,
        ];
    }

    private function getServicesForTargeting(bool $activeOnly = false): array
    {
        $where = $activeOnly ? 'WHERE s.is_active = 1' : '';

        return app('db')->fetchAll(
            "SELECT s.id, s.title, s.slug, s.excerpt, s.service_category, s.service_category_id, s.is_featured, s.is_active, s.sort_order,
                    c.title AS service_category_title, c.slug AS service_category_slug
             FROM services s
             LEFT JOIN service_categories c ON c.id = s.service_category_id
             {$where}
             ORDER BY s.is_active DESC, s.sort_order ASC, s.title ASC"
        ) ?: [];
    }

    private function getCategoriesForTargeting(): array
    {
        return app('db')->fetchAll(
            'SELECT id, title, slug, is_active, sort_order
             FROM service_categories
             ORDER BY is_active DESC, sort_order ASC, title ASC'
        ) ?: [];
    }

    private function getLogsByKeyword(array $rankingIds): array
    {
        $rankingIds = array_values(array_filter(array_map('intval', $rankingIds)));

        if ($rankingIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($rankingIds), '?'));

        try {
            $rows = app('db')->fetchAll(
                "SELECT *
                 FROM grow_ranking_logs
                 WHERE ranking_keyword_id IN ({$placeholders})
                 ORDER BY checked_at DESC, id DESC",
                $rankingIds
            ) ?: [];
        } catch (\Throwable $e) {
            return [];
        }

        $grouped = [];

        foreach ($rows as $row) {
            $keywordId = (int) ($row['ranking_keyword_id'] ?? 0);
            $grouped[$keywordId][] = $row;
        }

        return $grouped;
    }

    private function getTasksByKeyword(array $rankingIds): array
    {
        $rankingIds = array_values(array_filter(array_map('intval', $rankingIds)));

        if ($rankingIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($rankingIds), '?'));

        try {
            $rows = app('db')->fetchAll(
                "SELECT *
                 FROM grow_ranking_tasks
                 WHERE ranking_keyword_id IN ({$placeholders}) OR ranking_keyword_id IS NULL
                 ORDER BY FIELD(status, 'todo', 'in_progress', 'blocked', 'done'), due_date ASC, id DESC",
                $rankingIds
            ) ?: [];
        } catch (\Throwable $e) {
            return [];
        }

        $grouped = ['global' => []];

        foreach ($rows as $row) {
            $keywordId = (int) ($row['ranking_keyword_id'] ?? 0);

            if ($keywordId > 0) {
                $grouped[$keywordId][] = $row;
            } else {
                $grouped['global'][] = $row;
            }
        }

        return $grouped;
    }

    private function keywordTargetExists(string $keywordSlug, string $targetUrl, string $device = 'desktop', string $location = 'India', int $exceptId = 0): bool
    {
        $row = app('db')->fetch(
            "SELECT id
             FROM grow_ranking_keywords
             WHERE keyword_slug = :keyword_slug
               AND target_url = :target_url
               AND device = :device
               AND location = :location
               AND id != :except_id
             LIMIT 1",
            [
                'keyword_slug' => $keywordSlug,
                'target_url' => $targetUrl,
                'device' => $this->sanitizeDevice($device),
                'location' => $this->normalizeText($location) ?: 'India',
                'except_id' => $exceptId,
            ]
        );

        return is_array($row) && !empty($row['id']);
    }

    private function calculateRankingStatus(?int $newRank, ?int $oldRank): string
    {
        if ($newRank !== null && $newRank >= 1 && $newRank <= 3) {
            return 'won';
        }

        if ($oldRank === null || $newRank === null) {
            return 'monitoring';
        }

        if ($newRank < $oldRank) {
            return 'improving';
        }

        if ($newRank > $oldRank) {
            return 'declining';
        }

        return 'monitoring';
    }

    private function nullableRank($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $rank = (int) $value;

        return $rank > 0 ? $rank : null;
    }

    private function sanitizeSearchEngine(string $value): string
    {
        $value = $this->normalizeText($value);

        return $value !== '' ? $value : 'Google';
    }

    private function sanitizeDevice(string $value): string
    {
        return in_array($value, ['desktop', 'mobile'], true) ? $value : 'desktop';
    }

    private function sanitizePriority(string $value): string
    {
        return in_array($value, ['low', 'medium', 'high', 'critical'], true) ? $value : 'medium';
    }

    private function sanitizeRankingStatus(string $value): string
    {
        return in_array($value, ['new', 'monitoring', 'improving', 'declining', 'won', 'paused'], true) ? $value : 'monitoring';
    }

    private function sanitizeTaskType(string $value): string
    {
        return in_array($value, ['on_page', 'content', 'technical', 'backlink', 'internal_link', 'local_seo', 'monitoring'], true) ? $value : 'on_page';
    }

    private function sanitizeTaskStatus(string $value): string
    {
        return in_array($value, ['todo', 'in_progress', 'done', 'blocked'], true) ? $value : 'todo';
    }

    private function normalizeText(string $value): string
    {
        $value = strip_tags($value);
        $value = preg_replace('/\s+/', ' ', $value) ?? '';

        return trim($value);
    }

    private function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/i', '-', $value) ?? '';

        return trim($value, '-');
    }

    private function nullIfEmpty(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function checkbox(string $key): int
    {
        return isset($_POST[$key]) ? 1 : 0;
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    private function authorizeGrowRanking(): void
    {
        if (function_exists('can')) {
            foreach (['services.manage', 'website.manage', 'content.manage', 'admin.services.manage', 'admin.content.manage', 'reports.manage'] as $permission) {
                try {
                    if ((bool) can($permission)) {
                        return;
                    }
                } catch (\Throwable $e) {
                    // Continue to default gate.
                }
            }
        }

        if (function_exists('require_permission')) {
            require_permission('services.manage');
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
        $placeholders = array_map(static fn(string $column): string => ':' . $column, $columns);

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