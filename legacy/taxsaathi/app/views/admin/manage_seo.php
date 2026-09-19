<?php
/** @var array $keywords */
/** @var array $services */
/** @var array $categories */
/** @var array $stats */
/** @var array $filters */

$keywords = is_array($keywords ?? null) ? array_values($keywords) : [];
$services = is_array($services ?? null) ? array_values($services) : [];
$categories = is_array($categories ?? null) ? array_values($categories) : [];
$stats = is_array($stats ?? null) ? $stats : [];
$filters = is_array($filters ?? null) ? $filters : [];

$targetLabel = static function (array $keyword): string {
    $targetType = (string) ($keyword['target_type'] ?? 'global');

    if ($targetType === 'service') {
        return 'Service: ' . trim((string) ($keyword['service_title'] ?? $keyword['target_slug'] ?? 'Selected Service'));
    }

    if ($targetType === 'service_category') {
        return 'Category: ' . trim((string) ($keyword['category_title'] ?? $keyword['target_slug'] ?? 'Selected Category'));
    }

    if ($targetType === 'page') {
        return 'Page: ' . trim((string) ($keyword['page_path'] ?? '/'));
    }

    return 'Global Website';
};

$statusClass = static function (int $status): string {
    return $status === 1
        ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
        : 'border-slate-200 bg-slate-100 text-slate-500';
};
?>

