<?php
/** @var array $stats */
/** @var array $services */
/** @var array $categories */
/** @var array $iconGallery */

$stats = is_array($stats ?? null) ? array_merge([
    'total_services' => 0,
    'active_services' => 0,
    'featured_count' => 0,
], $stats) : [
    'total_services' => 0,
    'active_services' => 0,
    'featured_count' => 0,
];

$services = is_array($services ?? null) ? array_values($services) : [];
$categories = is_array($categories ?? null) ? array_values($categories) : [];
$iconGallery = is_array($iconGallery ?? null) ? array_values($iconGallery) : [];

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

$normalizeForFilter = static function (?string $value): string {
    $value = trim((string) $value);

    if ($value === '') {
        return '';
    }

    return function_exists('mb_strtolower')
        ? mb_strtolower($value, 'UTF-8')
        : strtolower($value);
};

/*
 * Strict final display guard:
 * Show every service title only once on this dashboard, even if the database
 * still contains duplicate rows or another query passes repeated records.
 */
$makeServiceUniqueKey = static function (array $service) use ($normalizeForFilter): string {
    $title = preg_replace('/\s+/u', ' ', (string) ($service['title'] ?? '')) ?? (string) ($service['title'] ?? '');
    $slug = preg_replace('/\s+/u', ' ', (string) ($service['slug'] ?? '')) ?? (string) ($service['slug'] ?? '');

    $title = $normalizeForFilter($title);
    $slug = $normalizeForFilter($slug);
    $id = (int) ($service['id'] ?? 0);

    if ($title !== '') {
        return 'title:' . $title;
    }

    if ($slug !== '') {
        return 'slug:' . $slug;
    }

    return $id > 0 ? 'id:' . $id : '';
};

$uniqueServices = [];
$seenServiceKeys = [];

foreach ($services as $service) {
    if (!is_array($service)) {
        continue;
    }

    $serviceKey = $makeServiceUniqueKey($service);

    if ($serviceKey === '' || isset($seenServiceKeys[$serviceKey])) {
        continue;
    }

    $seenServiceKeys[$serviceKey] = true;
    $uniqueServices[] = $service;
}

$services = array_values($uniqueServices);

$stats['total_services'] = count($services);
$stats['active_services'] = count(array_filter(
    $services,
    static fn(array $service): bool => (int) ($service['is_active'] ?? 0) === 1
));
$stats['featured_count'] = count(array_filter(
    $services,
    static fn(array $service): bool => (int) ($service['is_featured'] ?? 0) === 1
));
?>

<style>
    .ts-services-page {
        --ts-card-bg: #ffffff;
        --ts-card-soft: #f8fafc;
        --ts-text: #0f172a;
        --ts-muted: #64748b;
        --ts-border: #e2e8f0;
        --ts-hero-from: #053b73;
        --ts-hero-via: #075b9a;
        --ts-hero-to: #0ea5c6;
    }

    html[data-theme="dark"] .ts-services-page {
        --ts-card-bg: rgba(13, 27, 46, 0.94);
        --ts-card-soft: rgba(255, 255, 255, 0.06);
        --ts-text: #f8fafc;
        --ts-muted: #cbd5e1;
        --ts-border: rgba(255, 255, 255, 0.10);
        --ts-hero-from: #06233e;
        --ts-hero-via: #073b63;
        --ts-hero-to: #075b9a;
        color: #dbe7f3;
    }

    .ts-services-page .ts-hero {
        background: radial-gradient(circle at top left, rgba(255,255,255,0.16), transparent 28%),
            linear-gradient(135deg, var(--ts-hero-from), var(--ts-hero-via), var(--ts-hero-to));
    }

    .ts-services-page .ts-panel,
    .ts-services-page .ts-card,
    .ts-services-page .ts-gallery-card {
        background: var(--ts-card-bg);
        border-color: var(--ts-border);
    }

    .ts-services-page .ts-panel-flat,
    .ts-services-page .ts-flat-stat {
        background: transparent;
        border-color: var(--ts-border);
    }

    .ts-services-page .ts-soft {
        background: var(--ts-card-soft);
        border-color: var(--ts-border);
    }

    .ts-services-page .ts-title {
        color: var(--ts-text);
    }

    .ts-services-page .ts-muted {
        color: var(--ts-muted);
    }

    html[data-theme="dark"] .ts-services-page .bg-white {
        background-color: var(--ts-card-bg) !important;
    }

    html[data-theme="dark"] .ts-services-page .bg-slate-50,
    html[data-theme="dark"] .ts-services-page .bg-slate-100 {
        background-color: var(--ts-card-soft) !important;
    }

    html[data-theme="dark"] .ts-services-page .border-slate-100,
    html[data-theme="dark"] .ts-services-page .border-slate-200,
    html[data-theme="dark"] .ts-services-page .border-slate-300 {
        border-color: var(--ts-border) !important;
    }

    html[data-theme="dark"] .ts-services-page .divide-slate-100 > :not([hidden]) ~ :not([hidden]) {
        border-color: var(--ts-border) !important;
    }

    html[data-theme="dark"] .ts-services-page .text-slate-900,
    html[data-theme="dark"] .ts-services-page .text-slate-800,
    html[data-theme="dark"] .ts-services-page .text-slate-700 {
        color: #f8fafc !important;
    }

    html[data-theme="dark"] .ts-services-page .text-slate-600,
    html[data-theme="dark"] .ts-services-page .text-slate-500,
    html[data-theme="dark"] .ts-services-page .text-slate-400 {
        color: #cbd5e1 !important;
    }

    html[data-theme="dark"] .ts-services-page input,
    html[data-theme="dark"] .ts-services-page select,
    html[data-theme="dark"] .ts-services-page textarea {
        background-color: rgba(255,255,255,0.07) !important;
        border-color: rgba(255,255,255,0.12) !important;
        color: #f8fafc !important;
    }

    html[data-theme="dark"] .ts-services-page input::placeholder,
    html[data-theme="dark"] .ts-services-page textarea::placeholder {
        color: rgba(203, 213, 225, 0.62) !important;
    }

    html[data-theme="dark"] .ts-services-page option {
        background: #0d1b2e;
        color: #f8fafc;
    }

    html[data-theme="dark"] .ts-services-page .hover\:bg-slate-50:hover,
    html[data-theme="dark"] .ts-services-page .hover\:bg-white:hover,
    html[data-theme="dark"] .ts-services-page .hover\:bg-slate-50\/70:hover {
        background-color: rgba(255,255,255,0.08) !important;
    }

    html[data-theme="dark"] .ts-services-page .shadow-sm,
    html[data-theme="dark"] .ts-services-page .shadow-\[0_18px_40px_rgba\(15\,23\,42\,0\.05\)\],
    html[data-theme="dark"] .ts-services-page .shadow-\[0_20px_60px_rgba\(15\,23\,42\,0\.08\)\] {
        box-shadow: 0 18px 45px rgba(0,0,0,0.22) !important;
    }

    html[data-theme="dark"] .ts-services-page .ring-slate-200,
    html[data-theme="dark"] .ts-services-page .ring-sky-100 {
        --tw-ring-color: rgba(255,255,255,0.12) !important;
    }

    html[data-theme="dark"] .ts-services-page .ts-dark-invert {
        background: rgba(255,255,255,0.09) !important;
        color: #f8fafc !important;
        border-color: rgba(255,255,255,0.12) !important;
    }

    .ts-services-page .ts-premium-scroll {
        scrollbar-width: thin;
        scrollbar-color: rgba(14, 165, 198, 0.58) transparent;
    }

    .ts-services-page .ts-premium-scroll::-webkit-scrollbar {
        width: 6px;
        height: 6px;
    }

    .ts-services-page .ts-premium-scroll::-webkit-scrollbar-track {
        background: transparent;
    }

    .ts-services-page .ts-premium-scroll::-webkit-scrollbar-thumb {
        background: linear-gradient(180deg, rgba(14,165,198,0.72), rgba(45,140,255,0.62));
        border-radius: 999px;
    }

    .ts-services-page .ts-hero-flat {
        background: transparent;
    }

    .ts-services-page .ts-panel-flat,
    .ts-services-page .ts-flat-stat {
        border-color: var(--ts-border);
    }

    html[data-theme="dark"] .ts-services-page .ts-hero-flat {
        border-color: rgba(255,255,255,0.10) !important;
    }

    html[data-theme="dark"] .ts-services-page .ts-panel-flat,
    html[data-theme="dark"] .ts-services-page .ts-flat-stat {
        background: transparent !important;
        border-color: rgba(255,255,255,0.10) !important;
    }

    html[data-theme="dark"] .ts-services-page .border-y,
    html[data-theme="dark"] .ts-services-page .border-b,
    html[data-theme="dark"] .ts-services-page .divide-slate-200 > :not([hidden]) ~ :not([hidden]) {
        border-color: rgba(255,255,255,0.10) !important;
    }


    .ts-services-page .ts-filter-input:focus {
        border-color: #075B9A !important;
        box-shadow: 0 0 0 4px rgba(7, 91, 154, 0.10);
    }

    .ts-services-page .service-record-item.is-hidden {
        display: none !important;
    }

    .ts-services-page .ts-filter-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        border-radius: 999px;
        border: 1px solid var(--ts-border);
        background: var(--ts-card-soft);
        padding: 0.35rem 0.7rem;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: var(--ts-muted);
    }

