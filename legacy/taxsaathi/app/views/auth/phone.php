<section class="relative isolate mx-auto w-full max-w-6xl overflow-hidden rounded-[24px] border border-white/70 bg-[linear-gradient(145deg,rgba(255,255,255,0.96),rgba(248,250,252,0.98),rgba(236,253,245,0.96))] shadow-[0_24px_80px_rgba(15,23,42,0.10)] backdrop-blur-xl sm:rounded-[34px]">
    <div class="pointer-events-none absolute inset-0">
        <div class="absolute -left-24 -top-10 h-56 w-56 rounded-full bg-emerald-200/30 blur-3xl"></div>
        <div class="absolute -right-20 top-16 h-64 w-64 rounded-full bg-teal-200/25 blur-3xl"></div>
        <div class="absolute bottom-0 left-1/3 h-44 w-44 rounded-full bg-sky-200/20 blur-3xl"></div>
        <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-emerald-300/70 to-transparent"></div>
    </div>

    <div class="relative z-10 grid gap-0 lg:grid-cols-[0.95fr_1.05fr]">
        <div class="px-4 py-5 sm:px-7 sm:py-8 lg:px-10 lg:py-10">
            <div class="auth-form-head">
                <span class="inline-flex max-w-full items-center gap-2 rounded-full border border-emerald-200/70 bg-white/85 px-3 py-2 text-[9px] font-black uppercase tracking-[0.18em] text-emerald-700 shadow-[0_12px_28px_rgba(16,185,129,0.12)] backdrop-blur-md sm:px-4 sm:text-[10px] sm:tracking-[0.26em]">
                    <span class="relative flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-[linear-gradient(135deg,#22c55e,#14b8a6,#16a34a)] shadow-[0_10px_22px_rgba(16,185,129,0.35)]">
                        <span class="h-2.5 w-2.5 rounded-full bg-white"></span>
                        <span class="absolute inset-0 rounded-full ring-4 ring-emerald-300/30"></span>
                    </span>
                    <span class="truncate">Secure Account Access</span>
                </span>

                <h2 class="mt-5 max-w-2xl text-[2rem] font-black leading-[0.98] tracking-[-0.055em] text-slate-900 sm:mt-6 sm:text-[2.55rem] lg:text-[2.85rem]">
                    Login or
                    <span class="bg-[linear-gradient(135deg,#059669,#14b8a6,#16a34a)] bg-clip-text text-transparent">Register</span>
                </h2>

                <p class="mt-3 max-w-xl text-[13px] leading-6 text-slate-600 sm:mt-4 sm:text-[15px] sm:leading-7">
                    Register with your name, email, mobile number and password. Your account will be verified securely through a mobile OTP.
                </p>
            </div>

            <div class="mt-5 grid gap-3 sm:mt-7 sm:grid-cols-2">
                <div class="group rounded-[20px] border border-white/70 bg-white/80 p-3 shadow-[0_16px_40px_rgba(15,23,42,0.05)] backdrop-blur-md sm:rounded-[24px] sm:p-4">
                    <div class="flex items-center gap-3">
                        <div class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-[linear-gradient(135deg,#dcfce7,#a7f3d0)] text-emerald-700 sm:h-11 sm:w-11">
                            📱
                        </div>
                        <div class="min-w-0">
                            <div class="truncate text-sm font-bold text-slate-900">Mobile OTP</div>
                            <div class="text-[12px] leading-5 text-slate-500">One-time SMS verification.</div>
                        </div>
                    </div>
                </div>

                <div class="group rounded-[20px] border border-white/70 bg-white/80 p-3 shadow-[0_16px_40px_rgba(15,23,42,0.05)] backdrop-blur-md sm:rounded-[24px] sm:p-4">
                    <div class="flex items-center gap-3">
                        <div class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-[linear-gradient(135deg,#dbeafe,#bfdbfe)] text-sky-700 sm:h-11 sm:w-11">
                            🔐
                        </div>
                        <div class="min-w-0">
                            <div class="truncate text-sm font-bold text-slate-900">Password Login</div>
                            <div class="text-[12px] leading-5 text-slate-500">Fast sign-in for users.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="border-t border-white/60 bg-[linear-gradient(180deg,rgba(255,255,255,0.92),rgba(248,250,252,0.94))] px-4 py-5 sm:px-7 sm:py-8 lg:border-l lg:border-t-0 lg:px-8 lg:py-10">
            <div class="overflow-hidden rounded-[24px] border border-white/70 bg-white/95 shadow-[0_20px_60px_rgba(15,23,42,0.08)] backdrop-blur-xl sm:rounded-[30px]">
                <div class="border-b border-slate-100 bg-[linear-gradient(180deg,#ffffff_0%,#f8fafc_100%)] px-4 py-4 sm:px-6 sm:py-5">
                    <div class="flex items-center gap-2 rounded-[16px] bg-slate-100 p-1.5 sm:gap-3 sm:rounded-[18px]">
                        <button
                            type="button"
                            id="tab-login"
                            class="flex-1 rounded-[13px] bg-slate-900 px-3 py-3 text-sm font-bold text-white transition active:scale-[0.98] sm:rounded-[14px] sm:px-4"
                        >
                            Login
                        </button>

                        <button
                            type="button"
                            id="tab-register"
                            class="flex-1 rounded-[13px] px-3 py-3 text-sm font-bold text-slate-700 transition active:scale-[0.98] sm:rounded-[14px] sm:px-4"
                        >
                            Signup
                        </button>
                    </div>
                </div>

                <div class="px-4 py-5 sm:px-6 sm:py-6">
                    <form id="login-pane" method="post" action="<?= e(base_url('auth/login')) ?>" class="grid gap-4 sm:gap-5">
                        <?= csrf_field() ?>

                        <label class="grid gap-2">
                            <span class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500 sm:text-[11px] sm:tracking-[0.22em]">Email Address</span>
                            <input
                                class="h-12 w-full rounded-[16px] border border-slate-200 bg-white px-4 text-[16px] font-semibold text-slate-800 outline-none transition focus:border-emerald-300 focus:ring-4 focus:ring-emerald-100 sm:h-14 sm:rounded-[20px] sm:text-[15px]"
                                type="email"
                                name="email"
                                value="<?= e(old('login_email')) ?>"
                                placeholder="Enter your email"
                                autocomplete="email"
                                required
                            >
                        </label>

                        <label class="grid gap-2">
                            <span class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500 sm:text-[11px] sm:tracking-[0.22em]">Password</span>
                            <input
                                class="h-12 w-full rounded-[16px] border border-slate-200 bg-white px-4 text-[16px] font-semibold text-slate-800 outline-none transition focus:border-emerald-300 focus:ring-4 focus:ring-emerald-100 sm:h-14 sm:rounded-[20px] sm:text-[15px]"
                                type="password"
                                name="password"
                                placeholder="Enter your password"
                                autocomplete="current-password"
                                required
                            >
                        </label>

                        <div class="flex items-center justify-end">
                            <a
                                href="<?= e(base_url('auth/forgot-password')) ?>"
                                class="inline-flex items-center gap-2 text-xs font-black text-emerald-700 transition hover:text-emerald-800"
                            >
                                <span>Forgot password?</span>
                                <span aria-hidden="true">→</span>
                            </a>
                        </div>

                        <button
                            class="group inline-flex h-12 w-full items-center justify-center gap-3 rounded-[18px] bg-[linear-gradient(135deg,#0f172a,#1e293b,#111827)] px-5 text-sm font-bold text-white shadow-[0_16px_32px_rgba(15,23,42,0.22)] transition duration-200 active:scale-[0.98] sm:h-14 sm:rounded-[22px] sm:hover:-translate-y-[1px]"
                            type="submit"
                        >
                            <span>Login</span>
                            <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-white/10 text-base">→</span>
                        </button>
                    </form>

                    <form id="register-pane" method="post" action="<?= e(base_url('auth/register/send-otp')) ?>" class="hidden grid gap-4 sm:gap-5">
                        <?= csrf_field() ?>

                        <label class="grid gap-2">
                            <span class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500 sm:text-[11px] sm:tracking-[0.22em]">Full Name</span>
                            <input
                                class="h-12 w-full rounded-[16px] border border-slate-200 bg-white px-4 text-[16px] font-semibold text-slate-800 outline-none transition focus:border-emerald-300 focus:ring-4 focus:ring-emerald-100 sm:h-14 sm:rounded-[20px] sm:text-[15px]"
                                type="text"
                                name="name"
                                value="<?= e(old('name')) ?>"
                                placeholder="Enter your full name"
                                autocomplete="name"
                                required
                            >
                        </label>

                        <label class="grid gap-2">
                            <span class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500 sm:text-[11px] sm:tracking-[0.22em]">Email Address</span>
                            <input
                                class="h-12 w-full rounded-[16px] border border-slate-200 bg-white px-4 text-[16px] font-semibold text-slate-800 outline-none transition focus:border-emerald-300 focus:ring-4 focus:ring-emerald-100 sm:h-14 sm:rounded-[20px] sm:text-[15px]"
                                type="email"
                                name="email"
                                value="<?= e(old('email')) ?>"
                                placeholder="Enter your email"
                                autocomplete="email"
                                required
                            >
                        </label>

                        <label class="grid gap-2">
                            <span class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500 sm:text-[11px] sm:tracking-[0.22em]">Mobile Number</span>
                            <div class="flex h-12 items-center overflow-hidden rounded-[16px] border border-slate-200 bg-white transition focus-within:border-emerald-300 focus-within:ring-4 focus-within:ring-emerald-100 sm:h-14 sm:rounded-[20px]">
                                <div class="flex h-full shrink-0 items-center border-r border-slate-200 px-3 text-sm font-bold text-slate-700 sm:px-4">+91</div>
                                <input
                                    class="h-full w-full min-w-0 border-0 bg-transparent px-3 text-[16px] font-semibold text-slate-800 outline-none sm:px-4 sm:text-[15px]"
                                    type="tel"
                                    name="phone"
                                    value="<?= e(old('phone')) ?>"
                                    placeholder="10 digit mobile number"
                                    inputmode="numeric"
                                    autocomplete="tel"
                                    minlength="10"
                                    maxlength="10"
                                    pattern="[0-9]{10}"
                                    required
                                >
                            </div>
                        </label>

                        <label class="grid gap-2">
                            <span class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500 sm:text-[11px] sm:tracking-[0.22em]">Password</span>
                            <input
                                class="h-12 w-full rounded-[16px] border border-slate-200 bg-white px-4 text-[16px] font-semibold text-slate-800 outline-none transition focus:border-emerald-300 focus:ring-4 focus:ring-emerald-100 sm:h-14 sm:rounded-[20px] sm:text-[15px]"
                                type="password"
                                name="password"
                                placeholder="Create password"
                                autocomplete="new-password"
                                minlength="6"
                                required
                            >
                        </label>

                        <label class="grid gap-2">
                            <span class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500 sm:text-[11px] sm:tracking-[0.22em]">Confirm Password</span>
                            <input
                                class="h-12 w-full rounded-[16px] border border-slate-200 bg-white px-4 text-[16px] font-semibold text-slate-800 outline-none transition focus:border-emerald-300 focus:ring-4 focus:ring-emerald-100 sm:h-14 sm:rounded-[20px] sm:text-[15px]"
                                type="password"
                                name="confirm_password"
                                placeholder="Confirm password"
                                autocomplete="new-password"
                                minlength="6"
                                required
                            >
                        </label>

                        <button
                            class="group inline-flex h-12 w-full items-center justify-center gap-3 rounded-[18px] bg-[linear-gradient(135deg,#059669,#14b8a6,#16a34a)] px-5 text-sm font-bold text-white shadow-[0_16px_32px_rgba(16,185,129,0.24)] transition duration-200 active:scale-[0.98] sm:h-14 sm:rounded-[22px] sm:hover:-translate-y-[1px]"
                            type="submit"
                        >
                            <span class="truncate">Continue</span>
                            <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-white/10 text-base">→</span>
                        </button>
                    </form>
                </div>
            </div>

            <?php if (!empty($pending)): ?>
                <div class="mt-4 overflow-hidden rounded-[22px] border border-amber-200/80 bg-[linear-gradient(180deg,#fffdf7_0%,#fff7e6_100%)] shadow-[0_14px_34px_rgba(245,158,11,0.10)] sm:mt-5 sm:rounded-[28px]">
                    <div class="flex items-start gap-3 px-4 py-4 sm:gap-4 sm:px-6 sm:py-5">
                        <div class="min-w-0 flex-1">
                            <span class="inline-flex items-center rounded-full border border-amber-200 bg-white/85 px-3 py-1 text-[9px] font-black uppercase tracking-[0.18em] text-amber-700 sm:text-[10px] sm:tracking-[0.2em]">
                                OTP Pending
                            </span>

                            <h3 class="mt-3 text-[1rem] font-black tracking-[-0.03em] text-slate-900 sm:text-[1.05rem]">
                                Registration verification pending
                            </h3>

                            <p class="mt-2 text-sm leading-6 text-slate-600">
                                OTP was sent to
                                <strong class="font-bold text-slate-900">+91 <?= e($pending['fast2sms_mobile'] ?? $pending['phone'] ?? '') ?></strong>.
                            </p>

                            <a
                                class="mt-4 inline-flex h-11 w-full items-center justify-center rounded-[16px] border border-amber-200 bg-white px-5 text-sm font-bold text-slate-800 shadow-sm transition active:scale-[0.98] hover:border-amber-300 hover:text-amber-700 sm:w-auto sm:rounded-[18px]"
                                href="<?= e(base_url('auth/verify')) ?>"
                            >
                                Verify OTP
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const loginBtn = document.getElementById('tab-login');
    const registerBtn = document.getElementById('tab-register');
    const loginPane = document.getElementById('login-pane');
    const registerPane = document.getElementById('register-pane');

    if (!loginBtn || !registerBtn || !loginPane || !registerPane) {
        return;
    }

    function showLogin() {
        loginPane.classList.remove('hidden');
        registerPane.classList.add('hidden');

        loginBtn.classList.add('bg-slate-900', 'text-white');
        loginBtn.classList.remove('text-slate-700');

        registerBtn.classList.remove('bg-slate-900', 'text-white');
        registerBtn.classList.add('text-slate-700');
    }

    function showRegister() {
        registerPane.classList.remove('hidden');
        loginPane.classList.add('hidden');

        registerBtn.classList.add('bg-slate-900', 'text-white');
        registerBtn.classList.remove('text-slate-700');

        loginBtn.classList.remove('bg-slate-900', 'text-white');
        loginBtn.classList.add('text-slate-700');
    }

    loginBtn.addEventListener('click', showLogin);
    registerBtn.addEventListener('click', showRegister);

    <?php if (!empty(old('name')) || !empty(old('email')) || !empty(old('phone'))): ?>
    showRegister();
    <?php else: ?>
    showLogin();
    <?php endif; ?>
});
</script>