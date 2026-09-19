<?php
declare(strict_types=1);
$user = is_array($user ?? null) ? $user : [];
$profile = is_array($profile ?? null) ? $profile : [];
$name = (string)($user['name'] ?? '');
$email = (string)($user['email'] ?? '');
$phone = (string)($user['phone'] ?? '');
$firm = (string)($profile['firm_name'] ?? '');
$display = $firm !== '' ? $firm : ($name !== '' ? $name : 'Partner');
$logo = (string)($profile['logo_path'] ?? '');
?>
<section class="grid gap-5">
    <div class="overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-sm">
        <div class="bg-[linear-gradient(135deg,#0f172a,#0369a1,#0f766e)] px-5 py-8 text-white">
            <div class="flex flex-col gap-5 md:flex-row md:items-center md:justify-between">
                <div class="flex items-center gap-4">
                    <div class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-[24px] border border-white/20 bg-white/10 text-2xl font-black shadow-xl">
                        <?php if ($logo !== ''): ?><img src="<?= e(base_url($logo)) ?>" alt="Partner Logo" class="h-full w-full object-cover"><?php else: ?><?= e(strtoupper(substr($display,0,1))) ?><?php endif; ?>
                    </div>
                    <div>
                        <p class="text-[11px] font-black uppercase tracking-[0.2em] text-cyan-100">Partner Profile</p>
                        <h1 class="mt-1 text-3xl font-black tracking-[-0.04em]"><?= e($display) ?></h1>
                        <p class="mt-2 text-sm text-cyan-50/90"><?= e($email ?: 'No email added') ?><?= $phone !== '' ? ' · ' . e($phone) : '' ?></p>
                    </div>
                </div>
                <a href="<?= e(base_url('partner/orders')) ?>" class="inline-flex h-11 items-center justify-center rounded-2xl bg-white px-5 text-sm font-bold text-slate-900">View Orders</a>
            </div>
        </div>
    </div>

    <form method="post" action="<?= e(base_url('partner/profile/update')) ?>" enctype="multipart/form-data" class="grid gap-5">
        <?= csrf_field() ?>
        <div class="rounded-[24px] border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-black text-slate-900">Basic Details</h2>
            <p class="mt-1 text-sm text-slate-500">Your login and public partner account identity.</p>
            <div class="mt-5 grid gap-4 md:grid-cols-4">
                <label class="grid gap-2"><span class="text-xs font-bold uppercase tracking-[0.12em] text-slate-500">Name</span><input name="name" value="<?= e($name) ?>" class="h-11 rounded-2xl border border-slate-200 px-3 text-sm" required></label>
                <label class="grid gap-2"><span class="text-xs font-bold uppercase tracking-[0.12em] text-slate-500">Email</span><input type="email" name="email" value="<?= e($email) ?>" class="h-11 rounded-2xl border border-slate-200 px-3 text-sm"></label>
                <label class="grid gap-2"><span class="text-xs font-bold uppercase tracking-[0.12em] text-slate-500">Phone</span><input name="phone" value="<?= e($phone) ?>" class="h-11 rounded-2xl border border-slate-200 px-3 text-sm"></label>
                <label class="grid gap-2"><span class="text-xs font-bold uppercase tracking-[0.12em] text-slate-500">Logo</span><input type="file" name="logo" accept="image/png,image/jpeg,image/webp" class="rounded-2xl border border-slate-200 px-3 py-2 text-sm"></label>
            </div>
        </div>

        <div class="rounded-[24px] border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-black text-slate-900">Business Profile</h2>
            <div class="mt-5 grid gap-4 md:grid-cols-3">
                <label class="grid gap-2"><span class="text-xs font-bold uppercase tracking-[0.12em] text-slate-500">Firm / Brand Name</span><input name="firm_name" value="<?= e((string)($profile['firm_name'] ?? '')) ?>" class="h-11 rounded-2xl border border-slate-200 px-3 text-sm"></label>
                <label class="grid gap-2"><span class="text-xs font-bold uppercase tracking-[0.12em] text-slate-500">Legal Name</span><input name="legal_name" value="<?= e((string)($profile['legal_name'] ?? '')) ?>" class="h-11 rounded-2xl border border-slate-200 px-3 text-sm"></label>
                <label class="grid gap-2"><span class="text-xs font-bold uppercase tracking-[0.12em] text-slate-500">Business Type</span><select name="business_type" class="h-11 rounded-2xl border border-slate-200 px-3 text-sm"><?php $bt=(string)($profile['business_type'] ?? ''); ?><option value="">Select Type</option><?php foreach (['Individual','Proprietorship','Partnership','LLP','Private Limited','Agency','Consultant','Other'] as $type): ?><option value="<?= e($type) ?>" <?= $bt===$type?'selected':'' ?>><?= e($type) ?></option><?php endforeach; ?></select></label>
                <label class="grid gap-2"><span class="text-xs font-bold uppercase tracking-[0.12em] text-slate-500">GST Number</span><input name="gst_number" value="<?= e((string)($profile['gst_number'] ?? '')) ?>" class="h-11 rounded-2xl border border-slate-200 px-3 text-sm uppercase"></label>
                <label class="grid gap-2"><span class="text-xs font-bold uppercase tracking-[0.12em] text-slate-500">PAN Number</span><input name="pan_number" value="<?= e((string)($profile['pan_number'] ?? '')) ?>" class="h-11 rounded-2xl border border-slate-200 px-3 text-sm uppercase"></label>
                <label class="grid gap-2"><span class="text-xs font-bold uppercase tracking-[0.12em] text-slate-500">Website</span><input name="website" value="<?= e((string)($profile['website'] ?? '')) ?>" class="h-11 rounded-2xl border border-slate-200 px-3 text-sm" placeholder="https://"></label>
            </div>
        </div>

        <div class="rounded-[24px] border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-black text-slate-900">Address</h2>
            <div class="mt-5 grid gap-4 md:grid-cols-3">
                <label class="grid gap-2 md:col-span-3"><span class="text-xs font-bold uppercase tracking-[0.12em] text-slate-500">Address</span><textarea name="address" class="min-h-[90px] rounded-2xl border border-slate-200 px-3 py-3 text-sm"><?= e((string)($profile['address'] ?? '')) ?></textarea></label>
                <label class="grid gap-2"><span class="text-xs font-bold uppercase tracking-[0.12em] text-slate-500">City</span><input name="city" value="<?= e((string)($profile['city'] ?? '')) ?>" class="h-11 rounded-2xl border border-slate-200 px-3 text-sm"></label>
                <label class="grid gap-2"><span class="text-xs font-bold uppercase tracking-[0.12em] text-slate-500">State</span><input name="state_name" value="<?= e((string)($profile['state_name'] ?? '')) ?>" class="h-11 rounded-2xl border border-slate-200 px-3 text-sm"></label>
                <label class="grid gap-2"><span class="text-xs font-bold uppercase tracking-[0.12em] text-slate-500">Pincode</span><input name="pincode" value="<?= e((string)($profile['pincode'] ?? '')) ?>" class="h-11 rounded-2xl border border-slate-200 px-3 text-sm"></label>
            </div>
        </div>

        <div class="rounded-[24px] border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-black text-slate-900">Payment / Bank Details</h2>
            <div class="mt-5 grid gap-4 md:grid-cols-3">
                <label class="grid gap-2"><span class="text-xs font-bold uppercase tracking-[0.12em] text-slate-500">UPI ID</span><input name="upi_id" value="<?= e((string)($profile['upi_id'] ?? '')) ?>" class="h-11 rounded-2xl border border-slate-200 px-3 text-sm"></label>
                <label class="grid gap-2"><span class="text-xs font-bold uppercase tracking-[0.12em] text-slate-500">Bank Name</span><input name="bank_name" value="<?= e((string)($profile['bank_name'] ?? '')) ?>" class="h-11 rounded-2xl border border-slate-200 px-3 text-sm"></label>
                <label class="grid gap-2"><span class="text-xs font-bold uppercase tracking-[0.12em] text-slate-500">Account Holder</span><input name="account_holder_name" value="<?= e((string)($profile['account_holder_name'] ?? '')) ?>" class="h-11 rounded-2xl border border-slate-200 px-3 text-sm"></label>
                <label class="grid gap-2"><span class="text-xs font-bold uppercase tracking-[0.12em] text-slate-500">Account Number</span><input name="account_number" value="<?= e((string)($profile['account_number'] ?? '')) ?>" class="h-11 rounded-2xl border border-slate-200 px-3 text-sm"></label>
                <label class="grid gap-2"><span class="text-xs font-bold uppercase tracking-[0.12em] text-slate-500">IFSC Code</span><input name="ifsc_code" value="<?= e((string)($profile['ifsc_code'] ?? '')) ?>" class="h-11 rounded-2xl border border-slate-200 px-3 text-sm uppercase"></label>
            </div>
        </div>

        <div class="sticky bottom-4 z-10 flex justify-end"><button class="inline-flex h-12 items-center justify-center rounded-2xl bg-slate-900 px-7 text-sm font-bold text-white shadow-xl" type="submit">Save Partner Profile</button></div>
    </form>
</section>