</style>



<div class="ts-services-page space-y-6">
    <section class="ts-hero-flat border-b border-slate-200 pb-5">
        <div class="flex flex-col gap-5 py-4 sm:py-5 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-2xl">
                <span class="inline-flex items-center rounded-full border border-sky-200 bg-sky-50 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.22em] text-sky-700">
                    Service Management
                </span>

                <h1 class="mt-3 text-2xl font-black tracking-[-0.04em] text-slate-900 sm:text-[2rem] ts-title">
                    Manage Services
                </h1>

                <p class="mt-2 max-w-xl text-sm leading-6 text-slate-500 ts-muted">
                    Create, organize, and manage all service records from one elegant dashboard with separate service category mapping.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <button
                    type="button"
                    id="toggleCreateService"
                    class="inline-flex h-11 items-center justify-center rounded-xl bg-[#075B9A] px-5 text-sm font-semibold text-white transition hover:bg-[#053B73]"
                    aria-expanded="false"
                    aria-controls="createServicePanel"
                >
                    + Create New Service
                </button>
            </div>
        </div>
    </section>

    <section class="grid grid-cols-1 gap-0 divide-y divide-slate-200 rounded-none border-y border-slate-200 md:grid-cols-3 md:divide-x md:divide-y-0">
        <div class="ts-flat-stat bg-transparent p-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.20em] text-slate-500">Total Services</p>
                    <h3 class="mt-2 text-3xl font-black tracking-[-0.04em] text-slate-900"><?= e((string) $stats['total_services']) ?></h3>
                </div>
                <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-slate-900 text-lg text-white">#</span>
            </div>
            <p class="mt-4 text-sm text-slate-500">Overall service records in the system.</p>
        </div>

        <div class="ts-flat-stat bg-transparent p-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.20em] text-slate-500">Active Services</p>
                    <h3 class="mt-2 text-3xl font-black tracking-[-0.04em] text-emerald-600"><?= e((string) $stats['active_services']) ?></h3>
                </div>
                <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-50 text-lg text-emerald-600">✓</span>
            </div>
            <p class="mt-4 text-sm text-slate-500">Currently active and visible services.</p>
        </div>

        <div class="ts-flat-stat bg-transparent p-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.20em] text-slate-500">Featured</p>
                    <h3 class="mt-2 text-3xl font-black tracking-[-0.04em] text-amber-600"><?= e((string) $stats['featured_count']) ?></h3>
                </div>
                <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-amber-50 text-lg text-amber-600">★</span>
            </div>
            <p class="mt-4 text-sm text-slate-500">Highlighted services for priority display.</p>
        </div>
    </section>

    <section
        id="createServicePanel"
        class="ts-panel-flat hidden border-y border-slate-200 bg-transparent"
    >
        <div class="border-b border-slate-200 px-0 py-5">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.20em] text-slate-500">Create Service</p>
                    <h2 class="mt-1 text-[1.35rem] font-black tracking-[-0.04em] text-slate-900 ts-title">Add New Service</h2>
                </div>

                <span class="inline-flex rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-600">
                    Category + auto slug + upload + image gallery
                </span>
            </div>
        </div>

        <form method="post" action="<?= e(base_url('admin/services/save-service')) ?>" enctype="multipart/form-data" class="py-5 sm:py-6">
            <?= csrf_field() ?>

            <input type="hidden" name="selected_icon" id="selected_icon" value="">

            <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
                <div class="lg:col-span-2">
                    <label for="service_title" class="mb-2 block text-sm font-semibold text-slate-800">Service Title</label>
                    <input
                        id="service_title"
                        class="h-12 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm text-slate-700 outline-none transition focus:border-slate-400 focus:bg-white"
                        type="text"
                        name="title"
                        placeholder="Enter service title"
                        required
                    >
                </div>

                <div>
                    <label for="service_slug" class="mb-2 block text-sm font-semibold text-slate-800">Service Slug</label>
                    <input
                        id="service_slug"
                        class="h-12 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm text-slate-700 outline-none transition focus:border-slate-400 focus:bg-white"
                        type="text"
                        name="slug"
                        placeholder="auto-generated-from-title"
                    >
                    <p class="mt-2 text-xs text-slate-500">Slug auto-generates while typing the title. You can still edit it manually.</p>
                </div>

                <div>
                    <label for="service_category_id" class="mb-2 block text-sm font-semibold text-slate-800">Service Category</label>
                    <select
                        id="service_category_id"
                        name="service_category_id"
                        class="h-12 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm text-slate-700 outline-none transition focus:border-slate-400 focus:bg-white"
                        required
                    >
                        <option value="">Select category</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= e((string) $category['id']) ?>">
                                <?= e((string) $category['title']) ?><?= (int) ($category['is_active'] ?? 1) === 0 ? ' (Inactive)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="mt-2 text-xs text-slate-500">This service will be displayed under the selected category on the public side.</p>
                </div>

                <div>
                    <span class="mb-2 block text-sm font-semibold text-slate-800">Service Icon</span>

                    <div class="grid gap-3">
                        <label
                            for="icon_file"
                            class="group flex min-h-[124px] w-full cursor-pointer flex-col items-center justify-center rounded-xl border border-dashed border-slate-300 bg-white px-4 py-5 text-center transition hover:border-slate-400 hover:bg-white"
                        >
                            <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-900 text-white shadow-[0_12px_30px_rgba(15,23,42,0.16)] transition">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 0l-4 4m4-4l4 4M4 16.5v1.25A2.25 2.25 0 006.25 20h11.5A2.25 2.25 0 0020 17.75V16.5" />
                                </svg>
                            </span>

                            <span class="mt-3 block text-sm font-semibold text-slate-800">Upload new icon</span>
                            <span class="mt-1 block text-xs text-slate-500">Choose from gallery / files on device</span>
                            <span class="mt-2 inline-flex rounded-full border border-slate-200 bg-white px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-600">
                                Browse Image
                            </span>
                        </label>

                        <input
                            id="icon_file"
                            type="file"
                            name="icon_file"
                            accept="image/*,.svg"
                            class="hidden"
                        >

                        <div class="flex flex-wrap items-center gap-3">
                            <button
                                type="button"
                                id="toggleIconGallery"
                                class="inline-flex h-11 items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                            >
                                Choose from Uploaded Gallery
                            </button>

                            <span class="inline-flex rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-600">
                                <?= e((string) count($iconGallery)) ?> Uploaded Images
                            </span>
                        </div>
                    </div>

                    <div id="iconPreviewWrapper" class="mt-3 hidden">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Selected Icon</p>

                        <div class="inline-flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-3 py-3 shadow-sm">
                            <img id="iconPreview" src="" alt="Icon Preview" class="h-14 w-14 rounded-2xl object-cover ring-1 ring-slate-200">
                            <div>
                                <p id="iconFileName" class="max-w-[220px] truncate text-sm font-medium text-slate-700"></p>
                                <p id="iconFileMeta" class="text-xs text-slate-500">Selected icon file</p>
                            </div>
                        </div>
                    </div>

                    <div id="iconGalleryPanel" class="mt-4 hidden overflow-hidden rounded-xl border-y border-slate-200 bg-transparent">
                        <div class="border-b border-slate-200 bg-white px-4 py-3">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Uploaded Image Gallery</p>
                                    <h3 class="mt-0.5 text-sm font-bold text-slate-900 ts-title">Choose Existing Icon</h3>
                                </div>

                                <button
                                    type="button"
                                    id="closeIconGallery"
                                    class="inline-flex h-9 items-center justify-center rounded-xl border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-600 transition hover:bg-slate-50"
                                >
                                    Close
                                </button>
                            </div>
                        </div>

                        <?php if (!empty($iconGallery)): ?>
                            <div class="ts-premium-scroll grid max-h-[420px] grid-cols-2 gap-3 overflow-y-auto p-4 sm:grid-cols-3 lg:grid-cols-4">
                                <?php foreach ($iconGallery as $galleryIcon): ?>
                                    <button
                                        type="button"
                                        class="icon-gallery-item group overflow-hidden rounded-xl border border-slate-200 bg-transparent p-2 text-left transition hover:border-sky-300 hover:bg-sky-50/40"
                                        data-path="<?= e((string) $galleryIcon) ?>"
                                        data-url="<?= e(base_url(ltrim((string) $galleryIcon, '/'))) ?>"
                                        data-name="<?= e((string) basename((string) $galleryIcon)) ?>"
                                    >
                                        <span class="flex aspect-square w-full items-center justify-center overflow-hidden rounded-lg bg-slate-100">
                                            <img
                                                src="<?= e(base_url(ltrim((string) $galleryIcon, '/'))) ?>"
                                                alt="<?= e((string) basename((string) $galleryIcon)) ?>"
                                                class="h-full w-full object-cover"
                                            >
                                        </span>
                                        <span class="mt-2 block truncate px-1 text-xs font-medium text-slate-600">
                                            <?= e((string) basename((string) $galleryIcon)) ?>
                                        </span>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="px-4 py-10 text-center">
                                <p class="text-sm font-medium text-slate-700">No uploaded icons found.</p>
                                <p class="mt-1 text-xs text-slate-500">Upload an icon first and it will appear here for reuse.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="lg:col-span-2">
                    <label for="service_excerpt" class="mb-2 block text-sm font-semibold text-slate-800">Short Excerpt</label>
                    <input
                        id="service_excerpt"
                        class="h-12 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm text-slate-700 outline-none transition focus:border-slate-400 focus:bg-white"
                        type="text"
                        name="excerpt"
                        placeholder="Short summary for service cards"
                    >
                </div>

                <div class="lg:col-span-2">
                    <label for="service_description" class="mb-2 block text-sm font-semibold text-slate-800">Description</label>
                    <textarea
                        id="service_description"
                        class="min-h-[140px] w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-slate-400 focus:bg-white"
                        name="description"
                        rows="5"
                        placeholder="Write full service description"
                    ></textarea>
                </div>

                <div>
                    <label for="filing_fee" class="mb-2 block text-sm font-semibold text-slate-800">Filing Fee</label>
                    <input
                        id="filing_fee"
                        class="h-12 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm text-slate-700 outline-none transition focus:border-slate-400 focus:bg-white"
                        type="number"
                        step="0.01"
                        name="filing_fee"
                        value="0"
                        placeholder="0.00"
                    >
                </div>

                <div>
                    <label for="turnaround_days" class="mb-2 block text-sm font-semibold text-slate-800">Turnaround Days</label>
                    <input
                        id="turnaround_days"
                        class="h-12 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm text-slate-700 outline-none transition focus:border-slate-400 focus:bg-white"
                        type="number"
                        name="turnaround_days"
                        value="0"
                        placeholder="0"
                    >
                </div>

                <div>
                    <label for="sort_order" class="mb-2 block text-sm font-semibold text-slate-800">Sort Order</label>
                    <input
                        id="sort_order"
                        class="h-12 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm text-slate-700 outline-none transition focus:border-slate-400 focus:bg-white"
                        type="number"
                        name="sort_order"
                        value="1"
                        placeholder="1"
                    >
                </div>

                <div class="flex items-end">
                    <div class="grid w-full grid-cols-1 gap-3 sm:grid-cols-2">
                        <label class="flex h-12 items-center gap-2.5 rounded-xl border border-slate-200 bg-white px-4 text-sm font-medium text-slate-700 transition hover:bg-white">
                            <input type="checkbox" name="is_featured" value="1" class="h-4 w-4 rounded border-slate-300">
                            <span>Featured Service</span>
                        </label>

                        <label class="flex h-12 items-center gap-2.5 rounded-xl border border-slate-200 bg-white px-4 text-sm font-medium text-slate-700 transition hover:bg-white">
                            <input type="checkbox" name="is_active" value="1" checked class="h-4 w-4 rounded border-slate-300">
                            <span>Active Service</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex flex-wrap items-center gap-3">
                <button
                    class="inline-flex h-12 items-center justify-center rounded-2xl bg-[#075B9A] px-6 text-sm font-semibold text-white transition hover:bg-[#053B73]"
                    type="submit"
                >
                    Save New Service
                </button>

                <button
                    type="button"
                    id="closeCreateService"
                    class="inline-flex h-12 items-center justify-center rounded-2xl border border-slate-200 bg-white px-6 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                >
                    Cancel
                </button>
            </div>
        </form>
    </section>

    <section class="ts-panel-flat border-y border-slate-200 bg-transparent">
        <div class="border-b border-slate-200 px-0 py-5">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.20em] text-slate-500">Service Records</p>
                    <h2 class="mt-1 text-[1.35rem] font-black tracking-[-0.04em] text-slate-900 ts-title">All Services</h2>
                </div>

                <span
                    id="servicesFilterCountBadge"
                    class="inline-flex rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-600"
                    data-total-records="<?= e((string) count($services)) ?>"
                >
                    <?= e((string) count($services)) ?> Records
                </span>
            </div>
        </div>

        <?php if (!empty($services)): ?>
            <div class="border-b border-slate-200 py-5">
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-12">
                    <div class="lg:col-span-4">
                        <label for="serviceFilterSearch" class="mb-2 block text-xs font-bold uppercase tracking-[0.16em] text-slate-500">Search</label>
                        <div class="relative">
                            <input
                                id="serviceFilterSearch"
                                type="search"
                                class="ts-filter-input h-12 w-full rounded-xl border border-slate-200 bg-white px-4 pr-11 text-sm text-slate-700 outline-none transition"
                                placeholder="Search title, slug, category, excerpt..."
                                autocomplete="off"
                            >
                            <span class="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-slate-400">⌕</span>
                        </div>
                    </div>

                    <div class="lg:col-span-2">
                        <label for="serviceFilterCategory" class="mb-2 block text-xs font-bold uppercase tracking-[0.16em] text-slate-500">Category</label>
                        <select id="serviceFilterCategory" class="ts-filter-input h-12 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm text-slate-700 outline-none transition">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= e((string) $category['id']) ?>">
                                    <?= e((string) $category['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="lg:col-span-2">
                        <label for="serviceFilterStatus" class="mb-2 block text-xs font-bold uppercase tracking-[0.16em] text-slate-500">Status</label>
                        <select id="serviceFilterStatus" class="ts-filter-input h-12 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm text-slate-700 outline-none transition">
                            <option value="">All Status</option>
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>

                    <div class="lg:col-span-2">
                        <label for="serviceFilterFeatured" class="mb-2 block text-xs font-bold uppercase tracking-[0.16em] text-slate-500">Featured</label>
                        <select id="serviceFilterFeatured" class="ts-filter-input h-12 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm text-slate-700 outline-none transition">
                            <option value="">All Services</option>
                            <option value="1">Featured Only</option>
                            <option value="0">Not Featured</option>
                        </select>
                    </div>

                    <div class="lg:col-span-2">
                        <label for="serviceFilterSort" class="mb-2 block text-xs font-bold uppercase tracking-[0.16em] text-slate-500">Sort By</label>
                        <select id="serviceFilterSort" class="ts-filter-input h-12 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm text-slate-700 outline-none transition">
                            <option value="sort_asc">Sort Order: Low → High</option>
                            <option value="sort_desc">Sort Order: High → Low</option>
                            <option value="title_asc">Title: A → Z</option>
                            <option value="title_desc">Title: Z → A</option>
                            <option value="fee_asc">Fee: Low → High</option>
                            <option value="fee_desc">Fee: High → Low</option>
                            <option value="turnaround_asc">Turnaround: Fastest</option>
                            <option value="turnaround_desc">Turnaround: Longest</option>
                            <option value="id_desc">Newest First</option>
                            <option value="id_asc">Oldest First</option>
                        </select>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-12">
                    <div class="lg:col-span-2">
                        <label for="serviceFilterMinFee" class="mb-2 block text-xs font-bold uppercase tracking-[0.16em] text-slate-500">Min Fee</label>
                        <input id="serviceFilterMinFee" type="number" min="0" step="0.01" class="ts-filter-input h-12 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm text-slate-700 outline-none transition" placeholder="₹ 0">
                    </div>

                    <div class="lg:col-span-2">
                        <label for="serviceFilterMaxFee" class="mb-2 block text-xs font-bold uppercase tracking-[0.16em] text-slate-500">Max Fee</label>
                        <input id="serviceFilterMaxFee" type="number" min="0" step="0.01" class="ts-filter-input h-12 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm text-slate-700 outline-none transition" placeholder="No limit">
                    </div>

                    <div class="lg:col-span-2">
                        <label for="serviceFilterMinDays" class="mb-2 block text-xs font-bold uppercase tracking-[0.16em] text-slate-500">Min Days</label>
                        <input id="serviceFilterMinDays" type="number" min="0" step="1" class="ts-filter-input h-12 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm text-slate-700 outline-none transition" placeholder="0">
                    </div>

                    <div class="lg:col-span-2">
                        <label for="serviceFilterMaxDays" class="mb-2 block text-xs font-bold uppercase tracking-[0.16em] text-slate-500">Max Days</label>
                        <input id="serviceFilterMaxDays" type="number" min="0" step="1" class="ts-filter-input h-12 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm text-slate-700 outline-none transition" placeholder="No limit">
                    </div>

                    <div class="flex items-end lg:col-span-2">
                        <button
                            id="resetServiceFilters"
                            type="button"
                            class="inline-flex h-12 w-full items-center justify-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                        >
                            Reset Filters
                        </button>
                    </div>

                    <div class="flex items-end lg:col-span-2">
                        <div class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                            <span class="font-bold text-slate-900" id="servicesFilterCountText"><?= e((string) count($services)) ?></span>
                            <span>of <?= e((string) count($services)) ?> shown</span>
                        </div>
                    </div>
                </div>

                <div id="activeServiceFilterChips" class="mt-4 hidden flex-wrap gap-2"></div>
            </div>

            <div id="serviceRecordsList" class="ts-premium-scroll divide-y divide-slate-100 overflow-x-auto">
                <?php foreach ($services as $service): ?>
                    <?php
                        $iconValue = trim((string) ($service['icon'] ?? ''));
                        $isIconImage = $iconValue !== '' && (
                            str_starts_with($iconValue, '/uploads/service-icons/') ||
                            (bool) preg_match('/\.(svg|png|jpg|jpeg|webp|gif)$/i', $iconValue)
                        );

                        $categoryTitle = trim((string) ($service['service_category_title'] ?? 'Uncategorized'));
                        $categoryImage = trim((string) ($service['service_category_image'] ?? ''));
                        $categoryImageUrl = $resolveAssetUrl($categoryImage);
                    ?>
                    <div
                        class="service-record-item py-5 transition hover:bg-slate-50/70"
                        data-id="<?= e((string) ($service['id'] ?? 0)) ?>"
                        data-title="<?= e($normalizeForFilter((string) ($service['title'] ?? ''))) ?>"
                        data-category-id="<?= e((string) ($service['service_category_id'] ?? $service['category_id'] ?? '')) ?>"
                        data-category-title="<?= e($normalizeForFilter($categoryTitle)) ?>"
                        data-status="<?= e((string) ((int) ($service['is_active'] ?? 0))) ?>"
                        data-featured="<?= e((string) ((int) ($service['is_featured'] ?? 0))) ?>"
                        data-fee="<?= e((string) ((float) ($service['filing_fee'] ?? 0))) ?>"
                        data-turnaround="<?= e((string) ((int) ($service['turnaround_days'] ?? 0))) ?>"
                        data-sort="<?= e((string) ((int) ($service['sort_order'] ?? 0))) ?>"
                        data-search="<?= e($normalizeForFilter(implode(' ', [
                            (string) ($service['title'] ?? ''),
                            (string) ($service['slug'] ?? ''),
                            (string) ($service['excerpt'] ?? ''),
                            $categoryTitle,
                            ((int) ($service['is_active'] ?? 0) === 1 ? 'active' : 'inactive'),
                            ((int) ($service['is_featured'] ?? 0) === 1 ? 'featured' : 'not featured'),
                        ]))) ?>"
                    >
                        <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <?php if ($isIconImage): ?>
                                        <span class="inline-flex h-11 w-11 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                                            <img
                                                src="<?= e(base_url(ltrim($iconValue, '/'))) ?>"
                                                alt="<?= e((string) $service['title']) ?>"
                                                class="h-full w-full object-cover"
                                            >
                                        </span>
                                    <?php elseif ($iconValue !== ''): ?>
                                        <span class="inline-flex h-9 min-w-[2.25rem] items-center justify-center rounded-2xl bg-slate-900 px-3 text-xs font-bold uppercase tracking-[0.12em] text-white">
                                            <?= e($iconValue) ?>
                                        </span>
                                    <?php endif; ?>

                                    <h3 class="text-base font-bold tracking-[-0.02em] text-slate-900">
                                        <?= e((string) $service['title']) ?>
                                    </h3>

                                    <?php if ($categoryImageUrl !== ''): ?>
                                        <span class="inline-flex items-center gap-2 rounded-full border border-sky-200 bg-sky-50 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.14em] text-sky-700">
                                            <span class="inline-flex h-5 w-5 overflow-hidden rounded-full bg-white ring-1 ring-sky-100">
                                                <img src="<?= e($categoryImageUrl) ?>" alt="<?= e($categoryTitle) ?>" class="h-full w-full object-cover">
                                            </span>
                                            <?= e($categoryTitle) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="rounded-full border border-sky-200 bg-sky-50 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.14em] text-sky-700">
                                            <?= e($categoryTitle) ?>
                                        </span>
                                    <?php endif; ?>

                                    <?php if ((int) $service['is_featured'] === 1): ?>
                                        <span class="rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.14em] text-amber-700">
                                            Featured
                                        </span>
                                    <?php endif; ?>

                                    <span class="<?= (int) $service['is_active'] === 1 ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-slate-100 text-slate-500' ?> rounded-full border px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.14em]">
                                        <?= (int) $service['is_active'] === 1 ? 'Active' : 'Inactive' ?>
                                    </span>
                                </div>

                                <p class="mt-2 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">
                                    <?= e((string) $service['slug']) ?>
                                </p>

                                <?php if (!empty($service['excerpt'])): ?>
                                    <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-600">
                                        <?= e((string) $service['excerpt']) ?>
                                    </p>
                                <?php else: ?>
                                    <p class="mt-3 text-sm italic text-slate-400">
                                        No excerpt added yet.
                                    </p>
                                <?php endif; ?>

                                <div class="mt-4 flex flex-wrap gap-2">
                                    <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-600">
                                        Fee: ₹<?= e(number_format((float) $service['filing_fee'], 2)) ?>
                                    </span>
                                    <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-600">
                                        Turnaround: <?= e((string) $service['turnaround_days']) ?> days
                                    </span>
                                    <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-600">
                                        Sort: <?= e((string) $service['sort_order']) ?>
                                    </span>
                                </div>
                            </div>

                            <div class="flex shrink-0 flex-wrap items-center gap-2">
                                <a
                                    href="<?= e(base_url('admin/services/manage?id=' . (int) $service['id'])) ?>"
                                    class="inline-flex h-11 items-center justify-center rounded-2xl bg-[#075B9A] px-5 text-sm font-semibold text-white transition hover:bg-[#053B73]"
                                >
                                    Manage
                                </a>

                                <form method="post" action="<?= e(base_url('admin/services/delete-service')) ?>" onsubmit="return confirm('Delete this service and all related content?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= e((string) $service['id']) ?>">
                                    <button
                                        class="inline-flex h-11 items-center justify-center rounded-2xl bg-rose-600 px-5 text-sm font-semibold text-white transition hover:bg-rose-700"
                                        type="submit"
                                    >
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div id="serviceFilterEmptyState" class="hidden py-16 text-center">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-slate-50 text-2xl text-slate-500">
                    ⌕
                </div>
                <h3 class="mt-4 text-lg font-bold tracking-[-0.02em] text-slate-900 ts-title">No matching services found</h3>
                <p class="mt-2 text-sm text-slate-500">Try changing the keyword, category, status, fee, or turnaround filters.</p>
                <button
                    id="resetServiceFiltersEmpty"
                    type="button"
                    class="mt-5 inline-flex h-11 items-center justify-center rounded-2xl border border-slate-200 bg-white px-5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                >
                    Clear Filters
                </button>
            </div>
        <?php else: ?>
            <div class="py-16 text-center">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-sky-50 text-2xl text-sky-600">
                    +
                </div>
                <h3 class="mt-4 text-lg font-bold tracking-[-0.02em] text-slate-900 ts-title">No services added yet</h3>
                <p class="mt-2 text-sm text-slate-500">Click the create button above to add your first service.</p>
            </div>
        <?php endif; ?>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const panel = document.getElementById('createServicePanel');
    const toggleButton = document.getElementById('toggleCreateService');
    const closeButton = document.getElementById('closeCreateService');

    if (panel && toggleButton) {
        function openPanel() {
            panel.classList.remove('hidden');
            toggleButton.setAttribute('aria-expanded', 'true');
            toggleButton.textContent = '− Close Create Form';
            panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        function closePanel() {
            panel.classList.add('hidden');
            toggleButton.setAttribute('aria-expanded', 'false');
            toggleButton.textContent = '+ Create New Service';
        }

        toggleButton.addEventListener('click', function () {
            if (panel.classList.contains('hidden')) {
                openPanel();
            } else {
                closePanel();
            }
        });

        if (closeButton) {
            closeButton.addEventListener('click', function () {
                closePanel();
            });
        }
    }

    const titleInput = document.getElementById('service_title');
    const slugInput = document.getElementById('service_slug');
    let slugTouched = false;

    function slugify(value) {
        return String(value || '')
            .toLowerCase()
            .trim()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
    }

    if (titleInput && slugInput) {
        titleInput.addEventListener('input', function () {
            if (!slugTouched || slugInput.value.trim() === '') {
                slugInput.value = slugify(titleInput.value);
            }
        });

        slugInput.addEventListener('input', function () {
            const current = slugInput.value.trim();
            slugTouched = current !== '';
            slugInput.value = slugify(current);
        });
    }

    const iconInput = document.getElementById('icon_file');
    const selectedIconInput = document.getElementById('selected_icon');
    const previewWrapper = document.getElementById('iconPreviewWrapper');
    const previewImage = document.getElementById('iconPreview');
    const fileName = document.getElementById('iconFileName');
    const fileMeta = document.getElementById('iconFileMeta');

    const toggleIconGallery = document.getElementById('toggleIconGallery');
    const closeIconGallery = document.getElementById('closeIconGallery');
    const iconGalleryPanel = document.getElementById('iconGalleryPanel');
    const galleryItems = document.querySelectorAll('.icon-gallery-item');

    function showPreview(src, name, meta) {
        if (!previewWrapper || !previewImage || !fileName || !fileMeta) {
            return;
        }

        previewWrapper.classList.remove('hidden');
        previewImage.src = src;
        fileName.textContent = name;
        fileMeta.textContent = meta;
    }

    function clearGallerySelection() {
        galleryItems.forEach(function (item) {
            item.classList.remove('ring-2', 'ring-sky-500', 'border-sky-500', 'bg-sky-50');
        });
    }

    function selectGalleryItem(item) {
        clearGallerySelection();
        item.classList.add('ring-2', 'ring-sky-500', 'border-sky-500', 'bg-sky-50');
    }

    if (toggleIconGallery && iconGalleryPanel) {
        toggleIconGallery.addEventListener('click', function () {
            iconGalleryPanel.classList.toggle('hidden');
        });
    }

    if (closeIconGallery && iconGalleryPanel) {
        closeIconGallery.addEventListener('click', function () {
            iconGalleryPanel.classList.add('hidden');
        });
    }

    galleryItems.forEach(function (item) {
        item.addEventListener('click', function () {
            const path = item.getAttribute('data-path') || '';
            const url = item.getAttribute('data-url') || '';
            const name = item.getAttribute('data-name') || 'Selected image';

            if (selectedIconInput) {
                selectedIconInput.value = path;
            }

            if (iconInput) {
                iconInput.value = '';
            }

            selectGalleryItem(item);
            showPreview(url, name, 'Selected from uploaded gallery');
        });
    });

    if (iconInput) {
        iconInput.addEventListener('change', function (event) {
            const file = event.target.files && event.target.files[0];

            if (!file) {
                return;
            }

            if (selectedIconInput) {
                selectedIconInput.value = '';
            }

            clearGallerySelection();

            const objectUrl = URL.createObjectURL(file);
            showPreview(objectUrl, file.name, 'Selected from device gallery / files');
        });
    }

    const serviceFilterSearch = document.getElementById('serviceFilterSearch');
    const serviceFilterCategory = document.getElementById('serviceFilterCategory');
    const serviceFilterStatus = document.getElementById('serviceFilterStatus');
    const serviceFilterFeatured = document.getElementById('serviceFilterFeatured');
    const serviceFilterMinFee = document.getElementById('serviceFilterMinFee');
    const serviceFilterMaxFee = document.getElementById('serviceFilterMaxFee');
    const serviceFilterMinDays = document.getElementById('serviceFilterMinDays');
    const serviceFilterMaxDays = document.getElementById('serviceFilterMaxDays');
    const serviceFilterSort = document.getElementById('serviceFilterSort');
    const resetServiceFilters = document.getElementById('resetServiceFilters');
    const resetServiceFiltersEmpty = document.getElementById('resetServiceFiltersEmpty');
    const serviceRecordsList = document.getElementById('serviceRecordsList');
    const serviceRecords = Array.prototype.slice.call(document.querySelectorAll('.service-record-item'));
    const serviceFilterEmptyState = document.getElementById('serviceFilterEmptyState');
    const servicesFilterCountText = document.getElementById('servicesFilterCountText');
    const servicesFilterCountBadge = document.getElementById('servicesFilterCountBadge');
    const activeServiceFilterChips = document.getElementById('activeServiceFilterChips');

    function normalizeFilterText(value) {
        return String(value || '').toLowerCase().trim();
    }

    function parseFilterNumber(value, fallback) {
        const normalized = String(value || '').trim();

        if (normalized === '') {
            return fallback;
        }

        const parsed = Number(normalized);
        return Number.isFinite(parsed) ? parsed : fallback;
    }

    function getSelectedText(selectElement) {
        if (!selectElement || !selectElement.value) {
            return '';
        }

        const selected = selectElement.options[selectElement.selectedIndex];
        return selected ? selected.textContent.trim() : '';
    }

    function buildServiceFilterState() {
        return {
            search: normalizeFilterText(serviceFilterSearch ? serviceFilterSearch.value : ''),
            category: String(serviceFilterCategory ? serviceFilterCategory.value : '').trim(),
            categoryLabel: getSelectedText(serviceFilterCategory),
            status: String(serviceFilterStatus ? serviceFilterStatus.value : '').trim(),
            statusLabel: getSelectedText(serviceFilterStatus),
            featured: String(serviceFilterFeatured ? serviceFilterFeatured.value : '').trim(),
            featuredLabel: getSelectedText(serviceFilterFeatured),
            minFee: parseFilterNumber(serviceFilterMinFee ? serviceFilterMinFee.value : '', null),
            maxFee: parseFilterNumber(serviceFilterMaxFee ? serviceFilterMaxFee.value : '', null),
            minDays: parseFilterNumber(serviceFilterMinDays ? serviceFilterMinDays.value : '', null),
            maxDays: parseFilterNumber(serviceFilterMaxDays ? serviceFilterMaxDays.value : '', null),
            sort: String(serviceFilterSort ? serviceFilterSort.value : 'sort_asc').trim() || 'sort_asc'
        };
    }

    function recordMatchesServiceFilters(record, state) {
        const haystack = normalizeFilterText(record.getAttribute('data-search'));
        const categoryId = String(record.getAttribute('data-category-id') || '').trim();
        const categoryTitle = normalizeFilterText(record.getAttribute('data-category-title'));
        const status = String(record.getAttribute('data-status') || '').trim();
        const featured = String(record.getAttribute('data-featured') || '').trim();
        const fee = parseFilterNumber(record.getAttribute('data-fee'), 0);
        const turnaround = parseFilterNumber(record.getAttribute('data-turnaround'), 0);

        if (state.search && haystack.indexOf(state.search) === -1) {
            return false;
        }

        if (state.category && categoryId !== state.category && categoryTitle !== normalizeFilterText(state.categoryLabel)) {
            return false;
        }

        if (state.status !== '' && status !== state.status) {
            return false;
        }

        if (state.featured !== '' && featured !== state.featured) {
            return false;
        }

        if (state.minFee !== null && fee < state.minFee) {
            return false;
        }

        if (state.maxFee !== null && fee > state.maxFee) {
            return false;
        }

        if (state.minDays !== null && turnaround < state.minDays) {
            return false;
        }

        if (state.maxDays !== null && turnaround > state.maxDays) {
            return false;
        }

        return true;
    }

    function getRecordSortValue(record, key) {
        if (key === 'title') {
            return normalizeFilterText(record.getAttribute('data-title'));
        }

        if (key === 'fee') {
            return parseFilterNumber(record.getAttribute('data-fee'), 0);
        }

        if (key === 'turnaround') {
            return parseFilterNumber(record.getAttribute('data-turnaround'), 0);
        }

        if (key === 'id') {
            return parseFilterNumber(record.getAttribute('data-id'), 0);
        }

        return parseFilterNumber(record.getAttribute('data-sort'), 0);
    }

    function sortServiceRecords(records, sortMode) {
        const parts = String(sortMode || 'sort_asc').split('_');
        const direction = parts.pop() === 'desc' ? 'desc' : 'asc';
        const key = parts.join('_') || 'sort';

        return records.slice().sort(function (a, b) {
            const left = getRecordSortValue(a, key);
            const right = getRecordSortValue(b, key);

            if (typeof left === 'string' || typeof right === 'string') {
                const compared = String(left).localeCompare(String(right));
                return direction === 'desc' ? compared * -1 : compared;
            }

            if (left === right) {
                return parseFilterNumber(a.getAttribute('data-id'), 0) - parseFilterNumber(b.getAttribute('data-id'), 0);
            }

            return direction === 'desc' ? right - left : left - right;
        });
    }

    function renderActiveFilterChips(state) {
        if (!activeServiceFilterChips) {
            return;
        }

        const chips = [];

        if (state.search) {
            chips.push('Keyword: ' + state.search);
        }

        if (state.category) {
            chips.push('Category: ' + state.categoryLabel);
        }

        if (state.status !== '') {
            chips.push('Status: ' + state.statusLabel);
        }

        if (state.featured !== '') {
            chips.push('Featured: ' + state.featuredLabel);
        }

        if (state.minFee !== null) {
            chips.push('Min Fee: ₹' + state.minFee);
        }

        if (state.maxFee !== null) {
            chips.push('Max Fee: ₹' + state.maxFee);
        }

        if (state.minDays !== null) {
            chips.push('Min Days: ' + state.minDays);
        }

        if (state.maxDays !== null) {
            chips.push('Max Days: ' + state.maxDays);
        }

        activeServiceFilterChips.innerHTML = chips.map(function (chip) {
            const safeChip = chip.replace(/[&<>'"]/g, function (character) {
                return {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    "'": '&#039;',
                    '"': '&quot;'
                }[character];
            });

            return '<span class="ts-filter-chip">' + safeChip + '</span>';
        }).join('');

        activeServiceFilterChips.classList.toggle('hidden', chips.length === 0);
        activeServiceFilterChips.classList.toggle('flex', chips.length > 0);
    }

    function updateServiceFilterCounts(visibleCount) {
        const total = serviceRecords.length;
        const label = visibleCount === 1 ? 'Record' : 'Records';

        if (servicesFilterCountText) {
            servicesFilterCountText.textContent = String(visibleCount);
        }

        if (servicesFilterCountBadge) {
            servicesFilterCountBadge.textContent = visibleCount + ' / ' + total + ' ' + label;
        }
    }

    function applyServiceFilters() {
        if (!serviceRecordsList || serviceRecords.length === 0) {
            return;
        }

        const state = buildServiceFilterState();
        const sortedRecords = sortServiceRecords(serviceRecords, state.sort);
        let visibleCount = 0;

        sortedRecords.forEach(function (record) {
            const matches = recordMatchesServiceFilters(record, state);
            record.classList.toggle('is-hidden', !matches);

            if (matches) {
                visibleCount += 1;
            }

            serviceRecordsList.appendChild(record);
        });

        updateServiceFilterCounts(visibleCount);
        renderActiveFilterChips(state);

        if (serviceFilterEmptyState) {
            serviceFilterEmptyState.classList.toggle('hidden', visibleCount !== 0);
        }
    }

    function resetAllServiceFilters() {
        [
            serviceFilterSearch,
            serviceFilterCategory,
            serviceFilterStatus,
            serviceFilterFeatured,
            serviceFilterMinFee,
            serviceFilterMaxFee,
            serviceFilterMinDays,
            serviceFilterMaxDays
        ].forEach(function (field) {
            if (field) {
                field.value = '';
            }
        });

        if (serviceFilterSort) {
            serviceFilterSort.value = 'sort_asc';
        }

        applyServiceFilters();
    }

    [
        serviceFilterSearch,
        serviceFilterCategory,
        serviceFilterStatus,
        serviceFilterFeatured,
        serviceFilterMinFee,
        serviceFilterMaxFee,
        serviceFilterMinDays,
        serviceFilterMaxDays,
        serviceFilterSort
    ].forEach(function (field) {
        if (!field) {
            return;
        }

        field.addEventListener(field.tagName === 'INPUT' ? 'input' : 'change', applyServiceFilters);
    });

    if (resetServiceFilters) {
        resetServiceFilters.addEventListener('click', resetAllServiceFilters);
    }

    if (resetServiceFiltersEmpty) {
        resetServiceFiltersEmpty.addEventListener('click', resetAllServiceFilters);
    }

    applyServiceFilters();

});
</script>

