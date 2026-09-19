<?php
$userData = is_array($userData ?? null) ? $userData : [];
$dashboardPath = trim((string) ($dashboardPath ?? 'client/dashboard'));
$name = trim((string) ($userData['name'] ?? 'User'));
$email = trim((string) ($userData['email'] ?? ''));
?>

<div class="mx-auto w-full max-w-6xl space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="text-[11px] font-black uppercase tracking-[0.22em] text-emerald-700">Account Security</div>
            <h1 class="mt-2 text-3xl font-black tracking-[-0.05em] text-slate-900 sm:text-4xl">Change your password</h1>
            <p class="mt-2 text-sm leading-6 text-slate-500">Update your sign-in password without leaving the secure dashboard.</p>
        </div>

        <a href="<?= e(base_url($dashboardPath)) ?>" class="group inline-flex h-12 items-center justify-center gap-3 rounded-2xl border border-slate-200 bg-white px-5 text-sm font-black text-slate-700 shadow-sm transition hover:-translate-y-0.5 hover:border-emerald-200 hover:text-emerald-700">
            <span class="transition group-hover:-translate-x-0.5">←</span>
            Back to Dashboard
        </a>
    </div>

    <section class="relative isolate overflow-hidden rounded-[30px] border border-slate-200/80 bg-white shadow-[0_24px_80px_rgba(15,23,42,0.08)]">
        <div class="pointer-events-none absolute inset-0">
            <div class="absolute -right-24 -top-24 h-72 w-72 rounded-full bg-emerald-100/70 blur-3xl"></div>
            <div class="absolute -bottom-24 left-0 h-64 w-64 rounded-full bg-sky-100/50 blur-3xl"></div>
        </div>

        <div class="relative z-10 grid lg:grid-cols-[0.85fr_1.15fr]">
            <div class="border-b border-slate-100 bg-[linear-gradient(145deg,#0f172a,#1e293b,#064e3b)] p-6 text-white sm:p-8 lg:border-b-0 lg:border-r lg:p-10">
                <div class="flex h-16 w-16 items-center justify-center rounded-[22px] bg-white/10 text-3xl ring-1 ring-white/20">🔐</div>
                <h2 class="mt-6 text-2xl font-black tracking-[-0.04em]">Protect your Tax Saathi account</h2>
                <p class="mt-3 text-sm leading-7 text-slate-200">A strong and unique password helps protect your documents, orders and payment information.</p>

                <div class="mt-7 space-y-3">
                    <div class="rounded-[20px] border border-white/10 bg-white/10 p-4 backdrop-blur">
                        <div class="text-[10px] font-black uppercase tracking-[0.18em] text-emerald-200">Signed in as</div>
                        <div class="mt-2 font-black text-white"><?= e($name !== '' ? $name : 'User') ?></div>
                        <?php if ($email !== ''): ?><div class="mt-1 break-all text-xs text-slate-300"><?= e($email) ?></div><?php endif; ?>
                    </div>
                    <div class="rounded-[20px] border border-white/10 bg-white/10 p-4 text-xs leading-6 text-slate-200 backdrop-blur">
                        Your current password is required before the new password can be saved.
                    </div>
                </div>
            </div>

            <div class="p-6 sm:p-8 lg:p-10">
                <form method="post" action="<?= e(base_url('auth/change-password')) ?>" class="grid gap-5">
                    <?= csrf_field() ?>

                    <label class="grid gap-2.5">
                        <span class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-500">Current Password</span>
                        <input type="password" name="current_password" autocomplete="current-password" placeholder="Enter your current password" class="h-14 w-full rounded-[20px] border border-slate-200 bg-white px-4 text-[16px] font-semibold text-slate-800 outline-none transition focus:border-emerald-300 focus:ring-4 focus:ring-emerald-100" required>
                    </label>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <label class="grid gap-2.5">
                            <span class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-500">New Password</span>
                            <input type="password" name="password" minlength="6" autocomplete="new-password" placeholder="Create new password" class="h-14 w-full rounded-[20px] border border-slate-200 bg-white px-4 text-[16px] font-semibold text-slate-800 outline-none transition focus:border-emerald-300 focus:ring-4 focus:ring-emerald-100" required>
                        </label>

                        <label class="grid gap-2.5">
                            <span class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-500">Confirm Password</span>
                            <input type="password" name="confirm_password" minlength="6" autocomplete="new-password" placeholder="Confirm new password" class="h-14 w-full rounded-[20px] border border-slate-200 bg-white px-4 text-[16px] font-semibold text-slate-800 outline-none transition focus:border-emerald-300 focus:ring-4 focus:ring-emerald-100" required>
                        </label>
                    </div>

                    <div class="rounded-[18px] border border-emerald-100 bg-emerald-50 px-4 py-3 text-xs font-semibold leading-5 text-emerald-800">
                        Your new password must contain at least 6 characters and must be different from your current password.
                    </div>

                    <button type="submit" class="inline-flex h-14 w-full items-center justify-center rounded-[22px] bg-[linear-gradient(135deg,#0f172a,#1e293b,#111827)] px-5 text-sm font-black text-white shadow-[0_18px_38px_rgba(15,23,42,0.22)] transition hover:-translate-y-[1px] sm:w-auto sm:px-8">
                        Change Password
                    </button>
                </form>
            </div>
        </div>
    </section>
</div>
