<?php
declare(strict_types=1);

$siteName        = trim((string) setting('site_name', 'Tax Saathi'));
$resolveConfiguredAssetUrl = static function (?string $path): string {
    $path = trim((string) $path);

    if ($path === '') {
        return '';
    }

    if (preg_match('~^(https?:)?//~i', $path) === 1 || str_starts_with($path, 'data:')) {
        return $path;
    }

    return base_url(ltrim($path, '/'));
};

$siteLogoPath = trim((string) setting('site_logo', 'uploads/logo3.png'));
$siteLogoPath = $siteLogoPath !== '' ? $siteLogoPath : 'uploads/logo3.png';
$siteLogoUrl  = $resolveConfiguredAssetUrl($siteLogoPath);
$siteLogoAlt  = trim((string) setting('site_logo_alt', $siteName . ' Logo'));
$siteLogoAlt  = $siteLogoAlt !== '' ? $siteLogoAlt : $siteName . ' Logo';
$pageTitle       = trim((string) ($title ?? $siteName));
$metaDescription = trim((string) setting('hero_subheading', 'Smart tax filing and compliance services.'));
$sitePhone       = trim((string) setting('site_phone', '+91 70057 27288'));
$siteEmail       = trim((string) setting('site_email', 'hello@taxsaathi.in'));
$siteWhatsapp    = trim((string) setting('site_whatsapp', '+91 70057 27288'));
$contactAddress  = trim((string) setting('contact_address', 'India'));
$whatsappUrl     = 'https://wa.me/' . phone_digits($siteWhatsapp);
$homeUrl         = base_url('/');
$servicesUrl     = base_url('services');
$calculatorsUrl  = base_url('tax-calculators');
$dashboardUrl    = base_url('dashboard');
$authUrl         = base_url('auth');
$logoutUrl       = base_url('logout');

$requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
$currentPath = (string) (parse_url($requestUri, PHP_URL_PATH) ?: '/');
$currentPath = '/' . ltrim($currentPath, '/');
$currentPath = $currentPath === '//' ? '/' : rtrim($currentPath, '/');
$currentPath = $currentPath !== '' ? $currentPath : '/';
$currentServiceParam = trim((string) ($_GET['service'] ?? ''));

$isActiveNav = static function (string $path, string $currentPath): bool {
    $path = '/' . ltrim($path, '/');
    $path = $path === '//' ? '/' : rtrim($path, '/');
    $path = $path !== '' ? $path : '/';

    if ($path === '/') {
        return $currentPath === '/';
    }

    return $currentPath === $path || str_starts_with($currentPath, $path . '/');
};

$resolvePdo = static function (): ?\PDO {
    if (!function_exists('app')) {
        return null;
    }

    $db = app('db');

    if ($db instanceof \PDO) {
        return $db;
    }

    if (is_object($db)) {
        if (method_exists($db, 'pdo')) {
            $pdo = $db->pdo();
            if ($pdo instanceof \PDO) {
                return $pdo;
            }
        }

        if (method_exists($db, 'getPdo')) {
            $pdo = $db->getPdo();
            if ($pdo instanceof \PDO) {
                return $pdo;
            }
        }

        if (method_exists($db, 'connection')) {
            $pdo = $db->connection();
            if ($pdo instanceof \PDO) {
                return $pdo;
            }
        }

        if (method_exists($db, 'getConnection')) {
            $pdo = $db->getConnection();
            if ($pdo instanceof \PDO) {
                return $pdo;
            }
        }

        if (property_exists($db, 'pdo') && $db->pdo instanceof \PDO) {
            return $db->pdo;
        }
    }

    return null;
};

$navServices = [];

try {
    if (class_exists('\App\Models\Service') && method_exists('\App\Models\Service', 'active')) {
        $servicesData = \App\Models\Service::active();
        if (is_array($servicesData)) {
            $navServices = array_values($servicesData);
        }
    }

    if (empty($navServices)) {
        $pdo = $resolvePdo();
        if ($pdo instanceof \PDO) {
            $stmt = $pdo->prepare("
                SELECT id, title, slug, excerpt, filing_fee, turnaround_days, icon, sort_order
                FROM services
                WHERE is_active = 1
                ORDER BY sort_order ASC, id ASC
            ");
            $stmt->execute();
            $navServices = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        }
    }
} catch (\Throwable $e) {
    $navServices = [];
}

$navServices = is_array($navServices) ? array_values($navServices) : [];

$serviceDetailsBase = rtrim(base_url('service-details'), '/');
$serviceSeoBase     = rtrim(base_url('services'), '/');

$serviceMenuUrl = static function (array $service) use ($serviceDetailsBase, $serviceSeoBase): string {
    $slug = trim((string) ($service['slug'] ?? ''));

    if ($slug !== '') {
        return $serviceSeoBase . '/' . rawurlencode($slug);
    }

    $identifier = trim((string) ($service['id'] ?? ''));

    return $serviceDetailsBase . '?service=' . urlencode($identifier);
};

$isServicesActive = $isActiveNav('/services', $currentPath) || $isActiveNav('/service-details', $currentPath);

$getServiceBadge = static function (array $service): string {
    $title = trim((string) ($service['title'] ?? ''));
    if ($title === '') {
        return 'SRV';
    }

    $words = preg_split('/\s+/', $title) ?: [];
    $abbr = '';

    foreach ($words as $word) {
        $word = trim((string) $word);
        if ($word === '' || $word === '-' || $word === '/') {
            continue;
        }

        $abbr .= mb_substr($word, 0, 1);

        if (mb_strlen($abbr) >= 3) {
            break;
        }
    }

    if ($abbr !== '') {
        return mb_strtoupper($abbr);
    }

    $plain = preg_replace('/[^A-Za-z0-9]/', '', $title) ?: 'SRV';
    return mb_strtoupper(mb_substr($plain, 0, 3));
};

$getServiceCategoryMeta = static function (array $service): array {
    $rawIcon = trim((string) ($service['icon'] ?? ''));

    if ($rawIcon === '') {
        return [
            'key'   => 'other-services',
            'label' => 'Other Services',
            'badge' => 'OTH',
        ];
    }

    if (preg_match('/^[A-Z0-9&\/+\- ]{2,24}$/', $rawIcon) === 1) {
        $label = trim(preg_replace('/\s+/', ' ', $rawIcon) ?? $rawIcon);
    } else {
        $tokens = array_values(array_filter(
            preg_split('/\s+/', $rawIcon) ?: [],
            static fn ($token): bool => trim((string) $token) !== ''
        ));

        $candidate = $tokens !== [] ? (string) end($tokens) : $rawIcon;
        $candidate = preg_replace('/^(fa[srlbd]?|fa|ri|bi|ti|mdi|ph|icon)-/i', '', $candidate) ?? $candidate;
        $candidate = str_replace(['_', '-'], ' ', $candidate);
        $candidate = trim(preg_replace('/\s+/', ' ', $candidate) ?? $candidate);

        $label = $candidate !== '' ? ucwords($candidate) : 'Other Services';
    }

    $label = $label !== '' ? $label : 'Other Services';

    $key = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $label), '-'));
    $key = $key !== '' ? $key : 'other-services';

    $badgeSeed = preg_replace('/[^A-Za-z0-9]/', '', $label) ?: 'OTH';
    $badge = mb_strtoupper(mb_substr($badgeSeed, 0, 3));

    return [
        'key'   => $key,
        'label' => $label,
        'badge' => $badge,
    ];
};

$navServiceGroups = [];

foreach ($navServices as $service) {
    $category = $getServiceCategoryMeta($service);

    if (!isset($navServiceGroups[$category['key']])) {
        $navServiceGroups[$category['key']] = [
            'key'   => $category['key'],
            'label' => $category['label'],
            'badge' => $category['badge'],
            'items' => [],
        ];
    }

    $navServiceGroups[$category['key']]['items'][] = $service;
}

$navServiceGroups = array_values($navServiceGroups);


$navCategories = [];

try {
    $incomingCategories = is_array($categories ?? null) ? array_values($categories) : [];

    if (!empty($incomingCategories)) {
        $navCategories = $incomingCategories;
    } else {
        $pdo = $resolvePdo();
        if ($pdo instanceof \PDO) {
            $stmt = $pdo->prepare("\n                SELECT\n                    c.id,\n                    c.title,\n                    c.slug,\n                    c.image,\n                    c.description,\n                    c.sort_order,\n                    COUNT(s.id) AS service_count\n                FROM service_categories c\n                LEFT JOIN services s\n                    ON s.service_category_id = c.id\n                    AND s.is_active = 1\n                WHERE c.is_active = 1\n                GROUP BY c.id, c.title, c.slug, c.image, c.description, c.sort_order\n                ORDER BY c.sort_order ASC, c.id ASC\n            ");
            $stmt->execute();
            $navCategories = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        }
    }
} catch (\Throwable $e) {
    $navCategories = [];
}

$navCategories = is_array($navCategories) ? array_values($navCategories) : [];


$services     = is_array($services ?? null) ? array_values($services) : [];
$testimonials = is_array($testimonials ?? null) ? array_values($testimonials) : [];
$faqs         = is_array($faqs ?? null) ? array_values($faqs) : [];

$resolveAssetUrl = static function (?string $path): string {
    $path = trim((string) $path);

    if ($path === '') {
        return '';
    }

    if (preg_match('~^(https?:)?//~i', $path) === 1 || str_starts_with($path, 'data:')) {
        return $path;
    }

    return base_url(ltrim($path, '/'));
};

$isImageIcon = static function (?string $icon): bool {
    $icon = trim((string) $icon);

    if ($icon === '') {
        return false;
    }

    return str_starts_with($icon, '/uploads/')
        || preg_match('/\.(svg|png|jpg|jpeg|webp|gif)$/i', $icon) === 1
        || preg_match('~^(https?:)?//~i', $icon) === 1
        || str_starts_with($icon, 'data:');
};

