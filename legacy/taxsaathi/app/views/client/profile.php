<?php
declare(strict_types=1);

$userData = is_array($userData ?? null) ? $userData : [];
$client   = is_array($client ?? null) ? $client : [];

$name = (string) old(
    'name',
    $userData['name']
        ?? $userData['full_name']
        ?? $client['name']
        ?? $client['full_name']
        ?? ''
);

$email = (string) old('email', $userData['email'] ?? $client['email'] ?? '');
$phone = (string) (
    $userData['phone']
    ?? $userData['mobile']
    ?? $client['phone']
    ?? $client['mobile']
    ?? ''
);

$clientType  = (string) old('client_type', $client['client_type'] ?? 'individual');
$companyName = (string) old('company_name', $client['company_name'] ?? '');
$gstNumber   = (string) old('gst_number', $client['gst_number'] ?? '');
$panNumber   = (string) old('pan_number', $client['pan_number'] ?? '');
$city        = (string) old('city', $client['city'] ?? '');
$status      = strtolower(trim((string) ($client['status'] ?? 'active')));

$isPhoneVerified = (int) ($userData['is_phone_verified'] ?? 0) === 1;
$isEmailVerified = (int) ($userData['is_email_verified'] ?? 0) === 1;

$createdAt = (string) ($userData['created_at'] ?? $client['created_at'] ?? '');
$lastLogin = (string) ($userData['last_login_at'] ?? '');

$displayName = trim($name) !== '' ? trim($name) : 'My Profile';
$initialSource = trim($name) !== '' ? trim($name) : 'U';
$initial = strtoupper(function_exists('mb_substr') ? mb_substr($initialSource, 0, 1) : substr($initialSource, 0, 1));

$formatDate = static function (?string $value, string $format = 'd M Y'): string {
    $value = trim((string) $value);

    if ($value === '') {
        return 'Not available';
    }

    $timestamp = strtotime($value);

    return $timestamp !== false ? date($format, $timestamp) : 'Not available';
};

$profileFields = [
    trim($name),
    trim($email),
    trim($phone),
    trim($panNumber),
    trim($city),
];

if (in_array($clientType, ['business', 'company'], true)) {
    $profileFields[] = trim($companyName);
    $profileFields[] = trim($gstNumber);
}

$completedFields = count(array_filter($profileFields, static fn (string $value): bool => $value !== ''));
$totalFields = max(1, count($profileFields));
$profileCompletion = (int) round(($completedFields / $totalFields) * 100);
$profileCompletion = max(0, min(100, $profileCompletion));

$statusLabel = $status !== '' ? ucwords(str_replace(['_', '-'], ' ', $status)) : 'Unknown';
$statusBadgeClass = $status === 'active'
    ? 'border-emerald-300/30 bg-emerald-400/10 text-emerald-100'
    : 'border-amber-300/30 bg-amber-400/10 text-amber-100';

$inputClass = 'h-12 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 hover:border-slate-300 focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500';
$labelClass = 'text-[11px] font-black uppercase tracking-[0.16em] text-slate-500';
$cardClass = 'overflow-hidden rounded-[26px] border border-slate-200/80 bg-white shadow-[0_18px_55px_rgba(15,23,42,0.06)]';
?>

