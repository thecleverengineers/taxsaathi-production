<?php
declare(strict_types=1);

$mode = (string) ($mode ?? 'create');
$category = is_array($category ?? null) ? $category : [];
$errors = is_array($errors ?? null) ? $errors : [];

$isEdit = $mode === 'edit';

$h = static fn($value): string => function_exists('e')
    ? e((string) $value)
    : htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$url = static fn(string $path): string => function_exists('base_url')
    ? base_url($path)
    : '/' . ltrim($path, '/');

$asset = static function (?string $path) use ($url): string {
    $path = trim((string) $path);

    if ($path === '') {
        return '';
    }

    if (preg_match('~^(https?:)?//~i', $path) === 1 || str_starts_with($path, 'data:')) {
        return $path;
    }

    return $url(ltrim($path, '/'));
};

$id = (int) ($category['id'] ?? 0);
$title = (string) ($category['title'] ?? '');
$slug = (string) ($category['slug'] ?? '');
$image = (string) ($category['image'] ?? ($category['image_path'] ?? ''));
$imagePreview = $asset($image);
$description = (string) ($category['description'] ?? '');
$sortOrder = (int) ($category['sort_order'] ?? 0);
$isActive = (int) ($category['is_active'] ?? 1) === 1;

$action = $isEdit
    ? $url('admin/service-categories/update?id=' . $id)
    : $url('admin/service-categories/store');
?>