$getIconUrl = static function (?string $icon) use ($resolveAssetUrl): string {
    return $resolveAssetUrl($icon);
};

$iconFallbackText = static function (?string $value, string $fallback = 'TS'): string {
    $value = trim((string) $value);

    if ($value === '') {
        return $fallback;
    }

    if (preg_match('/^[A-Za-z0-9]{1,4}$/', $value) === 1) {
        return strtoupper($value);
    }

    $value = preg_replace('/\.[a-z0-9]+$/i', '', basename($value));
    $parts = preg_split('/[\s\-_]+/', $value) ?: [];

    $abbr = '';
    foreach ($parts as $part) {
        $part = trim((string) $part);
        if ($part === '') {
            continue;
        }

        $abbr .= strtoupper(substr($part, 0, 1));
        if (strlen($abbr) >= 2) {
            break;
        }
    }

    return $abbr !== '' ? $abbr : $fallback;
};

$serviceThumbnail = static function (array $service) use ($resolveAssetUrl): string {
    $raw = trim((string) (
        $service['thumbnail']
        ?? $service['thumbnail_image']
        ?? $service['image']
        ?? $service['cover_image']
        ?? ''
    ));

    return $resolveAssetUrl($raw);
};

$heroSlides = [
    [
        'eyebrow'      => (string) setting('hero_slide_1_eyebrow', 'Income Tax • GST • Compliance'),
        'title'        => (string) setting('hero_slide_1_title', 'File smarter. Track everything. Deliver faster.'),
        'text'         => (string) setting('hero_slide_1_text', 'Tax Saathi delivers a refined digital experience for filings, payments, document collection, progress visibility, and final delivery through one structured service ecosystem.'),
        'primary'      => (string) setting('hero_slide_1_primary_text', 'Explore Services'),
        'primaryUrl'   => (string) base_url((string) setting('hero_slide_1_primary_link', 'services')),
        'secondary'    => (string) setting('hero_slide_1_secondary_text', 'Use Calculators'),
        'secondaryUrl' => (string) base_url((string) setting('hero_slide_1_secondary_link', 'tax-calculators')),
        'chipA'        => 'Client-Centric Approach',
        'chipB'        => 'Efficient Workflow',
        'chipC'        => 'Scalable Service Model',
        'image'        => $resolveAssetUrl((string) setting('hero_slide_1_image', 'https://www.executivecentre.com/_next/image/?url=https%3A%2F%2Fassets.executivecentre.com%2Fassets%2FArticle-WhatIsCorporateTaxIndia-Header.jpg&w=1920&q=75')),
        'imageAlt'     => 'Corporate tax office environment',
    ],
    [
        'eyebrow'      => (string) setting('hero_slide_2_eyebrow', 'Client Experience'),
        'title'        => (string) setting('hero_slide_2_title', 'A digital tax experience for modern clients.'),
        'text'         => (string) setting('hero_slide_2_text', 'From service discovery to final document delivery, every stage is designed around clarity, convenience, and a polished client experience.'),
        'primary'      => (string) setting('hero_slide_2_primary_text', 'Get Started'),
        'primaryUrl'   => (string) base_url((string) setting('hero_slide_2_primary_link', 'auth')),
        'secondary'    => (string) setting('hero_slide_2_secondary_text', 'View Process'),
        'secondaryUrl' => '#process-section',
        'chipA'        => 'Premium Guidance',
        'chipB'        => 'Clear Communication',
        'chipC'        => 'Reliable Delivery',
        'image'        => $resolveAssetUrl((string) setting('hero_slide_2_image', 'https://blog.ipleaders.in/wp-content/uploads/2021/05/Meeting_Presentation_Conference-1.jpg')),
        'imageAlt'     => 'High-end client consultation and advisory meeting',
    ],
    [
        'eyebrow'      => (string) setting('hero_slide_3_eyebrow', 'Integrated Service Experience'),
        'title'        => (string) setting('hero_slide_3_title', 'Built for scale, trust, clarity, and speed.'),
        'text'         => (string) setting('hero_slide_3_text', 'Manage services, orders, uploads, reviews, and final delivery through a unified workflow built for consistency and confidence.'),
        'primary'      => (string) setting('hero_slide_3_primary_text', 'Request Callback'),
        'primaryUrl'   => '#contact-section',
        'secondary'    => (string) setting('hero_slide_3_secondary_text', 'See Services'),
        'secondaryUrl' => (string) base_url('services'),
        'chipA'        => 'Organized Experience',
        'chipB'        => 'Process Efficiency',
        'chipC'        => 'Flexible Framework',
        'image'        => $resolveAssetUrl((string) setting('hero_slide_3_image', 'https://img.freepik.com/free-photo/corporate-woman-suit-working-city-centre-using-laptop-mobile-phone_1258-124684.jpg?semt=ais_rp_progressive&w=740&q=80')),
        'imageAlt'     => 'Corporate digital workflow and analytics environment',
    ],
];

$serviceCount     = count($services);
$testimonialCount = count($testimonials);
$faqCount         = count($faqs);

/*
|--------------------------------------------------------------------------
| Dynamic Database SEO
|--------------------------------------------------------------------------
| Reads meta title, description, canonical and keywords from seo_keywords.
| Falls back to service title + slug keywords when the keyword table has no data.
*/
$seoLimitText = static function (string $value, int $limit): string {
    $value = trim((string) preg_replace('/\s+/', ' ', strip_tags($value)));

    if ($value === '') {
        return '';
    }

    if (function_exists('mb_strlen') && mb_strlen($value, 'UTF-8') > $limit) {
        return rtrim((string) mb_substr($value, 0, $limit - 1, 'UTF-8')) . '…';
    }

    if (!function_exists('mb_strlen') && strlen($value) > $limit) {
        return rtrim(substr($value, 0, $limit - 1)) . '…';
    }

    return $value;
};

$seoNormalizeKeyword = static function (?string $value): string {
    $value = trim((string) preg_replace('/\s+/', ' ', strip_tags((string) $value)));

    return $value;
};

$seoUniqueKeywords = static function (array $keywords) use ($seoNormalizeKeyword): array {
    $unique = [];

    foreach ($keywords as $keyword) {
        $keyword = $seoNormalizeKeyword((string) $keyword);

        if ($keyword === '') {
            continue;
        }

        $key = function_exists('mb_strtolower')
            ? mb_strtolower($keyword, 'UTF-8')
            : strtolower($keyword);

        $unique[$key] = $keyword;
    }

    return array_values($unique);
};

$seoMakeAbsoluteUrl = static function (?string $url) use ($homeUrl): string {
    $url = trim((string) $url);
    $siteRoot = rtrim($homeUrl, '/');

    if ($url === '') {
        return $siteRoot . '/';
    }

    if (preg_match('~^https?://~i', $url) === 1) {
        return $url;
    }

    return $siteRoot . '/' . ltrim($url, '/');
};

