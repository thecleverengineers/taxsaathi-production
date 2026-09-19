<?php
declare(strict_types=1);

$category = is_array($category ?? null) ? $category : [];
$services = is_array($services ?? null) ? array_values($services) : [];

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

$categoryTitle = trim((string) ($category['title'] ?? 'Service Category'));
$categoryImage = trim((string) ($category['image'] ?? ''));
$categoryDesc  = trim((string) ($category['description'] ?? ''));
$categoryImageUrl = $resolveAssetUrl($categoryImage);
$categoryFallback = $iconFallbackText($categoryTitle, 'TS');
?>

<section class="px-4 py-10 sm:px-6 lg:px-10">
    <div class="overflow-hidden rounded-[32px] border border-white/70 bg-white shadow-[0_20px_50px_rgba(15,23,42,0.06)]">
        <div class="relative min-h-[260px] overflow-hidden bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900">
            <?php if ($categoryImageUrl !== ''): ?>
                <img
                    src="<?= e($categoryImageUrl) ?>"
                    alt="<?= e($categoryTitle) ?>"
                    class="absolute inset-0 h-full w-full object-cover"
                >
                <div class="absolute inset-0 bg-[linear-gradient(90deg,rgba(15,23,42,0.84)_0%,rgba(15,23,42,0.60)_45%,rgba(15,23,42,0.72)_100%)]"></div>
            <?php else: ?>
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(94,122,196,.22),transparent_24%),radial-gradient(circle_at_bottom_right,rgba(240,141,57,.18),transparent_20%)]"></div>
            <?php endif; ?>

            <div class="relative z-10 flex min-h-[260px] flex-col justify-center px-6 py-8 sm:px-8 lg:px-10">
                <a
                    href="<?= e(base_url('services')) ?>"
                    class="inline-flex w-fit items-center rounded-full border border-white/20 bg-white/10 px-4 py-2 text-[11px] font-extrabold uppercase tracking-[0.16em] text-white backdrop-blur-md"
                >
                    ← Back to Categories
                </a>

                <div class="mt-5 flex items-center gap-4">
                    <?php if ($categoryImageUrl !== ''): ?>
                        <span class="inline-flex h-16 w-16 overflow-hidden rounded-[22px] bg-white ring-1 ring-white/20">
                            <img src="<?= e($categoryImageUrl) ?>" alt="<?= e($categoryTitle) ?>" class="h-full w-full object-cover">
                        </span>
                    <?php else: ?>
                        <span class="inline-flex h-16 w-16 items-center justify-center rounded-[22px] bg-white/10 text-sm font-black tracking-[0.16em] text-white backdrop-blur-md">
                            <?= e($categoryFallback) ?>
                        </span>
                    <?php endif; ?>

                    <div>
                        <h1 class="text-3xl font-black tracking-[-0.04em] text-white sm:text-4xl">
                            <?= e($categoryTitle) ?>
                        </h1>
                        <p class="mt-2 max-w-2xl text-sm leading-7 text-white/80">
                            <?= e($categoryDesc !== '' ? $categoryDesc : 'Services available under this category.') ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="px-6 py-8 sm:px-8 lg:px-10">
            <?php if (!empty($services)): ?>
                <div class="grid gap-4 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    <?php foreach ($services as $service): ?>
                        <?php
                            $title        = (string) ($service['title'] ?? 'Service');
                            $excerpt      = (string) ($service['excerpt'] ?? 'Professional tax and compliance support.');
                            $description  = (string) ($service['description'] ?? '');
                            $slug         = (string) ($service['slug'] ?? '');
                            $turnaround   = (int) ($service['turnaround_days'] ?? 0);
                            $fee          = (float) ($service['filing_fee'] ?? 0);
                            $thumbnailUrl = $serviceThumbnail($service);

                            $serviceIconRaw      = trim((string) ($service['icon'] ?? ''));
                            $serviceIconIsImage  = $isImageIcon($serviceIconRaw);
                            $serviceIconUrl      = $serviceIconIsImage ? $getIconUrl($serviceIconRaw) : '';
                            $serviceIconFallback = $iconFallbackText($title, 'TS');

                            $detailsUrl = base_url('service-details?service=' . urlencode($slug !== '' ? $slug : (string) ($service['id'] ?? '')));
                        ?>

                        <article
                            onclick="window.location.href='<?= e($detailsUrl) ?>'"
                            class="group flex h-full cursor-pointer flex-col overflow-hidden rounded-[22px] border border-white/80 bg-white shadow-[0_12px_30px_rgba(15,23,42,0.06)] transition duration-300 hover:-translate-y-1 hover:shadow-[0_18px_36px_rgba(56,82,180,0.12)]"
                        >
                            <div class="relative">
                                <?php if ($thumbnailUrl !== ''): ?>
                                    <div class="h-32 w-full overflow-hidden bg-slate-100">
                                        <img
                                            src="<?= e($thumbnailUrl) ?>"
                                            alt="<?= e($title) ?>"
                                            class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.04]"
                                        >
                                    </div>
                                <?php else: ?>
                                    <div class="relative flex h-32 w-full items-center justify-center overflow-hidden bg-gradient-to-br from-[#eef4ff] via-[#f8faff] to-[#fff3e8]">
                                        <?php if ($serviceIconIsImage): ?>
                                            <div class="relative inline-flex h-100 w-100 overflow-hidden rounded-[16px] bg-white shadow-[0_12px_24px_rgba(56,82,180,0.10)] ring-1 ring-white/70">
                                                <img src="<?= e($serviceIconUrl) ?>" alt="<?= e($title) ?>" class="h-full w-full object-cover">
                                            </div>
                                        <?php else: ?>
                                            <div class="relative inline-flex h-12 w-12 items-center justify-center rounded-[16px] bg-gradient-to-br from-[#5E7AC4] to-[#3852B4] text-[10px] font-black tracking-[0.12em] text-white shadow-[0_12px_24px_rgba(56,82,180,0.18)]">
                                                <?= e($serviceIconFallback) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <div class="absolute left-2 top-2 flex flex-wrap gap-1.5">
                                    <span class="inline-flex items-center rounded-full bg-white/90 px-2 py-1 text-[8px] font-extrabold uppercase tracking-[0.12em] text-emerald-700 backdrop-blur-sm">
                                        Verified
                                    </span>
                                    <span class="inline-flex items-center rounded-full bg-white/90 px-2 py-1 text-[8px] font-extrabold uppercase tracking-[0.12em] text-amber-700 backdrop-blur-sm">
                                        <?= e($categoryTitle) ?>
                                    </span>
                                </div>
                            </div>

                            <div class="flex flex-1 flex-col p-4">
                                <div class="flex items-start gap-2.5">
                                    <?php if ($serviceIconIsImage): ?>
                                        <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-white ring-1 ring-slate-200">
                                            <img src="<?= e($serviceIconUrl) ?>" alt="<?= e($title) ?>" class="h-full w-full object-cover">
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-[9px] font-black tracking-[0.12em] text-slate-700">
                                            <?= e($serviceIconFallback) ?>
                                        </span>
                                    <?php endif; ?>

                                    <div class="min-w-0 flex-1">
                                        <h3 class="truncate text-sm font-black tracking-[-0.02em] text-slate-900">
                                            <?= e($title) ?>
                                        </h3>
                                        <p class="mt-1 line-clamp-2 text-[11px] leading-5 text-slate-600">
                                            <?= e($excerpt) ?>
                                        </p>
                                    </div>
                                </div>

                                <p class="mt-2 line-clamp-2 text-[11px] leading-5 text-slate-500">
                                    <?= e($description !== '' ? $description : 'Professional filing and compliance support.') ?>
                                </p>

                                <div class="mt-3 flex items-center justify-between gap-2 rounded-xl bg-slate-50 px-3 py-2">
                                    <div class="min-w-0">
                                        <p class="text-[9px] font-extrabold uppercase tracking-[0.12em] text-slate-400">
                                            Application Price
                                        </p>
                                        <strong class="block truncate text-sm font-black text-brand-700">
                                            <?= e(format_money($fee)) ?>
                                        </strong>
                                    </div>

                                    <span class="inline-flex items-center rounded-full bg-slate-900 px-2 py-1 text-[8px] font-extrabold uppercase tracking-[0.12em] text-white">
                                        <?= e((string) $turnaround) ?>D
                                    </span>
                                </div>

                                <div class="mt-3 grid gap-2">
                                   <?php
