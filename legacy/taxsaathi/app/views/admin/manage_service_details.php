<?php
/** @var array $service */
/** @var array $banners */
/** @var array $benefits */
/** @var array $types */
/** @var array $requirements */
/** @var array $reviews */
/** @var string $activeTab */
/** @var array $iconGallery */
/** @var array $categories */

$tabs = [
    'edit-service' => 'Edit Service',
    'banners' => 'Banners',
    'benefits' => 'Benefits',
    'types' => 'Types',
    'requirements' => 'Requirements',
    'reviews' => 'Reviews',
];

$activeTab = $activeTab ?? 'edit-service';
$iconGallery = is_array($iconGallery ?? null) ? array_values($iconGallery) : [];
$categories = is_array($categories ?? null) ? array_values($categories) : [];

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

$serviceIconValue = trim((string) ($service['icon'] ?? ''));
$serviceIconIsImage = $serviceIconValue !== '' && (
    str_starts_with($serviceIconValue, '/uploads/service-icons/') ||
    (bool) preg_match('/\.(svg|png|jpg|jpeg|webp|gif)$/i', $serviceIconValue)
);

$serviceCategoryId = (int) ($service['service_category_id'] ?? 0);
$serviceCategoryTitle = trim((string) ($service['service_category_title'] ?? ''));
$serviceCategoryImage = trim((string) ($service['service_category_image'] ?? ''));
$serviceCategoryImageUrl = $resolveAssetUrl($serviceCategoryImage);
?>