<section class="px-4 py-6 sm:px-6 lg:px-8">
    <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <span class="inline-flex rounded-full border border-brand-100 bg-brand-50 px-4 py-2 text-[11px] font-extrabold uppercase tracking-[0.22em] text-brand-700">
                <?= $isEdit ? 'Edit Category' : 'New Category' ?>
            </span>

            <h1 class="mt-3 text-3xl font-black tracking-[-0.04em] text-slate-900">
                <?= $isEdit ? 'Update Service Category' : 'Create Service Category' ?>
            </h1>

            <p class="mt-2 text-sm leading-7 text-slate-600">
                Manage title, slug, image, description, sort order and visibility.
            </p>
        </div>

        <a
            href="<?= $h($url('admin/service-categories')) ?>"
            class="inline-flex items-center justify-center rounded-full border border-slate-200 bg-white px-5 py-3 text-sm font-extrabold text-slate-700 shadow-sm transition hover:bg-slate-900 hover:text-white"
        >
            ← Back
        </a>
    </div>

    <form
        method="post"
        action="<?= $h($action) ?>"
        enctype="multipart/form-data"
        class="grid gap-6 xl:grid-cols-[1fr_360px]"
    >
        <?= function_exists('csrf_field') ? csrf_field() : '' ?>

        <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?= $h((string) $id) ?>">
        <?php endif; ?>

        <div class="rounded-[30px] border border-white/70 bg-white p-5 shadow-[0_20px_50px_rgba(15,23,42,0.06)] sm:p-6">
            <div class="grid gap-5">
                <div>
                    <label class="mb-2 block text-[11px] font-extrabold uppercase tracking-[0.18em] text-slate-500">
                        Category Title
                    </label>

                    <input
                        type="text"
                        name="title"
                        value="<?= $h($title) ?>"
                        placeholder="Example: GST Services"
                        class="h-13 w-full rounded-2xl border <?= isset($errors['title']) ? 'border-rose-300 bg-rose-50' : 'border-slate-200 bg-slate-50' ?> px-4 py-3 text-sm font-semibold text-slate-800 outline-none transition focus:border-brand-300 focus:bg-white"
                        required
                    >

                    <?php if (!empty($errors['title'])): ?>
                        <p class="mt-2 text-xs font-bold text-rose-600"><?= $h($errors['title']) ?></p>
                    <?php endif; ?>
                </div>

                <div>
                    <label class="mb-2 block text-[11px] font-extrabold uppercase tracking-[0.18em] text-slate-500">
                        Slug
                    </label>

                    <input
                        type="text"
                        name="slug"
                        value="<?= $h($slug) ?>"
                        placeholder="gst-services"
                        class="h-13 w-full rounded-2xl border <?= isset($errors['slug']) ? 'border-rose-300 bg-rose-50' : 'border-slate-200 bg-slate-50' ?> px-4 py-3 text-sm font-semibold text-slate-800 outline-none transition focus:border-brand-300 focus:bg-white"
                    >

                    <p class="mt-2 text-xs font-medium text-slate-500">
                        Leave blank to auto-generate from title.
                    </p>

                    <?php if (!empty($errors['slug'])): ?>
                        <p class="mt-2 text-xs font-bold text-rose-600"><?= $h($errors['slug']) ?></p>
                    <?php endif; ?>
                </div>

                <div>
                    <label class="mb-2 block text-[11px] font-extrabold uppercase tracking-[0.18em] text-slate-500">
                        Description
                    </label>

                    <textarea
                        name="description"
                        rows="6"
                        placeholder="Short description about this category."
                        class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium leading-7 text-slate-800 outline-none transition focus:border-brand-300 focus:bg-white"
                    ><?= $h($description) ?></textarea>
                </div>

                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-[11px] font-extrabold uppercase tracking-[0.18em] text-slate-500">
                            Sort Order
                        </label>

                        <input
                            type="number"
                            name="sort_order"
                            value="<?= $h((string) $sortOrder) ?>"
                            class="h-13 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-800 outline-none transition focus:border-brand-300 focus:bg-white"
                        >
                    </div>

                    <div>
                        <label class="mb-2 block text-[11px] font-extrabold uppercase tracking-[0.18em] text-slate-500">
                            Status
                        </label>

                        <label class="flex h-13 items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <input
                                type="checkbox"
                                name="is_active"
                                value="1"
                                <?= $isActive ? 'checked' : '' ?>
                                class="h-4 w-4 rounded border-slate-300 text-brand-700"
                            >

                            <span class="text-sm font-extrabold text-slate-700">
                                Active Category
                            </span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <aside class="rounded-[30px] border border-white/70 bg-white p-5 shadow-[0_20px_50px_rgba(15,23,42,0.06)] sm:p-6">
            <h2 class="text-lg font-black tracking-[-0.03em] text-slate-900">
                Category Image
            </h2>

            <p class="mt-2 text-sm leading-6 text-slate-500">
                Upload a category image or paste an existing image path.
            </p>

            <div class="mt-5 overflow-hidden rounded-[24px] border border-slate-100 bg-gradient-to-br from-[#eef4ff] via-[#f8faff] to-[#fff3e8]">
                <?php if ($imagePreview !== ''): ?>
                    <img src="<?= $h($imagePreview) ?>" alt="<?= $h($title) ?>" class="h-52 w-full object-cover">
                <?php else: ?>
                    <div class="flex h-52 items-center justify-center text-sm font-black tracking-[0.16em] text-brand-700">
                        IMAGE
                    </div>
                <?php endif; ?>
            </div>

            <div class="mt-5 grid gap-4">
                <div>
                    <label class="mb-2 block text-[11px] font-extrabold uppercase tracking-[0.18em] text-slate-500">
                        Upload New Image
                    </label>

                    <input
                        type="file"
                        name="image_file"
                        accept=".jpg,.jpeg,.png,.webp,.gif,.svg"
                        class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700"
                    >

                    <?php if (!empty($errors['image'])): ?>
                        <p class="mt-2 text-xs font-bold text-rose-600"><?= $h($errors['image']) ?></p>
                    <?php endif; ?>
                </div>

                <div>
                    <label class="mb-2 block text-[11px] font-extrabold uppercase tracking-[0.18em] text-slate-500">
                        Existing Image Path
                    </label>

                    <input
                        type="text"
                        name="image_path"
                        value="<?= $h($image) ?>"
                        placeholder="/uploads/service-categories/example.jpg"
                        class="h-13 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-800 outline-none transition focus:border-brand-300 focus:bg-white"
                    >
                </div>

                <?php if ($isEdit && $image !== ''): ?>
                    <label class="flex items-center gap-3 rounded-2xl border border-rose-100 bg-rose-50 px-4 py-3">
                        <input type="checkbox" name="remove_image" value="1" class="h-4 w-4 rounded border-rose-300">
                        <span class="text-sm font-extrabold text-rose-700">
                            Remove current image
                        </span>
                    </label>
                <?php endif; ?>
            </div>

            <div class="mt-6 grid gap-3">
                <button
                    type="submit"
                    class="inline-flex w-full items-center justify-center rounded-full bg-slate-900 px-5 py-3 text-sm font-extrabold text-white shadow-[0_14px_30px_rgba(15,23,42,0.18)] transition hover:-translate-y-0.5 hover:bg-slate-800"
                >
                    <?= $isEdit ? 'Update Category' : 'Create Category' ?>
                </button>

                <a
                    href="<?= $h($url('admin/service-categories')) ?>"
                    class="inline-flex w-full items-center justify-center rounded-full border border-slate-200 bg-white px-5 py-3 text-sm font-extrabold text-slate-700 transition hover:bg-slate-50"
                >
                    Cancel
                </a>
            </div>
        </aside>
    </form>
</section>