$seoRowsForTarget = static function (
    string $targetType,
    ?int $targetId = null,
    ?string $targetSlug = null,
    ?string $pagePath = null
) use ($resolvePdo): array {
    try {
        $pdo = $resolvePdo();

        if (!$pdo instanceof \PDO) {
            return [];
        }

        $stmt = $pdo->prepare("
            SELECT
                keyword,
                keyword_slug,
                keyword_type,
                meta_title,
                meta_description,
                canonical_url,
                is_primary,
                sort_order
            FROM seo_keywords
            WHERE is_active = 1
              AND target_type = :target_type
              AND COALESCE(target_id, 0) = :target_id
              AND COALESCE(target_slug, '') = :target_slug
              AND COALESCE(page_path, '') = :page_path
            ORDER BY is_primary DESC, sort_order ASC, id ASC
        ");

        $stmt->execute([
            'target_type' => $targetType,
            'target_id' => (int) ($targetId ?? 0),
            'target_slug' => (string) ($targetSlug ?? ''),
            'page_path' => (string) ($pagePath ?? ''),
        ]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    } catch (\Throwable $e) {
        return [];
    }
};

$seoFirstValue = static function (array $rows, string $field): string {
    foreach ($rows as $row) {
        $value = trim((string) ($row[$field] ?? ''));

        if ($value !== '') {
            return $value;
        }
    }

    return '';
};

$seoKeywordsFromRows = static function (array $rows): array {
    return array_values(array_filter(array_map(
        static fn (array $row): string => trim((string) ($row['keyword'] ?? '')),
        $rows
    )));
};

$incomingServiceForSeo = is_array($service ?? null) ? $service : [];

$findServiceForSeo = static function () use ($incomingServiceForSeo, $services, $navServices, $currentPath, $currentServiceParam, $resolvePdo): array {
    $availableServices = [];

    if (!empty($incomingServiceForSeo)) {
        $availableServices[] = $incomingServiceForSeo;
    }

    foreach ($services as $item) {
        if (is_array($item)) {
            $availableServices[] = $item;
        }
    }

    foreach ($navServices as $item) {
        if (is_array($item)) {
            $availableServices[] = $item;
        }
    }

    $targetIdentifier = $currentServiceParam;

    if ($targetIdentifier === '' && preg_match('~^/services/([^/]+)$~', $currentPath, $matches) === 1) {
        $targetIdentifier = rawurldecode((string) $matches[1]);
    }

    $targetIdentifier = trim((string) $targetIdentifier);

    if ($targetIdentifier !== '') {
        foreach ($availableServices as $item) {
            $id = trim((string) ($item['id'] ?? ''));
            $slug = trim((string) ($item['slug'] ?? ''));

            if ($targetIdentifier === $slug || $targetIdentifier === $id) {
                return $item;
            }
        }

        try {
            $pdo = $resolvePdo();

            if ($pdo instanceof \PDO) {
                $stmt = $pdo->prepare("
                    SELECT
                        s.*,
                        c.title AS service_category_title,
                        c.slug AS service_category_slug
                    FROM services s
                    LEFT JOIN service_categories c ON c.id = s.service_category_id
                    WHERE s.slug = :identifier OR s.id = :numeric_id
                    LIMIT 1
                ");

                $stmt->execute([
                    'identifier' => $targetIdentifier,
                    'numeric_id' => ctype_digit($targetIdentifier) ? (int) $targetIdentifier : 0,
                ]);

                $row = $stmt->fetch(\PDO::FETCH_ASSOC);

                if (is_array($row)) {
                    return $row;
                }
            }
        } catch (\Throwable $e) {
        }
    }

    return [];
};

$findCategoryForSeo = static function () use ($navCategories, $currentPath, $resolvePdo): array {
    if (!$currentPath || !str_starts_with($currentPath, '/service-category')) {
        return [];
    }

    $target = trim((string) ($_GET['category'] ?? ''));

    if ($target === '') {
        return [];
    }

    foreach ($navCategories as $category) {
        if (!is_array($category)) {
            continue;
        }

        $id = trim((string) ($category['id'] ?? ''));
        $slug = trim((string) ($category['slug'] ?? ''));

        if ($target === $slug || $target === $id) {
            return $category;
        }
    }

    try {
        $pdo = $resolvePdo();

        if ($pdo instanceof \PDO) {
            $stmt = $pdo->prepare("
                SELECT *
                FROM service_categories
                WHERE slug = :target OR id = :numeric_id
                LIMIT 1
            ");

            $stmt->execute([
                'target' => $target,
                'numeric_id' => ctype_digit($target) ? (int) $target : 0,
            ]);

            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (is_array($row)) {
                return $row;
            }
        }
    } catch (\Throwable $e) {
    }

    return [];
};

$currentSeoService = $findServiceForSeo();
$currentSeoCategory = $findCategoryForSeo();

$globalSeoRows = $seoRowsForTarget('global', null, null, null);
$pageSeoRows = $seoRowsForTarget('page', null, null, $currentPath);
$targetSeoRows = [];
$fallbackSeoKeywords = [];

$seoTitle = $pageTitle !== '' ? $pageTitle : $siteName;
$seoDescription = $metaDescription;
$seoCanonicalUrl = $seoMakeAbsoluteUrl($currentPath === '/' ? '/' : $currentPath);
$seoOgType = 'website';
$seoImageUrl = $siteLogoUrl;

if (!empty($currentSeoService)) {
    $serviceId = (int) ($currentSeoService['id'] ?? 0);
    $serviceSlug = trim((string) ($currentSeoService['slug'] ?? ''));
    $serviceTitle = trim((string) ($currentSeoService['title'] ?? 'Service'));
    $serviceExcerpt = trim((string) ($currentSeoService['excerpt'] ?? ''));
    $serviceDescription = trim((string) ($currentSeoService['description'] ?? ''));
    $serviceCategoryTitle = trim((string) (($currentSeoService['service_category_title'] ?? '') ?: ($currentSeoService['service_category'] ?? '')));

    $targetSeoRows = $seoRowsForTarget('service', $serviceId, $serviceSlug, null);

    $seoTitle = $seoFirstValue($targetSeoRows, 'meta_title')
        ?: $seoLimitText($serviceTitle . ' Online | ' . $siteName, 60);

    $seoDescription = $seoFirstValue($targetSeoRows, 'meta_description')
        ?: $seoLimitText(
            ($serviceExcerpt !== '' ? $serviceExcerpt : ($serviceDescription !== '' ? $serviceDescription : $serviceTitle . ' service with online assistance, document checklist and filing support.')) . ' Apply online with ' . $siteName . '.',
            155
        );

    $seoCanonicalUrl = $seoMakeAbsoluteUrl(
        $seoFirstValue($targetSeoRows, 'canonical_url')
            ?: ($serviceSlug !== '' ? 'services/' . $serviceSlug : 'service-details?service=' . $serviceId)
    );

    $fallbackSeoKeywords = [
        $serviceTitle,
        str_replace('-', ' ', $serviceSlug),
        $serviceTitle . ' online',
        $serviceTitle . ' service',
        $serviceTitle . ' in India',
        $serviceTitle . ' ' . $siteName,
    ];

    if ($serviceCategoryTitle !== '') {
        $fallbackSeoKeywords[] = $serviceCategoryTitle . ' ' . $serviceTitle;
        $fallbackSeoKeywords[] = $serviceTitle . ' under ' . $serviceCategoryTitle;
    }

    $serviceIconForSeo = $resolveAssetUrl((string) ($currentSeoService['icon'] ?? ''));
    if ($serviceIconForSeo !== '') {
        $seoImageUrl = $serviceIconForSeo;
    }
} elseif (!empty($currentSeoCategory)) {
    $categoryId = (int) ($currentSeoCategory['id'] ?? 0);
    $categorySlug = trim((string) ($currentSeoCategory['slug'] ?? ''));
    $categoryTitle = trim((string) ($currentSeoCategory['title'] ?? 'Service Category'));
    $categoryDescription = trim((string) ($currentSeoCategory['description'] ?? ''));

    $targetSeoRows = $seoRowsForTarget('service_category', $categoryId, $categorySlug, null);

    $seoTitle = $seoFirstValue($targetSeoRows, 'meta_title')
        ?: $seoLimitText($categoryTitle . ' Services | ' . $siteName, 60);

    $seoDescription = $seoFirstValue($targetSeoRows, 'meta_description')
        ?: $seoLimitText(
            $categoryDescription !== ''
                ? $categoryDescription
                : 'Explore ' . $categoryTitle . ' services with ' . $siteName . '.',
            155
        );

    $seoCanonicalUrl = $seoMakeAbsoluteUrl(
        $seoFirstValue($targetSeoRows, 'canonical_url')
            ?: 'service-category?category=' . urlencode($categorySlug !== '' ? $categorySlug : (string) $categoryId)
    );

    $fallbackSeoKeywords = [
        $categoryTitle,
        $categoryTitle . ' services',
        $categoryTitle . ' online',
        $categoryTitle . ' ' . $siteName,
    ];

    $categoryImageForSeo = $resolveAssetUrl((string) ($currentSeoCategory['image'] ?? ''));
    if ($categoryImageForSeo !== '') {
        $seoImageUrl = $categoryImageForSeo;
    }
} else {
    $pageTitleFromDb = $seoFirstValue($pageSeoRows, 'meta_title');
    $pageDescriptionFromDb = $seoFirstValue($pageSeoRows, 'meta_description');
    $pageCanonicalFromDb = $seoFirstValue($pageSeoRows, 'canonical_url');

    if ($pageTitleFromDb !== '') {
        $seoTitle = $pageTitleFromDb;
    }

    if ($pageDescriptionFromDb !== '') {
        $seoDescription = $pageDescriptionFromDb;
    }

    if ($pageCanonicalFromDb !== '') {
        $seoCanonicalUrl = $seoMakeAbsoluteUrl($pageCanonicalFromDb);
    }
}

$globalKeywords = $seoKeywordsFromRows($globalSeoRows);
$pageKeywords = $seoKeywordsFromRows($pageSeoRows);
$targetKeywords = $seoKeywordsFromRows($targetSeoRows);
$configKeywords = trim((string) setting('site_keywords', 'tax filing, GST registration, income tax return filing, ROC compliance, business registration, Tax Saathi'));

$seoKeywordsArray = $seoUniqueKeywords(array_merge(
    $targetKeywords,
    $fallbackSeoKeywords,
    $pageKeywords,
    $globalKeywords,
    explode(',', $configKeywords)
));

$seoKeywords = implode(', ', $seoKeywordsArray);
$seoDescription = $seoLimitText($seoDescription, 155);
$seoTitle = $seoLimitText($seoTitle, 70);

$organizationSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'Organization',
    'name' => $siteName,
    'url' => $homeUrl,
    'logo' => $siteLogoUrl,
    'email' => $siteEmail,
    'telephone' => $sitePhone,
    'address' => [
        '@type' => 'PostalAddress',
        'addressCountry' => 'IN',
        'streetAddress' => $contactAddress,
    ],
];

$websiteSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'WebSite',
    'name' => $siteName,
    'url' => $homeUrl,
    'potentialAction' => [
        '@type' => 'SearchAction',
        'target' => rtrim($homeUrl, '/') . '/search?q={search_term_string}',
        'query-input' => 'required name=search_term_string',
    ],
];

$seoSchemas = [$organizationSchema, $websiteSchema];

if (!empty($currentSeoService)) {
    $seoSchemas[] = [
        '@context' => 'https://schema.org',
        '@type' => 'Service',
        'name' => trim((string) ($currentSeoService['title'] ?? 'Service')),
        'description' => $seoDescription,
        'provider' => [
            '@type' => 'Organization',
            'name' => $siteName,
            'url' => $homeUrl,
        ],
        'url' => $seoCanonicalUrl,
        'areaServed' => 'IN',
        'serviceType' => trim((string) (($currentSeoService['service_category_title'] ?? '') ?: ($currentSeoService['service_category'] ?? 'Tax and compliance service'))),
    ];
}

$seoSchemaJson = json_encode($seoSchemas, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

?>
<!doctype html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <title><?= e($seoTitle) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= e($seoDescription) ?>">
    <?php if ($seoKeywords !== ''): ?>
        <meta name="keywords" content="<?= e($seoKeywords) ?>">
    <?php endif; ?>
    <meta name="robots" content="index,follow">
    <link rel="canonical" href="<?= e($seoCanonicalUrl) ?>">
    <meta name="theme-color" content="#3852B4">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link
  href="https://cdn.jsdelivr.net/npm/remixicon@4.9.0/fonts/remixicon.css"
  rel="stylesheet"
/>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Lilita One&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
    <meta property="og:type" content="<?= e($seoOgType) ?>">
    <meta property="og:title" content="<?= e($seoTitle) ?>">
    <meta property="og:description" content="<?= e($seoDescription) ?>">
    <meta property="og:site_name" content="<?= e($siteName) ?>">
    <meta property="og:url" content="<?= e($seoCanonicalUrl) ?>">
    <?php if ($seoImageUrl !== ''): ?>
        <meta property="og:image" content="<?= e($seoImageUrl) ?>">
    <?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Great+Vibes&family=Lobster&family=Rowdies:wght@300;400;700&family=Trochut:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($seoTitle) ?>">
    <meta name="twitter:description" content="<?= e($seoDescription) ?>">
    <?php if ($seoImageUrl !== ''): ?>
        <meta name="twitter:image" content="<?= e($seoImageUrl) ?>">
    <?php endif; ?>

    <link rel="icon" href="<?= e($siteLogoUrl) ?>">
    <?php if ($seoSchemaJson !== false && $seoSchemaJson !== ''): ?>
        <script type="application/ld+json"><?= $seoSchemaJson ?></script>
    <?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Lilita+One&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50: '#eef2ff',
                            100: '#dbe4ff',
                            200: '#b8c8f3',
                            300: '#8ea3df',
                            400: '#5E7AC4',
                            500: '#3852B4',
                            600: '#2f4496',
                            700: '#27377b',
                            800: '#1f2b61',
                            900: '#171f46',
                        },
                        accent: {
                            100: '#fde9cf',
                            200: '#F3BE7A',
                            300: '#f6a756',
                            400: '#F08D39',
                            500: '#d97722',
                        }
                    },
                    boxShadow: {
                        softxl: '0 18px 60px rgba(15, 23, 42, 0.12)',
                        glow: '0 20px 45px rgba(56, 82, 180, 0.18)',
                        warm: '0 18px 40px rgba(240, 141, 57, 0.18)',
                    },
                    backgroundImage: {
                        'luxury-light':
                            'radial-gradient(circle at top left, rgba(94,122,196,0.18), transparent 30%), radial-gradient(circle at top right, rgba(243,190,122,0.22), transparent 28%), linear-gradient(180deg, #f8fbff 0%, #ffffff 46%, #fff9f2 100%)'
                    }
                }
            }
        }
    </script>
