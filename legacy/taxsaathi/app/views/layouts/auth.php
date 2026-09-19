<?php
declare(strict_types=1);

$siteName        = trim((string) setting('site_name', 'Tax Saathi'));
$pageTitle       = trim((string) ($title ?? $siteName));
$metaDescription = trim((string) setting('hero_subheading', 'Smart tax filing and compliance services.'));
$sitePhone       = trim((string) setting('site_phone', '+91 70057 27288'));
$siteEmail       = trim((string) setting('site_email', 'hello@taxsaathi.in'));
$siteWhatsapp    = trim((string) setting('site_whatsapp', '+91 70057 27288'));
$contactAddress  = trim((string) setting('contact_address', 'India'));
$whatsappUrl     = 'https://wa.me/' . phone_digits($siteWhatsapp);

$homeUrl         = base_url('/');
$servicesUrl     = base_url('services');
$calculatorsUrl  = base_url('tax-calculators');
$dashboardUrl    = base_url('dashboard');
$profileUrl      = base_url('client/profile');
$clientOrdersUrl = base_url('client/orders');
$authUrl         = base_url('auth');
$logoutUrl       = base_url('logout');

$authUser  = is_logged_in() ? auth_user() : [];
$userName  = trim((string) ($authUser['name'] ?? 'Account'));
$userEmail = trim((string) ($authUser['email'] ?? ''));
$userPhone = trim((string) ($authUser['phone'] ?? ''));
$initial   = strtoupper(substr($userName !== '' ? $userName : 'U', 0, 1));


$resolveSiteAssetUrl = static function (?string $path): string {
    $path = trim((string) $path);

    if ($path === '') {
        return '';
    }

    if (preg_match('~^(https?:)?//~i', $path) === 1 || str_starts_with($path, 'data:')) {
        return $path;
    }

    return base_url(ltrim($path, '/'));
};

$siteLogoRaw = trim((string) setting('site_logo', 'uploads/logo3.png'));
$siteLogoAlt = trim((string) setting('site_logo_alt', $siteName . ' Logo'));
$siteLogoUrl = $resolveSiteAssetUrl($siteLogoRaw !== '' ? $siteLogoRaw : 'uploads/logo3.png');
$siteLogoAlt = $siteLogoAlt !== '' ? $siteLogoAlt : $siteName . ' Logo';
$siteFaviconUrl = $siteLogoUrl !== '' ? $siteLogoUrl : 'data:,';
/* DYNAMIC DATABASE LOGO ENABLED: uses site_logo and site_logo_alt settings. */
?>
<!doctype html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <title><?= e($pageTitle) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= e($metaDescription) ?>">
    <meta name="robots" content="index,follow">
    <meta name="theme-color" content="#3852B4">

    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($metaDescription) ?>">
    <meta property="og:site_name" content="<?= e($siteName) ?>">
    <meta property="og:url" content="<?= e($homeUrl) ?>">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($pageTitle) ?>">
    <meta name="twitter:description" content="<?= e($metaDescription) ?>">

    <link rel="icon" href="<?= e($siteFaviconUrl) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Great+Vibes&family=Lobster&family=Rowdies:wght@300;400;700&family=Trochut:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50: '#eef2ff',
                            100: '#dbe4ff',
                            200: '#b8c8f3',
                            300: '#8ea3df',
                            400: '#5E7AC4',
                            500: '#3852B4',
                            600: '#2f4496',
                            700: '#27377b',
                            800: '#1f2b61',
                            900: '#171f46',
                        },
                        accent: {
                            100: '#fde9cf',
                            200: '#F3BE7A',
                            300: '#f6a756',
                            400: '#F08D39',
                            500: '#d97722',
                        }
                    },
                    boxShadow: {
                        softxl: '0 18px 60px rgba(15, 23, 42, 0.12)',
                        glow: '0 20px 45px rgba(56, 82, 180, 0.18)',
                        warm: '0 18px 40px rgba(240, 141, 57, 0.18)',
                    },
                    backgroundImage: {
                        'luxury-light':
                            'radial-gradient(circle at top left, rgba(94,122,196,0.18), transparent 30%), radial-gradient(circle at top right, rgba(243,190,122,0.22), transparent 28%), linear-gradient(180deg, #f8fbff 0%, #ffffff 46%, #fff9f2 100%)'
                    }
                }
            }
        }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Lilita One&display=swap" rel="stylesheet">