<div class="space-y-6">
    <section class="border-b border-slate-200 pb-5">
        <div class="flex flex-col gap-4 py-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <span class="inline-flex items-center rounded-full border border-sky-200 bg-sky-50 px-3 py-1 text-[11px] font-bold uppercase tracking-[0.22em] text-sky-700">
                    Dynamic SEO
                </span>
                <h1 class="mt-3 text-2xl font-black tracking-[-0.04em] text-slate-900 sm:text-[2rem]">
                    Manage SEO
                </h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">
                    Manage global, page, service, and category keywords from database. Service keywords use service name and slug as the main SEO source.
                </p>
            </div>

            <a href="<?= e(base_url('admin/services')) ?>" class="inline-flex h-11 items-center justify-center rounded-2xl border border-slate-200 bg-white px-5 text-sm font-bold text-slate-700 transition hover:bg-slate-50">Back to Services</a>

            <form method="post" action="<?= e(base_url('admin/seo/generate-from-services')) ?>" onsubmit="return confirm('Generate SEO keywords from all active services? Existing duplicates will be skipped.');">
                <?= csrf_field() ?>
                <button type="submit" class="inline-flex h-11 items-center justify-center rounded-2xl bg-[#075B9A] px-5 text-sm font-bold text-white transition hover:bg-[#053B73]">
                    Generate From Active Services
                </button>
            </form>
        </div>
    </section>

    <section class="grid grid-cols-1 gap-0 divide-y divide-slate-200 rounded-none border-y border-slate-200 md:grid-cols-4 md:divide-x md:divide-y-0">
        <div class="p-5">
            <p class="text-[11px] font-bold uppercase tracking-[0.20em] text-slate-500">Total Keywords</p>
            <h3 class="mt-2 text-3xl font-black text-slate-900"><?= e((string) ($stats['total_keywords'] ?? 0)) ?></h3>
        </div>
        <div class="p-5">
            <p class="text-[11px] font-bold uppercase tracking-[0.20em] text-slate-500">Active Keywords</p>
            <h3 class="mt-2 text-3xl font-black text-emerald-600"><?= e((string) ($stats['active_keywords'] ?? 0)) ?></h3>
        </div>
        <div class="p-5">
            <p class="text-[11px] font-bold uppercase tracking-[0.20em] text-slate-500">Primary Keywords</p>
            <h3 class="mt-2 text-3xl font-black text-amber-600"><?= e((string) ($stats['primary_keywords'] ?? 0)) ?></h3>
        </div>
        <div class="p-5">
            <p class="text-[11px] font-bold uppercase tracking-[0.20em] text-slate-500">Service Keywords</p>
            <h3 class="mt-2 text-3xl font-black text-sky-600"><?= e((string) ($stats['service_keywords'] ?? 0)) ?></h3>
        </div>
    </section>

    <section class="grid grid-cols-1 gap-6 xl:grid-cols-[0.95fr_1.45fr]">
        <div class="rounded-[24px] border border-slate-200 bg-white shadow-[0_18px_45px_rgba(15,23,42,0.06)]">
            <div class="border-b border-slate-100 px-5 py-4">
                <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-slate-500">Create Keyword</p>
                <h2 class="mt-1 text-lg font-black tracking-[-0.03em] text-slate-900">Add SEO Keywords</h2>
            </div>

            <form method="post" action="<?= e(base_url('admin/seo/save')) ?>" class="grid gap-4 p-5">
                <?= csrf_field() ?>

                <label class="grid gap-2">
                    <span class="text-sm font-bold text-slate-700">Keyword(s)</span>
                    <textarea name="keyword" rows="5" required placeholder="Example: GST Registration, gst registration online, GSTIN application service" class="min-h-[130px] rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-sky-300 focus:bg-white focus:ring-4 focus:ring-sky-100"></textarea>
                    <span class="text-xs text-slate-500">Use comma or new line to add multiple keywords at once.</span>
                </label>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <label class="grid gap-2">
                        <span class="text-sm font-bold text-slate-700">Keyword Type</span>
                        <select name="keyword_type" class="h-12 rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm outline-none focus:border-sky-300 focus:bg-white">
                            <option value="primary">Primary</option>
                            <option value="secondary" selected>Secondary</option>
                            <option value="long_tail">Long-tail</option>
                            <option value="local">Local</option>
                            <option value="semantic">Semantic</option>
                        </select>
                    </label>

                    <label class="grid gap-2">
                        <span class="text-sm font-bold text-slate-700">Target Type</span>
                        <select id="seoTargetType" name="target_type" class="h-12 rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm outline-none focus:border-sky-300 focus:bg-white">
                            <option value="global">Global Website</option>
                            <option value="service">Specific Service</option>
                            <option value="service_category">Service Category</option>
                            <option value="page">Specific Page</option>
                        </select>
                    </label>
                </div>

                <label id="seoServiceField" class="hidden grid gap-2">
                    <span class="text-sm font-bold text-slate-700">Select Service</span>
                    <select name="service_id" class="h-12 rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm outline-none focus:border-sky-300 focus:bg-white">
                        <option value="">Select service</option>
                        <?php foreach ($services as $service): ?>
                            <option value="<?= e((string) $service['id']) ?>">
                                <?= e((string) $service['title']) ?> — <?= e((string) $service['slug']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label id="seoCategoryField" class="hidden grid gap-2">
                    <span class="text-sm font-bold text-slate-700">Select Category</span>
                    <select name="category_id" class="h-12 rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm outline-none focus:border-sky-300 focus:bg-white">
                        <option value="">Select category</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= e((string) $category['id']) ?>">
                                <?= e((string) $category['title']) ?> — <?= e((string) $category['slug']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label id="seoPageField" class="hidden grid gap-2">
                    <span class="text-sm font-bold text-slate-700">Page Path</span>
                    <input type="text" name="page_path" placeholder="/about or /contact" class="h-12 rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm outline-none focus:border-sky-300 focus:bg-white">
                </label>

                <label class="grid gap-2">
                    <span class="text-sm font-bold text-slate-700">Meta Title</span>
                    <input type="text" name="meta_title" maxlength="255" placeholder="Auto-generated if blank" class="h-12 rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm outline-none focus:border-sky-300 focus:bg-white">
                </label>

                <label class="grid gap-2">
                    <span class="text-sm font-bold text-slate-700">Meta Description</span>
                    <textarea name="meta_description" rows="3" placeholder="Auto-generated if blank" class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none focus:border-sky-300 focus:bg-white"></textarea>
                </label>

                <label class="grid gap-2">
                    <span class="text-sm font-bold text-slate-700">Canonical URL / Path</span>
                    <input type="text" name="canonical_url" placeholder="/services/gst-registration" class="h-12 rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm outline-none focus:border-sky-300 focus:bg-white">
                </label>

                <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                    <label class="grid gap-2">
                        <span class="text-sm font-bold text-slate-700">Sort</span>
                        <input type="number" name="sort_order" value="1" class="h-12 rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm outline-none focus:border-sky-300 focus:bg-white">
                    </label>

                    <label class="flex items-center gap-2 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700">
                        <input type="checkbox" name="is_primary" value="1" class="h-4 w-4 rounded border-slate-300">
                        Primary
                    </label>

                    <label class="flex items-center gap-2 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700">
                        <input type="checkbox" name="is_active" value="1" checked class="h-4 w-4 rounded border-slate-300">
                        Active
                    </label>
                </div>

                <button type="submit" class="inline-flex h-12 items-center justify-center rounded-2xl bg-slate-900 px-5 text-sm font-bold text-white transition hover:bg-slate-800">
                    Save SEO Keywords
                </button>
            </form>
        </div>

        <div class="rounded-[24px] border border-slate-200 bg-white shadow-[0_18px_45px_rgba(15,23,42,0.06)]">
            <div class="border-b border-slate-100 px-5 py-4">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-slate-500">Keyword Database</p>
                        <h2 class="mt-1 text-lg font-black tracking-[-0.03em] text-slate-900">All SEO Keywords</h2>
                    </div>

                    <form method="get" action="<?= e(base_url('admin/seo')) ?>" class="grid grid-cols-1 gap-2 md:grid-cols-3">
                        <input type="search" name="q" value="<?= e((string) ($filters['q'] ?? '')) ?>" placeholder="Search keyword..." class="h-11 rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm outline-none focus:border-sky-300 focus:bg-white">
                        <select name="target_type" class="h-11 rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm outline-none focus:border-sky-300 focus:bg-white">
                            <option value="">All Targets</option>
                            <?php foreach (['global' => 'Global', 'page' => 'Page', 'service' => 'Service', 'service_category' => 'Category'] as $value => $label): ?>
                                <option value="<?= e($value) ?>" <?= (string) ($filters['target_type'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="status" class="h-11 rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm outline-none focus:border-sky-300 focus:bg-white">
                            <option value="">All Status</option>
                            <option value="1" <?= (string) ($filters['status'] ?? '') === '1' ? 'selected' : '' ?>>Active</option>
                            <option value="0" <?= (string) ($filters['status'] ?? '') === '0' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                        <button class="h-11 rounded-xl bg-[#075B9A] px-4 text-sm font-bold text-white md:col-span-3" type="submit">Filter</button>
                    </form>
                </div>
            </div>

            <div class="max-h-[760px] overflow-y-auto p-4">
                <?php if ($keywords === []): ?>
                    <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-5 py-12 text-center">
                        <h3 class="text-base font-black text-slate-900">No SEO keywords found</h3>
                        <p class="mt-2 text-sm text-slate-500">Create a keyword or generate automatically from active services.</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($keywords as $keyword): ?>
                            <details class="group overflow-hidden rounded-2xl border border-slate-200 bg-slate-50">
                                <summary class="flex cursor-pointer flex-col gap-3 px-4 py-3 transition hover:bg-white sm:flex-row sm:items-center sm:justify-between">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="text-sm font-black text-slate-900"><?= e((string) $keyword['keyword']) ?></span>
                                            <?php if ((int) $keyword['is_primary'] === 1): ?>
                                                <span class="rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-[0.12em] text-amber-700">Primary</span>
                                            <?php endif; ?>
                                            <span class="<?= e($statusClass((int) $keyword['is_active'])) ?> rounded-full border px-2 py-0.5 text-[10px] font-bold uppercase tracking-[0.12em]">
                                                <?= (int) $keyword['is_active'] === 1 ? 'Active' : 'Inactive' ?>
                                            </span>
                                        </div>
                                        <div class="mt-1 text-xs font-semibold text-slate-500">
                                            <?= e($targetLabel($keyword)) ?> · <?= e((string) $keyword['keyword_type']) ?> · <?= e((string) $keyword['keyword_slug']) ?>
                                        </div>
                                    </div>

                                    <div class="flex shrink-0 items-center gap-2">
                                        <form method="post" action="<?= e(base_url('admin/seo/toggle')) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= e((string) $keyword['id']) ?>">
                                            <button type="submit" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">
                                                Toggle
                                            </button>
                                        </form>
                                        <span class="text-slate-400 transition group-open:rotate-180">⌄</span>
                                    </div>
                                </summary>

                                <div class="border-t border-slate-200 bg-white p-4">
                                    <form method="post" action="<?= e(base_url('admin/seo/save')) ?>" class="grid gap-3">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= e((string) $keyword['id']) ?>">

                                        <input type="hidden" name="target_type" value="<?= e((string) $keyword['target_type']) ?>">
                                        <?php if ((string) $keyword['target_type'] === 'service'): ?>
                                            <input type="hidden" name="service_id" value="<?= e((string) $keyword['target_id']) ?>">
                                        <?php elseif ((string) $keyword['target_type'] === 'service_category'): ?>
                                            <input type="hidden" name="category_id" value="<?= e((string) $keyword['target_id']) ?>">
                                        <?php elseif ((string) $keyword['target_type'] === 'page'): ?>
                                            <input type="hidden" name="page_path" value="<?= e((string) $keyword['page_path']) ?>">
                                        <?php endif; ?>

                                        <label class="grid gap-1">
                                            <span class="text-xs font-bold uppercase tracking-[0.14em] text-slate-500">Keyword</span>
                                            <input type="text" name="keyword" value="<?= e((string) $keyword['keyword']) ?>" class="h-11 rounded-xl border border-slate-200 bg-white px-3 text-sm outline-none focus:border-sky-300">
                                        </label>

                                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                            <label class="grid gap-1">
                                                <span class="text-xs font-bold uppercase tracking-[0.14em] text-slate-500">Keyword Type</span>
                                                <select name="keyword_type" class="h-11 rounded-xl border border-slate-200 bg-white px-3 text-sm outline-none focus:border-sky-300">
                                                    <?php foreach (['primary','secondary','long_tail','local','semantic'] as $type): ?>
                                                        <option value="<?= e($type) ?>" <?= (string) $keyword['keyword_type'] === $type ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $type))) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </label>

                                            <label class="grid gap-1">
                                                <span class="text-xs font-bold uppercase tracking-[0.14em] text-slate-500">Sort</span>
                                                <input type="number" name="sort_order" value="<?= e((string) $keyword['sort_order']) ?>" class="h-11 rounded-xl border border-slate-200 bg-white px-3 text-sm outline-none focus:border-sky-300">
                                            </label>
                                        </div>

                                        <label class="grid gap-1">
                                            <span class="text-xs font-bold uppercase tracking-[0.14em] text-slate-500">Meta Title</span>
                                            <input type="text" name="meta_title" value="<?= e((string) ($keyword['meta_title'] ?? '')) ?>" class="h-11 rounded-xl border border-slate-200 bg-white px-3 text-sm outline-none focus:border-sky-300">
                                        </label>

                                        <label class="grid gap-1">
                                            <span class="text-xs font-bold uppercase tracking-[0.14em] text-slate-500">Meta Description</span>
                                            <textarea name="meta_description" rows="3" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-sky-300"><?= e((string) ($keyword['meta_description'] ?? '')) ?></textarea>
                                        </label>

                                        <label class="grid gap-1">
                                            <span class="text-xs font-bold uppercase tracking-[0.14em] text-slate-500">Canonical URL / Path</span>
                                            <input type="text" name="canonical_url" value="<?= e((string) ($keyword['canonical_url'] ?? '')) ?>" class="h-11 rounded-xl border border-slate-200 bg-white px-3 text-sm outline-none focus:border-sky-300">
                                        </label>

                                        <div class="flex flex-wrap items-center gap-3">
                                            <label class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-700">
                                                <input type="checkbox" name="is_primary" value="1" <?= (int) $keyword['is_primary'] === 1 ? 'checked' : '' ?>>
                                                Primary
                                            </label>

                                            <label class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-700">
                                                <input type="checkbox" name="is_active" value="1" <?= (int) $keyword['is_active'] === 1 ? 'checked' : '' ?>>
                                                Active
                                            </label>

                                            <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white hover:bg-slate-800">
                                                Save Changes
                                            </button>
                                    </form>

                                    <form method="post" action="<?= e(base_url('admin/seo/delete')) ?>" onsubmit="return confirm('Delete this SEO keyword?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= e((string) $keyword['id']) ?>">
                                        <button type="submit" class="rounded-xl bg-rose-600 px-4 py-2 text-sm font-bold text-white hover:bg-rose-700">
                                            Delete
                                        </button>
                                    </form>
                                        </div>
                                </div>
                            </details>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const targetType = document.getElementById('seoTargetType');
    const serviceField = document.getElementById('seoServiceField');
    const categoryField = document.getElementById('seoCategoryField');
    const pageField = document.getElementById('seoPageField');

    function syncTargetFields() {
        if (!targetType) return;

        const value = targetType.value;

        if (serviceField) serviceField.classList.toggle('hidden', value !== 'service');
        if (categoryField) categoryField.classList.toggle('hidden', value !== 'service_category');
        if (pageField) pageField.classList.toggle('hidden', value !== 'page');
    }

    if (targetType) {
        targetType.addEventListener('change', syncTargetFields);
        syncTargetFields();
    }
});
</script>