</head>
<body class="min-h-screen bg-luxury-light font-sans text-slate-800 antialiased">

    <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[999] focus:rounded-xl focus:bg-slate-900 focus:px-4 focus:py-3 focus:text-white">
        Skip to content
    </a>

    <div class="relative overflow-hidden">
        <div class="absolute left-[-140px] top-[-140px] -z-10 h-80 w-80 rounded-full bg-brand-200/50 blur-3xl"></div>
        <div class="absolute right-[-120px] top-20 -z-10 h-80 w-80 rounded-full bg-accent-200/50 blur-3xl"></div>
        <div class="absolute bottom-0 left-1/3 -z-10 h-72 w-72 rounded-full bg-brand-100/60 blur-3xl"></div>

        <header class="sticky top-0 z-50 w-full bg-gradient-to-r from-[#0B3C91] via-[#145DA0] to-[#1E81B0] shadow-[0_8px_22px_rgba(11,60,145,0.18)]">
            <div class="w-full border-b border-white/10 bg-white/[0.03] backdrop-blur-md">
                <div class="mx-auto flex w-full max-w-[1440px] items-center justify-between gap-3 px-4 py-3 sm:px-6 lg:px-8">
                    <a href="<?= e($homeUrl) ?>" class="flex min-w-0 items-center gap-3" aria-label="<?= e($siteName) ?> home">
  <div class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-[18px] bg-white shadow-md border border-gray-200">
    <img 
        src="<?= e($siteLogoUrl) ?>" 
        alt="<?= e($siteLogoAlt) ?>" 
        class="h-full w-full object-contain p-2"
    >