</head>

<body class="min-h-screen bg-luxury-light font-sans text-slate-800 antialiased">

    <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[999] focus:rounded-xl focus:bg-slate-900 focus:px-4 focus:py-3 focus:text-white">
        Skip to content
    </a>

    <div class="relative overflow-hidden">
        <div class="absolute left-[-140px] top-[-140px] -z-10 h-80 w-80 rounded-full bg-brand-200/50 blur-3xl"></div>
        <div class="absolute right-[-120px] top-20 -z-10 h-80 w-80 rounded-full bg-accent-200/50 blur-3xl"></div>
        <div class="absolute bottom-0 left-1/3 -z-10 h-72 w-72 rounded-full bg-brand-100/60 blur-3xl"></div>

        <header class="sticky top-0 z-50 w-full bg-gradient-to-r from-[#0B3C91] via-[#145DA0] to-[#1E81B0] shadow-[0_10px_30px_rgba(11,60,145,0.22)]">
            <div class="w-full border-b border-white/10 bg-transparent">
                <div class="flex w-full items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-10">
                    <a href="<?= e($homeUrl) ?>" class="flex min-w-0 items-center gap-4">
                        <div class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-[18px] bg-white shadow-md border border-gray-200">
    <img 
        src="<?= e($siteLogoUrl) ?>" 
        alt="<?= e($siteLogoAlt) ?>" 
        class="h-full w-full object-contain p-2"
    >
