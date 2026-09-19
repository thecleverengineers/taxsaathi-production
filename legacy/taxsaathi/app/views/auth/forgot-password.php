<?php
$pending = is_array($pending ?? null) ? $pending : null;
?>

<section class="relative isolate mx-auto w-full max-w-5xl overflow-hidden rounded-[26px] border border-white/70 bg-[linear-gradient(145deg,rgba(255,255,255,0.97),rgba(248,250,252,0.98),rgba(236,253,245,0.96))] shadow-[0_28px_90px_rgba(15,23,42,0.10)] backdrop-blur-xl sm:rounded-[34px]">
    <div class="pointer-events-none absolute inset-0">
        <div class="absolute -left-20 -top-12 h-60 w-60 rounded-full bg-emerald-200/30 blur-3xl"></div>
        <div class="absolute -right-20 bottom-0 h-64 w-64 rounded-full bg-teal-200/25 blur-3xl"></div>
        <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-emerald-300/70 to-transparent"></div>
    </div>

    <div class="relative z-10 grid lg:grid-cols-[0.9fr_1.1fr]">
        <div class="px-5 py-7 sm:px-8 sm:py-10 lg:px-10 lg:py-12">
            <span class="inline-flex items-center rounded-full border border-emerald-200 bg-white/85 px-4 py-2 text-[10px] font-black uppercase tracking-[0.22em] text-emerald-700 shadow-sm">
                Secure Password Recovery
            </span>

            <h1 class="mt-5 text-[2.15rem] font-black leading-[1.02] tracking-[-0.055em] text-slate-900 sm:text-[2.8rem]">
                Recover your account securely.
            </h1>

            <p class="mt-4 max-w-xl text-sm leading-7 text-slate-600 sm:text-[15px]">
                Enter your registered email address or mobile number. We will send a six-digit OTP to the mobile number linked to your account.
            </p>

            <div class="mt-7 grid gap-3 sm:grid-cols-2 lg:grid-cols-1">
                <div class="rounded-[22px] border border-white/70 bg-white/80 p-4 shadow-[0_16px_40px_rgba(15,23,42,0.05)]">
                    <div class="text-xs font-black uppercase tracking-[0.18em] text-emerald-700">Step 1</div>
                    <div class="mt-2 text-sm font-bold text-slate-900">Identify your account</div>
                    <div class="mt-1 text-xs leading-5 text-slate-500">Use the email or mobile number already registered with Tax Saathi.</div>
                </div>

                <div class="rounded-[22px] border border-white/70 bg-white/80 p-4 shadow-[0_16px_40px_rgba(15,23,42,0.05)]">
                    <div class="text-xs font-black uppercase tracking-[0.18em] text-emerald-700">Step 2</div>
                    <div class="mt-2 text-sm font-bold text-slate-900">Verify OTP and reset</div>
                    <div class="mt-1 text-xs leading-5 text-slate-500">After OTP verification, create a new password immediately.</div>
                </div>
            </div>
        </div>

        <div class="border-t border-white/60 bg-white/70 px-5 py-7 sm:px-8 sm:py-10 lg:border-l lg:border-t-0 lg:px-9 lg:py-12">
            <div class="rounded-[28px] border border-white/80 bg-white/95 p-5 shadow-[0_24px_70px_rgba(15,23,42,0.08)] sm:p-7">
                <div class="flex h-14 w-14 items-center justify-center rounded-[20px] bg-[linear-gradient(135deg,#dcfce7,#a7f3d0)] text-2xl shadow-sm">🔐</div>

                <h2 class="mt-5 text-2xl font-black tracking-[-0.04em] text-slate-900">Forgot your password?</h2>
                <p class="mt-2 text-sm leading-6 text-slate-500">We will verify your registered mobile before allowing a password reset.</p>

                <form method="post" action="<?= e(base_url('auth/forgot-password/send-otp')) ?>" class="mt-6 grid gap-5">
                    <?= csrf_field() ?>

                    <label class="grid gap-2.5">
                        <span class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-500">Email or Mobile Number</span>
                        <input
                            type="text"
                            name="identifier"
                            value="<?= e(old('forgot_identifier')) ?>"
                            autocomplete="username"
                            placeholder="name@example.com or 10 digit mobile"
                            class="h-14 w-full rounded-[20px] border border-slate-200 bg-white px-4 text-[16px] font-semibold text-slate-800 outline-none transition focus:border-emerald-300 focus:ring-4 focus:ring-emerald-100"
                            required
                        >
                    </label>

                    <button
                        type="submit"
                        class="inline-flex h-14 w-full items-center justify-center gap-3 rounded-[22px] bg-[linear-gradient(135deg,#059669,#14b8a6,#16a34a)] px-5 text-sm font-black text-white shadow-[0_18px_38px_rgba(16,185,129,0.24)] transition hover:-translate-y-[1px]"
                    >
                        Send Reset OTP
                        <span aria-hidden="true">→</span>
                    </button>
                </form>

                <?php if ($pending): ?>
                    <a href="<?= e(base_url('auth/forgot-password/verify')) ?>" class="mt-4 inline-flex h-12 w-full items-center justify-center rounded-[18px] border border-amber-200 bg-amber-50 px-4 text-sm font-black text-amber-700 transition hover:bg-amber-100">
                        Continue Existing OTP Verification
                    </a>
                <?php endif; ?>

                <div class="mt-6 border-t border-slate-100 pt-5 text-center">
                    <a href="<?= e(base_url('auth')) ?>" class="text-sm font-bold text-slate-500 transition hover:text-slate-900">← Back to login</a>
                </div>
            </div>
        </div>
    </div>
</section>