</div>
                        <div class="min-w-0">
                           <div class="truncate  flex items-center gap-1 leading-none">
    <span class="font-['Lilita One'] text-2xl sm:text-3xl font-bold text-[#fff]
        [text-shadow:1px_1px_0_#fff,2px_2px_0_#fff,3px_3px_0_#fffff,4px_4px_8px_rgba(0,0,0,0.0)]">
        Tax
    </span>

    <span class="font-['Lilita One'] text-2xl sm:text-3xl font-bold text-[#fff]
        [text-shadow:1px_1px_0_#fff,2px_2px_0_#fff,3px_3px_0_#fffff,4px_4px_8px_rgba(0,0,0,0.0)]">
        Saathi
    </span>
</div>
                            <div class="hidden truncate text-[10px] font-bold uppercase tracking-[0.22em] text-blue-100/80 sm:block">
                                Tax Filing • Compliance • CRM
                            </div>
                        </div>
                    </a>

                    <nav class="hidden items-center gap-1.5 xl:flex" aria-label="Primary navigation">
                        <a href="<?= e($homeUrl) ?>" class="<?= $isActiveNav('/', $currentPath) ? 'bg-white/15 text-white' : 'text-white/90' ?> rounded-full px-4 py-2.5 text-sm font-bold transition duration-150 ease-out hover:bg-white/12 hover:text-white">
                            Home
                        </a>

                        <div class="group relative">
                            <button
                                type="button"
                                class="<?= $isServicesActive ? 'bg-white/15 text-white' : 'text-white/90' ?> inline-flex items-center gap-2 rounded-full px-4 py-2.5 text-sm font-bold transition duration-150 ease-out hover:bg-white/12 hover:text-white"
                                aria-haspopup="true"
                                aria-expanded="false"
                            >
                                <span>Services</span>
                                <svg class="h-4 w-4 transition duration-150 ease-out group-hover:rotate-180 group-focus-within:rotate-180" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.938a.75.75 0 1 1 1.08 1.04l-4.25 4.51a.75.75 0 0 1-1.08 0L5.21 8.27a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd"/>
                                </svg>
                            </button>

                            <div class="pointer-events-none invisible absolute left-1/2 top-full z-50 mt-3 w-[min(920px,calc(100vw-32px))] -translate-x-1/2 translate-y-2 opacity-0 transition duration-150 ease-out group-hover:pointer-events-auto group-hover:visible group-hover:translate-y-0 group-hover:opacity-100 group-focus-within:pointer-events-auto group-focus-within:visible group-focus-within:translate-y-0 group-focus-within:opacity-100">
                                <div class="overflow-hidden rounded-[24px] border border-white/70 bg-white/95 shadow-[0_18px_46px_rgba(15,23,42,0.14)] ring-1 ring-slate-900/5 backdrop-blur-xl">
                                    <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3">
                                        <div>
                                            <div class="text-[10px] font-extrabold uppercase tracking-[0.22em] text-[#b86a1d]">Service Menu</div>
                                            <div class="mt-0.5 text-xs font-semibold text-slate-500">Choose a category or service</div>
                                        </div>
                                        <a href="<?= e($servicesUrl) ?>" class="inline-flex items-center rounded-full bg-slate-900 px-3.5 py-2 text-[10px] font-extrabold uppercase tracking-[0.12em] text-white transition duration-150 hover:bg-slate-800">
                                            View All
                                        </a>
                                    </div>

                                    <div class="max-h-[68vh] overflow-y-auto p-3">
                                        <?php if (!empty($navCategories)): ?>
                                            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                                <?php foreach ($navCategories as $category): ?>
                                                    <?php
                                                        $categoryTitle = trim((string) ($category['title'] ?? 'Category'));
                                                        $categorySlug  = trim((string) ($category['slug'] ?? ''));
                                                        $categoryId    = (string) ($category['id'] ?? '');
                                                        $categoryImage = $resolveAssetUrl((string) ($category['image'] ?? ''));
                                                        $categoryCount = (int) ($category['service_count'] ?? 0);
                                                        $categoryUrl   = base_url('service-category?category=' . urlencode($categorySlug !== '' ? $categorySlug : $categoryId));
                                                        $categoryBadge = $iconFallbackText($categoryTitle, 'TS');
                                                    ?>
                                                    <a href="<?= e($categoryUrl) ?>" class="group/card flex min-h-[72px] items-center gap-3 rounded-[18px] border border-slate-100 bg-white p-2.5 transition duration-150 ease-out hover:-translate-y-0.5 hover:border-brand-100 hover:shadow-[0_12px_26px_rgba(56,82,180,0.10)]">
                                                        <span class="relative flex h-12 w-12 shrink-0 overflow-hidden rounded-2xl bg-gradient-to-br from-[#eef4ff] via-[#f8faff] to-[#fff3e8]">
                                                            <?php if ($categoryImage !== ''): ?>
                                                                <img src="<?= e($categoryImage) ?>" alt="<?= e($categoryTitle) ?>" class="h-full w-full object-cover" loading="lazy" decoding="async">
                                                            <?php else: ?>
                                                                <span class="m-auto inline-flex h-8 w-8 items-center justify-center rounded-xl bg-gradient-to-br from-[#5E7AC4] to-[#3852B4] text-[10px] font-black tracking-[0.08em] text-white">
                                                                    <?= e($categoryBadge) ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        </span>
                                                        <span class="min-w-0 flex-1">
                                                            <span class="block truncate text-[13px] font-black tracking-[-0.02em] text-slate-900"><?= e($categoryTitle) ?></span>
                                                            <span class="mt-1 flex items-center justify-between gap-2">
                                                                <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-[9px] font-extrabold uppercase tracking-[0.1em] text-slate-600"><?= e((string) $categoryCount) ?> Services</span>
                                                                <span class="text-sm font-black text-slate-400 transition group-hover/card:text-brand-700">→</span>
                                                            </span>
                                                        </span>
                                                    </a>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php elseif (!empty($navServiceGroups)): ?>
                                            <div class="grid gap-3 lg:grid-cols-2">
                                                <?php foreach ($navServiceGroups as $serviceGroup): ?>
                                                    <div class="rounded-[20px] border border-slate-100 bg-white p-3">
                                                        <div class="mb-2 flex items-center justify-between gap-3">
                                                            <div class="flex min-w-0 items-center gap-2">
                                                                <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-[10px] font-black text-brand-700"><?= e((string) $serviceGroup['badge']) ?></span>
                                                                <span class="truncate text-sm font-black text-slate-900"><?= e((string) $serviceGroup['label']) ?></span>
                                                            </div>
                                                            <span class="rounded-full bg-slate-100 px-2 py-1 text-[10px] font-extrabold text-slate-600"><?= e((string) count($serviceGroup['items'])) ?></span>
                                                        </div>
                                                        <div class="grid gap-1.5">
                                                            <?php foreach ($serviceGroup['items'] as $navService): ?>
                                                                <?php
                                                                    $serviceUrl = $serviceMenuUrl($navService);
                                                                    $serviceIdentifier = trim((string) ($navService['slug'] ?? '')) !== '' ? (string) $navService['slug'] : (string) ($navService['id'] ?? '');
                                                                    $serviceSlugForCurrent = trim((string) ($navService['slug'] ?? ''));
                                                                    $isCurrentService = (
                                                                        $isActiveNav('/service-details', $currentPath)
                                                                        && $currentServiceParam !== ''
                                                                        && $currentServiceParam === $serviceIdentifier
                                                                    ) || (
                                                                        $serviceSlugForCurrent !== ''
                                                                        && $currentPath === '/services/' . trim($serviceSlugForCurrent, '/')
                                                                    );
                                                                ?>
                                                                <a href="<?= e($serviceUrl) ?>" class="<?= $isCurrentService ? 'bg-brand-50 text-brand-700' : 'text-slate-700' ?> rounded-2xl px-3 py-2 text-xs font-extrabold transition hover:bg-brand-50 hover:text-brand-700">
                                                                    <?= e((string) ($navService['title'] ?? 'Service')) ?>
                                                                </a>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-5 text-sm font-semibold text-slate-500">
                                                No active services available.
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <a href="<?= e($calculatorsUrl) ?>" class="<?= $isActiveNav('/tax-calculators', $currentPath) ? 'bg-white/15 text-white' : 'text-white/90' ?> rounded-full px-4 py-2.5 text-sm font-bold transition duration-150 ease-out hover:bg-white/12 hover:text-white">
                            Calculators
                        </a>

                        <?php if (is_logged_in()): ?>
                            <a href="/client/orders" class="<?= $isActiveNav('/dashboard', $currentPath) ? 'border-white/25 bg-white/15 text-white' : 'border-white/15 bg-white/10 text-white' ?> inline-flex items-center justify-center rounded-full border px-4 py-2.5 text-sm font-extrabold transition duration-150 hover:bg-white/15">
                                My Application
                            </a>
                            <form method="post" action="<?= e($logoutUrl) ?>" class="m-0">
                                <?= csrf_field() ?>
                                <button type="submit" class="inline-flex items-center justify-center rounded-full bg-white px-4 py-2.5 text-sm font-extrabold text-[#0B3C91] shadow-[0_8px_18px_rgba(255,255,255,0.14)] transition duration-150 hover:-translate-y-0.5">
                                    Logout
                                </button>
                            </form>
                        <?php else: ?>
                            <a href="<?= e($servicesUrl) ?>" class="inline-flex items-center justify-center rounded-full border border-white/15 bg-white/10 px-4 py-2.5 text-sm font-extrabold text-white transition duration-150 hover:bg-white/15">
                                Explore Services
                            </a>
                            <a href="<?= e($authUrl) ?>" class="<?= $isActiveNav('/auth', $currentPath) ? 'ring-2 ring-white/25' : '' ?> inline-flex items-center justify-center rounded-full bg-white px-4 py-2.5 text-sm font-extrabold text-[#0B3C91] shadow-[0_8px_18px_rgba(255,255,255,0.14)] transition duration-150 hover:-translate-y-0.5">
                                Login / Register
                            </a>
                        <?php endif; ?>
                    </nav>

                    <button
                        id="mobileMenuButton"
                        type="button"
                        class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-white/15 bg-white/10 text-white transition duration-150 hover:bg-white/15 xl:hidden"
                        aria-label="Open menu"
                        aria-expanded="false"
                        aria-controls="mobileSidebar"
                    >
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5" aria-hidden="true">
                            <path stroke-linecap="round" d="M4 7h16"/>
                            <path stroke-linecap="round" d="M4 12h16"/>
                            <path stroke-linecap="round" d="M4 17h16"/>
                        </svg>
                    </button>
                </div>
            </div>
        </header>

        <div id="mobileSidebarOverlay" class="pointer-events-none fixed inset-0 z-[70] bg-slate-950/45 opacity-0 transition-opacity duration-200 xl:hidden"></div>

        <aside
            id="mobileSidebar"
            class="fixed right-0 top-0 z-[80] flex h-full w-[92vw] max-w-[420px] translate-x-full transform-gpu flex-col border-l border-white/30 bg-gradient-to-b from-white via-[#f8fbff] to-[#fff8ef] shadow-[0_18px_46px_rgba(15,23,42,0.20)] transition-transform duration-200 ease-out xl:hidden"
            aria-hidden="true"
        >
            <div class="flex items-center justify-between border-b border-brand-100/60 px-4 py-4 sm:px-5">
                <div class="flex min-w-0 items-center gap-3">
                       <div class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-[18px] bg-white shadow-md border border-gray-200">
    <img 
        src="<?= e($siteLogoUrl) ?>" 
        alt="<?= e($siteLogoAlt) ?>" 
        class="h-full w-full object-contain p-2"
    >
</div>
                    <div class="min-w-0">
                        <div class="truncate text-base font-black text-slate-900"><?= e($siteName) ?></div>
                        <div class="text-[10px] font-bold uppercase tracking-[0.22em] text-brand-600">Menu</div>
                    </div>
                </div>

                <button
                    id="mobileMenuClose"
                    type="button"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-2xl border border-brand-100 bg-white text-brand-700 shadow-sm"
                    aria-label="Close menu"
                >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5" aria-hidden="true">
                        <path stroke-linecap="round" d="M6 6l12 12"/>
                        <path stroke-linecap="round" d="M18 6L6 18"/>
                    </svg>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto px-4 py-4 sm:px-5">
                <nav class="grid gap-2" aria-label="Mobile navigation">
                    <a href="<?= e($homeUrl) ?>" class="<?= $isActiveNav('/', $currentPath) ? 'bg-brand-50 text-brand-700' : 'text-slate-800' ?> rounded-2xl px-4 py-3 text-sm font-extrabold transition duration-150 hover:bg-brand-50 hover:text-brand-700">
                        Home
                    </a>

                    <button
                        id="mobileServicesToggle"
                        type="button"
                        class="<?= $isServicesActive ? 'bg-brand-50 text-brand-700' : 'text-slate-800' ?> flex items-center justify-between rounded-2xl px-4 py-3 text-sm font-extrabold transition duration-150 hover:bg-brand-50 hover:text-brand-700"
                        aria-expanded="<?= $isServicesActive ? 'true' : 'false' ?>"
                        aria-controls="mobileServicesPanel"
                    >
                        <span>Services</span>
                        <svg id="mobileServicesArrow" class="h-4 w-4 transition duration-150 <?= $isServicesActive ? 'rotate-180' : '' ?>" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.938a.75.75 0 1 1 1.08 1.04l-4.25 4.51a.75.75 0 0 1-1.08 0L5.21 8.27a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd"/>
                        </svg>
                    </button>

                    <div id="mobileServicesPanel" class="<?= $isServicesActive ? '' : 'hidden' ?> pl-2">
                        <div class="grid gap-2 border-l border-brand-100 pl-3">
                            <?php if (!empty($navCategories)): ?>
                                <?php foreach ($navCategories as $category): ?>
                                    <?php
                                        $categoryTitle = trim((string) ($category['title'] ?? 'Category'));
                                        $categorySlug  = trim((string) ($category['slug'] ?? ''));
                                        $categoryId    = (string) ($category['id'] ?? '');
                                        $categoryImage = $resolveAssetUrl((string) ($category['image'] ?? ''));
                                        $categoryCount = (int) ($category['service_count'] ?? 0);
                                        $categoryUrl   = base_url('service-category?category=' . urlencode($categorySlug !== '' ? $categorySlug : $categoryId));
                                        $categoryBadge = $iconFallbackText($categoryTitle, 'TS');
                                    ?>
                                    <a href="<?= e($categoryUrl) ?>" class="flex items-center gap-3 rounded-[18px] border border-brand-100 bg-white/90 p-2.5 shadow-sm transition duration-150 hover:bg-brand-50">
                                        <span class="flex h-11 w-11 shrink-0 overflow-hidden rounded-2xl bg-gradient-to-br from-[#eef4ff] via-[#f8faff] to-[#fff3e8]">
                                            <?php if ($categoryImage !== ''): ?>
                                                <img src="<?= e($categoryImage) ?>" alt="<?= e($categoryTitle) ?>" class="h-full w-full object-cover" loading="lazy" decoding="async">
                                            <?php else: ?>
                                                <span class="m-auto inline-flex h-8 w-8 items-center justify-center rounded-xl bg-brand-100 text-[10px] font-black text-brand-700"><?= e($categoryBadge) ?></span>
                                            <?php endif; ?>
                                        </span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate text-sm font-black text-slate-900"><?= e($categoryTitle) ?></span>
                                            <span class="mt-1 inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-[9px] font-extrabold uppercase tracking-[0.1em] text-slate-600"><?= e((string) $categoryCount) ?> Services</span>
                                        </span>
                                        <span class="text-sm font-black text-brand-500">→</span>
                                    </a>
                                <?php endforeach; ?>
                                <a href="<?= e($servicesUrl) ?>" class="rounded-2xl px-4 py-3 text-sm font-extrabold text-brand-700 transition hover:bg-brand-50">View All Services</a>
                            <?php elseif (!empty($navServiceGroups)): ?>
                                <?php foreach ($navServiceGroups as $serviceGroup): ?>
                                    <div class="rounded-[20px] border border-brand-100 bg-white/90 p-2 shadow-sm">
                                        <div class="flex items-center justify-between gap-3 px-2 py-2">
                                            <div class="flex min-w-0 items-center gap-3">
                                                <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-brand-100 text-[10px] font-black text-brand-700"><?= e((string) $serviceGroup['badge']) ?></span>
                                                <span class="truncate text-sm font-black text-slate-900"><?= e((string) $serviceGroup['label']) ?></span>
                                            </div>
                                            <span class="rounded-full bg-brand-50 px-2 py-1 text-[10px] font-extrabold text-brand-700"><?= e((string) count($serviceGroup['items'])) ?></span>
                                        </div>
                                        <div class="grid gap-1.5">
                                            <?php foreach ($serviceGroup['items'] as $navService): ?>
                                                <?php
                                                    $serviceUrl = $serviceMenuUrl($navService);
                                                    $serviceIdentifier = trim((string) ($navService['slug'] ?? '')) !== '' ? (string) $navService['slug'] : (string) ($navService['id'] ?? '');
                                                    $serviceSlugForCurrent = trim((string) ($navService['slug'] ?? ''));
                                                                    $isCurrentService = (
                                                                        $isActiveNav('/service-details', $currentPath)
                                                                        && $currentServiceParam !== ''
                                                                        && $currentServiceParam === $serviceIdentifier
                                                                    ) || (
                                                                        $serviceSlugForCurrent !== ''
                                                                        && $currentPath === '/services/' . trim($serviceSlugForCurrent, '/')
                                                                    );
                                                ?>
                                                <a href="<?= e($serviceUrl) ?>" class="<?= $isCurrentService ? 'bg-brand-50 text-brand-700' : 'text-slate-700' ?> rounded-2xl px-3 py-2 text-sm font-extrabold transition hover:bg-brand-50 hover:text-brand-700">
                                                    <?= e((string) ($navService['title'] ?? 'Service')) ?>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="rounded-2xl px-4 py-3 text-sm text-slate-500">No active services available.</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <a href="<?= e($calculatorsUrl) ?>" class="<?= $isActiveNav('/tax-calculators', $currentPath) ? 'bg-brand-50 text-brand-700' : 'text-slate-800' ?> rounded-2xl px-4 py-3 text-sm font-extrabold transition duration-150 hover:bg-brand-50 hover:text-brand-700">
                        Calculators
                    </a>

                    <?php if (is_logged_in()): ?>
                        <a href="<?= e($dashboardUrl) ?>" class="<?= $isActiveNav('/dashboard', $currentPath) ? 'border-brand-200 bg-brand-50 text-brand-700' : 'border-brand-100 bg-white text-brand-700' ?> mt-2 rounded-2xl border px-4 py-3 text-center text-sm font-extrabold shadow-sm">
                            Dashboard
                        </a>
                        <form method="post" action="<?= e($logoutUrl) ?>" class="m-0">
                            <?= csrf_field() ?>
                            <button type="submit" class="mt-2 w-full rounded-2xl bg-gradient-to-r from-[#3852B4] to-[#5E7AC4] px-4 py-3 text-sm font-extrabold text-white shadow-[0_10px_24px_rgba(56,82,180,0.18)]">
                                Logout
                            </button>
                        </form>
                    <?php else: ?>
                        <a href="<?= e($servicesUrl) ?>" class="mt-2 rounded-2xl border border-accent-200 bg-white px-4 py-3 text-center text-sm font-extrabold text-brand-700 shadow-sm">
                            Explore Services
                        </a>
                        <a href="<?= e($authUrl) ?>" class="<?= $isActiveNav('/auth', $currentPath) ? 'ring-2 ring-brand-200' : '' ?> mt-2 rounded-2xl bg-gradient-to-r from-[#F08D39] to-[#F3BE7A] px-4 py-3 text-center text-sm font-extrabold text-white shadow-[0_10px_24px_rgba(240,141,57,0.18)]">
                            Login / Register
                        </a>
                    <?php endif; ?>
                </nav>

                <div class="mt-5 rounded-[22px] border border-brand-100 bg-white/90 px-4 py-4 shadow-sm">
                    <div class="text-[10px] font-bold uppercase tracking-[0.22em] text-brand-600">Contact</div>
                    <div class="mt-3 grid gap-2 text-sm font-semibold text-slate-700">
                        <a href="tel:<?= e(phone_digits($sitePhone)) ?>"><?= e($sitePhone) ?></a>
                        <a href="mailto:<?= e($siteEmail) ?>"><?= e($siteEmail) ?></a>
                        <a href="<?= e($whatsappUrl) ?>" target="_blank" rel="noopener" class="text-[#c46f1f]"><?= e($siteWhatsapp) ?></a>
                    </div>
                </div>
            </div>

            <div class="border-t border-brand-100/60 px-4 py-4 sm:px-5">
                <p class="text-xs font-semibold leading-6 text-slate-500">
                    Premium tax, compliance, and advisory experience with a modern client-first interface.
                </p>
            </div>
        </aside>

        <section class="w-full px-4 pt-5 sm:px-6 lg:px-10">
            <?php if ($message = flash('success')): ?>
                <div class="mb-4 rounded-[22px] border border-green-200 bg-green-50 px-5 py-4 text-sm font-bold text-green-800 shadow-sm">
                    <?= e($message) ?>
                </div>
            <?php endif; ?>

            <?php if ($message = flash('warning')): ?>
                <div class="mb-4 rounded-[22px] border border-accent-200 bg-orange-50 px-5 py-4 text-sm font-bold text-[#9a581c] shadow-sm">
                    <?= e($message) ?>
                </div>
            <?php endif; ?>

            <?php if ($message = flash('error')): ?>
                <div class="mb-4 rounded-[22px] border border-rose-200 bg-rose-50 px-5 py-4 text-sm font-bold text-rose-800 shadow-sm">
                    <?= e($message) ?>
                </div>
            <?php endif; ?>
        </section>

        <main id="main-content" class="relative z-10 w-full">
            <?= $content ?>
        </main>
<footer class="mt-1 w-full px-0 pb-0">
    <style>
        .footer-premium {
            position: relative;
            overflow: hidden;
            background:
                radial-gradient(circle at top left, rgba(255,255,255,0.14), transparent 24%),
                radial-gradient(circle at 85% 20%, rgba(255,255,255,0.10), transparent 18%),
                linear-gradient(90deg, #0B3C91 0%, #145DA0 52%, #1E81B0 100%);
        }

        .footer-premium::before {
            content: "";
            position: absolute;
            inset: 0;
            background:
                linear-gradient(to bottom, rgba(255,255,255,0.05), transparent 22%, transparent 78%, rgba(255,255,255,0.04));
            pointer-events: none;
        }

        .footer-divider {
            border-color: rgba(255,255,255,0.08);
        }

        .footer-title-highlight {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.24em;
            text-transform: uppercase;
            color: rgba(219, 234, 254, 0.88);
            text-shadow: 0 1px 10px rgba(255,255,255,0.10);
        }

        .footer-title-highlight::before {
            content: "";
            width: 24px;
            height: 1px;
            background: linear-gradient(90deg, rgba(255,255,255,0.85), rgba(255,255,255,0.10));
            display: inline-block;
        }

        .footer-brand-title {
            font-size: 16px;
            font-weight: 800;
            letter-spacing: -0.03em;
            color: #ffffff;
            text-shadow: 0 2px 14px rgba(255,255,255,0.10);
        }

        .footer-highlight-text {
            color: rgba(255,255,255,0.96);
            font-weight: 600;
            position: relative;
            display: inline-block;
        }

        .footer-highlight-text::after {
            content: "";
            position: absolute;
            left: 0;
            bottom: -2px;
            width: 100%;
            height: 6px;
            background: linear-gradient(90deg, rgba(255,255,255,0.22), rgba(255,255,255,0.02));
            border-radius: 999px;
            filter: blur(4px);
            opacity: 0.8;
        }

        .footer-soft-copy {
            font-size: 12px;
            line-height: 1.9;
            font-weight: 500;
            color: rgba(219, 234, 254, 0.78);
        }

        .footer-link-row {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: rgba(255,255,255,0.84);
            font-size: 12px;
            font-weight: 500;
            letter-spacing: 0.01em;
            transition: all 0.25s ease;
        }

        .footer-link-row:hover {
            color: rgba(255,255,255,1);
            transform: translateX(2px);
        }

        .footer-link-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 18px;
            height: 18px;
            border-radius: 999px;
            border: 1px solid rgba(255,255,255,0.14);
            background: linear-gradient(180deg, rgba(255,255,255,0.12), rgba(255,255,255,0.05));
            box-shadow:
                inset 0 1px 0 rgba(255,255,255,0.10),
                0 4px 14px rgba(0,0,0,0.10);
            color: rgba(255,255,255,0.88);
            flex-shrink: 0;
            backdrop-filter: blur(8px);
        }

        .footer-link-card {
            padding: 6px 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }

        .footer-link-card:last-child {
            border-bottom: 0;
        }

        .footer-contact-row {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 12px;
            line-height: 1.8;
            font-weight: 500;
            color: rgba(219, 234, 254, 0.82);
            padding: 6px 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }

        .footer-contact-row:last-child {
            border-bottom: 0;
        }

        .footer-contact-row a {
            color: rgba(255,255,255,0.88);
            transition: all 0.25s ease;
        }

        .footer-contact-row a:hover {
            color: #ffffff;
        }

        .footer-glow-pill {
            box-shadow:
                inset 0 1px 0 rgba(255,255,255,0.12),
                0 8px 30px rgba(0,0,0,0.10);
        }

        .footer-bottom-text {
            font-size: 10.5px;
            font-weight: 500;
            letter-spacing: 0.03em;
            color: rgba(219, 234, 254, 0.68);
        }
    </style>

    <div class="footer-premium w-full rounded-none border-0 shadow-none">
        <div class="relative z-[1] grid gap-6 px-4 py-5 sm:px-5 lg:grid-cols-[1.15fr_0.7fr_0.7fr_0.9fr] lg:gap-8 lg:px-8 lg:py-7">

            <!-- Brand -->
            <div class="pr-0 lg:pr-6 lg:border-r footer-divider">
                <div class="flex items-start gap-3.5">
       <div class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-[18px] bg-white shadow-md border border-gray-200">
    <img 
        src="<?= e($siteLogoUrl) ?>" 
        alt="<?= e($siteLogoAlt) ?>" 
        class="h-full w-full object-contain p-2"
    >
</div>

                    <div>
                          <div class="font-['Lilita One'] text-2xl sm:text-3xl font-bold text-[#fff]
        [text-shadow:1px_1px_0_#fff,2px_2px_0_#fff,3px_3px_0_#fffff,4px_4px_8px_rgba(0,0,0,0.0)]">
        Tax
    </span>

    <span class="font-['Lilita One'] text-2xl sm:text-3xl font-bold text-[#fff]
        [text-shadow:1px_1px_0_#fff,2px_2px_0_#fff,3px_3px_0_#fffff,4px_4px_8px_rgba(0,0,0,0.0)]">
        Saathi
    </span>
</div>
                           
                        <p class="footer-soft-copy mt-2 max-w-md">
                            Professional platform for
                            <span class="footer-highlight-text">tax returns</span>,
                            <span class="footer-highlight-text">GST</span>,
                            <span class="footer-highlight-text">registrations</span>,
                            notices, and compliance work with clarity, speed, and trust.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Explore -->
            <div class="lg:border-r footer-divider lg:pr-6">
                <h4 class="footer-title-highlight">Explore</h4>

                <div class="mt-4 space-y-1">
                    <div class="footer-link-card">
                        <a href="<?= e($servicesUrl) ?>" class="footer-link-row">
                            <span class="footer-link-icon">
                                <svg viewBox="0 0 20 20" fill="currentColor" class="h-2.5 w-2.5">
                                    <path d="M10 2.5l2.2 4.45 4.9.72-3.55 3.46.84 4.87L10 13.68 5.61 16l.84-4.87L2.9 7.67l4.9-.72L10 2.5z"/>
                                </svg>
                            </span>
                            <span>Services</span>
                        </a>
                    </div>

                    <div class="footer-link-card">
                        <a href="<?= e($calculatorsUrl) ?>" class="footer-link-row">
                            <span class="footer-link-icon">
                                <svg viewBox="0 0 20 20" fill="currentColor" class="h-2.5 w-2.5">
                                    <path d="M5 2.75A2.25 2.25 0 0 0 2.75 5v10A2.25 2.25 0 0 0 5 17.25h10A2.25 2.25 0 0 0 17.25 15V5A2.25 2.25 0 0 0 15 2.75H5zm1.25 2.5h7.5v2h-7.5v-2zm0 4h2v2h-2v-2zm0 3.5h2v2h-2v-2zm3.25-3.5h2v2h-2v-2zm0 3.5h2v2h-2v-2zm3.25-3.5h2v5.5h-2V9.25z"/>
                                </svg>
                            </span>
                            <span>Tax Calculators</span>
                        </a>
                    </div>

                    <div class="footer-link-card">
                        <a href="<?= e(base_url('contact')) ?>" class="footer-link-row">
                            <span class="footer-link-icon">
                                <svg viewBox="0 0 20 20" fill="currentColor" class="h-2.5 w-2.5">
                                    <path d="M10 2.5A4.25 4.25 0 1 1 5.75 6.75 4.25 4.25 0 0 1 10 2.5zm0 9c3.07 0 5.75 1.57 5.75 3.5v1.25a1 1 0 0 1-1 1H5.25a1 1 0 0 1-1-1V15c0-1.93 2.68-3.5 5.75-3.5z"/>
                                </svg>
                            </span>
                            <span>Contact</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Legal -->
            <div class="lg:border-r footer-divider lg:pr-6">
                <h4 class="footer-title-highlight">Legal</h4>

                <div class="mt-4 space-y-1">
                    <div class="footer-link-card">
                        <a href="<?= e(base_url('about')) ?>" class="footer-link-row">
                            <span class="footer-link-icon">
                                <svg viewBox="0 0 20 20" fill="currentColor" class="h-2.5 w-2.5">
                                    <path d="M10 1.75l6 2.25v4.87c0 4.04-2.56 6.93-6 8.38-3.44-1.45-6-4.34-6-8.38V4l6-2.25zm-1 5.75v4.5l4-2.25-4-2.25z"/>
                                </svg>
                            </span>
                            <span>About </span>
                        </a>
                    </div>

                    <div class="footer-link-card">
                        <a href="<?= e(base_url('refund-policy')) ?>" class="footer-link-row">
                            <span class="footer-link-icon">
                                <svg viewBox="0 0 20 20" fill="currentColor" class="h-2.5 w-2.5">
                                    <path d="M10 1.75l6 2.25v4.87c0 4.04-2.56 6.93-6 8.38-3.44-1.45-6-4.34-6-8.38V4l6-2.25zm-1 5.75v4.5l4-2.25-4-2.25z"/>
                                </svg>
                            </span>
                            <span>Refund Policy</span>
                        </a>
                    </div>

                    <div class="footer-link-card">
                        <a href="<?= e(base_url('privacy-policy')) ?>" class="footer-link-row">
                            <span class="footer-link-icon">
                                <svg viewBox="0 0 20 20" fill="currentColor" class="h-2.5 w-2.5">
                                    <path d="M10 1.75l6 2.25v4.87c0 4.04-2.56 6.93-6 8.38-3.44-1.45-6-4.34-6-8.38V4l6-2.25zm0 4a2 2 0 0 1 2 2V8h.25A1.75 1.75 0 0 1 14 9.75v2.5A1.75 1.75 0 0 1 12.25 14h-4.5A1.75 1.75 0 0 1 6 12.25v-2.5A1.75 1.75 0 0 1 7.75 8H8v-.25a2 2 0 0 1 2-2zm0 1.5a.5.5 0 0 0-.5.5V8h1v-.25a.5.5 0 0 0-.5-.5z"/>
                                </svg>
                            </span>
                            <span>Privacy Policy</span>
                        </a>
                    </div>

                    <div class="footer-link-card">
                        <a href="<?= e(base_url('terms-conditions')) ?>" class="footer-link-row">
                            <span class="footer-link-icon">
                                <svg viewBox="0 0 20 20" fill="currentColor" class="h-2.5 w-2.5">
                                    <path d="M3 4.25A2.25 2.25 0 0 1 5.25 2h9.5A2.25 2.25 0 0 1 17 4.25v11.5A2.25 2.25 0 0 1 14.75 18h-9.5A2.25 2.25 0 0 1 3 15.75V4.25zm1.75.25v2h10.5v-2a.75.75 0 0 0-.75-.75h-9a.75.75 0 0 0-.75.75zM6 11.25a.75.75 0 0 0 0 1.5h3.25a.75.75 0 0 0 0-1.5H6z"/>
                                </svg>
                            </span>
                            <span>Terms & Conditions</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Contact -->
            <div>
                <h4 class="footer-title-highlight">Contact</h4>

                <div class="mt-4">
                    <div class="footer-contact-row">
                        <span class="footer-link-icon mt-1">
                            <svg viewBox="0 0 20 20" fill="currentColor" class="h-2.5 w-2.5">
                                <path d="M10 18s6-5.69 6-10a6 6 0 1 0-12 0c0 4.31 6 10 6 10zm0-8.25A2.25 2.25 0 1 1 10 5.25a2.25 2.25 0 0 1 0 4.5z"/>
                            </svg>
                        </span>
                        <p><?= e($contactAddress) ?></p>
                    </div>

                    <div class="footer-contact-row">
                        <span class="footer-link-icon">
                            <svg viewBox="0 0 20 20" fill="currentColor" class="h-2.5 w-2.5">
                                <path d="M2.5 4.75A2.25 2.25 0 0 1 4.75 2.5h1.14a1.5 1.5 0 0 1 1.45 1.13l.52 2.08a1.5 1.5 0 0 1-.43 1.45L6.2 8.4a10.02 10.02 0 0 0 5.4 5.4l1.24-1.23a1.5 1.5 0 0 1 1.45-.43l2.08.52a1.5 1.5 0 0 1 1.13 1.45v1.14a2.25 2.25 0 0 1-2.25 2.25H15C8.1 17.5 2.5 11.9 2.5 5V4.75z"/>
                            </svg>
                        </span>
                        <a href="tel:<?= e($sitePhone) ?>"><?= e($sitePhone) ?></a>
                    </div>

                    <div class="footer-contact-row">
                        <span class="footer-link-icon">
                            <svg viewBox="0 0 20 20" fill="currentColor" class="h-2.5 w-2.5">
                                <path d="M2.75 5.5A2.75 2.75 0 0 1 5.5 2.75h9A2.75 2.75 0 0 1 17.25 5.5v9A2.75 2.75 0 0 1 14.5 17.25h-9A2.75 2.75 0 0 1 2.75 14.5v-9zm1.9.42 5.02 3.58a.58.58 0 0 0 .66 0l5.02-3.58A1.25 1.25 0 0 0 14.5 4h-9a1.25 1.25 0 0 0-.85 1.92z"/>
                            </svg>
                        </span>
                        <a href="mailto:<?= e($siteEmail) ?>"><?= e($siteEmail) ?></a>
                    </div>

                    <div class="footer-contact-row">
                        <span class="footer-link-icon">
                            <svg viewBox="0 0 20 20" fill="currentColor" class="h-2.5 w-2.5">
                                <path d="M10 2.5A7.5 7.5 0 0 0 3.53 13.8L2.5 17.5l3.8-1a7.5 7.5 0 1 0 3.7-14zm3.61 10.28c-.15.41-.88.78-1.22.83-.32.05-.73.07-1.17-.07-.27-.08-.61-.2-1.05-.39-1.85-.8-3.06-2.77-3.15-2.89-.09-.12-.75-1-.75-1.92s.48-1.37.65-1.56c.17-.19.37-.24.49-.24h.35c.11 0 .27-.04.42.31.15.36.51 1.24.56 1.33.05.09.08.2.02.32-.06.12-.09.2-.18.31-.09.1-.19.23-.27.31-.09.09-.19.19-.08.38.11.19.49.81 1.05 1.31.72.64 1.32.84 1.51.93.19.1.3.08.41-.05.11-.13.47-.55.59-.74.12-.19.25-.16.42-.1.17.06 1.1.52 1.29.62.19.09.32.14.36.22.05.08.05.47-.1.88z"/>
                            </svg>
                        </span>
                        <a href="<?= e($whatsappUrl) ?>" target="_blank" rel="noopener"><?= e($siteWhatsapp) ?></a>
                    </div>
                </div>
            </div>
        </div>

        <div class="relative z-[1] border-t footer-divider">
            <div class="flex flex-col gap-2 px-4 py-3.5 sm:px-5 lg:flex-row lg:items-center lg:justify-between lg:px-8">
                <p class="footer-bottom-text">© <?= date('Y') ?> <?= e($siteName) ?>. All rights reserved.</p>
                <p class="footer-bottom-text">Built with trust, speed, clarity, and a Professional client experience.</p>
            </div>
        </div>
    </div>
</footer>
    </div>

    <script>
        (function () {
            const openBtn = document.getElementById('mobileMenuButton');
            const closeBtn = document.getElementById('mobileMenuClose');
            const sidebar = document.getElementById('mobileSidebar');
            const overlay = document.getElementById('mobileSidebarOverlay');
            const servicesToggle = document.getElementById('mobileServicesToggle');
            const servicesPanel = document.getElementById('mobileServicesPanel');
            const servicesArrow = document.getElementById('mobileServicesArrow');

            if (!openBtn || !closeBtn || !sidebar || !overlay) return;

            let isOpen = false;

            function openSidebar() {
                if (isOpen) return;
                isOpen = true;
                sidebar.classList.remove('translate-x-full');
                overlay.classList.remove('opacity-0', 'pointer-events-none');
                overlay.classList.add('opacity-100');
                openBtn.setAttribute('aria-expanded', 'true');
                sidebar.setAttribute('aria-hidden', 'false');
                document.body.classList.add('overflow-hidden');
            }

            function closeSidebar() {
                if (!isOpen) return;
                isOpen = false;
                sidebar.classList.add('translate-x-full');
                overlay.classList.add('opacity-0', 'pointer-events-none');
                overlay.classList.remove('opacity-100');
                openBtn.setAttribute('aria-expanded', 'false');
                sidebar.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('overflow-hidden');
            }

            openBtn.addEventListener('click', openSidebar, { passive: true });
            closeBtn.addEventListener('click', closeSidebar, { passive: true });
            overlay.addEventListener('click', closeSidebar, { passive: true });

            if (servicesToggle && servicesPanel && servicesArrow) {
                servicesToggle.addEventListener('click', function () {
                    const willOpen = servicesPanel.classList.contains('hidden');
                    servicesPanel.classList.toggle('hidden', !willOpen);
                    servicesArrow.classList.toggle('rotate-180', willOpen);
                    servicesToggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
                });
            }

            sidebar.querySelectorAll('a').forEach(function (link) {
                link.addEventListener('click', closeSidebar, { passive: true });
            });

            window.addEventListener('resize', function () {
                if (window.innerWidth >= 1280) {
                    closeSidebar();
                }
            }, { passive: true });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    closeSidebar();
                }
            });
        })();
    </script>
    <!-- Floating Call and WhatsApp Buttons -->
<div id="floating-contact-buttons">
    <a
        href="tel:+917576899990"
        class="floating-contact-call"
        aria-label="Call support"
        title="Call support"
    >
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M6.62 10.79a15.46 15.46 0 0 0 6.59 6.59l2.2-2.2a1 1 0 0 1 1.02-.24c1.12.37 2.33.57 3.57.57a1 1 0 0 1 1 1V20a1 1 0 0 1-1 1C10.61 21 3 13.39 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.25.2 2.45.57 3.57a1 1 0 0 1-.25 1.02l-2.2 2.2Z"/>
        </svg>

        <span>Call Now</span>
    </a>

    <a
        href="https://wa.me/917576899990?text=Hello%2C%20I%20need%20assistance%20with%20Tax%20Saathi."
        class="floating-contact-whatsapp"
        target="_blank"
        rel="noopener noreferrer"
        aria-label="Chat on WhatsApp"
        title="Chat on WhatsApp"
    >
        <svg viewBox="0 0 32 32" aria-hidden="true">
            <path d="M16.02 3C8.84 3 3 8.76 3 15.87c0 2.27.6 4.49 1.74 6.44L3 29l6.88-1.79a13.1 13.1 0 0 0 6.14 1.53h.01C23.21 28.74 29 22.98 29 15.88 29 8.77 23.21 3 16.02 3Zm0 23.57h-.01a10.9 10.9 0 0 1-5.54-1.51l-.4-.24-4.08 1.06 1.09-3.93-.26-.4a10.62 10.62 0 0 1-1.65-5.68c0-5.91 4.87-10.71 10.86-10.71 2.9 0 5.63 1.12 7.68 3.15a10.58 10.58 0 0 1 3.18 7.57c0 5.9-4.87 10.69-10.87 10.69Zm5.96-8.01c-.33-.16-1.93-.95-2.23-1.06-.3-.11-.52-.16-.74.16-.22.33-.85 1.06-1.04 1.28-.19.22-.38.25-.71.08-.33-.16-1.38-.51-2.63-1.63-.97-.87-1.63-1.94-1.82-2.26-.19-.33-.02-.5.14-.66.15-.15.33-.38.49-.57.16-.19.22-.33.33-.55.11-.22.05-.41-.03-.57-.08-.16-.74-1.78-1.01-2.43-.27-.64-.54-.55-.74-.56h-.63c-.22 0-.57.08-.87.41-.3.33-1.14 1.12-1.14 2.73s1.17 3.16 1.33 3.38c.16.22 2.31 3.53 5.59 4.95.78.34 1.39.54 1.87.69.78.25 1.49.21 2.05.13.63-.09 1.93-.79 2.2-1.55.27-.76.27-1.41.19-1.55-.08-.14-.3-.22-.63-.38Z"/>
        </svg>

        <span>WhatsApp</span>
    </a>
</div>

<style>
    #floating-contact-buttons {
        position: fixed !important;
        right: 20px !important;
        bottom: 20px !important;
        z-index: 2147483647 !important;
        display: flex !important;
        flex-direction: column !important;
        align-items: flex-end !important;
        gap: 12px !important;
        visibility: visible !important;
        opacity: 1 !important;
        pointer-events: auto !important;
    }

    #floating-contact-buttons a {
        width: 56px !important;
        height: 56px !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        border-radius: 9999px !important;
        color: #ffffff !important;
        text-decoration: none !important;
        overflow: hidden !important;
        border: 2px solid rgba(255, 255, 255, 0.8) !important;
        box-shadow: 0 10px 28px rgba(15, 23, 42, 0.3) !important;
        transition: width 0.25s ease, transform 0.25s ease !important;
    }

    #floating-contact-buttons a:hover {
        width: 145px !important;
        transform: translateY(-3px) !important;
    }

    #floating-contact-buttons svg {
        width: 25px !important;
        height: 25px !important;
        min-width: 25px !important;
        fill: currentColor !important;
    }

    #floating-contact-buttons span {
        width: 0;
        margin-left: 0;
        font-family: Arial, sans-serif;
        font-size: 13px;
        font-weight: 700;
        white-space: nowrap;
        opacity: 0;
        overflow: hidden;
        transition: width 0.25s ease, margin-left 0.25s ease, opacity 0.25s ease;
    }

    #floating-contact-buttons a:hover span {
        width: auto;
        margin-left: 9px;
        opacity: 1;
    }

    .floating-contact-call {
        background: linear-gradient(135deg, #2563eb, #1d4ed8) !important;
    }

    .floating-contact-whatsapp {
        background: linear-gradient(135deg, #25d366, #128c4a) !important;
    }

    @media (max-width: 640px) {
        #floating-contact-buttons {
            right: 14px !important;
            bottom: calc(16px + env(safe-area-inset-bottom)) !important;
        }

        #floating-contact-buttons a,
        #floating-contact-buttons a:hover {
            width: 52px !important;
            height: 52px !important;
            transform: none !important;
        }

        #floating-contact-buttons span {
            display: none !important;
        }
    }
</style>
    
</body>
</html>