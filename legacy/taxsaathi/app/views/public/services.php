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
?>


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

<section class="px-4 py-12 sm:px-6 lg:px-10">
    <div class="rounded-[36px] border border-white/70 bg-white/70 p-6 shadow-[0_20px_50px_rgba(15,23,42,0.06)] sm:p-8 lg:p-10">
        <div class="mx-auto max-w-3xl text-center">
            <span class="inline-flex rounded-full border border-accent-200 bg-accent-100/60 px-4 py-2 text-[11px] font-extrabold uppercase tracking-[0.24em] text-[#b86a1d] shadow-sm">
                Service Categories
            </span>
            <h2 class="mt-4 text-3xl font-black tracking-[-0.04em] text-slate-900 sm:text-4xl">
                Explore Our Services
            </h2>
            <p class="mt-4 text-base leading-8 text-slate-600">
                Choose a category to open a dedicated section with services only from that category.
            </p>
        </div>

        <?php if (!empty($categories)): ?>
            <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
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
                    ?>

                    <a
                        href="<?= e($categoryUrl) ?>"
                        class="group overflow-hidden rounded-[28px] border border-white/80 bg-white shadow-[0_12px_30px_rgba(15,23,42,0.06)] transition duration-300 hover:-translate-y-1 hover:shadow-[0_18px_36px_rgba(56,82,180,0.12)]"
                    >
                        <div class="relative h-44 overflow-hidden bg-gradient-to-br from-[#eef4ff] via-[#f8faff] to-[#fff3e8]">
                            <?php if ($categoryImageUrl !== ''): ?>
                                <img
                                    src="<?= e($categoryImageUrl) ?>"
                                    alt="<?= e($categoryTitle) ?>"
                                    class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.04]"
                                >
                                <div class="absolute inset-0 bg-gradient-to-t from-slate-950/45 via-slate-900/10 to-transparent"></div>
                            <?php else: ?>
                                <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(94,122,196,0.18),transparent_42%),radial-gradient(circle_at_bottom_left,rgba(248,161,63,0.16),transparent_38%)]"></div>
                                <div class="relative flex h-full items-center justify-center">
                                    <span class="inline-flex h-16 w-16 items-center justify-center rounded-[22px] bg-gradient-to-br from-[#5E7AC4] to-[#3852B4] text-sm font-black tracking-[0.16em] text-white shadow-[0_16px_30px_rgba(56,82,180,0.20)]">
                                        <?= e($categoryFallback) ?>
                                    </span>
                                </div>
                            <?php endif; ?>

                            <div class="absolute right-3 top-3 inline-flex rounded-full bg-white/90 px-3 py-1 text-[10px] font-extrabold uppercase tracking-[0.12em] text-slate-700 backdrop-blur-sm">
                                <?= e((string) $categoryCount) ?> Services
                            </div>
                        </div>

                        <div class="p-5">
                            <h3 class="text-lg font-black tracking-[-0.03em] text-slate-900">
                                <?= e($categoryTitle) ?>
                            </h3>

                            <p class="mt-2 line-clamp-2 text-sm leading-6 text-slate-600">
                                <?= e($categoryDesc !== '' ? $categoryDesc : 'Open this category to view all services inside it.') ?>
                            </p>

                            <div class="mt-4 inline-flex items-center rounded-full bg-slate-900 px-3 py-1.5 text-[10px] font-extrabold uppercase tracking-[0.12em] text-white">
Click Here                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="mt-10 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <?php for ($i = 1; $i <= 4; $i++): ?>
                    <article class="overflow-hidden rounded-[22px] border border-dashed border-brand-200 bg-white/80 shadow-[0_12px_30px_rgba(15,23,42,0.04)]">
                        <div class="flex h-40 items-center justify-center bg-gradient-to-br from-[#eef4ff] via-[#f8faff] to-[#fff3e8]">
                            <div class="inline-flex h-14 w-14 items-center justify-center rounded-[18px] bg-brand-100 text-[11px] font-black tracking-[0.12em] text-brand-700">
                                TS
                            </div>
                        </div>
                        <div class="p-5 text-center">
                            <h3 class="text-base font-black tracking-[-0.02em] text-slate-900">Category Coming Soon</h3>
                            <p class="mt-2 text-sm leading-6 text-slate-600">Service categories will appear here.</p>
                        </div>
                    </article>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

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