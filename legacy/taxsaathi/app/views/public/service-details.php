<?php
declare(strict_types=1);

$service = is_array($service ?? null) ? $service : [];
$types = is_array($types ?? null) ? array_values($types) : [];
$requirements = is_array($requirements ?? null) ? array_values($requirements) : [];
$benefits = is_array($benefits ?? null) ? array_values($benefits) : [];
$reviews = is_array($reviews ?? null) ? array_values($reviews) : [];

$states = is_array($states ?? null) ? array_values($states) : [];
$applyErrors = is_array($applyErrors ?? null) ? $applyErrors : [];
$applyOld = is_array($applyOld ?? null) ? $applyOld : [];
$applyDefaults = is_array($applyDefaults ?? null) ? $applyDefaults : [];
$applySuccess = trim((string) ($applySuccess ?? ''));
$serviceBanner = is_array($serviceBanner ?? null) ? $serviceBanner : [];

$resolveAssetUrl = static function (?string $path): string {
    $path = trim((string) $path);

    if ($path === '') {
        return '';
    }

    if (preg_match('~^(https?:)?//~i', $path) === 1 || str_starts_with($path, 'data:')) {
        return $path;
    }

    return base_url(ltrim($path, '/'));
};

$serviceTitle = trim((string) ($service['title'] ?? 'Service Details'));
$serviceExcerpt = trim((string) ($service['excerpt'] ?? 'Professional tax and compliance support.'));
$serviceDescription = trim((string) ($service['description'] ?? ''));
$serviceSlug = trim((string) ($service['slug'] ?? ''));
$serviceId = (int) ($service['id'] ?? 0);
$serviceFee = (float) ($service['filing_fee'] ?? 0);
$serviceTurnaround = (int) ($service['turnaround_days'] ?? 0);
$serviceIcon = trim((string) ($service['icon'] ?? 'TS'));

$serviceImage = $resolveAssetUrl(
    (string) (
        $service['thumbnail']
        ?? $service['thumbnail_image']
        ?? $service['image']
        ?? $service['cover_image']
        ?? ''
    )
);

$selectedServiceKey = $serviceSlug !== '' ? $serviceSlug : (string) $serviceId;
$returnUrl = base_url('service-details?service=' . urlencode($selectedServiceKey));
$servicesUrl = base_url('services');
$homeUrl = base_url('');
$backFallbackUrl = $servicesUrl;

$serviceOrderRedirects = [
    'itr-2-filing' => 'https://taxsaathi.in/service-order?service=itr-2-filing',
];

$serviceOrderUrl = $serviceOrderRedirects[$serviceSlug]
    ?? base_url('service-order?service=' . urlencode($selectedServiceKey));

$getItemIcon = static function (array $item, string $fallback = '•'): string {
    $icon = trim((string) ($item['icon'] ?? ''));
    return $icon !== '' ? $icon : $fallback;
};

$getRequirementLabel = static function (array $item): string {
    $candidates = [
        $item['label'] ?? null,
        $item['title'] ?? null,
        $item['document_name'] ?? null,
        $item['name'] ?? null,
        $item['requirement_name'] ?? null,
    ];

    foreach ($candidates as $candidate) {
        $value = trim((string) $candidate);
        if ($value !== '') {
            return $value;
        }
    }

    return 'Required document';
};

$getText = static function (array $item, array $keys, string $fallback = ''): string {
    foreach ($keys as $key) {
        $value = trim((string) ($item[$key] ?? ''));
        if ($value !== '') {
            return $value;
        }
    }

    return $fallback;
};

$getApplyValue = static function (string $key, string $fallback = '') use ($applyOld, $applyDefaults): string {
    $value = $applyOld[$key] ?? $applyDefaults[$key] ?? $fallback;
    return trim((string) $value);
};

$bannerTitle = trim((string) ($serviceBanner['title'] ?? $serviceTitle));
$bannerSubtitle = trim((string) ($serviceBanner['subtitle'] ?? $serviceExcerpt));
$bannerDescription = trim((string) ($serviceBanner['description'] ?? ($serviceDescription !== '' ? $serviceDescription : 'Get expert support, structured documentation, and a smooth assisted filing experience from Tax Saathi.')));
$bannerButtonText = trim((string) ($serviceBanner['button_text'] ?? 'Apply Now'));
$bannerButtonLink = trim((string) ($serviceBanner['button_link'] ?? '#apply-now'));
$bannerImage = $resolveAssetUrl(
    (string) (
        $serviceBanner['image']
        ?? $serviceBanner['banner_image']
        ?? $serviceBanner['image_path']
        ?? ''
    )
);
$bannerBadge = trim((string) ($serviceBanner['badge'] ?? 'Premium Tax Service'));
$heroImage = $bannerImage !== '' ? $bannerImage : $serviceImage;
$bannerPrimaryUrl = $bannerButtonLink !== '' && $bannerButtonLink !== '#' ? $bannerButtonLink : '#apply-now';
?>

