<?php
declare(strict_types=1);

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
        'title'        => (string) setting('hero_slide_2_title', 'A digital tax experience for  clients.'),
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
?>

<section class="relative overflow-hidden">
    <div class="absolute inset-x-0 top-0 -z-10 h-[420px] bg-gradient-to-b from-brand-50/70 via-white/40 to-transparent"></div>
    <div class="absolute -left-20 top-0 -z-10 h-56 w-56 rounded-full bg-brand-200/30 blur-3xl"></div>
    <div class="absolute -right-20 top-20 -z-10 h-56 w-56 rounded-full bg-accent-200/35 blur-3xl"></div>

    <div
        class="relative overflow-hidden border border-white/70 bg-[#0f172a] shadow-[0_20px_56px_rgba(15,23,42,0.14)]"
        data-banner-slider
        data-slider-autoplay="5000"
    >
        <?php foreach ($heroSlides as $index => $slide): ?>
            <div class="banner-slide <?= $index === 0 ? 'relative opacity-100' : 'absolute inset-0 pointer-events-none opacity-0' ?> transition duration-700 ease-out"
                data-slide="<?= (int) $index ?>"
            >
                <div class="absolute inset-0">
                    <?php if (!empty($slide['image'])): ?>
                        <img
                            src="<?= e((string) $slide['image']) ?>"
                            alt="<?= e((string) $slide['imageAlt']) ?>"
                            class="h-full w-full object-cover"
                        >
                    <?php else: ?>
                        <div class="h-full w-full bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900"></div>
                    <?php endif; ?>

                    <div class="absolute inset-0 bg-[linear-gradient(90deg,rgba(15,23,42,0.84)_0%,rgba(15,23,42,0.74)_34%,rgba(15,23,42,0.52)_60%,rgba(15,23,42,0.70)_100%)]"></div>
                    <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(94,122,196,.22),transparent_24%),radial-gradient(circle_at_bottom_right,rgba(240,141,57,.18),transparent_20%)]"></div>
                </div>

                <div class="relative grid min-h-[430px] items-center gap-6 px-5 py-6 sm:px-7 lg:min-h-[470px] lg:grid-cols-[1.08fr_0.92fr] lg:px-10 lg:py-8">
                    <div class="relative z-10">
                        <span class="inline-flex items-center rounded-full border border-white/20 bg-white/10 px-3.5 py-1.5 text-[10px] font-extrabold uppercase tracking-[0.22em] text-white shadow-sm backdrop-blur-md">
                            <?= e((string) $slide['eyebrow']) ?>
                        </span>

                        <h1 class="mt-4 max-w-4xl text-3xl font-black leading-[1.02] tracking-[-0.05em] text-white sm:text-4xl lg:text-4xl">
                            <?= e((string) $slide['title']) ?>
                        </h1>

                        <p class="mt-4 max-w-2xl text-sm leading-7 text-white/80 sm:text-base">
                            <?= e((string) $slide['text']) ?>
                        </p>

                        <div class="mt-6 flex flex-col gap-2.5 sm:flex-row">
                            <a
                                href="<?= e((string) $slide['primaryUrl']) ?>"
                                class="inline-flex items-center justify-center rounded-full bg-gradient-to-r from-[#3852B4] to-[#5E7AC4] px-5 py-2.5 text-xs font-extrabold text-white shadow-[0_14px_28px_rgba(56,82,180,0.24)] transition hover:-translate-y-0.5"
                            >
                                <?= e((string) $slide['primary']) ?>
                            </a>

                            <a
                                href="<?= e((string) $slide['secondaryUrl']) ?>"
                                class="inline-flex items-center justify-center rounded-full border border-white/20 bg-white/10 px-5 py-2.5 text-xs font-extrabold text-white shadow-sm backdrop-blur-md transition hover:-translate-y-0.5 hover:bg-white/16"
                            >
                                <?= e((string) $slide['secondary']) ?>
                            </a>
                        </div>

                        <div class="mt-6 flex flex-wrap gap-2.5">
                            <span class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3 py-1.5 text-[9px] font-bold uppercase tracking-[0.14em] text-white shadow-sm backdrop-blur-md">
                                <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-gradient-to-br from-emerald-400 via-teal-400 to-cyan-500 text-white shadow-[0_6px_14px_rgba(16,185,129,0.24)]">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-2.5 w-2.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 20a8 8 0 0 1 16 0"/>
                                    </svg>
                                </span>
                                <?= e((string) $slide['chipA']) ?>
                            </span>

                            <span class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3 py-1.5 text-[9px] font-bold uppercase tracking-[0.14em] text-white shadow-sm backdrop-blur-md">
                                <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-gradient-to-br from-amber-400 via-orange-400 to-rose-500 text-white shadow-[0_6px_14px_rgba(251,146,60,0.24)]">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-2.5 w-2.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 6l6 6-6 6"/>
                                    </svg>
                                </span>
                                <?= e((string) $slide['chipB']) ?>
                            </span>

                            <span class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3 py-1.5 text-[9px] font-bold uppercase tracking-[0.14em] text-white shadow-sm backdrop-blur-md">
                                <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-gradient-to-br from-violet-500 via-fuchsia-500 to-pink-500 text-white shadow-[0_6px_14px_rgba(168,85,247,0.24)]">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-2.5 w-2.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 17h16"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 12h10"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 7h4"/>
                                    </svg>
                                </span>
                                <?= e((string) $slide['chipC']) ?>
                            </span>
                        </div>
                    </div>

                    <div class="relative z-10 hidden lg:block">
                        <div class="grid gap-4 lg:ml-auto lg:max-w-[480px]">
                            <div class="rounded-[26px] border border-white/15 bg-white/10 p-5 shadow-[0_18px_44px_rgba(15,23,42,0.16)] backdrop-blur-xl">
                                <div class="mb-3 inline-flex rounded-full bg-white/14 px-3 py-1 text-[10px] font-extrabold uppercase tracking-[0.18em] text-white">
                                    Why clients choose Tax Saathi
                                </div>

                                <h3 class="text-xl font-black tracking-[-0.03em] text-white">
                                    A refined service experience built for confidence
                                </h3>

                            </div>

                            <div class="grid gap-3 sm:grid-cols-3">
                                <div class="rounded-[20px] border border-white/15 bg-white/10 p-4 text-center shadow-[0_14px_32px_rgba(15,23,42,0.12)] backdrop-blur-md">
                                    <div class="flex justify-center">
                                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-400 via-teal-400 to-cyan-500 text-white shadow-[0_8px_18px_rgba(16,185,129,0.24)]">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 20a8 8 0 0 1 16 0"/>
                                            </svg>
                                        </span>
                                    </div>
                                    <div class="mt-2.5 text-xs font-black uppercase tracking-[0.12em] text-white">Client-Centric</div>
                                    <div class="mt-1.5 text-[12px] leading-5 text-white/72" style="color: white;">Designed around clarity and convenience</div>
                                </div>

                                <div class="rounded-[20px] border border-white/15 bg-white/10 p-4 text-center shadow-[0_14px_32px_rgba(15,23,42,0.12)] backdrop-blur-md">
                                    <div class="flex justify-center">
                                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-2xl bg-gradient-to-br from-amber-400 via-orange-400 to-rose-500 text-white shadow-[0_8px_18px_rgba(251,146,60,0.24)]">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 6l6 6-6 6"/>
                                            </svg>
                                        </span>
                                    </div>
                                    <div class="mt-2.5 text-xs font-black uppercase tracking-[0.12em] text-white">Efficient</div>
                                    <div class="mt-1.5 text-[12px] leading-5 text-white/72" style="color: white;">Structured workflow with faster movement</div>
                                </div>

                                <div class="rounded-[20px] border border-white/15 bg-white/10 p-4 text-center shadow-[0_14px_32px_rgba(15,23,42,0.12)] backdrop-blur-md">
                                    <div class="flex justify-center">
                                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-500 via-fuchsia-500 to-pink-500 text-white shadow-[0_8px_18px_rgba(168,85,247,0.24)]">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 17h16"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M7 12h10"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M10 7h4"/>
                                            </svg>
                                        </span>
                                    </div>
                                    <div class="mt-2.5 text-xs font-black uppercase tracking-[0.12em] text-white">Scalable</div>
                                    <div class="mt-1.5 text-[12px] leading-5 text-white/72" style="color: white;">Built to support wider service needs</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="absolute bottom-3 left-1/2 z-20 flex -translate-x-1/2 items-center gap-2">
            <?php foreach ($heroSlides as $index => $slide): ?>
                <button
                    type="button"
                    class="<?= $index === 0 ? 'w-7 bg-white' : 'w-2.5 bg-white/60' ?> h-2.5 rounded-full border border-white/70 shadow-sm transition-all duration-300"
                    data-slide-dot="<?= (int) $index ?>"
                    aria-label="Go to slide <?= (int) ($index + 1) ?>"
                ></button>
            <?php endforeach; ?>
        </div>

        <button
            type="button"
            class="absolute left-4 top-1/2 z-20 hidden h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full border border-white/20 bg-white/10 text-white shadow-lg backdrop-blur-md transition hover:scale-105 lg:inline-flex"
            data-slide-prev
            aria-label="Previous slide"
        >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4.5 w-4.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 18l-6-6 6-6"/>
            </svg>
        </button>

        <button
            type="button"
            class="absolute right-4 top-1/2 z-20 hidden h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full border border-white/20 bg-white/10 text-white shadow-lg backdrop-blur-md transition hover:scale-105 lg:inline-flex"
            data-slide-next
            aria-label="Next slide"
        >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4.5 w-4.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 6l6 6-6 6"/>
            </svg>
        </button>
    </div>
