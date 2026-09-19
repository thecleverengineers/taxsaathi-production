<?php
declare(strict_types=1);

$formatFeatures = static function (mixed $json): string {
    $items = json_decode((string) $json, true);
    if (!is_array($items)) {
        return '';
    }

    return implode("\n", array_map('strval', $items));
};
?>

<section class="grid gap-5">
    <div class="rounded-[24px] border border-slate-200 bg-white p-5 shadow-[0_12px_26px_rgba(15,23,42,0.05)]">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-slate-500">Admin Feature</p>
                <h1 class="mt-1 text-2xl font-black tracking-[-0.04em] text-slate-900">Subscription Plans</h1>
                <p class="mt-2 text-sm text-slate-500">Create plans, set order limits, pricing and validity for partners.</p>
            </div>

            <a href="<?= e(base_url('admin/partners')) ?>" class="inline-flex h-11 items-center justify-center rounded-2xl bg-slate-900 px-5 text-sm font-bold text-white">
                Manage Partners
            </a>
        </div>
    </div>

    <div class="rounded-[24px] border border-slate-200 bg-white p-5 shadow-[0_12px_26px_rgba(15,23,42,0.05)]">
        <h2 class="text-lg font-black text-slate-900">Add New Plan</h2>

        <form method="post" action="<?= e(base_url('admin/subscription-plans/store')) ?>" class="mt-4 grid gap-3 lg:grid-cols-4">
            <?= csrf_field() ?>

            <input class="h-11 rounded-2xl border border-slate-200 px-3 text-sm" name="name" placeholder="Plan Name e.g. Partner Pro" required>
            <input class="h-11 rounded-2xl border border-slate-200 px-3 text-sm" name="slug" placeholder="Slug optional">
            <input class="h-11 rounded-2xl border border-slate-200 px-3 text-sm" type="number" step="0.01" min="0" name="price" placeholder="Price">
            <input class="h-11 rounded-2xl border border-slate-200 px-3 text-sm" type="number" min="1" name="duration_days" value="30" placeholder="Duration Days">

            <input class="h-11 rounded-2xl border border-slate-200 px-3 text-sm" type="number" min="0" name="orders_limit" value="25" placeholder="Order Limit">
            <input class="h-11 rounded-2xl border border-slate-200 px-3 text-sm" type="number" min="0" name="clients_limit" value="0" placeholder="Client Limit">
            <input class="h-11 rounded-2xl border border-slate-200 px-3 text-sm" type="number" min="0" name="staff_limit" value="0" placeholder="Staff Limit">
            <input class="h-11 rounded-2xl border border-slate-200 px-3 text-sm" type="number" name="sort_order" value="0" placeholder="Sort Order">

            <textarea class="min-h-[88px] rounded-2xl border border-slate-200 px-3 py-3 text-sm lg:col-span-2" name="description" placeholder="Plan description"></textarea>
            <textarea class="min-h-[88px] rounded-2xl border border-slate-200 px-3 py-3 text-sm lg:col-span-2" name="features" placeholder="Features, one per line"></textarea>

            <label class="inline-flex h-11 items-center gap-2 rounded-2xl border border-slate-200 px-3 text-sm font-bold text-slate-700">
                <input type="checkbox" name="is_active" value="1" checked>
                Active
            </label>

            <button class="h-11 rounded-2xl bg-brand-700 px-5 text-sm font-bold text-white lg:col-span-3" type="submit">
                Create Subscription Plan
            </button>
        </form>
    </div>

    <div class="overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-[0_12px_26px_rgba(15,23,42,0.05)]">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-[11px] uppercase tracking-[0.15em] text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Plan</th>
                        <th class="px-4 py-3">Limits</th>
                        <th class="px-4 py-3">Price / Validity</th>
                        <th class="px-4 py-3">Features</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Update</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100">
                    <?php foreach (($rows ?? []) as $row): ?>
                        <tr class="align-top">
                            <td class="px-4 py-4">
                                <div class="font-black text-slate-900"><?= e((string) ($row['name'] ?? '-')) ?></div>
                                <div class="mt-1 text-xs text-slate-500"><?= e((string) ($row['slug'] ?? '')) ?></div>
                                <div class="mt-2 text-xs text-slate-500"><?= e((string) ($row['description'] ?? '')) ?></div>
                            </td>

                            <td class="px-4 py-4 text-xs text-slate-600">
                                <div><strong>Orders:</strong> <?= e(((int) ($row['orders_limit'] ?? 0)) > 0 ? (string) $row['orders_limit'] : 'Unlimited') ?></div>
                                <div class="mt-1"><strong>Clients:</strong> <?= e(((int) ($row['clients_limit'] ?? 0)) > 0 ? (string) $row['clients_limit'] : 'Unlimited') ?></div>
                                <div class="mt-1"><strong>Staff:</strong> <?= e(((int) ($row['staff_limit'] ?? 0)) > 0 ? (string) $row['staff_limit'] : 'Unlimited') ?></div>
                            </td>

                            <td class="px-4 py-4">
                                <div class="font-black text-slate-900">₹<?= e(number_format((float) ($row['price'] ?? 0), 2)) ?></div>
                                <div class="mt-1 text-xs text-slate-500"><?= e((string) ($row['duration_days'] ?? 30)) ?> days</div>
                            </td>

                            <td class="px-4 py-4">
                                <?php
                                    $features = json_decode((string) ($row['features_json'] ?? '[]'), true);
                                    $features = is_array($features) ? array_slice($features, 0, 5) : [];
                                ?>
                                <ul class="grid gap-1 text-xs text-slate-600">
                                    <?php foreach ($features as $feature): ?>
                                        <li>• <?= e((string) $feature) ?></li>
                                    <?php endforeach; ?>
                                    <?php if ($features === []): ?>
                                        <li class="text-slate-400">No features added</li>
                                    <?php endif; ?>
                                </ul>
                            </td>

                            <td class="px-4 py-4">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-bold <?= (int) ($row['is_active'] ?? 0) === 1 ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' : 'bg-rose-50 text-rose-700 ring-1 ring-rose-200' ?>">
                                    <?= (int) ($row['is_active'] ?? 0) === 1 ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>

                            <td class="px-4 py-4">
                                <form method="post" action="<?= e(base_url('admin/subscription-plans/update')) ?>" class="grid min-w-[320px] gap-2">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= e((string) ($row['id'] ?? '')) ?>">

                                    <div class="grid grid-cols-2 gap-2">
                                        <input class="h-10 rounded-xl border border-slate-200 px-3 text-xs" name="name" value="<?= e((string) ($row['name'] ?? '')) ?>">
                                        <input class="h-10 rounded-xl border border-slate-200 px-3 text-xs" name="slug" value="<?= e((string) ($row['slug'] ?? '')) ?>">
                                        <input class="h-10 rounded-xl border border-slate-200 px-3 text-xs" type="number" step="0.01" min="0" name="price" value="<?= e((string) ($row['price'] ?? 0)) ?>">
                                        <input class="h-10 rounded-xl border border-slate-200 px-3 text-xs" type="number" min="1" name="duration_days" value="<?= e((string) ($row['duration_days'] ?? 30)) ?>">
                                        <input class="h-10 rounded-xl border border-slate-200 px-3 text-xs" type="number" min="0" name="orders_limit" value="<?= e((string) ($row['orders_limit'] ?? 0)) ?>">
                                        <input class="h-10 rounded-xl border border-slate-200 px-3 text-xs" type="number" min="0" name="clients_limit" value="<?= e((string) ($row['clients_limit'] ?? 0)) ?>">
                                        <input class="h-10 rounded-xl border border-slate-200 px-3 text-xs" type="number" min="0" name="staff_limit" value="<?= e((string) ($row['staff_limit'] ?? 0)) ?>">
                                        <input class="h-10 rounded-xl border border-slate-200 px-3 text-xs" type="number" name="sort_order" value="<?= e((string) ($row['sort_order'] ?? 0)) ?>">
                                    </div>

                                    <textarea class="min-h-[70px] rounded-xl border border-slate-200 px-3 py-2 text-xs" name="description"><?= e((string) ($row['description'] ?? '')) ?></textarea>
                                    <textarea class="min-h-[90px] rounded-xl border border-slate-200 px-3 py-2 text-xs" name="features"><?= e($formatFeatures($row['features_json'] ?? '[]')) ?></textarea>

                                    <label class="inline-flex items-center gap-2 text-xs font-bold text-slate-700">
                                        <input type="checkbox" name="is_active" value="1" <?= (int) ($row['is_active'] ?? 0) === 1 ? 'checked' : '' ?>>
                                        Active
                                    </label>

                                    <button class="h-10 rounded-xl bg-slate-900 text-xs font-bold text-white" type="submit">
                                        Save Plan
                                    </button>
                                </form>

                                <div class="mt-2 grid grid-cols-2 gap-2">
                                    <form method="post" action="<?= e(base_url('admin/subscription-plans/toggle')) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= e((string) ($row['id'] ?? '')) ?>">
                                        <button class="h-10 w-full rounded-xl border border-slate-200 bg-white text-xs font-bold text-slate-700" type="submit">
                                            Toggle
                                        </button>
                                    </form>

                                    <form method="post" action="<?= e(base_url('admin/subscription-plans/delete')) ?>" onsubmit="return confirm('Delete this plan? If already used, it will be deactivated instead.')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= e((string) ($row['id'] ?? '')) ?>">
                                        <button class="h-10 w-full rounded-xl border border-rose-200 bg-rose-50 text-xs font-bold text-rose-700" type="submit">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500">
                                No subscription plans found yet.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