$callNumber = '+917576899990';
$whatsappNumber = '917576899990';

$whatsappMessage = rawurlencode(
    'Hello, I need assistance regarding Tax Saathi services.'
);
?>

<div class="grid grid-cols-2 gap-2">

    <!-- Direct Call -->
    <a
        class="inline-flex w-full items-center justify-center gap-2 rounded-full border border-emerald-200 bg-white px-3 py-2 text-[10px] font-extrabold uppercase tracking-[0.12em] text-emerald-700 transition hover:-translate-y-0.5 hover:bg-emerald-50"
        href="tel:<?= e($callNumber) ?>"
        onclick="event.stopPropagation();"
        aria-label="Call us"
    >
        <i class="ri-phone-line text-base"></i>
        Call Us
    </a>

    <!-- WhatsApp -->
    <a
        class="inline-flex w-full items-center justify-center gap-2 rounded-full border border-green-200 bg-white px-3 py-2 text-[10px] font-extrabold uppercase tracking-[0.12em] text-green-700 transition hover:-translate-y-0.5 hover:bg-green-50"
        href="https://wa.me/<?= e($whatsappNumber) ?>?text=<?= e($whatsappMessage) ?>"
        target="_blank"
        rel="noopener noreferrer"
        onclick="event.stopPropagation();"
        aria-label="Chat on WhatsApp"
    >
        <i class="ri-whatsapp-line text-base"></i>
        WhatsApp
    </a>

</div>
                                    <a
                                            class="inline-flex w-full items-center justify-center rounded-full bg-gradient-to-r from-[#3852B4] to-[#5E7AC4] px-3 py-2 text-[10px] font-extrabold uppercase tracking-[0.12em] text-white shadow-[0_12px_24px_rgba(56,82,180,0.18)] transition hover:-translate-y-0.5"
                                            href="<?= e(base_url('service-order?service=' . urlencode($slug))) ?>"
                                            onclick="event.stopPropagation();"
                                        >
                                            Apply Now
                                        </a>

                            
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="rounded-[24px] border border-dashed border-slate-200 bg-slate-50 px-6 py-12 text-center">
                    <h3 class="text-lg font-black tracking-[-0.02em] text-slate-900">No services in this category yet</h3>
                    <p class="mt-2 text-sm leading-7 text-slate-600">Add services to this category and they will appear here.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>