<script>
/*
 * ABSOLUTE CLIENT-SIDE DUPLICATE GUARD
 * Keep this block after the main services script. It hides duplicate service
 * rows by visible title even if old cached controller/view code or duplicate
 * DB rows still send repeated records to the browser.
 */
(function () {
    function normalizeHardDuplicateKey(value) {
        return String(value || '')
            .replace(/\u00a0/g, ' ')
            .toLowerCase()
            .replace(/\s+/g, ' ')
            .trim();
    }

    function getHardDuplicateServiceKey(row) {
        if (!row) {
            return '';
        }

        const titleAttr = row.getAttribute('data-title') || '';
        const titleNode = row.querySelector('h3');
        const titleText = titleAttr || (titleNode ? titleNode.textContent : '');

        if (normalizeHardDuplicateKey(titleText) !== '') {
            return 'title:' + normalizeHardDuplicateKey(titleText);
        }

        const searchAttr = row.getAttribute('data-search') || '';
        return normalizeHardDuplicateKey(searchAttr) !== ''
            ? 'search:' + normalizeHardDuplicateKey(searchAttr)
            : '';
    }

    function updateHardDuplicateCounts() {
        const validRows = Array.prototype.slice.call(
            document.querySelectorAll('#serviceRecordsList .service-record-item:not(.is-hard-duplicate)')
        );

        const visibleCount = validRows.filter(function (row) {
            return !row.classList.contains('is-hidden');
        }).length;

        const total = validRows.length;
        const countText = document.getElementById('servicesFilterCountText');
        const countBadge = document.getElementById('servicesFilterCountBadge');
        const emptyState = document.getElementById('serviceFilterEmptyState');

        if (countText) {
            countText.textContent = String(visibleCount);
        }

        if (countBadge) {
            countBadge.textContent = visibleCount + ' / ' + total + ' ' + (visibleCount === 1 ? 'Record' : 'Records');
            countBadge.setAttribute('data-total-records', String(total));
        }

        if (emptyState) {
            emptyState.classList.toggle('hidden', visibleCount !== 0);
        }
    }

    function hardDeduplicateRenderedServices() {
        const rows = Array.prototype.slice.call(document.querySelectorAll('#serviceRecordsList .service-record-item'));
        const seen = new Set();

        rows.forEach(function (row) {
            const key = getHardDuplicateServiceKey(row);

            if (key !== '' && seen.has(key)) {
                row.classList.add('is-hard-duplicate');
                row.setAttribute('aria-hidden', 'true');
                return;
            }

            if (key !== '') {
                seen.add(key);
            }

            row.classList.remove('is-hard-duplicate');
            row.removeAttribute('aria-hidden');
        });

        updateHardDuplicateCounts();
    }

    function scheduleHardDeduplicate() {
        window.requestAnimationFrame(function () {
            hardDeduplicateRenderedServices();
        });
    }

    function bootHardDuplicateGuard() {
        const style = document.createElement('style');
        style.textContent = '.service-record-item.is-hard-duplicate{display:none!important;}';
        document.head.appendChild(style);

        scheduleHardDeduplicate();

        [
            'serviceFilterSearch',
            'serviceFilterCategory',
            'serviceFilterStatus',
            'serviceFilterFeatured',
            'serviceFilterMinFee',
            'serviceFilterMaxFee',
            'serviceFilterMinDays',
            'serviceFilterMaxDays',
            'serviceFilterSort',
            'resetServiceFilters',
            'resetServiceFiltersEmpty'
        ].forEach(function (id) {
            const field = document.getElementById(id);
            if (!field) {
                return;
            }

            field.addEventListener('input', scheduleHardDeduplicate);
            field.addEventListener('change', scheduleHardDeduplicate);
            field.addEventListener('click', scheduleHardDeduplicate);
        });

        const list = document.getElementById('serviceRecordsList');
        if (list && 'MutationObserver' in window) {
            const observer = new MutationObserver(scheduleHardDeduplicate);
            observer.observe(list, { childList: true, subtree: false, attributes: true, attributeFilter: ['class'] });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootHardDuplicateGuard);
    } else {
        bootHardDuplicateGuard();
    }
})();
</script>
