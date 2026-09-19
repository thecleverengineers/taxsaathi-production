<?php declare(strict_types=1); ?>
<section class="grid gap-5">
    <div class="rounded-[24px] border border-slate-200 bg-white p-5 shadow-sm">
        <h1 class="text-2xl font-black text-slate-900">Manage Partners</h1>
        <p class="mt-2 text-sm text-slate-500">View partner-wise records, manage partner profiles and status. Subscription has been removed completely.</p>
    </div>
    <div class="overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-[0.14em] text-slate-500"><tr><th class="px-4 py-3">Partner</th><th class="px-4 py-3">Orders</th><th class="px-4 py-3">Payable</th><th class="px-4 py-3">Discount</th><th class="px-4 py-3">Pending</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Action</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach (($rows??[]) as $row): ?>
                    <tr>
                        <td class="px-4 py-3"><div class="font-bold"><?= e((string)($row['name']??'-')) ?></div><div class="text-xs text-slate-500"><?= e((string)($row['email']??'')) ?> · <?= e((string)($row['phone']??'')) ?></div></td>
                        <td class="px-4 py-3 font-bold"><?= e((string)($row['total_orders']??0)) ?></td>
                        <td class="px-4 py-3">₹<?= e(number_format((float)($row['total_payable']??0),2)) ?></td>
                        <td class="px-4 py-3">₹<?= e(number_format((float)($row['total_discount']??0),2)) ?></td>
                        <td class="px-4 py-3"><?= e((string)($row['pending_payments']??0)) ?></td>
                        <td class="px-4 py-3"><?= (int)($row['is_active']??1)===1 ? 'Active' : 'Inactive' ?></td>
                        <td class="px-4 py-3 text-right"><a class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-bold" href="<?= e(base_url('admin/partners/show?id='.(int)($row['id']??0))) ?>">Open</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($rows)): ?><tr><td colspan="7" class="px-4 py-8 text-center text-slate-500">No partners found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
