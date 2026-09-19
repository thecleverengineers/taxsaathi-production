<?php
declare(strict_types=1);

$categories = is_array($categories ?? null) ? $categories : [];
$search = trim((string) ($search ?? ''));
$status = trim((string) ($status ?? ''));

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

$flash = $_SESSION['flash'] ?? [];
unset($_SESSION['flash']);
?>

<section class="px-4 py-6 sm:px-6 lg:px-8">
    <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <span class="inline-flex rounded-full border border-brand-100 bg-brand-50 px-4 py-2 text-[11px] font-extrabold uppercase tracking-[0.22em] text-brand-700">
                Admin
            </span>

            <h1 class="mt-3 text-3xl font-black tracking-[-0.04em] text-slate-900">
                Service Categories
            </h1>

            <p class="mt-2 text-sm leading-7 text-slate-600">
                Manage category title, image, description, order and active status.
            </p>
        </div>

        <a
            href="<?= $h($url('admin/service-categories/create')) ?>"
            class="inline-flex items-center justify-center rounded-full bg-slate-900 px-5 py-3 text-sm font-extrabold text-white shadow-[0_14px_30px_rgba(15,23,42,0.18)] transition hover:-translate-y-0.5 hover:bg-slate-800"
        >
            + Add Category
        </a>
    </div>

    <?php if (!empty($flash['success'])): ?>
        <div class="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-bold text-emerald-700">
            <?= $h($flash['success']) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($flash['error'])): ?>
        <div class="mb-4 rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm font-bold text-rose-700">
            <?= $h($flash['error']) ?>
        </div>
    <?php endif; ?>

    <div class="mb-5 rounded-[28px] border border-white/70 bg-white p-4 shadow-[0_14px_34px_rgba(15,23,42,0.05)]">
        <form method="get" action="<?= $h($url('admin/service-categories')) ?>" class="grid gap-3 md:grid-cols-[1fr_180px_auto]">
            <input
                type="text"
                name="q"
                value="<?= $h($search) ?>"
                placeholder="Search category..."
                class="h-12 rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm font-semibold text-slate-800 outline-none transition focus:border-brand-300 focus:bg-white"
            >

            <select
                name="status"
                class="h-12 rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm font-semibold text-slate-800 outline-none transition focus:border-brand-300 focus:bg-white"
            >
                <option value="">All Status</option>
                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>

            <button
                type="submit"
                class="inline-flex h-12 items-center justify-center rounded-2xl bg-slate-900 px-5 text-sm font-extrabold text-white transition hover:bg-slate-800"
            >
                Filter
            </button>
        </form>
    </div>

    <div class="overflow-hidden rounded-[30px] border border-white/70 bg-white shadow-[0_20px_50px_rgba(15,23,42,0.06)]">
        <?php if (!empty($categories)): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-5 py-4 text-left text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Category</th>
                            <th class="px-5 py-4 text-left text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Slug</th>
                            <th class="px-5 py-4 text-left text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Services</th>
                            <th class="px-5 py-4 text-left text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Order</th>
                            <th class="px-5 py-4 text-left text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Status</th>
                            <th class="px-5 py-4 text-right text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Action</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($categories as $category): ?>
                            <?php
                                $id = (int) ($category['id'] ?? 0);
                                $title = (string) ($category['title'] ?? 'Category');
                                $slug = (string) ($category['slug'] ?? '');
                                $image = $asset((string) ($category['image'] ?? ''));
                                $description = (string) ($category['description'] ?? '');
                                $serviceCount = (int) ($category['service_count'] ?? 0);
                                $sortOrder = (int) ($category['sort_order'] ?? 0);
                                $isActive = (int) ($category['is_active'] ?? 0) === 1;
                            ?>

                            <tr class="transition hover:bg-slate-50/80">
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="h-12 w-12 shrink-0 overflow-hidden rounded-2xl bg-gradient-to-br from-[#eef4ff] via-[#f8faff] to-[#fff3e8]">
                                            <?php if ($image !== ''): ?>
                                                <img src="<?= $h($image) ?>" alt="<?= $h($title) ?>" class="h-full w-full object-cover">
                                            <?php else: ?>
                                                <div class="flex h-full w-full items-center justify-center text-[10px] font-black tracking-[0.12em] text-brand-700">
                                                    TS
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <div class="min-w-0">
                                            <div class="line-clamp-1 text-sm font-black tracking-[-0.02em] text-slate-900">
                                                <?= $h($title) ?>
                                            </div>
                                            <div class="mt-1 line-clamp-1 max-w-[360px] text-xs font-medium text-slate-500">
                                                <?= $h($description !== '' ? $description : 'No description added.') ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-5 py-4">
                                    <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-[10px] font-extrabold text-slate-600">
                                        <?= $h($slug) ?>
                                    </span>
                                </td>

                                <td class="px-5 py-4">
                                    <span class="inline-flex rounded-full bg-brand-50 px-3 py-1 text-[10px] font-extrabold text-brand-700">
                                        <?= $h((string) $serviceCount) ?> Services
                                    </span>
                                </td>

                                <td class="px-5 py-4 text-sm font-black text-slate-700">
                                    <?= $h((string) $sortOrder) ?>
                                </td>

                                <td class="px-5 py-4">
                                    <form method="post" action="<?= $h($url('admin/service-categories/toggle')) ?>">
                                        <?= function_exists('csrf_field') ? csrf_field() : '' ?>
                                        <input type="hidden" name="id" value="<?= $h((string) $id) ?>">

                                        <button
                                            type="submit"
                                            class="<?= $isActive ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' ?> inline-flex rounded-full px-3 py-1 text-[10px] font-extrabold uppercase tracking-[0.12em]"
                                        >
                                            <?= $isActive ? 'Active' : 'Inactive' ?>
                                        </button>
                                    </form>
                                </td>

                                <td class="px-5 py-4">
                                    <div class="flex justify-end gap-2">
                                        <a
                                            href="<?= $h($url('admin/service-categories/edit?id=' . $id)) ?>"
                                            class="inline-flex items-center rounded-full border border-slate-200 bg-white px-3 py-2 text-[10px] font-extrabold uppercase tracking-[0.12em] text-slate-700 transition hover:bg-slate-900 hover:text-white"
                                        >
                                            Edit
                                        </a>

                                        <form method="post" action="<?= $h($url('admin/service-categories/delete')) ?>" onsubmit="return confirm('Delete this category? This cannot be undone.');">
                                            <?= function_exists('csrf_field') ? csrf_field() : '' ?>
                                            <input type="hidden" name="id" value="<?= $h((string) $id) ?>">

                                            <button
                                                type="submit"
                                                class="inline-flex items-center rounded-full border border-rose-200 bg-rose-50 px-3 py-2 text-[10px] font-extrabold uppercase tracking-[0.12em] text-rose-700 transition hover:bg-rose-600 hover:text-white"
                                            >
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="px-6 py-16 text-center">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-3xl bg-slate-100 text-sm font-black text-slate-500">
                    TS
                </div>

                <h3 class="mt-4 text-lg font-black tracking-[-0.02em] text-slate-900">
                    No categories found
                </h3>

                <p class="mt-2 text-sm leading-7 text-slate-600">
                    Create your first service category to organize services.
                </p>

                <a
                    href="<?= $h($url('admin/service-categories/create')) ?>"
                    class="mt-5 inline-flex items-center justify-center rounded-full bg-slate-900 px-5 py-3 text-sm font-extrabold text-white"
                >
                    Add Category
                </a>
            </div>
        <?php endif; ?>
    </div>
</section>