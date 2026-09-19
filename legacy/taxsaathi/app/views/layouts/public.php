<?php
declare(strict_types=1);

$siteSettings = is_array($siteSettings ?? null) ? $siteSettings : [];

$siteName      = trim((string)($siteSettings['site_name'] ?? 'Tax Saathi'));
$footerText    = trim((string)($siteSettings['footer_text'] ?? ('© ' . date('Y') . ' ' . $siteName . '. All rights reserved.')));
$contactPhone  = trim((string)($siteSettings['contact_phone'] ?? '+91 7005727288'));
$contactEmail  = trim((string)($siteSettings['contact_email'] ?? 'support@taxsaathi.com'));
$ctaText       = trim((string)($siteSettings['primary_cta_text'] ?? 'Contact Us'));
$ctaUrl        = trim((string)($siteSettings['primary_cta_url'] ?? '#'));

$siteLogoRaw = trim((string)($siteSettings['site_logo'] ?? ''));
$siteLogoUrl = $siteLogoRaw !== '' ? asset(ltrim($siteLogoRaw, '/')) : '';

$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

$navItems = [
    ['label' => 'Home',     'url' => '/'],
    ['label' => 'Services', 'url' => '/services'],
    ['label' => 'About',    'url' => '/about'],
    ['label' => 'Login',    'url' => '/login'],
    ['label' => 'Register', 'url' => '/register'],
];

$isActiveNav = static function (string $url, string $currentPath): bool {
    if ($url === '/') {
        return $currentPath === '/';
    }
    return strpos($currentPath, $url) === 0;
};
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= e($title ?? ('Authentication – ' . $siteName)) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="data:,">
    <link rel="stylesheet" href="<?= e(asset('/../css/app.css')) ?>">

    <style>
        :root{
            --bg-dark:#091413;
            --bg-deep:#285A48;
            --bg-main:#ffff;
            --bg-soft:#B0E4CC;
            --white:#fff;

            --text-main:#ffffff;
            --text-soft:rgba(255,255,255,.78);
            --text-dark:#091413;
            --border-soft:rgba(176,228,204,.18);
            --border-strong:rgba(176,228,204,.35);
            --shadow-lg:0 30px 80px rgba(0,0,0,.28);
            --shadow-md:0 18px 40px rgba(0,0,0,.18);
        }

.topbar-pill .nav-link.active{
    background: rgba(255,255,255,0.14);
    color: #ffffff;
    box-shadow: inset 0 0 0 1px rgba(255,255,255,0.10);
}

.topbar-pill .nav-cta:hover{
    transform: translateY(-2px);
}