<section class="relative min-h-screen overflow-hidden bg-[#f6f8fc]">
    <div aria-hidden="true" class="pointer-events-none absolute inset-0 select-none overflow-hidden">
        <div class="absolute inset-x-0 top-0 h-[520px] bg-gradient-to-br from-[#eef4ff] via-white to-[#fff7ec]"></div>
        <div class="absolute -left-24 top-10 h-80 w-80 rounded-full bg-[#9bb4ff]/30 blur-3xl"></div>
        <div class="absolute -right-24 top-24 h-96 w-96 rounded-full bg-[#ffc780]/30 blur-3xl"></div>
        <div class="absolute left-[14%] top-[42%] h-72 w-72 rounded-full bg-cyan-200/20 blur-3xl"></div>
        <div class="absolute -top-10 left-[-4%] rotate-[-16deg] text-[90px] font-black uppercase tracking-[0.34em] text-white/60 sm:text-[130px]">TAX</div>
        <div class="absolute right-[-4%] top-[20%] rotate-[11deg] text-[78px] font-black uppercase tracking-[0.3em] text-white/55 sm:text-[110px]">GST</div>
        <div class="absolute bottom-[8%] left-[10%] rotate-[-8deg] text-[64px] font-black uppercase tracking-[0.28em] text-white/50 sm:text-[100px]">SAATHI</div>
    </div>

    <div class="relative z-10 mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
        <div class="mb-5 flex flex-col gap-4 rounded-[26px] border border-white/70 bg-white/75 px-4 py-4 shadow-[0_16px_42px_rgba(15,23,42,0.06)] backdrop-blur-xl sm:flex-row sm:items-center sm:justify-between sm:px-5">
            <div class="flex flex-wrap items-center gap-2 text-sm text-slate-500">
                <a href="<?= e($homeUrl) ?>" class="font-semibold transition hover:text-slate-900">Home</a>
                <span class="text-slate-300">/</span>
                <a href="<?= e($servicesUrl) ?>" class="font-semibold transition hover:text-slate-900">Services</a>
                <span class="text-slate-300">/</span>
                <span class="max-w-[260px] truncate font-black text-slate-900 sm:max-w-md"><?= e($serviceTitle) ?></span>
            </div>

            <button
                type="button"
                id="serviceDetailsBackButton"
                data-back-url="<?= e($backFallbackUrl) ?>"
                class="inline-flex w-fit items-center justify-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2.5 text-sm font-black text-slate-700 shadow-sm transition hover:-translate-y-0.5 hover:border-slate-300 hover:bg-slate-50"
            >
                <span class="text-lg leading-none">←</span>
                Back to Service
            </button>
        </div>

        <section class="relative overflow-hidden rounded-[36px] border border-white/75 bg-slate-950 shadow-[0_30px_90px_rgba(15,23,42,0.18)]">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(94,122,196,0.55),transparent_42%),linear-gradient(135deg,#0f172a_0%,#172554_52%,#365486_100%)]"></div>
            <div class="absolute -right-28 -top-28 h-80 w-80 rounded-full bg-blue-400/20 blur-3xl"></div>
            <div class="absolute -bottom-32 left-8 h-96 w-96 rounded-full bg-amber-300/15 blur-3xl"></div>
            <div class="pointer-events-none absolute right-6 top-6 hidden rounded-full border border-white/10 bg-white/5 px-5 py-2 text-[11px] font-black uppercase tracking-[0.22em] text-white/70 lg:inline-flex">
                Tax Saathi
            </div>

            <div class="relative z-10 grid min-h-[470px] gap-8 px-6 py-10 sm:px-8 lg:grid-cols-[1.04fr_0.96fr] lg:px-12 lg:py-12">
                <div class="flex flex-col justify-center">
                    <?php if ($bannerBadge !== ''): ?>
                        <span class="inline-flex w-fit items-center rounded-full border border-white/15 bg-white/10 px-4 py-2 text-[11px] font-black uppercase tracking-[0.24em] text-blue-100 shadow-sm backdrop-blur">
                            <?= e($bannerBadge) ?>
                        </span>
                    <?php endif; ?>

                    <h1 class="mt-5 max-w-3xl text-4xl font-black leading-tight tracking-[-0.055em] text-white sm:text-5xl lg:text-6xl">
                        <?= e($bannerTitle !== '' ? $bannerTitle : $serviceTitle) ?>
                    </h1>

                    <?php if ($bannerSubtitle !== ''): ?>
                        <p class="mt-5 max-w-2xl text-lg font-semibold leading-8 text-blue-50/90">
                            <?= e($bannerSubtitle) ?>
                        </p>
                    <?php endif; ?>

                    <?php if ($bannerDescription !== ''): ?>
                        <p class="mt-4 max-w-2xl text-sm leading-7 text-slate-200/80 sm:text-base sm:leading-8">
                            <?= e($bannerDescription) ?>
                        </p>
                    <?php endif; ?>

                    <div class="mt-8 flex flex-wrap gap-3">
                        <a
                            href="<?= e($bannerPrimaryUrl) ?>"
                            class="inline-flex items-center justify-center rounded-full bg-white px-6 py-3.5 text-sm font-black text-slate-950 shadow-[0_18px_38px_rgba(255,255,255,0.16)] transition hover:-translate-y-0.5 hover:bg-blue-50"
                        >
                            <?= e($bannerButtonText !== '' ? $bannerButtonText : 'Apply Now') ?>
                        </a>

                        <a
                            href="<?= e($serviceOrderUrl) ?>"
                            class="inline-flex items-center justify-center rounded-full border border-white/20 bg-white/10 px-6 py-3.5 text-sm font-black text-white backdrop-blur transition hover:-translate-y-0.5 hover:bg-white/15"
                        >
                            Start Service Order
                        </a>

                        <a
                            href="<?= e($servicesUrl) ?>"
                            class="inline-flex items-center justify-center rounded-full border border-white/15 px-6 py-3.5 text-sm font-black text-white/85 transition hover:-translate-y-0.5 hover:bg-white/10"
                        >
                            Explore Services
                        </a>
                    </div>

                    <div class="mt-9 grid gap-3 sm:grid-cols-3">
                        <div class="rounded-[22px] border border-white/10 bg-white/10 px-4 py-4 backdrop-blur">
                            <div class="text-[11px] font-black uppercase tracking-[0.2em] text-blue-100/80">Starting Fee</div>
                            <div class="mt-1 text-lg font-black text-white"><?= e(format_money($serviceFee)) ?></div>
                        </div>

                        <div class="rounded-[22px] border border-white/10 bg-white/10 px-4 py-4 backdrop-blur">
                            <div class="text-[11px] font-black uppercase tracking-[0.2em] text-blue-100/80">Turnaround</div>
                            <div class="mt-1 text-lg font-black text-white"><?= e((string) $serviceTurnaround) ?> Days</div>
                        </div>

                        <div class="rounded-[22px] border border-white/10 bg-white/10 px-4 py-4 backdrop-blur">
                            <div class="text-[11px] font-black uppercase tracking-[0.2em] text-blue-100/80">Documents</div>
                            <div class="mt-1 text-lg font-black text-white"><?= count($requirements) ?> Required</div>
                        </div>
                    </div>
                </div>
<div class="relative flex items-center justify-center lg:justify-end">
    <div class="relative w-full max-w-[460px] overflow-hidden rounded-[32px] border border-white/15 bg-white/10 p-8 text-center shadow-[0_30px_80px_rgba(0,0,0,0.24)] backdrop-blur">

        <!-- Decorative background -->
        <div class="pointer-events-none absolute -right-16 -top-16 h-52 w-52 rounded-full bg-white/10 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-20 -left-20 h-52 w-52 rounded-full bg-blue-300/15 blur-3xl"></div>

        <div class="relative">
            <!-- Fixed Service Icon -->
            <div class="relative mx-auto flex h-28 w-28 items-center justify-center rounded-[34px] border border-white/70 bg-white text-[#3852B4] shadow-[0_24px_55px_rgba(15,23,42,0.28)]">
                <div class="absolute inset-2 rounded-[27px] border border-blue-100"></div>

                <i
                    class="ri-file-list-3-line relative text-[52px] leading-none"
                    aria-hidden="true"
                ></i>
            </div>

            <!-- Status badge -->
            <div class="mx-auto -mt-3 flex w-fit items-center gap-2 rounded-full border border-white/70 bg-white px-4 py-2 text-[10px] font-black uppercase tracking-[0.14em] text-emerald-700 shadow-lg">
                <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                Service Available
            </div>

            <h2 class="mt-7 text-2xl font-black tracking-[-0.04em] text-white">
                <?= e($serviceTitle) ?>
            </h2>

            <p class="mx-auto mt-3 max-w-[360px] text-sm leading-7 text-blue-50/75">
                A clean, guided service experience built for faster applications and smoother payments.
            </p>

            <div class="mt-7 rounded-[24px] border border-white/70 bg-white/95 p-5 text-left shadow-[0_20px_50px_rgba(15,23,42,0.18)]">
                <div class="flex items-center gap-4">
                    <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-[20px] bg-gradient-to-br from-[#3852B4] to-[#5E7AC4] text-white shadow-lg">
                        <i class="ri-customer-service-2-line text-2xl"></i>
                    </div>

                    <div class="min-w-0">
                        <div class="truncate text-base font-black text-slate-900">
                            <?= e($serviceTitle) ?>
                        </div>

                        <div class="mt-1 text-sm font-semibold text-slate-500">
                            Expert-assisted service workflow
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
            </div>
        </section>

        <div class="mt-8 grid gap-6 xl:grid-cols-[0.92fr_1.08fr]">
            <aside class="grid content-start gap-6">
                <section class="rounded-[32px] border border-white/80 bg-white/90 p-6 shadow-[0_18px_45px_rgba(15,23,42,0.06)] backdrop-blur sm:p-7">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-[11px] font-black uppercase tracking-[0.24em] text-[#3852B4]">Overview</p>
                            <h2 class="mt-2 text-2xl font-black tracking-[-0.04em] text-slate-900">Service Summary</h2>
                        </div>
                      
                    </div>

                    <p class="mt-4 text-sm leading-7 text-slate-600">
                        <?= e($serviceDescription !== '' ? $serviceDescription : $serviceExcerpt) ?>
                    </p>

                    <div class="mt-6 space-y-3">
                        <div class="flex items-center justify-between gap-4 rounded-[20px] border border-slate-200 bg-slate-50/70 px-4 py-4">
                            <span class="text-sm font-bold text-slate-600">Service</span>
                            <strong class="text-right text-sm font-black text-slate-900"><?= e($serviceTitle) ?></strong>
                        </div>
                        <div class="flex items-center justify-between gap-4 rounded-[20px] border border-slate-200 bg-slate-50/70 px-4 py-4">
                            <span class="text-sm font-bold text-slate-600">Starting Fee</span>
                            <strong class="text-base font-black text-[#3852B4]"><?= e(format_money($serviceFee)) ?></strong>
                        </div>
                        <div class="flex items-center justify-between gap-4 rounded-[20px] border border-slate-200 bg-slate-50/70 px-4 py-4">
                            <span class="text-sm font-bold text-slate-600">Turnaround</span>
                            <strong class="text-base font-black text-[#b86a1d]"><?= e((string) $serviceTurnaround) ?> days</strong>
                        </div>
                        <div class="flex items-center justify-between gap-4 rounded-[20px] border border-slate-200 bg-slate-50/70 px-4 py-4">
                            <span class="text-sm font-bold text-slate-600">Reviews</span>
                            <strong class="text-base font-black text-[#3852B4]"><?= count($reviews) ?></strong>
                        </div>
                    </div>

                    <a
                        href="<?= e($serviceOrderUrl) ?>"
                        class="mt-6 inline-flex w-full items-center justify-center rounded-full bg-slate-900 px-6 py-3.5 text-sm font-black text-white shadow-[0_14px_30px_rgba(15,23,42,0.18)] transition hover:-translate-y-0.5 hover:bg-slate-800"
                    >
                        Continue to Service Order
                    </a>
                </section>

                <section class="rounded-[32px] border border-[#f0d3a5] bg-gradient-to-br from-[#fff7ea] to-white p-6 shadow-[0_18px_45px_rgba(180,105,25,0.08)] sm:p-7">
                    <p class="text-[11px] font-black uppercase tracking-[0.24em] text-[#b86a1d]">Documents</p>
                    <h2 class="mt-2 text-2xl font-black tracking-[-0.04em] text-slate-900">Keep these ready</h2>

                    <div class="mt-5 space-y-3">
                        <?php if ($requirements !== []): ?>
                            <?php foreach (array_slice($requirements, 0, 5) as $requirement): ?>
                                <div class="flex items-start gap-3 rounded-[20px] border border-white bg-white/80 px-4 py-4 shadow-sm">
                                    <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#fff0d7] text-sm font-black text-[#b86a1d]">✓</span>
                                    <div>
                                        <h3 class="text-sm font-black text-slate-900"><?= e($getRequirementLabel($requirement)) ?></h3>
                                        <p class="mt-1 text-xs leading-5 text-slate-500">
                                            <?= e($getText($requirement, ['description', 'notes', 'help_text'], 'Required for faster processing.')) ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="rounded-[20px] border border-dashed border-[#e8b265] bg-white/70 px-4 py-5 text-sm font-semibold text-slate-500">
                                Required documents will appear here once configured for this service.
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            </aside>

            <section id="apply-now" class="relative overflow-hidden rounded-[34px] border border-white/80 bg-white/95 p-6 shadow-[0_24px_70px_rgba(15,23,42,0.08)] backdrop-blur sm:p-8">
                <div class="pointer-events-none absolute -right-8 top-3 rotate-[-8deg] text-[62px] font-black uppercase tracking-[0.24em] text-slate-100 sm:text-[86px]">Apply</div>

                <div class="relative z-10 grid gap-8 lg:grid-cols-[0.9fr_1.1fr] lg:items-start">
                    <div>
                        <span class="inline-flex rounded-full border border-[#f0d3a5] bg-[#fff7ea] px-4 py-2 text-[11px] font-black uppercase tracking-[0.24em] text-[#b86a1d]">
                            Ready to proceed
                        </span>

                        <h2 class="mt-5 text-3xl font-black tracking-[-0.045em] text-slate-900 sm:text-4xl">
                            Apply for <?= e($serviceTitle) ?>
                        </h2>

                        <p class="mt-4 text-sm leading-7 text-slate-600 sm:text-base sm:leading-8">
                            Fill in your details below. Once submitted successfully, the application will be saved and the user will be redirected to the payment method.
                        </p>

                        <div class="mt-7 grid gap-3 sm:grid-cols-2">
                            <div class="rounded-[22px] border border-slate-200 bg-slate-50/70 px-4 py-4">
                                <div class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">Selected Service</div>
                                <div class="mt-1 text-sm font-black text-slate-900"><?= e($serviceTitle) ?></div>
                            </div>

                            <div class="rounded-[22px] border border-slate-200 bg-slate-50/70 px-4 py-4">
                                <div class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">Starting Fee</div>
                                <div class="mt-1 text-sm font-black text-[#3852B4]"><?= e(format_money($serviceFee)) ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-[30px] bg-gradient-to-br from-[#3852B4] via-[#5E7AC4] to-[#8fa6e5] p-[1px] shadow-[0_24px_60px_rgba(56,82,180,0.18)]">
                        <div class="rounded-[29px] bg-white p-5 sm:p-6">
                            <?php if ($applySuccess !== ''): ?>
                                <div class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-700">
                                    <?= e($applySuccess) ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($applyErrors['general'])): ?>
                                <div class="mb-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-700">
                                    <?= e($applyErrors['general']) ?>
                                </div>
                            <?php endif; ?>

                            <form action="<?= e(base_url('service-apply')) ?>" method="POST" class="grid gap-4" novalidate>
                                <?= csrf_field() ?>

                                <input type="hidden" name="service_id" value="<?= e((string) $serviceId) ?>">
                                <input type="hidden" name="service_slug" value="<?= e($serviceSlug) ?>">
                                <input type="hidden" name="service_title" value="<?= e($serviceTitle) ?>">
                                <input type="hidden" name="service_fee" value="<?= e((string) $serviceFee) ?>">
                                <input type="hidden" name="return_url" value="<?= e($returnUrl) ?>">
                                <input type="hidden" name="redirect_after" value="payment-method">
                                <input type="hidden" name="next_step" value="payment">

                                <div class="hidden" aria-hidden="true">
                                    <label for="website">Website</label>
                                    <input type="text" name="website" id="website" tabindex="-1" autocomplete="off">
                                </div>

                                <div>
                                    <label for="full_name" class="mb-2 block text-sm font-black text-slate-700">Name</label>
                                    <input
                                        type="text"
                                        id="full_name"
                                        name="full_name"
                                        value="<?= e($getApplyValue('full_name')) ?>"
                                        class="h-14 w-full rounded-2xl border border-slate-200 bg-slate-50/70 px-4 text-sm font-semibold text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-[#5E7AC4] focus:bg-white focus:ring-4 focus:ring-[#5E7AC4]/10"
                                        placeholder="Name"
                                        maxlength="120"
                                        required
                                    >
                                    <?php if (!empty($applyErrors['full_name'])): ?>
                                        <p class="mt-2 text-xs font-bold text-red-600"><?= e($applyErrors['full_name']) ?></p>
                                    <?php endif; ?>
                                </div>

                                <div>
                                    <label for="email" class="mb-2 block text-sm font-black text-slate-700">Email</label>
                                    <input
                                        type="email"
                                        id="email"
                                        name="email"
                                        value="<?= e($getApplyValue('email')) ?>"
                                        class="h-14 w-full rounded-2xl border border-slate-200 bg-slate-50/70 px-4 text-sm font-semibold text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-[#5E7AC4] focus:bg-white focus:ring-4 focus:ring-[#5E7AC4]/10"
                                        placeholder="Email"
                                        maxlength="150"
                                        required
                                    >
                                    <?php if (!empty($applyErrors['email'])): ?>
                                        <p class="mt-2 text-xs font-bold text-red-600"><?= e($applyErrors['email']) ?></p>
                                    <?php endif; ?>
                                </div>

                                <div>
                                    <label for="mobile" class="mb-2 block text-sm font-black text-slate-700">Mobile</label>
                                    <input
                                        type="text"
                                        id="mobile"
                                        name="mobile"
                                        value="<?= e($getApplyValue('mobile')) ?>"
                                        class="h-14 w-full rounded-2xl border border-slate-200 bg-slate-50/70 px-4 text-sm font-semibold text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-[#5E7AC4] focus:bg-white focus:ring-4 focus:ring-[#5E7AC4]/10"
                                        placeholder="Mobile (Without 0 or +91)"
                                        inputmode="numeric"
                                        maxlength="10"
                                        pattern="[6-9][0-9]{9}"
                                        required
                                    >
                                    <p class="mt-2 text-xs font-semibold text-slate-500">Enter 10 digits only. Example: 9876543210</p>
                                    <?php if (!empty($applyErrors['mobile'])): ?>
                                        <p class="mt-2 text-xs font-bold text-red-600"><?= e($applyErrors['mobile']) ?></p>
                                    <?php endif; ?>
                                </div>

                                <div>
                                    <label for="state" class="mb-2 block text-sm font-black text-slate-700">Select State</label>
                                    <select
                                        id="state"
                                        name="state"
                                        class="h-14 w-full rounded-2xl border border-slate-200 bg-slate-50/70 px-4 text-sm font-semibold text-slate-900 outline-none transition focus:border-[#5E7AC4] focus:bg-white focus:ring-4 focus:ring-[#5E7AC4]/10"
                                        required
                                    >
                                        <option value="">Select State</option>
                                        <?php foreach ($states as $stateOption): ?>
                                            <option
                                                value="<?= e((string) $stateOption) ?>"
                                                <?= $getApplyValue('state') === (string) $stateOption ? 'selected' : '' ?>
                                            >
                                                <?= e((string) $stateOption) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (!empty($applyErrors['state'])): ?>
                                        <p class="mt-2 text-xs font-bold text-red-600"><?= e($applyErrors['state']) ?></p>
                                    <?php endif; ?>
                                </div>

                                <button
                                    type="submit"
                                    class="mt-2 inline-flex items-center justify-center rounded-full bg-gradient-to-r from-[#3852B4] to-[#5E7AC4] px-6 py-3.5 text-sm font-black text-white shadow-[0_18px_36px_rgba(56,82,180,0.22)] transition hover:-translate-y-0.5 disabled:cursor-not-allowed disabled:opacity-70"
                                >
                                    Apply Now
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <div class="mt-8 grid gap-6">
            <section class="relative overflow-hidden rounded-[32px] border border-white/80 bg-white/92 p-6 shadow-[0_18px_45px_rgba(15,23,42,0.06)] backdrop-blur sm:p-8">
                <div class="pointer-events-none absolute right-4 top-3 rotate-[-8deg] text-[54px] font-black uppercase tracking-[0.24em] text-slate-100/80 sm:text-[72px]">Types</div>

                <div class="relative z-10 max-w-3xl">
                    <div class="text-[11px] font-black uppercase tracking-[0.24em] text-[#3852B4]">Service Types</div>
                    <h2 class="mt-2 text-2xl font-black tracking-[-0.04em] text-slate-900 sm:text-3xl">Available options under this service</h2>
                </div>

                <div class="relative z-10 mt-7 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <?php if ($types !== []): ?>
                        <?php foreach ($types as $type): ?>
                            <article class="group rounded-[26px] border border-slate-200 bg-gradient-to-br from-white to-slate-50 p-6 shadow-sm transition hover:-translate-y-1 hover:shadow-[0_18px_45px_rgba(15,23,42,0.08)]">
                                <div class="flex h-12 w-12 items-center justify-center rounded-[18px] bg-slate-900 text-xl text-white shadow-[0_12px_26px_rgba(15,23,42,0.16)]">
                                    <i class="<?= e($getItemIcon($type, 'ri-stack-line')) ?>"></i>
                                </div>

                                <h3 class="mt-5 text-lg font-black text-slate-900">
                                    <?= e($getText($type, ['title', 'name'], 'Service Type')) ?>
                                </h3>

                                <p class="mt-2 text-sm leading-7 text-slate-600">
                                    <?= e($getText($type, ['description', 'excerpt'], 'Structured service option with professional support.')) ?>
                                </p>
                            </article>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="rounded-[26px] border border-dashed border-slate-300 bg-slate-50 p-6 text-sm font-semibold text-slate-500 md:col-span-2 xl:col-span-3">
                            Service types will appear here.
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="relative overflow-hidden rounded-[32px] border border-white/80 bg-white/92 p-6 shadow-[0_18px_45px_rgba(15,23,42,0.06)] backdrop-blur sm:p-8">
                <div class="pointer-events-none absolute right-4 top-3 rotate-[-8deg] text-[54px] font-black uppercase tracking-[0.24em] text-slate-100/80 sm:text-[72px]">Docs</div>

                <div class="relative z-10 max-w-3xl">
                    <div class="text-[11px] font-black uppercase tracking-[0.24em] text-[#b86a1d]">Required Documents</div>
                    <h2 class="mt-2 text-2xl font-black tracking-[-0.04em] text-slate-900 sm:text-3xl">What you need before placing the order</h2>
                </div>

                <div class="relative z-10 mt-7 grid gap-4 md:grid-cols-2">
                    <?php if ($requirements !== []): ?>
                        <?php foreach ($requirements as $requirement): ?>
                            <div class="flex items-start gap-4 rounded-[26px] border border-slate-200 bg-gradient-to-br from-white to-[#fffaf2] p-5 shadow-sm">
                                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-[18px] bg-[#fff0d7] text-xl text-[#b86a1d]">
                                    <i class="<?= e($getItemIcon($requirement, 'ri-file-list-3-line')) ?>"></i>
                                </span>

                                <div>
                                    <h3 class="text-base font-black text-slate-900">
                                        <?= e($getRequirementLabel($requirement)) ?>
                                    </h3>

                                    <p class="mt-2 text-sm leading-7 text-slate-600">
                                        <?= e($getText($requirement, ['description', 'notes', 'help_text'], 'Please keep this document ready for a faster and smoother order process.')) ?>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="rounded-[26px] border border-dashed border-slate-300 bg-slate-50 p-6 text-sm font-semibold text-slate-500 md:col-span-2">
                            Required documents will appear here once configured for this service.
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="relative overflow-hidden rounded-[32px] border border-white/80 bg-white/92 p-6 shadow-[0_18px_45px_rgba(15,23,42,0.06)] backdrop-blur sm:p-8">
                <div class="pointer-events-none absolute right-4 top-3 rotate-[-8deg] text-[54px] font-black uppercase tracking-[0.24em] text-slate-100/80 sm:text-[72px]">Benefits</div>

                <div class="relative z-10 max-w-3xl">
                    <div class="text-[11px] font-black uppercase tracking-[0.24em] text-[#3852B4]">Benefits</div>
                    <h2 class="mt-2 text-2xl font-black tracking-[-0.04em] text-slate-900 sm:text-3xl">Why clients choose this service</h2>
                </div>

                <div class="relative z-10 mt-7 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <?php if ($benefits !== []): ?>
                        <?php foreach ($benefits as $benefit): ?>
                            <article class="group rounded-[26px] border border-slate-200 bg-gradient-to-br from-white to-slate-50 p-6 shadow-sm transition hover:-translate-y-1 hover:shadow-[0_18px_45px_rgba(15,23,42,0.08)]">
                                <div class="flex h-12 w-12 items-center justify-center rounded-[18px] bg-gradient-to-br from-[#3852B4] to-[#5E7AC4] text-xl text-white shadow-[0_12px_26px_rgba(56,82,180,0.18)]">
                                    <i class="<?= e($getItemIcon($benefit, 'ri-award-line')) ?>"></i>
                                </div>

                                <h3 class="mt-5 text-lg font-black text-slate-900">
                                    <?= e($getText($benefit, ['title', 'name'], 'Service Benefit')) ?>
                                </h3>

                                <p class="mt-2 text-sm leading-7 text-slate-600">
                                    <?= e($getText($benefit, ['description', 'excerpt'], 'A strong benefit that supports a smoother and more dependable service experience.')) ?>
                                </p>
                            </article>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="rounded-[26px] border border-dashed border-slate-300 bg-slate-50 p-6 text-sm font-semibold text-slate-500 md:col-span-2 xl:col-span-3">
                            Service benefits will appear here once added.
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="relative overflow-hidden rounded-[32px] border border-white/80 bg-white/92 p-6 shadow-[0_18px_45px_rgba(15,23,42,0.06)] backdrop-blur sm:p-8">
                <div class="pointer-events-none absolute right-4 top-3 rotate-[-8deg] text-[54px] font-black uppercase tracking-[0.24em] text-slate-100/80 sm:text-[72px]">Trust</div>

                <div class="relative z-10 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div class="max-w-3xl">
                        <div class="text-[11px] font-black uppercase tracking-[0.24em] text-[#b86a1d]">Reviews</div>
                        <h2 class="mt-2 text-2xl font-black tracking-[-0.04em] text-slate-900 sm:text-3xl">Client feedback for this service</h2>
                    </div>

                    <div class="inline-flex w-fit rounded-full border border-slate-200 bg-slate-50 px-4 py-2 text-sm font-black text-slate-500">
                        <?= count($reviews) ?> Review<?= count($reviews) === 1 ? '' : 's' ?>
                    </div>
                </div>

                <div class="relative z-10 mt-7 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <?php if ($reviews !== []): ?>
                        <?php foreach ($reviews as $review): ?>
                            <?php $rating = max(1, min(5, (int) ($review['rating'] ?? 5))); ?>
                            <article class="rounded-[26px] border border-slate-200 bg-gradient-to-br from-white to-slate-50 p-6 shadow-sm">
                                <div class="text-sm tracking-[0.18em] text-amber-500">
                                    <?= str_repeat('★', $rating) ?>
                                </div>

                                <p class="mt-4 text-sm leading-7 text-slate-600">
                                    <?= e($getText($review, ['quote', 'review', 'comment'], 'Excellent service experience.')) ?>
                                </p>

                                <div class="mt-5 flex items-center gap-3">
                                    <div class="flex h-11 w-11 items-center justify-center rounded-full bg-slate-900 text-sm font-black text-white">
                                        <?= e(strtoupper(substr($getText($review, ['name'], 'Client'), 0, 1))) ?>
                                    </div>
                                    <div>
                                        <strong class="block text-base font-black text-slate-900">
                                            <?= e($getText($review, ['name'], 'Client')) ?>
                                        </strong>

                                        <?php
                                        $designation = trim((string) ($review['designation'] ?? ''));
                                        $company = trim((string) ($review['company'] ?? ''));
                                        $meta = trim($designation . ($designation !== '' && $company !== '' ? ' • ' : '') . $company);
                                        ?>
                                        <?php if ($meta !== ''): ?>
                                            <span class="mt-1 block text-sm font-semibold text-slate-500">
                                                <?= e($meta) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="rounded-[26px] border border-dashed border-slate-300 bg-slate-50 p-6 text-sm font-semibold text-slate-500 md:col-span-2 xl:col-span-3">
                            Client reviews will appear here once published for this service.
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const backButton = document.getElementById('serviceDetailsBackButton');

    if (backButton) {
        backButton.addEventListener('click', function () {
            const fallbackUrl = backButton.getAttribute('data-back-url') || '/';

            if (window.history.length > 1) {
                window.history.back();
                return;
            }

            window.location.href = fallbackUrl;
        });
    }
});
</script>