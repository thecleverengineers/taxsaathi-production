<?php
$pending = is_array($pending ?? null) ? $pending : [];
$email = trim((string) ($pending['email'] ?? ''));
?>

<section class="relative isolate overflow-hidden rounded-[34px] border border-white/60 bg-[linear-gradient(145deg,rgba(255,255,255,0.96),rgba(248,250,252,0.98),rgba(236,253,245,0.95))] p-5 shadow-[0_30px_100px_rgba(15,23,42,0.10)] backdrop-blur-xl sm:p-8 lg:p-10">
    <div class="pointer-events-none absolute inset-0">
        <div class="absolute -left-20 top-0 h-56 w-56 rounded-full bg-emerald-200/25 blur-3xl"></div>
        <div class="absolute -right-16 bottom-0 h-64 w-64 rounded-full bg-teal-200/20 blur-3xl"></div>
    </div>

    <div class="relative z-10 mx-auto max-w-xl">
        <div class="rounded-[30px] border border-white/70 bg-white/92 p-6 shadow-[0_24px_80px_rgba(15,23,42,0.08)] sm:p-8">
            <div class="text-center">
                <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-4 py-2 text-[10px] font-black uppercase tracking-[0.24em] text-emerald-700">OTP Verified</span>
                <div class="mx-auto mt-5 flex h-16 w-16 items-center justify-center rounded-[22px] bg-[linear-gradient(135deg,#dcfce7,#a7f3d0)] text-3xl">✓</div>
                <h1 class="mt-5 text-[2rem] font-black tracking-[-0.05em] text-slate-900">Create a new password</h1>
                <p class="mt-3 text-sm leading-7 text-slate-600">
                    Choose a secure password<?= $email !== '' ? ' for ' . e($email) : '' ?>. Your previous password will stop working immediately.
                </p>
            </div>

            <form method="post" action="<?= e(base_url('auth/reset-password')) ?>" class="mt-7 grid gap-5">
                <?= csrf_field() ?>

                <label class="grid gap-2.5">
                    <span class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-500">New Password</span>
                    <input type="password" name="password" minlength="6" autocomplete="new-password" placeholder="Create a new password" class="h-14 w-full rounded-[20px] border border-slate-200 bg-white px-4 text-[16px] font-semibold text-slate-800 outline-none focus:border-emerald-300 focus:ring-4 focus:ring-emerald-100" required autofocus>
                </label>

                <label class="grid gap-2.5">
                    <span class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-500">Confirm New Password</span>
                    <input type="password" name="confirm_password" minlength="6" autocomplete="new-password" placeholder="Re-enter your new password" class="h-14 w-full rounded-[20px] border border-slate-200 bg-white px-4 text-[16px] font-semibold text-slate-800 outline-none focus:border-emerald-300 focus:ring-4 focus:ring-emerald-100" required>
                </label>

                <div class="rounded-[18px] border border-slate-200 bg-slate-50 px-4 py-3 text-xs leading-5 text-slate-500">
                    Use at least 6 characters. Avoid reusing your current password.
                </div>

                <button type="submit" class="inline-flex h-14 w-full items-center justify-center rounded-[22px] bg-[linear-gradient(135deg,#059669,#14b8a6,#16a34a)] px-5 text-sm font-black text-white shadow-[0_18px_38px_rgba(16,185,129,0.24)] transition hover:-translate-y-[1px]">
                    Reset Password Securely
                </button>
            </form>
        </div>
    </div>
</section>
