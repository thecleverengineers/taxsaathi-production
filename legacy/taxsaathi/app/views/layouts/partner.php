<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/helpers/user_roles_rbac_helper.php';

if (!function_exists('e')) {
    function e(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
}

if (!function_exists('base_url')) {
    function base_url(string $path = ''): string { return '/' . ltrim($path, '/'); }
}

$siteName = function_exists('setting') ? trim((string) setting('site_name', 'Tax Saathi')) : 'Tax Saathi';
$siteName = $siteName !== '' ? $siteName : 'Tax Saathi';
$siteLogoPath = function_exists('setting') ? trim((string) setting('site_logo', '')) : '';
$siteLogoAlt = function_exists('setting') ? trim((string) setting('site_logo_alt', $siteName . ' Logo')) : $siteName . ' Logo';
$siteLogoAlt = $siteLogoAlt !== '' ? $siteLogoAlt : $siteName . ' Logo';
$resolveAsset = static function (?string $path): string {
    $path = trim((string) $path);
    if ($path === '') return '';
    if (preg_match('~^(https?:)?//~i', $path) === 1 || str_starts_with($path, 'data:')) return $path;
    return base_url(ltrim($path, '/'));
};
$siteLogoUrl = $resolveAsset($siteLogoPath);

$user = rbac_current_user();
$userId = rbac_current_user_id();
$rbac = rbac_context($userId, true);
$userName = trim((string) ($user['name'] ?? 'User'));
$userInitial = strtoupper(substr($userName !== '' ? $userName : 'U', 0, 1));
$roleLabel = ($rbac['role_names'] ?? []) !== [] ? implode(' + ', $rbac['role_names']) : 'No roles assigned';
$workspaceLabel = $roleLabel . ' Workspace';
$dashboardPageTitle = trim((string) ($title ?? 'Dashboard – ' . $siteName));
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$logoutUrl = base_url('logout');
$notificationsEnabled = $userId > 0 && rbac_can('notifications.view');
$notificationsPollUrl = base_url('notifications/poll');
$notificationsMarkReadUrl = base_url('notifications/mark-read');
$notificationsMarkUnreadUrl = base_url('notifications/mark-unread');
$notificationsMarkAllReadUrl = base_url('notifications/mark-all-read');
$csrfTokenForJs = function_exists('csrf_token') ? (string) csrf_token() : '';

$navItems = rbac_sidebar_items($userId);
$navGroups = [];
foreach ($navItems as $item) {
    $section = trim((string) ($item['section'] ?? 'Menu')) ?: 'Menu';
    $navGroups[$section][] = $item;
}

$dashboardHref = base_url('dashboard');
foreach ($navItems as $item) {
    if (strtolower((string) ($item['label'] ?? '')) === 'dashboard') {
        $dashboardHref = (string) $item['href'];
        break;
    }
}

$profileHref = rbac_can('partners.profile.manage_own') || rbac_can('profile.manage_own')
    ? base_url('partner/profile')
    : (rbac_can('client.profile.manage') ? base_url('client/profile') : $dashboardHref);

$ordersHref = match (true) {
    rbac_can('orders.view_all') || rbac_can('orders.manage') || rbac_can('orders.view_assigned') => base_url('admin/orders'),
    rbac_can('partners.orders.view_own') => base_url('partner/orders'),
    rbac_can('client.orders.view') || rbac_can('client.portal') => base_url('client/orders'),
    default => $dashboardHref,
};

$isActive = static function (string $href, string $currentPath): bool {
    $hrefPath = parse_url($href, PHP_URL_PATH) ?: $href;
    if ($hrefPath === '/') return $currentPath === '/';
    return $currentPath === $hrefPath || str_starts_with($currentPath, rtrim($hrefPath, '/') . '/');
};

$icon = static function (string $name, string $classes = 'h-4.5 w-4.5'): string {
    return match ($name) {
        'home' => '<svg viewBox="0 0 24 24" width="18" height="18"  fill="none" stroke="currentColor" stroke-width="1.8" class="' . $classes . '"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10.5 12 3l9 7.5"/><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 9.75V20a1 1 0 0 0 1 1h4.5v-6h3v6h4.5a1 1 0 0 0 1-1V9.75"/></svg>',
        'users' => '<svg viewBox="0 0 24 24" width="18" height="18"  fill="none" stroke="currentColor" stroke-width="1.8" class="' . $classes . '"><path stroke-linecap="round" stroke-linejoin="round" d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/><circle cx="9.5" cy="7" r="3.5"/><path stroke-linecap="round" stroke-linejoin="round" d="M20 21v-2a4 4 0 0 0-3-3.87"/><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 3.13a3.5 3.5 0 0 1 0 6.74"/></svg>',
        'briefcase' => '<svg viewBox="0 0 24 24" width="18" height="18"  fill="none" stroke="currentColor" stroke-width="1.8" class="' . $classes . '"><rect x="3" y="7" width="18" height="13" rx="2"/><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path stroke-linecap="round" d="M3 12h18"/></svg>',
        'grid' => '<svg viewBox="0 0 24 24" width="18" height="18"  fill="none" stroke="currentColor" stroke-width="1.8" class="' . $classes . '"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/></svg>',
        'chart' => '<svg viewBox="0 0 24 24" width="18" height="18"  fill="none" stroke="currentColor" stroke-width="1.8" class="' . $classes . '"><path stroke-linecap="round" stroke-linejoin="round" d="M4 19h16"/><path stroke-linecap="round" stroke-linejoin="round" d="M7 16V9"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 16V5"/><path stroke-linecap="round" stroke-linejoin="round" d="M17 16v-4"/></svg>',
        'bell' => '<svg viewBox="0 0 24 24" width="18" height="18"  fill="none" stroke="currentColor" stroke-width="1.8" class="' . $classes . '"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17H5.5a1.5 1.5 0 0 1-1.2-2.4L6 12.5V9a6 6 0 1 1 12 0v3.5l1.7 2.1a1.5 1.5 0 0 1-1.2 2.4H15"/><path stroke-linecap="round" d="M10 20a2 2 0 0 0 4 0"/></svg>',
        'coupon' => '<svg viewBox="0 0 24 24" width="18" height="18"  fill="none" stroke="currentColor" stroke-width="1.8" class="' . $classes . '"><path stroke-linecap="round" stroke-linejoin="round" d="M3.5 12.5 12.5 3.5h7v7l-9 9a2 2 0 0 1-2.83 0l-4.17-4.17a2 2 0 0 1 0-2.83Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 7.5h.01"/></svg>',
        'folder' => '<svg viewBox="0 0 24 24" width="18" height="18"  fill="none" stroke="currentColor" stroke-width="1.8" class="' . $classes . '"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z"/></svg>',
        'calculator' => '<svg viewBox="0 0 24 24" fill="none" width="18" height="18"  stroke="currentColor" stroke-width="1.8" class="' . $classes . '"><rect x="6" y="3" width="12" height="18" rx="2"/><path stroke-linecap="round" d="M9 7h6M9 12h.01M12 12h.01M15 12h.01M9 16h.01M12 16h.01M15 16h.01"/></svg>',
        'userCircle' => '<svg viewBox="0 0 24 24" fill="none" width="18" height="18"  stroke="currentColor" stroke-width="1.8" class="' . $classes . '"><circle cx="12" cy="8" r="4"/><path stroke-linecap="round" stroke-linejoin="round" d="M4 20a8 8 0 0 1 16 0"/></svg>',
        'globe' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="' . $classes . '"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M3 12h18M12 3a14.5 14.5 0 0 1 0 18M12 3a14.5 14.5 0 0 0 0 18"/></svg>',
        'chat' => '<svg viewBox="0 0 24 24" fill="none" width="18" height="18"  stroke="currentColor" stroke-width="1.8" class="' . $classes . '"><path stroke-linecap="round" stroke-linejoin="round" d="M7 10h10M7 14h6"/><path stroke-linecap="round" stroke-linejoin="round" d="M6 4h12a3 3 0 0 1 3 3v8a3 3 0 0 1-3 3h-7l-5 3v-3H6a3 3 0 0 1-3-3V7a3 3 0 0 1 3-3Z"/></svg>',
        default => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="18" height="18"  stroke-width="1.8" class="' . $classes . '"><path stroke-linecap="round" stroke-linejoin="round" d="M14 3h7v7"/><path stroke-linecap="round" stroke-linejoin="round" d="M10 14 21 3"/><path stroke-linecap="round" stroke-linejoin="round" d="M21 14v4a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V6a3 3 0 0 1 3-3h4"/></svg>',
    };
};

$mobileItems = array_slice($navItems, 0, 5);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= e($dashboardPageTitle) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="<?= e($siteLogoUrl !== '' ? $siteLogoUrl : 'data:,') ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: { colors: { brand: { 50:'#eef6ff',100:'#d8ebff',500:'#2d8cff',600:'#1d74e8',700:'#155fcd',900:'#173f81' } } } } };
    </script>
    <style>
        html,body{width:100%;max-width:100%;overflow-x:hidden} body{background:#f5f7fb}.sidebar-scroll{scrollbar-width:thin;scrollbar-color:rgba(14,165,198,.55) transparent}.sidebar-scroll::-webkit-scrollbar{width:6px}.sidebar-scroll::-webkit-scrollbar-thumb{background:rgba(14,165,198,.7);border-radius:999px}.ts-card{background:#fff;border:1px solid rgb(226 232 240);box-shadow:0 8px 24px rgba(15,23,42,.05)}
    </style>
</head>
<body class="min-h-screen font-sans text-slate-700 antialiased">
<div class="min-h-screen">
    <div class="flex min-h-screen">
        <div id="sidebarBackdrop" class="fixed inset-0 z-30 hidden bg-slate-900/40 lg:hidden"></div>

        <aside id="sidebarPanel" class="sidebar-scroll fixed inset-y-0 left-0 z-40 flex w-[260px] -translate-x-full flex-col overflow-y-auto border-r border-white/10 bg-gradient-to-b from-[#053B73] via-[#075B9A] to-[#0EA5C6] px-4 py-4 text-white shadow-xl transition-transform duration-200 lg:static lg:translate-x-0">
            <div class="flex items-center justify-between">
                <a href="<?= e($dashboardHref) ?>" class="flex min-w-0 items-center gap-3">
                    <div class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-[18px] border border-gray-200 bg-white shadow-md">
                        <?php if ($siteLogoUrl !== ''): ?>
                            <img src="<?= e($siteLogoUrl) ?>" alt="<?= e($siteLogoAlt) ?>" class="h-full w-full object-contain p-2">
                        <?php else: ?>
                            <span class="text-sm font-black text-brand-700">TS</span>
                        <?php endif; ?>
                    </div>
                    <div class="min-w-0">
                        <div class="truncate text-sm font-extrabold uppercase tracking-[0.14em] text-white"><?= e($siteName) ?></div>
                        <div class="truncate text-[11px] font-medium text-cyan-100/75"><?= e($workspaceLabel) ?></div>
                    </div>
                </a>
                <button id="sidebarClose" type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-white/15 bg-white/10 text-white lg:hidden">×</button>
            </div>

            <div class="mt-6 rounded-2xl border border-white/12 bg-white/10 p-3.5 backdrop-blur">
                <div class="flex items-center gap-3">
                    <div class="relative flex h-11 w-11 items-center justify-center rounded-full bg-white/18 text-sm font-bold text-white ring-1 ring-white/15"><?= e($userInitial) ?><span class="absolute bottom-0 right-0 h-3 w-3 rounded-full border-2 border-white bg-emerald-500"></span></div>
                    <div class="min-w-0"><div class="truncate text-sm font-semibold text-white"><?= e($userName) ?></div><div class="truncate text-xs text-cyan-100/70"><?= e($roleLabel) ?></div></div>
                </div>
            </div>

            <?php if ($navGroups === []): ?>
                <div class="mt-6 rounded-2xl border border-amber-200/30 bg-amber-50/10 p-4 text-xs leading-5 text-amber-50">
                    No sidebar menus available. Add rows in <strong>sidebar_menus</strong> and assign permissions through <strong>user_roles</strong>.
                </div>
            <?php endif; ?>

            <?php foreach ($navGroups as $section => $items): ?>
                <div class="mt-6">
                    <div class="mb-2 px-2 text-[11px] font-semibold uppercase tracking-[0.2em] text-cyan-100/55"><?= e($section) ?></div>
                    <nav class="space-y-1.5">
                        <?php foreach ($items as $item): ?>
                            <?php $active = $isActive((string) $item['href'], $currentPath); ?>
                            <a href="<?= e((string) $item['href']) ?>" class="group flex items-center gap-3 rounded-xl px-3 py-2.5 text-[13px] transition <?= $active ? 'bg-white/18 text-white shadow-sm ring-1 ring-white/12' : 'text-cyan-50/78 hover:bg-white/10 hover:text-white' ?>">
                                <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl <?= $active ? 'bg-white text-[#075B9A]' : 'bg-white/10 text-cyan-50/75 group-hover:bg-white/15 group-hover:text-white' ?>"><?= $icon((string) $item['icon']) ?></span>
                                <span class="flex-1 truncate font-medium"><?= e((string) $item['label']) ?></span>
                                <?php if ($active): ?><span class="h-2 w-2 rounded-full bg-cyan-200"></span><?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                </div>
            <?php endforeach; ?>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-20 border-b border-white/10 bg-gradient-to-r from-[#053B73] via-[#075B9A] to-[#0EA5C6] shadow-lg">
                <div class="flex min-h-[58px] items-center gap-2.5 px-4 py-2 sm:px-6 lg:px-7">
                    <button id="sidebarToggle" type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-white/15 bg-white/10 text-white lg:hidden">☰</button>
                    <div class="min-w-0 flex-1"><div class="flex items-center gap-2 text-[12px] text-cyan-100/70"><span><?= e($roleLabel) ?></span><span class="h-1 w-1 rounded-full bg-cyan-100/50"></span><span class="truncate text-cyan-50/85"><?= e($workspaceLabel) ?></span></div><h1 class="truncate text-[15px] font-semibold text-white sm:text-base"><?= e($title ?? 'Dashboard') ?></h1></div>

                    <?php if ($notificationsEnabled): ?>
                        <a href="<?= e(base_url('notifications')) ?>" class="relative inline-flex h-9 w-9 items-center justify-center rounded-xl border border-white/15 bg-rose-50 text-rose-600 shadow-sm"><?= $icon('bell', 'h-4 w-4') ?></a>
                    <?php endif; ?>

                    <div class="relative" id="profileDropdownWrap">
                        <button type="button" id="profileDropdownBtn" class="group inline-flex h-9 items-center gap-2 rounded-xl border border-white/15 bg-white/10 px-2 py-1 transition hover:bg-white/15">
                            <div class="flex h-7 w-7 items-center justify-center rounded-full bg-white/18 text-[10px] font-bold text-white ring-1 ring-white/15"><?= e($userInitial) ?></div>
                            <div class="hidden text-left sm:block"><div class="max-w-[150px] truncate text-[12px] font-semibold text-white"><?= e($userName) ?></div><div class="max-w-[150px] truncate text-[10px] text-cyan-100/70"><?= e($roleLabel) ?></div></div>
                        </button>
                        <div id="profileDropdownMenu" class="fixed left-3 right-3 top-[72px] z-50 hidden overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl sm:absolute sm:left-auto sm:right-0 sm:top-[calc(100%+10px)] sm:w-[280px]">
                            <div class="border-b border-slate-200 bg-slate-50 px-4 py-4"><div class="text-sm font-semibold text-slate-900"><?= e($userName) ?></div><div class="text-xs text-slate-500"><?= e($roleLabel) ?></div></div>
                            <div class="p-2">
                                <a href="<?= e($profileHref) ?>" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-[13px] text-slate-600 hover:bg-slate-50"><?= $icon('userCircle') ?><span>My Profile</span></a>
                                <a href="<?= e($ordersHref) ?>" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-[13px] text-slate-600 hover:bg-slate-50"><?= $icon('briefcase') ?><span>Orders / Applications</span></a>
                                <a href="<?= e($dashboardHref) ?>" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-[13px] text-slate-600 hover:bg-slate-50"><?= $icon('home') ?><span>Dashboard</span></a>
                                <div class="my-2 h-px bg-slate-200"></div>
                                <form method="post" action="<?= e($logoutUrl) ?>"><?= function_exists('csrf_field') ? csrf_field() : '' ?><button type="submit" class="flex w-full rounded-xl px-3 py-2.5 text-left text-[13px] text-rose-600 hover:bg-rose-50">Logout</button></form>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <main class="min-w-0 flex-1 p-4 pb-24 sm:p-6 lg:p-7">
                <?php if (function_exists('flash') && ($message = flash('success'))): ?><div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700"><?= e($message) ?></div><?php endif; ?>
                <?php if (function_exists('flash') && ($message = flash('error'))): ?><div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700"><?= e($message) ?></div><?php endif; ?>

                <div class="ts-card rounded-2xl p-4 sm:p-6">
                    <?= $content ?? '' ?>
                </div>
            </main>
        </div>
    </div>

    <?php if ($mobileItems !== []): ?>
        <nav class="fixed bottom-0 left-0 right-0 z-30 border-t border-slate-200 bg-white/95 px-2 py-2 shadow-xl backdrop-blur lg:hidden">
            <div class="mx-auto grid max-w-lg grid-cols-<?= min(5, max(1, count($mobileItems))) ?> gap-1">
                <?php foreach ($mobileItems as $item): ?>
                    <?php $active = $isActive((string) $item['href'], $currentPath); ?>
                    <a href="<?= e((string) $item['href']) ?>" class="flex flex-col items-center justify-center rounded-xl px-1 py-2 text-[10px] <?= $active ? 'bg-brand-50 text-brand-700' : 'text-slate-500' ?>">
                        <?= $icon((string) $item['icon'], 'h-4 w-4') ?>
                        <span class="mt-1 max-w-[64px] truncate"><?= e((string) $item['label']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </nav>
    <?php endif; ?>
</div>
<script>
(function(){
    const panel=document.getElementById('sidebarPanel'),backdrop=document.getElementById('sidebarBackdrop'),toggle=document.getElementById('sidebarToggle'),close=document.getElementById('sidebarClose');
    function openSidebar(){panel.classList.remove('-translate-x-full');backdrop.classList.remove('hidden');}
    function closeSidebar(){panel.classList.add('-translate-x-full');backdrop.classList.add('hidden');}
    if(toggle)toggle.addEventListener('click',openSidebar); if(close)close.addEventListener('click',closeSidebar); if(backdrop)backdrop.addEventListener('click',closeSidebar);
    const btn=document.getElementById('profileDropdownBtn'),menu=document.getElementById('profileDropdownMenu'),wrap=document.getElementById('profileDropdownWrap');
    if(btn&&menu&&wrap){btn.addEventListener('click',function(e){e.stopPropagation();menu.classList.toggle('hidden');});document.addEventListener('click',function(e){if(!wrap.contains(e.target))menu.classList.add('hidden');});}
})();
</script>
</body>
</html>
