<?php
/** @var array $inquiry */

$inquiry = $inquiry ?? [];

$statusBadgeClass = static function (string $status): string {
    return match ($status) {
        'new' => 'border-sky-200 bg-sky-50 text-sky-700',
        'contacted' => 'border-amber-200 bg-amber-50 text-amber-700',
        'converted' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
        'closed' => 'border-rose-200 bg-rose-50 text-rose-700',
        default => 'border-slate-200 bg-slate-50 text-slate-600',
    };
};
?>

<div class="space-y-4">
    <section class="overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-[0_12px_26px_rgba(15,23,42,0.05)]">
        <div class="border-b border-slate-100 px-5 py-5">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Inquiry Details</p>
                    <h1 class="mt-1 text-[1.3rem] font-bold tracking-[-0.03em] text-slate-900">
                        <?= e((string) ($inquiry['full_name'] ?? 'Inquiry')) ?>
                    </h1>

                    <div class="mt-3 flex flex-wrap gap-2">
                        <span class="<?= (int) ($inquiry['is_read'] ?? 0) === 0 ? 'border-sky-200 bg-sky-50 text-sky-700' : 'border-slate-200 bg-slate-50 text-slate-600' ?> rounded-full border px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.14em]">
                            <?= (int) ($inquiry['is_read'] ?? 0) === 0 ? 'Unread' : 'Read' ?>
                        </span>

                        <span class="<?= $statusBadgeClass((string) ($inquiry['status'] ?? 'new')) ?> rounded-full border px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.14em]">
                            <?= e(ucfirst((string) ($inquiry['status'] ?? 'new'))) ?>
                        </span>

                        <span class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-600">
                            Inquiry #<?= e((string) ($inquiry['id'] ?? 0)) ?>
                        </span>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <a
                        href="<?= e(base_url('admin/inquiries')) ?>"
                        class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:border-slate-300"
                    >
                        Back to Inquiries
                    </a>
                </div>
            </div>
        </div>

        <div class="grid gap-4 p-5 lg:grid-cols-[1.1fr_0.9fr]">
            <div class="space-y-4">
                <div class="rounded-[18px] border border-slate-200 bg-slate-50 p-4">
                    <h3 class="text-sm font-bold text-slate-900">Applicant Information</h3>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Full Name</p>
                            <p class="mt-1 text-sm font-medium text-slate-800"><?= e((string) ($inquiry['full_name'] ?? '')) ?></p>
                        </div>

                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Email</p>
                            <p class="mt-1 text-sm font-medium text-slate-800"><?= e((string) ($inquiry['email'] ?? '')) ?></p>
                        </div>

                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Mobile</p>
                            <p class="mt-1 text-sm font-medium text-slate-800"><?= e((string) ($inquiry['mobile'] ?? '')) ?></p>
                        </div>

                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">State</p>
                            <p class="mt-1 text-sm font-medium text-slate-800"><?= e((string) ($inquiry['state_name'] ?? '')) ?></p>
                        </div>
                    </div>
                </div>

                <div class="rounded-[18px] border border-slate-200 bg-slate-50 p-4">
                    <h3 class="text-sm font-bold text-slate-900">Service Information</h3>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Service Title</p>
                            <p class="mt-1 text-sm font-medium text-slate-800"><?= e((string) ($inquiry['service_title'] ?? '')) ?></p>
                        </div>

                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Service Slug</p>
                            <p class="mt-1 text-sm font-medium text-slate-800"><?= e((string) ($inquiry['service_slug'] ?? '')) ?></p>
                        </div>

                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Service ID</p>
                            <p class="mt-1 text-sm font-medium text-slate-800"><?= e((string) ($inquiry['service_id'] ?? '')) ?></p>
                        </div>

                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Created At</p>
                            <p class="mt-1 text-sm font-medium text-slate-800"><?= e((string) ($inquiry['created_at'] ?? '')) ?></p>
                        </div>
                    </div>
                </div>

                <div class="rounded-[18px] border border-slate-200 bg-slate-50 p-4">
                    <h3 class="text-sm font-bold text-slate-900">Technical Information</h3>

                    <div class="mt-4 space-y-3 text-sm text-slate-700">
                        <div><strong>Admin Email Sent:</strong> <?= (int) ($inquiry['admin_email_sent'] ?? 0) === 1 ? 'Yes' : 'No' ?></div>
                        <div><strong>User Email Sent:</strong> <?= (int) ($inquiry['user_email_sent'] ?? 0) === 1 ? 'Yes' : 'No' ?></div>
                        <div><strong>IP Address:</strong> <?= e((string) ($inquiry['ip_address'] ?? '')) ?: '—' ?></div>
                        <div><strong>Updated At:</strong> <?= e((string) ($inquiry['updated_at'] ?? '')) ?></div>
                        <div class="break-all"><strong>User Agent:</strong> <?= e((string) ($inquiry['user_agent'] ?? '')) ?: '—' ?></div>
                    </div>
                </div>
            </div>

            <div class="space-y-4">
                <div class="rounded-[18px] border border-slate-200 bg-white p-4">
                    <h3 class="text-sm font-bold text-slate-900">Update Inquiry Status</h3>

                    <form method="post" action="<?= e(base_url('admin/inquiries/update-status')) ?>" class="mt-4 space-y-3">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= e((string) ($inquiry['id'] ?? 0)) ?>">

                        <select name="status" class="h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-3 text-sm text-slate-700">
                            <option value="new" <?= (string) ($inquiry['status'] ?? '') === 'new' ? 'selected' : '' ?>>New</option>
                            <option value="contacted" <?= (string) ($inquiry['status'] ?? '') === 'contacted' ? 'selected' : '' ?>>Contacted</option>
                            <option value="converted" <?= (string) ($inquiry['status'] ?? '') === 'converted' ? 'selected' : '' ?>>Converted</option>
                            <option value="closed" <?= (string) ($inquiry['status'] ?? '') === 'closed' ? 'selected' : '' ?>>Closed</option>
                        </select>

                        <button class="inline-flex h-11 w-full items-center justify-center rounded-2xl bg-slate-900 px-4 text-sm font-semibold text-white transition hover:bg-slate-800" type="submit">
                            Save Status
                        </button>
                    </form>
                </div>

                <div class="rounded-[18px] border border-slate-200 bg-white p-4">
                    <h3 class="text-sm font-bold text-slate-900">Read / Unread</h3>

                    <div class="mt-4 space-y-2">
                        <?php if ((int) ($inquiry['is_read'] ?? 0) === 0): ?>
                            <form method="post" action="<?= e(base_url('admin/inquiries/mark-read')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= e((string) ($inquiry['id'] ?? 0)) ?>">
                                <button class="inline-flex h-11 w-full items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:border-slate-300" type="submit">
                                    Mark as Read
                                </button>
                            </form>
                        <?php else: ?>
                            <form method="post" action="<?= e(base_url('admin/inquiries/mark-unread')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= e((string) ($inquiry['id'] ?? 0)) ?>">
                                <button class="inline-flex h-11 w-full items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:border-slate-300" type="submit">
                                    Mark as Unread
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="rounded-[18px] border border-rose-200 bg-rose-50 p-4">
                    <h3 class="text-sm font-bold text-rose-700">Danger Zone</h3>

                    <form method="post" action="<?= e(base_url('admin/inquiries/delete')) ?>" class="mt-4" onsubmit="return confirm('Delete this inquiry?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= e((string) ($inquiry['id'] ?? 0)) ?>">
                        <button class="inline-flex h-11 w-full items-center justify-center rounded-2xl bg-rose-600 px-4 text-sm font-semibold text-white transition hover:bg-rose-700" type="submit">
                            Delete Inquiry
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>
</div>