.topbar-pill .mobile-nav-toggle{
    flex-direction: column;
}

        *{
            box-sizing:border-box;
        }

        html,body{
            margin:0;
            padding:0;
        }

        body.auth-portfolio-page{
            min-height:100vh;
            display:flex;
            flex-direction:column;
            background:
                radial-gradient(circle at top left, rgba(176,228,204,.18) 0%, transparent 34%),
                radial-gradient(circle at bottom right, rgba(64,138,113,.22) 0%, transparent 38%),
                linear-gradient(145deg, #B0E4CC 0%, #fff 38%, #fff 72%, #B0E4CC 100%);
            color:var(--text-main);
            position:relative;
            overflow-x:hidden;
        }

        .auth-bg-orb{
            position:fixed;
            border-radius:999px;
            filter:blur(28px);
            pointer-events:none;
            z-index:0;
            opacity:.55;
        }

        .auth-bg-orb-1{
            width:280px;
            height:280px;
            top:-80px;
            left:-70px;
            background:rgba(176,228,204,.16);
        }

        .auth-bg-orb-2{
            width:340px;
            height:340px;
            bottom:-120px;
            right:-90px;
            background:rgba(64,138,113,.22);
        }

        .auth-portfolio-page > *{
            position:relative;
            z-index:1;
        }

        .container{
            margin:0 auto;
        }

        .topbar-wrap{
            padding:0px 0 0;
        }

        .topbar-pill{
            min-height:68px;
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:18px;
          
            border-radius:1px;
            background:rgba(9,20,19,.62);
            border:1px solid var(--border-soft);
            backdrop-filter:blur(18px);
            -webkit-backdrop-filter:blur(18px);
            box-shadow:var(--shadow-md);
        }

        .brand-box{
            display:flex;
            align-items:center;
            gap:12px;
            text-decoration:none;
            color:var(--white);
        }

        .brand-name{
            line-height:1;
            display:flex;
            align-items:center;
            font-size:1.02rem;
            font-weight:800;
            color:var(--white);
            letter-spacing:-0.02em;
        }

        .brand-logo-img,
        .footer-logo-img{
            max-height:42px;
            width:auto;
            display:block;
            object-fit:contain;
        }

        .brand-mark{
            width:42px;
            height:42px;
            border-radius:14px;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            background:linear-gradient(135deg, var(--bg-soft), #d7f3e5);
            color:var(--bg-dark);
            font-weight:900;
            letter-spacing:.08em;
            box-shadow:0 10px 22px rgba(176,228,204,.18);
        }

        .brand-mark.lg{
            width:48px;
            height:48px;
            border-radius:16px;
        }

        .topbar-nav{
            flex:1;
            display:flex;
            align-items:center;
            justify-content:center;
            gap:12px;
            text-align:center;
        }

        .topbar-nav .nav-link{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            min-height:42px;
            padding:0 16px;
            border-radius:999px;
            text-align:center;
            white-space:nowrap;
            color:var(--text-soft);
            text-decoration:none;
            font-weight:700;
            border:1px solid transparent;
            transition:.25s ease;
        }

        .topbar-nav .nav-link:hover{
            color:var(--white);
            background:rgba(176,228,204,.1);
            border-color:rgba(176,228,204,.18);
        }

        .topbar-nav .nav-link.active{
            color:var(--bg-dark);
            background:var(--bg-soft);
            border-color:var(--bg-soft);
            box-shadow:0 10px 24px rgba(176,228,204,.18);
        }

        .nav-cta{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            min-height:46px;
            padding:0 20px;
            border-radius:999px;
            text-align:center;
            white-space:nowrap;
            text-decoration:none;
            font-weight:800;
            color:var(--bg-dark);
            background:linear-gradient(135deg, var(--bg-soft), #ffffff);
            border:1px solid rgba(255,255,255,.38);
            box-shadow:0 16px 30px rgba(176,228,204,.2);
            transition:.25s ease;
        }

        .nav-cta:hover{
            transform:translateY(-1px);
            background:linear-gradient(135deg, #ffffff, var(--bg-soft));
        }

        .mobile-nav-toggle{
            display:none;
            align-items:center;
            justify-content:center;
            flex-direction:column;
            gap:5px;
            border:1px solid rgba(176,228,204,.18);
            background:rgba(176,228,204,.08);
            color:var(--white);
            border-radius:14px;
            width:46px;
            height:46px;
            cursor:pointer;
        }

        .mobile-nav-toggle span{
            display:block;
            width:22px;
            height:2px;
            border-radius:999px;
            background:currentColor;
        }

        main.auth-main{
            flex:1;
            display:flex;
            align-items:center;
            justify-content:center;
            padding:0px 0 0px;
        }

        .auth-hero-grid{
            min-height:calc(100vh - 220px);
            display:grid;
            grid-template-columns:minmax(0, 1fr);
            align-items:center;
            justify-items:center;
        }

        .auth-right-panel{
            width:100%;
            display:flex;
            align-items:center;
            justify-content:center;
        }

        .auth-card,
        .premium-auth-card{
            width:100%;
            max-width:680px;
            margin:0 auto;
            text-align:center;
            padding:32px;
            border-radius:12px;
            background:linear-gradient(180deg, rgba(255,255,255,.96) 0%, rgba(176,228,204,.94) 100%);
            border:1px solid rgba(176,228,204,.45);
            box-shadow:var(--shadow-lg);
            color:var(--text-dark);
        }

        .premium-auth-card form{
            text-align:left;
        }

        .premium-auth-card h1,
        .premium-auth-card h2,
        .premium-auth-card h3,
        .premium-auth-card p:not(form p):not(label span),
        .auth-card h1,
        .auth-card h2,
        .auth-card h3,
        .auth-card p:not(form p):not(label span){
            text-align:center;
            color:var(--bg-dark);
        }

        .premium-auth-card a,
        .auth-card a{
            color:var(--bg-deep);
        }

        .premium-auth-card input,
        .premium-auth-card select,
        .premium-auth-card textarea,
        .auth-card input,
        .auth-card select,
        .auth-card textarea{
            width:100%;
            border-radius:16px;
            border:1px solid rgba(40,90,72,.14);
            background:#fff;
            color:var(--bg-dark);
            padding:14px 16px;
            outline:none;
            box-shadow:none;
            transition:.2s ease;
        }

        .premium-auth-card input:focus,
        .premium-auth-card select:focus,
        .premium-auth-card textarea:focus,
        .auth-card input:focus,
        .auth-card select:focus,
        .auth-card textarea:focus{
            border-color:var(--bg-main);
            box-shadow:0 0 0 4px rgba(64,138,113,.14);
        }

        .premium-auth-card button,
        .premium-auth-card .btn,
        .premium-auth-card input[type="submit"],
        .premium-auth-card input[type="button"],
        .auth-card button,
        .auth-card .btn,
        .auth-card input[type="submit"],
        .auth-card input[type="button"]{
            border:0;
            border-radius:16px;
            min-height:50px;
            padding:0 18px;
            background:linear-gradient(135deg, var(--bg-deep), var(--bg-main));
            color:var(--white);
            font-weight:800;
            cursor:pointer;
            box-shadow:0 16px 28px rgba(40,90,72,.24);
            transition:.25s ease;
        }

        .premium-auth-card button:hover,
        .premium-auth-card .btn:hover,
        .premium-auth-card input[type="submit"]:hover,
        .premium-auth-card input[type="button"]:hover,
        .auth-card button:hover,
        .auth-card .btn:hover,
        .auth-card input[type="submit"]:hover,
        .auth-card input[type="button"]:hover{
            transform:translateY(-1px);
            background:linear-gradient(135deg, var(--bg-main), var(--bg-deep));
        }

        .flash{
            max-width:560px;
            margin:0 auto 16px;
            text-align:center;
            padding:14px 16px;
            border-radius:1px;
            font-weight:700;
            border:1px solid transparent;
        }

        .flash.success{
            background:rgba(64,138,113,.12);
            color:#1d4d3f;
            border-color:rgba(64,138,113,.24);
        }

        .flash.warning{
            background:rgba(176,228,204,.35);
            color:#234739;
            border-color:rgba(40,90,72,.18);
        }

        .flash.danger{
            background:rgba(9,20,19,.08);
            color:#5c1f1f;
            border-color:rgba(9,20,19,.12);
        }

 .site-footer{
    width: 100%;
    margin: 0;
    padding: 0;
    border: 0;
    border-radius: 0;
    box-shadow: none;
}

.site-footer .footer-grid{
    width: 100%;
    max-width: 100%;
    margin: 0;
    padding: 32px 24px;
    display: grid;
    grid-template-columns: 1.2fr 1fr 1fr;
    gap: 24px;
    border: 0;
    border-radius: 0;
    background: linear-gradient(90deg, #0B3C91 0%, #145DA0 50%, #1E81B0 100%);
}

.site-footer .footer-brand-top strong{
    color: #ffffff;
}

.site-footer .footer-brand p,
.site-footer .footer-contact a{
    color: rgba(219, 234, 254, 0.88);
}

.site-footer .footer-links a{
    color: rgba(255,255,255,0.92);
}

.site-footer .footer-links a:hover,
.site-footer .footer-contact a:hover{
    color: #ffffff;
}

.site-footer .brand-mark{
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 44px;
    height: 44px;
    border-radius: 14px;
    background: rgba(255,255,255,0.12);
    color: #ffffff;
    border: 1px solid rgba(255,255,255,0.15);
}

@media (max-width: 900px){
    .site-footer .footer-grid{
        grid-template-columns: 1fr;
    }
}

@media (max-width: 900px){
    .site-footer .footer-grid{
        grid-template-columns: 1fr;
        gap: 20px;
    }
}
        @media (max-width: 992px){
            .topbar-pill{
                border-radius:2px;
                flex-wrap:wrap;
                justify-content:center;
            }

            .brand-box{
                width:100%;
                justify-content:center;
            }

            .topbar-nav{
                order:3;
                width:100%;
                justify-content:center;
                flex-wrap:wrap;
            }

            .nav-cta{
                order:2;
            }

            .footer-grid{
                grid-template-columns:1fr;
                gap:18px;
            }

            .footer-brand,
            .footer-contact{
                justify-self:center;
                text-align:center;
            }

            .footer-brand-top,
            .footer-contact{
                align-items:center;
            }
        }

        @media (max-width: 768px){
            .topbar-pill{
                position:relative;
                justify-content:space-between;
                padding:14px 16px;
                border-radius:24px;
            }

            .brand-box{
                width:auto;
                justify-content:flex-start;
            }

            .mobile-nav-toggle{
                display:inline-flex;
            }

            .topbar-nav{
                display:none;
                width:100%;
                flex-direction:column;
                gap:10px;
                padding-top:10px;
            }

            body.nav-open .topbar-nav{
                display:flex;
            }

            .topbar-nav .nav-link{
                width:100%;
                justify-content:center;
                background:rgba(176,228,204,.06);
                border-color:rgba(176,228,204,.1);
            }

            .nav-cta{
                display:none;
                width:100%;
            }

            body.nav-open .nav-cta{
                display:inline-flex;
                justify-content:center;
            }

            .auth-hero-grid{
                min-height:auto;
            }

            main.auth-main{
                padding:0 0 0;
            }

           

            .footer-grid{
                border-radius:24px;
                padding:20px 18px;
            }
        }
    </style>
</head>
<body class="auth-portfolio-page">

   <header class="topbar-wrap sticky top-0 z-50 w-full bg-gradient-to-r from-[#0B3C91] via-[#145DA0] to-[#1E81B0] shadow-[0_10px_30px_rgba(11,60,145,0.22)]">
   <?php
$navServices = [];

try {
    if (class_exists('\App\Models\Service') && method_exists('\App\Models\Service', 'active')) {
        $navServices = \App\Models\Service::active();
    } elseif (function_exists('app')) {
        $db = app('db');
        $pdo = null;

        if ($db instanceof \PDO) {
            $pdo = $db;
        } elseif (is_object($db)) {
            if (method_exists($db, 'pdo')) {
                $candidate = $db->pdo();
                if ($candidate instanceof \PDO) {
                    $pdo = $candidate;
                }
            } elseif (method_exists($db, 'getPdo')) {
                $candidate = $db->getPdo();
                if ($candidate instanceof \PDO) {
                    $pdo = $candidate;
                }
            } elseif (method_exists($db, 'connection')) {
                $candidate = $db->connection();
                if ($candidate instanceof \PDO) {
                    $pdo = $candidate;
                }
            } elseif (method_exists($db, 'getConnection')) {
                $candidate = $db->getConnection();
                if ($candidate instanceof \PDO) {
                    $pdo = $candidate;
                }
            } elseif (property_exists($db, 'pdo') && $db->pdo instanceof \PDO) {
                $pdo = $db->pdo;
            }
        }

        if ($pdo instanceof \PDO) {
            $stmt = $pdo->prepare("
                SELECT id, title, slug, excerpt, icon, filing_fee, turnaround_days
                FROM services
                WHERE is_active = 1
                ORDER BY sort_order ASC, id ASC
            ");
            $stmt->execute();
            $navServices = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        }
    }
} catch (\Throwable $e) {
    $navServices = [];
}

$navServices = is_array($navServices) ? array_values($navServices) : [];

$serviceDetailsBase = 'service-details?service=';

$serviceMenuUrl = static function (array $item) use ($serviceDetailsBase): string {
    $identifier = trim((string) ($item['slug'] ?? '')) !== ''
        ? (string) $item['slug']
        : (string) ($item['id'] ?? '');

    return base_url($serviceDetailsBase . urlencode($identifier));
};

$isServicesActive =
    $isActiveNav('/services', $currentPath)
    || $isActiveNav('/service-details', $currentPath);
?>

<div class="w-full">
    <nav class="topbar-pill flex items-center justify-between gap-4 border-b border-white/10 bg-transparent px-4 py-3 sm:px-6 lg:px-10">
        <a href="/" class="brand-box flex min-w-0 items-center">
            <img src="/uploads/TaxSaathi.png" alt="<?= e($siteName) ?>" height="790" width="140" class="brand-logo-img">
        </a>

        <button
            class="mobile-nav-toggle inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-white/15 bg-white/10 text-white shadow-sm backdrop-blur-md lg:hidden"
            type="button"
            aria-label="Toggle navigation"
            aria-expanded="false"
            onclick="
                const panel = document.getElementById('mobileTopbarMenu');
                const expanded = this.getAttribute('aria-expanded') === 'true';
                this.setAttribute('aria-expanded', expanded ? 'false' : 'true');
                panel.classList.toggle('hidden');
            "
        >
            <span class="block h-[2px] w-5 rounded-full bg-white"></span>
            <span class="mt-1 block h-[2px] w-5 rounded-full bg-white"></span>
            <span class="mt-1 block h-[2px] w-5 rounded-full bg-white"></span>
        </button>

        <div class="topbar-nav hidden items-center gap-2 lg:flex">
            <a href="/" class="nav-link <?= $isActiveNav('/', $currentPath) ? 'active' : '' ?> rounded-full px-4 py-2.5 text-sm font-bold text-white/90 transition hover:bg-white/12 hover:text-white">
                Home
            </a>

            <div class="group relative">
                <button
                    type="button"
                    class="nav-link <?= $isServicesActive ? 'active' : '' ?> inline-flex items-center gap-2 rounded-full px-4 py-2.5 text-sm font-bold text-white/90 transition hover:bg-white/12 hover:text-white"
                >
                    <span>Services</span>
                    <svg class="h-4 w-4 transition duration-200 group-hover:rotate-180" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.938a.75.75 0 1 1 1.08 1.04l-4.25 4.51a.75.75 0 0 1-1.08 0L5.21 8.27a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd"/>
                    </svg>
                </button>

                <div class="invisible absolute left-0 top-full z-50 mt-3 w-[360px] translate-y-2 opacity-0 transition-all duration-200 group-hover:visible group-hover:translate-y-0 group-hover:opacity-100">
                    <div class="overflow-hidden rounded-3xl border border-white/15 bg-[#0B3C91]/95 p-3 shadow-[0_24px_60px_rgba(0,0,0,0.28)] backdrop-blur-xl">
                        <div class="mb-2 px-3 pt-2 text-[11px] font-extrabold uppercase tracking-[0.24em] text-white/50">
                            Our Services
                        </div>

                        <?php if (!empty($navServices)): ?>
                            <div class="grid gap-1">
                                <?php foreach ($navServices as $navService): ?>
                                    <a
                                        href="<?= e($serviceMenuUrl($navService)) ?>"
                                        class="flex items-start gap-3 rounded-2xl px-3 py-3 text-white/90 transition hover:bg-white/10 hover:text-white"
                                    >
                                        <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border border-white/10 bg-white/10 text-xs font-black text-white shadow-sm">
                                            <?= e(trim((string) ($navService['icon'] ?? 'SRV')) !== '' ? (string) $navService['icon'] : 'SRV') ?>
                                        </span>

                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate text-sm font-extrabold">
                                                <?= e((string) ($navService['title'] ?? 'Service')) ?>
                                            </span>

                                            <?php if (trim((string) ($navService['excerpt'] ?? '')) !== ''): ?>
                                                <span class="mt-1 block text-xs leading-5 text-white/65">
                                                    <?= e((string) $navService['excerpt']) ?>
                                                </span>
                                            <?php endif; ?>

                                            <span class="mt-2 block text-[11px] font-bold uppercase tracking-[0.16em] text-white/50">
                                                Starting ₹<?= e(number_format((float) ($navService['filing_fee'] ?? 0), 2)) ?>
                                                <?php if ((int) ($navService['turnaround_days'] ?? 0) > 0): ?>
                                                    • <?= e((string) ((int) $navService['turnaround_days'])) ?> Day<?= (int) ($navService['turnaround_days'] ?? 0) === 1 ? '' : 's' ?>
                                                <?php endif; ?>
                                            </span>
                                        </span>
                                    </a>
                                <?php endforeach; ?>
                            </div>

                            <div class="mt-2 border-t border-white/10 pt-2">
                                <a
                                    href="/services"
                                    class="flex items-center justify-between rounded-2xl px-3 py-3 text-sm font-extrabold text-white/85 transition hover:bg-white/10 hover:text-white"
                                >
                                    <span>View All Services</span>
                                    <span>→</span>
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-4 text-sm text-white/70">
                                No active services available right now.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <a href="/auth" class="nav-link <?= $isActiveNav('/auth', $currentPath) ? 'active' : '' ?> rounded-full px-4 py-2.5 text-sm font-bold text-white/90 transition hover:bg-white/12 hover:text-white">
                Login
            </a>
        </div>

        <a href="<?= e(asset(ltrim($ctaUrl, '/'))) ?>" class="nav-cta hidden lg:inline-flex items-center justify-center rounded-full bg-white px-5 py-3 text-sm font-extrabold text-[#0B3C91] shadow-[0_10px_24px_rgba(255,255,255,0.18)] transition hover:-translate-y-0.5">
            <?= e($ctaText) ?>
        </a>
    </nav>

    <div id="mobileTopbarMenu" class="hidden border-b border-white/10 bg-[#0B3C91]/95 px-4 pb-4 pt-2 shadow-[0_18px_50px_rgba(0,0,0,0.25)] backdrop-blur-xl lg:hidden sm:px-6">
        <div class="grid gap-2">
            <a
                href="/"
                class="<?= $isActiveNav('/', $currentPath) ? 'bg-white/12 text-white' : 'text-white/90' ?> rounded-2xl px-4 py-3 text-sm font-bold transition hover:bg-white/12 hover:text-white"
            >
                Home
            </a>

            <button
                type="button"
                class="<?= $isServicesActive ? 'bg-white/12 text-white' : 'text-white/90' ?> flex items-center justify-between rounded-2xl px-4 py-3 text-sm font-bold transition hover:bg-white/12 hover:text-white"
                onclick="
                    document.getElementById('mobileServicesSubmenu').classList.toggle('hidden');
                    this.querySelector('[data-arrow]').classList.toggle('rotate-180');
                "
            >
                <span>Services</span>
                <svg data-arrow class="h-4 w-4 transition duration-200" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.938a.75.75 0 1 1 1.08 1.04l-4.25 4.51a.75.75 0 0 1-1.08 0L5.21 8.27a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd"/>
                </svg>
            </button>

            <div id="mobileServicesSubmenu" class="hidden pl-2">
                <div class="grid gap-2 border-l border-white/10 pl-3">
                    <?php if (!empty($navServices)): ?>
                        <?php foreach ($navServices as $navService): ?>
                            <a
                                href="<?= e($serviceMenuUrl($navService)) ?>"
                                class="rounded-2xl px-4 py-3 text-sm text-white/80 transition hover:bg-white/10 hover:text-white"
                            >
                                <span class="block font-bold"><?= e((string) ($navService['title'] ?? 'Service')) ?></span>
                                <?php if (trim((string) ($navService['excerpt'] ?? '')) !== ''): ?>
                                    <span class="mt-1 block text-xs leading-5 text-white/55">
                                        <?= e((string) $navService['excerpt']) ?>
                                    </span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>

                        <a
                            href="/services"
                            class="rounded-2xl px-4 py-3 text-sm font-bold text-white/90 transition hover:bg-white/10 hover:text-white"
                        >
                            View All Services
                        </a>
                    <?php else: ?>
                        <div class="rounded-2xl px-4 py-3 text-sm text-white/60">
                            No active services available.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <a
                href="/auth"
                class="<?= $isActiveNav('/auth', $currentPath) ? 'bg-white/12 text-white' : 'text-white/90' ?> rounded-2xl px-4 py-3 text-sm font-bold transition hover:bg-white/12 hover:text-white"
            >
                Login
            </a>

            <a
                href="<?= e(asset(ltrim($ctaUrl, '/'))) ?>"
                class="mt-2 inline-flex items-center justify-center rounded-2xl bg-white px-5 py-3 text-sm font-extrabold text-[#0B3C91] shadow-[0_10px_24px_rgba(255,255,255,0.18)] transition hover:-translate-y-0.5"
            >
                <?= e($ctaText) ?>
            </a>
        </div>
    </div>
</div>
</header>
    <main class="auth-main">
       
                        <?= $content ?>
                    
           
    </main>
    <footer class="site-footer">
    <div class="footer-grid bg-gradient-to-r from-[#0B3C91] via-[#145DA0] to-[#1E81B0]">
        <div class="footer-brand">
            <div class="footer-brand-top">
                <?php if ($siteLogoUrl !== ''): ?>
                    <img src="<?= e($siteLogoUrl) ?>" alt="<?= e($siteName) ?>" class="footer-logo-img">
                <?php else: ?>
                    <span class="brand-mark bg-white/12 text-white border border-white/15"><?= e(strtoupper(substr($siteName, 0, 2))) ?></span>
                <?php endif; ?>
                <strong class="text-white"><?= e($siteName) ?></strong>
            </div>
            <p class="text-blue-100/85"><?= e($footerText) ?></p>
        </div>

        <div class="footer-links">
            <a href="<?= e(asset('')) ?>" class="text-white/90 hover:text-white">Home</a>
            <a href="<?= e(asset('services')) ?>" class="text-white/90 hover:text-white">Services</a>
            <a href="<?= e(asset('login')) ?>" class="text-white/90 hover:text-white">Login</a>
            <a href="<?= e(asset('register')) ?>" class="text-white/90 hover:text-white">Register</a>
        </div>

        <div class="footer-contact">
            <a href="tel:<?= e(preg_replace('/\s+/', '', $contactPhone)) ?>" class="text-blue-100/85 hover:text-white"><?= e($contactPhone) ?></a>
            <a href="mailto:<?= e($contactEmail) ?>" class="text-blue-100/85 hover:text-white"><?= e($contactEmail) ?></a>
        </div>
    </div>
</footer>

    <script>
        document.addEventListener('click', function (e) {
            const nav = document.querySelector('.topbar-nav');
            const toggle = document.querySelector('.mobile-nav-toggle');
            if (!nav || !toggle) return;

            if (!nav.contains(e.target) && !toggle.contains(e.target)) {
                document.body.classList.remove('nav-open');
            }
        });
    </script>
</body>
</html>