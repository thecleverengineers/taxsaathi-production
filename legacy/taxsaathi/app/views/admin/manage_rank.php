<?php
/** @var array $rankings */
/** @var array $logsByKeyword */
/** @var array $tasksByKeyword */
/** @var array $services */
/** @var array $categories */
/** @var array $stats */
/** @var array $filters */

$rankings = is_array($rankings ?? null) ? array_values($rankings) : [];
$logsByKeyword = is_array($logsByKeyword ?? null) ? $logsByKeyword : [];
$tasksByKeyword = is_array($tasksByKeyword ?? null) ? $tasksByKeyword : [];
$services = is_array($services ?? null) ? array_values($services) : [];
$categories = is_array($categories ?? null) ? array_values($categories) : [];
$stats = is_array($stats ?? null) ? $stats : [];
$filters = is_array($filters ?? null) ? $filters : [];

$rankMovement = static function (?int $current, ?int $previous): array {
    if ($current === null || $previous === null) {
        return ['label' => 'No trend', 'class' => 'border-slate-200 bg-slate-50 text-slate-500', 'symbol' => '—'];
    }

    if ($current < $previous) {
        return ['label' => 'Improved ' . ($previous - $current), 'class' => 'border-emerald-200 bg-emerald-50 text-emerald-700', 'symbol' => '↑'];
    }

    if ($current > $previous) {
        return ['label' => 'Dropped ' . ($current - $previous), 'class' => 'border-rose-200 bg-rose-50 text-rose-700', 'symbol' => '↓'];
    }

    return ['label' => 'Stable', 'class' => 'border-blue-200 bg-blue-50 text-blue-700', 'symbol' => '→'];
};

$priorityClass = static function (string $priority): string {
    return match ($priority) {
        'critical' => 'border-rose-200 bg-rose-50 text-rose-700',
        'high' => 'border-orange-200 bg-orange-50 text-orange-700',
        'medium' => 'border-sky-200 bg-sky-50 text-sky-700',
        default => 'border-slate-200 bg-slate-50 text-slate-600',
    };
};

$statusClass = static function (string $status): string {
    return match ($status) {
        'won' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
        'improving' => 'border-green-200 bg-green-50 text-green-700',
        'declining' => 'border-rose-200 bg-rose-50 text-rose-700',
        'paused' => 'border-slate-200 bg-slate-100 text-slate-500',
        'new' => 'border-amber-200 bg-amber-50 text-amber-700',
        default => 'border-blue-200 bg-blue-50 text-blue-700',
    };
};

$targetLabel = static function (array $ranking): string {
    $type = (string) ($ranking['target_type'] ?? 'custom');

    if ($type === 'service') {
        return 'Service: ' . trim((string) ($ranking['service_title'] ?? $ranking['target_slug'] ?? 'Selected Service'));
    }

    if ($type === 'service_category') {
        return 'Category: ' . trim((string) ($ranking['category_title'] ?? $ranking['target_slug'] ?? 'Selected Category'));
    }

    if ($type === 'page') {
        return 'Page URL';
    }

    return 'Custom URL';
};
?>