<section class="relative isolate overflow-hidden rounded-[28px] bg-slate-50/80 p-3 sm:p-5 lg:p-6">
    <div class="pointer-events-none absolute inset-0 -z-10 overflow-hidden">
        <div class="absolute -right-24 -top-24 h-72 w-72 rounded-full bg-emerald-200/30 blur-3xl"></div>
        <div class="absolute -bottom-28 -left-24 h-80 w-80 rounded-full bg-sky-200/25 blur-3xl"></div>
    </div>

    <div class="mx-auto w-full max-w-[1480px] space-y-5 sm:space-y-6">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <a
                href="<?= e(base_url('dashboard')) ?>"
                aria-label="Back to dashboard"
                class="group inline-flex w-fit min-h-12 items-center gap-3 rounded-2xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm font-black text-slate-800 shadow-[0_10px_28px_rgba(15,23,42,0.07)] transition duration-300 hover:-translate-y-0.5 hover:border-emerald-200 hover:text-emerald-700 hover:shadow-[0_16px_36px_rgba(15,23,42,0.11)] focus:outline-none focus:ring-4 focus:ring-emerald-100 sm:px-4"
            >
                <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-950 text-white shadow-lg shadow-slate-950/15 transition duration-300 group-hover:-translate-x-0.5 group-hover:bg-emerald-600">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 18l-6-6 6-6" />
                    </svg>
                </span>
                <span class="leading-tight">
                    <span class="block text-[10px] font-black uppercase tracking-[0.16em] text-slate-400 transition group-hover:text-emerald-500">Navigation</span>
                    <span class="mt-0.5 block">Back to Dashboard</span>
                </span>
            </a>

            <div class="flex items-center gap-2 text-xs font-semibold text-slate-500">
                <span class="inline-flex h-2.5 w-2.5 rounded-full bg-emerald-500 ring-4 ring-emerald-100"></span>
                Secure profile workspace
            </div>
        </header>

        <section class="relative overflow-hidden rounded-[30px] border border-white/10 bg-[linear-gradient(135deg,#020617_0%,#0f172a_42%,#064e3b_100%)] px-5 py-6 shadow-[0_26px_80px_rgba(2,6,23,0.26)] sm:px-7 sm:py-8 lg:px-9">
            <div class="pointer-events-none absolute inset-0">
                <div class="absolute -right-14 -top-20 h-64 w-64 rounded-full bg-emerald-400/20 blur-3xl"></div>
                <div class="absolute -bottom-24 left-1/3 h-72 w-72 rounded-full bg-cyan-400/10 blur-3xl"></div>
                <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-white/50 to-transparent"></div>
                <div class="absolute right-8 top-8 hidden h-24 w-24 rounded-full border border-white/10 lg:block"></div>
                <div class="absolute right-16 top-16 hidden h-12 w-12 rounded-full border border-white/10 lg:block"></div>
            </div>

            <div class="relative z-10 grid gap-7 xl:grid-cols-[minmax(0,1fr)_420px] xl:items-center">
                <div class="flex min-w-0 flex-col gap-5 sm:flex-row sm:items-center">
                    <div class="relative shrink-0">
                        <div class="flex h-20 w-20 items-center justify-center rounded-[24px] border border-white/15 bg-white/10 text-3xl font-black text-white shadow-2xl backdrop-blur-xl sm:h-24 sm:w-24 sm:rounded-[28px] sm:text-4xl">
                            <?= e($initial) ?>
                        </div>
                        <span class="absolute -bottom-1 -right-1 inline-flex h-7 w-7 items-center justify-center rounded-full border-4 border-slate-900 bg-emerald-500 text-white">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="h-3.5 w-3.5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6" />
                            </svg>
                        </span>
                    </div>

                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex rounded-full border border-emerald-300/30 bg-emerald-400/10 px-3 py-1 text-[10px] font-black uppercase tracking-[0.18em] text-emerald-100">
                                Client Profile
                            </span>
                            <span class="inline-flex rounded-full border px-3 py-1 text-[10px] font-black uppercase tracking-[0.16em] <?= e($statusBadgeClass) ?>">
                                <?= e($statusLabel) ?>
                            </span>
                        </div>

                        <h1 class="mt-3 truncate text-3xl font-black tracking-[-0.045em] text-white sm:text-4xl lg:text-5xl">
                            <?= e($displayName) ?>
                        </h1>

                        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-300 sm:text-[15px]">
                            Keep your identity, tax information, contact details and account security up to date.
                        </p>

                        <div class="mt-4 flex flex-wrap gap-x-5 gap-y-2 text-xs font-semibold text-slate-300">
                            <span class="inline-flex items-center gap-2">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4 text-emerald-300" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4h16v16H4z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4 6 8 6 8-6" />
                                </svg>
                                <?= e($email !== '' ? $email : 'Email not added') ?>
                            </span>
                            <span class="inline-flex items-center gap-2">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4 text-emerald-300" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.79 19.79 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.12.9.33 1.78.62 2.63a2 2 0 0 1-.45 2.11L8 9.73a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.85.29 1.73.5 2.63.62A2 2 0 0 1 22 16.92z" />
                                </svg>
                                <?= e($phone !== '' ? $phone : 'Mobile not added') ?>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
                    <div class="rounded-[22px] border border-white/10 bg-white/[0.08] p-4 backdrop-blur-xl">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Profile completion</p>
                                <p class="mt-1 text-2xl font-black text-white"><?= e((string) $profileCompletion) ?>%</p>
                            </div>
                            <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-400/15 text-emerald-200 ring-1 ring-emerald-300/20">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3a9 9 0 1 0 9 9" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l3 2" />
                                </svg>
                            </span>
                        </div>
                        <div class="mt-3 h-2 overflow-hidden rounded-full bg-white/10">
                            <div class="h-full rounded-full bg-gradient-to-r from-emerald-400 to-teal-300" style="width: <?= e((string) $profileCompletion) ?>%"></div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="rounded-[20px] border border-white/10 bg-white/[0.08] p-4 backdrop-blur-xl">
                            <p class="text-[10px] font-black uppercase tracking-[0.15em] text-slate-400">Client type</p>
                            <p class="mt-2 truncate text-sm font-black text-white"><?= e(ucfirst($clientType)) ?></p>
                        </div>
                        <div class="rounded-[20px] border border-white/10 bg-white/[0.08] p-4 backdrop-blur-xl">
                            <p class="text-[10px] font-black uppercase tracking-[0.15em] text-slate-400">Member since</p>
                            <p class="mt-2 truncate text-sm font-black text-white"><?= e($formatDate($createdAt, 'M Y')) ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <div class="grid gap-5 xl:grid-cols-[minmax(0,1.45fr)_minmax(330px,0.72fr)] xl:items-start">
            <main class="order-1 space-y-5">
                <section class="<?= e($cardClass) ?>">
                    <div class="flex flex-col gap-4 border-b border-slate-100 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                        <div>
                            <div class="flex items-center gap-3">
                                <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M20 21a8 8 0 0 0-16 0" />
                                        <circle cx="12" cy="7" r="4" />
                                    </svg>
                                </span>
                                <div>
                                    <h2 class="text-lg font-black tracking-[-0.025em] text-slate-950 sm:text-xl">Profile Information</h2>
                                    <p class="mt-0.5 text-sm text-slate-500">Identity, contact and tax profile details.</p>
                                </div>
                            </div>
                        </div>

                        <span class="inline-flex w-fit items-center gap-2 rounded-full bg-slate-100 px-3 py-1.5 text-[11px] font-black uppercase tracking-[0.13em] text-slate-600">
                            <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                            Editable profile
                        </span>
                    </div>

                    <form method="post" action="<?= e(base_url('client/profile/update')) ?>" class="divide-y divide-slate-100">
                        <?= csrf_field() ?>

                        <div class="grid gap-5 px-5 py-6 sm:px-6 lg:grid-cols-2">
                            <div class="lg:col-span-2">
                                <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-400">Personal identity</p>
                                <p class="mt-1 text-sm text-slate-500">Use details that match your official records.</p>
                            </div>

                            <label class="grid gap-2 lg:col-span-2">
                                <span class="<?= e($labelClass) ?>">Full Name <span class="text-rose-500">*</span></span>
                                <div class="relative">
                                    <span class="pointer-events-none absolute inset-y-0 left-0 flex w-12 items-center justify-center text-slate-400">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4.5 w-4.5" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M20 21a8 8 0 0 0-16 0" />
                                            <circle cx="12" cy="7" r="4" />
                                        </svg>
                                    </span>
                                    <input type="text" name="name" value="<?= e($name) ?>" autocomplete="name" class="<?= e($inputClass) ?> pl-12" placeholder="Enter your full legal name" required>
                                </div>
                            </label>

                            <label class="grid gap-2">
                                <span class="<?= e($labelClass) ?>">Email Address <span class="text-rose-500">*</span></span>
                                <div class="relative">
                                    <span class="pointer-events-none absolute inset-y-0 left-0 flex w-12 items-center justify-center text-slate-400">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4.5 w-4.5" aria-hidden="true">
                                            <rect x="3" y="5" width="18" height="14" rx="2" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m3 7 9 6 9-6" />
                                        </svg>
                                    </span>
                                    <input type="email" name="email" value="<?= e($email) ?>" autocomplete="email" class="<?= e($inputClass) ?> pl-12" placeholder="name@example.com" required>
                                </div>
                            </label>

                            <label class="grid gap-2">
                                <span class="<?= e($labelClass) ?>">Mobile Number</span>
                                <div class="relative">
                                    <span class="pointer-events-none absolute inset-y-0 left-0 flex w-12 items-center justify-center text-slate-400">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4.5 w-4.5" aria-hidden="true">
                                            <rect x="5" y="2" width="14" height="20" rx="2" />
                                            <path stroke-linecap="round" d="M9 18h6" />
                                        </svg>
                                    </span>
                                    <input type="text" name="phone" value="<?= e($phone) ?>" class="<?= e($inputClass) ?> pl-12 pr-12" placeholder="Mobile number" readonly aria-describedby="mobile-lock-note">
                                    <span class="pointer-events-none absolute inset-y-0 right-0 flex w-12 items-center justify-center text-emerald-600">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4.5 w-4.5" aria-hidden="true">
                                            <rect x="5" y="11" width="14" height="9" rx="2" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 11V7a4 4 0 0 1 8 0v4" />
                                        </svg>
                                    </span>
                                </div>
                                <span id="mobile-lock-note" class="text-xs leading-5 text-slate-400">Protected because this number is connected to your verified account.</span>
                            </label>
                        </div>

                        <div class="grid gap-5 px-5 py-6 sm:px-6 lg:grid-cols-2">
                            <div class="lg:col-span-2">
                                <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-400">Business & tax details</p>
                                <p class="mt-1 text-sm text-slate-500">Optional for individuals; recommended for businesses and companies.</p>
                            </div>

                            <label class="grid gap-2">
                                <span class="<?= e($labelClass) ?>">Client Type</span>
                                <div class="relative">
                                    <select name="client_type" class="<?= e($inputClass) ?> appearance-none pr-11">
                                        <option value="individual" <?= $clientType === 'individual' ? 'selected' : '' ?>>Individual</option>
                                        <option value="business" <?= $clientType === 'business' ? 'selected' : '' ?>>Business</option>
                                        <option value="company" <?= $clientType === 'company' ? 'selected' : '' ?>>Company</option>
                                    </select>
                                    <span class="pointer-events-none absolute inset-y-0 right-0 flex w-11 items-center justify-center text-slate-400">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                                        </svg>
                                    </span>
                                </div>
                            </label>

                            <label class="grid gap-2">
                                <span class="<?= e($labelClass) ?>">Company Name</span>
                                <input type="text" name="company_name" value="<?= e($companyName) ?>" autocomplete="organization" class="<?= e($inputClass) ?>" placeholder="Registered business or company name">
                            </label>

                            <label class="grid gap-2">
                                <span class="<?= e($labelClass) ?>">PAN Number</span>
                                <input type="text" name="pan_number" value="<?= e($panNumber) ?>" maxlength="10" autocomplete="off" spellcheck="false" class="<?= e($inputClass) ?> uppercase tracking-[0.08em]" placeholder="ABCDE1234F">
                                <span class="text-xs leading-5 text-slate-400">10-character permanent account number.</span>
                            </label>

                            <label class="grid gap-2">
                                <span class="<?= e($labelClass) ?>">GST Number</span>
                                <input type="text" name="gst_number" value="<?= e($gstNumber) ?>" maxlength="15" autocomplete="off" spellcheck="false" class="<?= e($inputClass) ?> uppercase tracking-[0.08em]" placeholder="22ABCDE1234F1Z5">
                                <span class="text-xs leading-5 text-slate-400">15-character GSTIN, when applicable.</span>
                            </label>

                            <label class="grid gap-2 lg:col-span-2">
                                <span class="<?= e($labelClass) ?>">City</span>
                                <div class="relative">
                                    <span class="pointer-events-none absolute inset-y-0 left-0 flex w-12 items-center justify-center text-slate-400">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4.5 w-4.5" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z" />
                                            <circle cx="12" cy="10" r="2.5" />
                                        </svg>
                                    </span>
                                    <input type="text" name="city" value="<?= e($city) ?>" autocomplete="address-level2" class="<?= e($inputClass) ?> pl-12" placeholder="Enter your city">
                                </div>
                            </label>
                        </div>

                        <div class="flex flex-col gap-4 bg-slate-50/80 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                            <div class="flex items-start gap-3 text-xs leading-5 text-slate-500">
                                <span class="mt-0.5 inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-xl bg-white text-emerald-700 ring-1 ring-slate-200">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m9 12 2 2 4-4" />
                                    </svg>
                                </span>
                                <p>Your information is used only for your Tax Saathi account and service processing.</p>
                            </div>

                            <button type="submit" class="group inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-2xl bg-[linear-gradient(135deg,#059669,#0d9488,#16a34a)] px-6 py-3 text-sm font-black text-white shadow-[0_16px_34px_rgba(16,185,129,0.24)] transition duration-300 hover:-translate-y-0.5 hover:shadow-[0_20px_42px_rgba(16,185,129,0.3)] focus:outline-none focus:ring-4 focus:ring-emerald-100 sm:w-auto">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4.5 w-4.5" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 21v-8H7v8M7 3v5h8" />
                                </svg>
                                Save Profile
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4 transition group-hover:translate-x-0.5" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6" />
                                </svg>
                            </button>
                        </div>
                    </form>
                </section>
            </main>

            <aside class="order-2 space-y-5 xl:sticky xl:top-5">
                <section class="<?= e($cardClass) ?>">
                    <div class="border-b border-slate-100 px-5 py-5">
                        <div class="flex items-center gap-3">
                            <span class="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-sky-50 text-sky-700 ring-1 ring-sky-100">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 13h6V3H4v10Zm10 8h6V11h-6v10ZM4 21h6v-4H4v4Zm10-14h6V3h-6v4Z" />
                                </svg>
                            </span>
                            <div>
                                <h2 class="text-base font-black text-slate-950">Account Overview</h2>
                                <p class="mt-0.5 text-xs text-slate-500">Verification and recent activity.</p>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-3 p-5">
                        <div class="flex items-center justify-between gap-4 rounded-2xl border border-slate-100 bg-slate-50/80 p-4">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl <?= $isPhoneVerified ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' ?>">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4.5 w-4.5" aria-hidden="true">
                                        <rect x="5" y="2" width="14" height="20" rx="2" />
                                        <path stroke-linecap="round" d="M9 18h6" />
                                    </svg>
                                </span>
                                <div class="min-w-0">
                                    <p class="text-sm font-black text-slate-900">Phone</p>
                                    <p class="truncate text-xs text-slate-500">OTP verification</p>
                                </div>
                            </div>
                            <span class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.1em] <?= $isPhoneVerified ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' ?>">
                                <?= $isPhoneVerified ? 'Verified' : 'Pending' ?>
                            </span>
                        </div>

                        <div class="flex items-center justify-between gap-4 rounded-2xl border border-slate-100 bg-slate-50/80 p-4">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl <?= $isEmailVerified ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' ?>">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4.5 w-4.5" aria-hidden="true">
                                        <rect x="3" y="5" width="18" height="14" rx="2" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m3 7 9 6 9-6" />
                                    </svg>
                                </span>
                                <div class="min-w-0">
                                    <p class="text-sm font-black text-slate-900">Email</p>
                                    <p class="truncate text-xs text-slate-500">Account confirmation</p>
                                </div>
                            </div>
                            <span class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.1em] <?= $isEmailVerified ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' ?>">
                                <?= $isEmailVerified ? 'Verified' : 'Not verified' ?>
                            </span>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
                                <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-400">Member since</p>
                                <p class="mt-2 text-sm font-black text-slate-900"><?= e($formatDate($createdAt)) ?></p>
                            </div>
                            <div class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
                                <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-400">Last login</p>
                                <p class="mt-2 text-sm font-black text-slate-900"><?= e($formatDate($lastLogin, 'd M Y')) ?></p>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="<?= e($cardClass) ?>">
                    <div class="border-b border-slate-100 px-5 py-5">
                        <div class="flex items-center gap-3">
                            <span class="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-violet-50 text-violet-700 ring-1 ring-violet-100">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5" aria-hidden="true">
                                    <rect x="4" y="10" width="16" height="10" rx="2" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 10V7a4 4 0 0 1 8 0v3" />
                                </svg>
                            </span>
                            <div>
                                <h2 class="text-base font-black text-slate-950">Account Security</h2>
                                <p class="mt-0.5 text-xs text-slate-500">Update your login password.</p>
                            </div>
                        </div>
                    </div>

                    <form method="post" action="<?= e(base_url('client/profile/password')) ?>" class="grid gap-4 p-5" data-password-form>
                        <?= csrf_field() ?>

                        <label class="grid gap-2">
                            <span class="<?= e($labelClass) ?>">Current Password</span>
                            <div class="relative">
                                <input type="password" name="current_password" autocomplete="current-password" class="<?= e($inputClass) ?> pr-12" placeholder="Enter current password" required data-password-input>
                                <button type="button" class="absolute inset-y-0 right-0 inline-flex w-12 items-center justify-center text-slate-400 transition hover:text-emerald-600 focus:outline-none" aria-label="Show current password" data-password-toggle>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4.5 w-4.5" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" />
                                        <circle cx="12" cy="12" r="3" />
                                    </svg>
                                </button>
                            </div>
                        </label>

                        <label class="grid gap-2">
                            <span class="<?= e($labelClass) ?>">New Password</span>
                            <div class="relative">
                                <input type="password" name="password" autocomplete="new-password" minlength="6" class="<?= e($inputClass) ?> pr-12" placeholder="Minimum 6 characters" required data-password-input>
                                <button type="button" class="absolute inset-y-0 right-0 inline-flex w-12 items-center justify-center text-slate-400 transition hover:text-emerald-600 focus:outline-none" aria-label="Show new password" data-password-toggle>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4.5 w-4.5" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" />
                                        <circle cx="12" cy="12" r="3" />
                                    </svg>
                                </button>
                            </div>
                        </label>

                        <label class="grid gap-2">
                            <span class="<?= e($labelClass) ?>">Confirm Password</span>
                            <div class="relative">
                                <input type="password" name="confirm_password" autocomplete="new-password" minlength="6" class="<?= e($inputClass) ?> pr-12" placeholder="Repeat new password" required data-password-input>
                                <button type="button" class="absolute inset-y-0 right-0 inline-flex w-12 items-center justify-center text-slate-400 transition hover:text-emerald-600 focus:outline-none" aria-label="Show confirm password" data-password-toggle>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4.5 w-4.5" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" />
                                        <circle cx="12" cy="12" r="3" />
                                    </svg>
                                </button>
                            </div>
                        </label>

                        <div class="rounded-2xl border border-amber-100 bg-amber-50 px-4 py-3 text-xs leading-5 text-amber-800">
                            Use a password you do not use on other websites.
                        </div>

                        <button type="submit" class="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-2xl bg-slate-950 px-5 py-3 text-sm font-black text-white shadow-[0_14px_30px_rgba(15,23,42,0.2)] transition hover:-translate-y-0.5 hover:bg-slate-800 focus:outline-none focus:ring-4 focus:ring-slate-200">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4.5 w-4.5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z" />
                            </svg>
                            Update Password
                        </button>
                    </form>
                </section>
            </aside>
        </div>
    </div>
</section>

<script>
(() => {
    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const wrapper = button.parentElement;
            const input = wrapper ? wrapper.querySelector('[data-password-input]') : null;

            if (!input) {
                return;
            }

            const shouldShow = input.type === 'password';
            input.type = shouldShow ? 'text' : 'password';
            button.setAttribute('aria-label', shouldShow ? 'Hide password' : 'Show password');
            button.classList.toggle('text-emerald-600', shouldShow);
        });
    });
})();
</script>