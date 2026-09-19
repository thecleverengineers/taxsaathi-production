<?php
declare(strict_types=1);

namespace App\Services;

final class SeoKeywordService
{
    public static function forService(array $service): array
    {
        $serviceId = (int) ($service['id'] ?? 0);
        $serviceSlug = trim((string) ($service['slug'] ?? ''));

        $keywords = self::fetchKeywords('service', $serviceId, $serviceSlug, null);

        if ($keywords === []) {
            $keywords = self::fallbackServiceKeywords($service);
        }

        return self::uniqueKeywords($keywords);
    }

    public static function forCategory(array $category): array
    {
        $categoryId = (int) ($category['id'] ?? 0);
        $categorySlug = trim((string) ($category['slug'] ?? ''));

        $keywords = self::fetchKeywords('service_category', $categoryId, $categorySlug, null);

        if ($keywords === []) {
            $keywords = [
                (string) ($category['title'] ?? ''),
                str_replace('-', ' ', $categorySlug),
                trim((string) ($category['title'] ?? '')) . ' services',
                trim((string) ($category['title'] ?? '')) . ' Tax Saathi',
            ];
        }

        return self::uniqueKeywords($keywords);
    }

    public static function forPage(string $pagePath): array
    {
        $pagePath = '/' . ltrim(trim($pagePath), '/');

        return self::uniqueKeywords(self::fetchKeywords('page', null, null, $pagePath));
    }

    public static function global(): array
    {
        return self::uniqueKeywords(self::fetchKeywords('global', null, null, null));
    }

    public static function metaForService(array $service): array
    {
        $serviceId = (int) ($service['id'] ?? 0);
        $serviceSlug = trim((string) ($service['slug'] ?? ''));

        $primary = self::fetchPrimaryRow('service', $serviceId, $serviceSlug, null);

        $title = trim((string) ($primary['meta_title'] ?? ''));
        $description = trim((string) ($primary['meta_description'] ?? ''));
        $canonical = trim((string) ($primary['canonical_url'] ?? ''));

        $serviceTitle = trim((string) ($service['title'] ?? 'Service'));
        $siteName = function_exists('setting') ? trim((string) setting('site_name', 'Tax Saathi')) : 'Tax Saathi';

        if ($title === '') {
            $title = self::limitText($serviceTitle . ' Online | ' . $siteName, 60);
        }

        if ($description === '') {
            $excerpt = trim((string) ($service['excerpt'] ?? ''));
            $description = $excerpt !== ''
                ? self::limitText($excerpt . ' Apply online with ' . $siteName . '.', 155)
                : self::limitText($serviceTitle . ' service with online assistance, document checklist and filing support by ' . $siteName . '.', 155);
        }

        if ($canonical === '') {
            $canonical = '/services/' . ltrim($serviceSlug, '/');
        }

        return [
            'title' => $title,
            'description' => $description,
            'keywords' => implode(', ', self::forService($service)),
            'canonical' => $canonical,
        ];
    }

    public static function metaForPage(string $pagePath, string $defaultTitle = '', string $defaultDescription = ''): array
    {
        $pagePath = '/' . ltrim(trim($pagePath), '/');
        $primary = self::fetchPrimaryRow('page', null, null, $pagePath);

        $keywords = self::forPage($pagePath);
        $globalKeywords = self::global();

        return [
            'title' => trim((string) ($primary['meta_title'] ?? '')) ?: $defaultTitle,
            'description' => trim((string) ($primary['meta_description'] ?? '')) ?: $defaultDescription,
            'keywords' => implode(', ', self::uniqueKeywords(array_merge($keywords, $globalKeywords))),
            'canonical' => trim((string) ($primary['canonical_url'] ?? '')) ?: $pagePath,
        ];
    }

    private static function fetchKeywords(string $targetType, ?int $targetId, ?string $targetSlug, ?string $pagePath): array
    {
        try {
            $params = [
                'target_type' => $targetType,
                'target_id' => (int) ($targetId ?? 0),
                'target_slug' => (string) ($targetSlug ?? ''),
                'page_path' => (string) ($pagePath ?? ''),
            ];

            $rows = app('db')->fetchAll(
                "SELECT keyword
                 FROM seo_keywords
                 WHERE is_active = 1
                   AND target_type = :target_type
                   AND COALESCE(target_id, 0) = :target_id
                   AND COALESCE(target_slug, '') = :target_slug
                   AND COALESCE(page_path, '') = :page_path
                 ORDER BY is_primary DESC, sort_order ASC, id ASC",
                $params
            ) ?: [];

            return array_map(static fn(array $row): string => (string) ($row['keyword'] ?? ''), $rows);
        } catch (\Throwable $e) {
            return [];
        }
    }

    private static function fetchPrimaryRow(string $targetType, ?int $targetId, ?string $targetSlug, ?string $pagePath): array
    {
        try {
            $row = app('db')->fetch(
                "SELECT *
                 FROM seo_keywords
                 WHERE is_active = 1
                   AND target_type = :target_type
                   AND COALESCE(target_id, 0) = :target_id
                   AND COALESCE(target_slug, '') = :target_slug
                   AND COALESCE(page_path, '') = :page_path
                 ORDER BY is_primary DESC, sort_order ASC, id ASC
                 LIMIT 1",
                [
                    'target_type' => $targetType,
                    'target_id' => (int) ($targetId ?? 0),
                    'target_slug' => (string) ($targetSlug ?? ''),
                    'page_path' => (string) ($pagePath ?? ''),
                ]
            );

            return is_array($row) ? $row : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private static function fallbackServiceKeywords(array $service): array
    {
        $title = trim((string) ($service['title'] ?? ''));
        $slug = trim((string) ($service['slug'] ?? ''));
        $category = trim((string) ($service['service_category'] ?? ''));

        return [
            $title,
            str_replace('-', ' ', $slug),
            $title . ' online',
            $title . ' service',
            $title . ' Tax Saathi',
            $category !== '' ? $category . ' ' . $title : '',
        ];
    }

    private static function uniqueKeywords(array $keywords): array
    {
        $unique = [];

        foreach ($keywords as $keyword) {
            $keyword = trim(preg_replace('/\s+/', ' ', strip_tags((string) $keyword)) ?? '');

            if ($keyword === '') {
                continue;
            }

            $key = strtolower($keyword);
            $unique[$key] = $keyword;
        }

        return array_values($unique);
    }

    private static function limitText(string $value, int $limit): string
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
}
