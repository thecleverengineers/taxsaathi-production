<?php
/** @var array $stats */
/** @var array $inquiries */
/** @var string $filter */
/** @var string $q */

$filter = $filter ?? 'all';
$q = $q ?? '';

$filterButtonClass = static function (string $key, string $active): string {
    return $key === $active
        ? 'border-slate-900 bg-slate-900 text-white'
        : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:text-slate-900';
};

$statusBadgeClass = static function (string $status): string {
    return match ($status) {
        'new' => 'border-sky-200 bg-sky-50 text-sky-700',
        'contacted' => 'border-amber-200 bg-amber-50 text-amber-700',
        'converted' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
        'closed' => 'border-rose-200 bg-rose-50 text-rose-700',
        default => 'border-slate-200 bg-slate-50 text-slate-600',
    };
};

$readBadgeClass = static function (int $isRead): string {
    return $isRead === 0
        ? 'border-sky-200 bg-sky-50 text-sky-700'
        : 'border-slate-200 bg-slate-50 text-slate-600';
};
?>

<div class="space-y-5">
    <section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-[22px] border border-slate-200 bg-white p-5 shadow-[0_12px_26px_rgba(15,23,42,0.05)]">
            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Total</p>
            <h3 class="mt-2 text-2xl font-bold tracking-[-0.03em] text-slate-900"><?= e((string) ($stats['total'] ?? 0)) ?></h3>
            <p class="mt-1 text-sm text-slate-500">All inquiries</p>
        </div>

        <div class="rounded-[22px] border border-slate-200 bg-white p-5 shadow-[0_12px_26px_rgba(15,23,42,0.05)]">
            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Unread</p>
            <h3 class="mt-2 text-2xl font-bold tracking-[-0.03em] text-slate-900"><?= e((string) ($stats['unread'] ?? 0)) ?></h3>
            <p class="mt-1 text-sm text-slate-500">Need attention</p>
        </div>

        <div class="rounded-[22px] border border-slate-200 bg-white p-5 shadow-[0_12px_26px_rgba(15,23,42,0.05)]">
            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Read</p>
            <h3 class="mt-2 text-2xl font-bold tracking-[-0.03em] text-slate-900"><?= e((string) ($stats['read'] ?? 0)) ?></h3>
            <p class="mt-1 text-sm text-slate-500">Already reviewed</p>
        </div>

        <div class="rounded-[22px] border border-slate-200 bg-white p-5 shadow-[0_12px_26px_rgba(15,23,42,0.05)]">
            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">New Status</p>
            <h3 class="mt-2 text-2xl font-bold tracking-[-0.03em] text-slate-900"><?= e((string) ($stats['new'] ?? 0)) ?></h3>
            <p class="mt-1 text-sm text-slate-500">Fresh applications</p>
        </div>
    </section>

    <section class="overflow-hidden rounded-[22px] border border-slate-200 bg-white shadow-[0_12px_26px_rgba(15,23,42,0.05)]">
        <div class="border-b border-slate-100 px-5 py-5">
            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Service Inquiries</p>
            <h2 class="mt-1 text-[1.2rem] font-bold tracking-[-0.03em] text-slate-900">Manage Inquiries</h2>
        </div>

        <div class="space-y-4 border-b border-slate-100 p-5">
            <form method="get" action="<?= e(base_url('admin/inquiries')) ?>" class="grid gap-3 lg:grid-cols-[1fr_auto]">
                <input
                    class="h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700 outline-none transition focus:border-slate-300 focus:bg-white"
                    type="text"
                    name="q"
                    value="<?= e($q) ?>"
                    placeholder="Search by full name, service title, email, mobile, state, or slug"
                >
                <input type="hidden" name="filter" value="<?= e($filter) ?>">
                <button
                    class="inline-flex h-11 items-center justify-center rounded-2xl bg-slate-900 px-5 text-sm font-semibold text-white transition hover:bg-slate-800"
                    type="submit"
                >
                    Search
                </button>
            </form>

            <div class="flex flex-wrap gap-2">
                <a href="<?= e(base_url('admin/inquiries?filter=all&q=' . urlencode($q))) ?>" class="<?= $filterButtonClass('all', $filter) ?> inline-flex rounded-full border px-4 py-2 text-[11px] font-semibold uppercase tracking-[0.14em] transition">All</a>
                <a href="<?= e(base_url('admin/inquiries?filter=unread&q=' . urlencode($q))) ?>" class="<?= $filterButtonClass('unread', $filter) ?> inline-flex rounded-full border px-4 py-2 text-[11px] font-semibold uppercase tracking-[0.14em] transition">Unread</a>
                <a href="<?= e(base_url('admin/inquiries?filter=read&q=' . urlencode($q))) ?>" class="<?= $filterButtonClass('read', $filter) ?> inline-flex rounded-full border px-4 py-2 text-[11px] font-semibold uppercase tracking-[0.14em] transition">Read</a>
                <a href="<?= e(base_url('admin/inquiries?filter=new&q=' . urlencode($q))) ?>" class="<?= $filterButtonClass('new', $filter) ?> inline-flex rounded-full border px-4 py-2 text-[11px] font-semibold uppercase tracking-[0.14em] transition">New</a>
                <a href="<?= e(base_url('admin/inquiries?filter=contacted&q=' . urlencode($q))) ?>" class="<?= $filterButtonClass('contacted', $filter) ?> inline-flex rounded-full border px-4 py-2 text-[11px] font-semibold uppercase tracking-[0.14em] transition">Contacted</a>
                <a href="<?= e(base_url('admin/inquiries?filter=converted&q=' . urlencode($q))) ?>" class="<?= $filterButtonClass('converted', $filter) ?> inline-flex rounded-full border px-4 py-2 text-[11px] font-semibold uppercase tracking-[0.14em] transition">Converted</a>
                <a href="<?= e(base_url('admin/inquiries?filter=closed&q=' . urlencode($q))) ?>" class="<?= $filterButtonClass('closed', $filter) ?> inline-flex rounded-full border px-4 py-2 text-[11px] font-semibold uppercase tracking-[0.14em] transition">Closed</a>
            </div>
        </div>