<div class="space-y-5">
    <section class="overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-[0_18px_50px_rgba(15,23,42,0.07)]">
        <div class="flex flex-col gap-5 border-b border-slate-100 bg-gradient-to-r from-slate-950 via-slate-900 to-slate-800 px-5 py-5 md:flex-row md:items-center md:justify-between md:px-6">
            <div class="min-w-0">
                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-300">Service Management</p>

                <div class="mt-2 flex flex-wrap items-center gap-3">
                    <?php if ($serviceIconIsImage): ?>
                        <span class="inline-flex h-12 w-12 items-center justify-center overflow-hidden rounded-2xl border border-white/10 bg-white/10 shadow-sm">
                            <img
                                src="<?= e(base_url(ltrim($serviceIconValue, '/'))) ?>"
                                alt="<?= e((string) $service['title']) ?>"
                                class="h-full w-full object-cover"
                            >
                        </span>
                    <?php endif; ?>

                    <div class="min-w-0">
                        <h2 class="truncate text-[1.35rem] font-black tracking-[-0.04em] text-white">
                            <?= e((string) $service['title']) ?>
                        </h2>
                        <div class="mt-1 flex flex-wrap items-center gap-2">
                            <p class="text-[11px] uppercase tracking-[0.16em] text-slate-300">
                                <?= e((string) $service['slug']) ?>
                            </p>

                            <?php if ($serviceCategoryTitle !== ''): ?>
                                <span class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-100">
                                    <?php if ($serviceCategoryImageUrl !== ''): ?>
                                        <span class="inline-flex h-4 w-4 overflow-hidden rounded-full bg-white ring-1 ring-white/15">
                                            <img src="<?= e($serviceCategoryImageUrl) ?>" alt="<?= e($serviceCategoryTitle) ?>" class="h-full w-full object-cover">
                                        </span>
                                    <?php endif; ?>
                                    <?= e($serviceCategoryTitle) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <a
                href="<?= e(base_url('admin/services')) ?>"
                class="inline-flex h-11 items-center justify-center rounded-2xl border border-white/15 bg-white px-5 text-sm font-semibold text-slate-900 transition hover:bg-slate-100"
            >
                Back to Services
            </a>
        </div>

        <div class="border-b border-slate-100 px-4 py-3 sm:px-6">
            <div class="flex flex-wrap gap-2">
                <?php foreach ($tabs as $key => $label): ?>
                    <button
                        type="button"
                        class="service-tab-btn <?= $activeTab === $key ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-600 border-slate-200' ?> inline-flex items-center justify-center rounded-full border px-4 py-2 text-[11px] font-semibold uppercase tracking-[0.14em] transition"
                        data-tab="<?= e($key) ?>"
                    >
                        <?= e($label) ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="px-4 py-4 sm:px-6 sm:py-5">
            <div id="tab-edit-service" class="service-tab-panel <?= $activeTab === 'edit-service' ? '' : 'hidden' ?>">
                <form method="post" action="<?= e(base_url('admin/services/save-service')) ?>" enctype="multipart/form-data" class="grid gap-5 rounded-[24px] border border-slate-200 bg-slate-50/60 p-4 sm:p-5">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= e((string) $service['id']) ?>">
                    <input type="hidden" name="selected_icon" id="edit_selected_icon" value="">

                    <div class="flex flex-col gap-1">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Edit Service</p>
                        <h3 class="text-lg font-black tracking-[-0.03em] text-slate-900">Service Details</h3>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <label for="edit_service_title" class="mb-2 block text-sm font-semibold text-slate-800">Service Title</label>
                            <input
                                id="edit_service_title"
                                class="h-12 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm text-slate-700 outline-none transition focus:border-slate-400"
                                type="text"
                                name="title"
                                value="<?= e((string) $service['title']) ?>"
                                required
                            >
                        </div>

                        <div>
                            <label for="edit_service_slug" class="mb-2 block text-sm font-semibold text-slate-800">Service Slug</label>
                            <input
                                id="edit_service_slug"
                                class="h-12 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm text-slate-700 outline-none transition focus:border-slate-400"
                                type="text"
                                name="slug"
                                value="<?= e((string) $service['slug']) ?>"
                                required
                            >
                            <p class="mt-2 text-xs text-slate-500">Slug auto-generates from title until you edit it manually.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <label for="edit_service_category_id" class="mb-2 block text-sm font-semibold text-slate-800">Service Category</label>
                            <select
                                id="edit_service_category_id"
                                name="service_category_id"
                                class="h-12 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm text-slate-700 outline-none transition focus:border-slate-400"
                                required
                            >
                                <option value="">Select category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option
                                        value="<?= e((string) $category['id']) ?>"
                                        <?= (int) ($category['id'] ?? 0) === $serviceCategoryId ? 'selected' : '' ?>
                                    >
                                        <?= e((string) $category['title']) ?><?= (int) ($category['is_active'] ?? 1) === 0 ? ' (Inactive)' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label for="edit_service_excerpt" class="mb-2 block text-sm font-semibold text-slate-800">Short Excerpt</label>
                            <input
                                id="edit_service_excerpt"
                                class="h-12 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm text-slate-700 outline-none transition focus:border-slate-400"
                                type="text"
                                name="excerpt"
                                value="<?= e((string) $service['excerpt']) ?>"
                                placeholder="Short summary for service cards"
                            >
                        </div>
                    </div>

                    <div>
                        <label for="edit_service_description" class="mb-2 block text-sm font-semibold text-slate-800">Description</label>
                        <textarea
                            id="edit_service_description"
                            class="min-h-[130px] w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-slate-400"
                            name="description"
                            rows="5"
                            placeholder="Write full service description"
                        ><?= e((string) $service['description']) ?></textarea>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <div>
                            <label for="edit_filing_fee" class="mb-2 block text-sm font-semibold text-slate-800">Filing Fee</label>
                            <input
                                id="edit_filing_fee"
                                class="h-12 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm text-slate-700 outline-none transition focus:border-slate-400"
                                type="number"
                                step="0.01"
                                name="filing_fee"
                                value="<?= e((string) $service['filing_fee']) ?>"
                            >
                        </div>

                        <div>
                            <label for="edit_turnaround_days" class="mb-2 block text-sm font-semibold text-slate-800">Turnaround Days</label>
                            <input
                                id="edit_turnaround_days"
                                class="h-12 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm text-slate-700 outline-none transition focus:border-slate-400"
                                type="number"
                                name="turnaround_days"
                                value="<?= e((string) $service['turnaround_days']) ?>"
                            >
                        </div>

                        <div>
                            <label for="edit_sort_order" class="mb-2 block text-sm font-semibold text-slate-800">Sort Order</label>
                            <input
                                id="edit_sort_order"
                                class="h-12 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm text-slate-700 outline-none transition focus:border-slate-400"
                                type="number"
                                name="sort_order"
                                value="<?= e((string) $service['sort_order']) ?>"
                            >
                        </div>

                        <div class="grid grid-cols-1 gap-3">
                            <label class="flex h-12 items-center gap-2.5 rounded-2xl border border-slate-200 bg-white px-4 text-sm text-slate-700">
                                <input type="checkbox" name="is_featured" value="1" <?= (int) $service['is_featured'] === 1 ? 'checked' : '' ?> class="h-4 w-4 rounded border-slate-300">
                                <span>Featured Service</span>
                            </label>

                            <label class="flex h-12 items-center gap-2.5 rounded-2xl border border-slate-200 bg-white px-4 text-sm text-slate-700">
                                <input type="checkbox" name="is_active" value="1" <?= (int) $service['is_active'] === 1 ? 'checked' : '' ?> class="h-4 w-4 rounded border-slate-300">
                                <span>Active Service</span>
                            </label>
                        </div>
                    </div>

                    <div class="rounded-[22px] border border-slate-200 bg-white p-4">
                        <div class="flex flex-col gap-1">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Service Icon</p>
                            <h4 class="text-sm font-bold text-slate-900">Upload or Choose from Gallery</h4>
                        </div>

                        <div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-[0.9fr_1.1fr]">
                            <div class="space-y-3">
                                <label
                                    for="edit_icon_file"
                                    class="group flex min-h-[124px] w-full cursor-pointer flex-col items-center justify-center rounded-[24px] border border-dashed border-slate-300 bg-slate-50 px-4 py-5 text-center transition hover:border-slate-400 hover:bg-white"
                                >
                                    <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-900 text-white shadow-[0_12px_30px_rgba(15,23,42,0.16)] transition group-hover:-translate-y-0.5">
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
                                    id="edit_icon_file"
                                    type="file"
                                    name="icon_file"
                                    accept="image/*,.svg"
                                    class="hidden"
                                >

                                <div class="flex flex-wrap items-center gap-3">
                                    <button
                                        type="button"
                                        id="toggleEditIconGallery"
                                        class="inline-flex h-11 items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                                    >
                                        Choose from Uploaded Gallery
                                    </button>

                                    <span class="inline-flex rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-600">
                                        <?= e((string) count($iconGallery)) ?> Uploaded Images
                                    </span>
                                </div>
                            </div>

                            <div>
                                <div id="editIconPreviewWrapper" class="<?= $serviceIconIsImage ? '' : 'hidden' ?>">
                                    <p class="mb-2 text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Selected Icon</p>

                                    <div class="inline-flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-3 py-3 shadow-sm">
                                        <img
                                            id="editIconPreview"
                                            src="<?= $serviceIconIsImage ? e(base_url(ltrim($serviceIconValue, '/'))) : '' ?>"
                                            alt="Icon Preview"
                                            class="h-16 w-16 rounded-2xl object-cover ring-1 ring-slate-200"
                                        >
                                        <div>
                                            <p id="editIconFileName" class="max-w-[240px] truncate text-sm font-medium text-slate-700">
                                                <?= $serviceIconIsImage ? e((string) basename($serviceIconValue)) : '' ?>
                                            </p>
                                            <p id="editIconFileMeta" class="text-xs text-slate-500">
                                                <?= $serviceIconIsImage ? 'Current service icon' : 'Selected icon file' ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <?php if (!$serviceIconIsImage && $serviceIconValue !== ''): ?>
                                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Current Icon Value</p>
                                        <p class="mt-2 text-sm text-slate-700"><?= e($serviceIconValue) ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div id="editIconGalleryPanel" class="mt-4 hidden overflow-hidden rounded-[24px] border border-slate-200 bg-slate-50">
                            <div class="border-b border-slate-200 bg-white px-4 py-3">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Uploaded Image Gallery</p>
                                        <h3 class="mt-0.5 text-sm font-bold text-slate-900">Choose Existing Icon</h3>
                                    </div>

                                    <button
                                        type="button"
                                        id="closeEditIconGallery"
                                        class="inline-flex h-9 items-center justify-center rounded-xl border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-600 transition hover:bg-slate-50"
                                    >
                                        Close
                                    </button>
                                </div>
                            </div>

                            <?php if (!empty($iconGallery)): ?>
                                <div class="grid grid-cols-2 gap-3 p-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                                    <?php foreach ($iconGallery as $galleryIcon): ?>
                                        <button
                                            type="button"
                                            class="edit-icon-gallery-item group overflow-hidden rounded-[22px] border border-slate-200 bg-white p-2 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-slate-300"
                                            data-path="<?= e((string) $galleryIcon) ?>"
                                            data-url="<?= e(base_url(ltrim((string) $galleryIcon, '/'))) ?>"
                                            data-name="<?= e((string) basename((string) $galleryIcon)) ?>"
                                        >
                                            <span class="flex aspect-square w-full items-center justify-center overflow-hidden rounded-[18px] bg-slate-100">
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
                                    <p class="mt-1 text-xs text-slate-500">Upload one from the create page first and it will appear here.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <button class="inline-flex h-12 items-center justify-center rounded-2xl bg-slate-900 px-5 text-sm font-semibold text-white transition hover:bg-slate-800" type="submit">
                        Save Service
                    </button>
                </form>
            </div>

            <div id="tab-banners" class="service-tab-panel <?= $activeTab === 'banners' ? '' : 'hidden' ?>">
                <div class="space-y-4">
                    <form method="post" action="<?= e(base_url('admin/services/save-banner')) ?>" enctype="multipart/form-data" class="grid gap-4 rounded-[18px] border border-slate-200 bg-slate-50/60 p-4">
                        <?= csrf_field() ?>
                        <input type="hidden" name="service_id" value="<?= e((string) $service['id']) ?>">

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-800">Badge</label>
                                <input class="h-11 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm text-slate-700" type="text" name="badge" placeholder="Badge">
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-800">Banner Title</label>
                                <input class="h-11 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm text-slate-700" type="text" name="title" placeholder="Banner title" required>
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-800">Button Text</label>
                                <input class="h-11 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm text-slate-700" type="text" name="button_text" placeholder="Button text">
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-800">Button Link</label>
                                <input class="h-11 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm text-slate-700" type="text" name="button_link" placeholder="Button link">
                            </div>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-800">Subtitle</label>
                            <textarea class="min-h-[100px] w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700" name="subtitle" rows="3" placeholder="Subtitle"></textarea>
                        </div>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-800">Background Type</label>
                                <select class="h-11 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm text-slate-700" name="background_type">
                                    <option value="gradient">Gradient</option>
                                    <option value="image">Image</option>
                                    <option value="solid">Solid</option>
                                </select>
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-800">Background Value</label>
                                <input class="h-11 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm text-slate-700" type="text" name="background_value" placeholder="Background value">
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-800">Text Color</label>
                                <input class="h-11 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm text-slate-700" type="text" name="text_color" value="#ffffff">
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-800">Sort Order</label>
                                <input class="h-11 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm text-slate-700" type="number" name="sort_order" value="0">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <label class="flex items-center gap-2.5 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700">
                                <input type="checkbox" name="is_active" value="1" checked class="h-4 w-4 rounded border-slate-300 text-brand-600">
                                <span>Active Banner</span>
                            </label>

                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-800">Banner Image Upload</label>
                                <label class="grid gap-2 rounded-2xl border border-dashed border-slate-200 bg-white px-4 py-3">
                                    <span class="text-[12px] font-semibold uppercase tracking-[0.14em] text-slate-500">Upload Banner Image</span>
                                    <input type="file" name="image_file" accept="image/*" class="block w-full text-sm text-slate-600">
                                </label>
                            </div>
                        </div>

                        <button class="inline-flex h-11 items-center justify-center rounded-2xl bg-slate-900 px-5 text-sm font-semibold text-white" type="submit">
                            Add Banner
                        </button>
                    </form>

                    <?php foreach ($banners as $item): ?>
                        <div class="rounded-[18px] border border-slate-200 bg-white p-4">
                            <form method="post" action="<?= e(base_url('admin/services/save-banner')) ?>" enctype="multipart/form-data" class="grid gap-4">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= e((string) $item['id']) ?>">
                                <input type="hidden" name="service_id" value="<?= e((string) $service['id']) ?>">
                                <input type="hidden" name="existing_image" value="<?= e((string) $item['image']) ?>">

                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div>
                                        <label class="mb-2 block text-sm font-semibold text-slate-800">Badge</label>
                                        <input class="h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700" type="text" name="badge" value="<?= e((string) $item['badge']) ?>">
                                    </div>

                                    <div>
                                        <label class="mb-2 block text-sm font-semibold text-slate-800">Banner Title</label>
                                        <input class="h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700" type="text" name="title" value="<?= e((string) $item['title']) ?>" required>
                                    </div>

                                    <div>
                                        <label class="mb-2 block text-sm font-semibold text-slate-800">Button Text</label>
                                        <input class="h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700" type="text" name="button_text" value="<?= e((string) $item['button_text']) ?>">
                                    </div>

                                    <div>
                                        <label class="mb-2 block text-sm font-semibold text-slate-800">Button Link</label>
                                        <input class="h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700" type="text" name="button_link" value="<?= e((string) $item['button_link']) ?>">
                                    </div>
                                </div>

                                <div>
                                    <label class="mb-2 block text-sm font-semibold text-slate-800">Subtitle</label>
                                    <textarea class="min-h-[100px] w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700" name="subtitle" rows="3"><?= e((string) $item['subtitle']) ?></textarea>
                                </div>

                                <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                                    <div>
                                        <label class="mb-2 block text-sm font-semibold text-slate-800">Background Type</label>
                                        <select class="h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700" name="background_type">
                                            <option value="gradient" <?= (string) $item['background_type'] === 'gradient' ? 'selected' : '' ?>>Gradient</option>
                                            <option value="image" <?= (string) $item['background_type'] === 'image' ? 'selected' : '' ?>>Image</option>
                                            <option value="solid" <?= (string) $item['background_type'] === 'solid' ? 'selected' : '' ?>>Solid</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="mb-2 block text-sm font-semibold text-slate-800">Background Value</label>
                                        <input class="h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700" type="text" name="background_value" value="<?= e((string) $item['background_value']) ?>">
                                    </div>

                                    <div>
                                        <label class="mb-2 block text-sm font-semibold text-slate-800">Text Color</label>
                                        <input class="h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700" type="text" name="text_color" value="<?= e((string) $item['text_color']) ?>">
                                    </div>

                                    <div>
                                        <label class="mb-2 block text-sm font-semibold text-slate-800">Sort Order</label>
                                        <input class="h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700" type="number" name="sort_order" value="<?= e((string) $item['sort_order']) ?>">
                                    </div>
                                </div>

                                <?php if (!empty($item['image'])): ?>
                                    <div class="rounded-[16px] border border-slate-200 bg-slate-50 p-3">
                                        <p class="mb-2 text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-500">Current Image</p>
                                        <img src="<?= e(base_url(ltrim((string) $item['image'], '/'))) ?>" alt="Banner image" class="max-h-40 rounded-xl border border-slate-200 object-cover">
                                    </div>
                                <?php endif; ?>

                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <label class="flex items-center gap-2.5 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                                        <input type="checkbox" name="is_active" value="1" <?= (int) $item['is_active'] === 1 ? 'checked' : '' ?> class="h-4 w-4 rounded border-slate-300 text-brand-600">
                                        <span>Active Banner</span>
                                    </label>

                                    <div>
                                        <label class="mb-2 block text-sm font-semibold text-slate-800">Replace Banner Image</label>
                                        <label class="grid gap-2 rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-3">
                                            <span class="text-[12px] font-semibold uppercase tracking-[0.14em] text-slate-500">Replace Image</span>
                                            <input type="file" name="image_file" accept="image/*" class="block w-full text-sm text-slate-600">
                                        </label>
                                    </div>
                                </div>

                                <div class="flex flex-wrap items-center gap-2">
                                    <button class="inline-flex h-10 items-center justify-center rounded-xl bg-slate-900 px-4 text-[12px] font-semibold text-white" type="submit">
                                        Save
                                    </button>
                            </form>

                            <form method="post" action="<?= e(base_url('admin/services/delete-banner')) ?>" onsubmit="return confirm('Delete this banner?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= e((string) $item['id']) ?>">
                                <input type="hidden" name="service_id" value="<?= e((string) $service['id']) ?>">
                                <button class="inline-flex h-10 items-center justify-center rounded-xl bg-rose-600 px-4 text-[12px] font-semibold text-white" type="submit">
                                    Delete
                                </button>
                            </form>
                                </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if (empty($banners)): ?>
                        <div class="rounded-[18px] border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                            No banners added yet.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div id="tab-benefits" class="service-tab-panel <?= $activeTab === 'benefits' ? '' : 'hidden' ?>">
                <div class="space-y-4">
                    <form method="post" action="<?= e(base_url('admin/services/save-benefit')) ?>" class="grid gap-4 rounded-[18px] border border-slate-200 bg-slate-50/60 p-4">
                        <?= csrf_field() ?>
                        <input type="hidden" name="service_id" value="<?= e((string) $service['id']) ?>">

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-800">Benefit Icon</label>
                                <input class="h-11 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm text-slate-700" type="text" name="icon" placeholder="Icon">
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-800">Benefit Title</label>
                                <input class="h-11 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm text-slate-700" type="text" name="title" placeholder="Title" required>
                            </div>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-800">Benefit Description</label>
                            <textarea class="min-h-[90px] w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700" name="description" rows="3" placeholder="Description"></textarea>
                        </div>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-800">Sort Order</label>
                                <input class="h-11 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm text-slate-700" type="number" name="sort_order" value="0">
                            </div>

                            <label class="flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700">
                                <input type="checkbox" name="is_active" value="1" checked class="h-4 w-4 rounded border-slate-300 text-brand-600">
                                <span>Active Benefit</span>
                            </label>
                        </div>

                        <button class="inline-flex h-11 items-center justify-center rounded-2xl bg-slate-900 px-5 text-sm font-semibold text-white" type="submit">Add Benefit</button>
                    </form>

                    <?php foreach ($benefits as $item): ?>
                        <div class="rounded-[18px] border border-slate-200 bg-white p-4">
                            <form method="post" action="<?= e(base_url('admin/services/save-benefit')) ?>" class="grid gap-4">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= e((string) $item['id']) ?>">
                                <input type="hidden" name="service_id" value="<?= e((string) $service['id']) ?>">

                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div>
                                        <label class="mb-2 block text-sm font-semibold text-slate-800">Benefit Icon</label>
                                        <input class="h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700" type="text" name="icon" value="<?= e((string) $item['icon']) ?>">
                                    </div>

                                    <div>
                                        <label class="mb-2 block text-sm font-semibold text-slate-800">Benefit Title</label>
                                        <input class="h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700" type="text" name="title" value="<?= e((string) $item['title']) ?>" required>
                                    </div>
                                </div>

                                <div>
                                    <label class="mb-2 block text-sm font-semibold text-slate-800">Benefit Description</label>
                                    <textarea class="min-h-[90px] w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700" name="description" rows="3"><?= e((string) $item['description']) ?></textarea>
                                </div>

                                <div class="flex flex-wrap items-center gap-2">
                                    <div class="w-full md:w-auto">
                                        <label class="mb-2 block text-sm font-semibold text-slate-800">Sort Order</label>
                                        <input class="h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700 md:w-[180px]" type="number" name="sort_order" value="<?= e((string) $item['sort_order']) ?>">
                                    </div>

                                    <label class="mt-7 flex items-center gap-2 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                                        <input type="checkbox" name="is_active" value="1" <?= (int) $item['is_active'] === 1 ? 'checked' : '' ?> class="h-4 w-4 rounded border-slate-300 text-brand-600">
                                        <span>Active Benefit</span>
                                    </label>

                                    <button class="mt-7 inline-flex h-10 items-center justify-center rounded-xl bg-slate-900 px-4 text-[12px] font-semibold text-white" type="submit">Save</button>
                            </form>

                            <form method="post" action="<?= e(base_url('admin/services/delete-benefit')) ?>" onsubmit="return confirm('Delete this benefit?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= e((string) $item['id']) ?>">
                                <input type="hidden" name="service_id" value="<?= e((string) $service['id']) ?>">
                                <button class="mt-7 inline-flex h-10 items-center justify-center rounded-xl bg-rose-600 px-4 text-[12px] font-semibold text-white" type="submit">Delete</button>
                            </form>
                                </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if (empty($benefits)): ?>
                        <div class="rounded-[18px] border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">No benefits added yet.</div>
                    <?php endif; ?>
                </div>
            </div>

            <div id="tab-types" class="service-tab-panel <?= $activeTab === 'types' ? '' : 'hidden' ?>">
                <div class="space-y-4">
                    <form method="post" action="<?= e(base_url('admin/services/save-type')) ?>" class="grid gap-4 rounded-[18px] border border-slate-200 bg-slate-50/60 p-4">
                        <?= csrf_field() ?>
                        <input type="hidden" name="service_id" value="<?= e((string) $service['id']) ?>">

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-800">Type Icon</label>
                                <input class="h-11 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm text-slate-700" type="text" name="icon" placeholder="Icon">
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-800">Type Title</label>
                                <input class="h-11 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm text-slate-700" type="text" name="title" placeholder="Title" required>
                            </div>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-800">Type Description</label>
                            <textarea class="min-h-[90px] w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700" name="description" rows="3" placeholder="Description"></textarea>
                        </div>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-800">Sort Order</label>
                                <input class="h-11 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm text-slate-700" type="number" name="sort_order" value="0">
                            </div>

                            <label class="flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700">
                                <input type="checkbox" name="is_active" value="1" checked class="h-4 w-4 rounded border-slate-300 text-brand-600">
                                <span>Active Type</span>
                            </label>
                        </div>

                        <button class="inline-flex h-11 items-center justify-center rounded-2xl bg-slate-900 px-5 text-sm font-semibold text-white" type="submit">Add Type</button>
                    </form>

                    <?php foreach ($types as $item): ?>
                        <div class="rounded-[18px] border border-slate-200 bg-white p-4">
                            <form method="post" action="<?= e(base_url('admin/services/save-type')) ?>" class="grid gap-4">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= e((string) $item['id']) ?>">
                                <input type="hidden" name="service_id" value="<?= e((string) $service['id']) ?>">

                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div>
                                        <label class="mb-2 block text-sm font-semibold text-slate-800">Type Icon</label>
                                        <input class="h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700" type="text" name="icon" value="<?= e((string) $item['icon']) ?>">
                                    </div>

                                    <div>
                                        <label class="mb-2 block text-sm font-semibold text-slate-800">Type Title</label>
                                        <input class="h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700" type="text" name="title" value="<?= e((string) $item['title']) ?>" required>
                                    </div>
                                </div>

                                <div>
                                    <label class="mb-2 block text-sm font-semibold text-slate-800">Type Description</label>
                                    <textarea class="min-h-[90px] w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700" name="description" rows="3"><?= e((string) $item['description']) ?></textarea>
                                </div>

                                <div class="flex flex-wrap items-center gap-2">
                                    <div class="w-full md:w-auto">
                                        <label class="mb-2 block text-sm font-semibold text-slate-800">Sort Order</label>
                                        <input class="h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700 md:w-[180px]" type="number" name="sort_order" value="<?= e((string) $item['sort_order']) ?>">
                                    </div>

                                    <label class="mt-7 flex items-center gap-2 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                                        <input type="checkbox" name="is_active" value="1" <?= (int) $item['is_active'] === 1 ? 'checked' : '' ?> class="h-4 w-4 rounded border-slate-300 text-brand-600">
                                        <span>Active Type</span>
                                    </label>

                                    <button class="mt-7 inline-flex h-10 items-center justify-center rounded-xl bg-slate-900 px-4 text-[12px] font-semibold text-white" type="submit">Save</button>
                            </form>

                            <form method="post" action="<?= e(base_url('admin/services/delete-type')) ?>" onsubmit="return confirm('Delete this type?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= e((string) $item['id']) ?>">
                                <input type="hidden" name="service_id" value="<?= e((string) $service['id']) ?>">
                                <button class="mt-7 inline-flex h-10 items-center justify-center rounded-xl bg-rose-600 px-4 text-[12px] font-semibold text-white" type="submit">Delete</button>
                            </form>
                                </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if (empty($types)): ?>
                        <div class="rounded-[18px] border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">No types added yet.</div>
                    <?php endif; ?>
                </div>
            </div>

            <div id="tab-requirements" class="service-tab-panel <?= $activeTab === 'requirements' ? '' : 'hidden' ?>">
                <div class="space-y-4">
                    <form method="post" action="<?= e(base_url('admin/services/save-requirement')) ?>" class="grid gap-4 rounded-[18px] border border-slate-200 bg-slate-50/60 p-4">
                        <?= csrf_field() ?>
                        <input type="hidden" name="service_id" value="<?= e((string) $service['id']) ?>">

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-800">Requirement Label</label>
                                <input class="h-11 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm text-slate-700" type="text" name="label" placeholder="Label" required>
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-800">Requirement Icon</label>
                                <input class="h-11 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm text-slate-700" type="text" name="icon" placeholder="Icon">
                            </div>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-800">Help Text</label>
                            <textarea class="min-h-[90px] w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700" name="help_text" rows="3" placeholder="Help text"></textarea>
                        </div>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-800">Sort Order</label>
                                <input class="h-11 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm text-slate-700" type="number" name="sort_order" value="1">
                            </div>

                            <label class="flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700">
                                <input type="checkbox" name="is_required" value="1" checked class="h-4 w-4 rounded border-slate-300 text-brand-600">
                                <span>Required</span>
                            </label>

                            <label class="flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700">
                                <input type="checkbox" name="allow_multiple" value="1" class="h-4 w-4 rounded border-slate-300 text-brand-600">
                                <span>Allow Multiple</span>
                            </label>

                            <label class="flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700">
                                <input type="checkbox" name="is_active" value="1" checked class="h-4 w-4 rounded border-slate-300 text-brand-600">
                                <span>Active Requirement</span>
                            </label>
                        </div>

                        <button class="inline-flex h-11 items-center justify-center rounded-2xl bg-slate-900 px-5 text-sm font-semibold text-white" type="submit">Add Requirement</button>
                    </form>

                    <?php foreach ($requirements as $item): ?>
                        <div class="rounded-[18px] border border-slate-200 bg-white p-4">
                            <form method="post" action="<?= e(base_url('admin/services/save-requirement')) ?>" class="grid gap-4">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= e((string) $item['id']) ?>">
                                <input type="hidden" name="service_id" value="<?= e((string) $service['id']) ?>">

                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div>
                                        <label class="mb-2 block text-sm font-semibold text-slate-800">Requirement Label</label>
                                        <input class="h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700" type="text" name="label" value="<?= e((string) $item['label']) ?>" required>
                                    </div>

                                    <div>
                                        <label class="mb-2 block text-sm font-semibold text-slate-800">Requirement Icon</label>
                                        <input class="h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700" type="text" name="icon" value="<?= e((string) $item['icon']) ?>">
                                    </div>
                                </div>

                                <div>
                                    <label class="mb-2 block text-sm font-semibold text-slate-800">Help Text</label>
                                    <textarea class="min-h-[90px] w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700" name="help_text" rows="3"><?= e((string) $item['help_text']) ?></textarea>
                                </div>

                                <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                                    <div>
                                        <label class="mb-2 block text-sm font-semibold text-slate-800">Sort Order</label>
                                        <input class="h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700" type="number" name="sort_order" value="<?= e((string) $item['sort_order']) ?>">
                                    </div>

                                    <label class="flex items-center gap-2 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                                        <input type="checkbox" name="is_required" value="1" <?= (int) $item['is_required'] === 1 ? 'checked' : '' ?> class="h-4 w-4 rounded border-slate-300 text-brand-600">
                                        <span>Required</span>
                                    </label>

                                    <label class="flex items-center gap-2 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                                        <input type="checkbox" name="allow_multiple" value="1" <?= (int) $item['allow_multiple'] === 1 ? 'checked' : '' ?> class="h-4 w-4 rounded border-slate-300 text-brand-600">
                                        <span>Allow Multiple</span>
                                    </label>

                                    <label class="flex items-center gap-2 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                                        <input type="checkbox" name="is_active" value="1" <?= (int) $item['is_active'] === 1 ? 'checked' : '' ?> class="h-4 w-4 rounded border-slate-300 text-brand-600">
                                        <span>Active Requirement</span>
                                    </label>
                                </div>

                                <div class="flex flex-wrap items-center gap-2">
                                    <button class="inline-flex h-10 items-center justify-center rounded-xl bg-slate-900 px-4 text-[12px] font-semibold text-white" type="submit">Save</button>
                            </form>

                            <form method="post" action="<?= e(base_url('admin/services/delete-requirement')) ?>" onsubmit="return confirm('Delete this requirement?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= e((string) $item['id']) ?>">
                                <input type="hidden" name="service_id" value="<?= e((string) $service['id']) ?>">
                                <button class="inline-flex h-10 items-center justify-center rounded-xl bg-rose-600 px-4 text-[12px] font-semibold text-white" type="submit">Delete</button>
                            </form>
                                </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if (empty($requirements)): ?>
                        <div class="rounded-[18px] border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">No requirements added yet.</div>
                    <?php endif; ?>
                </div>
            </div>

            <div id="tab-reviews" class="service-tab-panel <?= $activeTab === 'reviews' ? '' : 'hidden' ?>">
                <div class="space-y-4">
                    <form method="post" action="<?= e(base_url('admin/services/save-review')) ?>" class="grid gap-4 rounded-[18px] border border-slate-200 bg-slate-50/60 p-4">
                        <?= csrf_field() ?>
                        <input type="hidden" name="service_id" value="<?= e((string) $service['id']) ?>">

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-800">Reviewer Name</label>
                                <input class="h-11 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm text-slate-700" type="text" name="name" placeholder="Name" required>
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-800">Designation</label>
                                <input class="h-11 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm text-slate-700" type="text" name="designation" placeholder="Designation">
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-800">Company</label>
                                <input class="h-11 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm text-slate-700" type="text" name="company" placeholder="Company">
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-800">Rating</label>
                                <input class="h-11 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm text-slate-700" type="number" name="rating" min="1" max="5" value="5">
                            </div>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-800">Review Quote</label>
                            <textarea class="min-h-[100px] w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700" name="quote" rows="3" placeholder="Quote" required></textarea>
                        </div>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-800">Sort Order</label>
                                <input class="h-11 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm text-slate-700" type="number" name="sort_order" value="0">
                            </div>

                            <label class="flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700">
                                <input type="checkbox" name="is_active" value="1" checked class="h-4 w-4 rounded border-slate-300 text-brand-600">
                                <span>Active Review</span>
                            </label>
                        </div>

                        <button class="inline-flex h-11 items-center justify-center rounded-2xl bg-slate-900 px-5 text-sm font-semibold text-white" type="submit">Add Review</button>
                    </form>

                    <?php foreach ($reviews as $item): ?>
                        <div class="rounded-[18px] border border-slate-200 bg-white p-4">
                            <form method="post" action="<?= e(base_url('admin/services/save-review')) ?>" class="grid gap-4">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= e((string) $item['id']) ?>">
                                <input type="hidden" name="service_id" value="<?= e((string) $service['id']) ?>">

                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div>
                                        <label class="mb-2 block text-sm font-semibold text-slate-800">Reviewer Name</label>
                                        <input class="h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700" type="text" name="name" value="<?= e((string) $item['name']) ?>" required>
                                    </div>

                                    <div>
                                        <label class="mb-2 block text-sm font-semibold text-slate-800">Designation</label>
                                        <input class="h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700" type="text" name="designation" value="<?= e((string) $item['designation']) ?>">
                                    </div>

                                    <div>
                                        <label class="mb-2 block text-sm font-semibold text-slate-800">Company</label>
                                        <input class="h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700" type="text" name="company" value="<?= e((string) $item['company']) ?>">
                                    </div>

                                    <div>
                                        <label class="mb-2 block text-sm font-semibold text-slate-800">Rating</label>
                                        <input class="h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700" type="number" name="rating" min="1" max="5" value="<?= e((string) $item['rating']) ?>">
                                    </div>
                                </div>

                                <div>
                                    <label class="mb-2 block text-sm font-semibold text-slate-800">Review Quote</label>
                                    <textarea class="min-h-[100px] w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700" name="quote" rows="3" required><?= e((string) $item['quote']) ?></textarea>
                                </div>

                                <div class="flex flex-wrap items-center gap-2">
                                    <div class="w-full md:w-auto">
                                        <label class="mb-2 block text-sm font-semibold text-slate-800">Sort Order</label>
                                        <input class="h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700 md:w-[180px]" type="number" name="sort_order" value="<?= e((string) $item['sort_order']) ?>">
                                    </div>

                                    <label class="mt-7 flex items-center gap-2 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                                        <input type="checkbox" name="is_active" value="1" <?= (int) $item['is_active'] === 1 ? 'checked' : '' ?> class="h-4 w-4 rounded border-slate-300 text-brand-600">
                                        <span>Active Review</span>
                                    </label>

                                    <button class="mt-7 inline-flex h-10 items-center justify-center rounded-xl bg-slate-900 px-4 text-[12px] font-semibold text-white" type="submit">Save</button>
                            </form>

                            <form method="post" action="<?= e(base_url('admin/services/delete-review')) ?>" onsubmit="return confirm('Delete this review?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= e((string) $item['id']) ?>">
                                <input type="hidden" name="service_id" value="<?= e((string) $service['id']) ?>">
                                <button class="mt-7 inline-flex h-10 items-center justify-center rounded-xl bg-rose-600 px-4 text-[12px] font-semibold text-white" type="submit">Delete</button>
                            </form>
                                </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if (empty($reviews)): ?>
                        <div class="rounded-[18px] border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">No reviews added yet.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const buttons = document.querySelectorAll('.service-tab-btn');
    const panels = document.querySelectorAll('.service-tab-panel');
    const serviceId = <?= json_encode((string) $service['id']) ?>;

    function setActiveTab(tab) {
        buttons.forEach((button) => {
            const active = button.dataset.tab === tab;
            button.classList.toggle('bg-slate-900', active);
            button.classList.toggle('text-white', active);
            button.classList.toggle('border-slate-900', active);
            button.classList.toggle('bg-white', !active);
            button.classList.toggle('text-slate-600', !active);
            button.classList.toggle('border-slate-200', !active);
        });

        panels.forEach((panel) => {
            panel.classList.toggle('hidden', panel.id !== 'tab-' + tab);
        });

        const url = new URL(window.location.href);
        url.searchParams.set('id', serviceId);
        url.searchParams.set('tab', tab);
        window.history.replaceState({}, '', url.toString());
    }

    buttons.forEach((button) => {
        button.addEventListener('click', function () {
            setActiveTab(this.dataset.tab);
        });
    });

    const titleInput = document.getElementById('edit_service_title');
    const slugInput = document.getElementById('edit_service_slug');
    let slugTouched = false;

    function slugify(value) {
        return String(value || '')
            .toLowerCase()
            .trim()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
    }

    if (titleInput && slugInput) {
        const initialSlug = slugify(slugInput.value);
        const initialTitleSlug = slugify(titleInput.value);
        slugTouched = initialSlug !== '' && initialSlug !== initialTitleSlug;

        titleInput.addEventListener('input', function () {
            if (!slugTouched || slugInput.value.trim() === '') {
                slugInput.value = slugify(titleInput.value);
            }
        });

        slugInput.addEventListener('input', function () {
            const current = slugInput.value.trim();
            slugInput.value = slugify(current);
            slugTouched = slugInput.value !== '' && slugInput.value !== slugify(titleInput.value);
        });
    }

    const iconInput = document.getElementById('edit_icon_file');
    const selectedIconInput = document.getElementById('edit_selected_icon');
    const previewWrapper = document.getElementById('editIconPreviewWrapper');
    const previewImage = document.getElementById('editIconPreview');
    const fileName = document.getElementById('editIconFileName');
    const fileMeta = document.getElementById('editIconFileMeta');

    const toggleIconGallery = document.getElementById('toggleEditIconGallery');
    const closeIconGallery = document.getElementById('closeEditIconGallery');
    const iconGalleryPanel = document.getElementById('editIconGalleryPanel');
    const galleryItems = document.querySelectorAll('.edit-icon-gallery-item');

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
            item.classList.remove('ring-2', 'ring-slate-900', 'border-slate-900', 'bg-slate-50');
        });
    }

    function selectGalleryItem(item) {
        clearGallerySelection();
        item.classList.add('ring-2', 'ring-slate-900', 'border-slate-900', 'bg-slate-50');
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
});
</script>