<div class="space-y-6">
    <section class="border-b border-slate-200 pb-5">
        <div class="flex flex-col gap-4 py-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-[11px] font-bold uppercase tracking-[0.22em] text-emerald-700">
                    SEO Growth Module
                </span>
                <h1 class="mt-3 text-2xl font-black tracking-[-0.04em] text-slate-900 sm:text-[2rem]">
                    Grow Ranking
                </h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">
                    Track keyword positions, ranking history, target URLs, and SEO growth tasks for every service and page.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <a href="<?= e(base_url('admin/seo')) ?>" class="inline-flex h-11 items-center justify-center rounded-2xl border border-slate-200 bg-white px-5 text-sm font-bold text-slate-700 transition hover:bg-slate-50">
                    Manage SEO
                </a>

                <form method="post" action="<?= e(base_url('admin/ranks/generate-from-services')) ?>" onsubmit="return confirm('Generate grow ranking trackers from all active services? Existing duplicates will be skipped.');">
                    <?= csrf_field() ?>
                    <button type="submit" class="inline-flex h-11 items-center justify-center rounded-2xl bg-[#075B9A] px-5 text-sm font-bold text-white transition hover:bg-[#053B73]">
                        Generate From Active Services
                    </button>
                </form>
            </div>
        </div>
    </section>

    <section class="grid grid-cols-1 gap-0 divide-y divide-slate-200 rounded-none border-y border-slate-200 md:grid-cols-4 xl:grid-cols-7 md:divide-x md:divide-y-0">
        <div class="p-5">
            <p class="text-[11px] font-bold uppercase tracking-[0.20em] text-slate-500">Total</p>
            <h3 class="mt-2 text-3xl font-black text-slate-900"><?= e((string) ($stats['total_keywords'] ?? 0)) ?></h3>
        </div>
        <div class="p-5">
            <p class="text-[11px] font-bold uppercase tracking-[0.20em] text-slate-500">Active</p>
            <h3 class="mt-2 text-3xl font-black text-sky-600"><?= e((string) ($stats['active_keywords'] ?? 0)) ?></h3>
        </div>
        <div class="p-5">
            <p class="text-[11px] font-bold uppercase tracking-[0.20em] text-slate-500">Top 10</p>
            <h3 class="mt-2 text-3xl font-black text-emerald-600"><?= e((string) ($stats['top_10'] ?? 0)) ?></h3>
        </div>
        <div class="p-5">
            <p class="text-[11px] font-bold uppercase tracking-[0.20em] text-slate-500">Improving</p>
            <h3 class="mt-2 text-3xl font-black text-green-600"><?= e((string) ($stats['improving'] ?? 0)) ?></h3>
        </div>
        <div class="p-5">
            <p class="text-[11px] font-bold uppercase tracking-[0.20em] text-slate-500">Declining</p>
            <h3 class="mt-2 text-3xl font-black text-rose-600"><?= e((string) ($stats['declining'] ?? 0)) ?></h3>
        </div>
        <div class="p-5">
            <p class="text-[11px] font-bold uppercase tracking-[0.20em] text-slate-500">Open Tasks</p>
            <h3 class="mt-2 text-3xl font-black text-amber-600"><?= e((string) ($stats['open_tasks'] ?? 0)) ?></h3>
        </div>
        <div class="p-5">
            <p class="text-[11px] font-bold uppercase tracking-[0.20em] text-slate-500">Avg Rank</p>
            <h3 class="mt-2 text-3xl font-black text-violet-600"><?= e(number_format((float) ($stats['average_rank'] ?? 0), 1)) ?></h3>
        </div>
    </section>

    <section class="grid grid-cols-1 gap-6 xl:grid-cols-[0.95fr_1.45fr]">
        <div class="rounded-[24px] border border-slate-200 bg-white shadow-[0_18px_45px_rgba(15,23,42,0.06)]">
            <div class="border-b border-slate-100 px-5 py-4">
                <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-slate-500">Create Tracker</p>
                <h2 class="mt-1 text-lg font-black tracking-[-0.03em] text-slate-900">Add Ranking Keyword</h2>
            </div>

            <form method="post" action="<?= e(base_url('admin/ranks/save-keyword')) ?>" class="grid gap-4 p-5">
                <?= csrf_field() ?>

                <label class="grid gap-2">
                    <span class="text-sm font-bold text-slate-700">Keyword</span>
                    <input type="text" name="keyword" required placeholder="Example: GST Registration Online" class="h-12 rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm outline-none focus:border-sky-300 focus:bg-white">
                </label>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <label class="grid gap-2">
                        <span class="text-sm font-bold text-slate-700">Target Type</span>
                        <select id="rankingTargetType" name="target_type" class="h-12 rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm outline-none focus:border-sky-300 focus:bg-white">
                            <option value="custom">Custom URL</option>
                            <option value="service">Specific Service</option>
                            <option value="service_category">Service Category</option>
                            <option value="page">Specific Page</option>
                        </select>
                    </label>

                    <label class="grid gap-2">
                        <span class="text-sm font-bold text-slate-700">Priority</span>
                        <select name="priority" class="h-12 rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm outline-none focus:border-sky-300 focus:bg-white">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="critical">Critical</option>
                        </select>
                    </label>
                </div>

                <label id="rankingServiceField" class="hidden grid gap-2">
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

                <label id="rankingCategoryField" class="hidden grid gap-2">
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

                <label id="rankingUrlField" class="grid gap-2">
                    <span class="text-sm font-bold text-slate-700">Target URL / Page Path</span>
                    <input type="text" name="target_url" placeholder="/services/gst-registration or https://taxsaathi.in/services/gst-registration" class="h-12 rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm outline-none focus:border-sky-300 focus:bg-white">
                </label>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <label class="grid gap-2">
                        <span class="text-sm font-bold text-slate-700">Current Rank</span>
                        <input type="number" name="current_rank" min="1" placeholder="Example: 18" class="h-12 rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm outline-none focus:border-sky-300 focus:bg-white">
                    </label>

                    <label class="grid gap-2">
                        <span class="text-sm font-bold text-slate-700">Previous Rank</span>
                        <input type="number" name="previous_rank" min="1" placeholder="Example: 25" class="h-12 rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm outline-none focus:border-sky-300 focus:bg-white">
                    </label>

                    <label class="grid gap-2">
                        <span class="text-sm font-bold text-slate-700">Best Rank</span>
                        <input type="number" name="best_rank" min="1" placeholder="Auto if blank" class="h-12 rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm outline-none focus:border-sky-300 focus:bg-white">
                    </label>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <label class="grid gap-2">
                        <span class="text-sm font-bold text-slate-700">Search Volume</span>
                        <input type="number" name="search_volume" value="0" min="0" class="h-12 rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm outline-none focus:border-sky-300 focus:bg-white">
                    </label>

                    <label class="grid gap-2">
                        <span class="text-sm font-bold text-slate-700">Difficulty 0-100</span>
                        <input type="number" name="keyword_difficulty" value="0" min="0" max="100" class="h-12 rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm outline-none focus:border-sky-300 focus:bg-white">
                    </label>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <label class="grid gap-2">
                        <span class="text-sm font-bold text-slate-700">Engine</span>
                        <input type="text" name="search_engine" value="Google" class="h-12 rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm outline-none focus:border-sky-300 focus:bg-white">
                    </label>

                    <label class="grid gap-2">
                        <span class="text-sm font-bold text-slate-700">Location</span>
                        <input type="text" name="location" value="India" class="h-12 rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm outline-none focus:border-sky-300 focus:bg-white">
                    </label>

                    <label class="grid gap-2">
                        <span class="text-sm font-bold text-slate-700">Device</span>
                        <select name="device" class="h-12 rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm outline-none focus:border-sky-300 focus:bg-white">
                            <option value="desktop">Desktop</option>
                            <option value="mobile">Mobile</option>
                        </select>
                    </label>
                </div>

                <label class="grid gap-2">
                    <span class="text-sm font-bold text-slate-700">Notes</span>
                    <textarea name="notes" rows="3" class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none focus:border-sky-300 focus:bg-white"></textarea>
                </label>

                <label class="flex items-center gap-2 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700">
                    <input type="checkbox" name="is_active" value="1" checked class="h-4 w-4 rounded border-slate-300">
                    Active Tracking
                </label>

                <button type="submit" class="inline-flex h-12 items-center justify-center rounded-2xl bg-slate-900 px-5 text-sm font-bold text-white transition hover:bg-slate-800">
                    Save Ranking Keyword
                </button>
            </form>
        </div>

        <div class="rounded-[24px] border border-slate-200 bg-white shadow-[0_18px_45px_rgba(15,23,42,0.06)]">
            <div class="border-b border-slate-100 px-5 py-4">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-slate-500">Ranking Database</p>
                        <h2 class="mt-1 text-lg font-black tracking-[-0.03em] text-slate-900">Tracked Keywords</h2>
                    </div>

                    <form method="get" action="<?= e(base_url('admin/ranks')) ?>" class="grid grid-cols-1 gap-2 md:grid-cols-4">
                        <input type="search" name="q" value="<?= e((string) ($filters['q'] ?? '')) ?>" placeholder="Search keyword..." class="h-11 rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm outline-none focus:border-sky-300 focus:bg-white">
                        <select name="target_type" class="h-11 rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm outline-none focus:border-sky-300 focus:bg-white">
                            <option value="">All Targets</option>
                            <?php foreach (['service' => 'Service', 'service_category' => 'Category', 'page' => 'Page', 'custom' => 'Custom'] as $value => $label): ?>
                                <option value="<?= e($value) ?>" <?= (string) ($filters['target_type'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="status" class="h-11 rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm outline-none focus:border-sky-300 focus:bg-white">
                            <option value="">All Status</option>
                            <?php foreach (['new','monitoring','improving','declining','won','paused'] as $value): ?>
                                <option value="<?= e($value) ?>" <?= (string) ($filters['status'] ?? '') === $value ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $value))) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="priority" class="h-11 rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm outline-none focus:border-sky-300 focus:bg-white">
                            <option value="">All Priority</option>
                            <?php foreach (['critical','high','medium','low'] as $value): ?>
                                <option value="<?= e($value) ?>" <?= (string) ($filters['priority'] ?? '') === $value ? 'selected' : '' ?>><?= e(ucfirst($value)) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="h-11 rounded-xl bg-[#075B9A] px-4 text-sm font-bold text-white md:col-span-4" type="submit">Filter</button>
                    </form>
                </div>
            </div>

            <div class="max-h-[860px] overflow-y-auto p-4">
                <?php if ($rankings === []): ?>
                    <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-5 py-12 text-center">
                        <h3 class="text-base font-black text-slate-900">No ranking keywords found</h3>
                        <p class="mt-2 text-sm text-slate-500">Create a tracker or generate automatically from active services.</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($rankings as $ranking): ?>
                            <?php
                                $rankingId = (int) ($ranking['id'] ?? 0);
                                $current = isset($ranking['current_rank']) && $ranking['current_rank'] !== null ? (int) $ranking['current_rank'] : null;
                                $previous = isset($ranking['previous_rank']) && $ranking['previous_rank'] !== null ? (int) $ranking['previous_rank'] : null;
                                $movement = $rankMovement($current, $previous);
                                $logs = array_slice($logsByKeyword[$rankingId] ?? [], 0, 8);
                                $tasks = $tasksByKeyword[$rankingId] ?? [];
                            ?>
                            <details class="group overflow-hidden rounded-2xl border border-slate-200 bg-slate-50">
                                <summary class="flex cursor-pointer flex-col gap-3 px-4 py-3 transition hover:bg-white xl:flex-row xl:items-center xl:justify-between">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="text-sm font-black text-slate-900"><?= e((string) $ranking['keyword']) ?></span>
                                            <span class="<?= e($priorityClass((string) $ranking['priority'])) ?> rounded-full border px-2 py-0.5 text-[10px] font-bold uppercase tracking-[0.12em]"><?= e((string) $ranking['priority']) ?></span>
                                            <span class="<?= e($statusClass((string) $ranking['status'])) ?> rounded-full border px-2 py-0.5 text-[10px] font-bold uppercase tracking-[0.12em]"><?= e((string) $ranking['status']) ?></span>
                                            <span class="<?= e($movement['class']) ?> rounded-full border px-2 py-0.5 text-[10px] font-bold uppercase tracking-[0.12em]"><?= e($movement['symbol'] . ' ' . $movement['label']) ?></span>
                                            <?php if ((int) $ranking['is_active'] !== 1): ?>
                                                <span class="rounded-full border border-slate-200 bg-slate-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-[0.12em] text-slate-500">Inactive</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="mt-1 text-xs font-semibold text-slate-500">
                                            <?= e($targetLabel($ranking)) ?> · <?= e((string) $ranking['target_url']) ?> · <?= e((string) $ranking['location']) ?> · <?= e((string) $ranking['device']) ?>
                                        </div>
                                    </div>

                                    <div class="flex shrink-0 flex-wrap items-center gap-2">
                                        <span class="rounded-xl bg-white px-3 py-2 text-xs font-black text-slate-700">Rank: <?= e($current !== null ? (string) $current : 'N/A') ?></span>
                                        <span class="rounded-xl bg-white px-3 py-2 text-xs font-black text-slate-700">Best: <?= e($ranking['best_rank'] !== null ? (string) $ranking['best_rank'] : 'N/A') ?></span>
                                        <span class="rounded-xl bg-white px-3 py-2 text-xs font-black text-slate-700">Tasks: <?= e((string) ($ranking['open_task_count'] ?? 0)) ?></span>

                                        <form method="post" action="<?= e(base_url('admin/ranks/toggle-keyword')) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= e((string) $rankingId) ?>">
                                            <button type="submit" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Toggle</button>
                                        </form>

                                        <span class="text-slate-400 transition group-open:rotate-180">⌄</span>
                                    </div>
                                </summary>

                                <div class="grid gap-4 border-t border-slate-200 bg-white p-4">
                                    <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                                        <form method="post" action="<?= e(base_url('admin/ranks/save-log')) ?>" class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="ranking_keyword_id" value="<?= e((string) $rankingId) ?>">
                                            <h4 class="text-sm font-black text-slate-900">Add Rank Check</h4>
                                            <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2">
                                                <input type="number" name="rank_position" min="1" placeholder="New rank" class="h-11 rounded-xl border border-slate-200 bg-white px-3 text-sm">
                                                <input type="datetime-local" name="checked_at" class="h-11 rounded-xl border border-slate-200 bg-white px-3 text-sm">
                                                <input type="text" name="search_engine" value="<?= e((string) $ranking['search_engine']) ?>" class="h-11 rounded-xl border border-slate-200 bg-white px-3 text-sm">
                                                <input type="text" name="location" value="<?= e((string) $ranking['location']) ?>" class="h-11 rounded-xl border border-slate-200 bg-white px-3 text-sm">
                                                <select name="device" class="h-11 rounded-xl border border-slate-200 bg-white px-3 text-sm">
                                                    <option value="desktop" <?= (string) $ranking['device'] === 'desktop' ? 'selected' : '' ?>>Desktop</option>
                                                    <option value="mobile" <?= (string) $ranking['device'] === 'mobile' ? 'selected' : '' ?>>Mobile</option>
                                                </select>
                                                <input type="text" name="notes" placeholder="Notes" class="h-11 rounded-xl border border-slate-200 bg-white px-3 text-sm">
                                            </div>
                                            <button type="submit" class="mt-3 rounded-xl bg-[#075B9A] px-4 py-2 text-sm font-bold text-white">Save Rank Log</button>
                                        </form>

                                        <form method="post" action="<?= e(base_url('admin/ranks/save-task')) ?>" class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="ranking_keyword_id" value="<?= e((string) $rankingId) ?>">
                                            <h4 class="text-sm font-black text-slate-900">Add Growth Task</h4>
                                            <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2">
                                                <input type="text" name="task_title" required placeholder="Task title" class="h-11 rounded-xl border border-slate-200 bg-white px-3 text-sm md:col-span-2">
                                                <select name="task_type" class="h-11 rounded-xl border border-slate-200 bg-white px-3 text-sm">
                                                    <?php foreach (['on_page','content','technical','backlink','internal_link','local_seo','monitoring'] as $type): ?>
                                                        <option value="<?= e($type) ?>"><?= e(ucwords(str_replace('_', ' ', $type))) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <select name="priority" class="h-11 rounded-xl border border-slate-200 bg-white px-3 text-sm">
                                                    <?php foreach (['low','medium','high','critical'] as $priority): ?>
                                                        <option value="<?= e($priority) ?>" <?= $priority === 'medium' ? 'selected' : '' ?>><?= e(ucfirst($priority)) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <select name="status" class="h-11 rounded-xl border border-slate-200 bg-white px-3 text-sm">
                                                    <?php foreach (['todo','in_progress','blocked','done'] as $status): ?>
                                                        <option value="<?= e($status) ?>"><?= e(ucwords(str_replace('_', ' ', $status))) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <input type="date" name="due_date" class="h-11 rounded-xl border border-slate-200 bg-white px-3 text-sm">
                                                <input type="text" name="assigned_to" placeholder="Assigned to" class="h-11 rounded-xl border border-slate-200 bg-white px-3 text-sm">
                                                <input type="text" name="notes" placeholder="Notes" class="h-11 rounded-xl border border-slate-200 bg-white px-3 text-sm">
                                            </div>
                                            <button type="submit" class="mt-3 rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white">Save Task</button>
                                        </form>
                                    </div>

                                    <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                                        <div class="rounded-2xl border border-slate-200 bg-white p-4">
                                            <h4 class="text-sm font-black text-slate-900">Recent Rank History</h4>
                                            <?php if ($logs === []): ?>
                                                <p class="mt-3 text-sm text-slate-500">No rank logs yet.</p>
                                            <?php else: ?>
                                                <div class="mt-3 divide-y divide-slate-100">
                                                    <?php foreach ($logs as $log): ?>
                                                        <div class="flex items-center justify-between gap-3 py-2">
                                                            <div>
                                                                <div class="text-sm font-bold text-slate-800">Position <?= e($log['rank_position'] !== null ? (string) $log['rank_position'] : 'N/A') ?></div>
                                                                <div class="text-xs text-slate-500"><?= e((string) $log['checked_at']) ?> · <?= e((string) $log['device']) ?></div>
                                                            </div>
                                                            <form method="post" action="<?= e(base_url('admin/ranks/delete-log')) ?>" onsubmit="return confirm('Delete this rank log?');">
                                                                <?= csrf_field() ?>
                                                                <input type="hidden" name="id" value="<?= e((string) $log['id']) ?>">
                                                                <button class="rounded-lg bg-rose-50 px-2 py-1 text-xs font-bold text-rose-600">Delete</button>
                                                            </form>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <div class="rounded-2xl border border-slate-200 bg-white p-4">
                                            <h4 class="text-sm font-black text-slate-900">Growth Tasks</h4>
                                            <?php if ($tasks === []): ?>
                                                <p class="mt-3 text-sm text-slate-500">No tasks yet.</p>
                                            <?php else: ?>
                                                <div class="mt-3 divide-y divide-slate-100">
                                                    <?php foreach ($tasks as $task): ?>
                                                        <div class="py-2">
                                                            <form method="post" action="<?= e(base_url('admin/ranks/save-task')) ?>" class="grid gap-2">
                                                                <?= csrf_field() ?>
                                                                <input type="hidden" name="id" value="<?= e((string) $task['id']) ?>">
                                                                <input type="hidden" name="ranking_keyword_id" value="<?= e((string) ($task['ranking_keyword_id'] ?? $rankingId)) ?>">
                                                                <input type="hidden" name="task_title" value="<?= e((string) $task['task_title']) ?>">
                                                                <input type="hidden" name="task_type" value="<?= e((string) $task['task_type']) ?>">
                                                                <input type="hidden" name="priority" value="<?= e((string) $task['priority']) ?>">
                                                                <input type="hidden" name="due_date" value="<?= e((string) ($task['due_date'] ?? '')) ?>">
                                                                <input type="hidden" name="assigned_to" value="<?= e((string) ($task['assigned_to'] ?? '')) ?>">
                                                                <input type="hidden" name="notes" value="<?= e((string) ($task['notes'] ?? '')) ?>">

                                                                <div class="flex items-start justify-between gap-3">
                                                                    <div>
                                                                        <div class="text-sm font-bold text-slate-800"><?= e((string) $task['task_title']) ?></div>
                                                                        <div class="text-xs text-slate-500"><?= e(ucwords(str_replace('_', ' ', (string) $task['task_type']))) ?> · <?= e((string) $task['priority']) ?><?= !empty($task['due_date']) ? ' · Due ' . e((string) $task['due_date']) : '' ?></div>
                                                                    </div>
                                                                    <select name="status" onchange="this.form.submit()" class="rounded-lg border border-slate-200 bg-slate-50 px-2 py-1 text-xs font-bold">
                                                                        <?php foreach (['todo','in_progress','blocked','done'] as $status): ?>
                                                                            <option value="<?= e($status) ?>" <?= (string) $task['status'] === $status ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $status))) ?></option>
                                                                        <?php endforeach; ?>
                                                                    </select>
                                                                </div>
                                                            </form>

                                                            <form method="post" action="<?= e(base_url('admin/ranks/delete-task')) ?>" class="mt-2" onsubmit="return confirm('Delete this task?');">
                                                                <?= csrf_field() ?>
                                                                <input type="hidden" name="id" value="<?= e((string) $task['id']) ?>">
                                                                <button class="rounded-lg bg-rose-50 px-2 py-1 text-xs font-bold text-rose-600">Delete Task</button>
                                                            </form>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="flex flex-wrap gap-2">
                                        <form method="post" action="<?= e(base_url('admin/ranks/delete-keyword')) ?>" onsubmit="return confirm('Delete this ranking keyword, logs, and tasks?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= e((string) $rankingId) ?>">
                                            <button type="submit" class="rounded-xl bg-rose-600 px-4 py-2 text-sm font-bold text-white hover:bg-rose-700">
                                                Delete Tracker
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
    const targetType = document.getElementById('rankingTargetType');
    const serviceField = document.getElementById('rankingServiceField');
    const categoryField = document.getElementById('rankingCategoryField');
    const urlField = document.getElementById('rankingUrlField');

    function syncTargetFields() {
        if (!targetType) return;

        const value = targetType.value;

        if (serviceField) serviceField.classList.toggle('hidden', value !== 'service');
        if (categoryField) categoryField.classList.toggle('hidden', value !== 'service_category');
        if (urlField) urlField.classList.toggle('hidden', value === 'service' || value === 'service_category');
    }

    if (targetType) {
        targetType.addEventListener('change', syncTargetFields);
        syncTargetFields();
    }
});
</script>