<div class="divide-y divide-slate-100/80">
    <?php if (!empty($inquiries)): ?>
        <?php foreach ($inquiries as $inquiry): ?>
            <?php
                $isRead = (int) ($inquiry['is_read'] ?? 0);
                $status = (string) ($inquiry['status'] ?? 'new');

                $statusClasses = [
                    'new' => 'border-sky-200 bg-sky-50 text-sky-700',
                    'contacted' => 'border-amber-200 bg-amber-50 text-amber-700',
                    'converted' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                    'closed' => 'border-slate-200 bg-slate-100 text-slate-600',
                ];

                $rowStatusClass = $statusClasses[$status] ?? 'border-slate-200 bg-slate-50 text-slate-700';
                $readText = $isRead === 0 ? 'Unread' : 'Read';
                $readWrapClass = $isRead === 0
                    ? 'border-sky-200 bg-sky-50 text-sky-700'
                    : 'border-slate-200 bg-slate-50 text-slate-600';
            ?>

            <div class="group px-4 py-3 transition hover:bg-slate-50/80 sm:px-5">
                <div class="flex items-center gap-3 rounded-[20px] border border-slate-200/80 bg-white px-4 py-3 shadow-[0_6px_24px_rgba(15,23,42,0.04)] transition duration-200 hover:border-slate-300/80 hover:shadow-[0_10px_30px_rgba(15,23,42,0.06)]">

                    <!-- Name -->
                    <div class="min-w-0 flex-[1.2]">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-400">Name</p>
                        <p class="truncate text-sm font-semibold text-slate-900">
                            <?= e((string) ($inquiry['full_name'] ?? '—')) ?>
                        </p>
                    </div>

                    <!-- Service -->
                    <div class="min-w-0 flex-[1.3]">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-400">Service</p>
                        <p class="truncate text-sm font-medium text-slate-700">
                            <?= e((string) ($inquiry['service_title'] ?? '—')) ?>
                        </p>
                    </div>

                    <!-- Created -->
                    <div class="hidden min-w-0 flex-[0.9] md:block">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-400">Created</p>
                        <p class="truncate text-sm font-medium text-slate-700">
                            <?= e((string) ($inquiry['created_at'] ?? '—')) ?>
                        </p>
                    </div>

                    <!-- Status -->
                    <div class="shrink-0">
                        <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.14em] <?= $rowStatusClass ?>">
                            <?= e(ucfirst($status)) ?>
                        </span>
                    </div>

                    <!-- Read / Unread -->
                    <div class="shrink-0">
                        <?php if ($isRead === 0): ?>
                            <form method="post" action="<?= e(base_url('admin/inquiries/mark-read')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= e((string) ($inquiry['id'] ?? '')) ?>">
                                <input type="hidden" name="filter" value="<?= e($filter) ?>">
                                <input type="hidden" name="q" value="<?= e($q) ?>">
                                <button
                                    type="submit"
                                    class="inline-flex h-9 items-center gap-2 rounded-full border px-3 text-[11px] font-semibold transition hover:border-slate-300 <?= $readWrapClass ?>"
                                    title="Mark as read"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        <circle cx="12" cy="12" r="3" />
                                    </svg>
                                    <?= $readText ?>
                                </button>
                            </form>
                        <?php else: ?>
                            <form method="post" action="<?= e(base_url('admin/inquiries/mark-unread')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= e((string) ($inquiry['id'] ?? '')) ?>">
                                <input type="hidden" name="filter" value="<?= e($filter) ?>">
                                <input type="hidden" name="q" value="<?= e($q) ?>">
                                <button
                                    type="submit"
                                    class="inline-flex h-9 items-center gap-2 rounded-full border px-3 text-[11px] font-semibold transition hover:border-slate-300 <?= $readWrapClass ?>"
                                    title="Mark as unread"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.584 10.587A2 2 0 0012 14a2 2 0 001.414-.586" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.88 5.09A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.542 7a9.97 9.97 0 01-4.205 5.132M6.228 6.228A9.956 9.956 0 002.458 12c1.274 4.057 5.064 7 9.542 7 1.61 0 3.133-.38 4.484-1.056" />
                                    </svg>
                                    <?= $readText ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <!-- Open Inquiry -->
                    <div class="shrink-0">
                        <a
                            href="<?= e(base_url('admin/inquiries/show?id=' . (int) $inquiry['id'])) ?>"
                            class="inline-flex h-9 items-center justify-center rounded-full border border-slate-200 bg-white px-4 text-[11px] font-semibold text-slate-700 transition hover:border-slate-300 hover:text-slate-900"
                        >
                            Open Inquiry
                        </a>
                    </div>

                    <!-- Delete -->
                    <div class="shrink-0">
                        <form method="post" action="<?= e(base_url('admin/inquiries/delete')) ?>" onsubmit="return confirm('Delete this inquiry?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= e((string) ($inquiry['id'] ?? '')) ?>">
                            <input type="hidden" name="filter" value="<?= e($filter) ?>">
                            <input type="hidden" name="q" value="<?= e($q) ?>">
                            <button
                                type="submit"
                                class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-rose-200 bg-rose-50 text-rose-600 transition hover:bg-rose-100 hover:text-rose-700"
                                title="Delete inquiry"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.9">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 7V5.5A1.5 1.5 0 0110.5 4h3A1.5 1.5 0 0115 5.5V7" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7l.7 11.2A2 2 0 0010.696 20h2.608a2 2 0 001.996-1.8L16 7" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 11v5M14 11v5" />
                                </svg>
                            </button>
                        </form>
                    </div>

                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="px-5 py-12 text-center text-sm text-slate-500">
            No inquiries found.
        </div>
    <?php endif; ?>
</div>
    </section>
</div>