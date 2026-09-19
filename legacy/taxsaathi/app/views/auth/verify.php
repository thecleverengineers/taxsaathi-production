
<?php
//verify.php
$mobile = (string) ($pending['fast2sms_mobile'] ?? $pending['phone'] ?? '');
?>

<section class="relative isolate overflow-hidden rounded-[34px] border border-white/60 bg-[linear-gradient(145deg,rgba(255,255,255,0.96),rgba(248,250,252,0.98),rgba(236,253,245,0.95))] p-6 shadow-[0_30px_100px_rgba(15,23,42,0.10)] backdrop-blur-xl sm:p-8 lg:p-10">
    <div class="pointer-events-none absolute inset-0">
        <div class="absolute -left-20 top-0 h-56 w-56 rounded-full bg-emerald-200/25 blur-3xl"></div>
        <div class="absolute -right-16 bottom-0 h-64 w-64 rounded-full bg-teal-200/20 blur-3xl"></div>
        <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-emerald-300/70 to-transparent"></div>
    </div>

    <div class="relative z-10 mx-auto max-w-xl">
        <div class="rounded-[30px] border border-white/70 bg-white/90 p-6 shadow-[0_24px_80px_rgba(15,23,42,0.08)] backdrop-blur-xl sm:p-8">
            <div class="text-center">
                <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-4 py-2 text-[10px] font-black uppercase tracking-[0.24em] text-emerald-700">
                    Mobile OTP Verification
                </span>

                <h1 class="mt-5 text-[2rem] font-black leading-tight tracking-[-0.05em] text-slate-900">
                    Verify your mobile number
                </h1>

                <p class="mt-3 text-sm leading-7 text-slate-600">
                    Enter the 6 digit OTP sent to
                    <strong class="font-bold text-slate-900">+91 <?= e($mobile) ?></strong>.
                </p>

                <?php if (!empty($pending['debug_otp']) && (bool) config('app.debug', false)): ?>
                    
                <?php endif; ?>
            </div>

            <form method="post" action="<?= e(base_url('auth/verify')) ?>" class="mt-7 grid gap-5">
                <?= csrf_field() ?>

                <label class="grid gap-2.5">
                    <span class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-500">Enter OTP</span>
                    <input
                        class="h-16 w-full rounded-[22px] border border-slate-200 bg-white px-5 text-center text-[24px] font-black tracking-[0.35em] text-slate-900 outline-none focus:border-emerald-300 focus:ring-4 focus:ring-emerald-100"
                        type="text"
                        name="otp"
                        inputmode="numeric"
                        pattern="[0-9]{6}"
                        maxlength="6"
                        placeholder="------"
                        required
                    >
                </label>

                <button
                    class="inline-flex h-14 w-full items-center justify-center rounded-[22px] bg-[linear-gradient(135deg,#059669,#14b8a6,#16a34a)] px-5 text-sm font-bold text-white shadow-[0_18px_38px_rgba(16,185,129,0.24)] transition duration-200 hover:-translate-y-[1px]"
                    type="submit"
                >
                    Verify & Create Account
                </button>
            </form>

            <form method="post" action="<?= e(base_url('auth/register/resend-otp')) ?>" class="mt-4">
                <?= csrf_field() ?>

                <button
                    class="inline-flex h-12 w-full items-center justify-center rounded-[20px] border border-slate-200 bg-white px-5 text-sm font-bold text-slate-700 shadow-sm transition hover:-translate-y-[1px] hover:border-emerald-300 hover:text-emerald-700"
                    type="submit"
                >
                    Resend OTP
                </button>
            </form>

            <div class="mt-5 text-center">
                <a href="<?= e(base_url('auth')) ?>" class="text-sm font-bold text-slate-500 transition hover:text-slate-900">
                    Back to login / signup
                </a>
            </div>
        </div>
    </div>
</section>