</div>
                        <div class="min-w-0">
  <div class="truncate  flex items-center gap-1 leading-none">
    <span class="font-['Lilita One'] text-2xl sm:text-3xl font-bold text-[#fff]
        [text-shadow:1px_1px_0_#fff,2px_2px_0_#fff,3px_3px_0_#fffff,4px_4px_8px_rgba(0,0,0,0.0)]">
        Tax
    </span>

    <span class="font-['Lilita One'] text-2xl sm:text-3xl font-bold text-[#fff]
        [text-shadow:1px_1px_0_#fff,2px_2px_0_#fff,3px_3px_0_#fffff,4px_4px_8px_rgba(0,0,0,0.0)]">
        Saathi
    </span>
</div>
                                                       <div class="truncate text-[11px] font-bold uppercase tracking-[0.24em] text-blue-100/80">
                                Tax Filing • Compliance • CRM
                            </div>
                        </div>
                    </a>

                    <nav class="hidden items-center gap-2 lg:flex">
                        <a href="<?= e($homeUrl) ?>" class="rounded-full px-4 py-2.5 text-sm font-bold text-white/90 transition hover:bg-white/12 hover:text-white">
                            Home
                        </a>

                        <a href="<?= e($servicesUrl) ?>" class="rounded-full px-4 py-2.5 text-sm font-bold text-white/90 transition hover:bg-white/12 hover:text-white">
                            Services
                        </a>

                        <a href="<?= e($calculatorsUrl) ?>" class="rounded-full px-4 py-2.5 text-sm font-bold text-white/90 transition hover:bg-white/12 hover:text-white">
                            Calculators
                        </a>

                        <?php if (is_logged_in()): ?>
                            <div class="relative ml-2" id="accountDropdownWrap">
                                <button
                                    id="accountDropdownButton"
                                    type="button"
                                    class="inline-flex items-center gap-3 rounded-full border border-white/15 bg-white/10 py-2 pl-2 pr-4 text-sm font-extrabold text-white shadow-sm backdrop-blur-md transition hover:-translate-y-0.5 hover:bg-white/15"
                                    aria-expanded="false"
                                    aria-controls="accountDropdownMenu"
                                >
                                    <span class="flex h-9 w-9 items-center justify-center rounded-full bg-white text-sm font-black text-[#0B3C91] shadow-sm">
                                        <?= e($initial) ?>
                                    </span>

                                    <span class="max-w-[140px] truncate">
                                        <?= e($userName ?: 'My Account') ?>
                                    </span>

                                    <svg id="accountDropdownIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4 transition">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/>
                                    </svg>
                                </button>

                                <div
                                    id="accountDropdownMenu"
                                    class="pointer-events-none absolute right-0 top-[calc(100%+14px)] z-[90] w-[292px] translate-y-2 rounded-[26px] border border-white/70 bg-white/95 p-2 opacity-0 shadow-[0_24px_70px_rgba(15,23,42,0.18)] ring-1 ring-slate-900/5 backdrop-blur-xl transition duration-200"
                                    aria-hidden="true"
                                >
                                    <div class="rounded-[22px] bg-gradient-to-br from-[#0B3C91] via-[#145DA0] to-[#1E81B0] p-4 text-white">
                                        <div class="flex items-center gap-3">
                                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/15 text-base font-black ring-1 ring-white/20">
                                                <?= e($initial) ?>
                                            </div>

                                            <div class="min-w-0">
                                                <div class="truncate text-sm font-black">
                                                    <?= e($userName ?: 'My Account') ?>
                                                </div>

                                                <?php if ($userEmail !== ''): ?>
                                                    <div class="mt-1 truncate text-xs font-semibold text-blue-100/85">
                                                        <?= e($userEmail) ?>
                                                    </div>
                                                <?php elseif ($userPhone !== ''): ?>
                                                    <div class="mt-1 truncate text-xs font-semibold text-blue-100/85">
                                                        <?= e($userPhone) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mt-2 grid gap-1">
                                        <a href="<?= e($dashboardUrl) ?>" class="flex items-center justify-between rounded-[18px] px-4 py-3 text-sm font-extrabold text-slate-700 transition hover:bg-brand-50 hover:text-brand-700">
                                            <span>Dashboard</span>
                                            <span class="text-slate-300">→</span>
                                        </a>

                                        <a href="<?= e($profileUrl) ?>" class="flex items-center justify-between rounded-[18px] px-4 py-3 text-sm font-extrabold text-slate-700 transition hover:bg-brand-50 hover:text-brand-700">
                                            <span>My Profile</span>
                                            <span class="text-slate-300">→</span>
                                        </a>

                                        <a href="<?= e($clientOrdersUrl) ?>" class="flex items-center justify-between rounded-[18px] px-4 py-3 text-sm font-extrabold text-slate-700 transition hover:bg-brand-50 hover:text-brand-700">
                                            <span>My Orders</span>
                                            <span class="text-slate-300">→</span>
                                        </a>

                                        <div class="my-1 h-px bg-slate-100"></div>

                                        <form method="post" action="<?= e($logoutUrl) ?>" class="m-0">
                                            <?= csrf_field() ?>
                                            <button
                                                type="submit"
                                                class="flex w-full items-center justify-between rounded-[18px] px-4 py-3 text-left text-sm font-extrabold text-rose-600 transition hover:bg-rose-50"
                                            >
                                                <span>Logout</span>
                                                <span>↗</span>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <a href="<?= e($servicesUrl) ?>" class="inline-flex items-center justify-center rounded-full border border-white/15 bg-white/10 px-5 py-3 text-sm font-extrabold text-white shadow-sm backdrop-blur-md transition hover:-translate-y-0.5 hover:bg-white/15">
                                Explore Services
                            </a>

                            <a href="<?= e($authUrl) ?>" class="inline-flex items-center justify-center rounded-full bg-white px-5 py-3 text-sm font-extrabold text-[#0B3C91] shadow-[0_10px_24px_rgba(255,255,255,0.18)] transition hover:-translate-y-0.5">
                                Login / Register
                            </a>
                        <?php endif; ?>
                    </nav>

                    <button
                        id="mobileMenuButton"
                        type="button"
                        class="inline-flex h-12 w-12 items-center justify-center rounded-2xl border border-white/15 bg-white/10 text-white shadow-sm backdrop-blur-md lg:hidden"
                        aria-label="Open menu"
                        aria-expanded="false"
                        aria-controls="mobileSidebar"
                    >
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5">
                            <path stroke-linecap="round" d="M4 7h16"/>
                            <path stroke-linecap="round" d="M4 12h16"/>
                            <path stroke-linecap="round" d="M4 17h16"/>
                        </svg>
                    </button>
                </div>
            </div>
        </header>

        <div id="mobileSidebarOverlay" class="pointer-events-none fixed inset-0 z-[70] bg-slate-950/40 opacity-0 transition duration-300 lg:hidden"></div>

        <aside
            id="mobileSidebar"
            class="fixed right-0 top-0 z-[80] flex h-full w-[86%] max-w-[360px] translate-x-full flex-col border-l border-white/30 bg-gradient-to-b from-white via-[#f8fbff] to-[#fff8ef] shadow-[0_20px_60px_rgba(15,23,42,0.22)] transition duration-300 lg:hidden"
            aria-hidden="true"
        >
            <div class="flex items-center justify-between border-b border-brand-100/60 px-5 py-5">
                <div class="flex items-center gap-3">
                    <div class="flex h-12 w-12 items-center justify-center overflow-hidden rounded-2xl bg-gradient-to-br from-[#5E7AC4] via-[#3852B4] to-[#233885] text-sm font-black tracking-[0.18em] text-white shadow-glow">
                        <?php if ($siteLogoUrl !== ''): ?>
                            <img
                                src="<?= e($siteLogoUrl) ?>"
                                alt="<?= e($siteLogoAlt) ?>"
                                class="h-full w-full object-contain p-2"
                                loading="eager"
                                decoding="async"
                            >
                        <?php else: ?>
                            TS
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="text-base font-black text-slate-900"><?= e($siteName) ?></div>
                        <div class="text-[10px] font-bold uppercase tracking-[0.22em] text-brand-600">Menu</div>
                    </div>
                </div>

                <button
                    id="mobileMenuClose"
                    type="button"
                    class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-brand-100 bg-white text-brand-700 shadow-sm"
                    aria-label="Close menu"
                >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5">
                        <path stroke-linecap="round" d="M6 6l12 12"/>
                        <path stroke-linecap="round" d="M18 6L6 18"/>
                    </svg>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto px-5 py-5">
                <?php if (is_logged_in()): ?>
                    <div class="mb-5 rounded-[24px] bg-gradient-to-r from-[#3852B4] to-[#5E7AC4] p-[1px] shadow-glow">
                        <div class="rounded-[23px] bg-white/95 px-4 py-4">
                            <div class="flex items-center gap-3">
                                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-[#0B3C91] to-[#1E81B0] text-base font-black text-white">
                                    <?= e($initial) ?>
                                </div>
                                <div class="min-w-0">
                                    <div class="truncate text-sm font-black text-slate-900">
                                        <?= e($userName ?: 'My Account') ?>
                                    </div>
                                    <div class="mt-1 truncate text-xs font-semibold text-slate-500">
                                        <?= e($userEmail ?: $userPhone ?: 'Client Account') ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="mb-5 rounded-[24px] bg-gradient-to-r from-[#3852B4] to-[#5E7AC4] p-[1px] shadow-glow">
                        <div class="rounded-[23px] bg-white/95 px-4 py-4">
                            <div class="text-[11px] font-bold uppercase tracking-[0.24em] text-brand-600">Contact</div>
                            <div class="mt-3 space-y-2 text-sm font-semibold text-slate-700">
                                <p><a href="tel:<?= e($sitePhone) ?>"><?= e($sitePhone) ?></a></p>
                                <p><a href="mailto:<?= e($siteEmail) ?>"><?= e($siteEmail) ?></a></p>
                                <p><a href="<?= e($whatsappUrl) ?>" target="_blank" rel="noopener" class="text-[#c46f1f]"><?= e($siteWhatsapp) ?></a></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <nav class="grid gap-2">
                    <a href="<?= e($homeUrl) ?>" class="rounded-2xl px-4 py-3 text-sm font-extrabold text-slate-800 transition hover:bg-brand-50 hover:text-brand-700">Home</a>
                    <a href="<?= e($servicesUrl) ?>" class="rounded-2xl px-4 py-3 text-sm font-extrabold text-slate-800 transition hover:bg-brand-50 hover:text-brand-700">Services</a>
                    <a href="<?= e($calculatorsUrl) ?>" class="rounded-2xl px-4 py-3 text-sm font-extrabold text-slate-800 transition hover:bg-brand-50 hover:text-brand-700">Calculators</a>

                    <?php if (is_logged_in()): ?>
                        <a href="<?= e($dashboardUrl) ?>" class="mt-2 rounded-2xl border border-brand-100 bg-white px-4 py-3 text-center text-sm font-extrabold text-brand-700 shadow-sm">
                            Dashboard
                        </a>

                        <a href="<?= e($profileUrl) ?>" class="rounded-2xl border border-brand-100 bg-white px-4 py-3 text-center text-sm font-extrabold text-brand-700 shadow-sm">
                            My Profile
                        </a>

                        <a href="<?= e($clientOrdersUrl) ?>" class="rounded-2xl border border-brand-100 bg-white px-4 py-3 text-center text-sm font-extrabold text-brand-700 shadow-sm">
                            My Orders
                        </a>

                        <form method="post" action="<?= e($logoutUrl) ?>" class="m-0">
                            <?= csrf_field() ?>
                            <button type="submit" class="mt-2 w-full rounded-2xl bg-gradient-to-r from-[#3852B4] to-[#5E7AC4] px-4 py-3 text-sm font-extrabold text-white shadow-glow">
                                Logout
                            </button>
                        </form>
                    <?php else: ?>
                        <a href="<?= e($servicesUrl) ?>" class="mt-2 rounded-2xl border border-accent-200 bg-white px-4 py-3 text-center text-sm font-extrabold text-brand-700 shadow-sm">
                            Explore Services
                        </a>

                        <a href="<?= e($authUrl) ?>" class="mt-2 rounded-2xl bg-gradient-to-r from-[#F08D39] to-[#F3BE7A] px-4 py-3 text-center text-sm font-extrabold text-white shadow-warm">
                            Login / Register
                        </a>
                    <?php endif; ?>
                </nav>
            </div>

            <div class="border-t border-brand-100/60 px-5 py-4">
                <p class="text-xs font-semibold leading-6 text-slate-500">
                    Premium tax, compliance, and advisory experience with a modern client-first interface.
                </p>
            </div>
        </aside>

        <section class="w-full px-4 pt-5 sm:px-6 lg:px-10">
            <?php if ($message = flash('success')): ?>
                <div class="mb-4 rounded-[22px] border border-green-200 bg-green-50 px-5 py-4 text-sm font-bold text-green-800 shadow-sm">
                    <?= e($message) ?>
                </div>
            <?php endif; ?>

            <?php if ($message = flash('warning')): ?>
                <div class="mb-4 rounded-[22px] border border-accent-200 bg-orange-50 px-5 py-4 text-sm font-bold text-[#9a581c] shadow-sm">
                    <?= e($message) ?>
                </div>
            <?php endif; ?>

            <?php if ($message = flash('error')): ?>
                <div class="mb-4 rounded-[22px] border border-rose-200 bg-rose-50 px-5 py-4 text-sm font-bold text-rose-800 shadow-sm">
                    <?= e($message) ?>
                </div>
            <?php endif; ?>
        </section>

        <main id="main-content" class="relative z-10 w-full">
            <?= $content ?>
        </main>

        <footer class="mt-16 w-full px-0 pb-0">
            <div class="w-full overflow-hidden rounded-none border-0 bg-gradient-to-r from-[#0B3C91] via-[#145DA0] to-[#1E81B0] shadow-none">
                <div class="grid gap-10 px-6 py-8 sm:px-8 lg:grid-cols-[1.25fr_0.8fr_0.9fr] lg:px-10 lg:py-10">
                    <div>
                        <div class="flex items-start gap-4">
                         <div class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-[18px] bg-white shadow-md border border-gray-200">
    <img 
        src="<?= e($siteLogoUrl) ?>" 
        alt="<?= e($siteLogoAlt) ?>" 
        class="h-full w-full object-contain p-2"
    >
</div>
                            <div>
         <div class="truncate  flex items-center gap-1 leading-none">
    <span class="font-['Lilita One'] text-2xl sm:text-3xl font-bold text-[#fff]
        [text-shadow:1px_1px_0_#fff,2px_2px_0_#fff,3px_3px_0_#fffff,4px_4px_8px_rgba(0,0,0,0.0)]">
        Tax
    </span>

    <span class="font-['Lilita One'] text-2xl sm:text-3xl font-bold text-[#fff]
        [text-shadow:1px_1px_0_#fff,2px_2px_0_#fff,3px_3px_0_#fffff,4px_4px_8px_rgba(0,0,0,0.0)]">
        Saathi
    </span>
</div>
                           
                           
                                <p class="mt-3 max-w-xl text-sm leading-7 text-blue-100/85">
                                    Dynamic tax filing platform and admin-driven CRM for returns, GST, notices, registrations, and compliance work.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h4 class="text-sm font-extrabold uppercase tracking-[0.22em] text-blue-100/80">Quick Links</h4>
                        <div class="mt-4 space-y-3">
                            <p><a href="<?= e($servicesUrl) ?>" class="text-sm font-semibold text-white/90 transition hover:text-white">Services</a></p>
                            <p><a href="<?= e($calculatorsUrl) ?>" class="text-sm font-semibold text-white/90 transition hover:text-white">Tax Calculators</a></p>

                            <?php if (is_logged_in()): ?>
                                <p><a href="<?= e($dashboardUrl) ?>" class="text-sm font-semibold text-white/90 transition hover:text-white">Dashboard</a></p>
                                <p><a href="<?= e($profileUrl) ?>" class="text-sm font-semibold text-white/90 transition hover:text-white">My Profile</a></p>
                                <p><a href="<?= e($clientOrdersUrl) ?>" class="text-sm font-semibold text-white/90 transition hover:text-white">My Orders</a></p>
                            <?php else: ?>
                                <p><a href="<?= e($authUrl) ?>" class="text-sm font-semibold text-white/90 transition hover:text-white">Client Login</a></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div>
                        <h4 class="text-sm font-extrabold uppercase tracking-[0.22em] text-blue-100/80">Contact</h4>
                        <div class="mt-4 space-y-3 text-sm font-medium text-blue-100/85">
                            <p><?= e($contactAddress) ?></p>
                            <p><a href="tel:<?= e($sitePhone) ?>" class="transition hover:text-white"><?= e($sitePhone) ?></a></p>
                            <p><a href="mailto:<?= e($siteEmail) ?>" class="transition hover:text-white"><?= e($siteEmail) ?></a></p>
                            <p><a href="<?= e($whatsappUrl) ?>" target="_blank" rel="noopener" class="transition hover:text-white"><?= e($siteWhatsapp) ?></a></p>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-3 border-t border-white/10 px-6 py-5 text-sm text-blue-100/75 sm:px-8 lg:flex-row lg:items-center lg:justify-between lg:px-10">
                    <p>© <?= date('Y') ?> <?= e($siteName) ?>. All rights reserved.</p>
                    <p>Built with trust, speed, clarity, and a premium client experience.</p>
                </div>
            </div>
        </footer>
    </div>

    <script>
        (function () {
            const openBtn = document.getElementById('mobileMenuButton');
            const closeBtn = document.getElementById('mobileMenuClose');
            const sidebar = document.getElementById('mobileSidebar');
            const overlay = document.getElementById('mobileSidebarOverlay');

            if (!openBtn || !closeBtn || !sidebar || !overlay) return;

            function openSidebar() {
                sidebar.classList.remove('translate-x-full');
                overlay.classList.remove('opacity-0', 'pointer-events-none');
                overlay.classList.add('opacity-100');
                openBtn.setAttribute('aria-expanded', 'true');
                sidebar.setAttribute('aria-hidden', 'false');
                document.body.classList.add('overflow-hidden');
            }

            function closeSidebar() {
                sidebar.classList.add('translate-x-full');
                overlay.classList.add('opacity-0', 'pointer-events-none');
                overlay.classList.remove('opacity-100');
                openBtn.setAttribute('aria-expanded', 'false');
                sidebar.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('overflow-hidden');
            }

            openBtn.addEventListener('click', openSidebar);
            closeBtn.addEventListener('click', closeSidebar);
            overlay.addEventListener('click', closeSidebar);

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    closeSidebar();
                }
            });
        })();

        (function () {
            const wrap = document.getElementById('accountDropdownWrap');
            const button = document.getElementById('accountDropdownButton');
            const menu = document.getElementById('accountDropdownMenu');
            const icon = document.getElementById('accountDropdownIcon');

            if (!wrap || !button || !menu) return;

            function openDropdown() {
                menu.classList.remove('pointer-events-none', 'opacity-0', 'translate-y-2');
                menu.classList.add('opacity-100', 'translate-y-0');
                button.setAttribute('aria-expanded', 'true');
                menu.setAttribute('aria-hidden', 'false');

                if (icon) {
                    icon.classList.add('rotate-180');
                }
            }

            function closeDropdown() {
                menu.classList.add('pointer-events-none', 'opacity-0', 'translate-y-2');
                menu.classList.remove('opacity-100', 'translate-y-0');
                button.setAttribute('aria-expanded', 'false');
                menu.setAttribute('aria-hidden', 'true');

                if (icon) {
                    icon.classList.remove('rotate-180');
                }
            }

            function toggleDropdown() {
                const isOpen = button.getAttribute('aria-expanded') === 'true';

                if (isOpen) {
                    closeDropdown();
                } else {
                    openDropdown();
                }
            }

            button.addEventListener('click', function (event) {
                event.stopPropagation();
                toggleDropdown();
            });

            document.addEventListener('click', function (event) {
                if (!wrap.contains(event.target)) {
                    closeDropdown();
                }
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    closeDropdown();
                }
            });
        })();
    </script>
    <!-- Floating Call and WhatsApp Buttons -->
<div id="floating-contact-buttons">
    <a
        href="tel:+917576899990"
        class="floating-contact-call"
        aria-label="Call support"
        title="Call support"
    >
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M6.62 10.79a15.46 15.46 0 0 0 6.59 6.59l2.2-2.2a1 1 0 0 1 1.02-.24c1.12.37 2.33.57 3.57.57a1 1 0 0 1 1 1V20a1 1 0 0 1-1 1C10.61 21 3 13.39 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.25.2 2.45.57 3.57a1 1 0 0 1-.25 1.02l-2.2 2.2Z"/>
        </svg>

        <span>Call Now</span>
    </a>

    <a
        href="https://wa.me/917576899990?text=Hello%2C%20I%20need%20assistance%20with%20Tax%20Saathi."
        class="floating-contact-whatsapp"
        target="_blank"
        rel="noopener noreferrer"
        aria-label="Chat on WhatsApp"
        title="Chat on WhatsApp"
    >
        <svg viewBox="0 0 32 32" aria-hidden="true">
            <path d="M16.02 3C8.84 3 3 8.76 3 15.87c0 2.27.6 4.49 1.74 6.44L3 29l6.88-1.79a13.1 13.1 0 0 0 6.14 1.53h.01C23.21 28.74 29 22.98 29 15.88 29 8.77 23.21 3 16.02 3Zm0 23.57h-.01a10.9 10.9 0 0 1-5.54-1.51l-.4-.24-4.08 1.06 1.09-3.93-.26-.4a10.62 10.62 0 0 1-1.65-5.68c0-5.91 4.87-10.71 10.86-10.71 2.9 0 5.63 1.12 7.68 3.15a10.58 10.58 0 0 1 3.18 7.57c0 5.9-4.87 10.69-10.87 10.69Zm5.96-8.01c-.33-.16-1.93-.95-2.23-1.06-.3-.11-.52-.16-.74.16-.22.33-.85 1.06-1.04 1.28-.19.22-.38.25-.71.08-.33-.16-1.38-.51-2.63-1.63-.97-.87-1.63-1.94-1.82-2.26-.19-.33-.02-.5.14-.66.15-.15.33-.38.49-.57.16-.19.22-.33.33-.55.11-.22.05-.41-.03-.57-.08-.16-.74-1.78-1.01-2.43-.27-.64-.54-.55-.74-.56h-.63c-.22 0-.57.08-.87.41-.3.33-1.14 1.12-1.14 2.73s1.17 3.16 1.33 3.38c.16.22 2.31 3.53 5.59 4.95.78.34 1.39.54 1.87.69.78.25 1.49.21 2.05.13.63-.09 1.93-.79 2.2-1.55.27-.76.27-1.41.19-1.55-.08-.14-.3-.22-.63-.38Z"/>
        </svg>

        <span>WhatsApp</span>
    </a>
</div>

<style>
    #floating-contact-buttons {
        position: fixed !important;
        right: 20px !important;
        bottom: 20px !important;
        z-index: 2147483647 !important;
        display: flex !important;
        flex-direction: column !important;
        align-items: flex-end !important;
        gap: 12px !important;
        visibility: visible !important;
        opacity: 1 !important;
        pointer-events: auto !important;
    }

    #floating-contact-buttons a {
        width: 56px !important;
        height: 56px !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        border-radius: 9999px !important;
        color: #ffffff !important;
        text-decoration: none !important;
        overflow: hidden !important;
        border: 2px solid rgba(255, 255, 255, 0.8) !important;
        box-shadow: 0 10px 28px rgba(15, 23, 42, 0.3) !important;
        transition: width 0.25s ease, transform 0.25s ease !important;
    }

    #floating-contact-buttons a:hover {
        width: 145px !important;
        transform: translateY(-3px) !important;
    }

    #floating-contact-buttons svg {
        width: 25px !important;
        height: 25px !important;
        min-width: 25px !important;
        fill: currentColor !important;
    }

    #floating-contact-buttons span {
        width: 0;
        margin-left: 0;
        font-family: Arial, sans-serif;
        font-size: 13px;
        font-weight: 700;
        white-space: nowrap;
        opacity: 0;
        overflow: hidden;
        transition: width 0.25s ease, margin-left 0.25s ease, opacity 0.25s ease;
    }

    #floating-contact-buttons a:hover span {
        width: auto;
        margin-left: 9px;
        opacity: 1;
    }

    .floating-contact-call {
        background: linear-gradient(135deg, #2563eb, #1d4ed8) !important;
    }

    .floating-contact-whatsapp {
        background: linear-gradient(135deg, #25d366, #128c4a) !important;
    }

    @media (max-width: 640px) {
        #floating-contact-buttons {
            right: 14px !important;
            bottom: calc(16px + env(safe-area-inset-bottom)) !important;
        }

        #floating-contact-buttons a,
        #floating-contact-buttons a:hover {
            width: 52px !important;
            height: 52px !important;
            transform: none !important;
        }

        #floating-contact-buttons span {
            display: none !important;
        }
    }
</style>
</body>
</html>