</section>


<!----------------------Service Categories---------------->

<?php
    $normalizeCategoryLabel = static function (?string $value): string {
        $value = trim((string) $value);

        if ($value === '') {
            return 'General Services';
        }

        $value = preg_replace('/[_\-]+/', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;
        $value = trim($value);

        return ucwords(strtolower($value));
    };

    $normalizeCategoryKey = static function (?string $value): string {
        $value = strtolower(trim((string) $value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        $value = trim($value, '-');

        return $value !== '' ? $value : 'general-services';
    };

    $groupedServices = [];

    if (!empty($services)) {
        foreach ($services as $service) {
            $rawCategory = trim((string) (
                $service['service_category']
                ?? $service['category']
                ?? $service['group_name']
                ?? ''
            ));

            $groupKey = $normalizeCategoryKey($rawCategory);
            $groupLabel = $normalizeCategoryLabel(
                (string) (
                    $service['service_category_label']
                    ?? $service['category_label']
                    ?? ($rawCategory !== '' ? $rawCategory : 'General Services')
                )
            );

            $groupImage = trim((string) (
                $service['service_category_image']
                ?? $service['category_image']
                ?? $service['service_category_icon']
                ?? $service['category_icon']
                ?? $service['icon']
                ?? $service['thumbnail']
                ?? $service['thumbnail_image']
                ?? $service['image']
                ?? $service['cover_image']
                ?? ''
            ));

            if (!isset($groupedServices[$groupKey])) {
                $groupedServices[$groupKey] = [
                    'key'       => $groupKey,
                    'label'     => $groupLabel,
                    'image'     => $groupImage,
                    'fallback'  => $iconFallbackText($groupLabel, 'TS'),
                    'items'     => [],
                ];
            }

            if ($groupedServices[$groupKey]['image'] === '' && $groupImage !== '') {
                $groupedServices[$groupKey]['image'] = $groupImage;
            }

            $groupedServices[$groupKey]['items'][] = $service;
        }
    }

    $defaultTab = 'all';
?>

<!----------------------Service Categories---------------->

<?php
$categories = is_array($categories ?? null) ? array_values($categories) : [];
?>

<section class="px-4 py-8 sm:px-6 lg:px-10">
    <div class="rounded-[4px] border border-slate-200/70 bg-white/70 p-4 shadow-[0_18px_60px_rgba(15,23,42,0.05)] backdrop-blur-xl sm:p-5 lg:p-6">
        <div class="mx-auto max-w-3xl text-center">
            <span class="inline-flex rounded-[4px] border border-accent-200/70 bg-accent-100/50 px-4 py-1.5 text-[11px] font-extrabold uppercase tracking-[0.22em] text-[#b86a1d] shadow-sm">
                We can help you with !
            </span>
        </div>

        <?php if (!empty($categories)): ?>
            <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-3">
                <?php foreach ($categories as $category): ?>
                    <?php
                        $categoryTitle = trim((string) ($category['title'] ?? 'Category'));
                        $categorySlug  = trim((string) ($category['slug'] ?? ''));
                        $categoryImage = trim((string) ($category['image'] ?? ''));
                        $categoryDesc  = trim((string) ($category['description'] ?? ''));
                        $categoryCount = (int) ($category['service_count'] ?? 0);
                        $categoryUrl   = base_url('service-category?category=' . urlencode($categorySlug !== '' ? $categorySlug : (string) ($category['id'] ?? '')));
                        $categoryImageUrl = $resolveAssetUrl($categoryImage);
                        $categoryFallback = $iconFallbackText($categoryTitle, 'TS');

                        $categoryPriceRaw = $category['starting_price']
                            ?? $category['min_price']
                            ?? $category['filing_fee']
                            ?? $category['price']
                            ?? '';

                        $categoryPrice = '';
                        if ($categoryPriceRaw !== '' && $categoryPriceRaw !== null) {
                            $categoryPrice = is_numeric($categoryPriceRaw)
                                ? '₹' . number_format((float) $categoryPriceRaw, 0)
                                : trim((string) $categoryPriceRaw);
                        }

                        $bottomMeta = $categoryPrice !== ''
                            ? 'Starting from ' . $categoryPrice
                            : $categoryCount . ' Services';
                    ?>

                    <a
                        href="<?= e($categoryUrl) ?>"
                        class="group relative flex min-h-[66px] items-center gap-3 overflow-hidden rounded-[10px] border border-slate-200/80 bg-white px-2.5 py-2 shadow-[0_10px_28px_rgba(15,23,42,0.055)] transition-all duration-300 hover:-translate-y-0.5 hover:border-[#5E7AC4]/35 hover:bg-[#fbfcff] hover:shadow-[0_18px_42px_rgba(56,82,180,0.14)]"
                    >
                        <div class="pointer-events-none absolute inset-0 rounded-[4px] bg-gradient-to-r from-white via-[#f8faff] to-[#fff7ee] opacity-90"></div>

                        <div class="relative h-[68px] w-[97px] shrink-0 overflow-hidden rounded-[14px] border border-white bg-gradient-to-br from-[#eef4ff] via-[#f8faff] to-[#fff3e8] shadow-[inset_0_0_0_1px_rgba(255,255,255,0.8),0_8px_20px_rgba(15,23,42,0.08)]">
                            <?php if ($categoryImageUrl !== ''): ?>
                                <img
                                    src="<?= e($categoryImageUrl) ?>"
                                    alt="<?= e($categoryTitle) ?>"
                                    class="h-full w-full object-cover transition duration-700 group-hover:scale-[1.08]"
                                >
                                <div class="absolute inset-0 bg-gradient-to-t from-slate-950/25 via-transparent to-white/5"></div>
                            <?php else: ?>
                                <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(94,122,196,0.22),transparent_42%),radial-gradient(circle_at_bottom_left,rgba(248,161,63,0.20),transparent_38%)]"></div>

                                <div class="relative flex h-full items-center justify-center">
                                    <span class="inline-flex h-11 w-11 items-center justify-center rounded-full bg-gradient-to-br from-[#5E7AC4] to-[#3852B4] text-[10px] font-black tracking-[0.12em] text-white shadow-[0_12px_22px_rgba(56,82,180,0.24)]">
                                        <?= e($categoryFallback) ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="relative min-w-0 flex-1 pr-2">
                            <h3 class="truncate text-[14px] font-black leading-tight tracking-[-0.025em] text-slate-950">
                                <?= e($categoryTitle) ?>
                            </h3>

                            <div class="mt-1.5 flex items-center justify-between gap-2">
                                <span class="truncate text-[11px] font-extrabold uppercase tracking-[0.08em] text-[#3852B4]">
                                    <?= e($bottomMeta) ?>
                                </span>

                                <span class="flex h-13 w-13 shrink-0 items-center justify-center rounded-full bg-slate-950 text-[23px] text-white shadow-sm transition duration-300 group-hover:translate-x-0.5 group-hover:bg-[#3852B4]">
                                    <i class="ri-arrow-right-double-line"></i>
                                </span>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-3">
                <?php for ($i = 1; $i <= 4; $i++): ?>
                    <article class="flex min-h-[82px] items-center gap-3 rounded-full border border-dashed border-brand-200 bg-white/80 px-2.5 py-2 shadow-[0_10px_24px_rgba(15,23,42,0.04)]">
                        <div class="flex h-[64px] w-[64px] shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-[#eef4ff] via-[#f8faff] to-[#fff3e8]">
                            <div class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-brand-100 text-[10px] font-black tracking-[0.12em] text-brand-700">
                                TS
                            </div>
                        </div>

                        <div class="min-w-0 flex-1">
                            <h3 class="truncate text-[13px] font-black tracking-[-0.02em] text-slate-900">
                                Category Coming Soon
                            </h3>
                            <p class="mt-1 truncate text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">
                                Service categories will appear here.
                            </p>
                        </div>
                    </article>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

<section id="process-section" class="px-4 py-12 sm:px-6 lg:px-10">
    <div class="mx-auto max-w-3xl text-center">
        <span class="inline-flex rounded-full border border-brand-100 bg-white/80 px-4 py-2 text-[11px] font-extrabold uppercase tracking-[0.24em] text-brand-700 shadow-sm">
            Process
        </span>
        <h2 class="mt-4 text-3xl font-black tracking-[-0.04em] text-slate-900 sm:text-4xl">
            <?= e(setting('process_title', 'How Tax Saathi works')) ?>
        </h2>
    </div>

    <div class="mt-10 grid gap-5 lg:grid-cols-2">
        <div class="rounded-[28px] border border-white/70 bg-white/85 p-6 shadow-[0_18px_40px_rgba(15,23,42,0.06)]">
            <div class="flex gap-4">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-400 via-teal-400 to-cyan-500 text-white shadow-[0_12px_24px_rgba(16,185,129,0.18)]">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 20a8 8 0 0 1 16 0"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-black tracking-[-0.03em] text-slate-900">Secure Client Access</h3>
                    <p class="mt-2 text-sm leading-7 text-slate-600">Clients begin with a quick, secure sign-in experience designed for convenience and trust.</p>
                </div>
            </div>
        </div>

        <div class="rounded-[28px] border border-white/70 bg-white/85 p-6 shadow-[0_18px_40px_rgba(15,23,42,0.06)]">
            <div class="flex gap-4">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-amber-400 via-orange-400 to-rose-500 text-white shadow-[0_12px_24px_rgba(251,146,60,0.18)]">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 6l6 6-6 6"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-black tracking-[-0.03em] text-slate-900">Service Request Submission</h3>
                    <p class="mt-2 text-sm leading-7 text-slate-600">Clients select the required service, share essential details, and submit documents through a structured workflow.</p>
                </div>
            </div>
        </div>

        <div class="rounded-[28px] border border-white/70 bg-white/85 p-6 shadow-[0_18px_40px_rgba(15,23,42,0.06)]">
            <div class="flex gap-4">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-500 via-fuchsia-500 to-pink-500 text-white shadow-[0_12px_24px_rgba(168,85,247,0.18)]">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3l7 4v5c0 5-3.5 7.5-7 9-3.5-1.5-7-4-7-9V7l7-4z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-black tracking-[-0.03em] text-slate-900">Professional Review</h3>
                    <p class="mt-2 text-sm leading-7 text-slate-600">Each request moves through a carefully managed review process to maintain accuracy and consistency.</p>
                </div>
            </div>
        </div>

        <div class="rounded-[28px] border border-white/70 bg-white/85 p-6 shadow-[0_18px_40px_rgba(15,23,42,0.06)]">
            <div class="flex gap-4">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-sky-500 via-indigo-500 to-blue-600 text-white shadow-[0_12px_24px_rgba(59,130,246,0.18)]">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h8"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v8"/>
                        <circle cx="12" cy="12" r="9"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-black tracking-[-0.03em] text-slate-900">Final Delivery</h3>
                    <p class="mt-2 text-sm leading-7 text-slate-600">Completed outputs are delivered through a polished and dependable service experience.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="px-4 py-12 sm:px-6 lg:px-10">
    <div class="rounded-[36px] border border-white/70 bg-gradient-to-br from-white via-[#f8fbff] to-[#fff8f2] p-6 shadow-[0_20px_50px_rgba(15,23,42,0.06)] sm:p-8 lg:p-10">
        <div class="mx-auto max-w-3xl text-center">
            <span class="inline-flex rounded-full border border-accent-200 bg-accent-100/60 px-4 py-2 text-[11px] font-extrabold uppercase tracking-[0.24em] text-[#b86a1d] shadow-sm">
                Testimonials
            </span>
            <h2 class="mt-4 text-3xl font-black tracking-[-0.04em] text-slate-900 sm:text-4xl">
                Client trust that converts
            </h2>
        </div>

        <div class="mt-10 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
            <?php if (!empty($testimonials)): ?>
                <?php foreach ($testimonials as $testimonial): ?>
                    <article class="rounded-[28px] border border-white/80 bg-white p-6 shadow-[0_18px_40px_rgba(15,23,42,0.06)]">
                        <div class="text-lg tracking-[0.2em] text-[#f0a243]">
                            <?= str_repeat('★', (int) ($testimonial['rating'] ?? 5)) ?>
                        </div>
                        <p class="mt-4 text-sm leading-7 text-slate-600">
                            <?= e((string) ($testimonial['quote'] ?? 'Excellent service experience.')) ?>
                        </p>
                        <div class="mt-5">
                            <strong class="block text-base font-black tracking-[-0.02em] text-slate-900">
                                <?= e((string) ($testimonial['name'] ?? 'Client')) ?>
                            </strong>
                            <span class="mt-1 block text-sm text-slate-500">
                                <?= e(trim((string) (($testimonial['designation'] ?? '') . ' ' . ($testimonial['company'] ?? '')))) ?>
                            </span>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <?php for ($i = 1; $i <= 3; $i++): ?>
                    <article class="rounded-[28px] border border-dashed border-accent-200 bg-white p-6 shadow-[0_18px_40px_rgba(15,23,42,0.04)]">
                        <div class="text-lg tracking-[0.2em] text-[#f0a243]">★★★★★</div>
                        <p class="mt-4 text-sm leading-7 text-slate-600">Client feedback will appear here to reinforce credibility and trust.</p>
                        <div class="mt-5">
                            <strong class="block text-base font-black tracking-[-0.02em] text-slate-900">Client Review Placeholder</strong>
                            <span class="mt-1 block text-sm text-slate-500">Professional Testimonial</span>
                        </div>
                    </article>
                <?php endfor; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<section id="contact-section" class="px-4 py-12 sm:px-6 lg:px-10">
    <div class="grid gap-6 xl:grid-cols-[1fr_0.88fr]">
        <div class="rounded-[32px] border border-white/70 bg-white/85 p-6 shadow-[0_18px_40px_rgba(15,23,42,0.06)] sm:p-8">
            <div class="max-w-xl">
                <span class="inline-flex rounded-full border border-brand-100 bg-brand-50 px-4 py-2 text-[11px] font-extrabold uppercase tracking-[0.24em] text-brand-700">
                    FAQ
                </span>
                <h2 class="mt-4 text-3xl font-black tracking-[-0.04em] text-slate-900">
                    Common questions
                </h2>
            </div>

            <div class="mt-8 space-y-4">
                <?php if (!empty($faqs)): ?>
                    <?php foreach ($faqs as $faq): ?>
                        <details class="group rounded-[24px] border border-slate-200 bg-slate-50/70 p-5 transition open:bg-white open:shadow-sm">
                            <summary class="cursor-pointer list-none pr-8 text-base font-extrabold tracking-[-0.02em] text-slate-900">
                                <?= e((string) ($faq['question'] ?? 'Question')) ?>
                            </summary>
                            <p class="mt-4 text-sm leading-7 text-slate-600">
                                <?= nl2br(e((string) ($faq['answer'] ?? ''))) ?>
                            </p>
                        </details>
                    <?php endforeach; ?>
                <?php else: ?>
                    <details class="rounded-[24px] border border-slate-200 bg-slate-50/70 p-5">
                        <summary class="cursor-pointer list-none pr-8 text-base font-extrabold tracking-[-0.02em] text-slate-900">
                            How are services presented here?
                        </summary>
                        <p class="mt-4 text-sm leading-7 text-slate-600">
                            Common questions and answers will appear here in a clean, easy-to-navigate format.
                        </p>
                    </details>
                <?php endif; ?>
            </div>
        </div>

        <div class="rounded-[32px] bg-gradient-to-br from-[#3852B4] via-[#5E7AC4] to-[#7f96d4] p-[1px] shadow-[0_24px_60px_rgba(56,82,180,0.20)]">
            <div class="h-full rounded-[31px] bg-white/95 p-6 sm:p-8">
                <div class="max-w-md">
                    <span class="inline-flex rounded-full border border-accent-200 bg-accent-100/60 px-4 py-2 text-[11px] font-extrabold uppercase tracking-[0.24em] text-[#b86a1d]">
                        Get in touch
                    </span>
                    <h3 class="mt-4 text-3xl font-black tracking-[-0.04em] text-slate-900">Request a callback</h3>
                    <p class="mt-3 text-sm leading-7 text-slate-600">Share your requirement and our team will get back to you quickly.</p>
                </div>

                <form method="post" action="<?= e(base_url('contact-submit')) ?>" class="mt-8 grid gap-4">
                    <?= csrf_field() ?>

                    <input class="h-14 rounded-2xl border border-slate-200 bg-white px-4 text-sm font-medium text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-brand-300" type="text" name="name" placeholder="Your name" required>
                    <input class="h-14 rounded-2xl border border-slate-200 bg-white px-4 text-sm font-medium text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-brand-300" type="text" name="phone" placeholder="WhatsApp number" required>
                    <input class="h-14 rounded-2xl border border-slate-200 bg-white px-4 text-sm font-medium text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-brand-300" type="email" name="email" placeholder="Email address">
                    <input class="h-14 rounded-2xl border border-slate-200 bg-white px-4 text-sm font-medium text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-brand-300" type="text" name="service" placeholder="Service interested in">
                    <textarea class="min-h-[140px] rounded-2xl border border-slate-200 bg-white px-4 py-4 text-sm font-medium text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-brand-300" name="message" rows="5" placeholder="Tell us about your requirement"></textarea>

                    <button class="inline-flex items-center justify-center rounded-full bg-gradient-to-r from-[#3852B4] to-[#5E7AC4] px-6 py-3.5 text-sm font-extrabold text-white shadow-[0_18px_36px_rgba(56,82,180,0.22)] transition hover:-translate-y-0.5" type="submit">
                        Request Callback
                    </button>
                </form>
            </div>
        </div>
    </div>
</section>

<script>
(function () {
    const root = document.querySelector('[data-banner-slider]');
    if (!root) return;

    const slides = Array.from(root.querySelectorAll('[data-slide]'));
    const dots = Array.from(root.querySelectorAll('[data-slide-dot]'));
    const prev = root.querySelector('[data-slide-prev]');
    const next = root.querySelector('[data-slide-next]');
    const delay = parseInt(root.getAttribute('data-slider-autoplay') || '5000', 10);
    let current = 0;
    let timer = null;

    if (!slides.length) return;

    function render(index) {
        current = index;

        slides.forEach((slide, i) => {
            if (i === index) {
                slide.classList.remove('opacity-0', 'pointer-events-none', 'absolute', 'inset-0');
                slide.classList.add('opacity-100', 'relative');
            } else {
                slide.classList.remove('opacity-100', 'relative');
                slide.classList.add('opacity-0', 'pointer-events-none', 'absolute', 'inset-0');
            }
        });

        dots.forEach((dot, i) => {
            if (i === index) {
                dot.classList.add('w-8', 'bg-white');
                dot.classList.remove('w-3', 'bg-white/60');
            } else {
                dot.classList.remove('w-8', 'bg-white');
                dot.classList.add('w-3', 'bg-white/60');
            }
        });
    }

    function goNext() {
        render((current + 1) % slides.length);
    }

    function goPrev() {
        render((current - 1 + slides.length) % slides.length);
    }

    function start() {
        stop();
        timer = setInterval(goNext, delay);
    }

    function stop() {
        if (timer) clearInterval(timer);
    }

    if (prev) {
        prev.addEventListener('click', function () {
            goPrev();
            start();
        });
    }

    if (next) {
        next.addEventListener('click', function () {
            goNext();
            start();
        });
    }

    dots.forEach((dot, i) => {
        dot.addEventListener('click', function () {
            render(i);
            start();
        });
    });

    root.addEventListener('mouseenter', stop);
    root.addEventListener('mouseleave', start);
    root.addEventListener('touchstart', stop, { passive: true });
    root.addEventListener('touchend', start, { passive: true });

    render(0);
    start();
})();
</script>