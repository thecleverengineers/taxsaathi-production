<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/helpers/user_roles_rbac_helper.php';

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('base_url')) {
    function base_url(string $path = ''): string
    {
        return '/' . ltrim($path, '/');
    }
}

$siteName = function_exists('setting')
    ? trim((string) setting('site_name', 'Tax Saathi'))
    : 'Tax Saathi';

$siteName = $siteName !== '' ? $siteName : 'Tax Saathi';

$siteLogoPath = function_exists('setting')
    ? trim((string) setting('site_logo', ''))
    : '';

$siteLogoAlt = function_exists('setting')
    ? trim((string) setting('site_logo_alt', $siteName . ' Logo'))
    : $siteName . ' Logo';

$siteLogoAlt = $siteLogoAlt !== ''
    ? $siteLogoAlt
    : $siteName . ' Logo';

$resolveAsset = static function (?string $path): string {
    $path = trim((string) $path);

    if ($path === '') {
        return '';
    }

    if (
        preg_match('~^(https?:)?//~i', $path) === 1
        || str_starts_with($path, 'data:')
    ) {
        return $path;
    }

    return base_url(ltrim($path, '/'));
};

$siteLogoUrl = $resolveAsset($siteLogoPath);

$user = rbac_current_user();
$userId = rbac_current_user_id();
$rbac = rbac_context($userId, true);

$userName = trim((string) ($user['name'] ?? 'User'));
$userInitial = strtoupper(substr($userName !== '' ? $userName : 'U', 0, 1));

$roleLabel = ($rbac['role_names'] ?? []) !== []
    ? implode(' + ', $rbac['role_names'])
    : 'No roles assigned';

$workspaceLabel = $roleLabel . ' Workspace';

$notificationRoleIds = array_values(
    array_unique(
        array_filter(
            array_map(
                'intval',
                is_array($rbac['role_ids'] ?? null)
                    ? $rbac['role_ids']
                    : []
            ),
            static fn (int $id): bool => $id > 0
        )
    )
);

$notificationRoleKeys = [];

foreach (
    array_merge(
        is_array($rbac['role_names'] ?? null)
            ? $rbac['role_names']
            : [],
        is_array($rbac['role_slugs'] ?? null)
            ? $rbac['role_slugs']
            : []
    ) as $notificationRoleValue
) {
    $notificationRoleKey = strtolower(trim((string) $notificationRoleValue));
    $notificationRoleKey = str_replace(
        [' ', '_'],
        '-',
        $notificationRoleKey
    );

    $notificationRoleKey = preg_replace(
        '/[^a-z0-9-]+/',
        '',
        $notificationRoleKey
    ) ?: '';

    $notificationRoleKey = trim($notificationRoleKey, '-');

    if ($notificationRoleKey !== '') {
        $notificationRoleKeys[$notificationRoleKey] = $notificationRoleKey;
    }
}

$notificationRoleKeys = array_values($notificationRoleKeys);

/*
|--------------------------------------------------------------------------
| Floating support chat role visibility
|--------------------------------------------------------------------------
| The widget is displayed only for authenticated client and partner roles.
| Admin, manager, telecaller and other staff roles do not receive the
| floating client-side widget.
|--------------------------------------------------------------------------
*/

$supportChatIsPartner =
    array_intersect($notificationRoleIds, [4]) !== []
    || array_intersect(
        $notificationRoleKeys,
        ['partner', 'partners']
    ) !== [];

$supportChatIsClient =
    array_intersect($notificationRoleIds, [5]) !== []
    || array_intersect(
        $notificationRoleKeys,
        ['client', 'user', 'customer']
    ) !== [];

$supportChatIsSupportTeam =
    array_intersect($notificationRoleIds, [1, 2]) !== []
    || array_intersect(
        $notificationRoleKeys,
        [
            'admin',
            'super-admin',
            'superadmin',
            'administrator',
            'manager',
            'telecaller',
            'tele-caller',
            'tele-caller-user',
        ]
    ) !== [];

$supportChatWidgetEnabled =
    $userId > 0
    && !$supportChatIsSupportTeam
    && ($supportChatIsClient || $supportChatIsPartner);

$supportChatWidgetPath = dirname(__DIR__) . '/partials/support-chat-widget.php';

$notificationIsAdminManager =
    array_intersect($notificationRoleIds, [1, 2]) !== []
    || array_intersect(
        $notificationRoleKeys,
        [
            'admin',
            'super-admin',
            'superadmin',
            'administrator',
            'manager',
        ]
    ) !== [];

$notificationIsRestrictedRole =
    array_intersect($notificationRoleIds, [3, 4, 5]) !== []
    || array_intersect(
        $notificationRoleKeys,
        [
            'executive',
            'staff',
            'partner',
            'partners',
            'client',
            'user',
            'customer',
        ]
    ) !== [];

$notificationExpectedScopeMode = $notificationIsAdminManager
    ? 'all_activity_logs'
    : (
        $notificationIsRestrictedRole
            ? 'activity_logs_user_id_match'
            : 'none'
    );

$dashboardPageTitle = trim(
    (string) ($title ?? 'Dashboard – ' . $siteName)
);

$currentPath = parse_url(
    $_SERVER['REQUEST_URI'] ?? '/',
    PHP_URL_PATH
) ?: '/';

$logoutUrl = base_url('logout');

$notificationsEnabled =
    $userId > 0
    && ($notificationIsAdminManager || $notificationIsRestrictedRole);

$notificationsPollUrl = base_url('notifications/poll');
$notificationsMarkReadUrl = base_url('notifications/mark-read');
$notificationsMarkUnreadUrl = base_url('notifications/mark-unread');
$notificationsMarkAllReadUrl = base_url('notifications/mark-all-read');

$csrfTokenForJs = function_exists('csrf_token')
    ? (string) csrf_token()
    : '';

$navItems = rbac_sidebar_items($userId);
$navGroups = [];

foreach ($navItems as $item) {
    $section = trim((string) ($item['section'] ?? 'Menu'))
        ?: 'Menu';

    $navGroups[$section][] = $item;
}

$dashboardHref = base_url('dashboard');

foreach ($navItems as $item) {
    if (
        strtolower((string) ($item['label'] ?? ''))
        === 'dashboard'
    ) {
        $dashboardHref = (string) $item['href'];
        break;
    }
}

$profileHref =
    rbac_can('partners.profile.manage_own')
    || rbac_can('profile.manage_own')
        ? base_url('partner/profile')
        : (
            rbac_can('client.profile.manage')
                ? base_url('client/profile')
                : $dashboardHref
        );

$ordersHref = match (true) {
    rbac_can('orders.view_all')
    || rbac_can('orders.manage')
    || rbac_can('orders.view_assigned')
        => base_url('admin/orders'),

    rbac_can('partners.orders.view_own')
        => base_url('partner/orders'),

    rbac_can('client.orders.view')
    || rbac_can('client.portal')
        => base_url('client/orders'),

    default => $dashboardHref,
};

$isActive = static function (
    string $href,
    string $currentPath
): bool {
    $hrefPath = parse_url($href, PHP_URL_PATH) ?: $href;

    if ($hrefPath === '/') {
        return $currentPath === '/';
    }

    return $currentPath === $hrefPath
        || str_starts_with(
            $currentPath,
            rtrim($hrefPath, '/') . '/'
        );
};

$icon = static function (
    string $name,
    string $classes = 'h-4.5 w-4.5'
): string {
    return match ($name) {
        'home' => '
            <svg
                viewBox="0 0 24 24"
                width="18"
                height="18"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                class="' . $classes . '"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M3 10.5 12 3l9 7.5"
                />
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M5.25 9.75V20a1 1 0 0 0 1 1h4.5v-6h3v6h4.5a1 1 0 0 0 1-1V9.75"
                />
            </svg>
        ',

        'users' => '
            <svg
                viewBox="0 0 24 24"
                width="18"
                height="18"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                class="' . $classes . '"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"
                />
                <circle cx="9.5" cy="7" r="3.5"/>
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M20 21v-2a4 4 0 0 0-3-3.87"
                />
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M16.5 3.13a3.5 3.5 0 0 1 0 6.74"
                />
            </svg>
        ',

        'briefcase' => '
            <svg
                viewBox="0 0 24 24"
                width="18"
                height="18"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                class="' . $classes . '"
            >
                <rect x="3" y="7" width="18" height="13" rx="2"/>
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"
                />
                <path
                    stroke-linecap="round"
                    d="M3 12h18"
                />
            </svg>
        ',

        'grid' => '
            <svg
                viewBox="0 0 24 24"
                width="18"
                height="18"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                class="' . $classes . '"
            >
                <rect x="3" y="3" width="7" height="7" rx="1.5"/>
                <rect x="14" y="3" width="7" height="7" rx="1.5"/>
                <rect x="14" y="14" width="7" height="7" rx="1.5"/>
                <rect x="3" y="14" width="7" height="7" rx="1.5"/>
            </svg>
        ',

        'chart' => '
            <svg
                viewBox="0 0 24 24"
                width="18"
                height="18"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                class="' . $classes . '"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M4 19h16"
                />
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M7 16V9"
                />
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M12 16V5"
                />
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M17 16v-4"
                />
            </svg>
        ',

        'bell' => '
            <svg
                viewBox="0 0 24 24"
                width="18"
                height="18"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                class="' . $classes . '"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M15 17H5.5a1.5 1.5 0 0 1-1.2-2.4L6 12.5V9a6 6 0 1 1 12 0v3.5l1.7 2.1a1.5 1.5 0 0 1-1.2 2.4H15"
                />
                <path
                    stroke-linecap="round"
                    d="M10 20a2 2 0 0 0 4 0"
                />
            </svg>
        ',

        'coupon' => '
            <svg
                viewBox="0 0 24 24"
                width="18"
                height="18"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                class="' . $classes . '"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M3.5 12.5 12.5 3.5h7v7l-9 9a2 2 0 0 1-2.83 0l-4.17-4.17a2 2 0 0 1 0-2.83Z"
                />
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M16.5 7.5h.01"
                />
            </svg>
        ',

        'folder' => '
            <svg
                viewBox="0 0 24 24"
                width="18"
                height="18"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                class="' . $classes . '"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M3 8a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z"
                />
            </svg>
        ',

        'calculator' => '
            <svg
                viewBox="0 0 24 24"
                fill="none"
                width="18"
                height="18"
                stroke="currentColor"
                stroke-width="1.8"
                class="' . $classes . '"
            >
                <rect x="6" y="3" width="12" height="18" rx="2"/>
                <path
                    stroke-linecap="round"
                    d="M9 7h6M9 12h.01M12 12h.01M15 12h.01M9 16h.01M12 16h.01M15 16h.01"
                />
            </svg>
        ',

        'userCircle' => '
            <svg
                viewBox="0 0 24 24"
                fill="none"
                width="18"
                height="18"
                stroke="currentColor"
                stroke-width="1.8"
                class="' . $classes . '"
            >
                <circle cx="12" cy="8" r="4"/>
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M4 20a8 8 0 0 1 16 0"
                />
            </svg>
        ',

        'globe' => '
            <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                class="' . $classes . '"
            >
                <circle cx="12" cy="12" r="9"/>
                <path stroke-linecap="round" d="M3 12h18"/>
                <path
                    stroke-linecap="round"
                    d="M12 3a14.5 14.5 0 0 1 0 18"
                />
                <path
                    stroke-linecap="round"
                    d="M12 3a14.5 14.5 0 0 0 0 18"
                />
            </svg>
        ',

        'chat' => '
            <svg
                viewBox="0 0 24 24"
                fill="none"
                width="18"
                height="18"
                stroke="currentColor"
                stroke-width="1.8"
                class="' . $classes . '"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M7 10h10M7 14h6"
                />
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M6 4h12a3 3 0 0 1 3 3v8a3 3 0 0 1-3 3h-7l-5 3v-3H6a3 3 0 0 1-3-3V7a3 3 0 0 1 3-3Z"
                />
            </svg>
        ',

        'wallet' => '
            <svg
                viewBox="0 0 24 24"
                fill="none"
                width="18"
                height="18"
                stroke="currentColor"
                stroke-width="1.8"
                class="' . $classes . '"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M4 6.5A2.5 2.5 0 0 1 6.5 4H19a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6.5A2.5 2.5 0 0 1 4 17.5v-11Z"
                />
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M4 8h15a2 2 0 0 1 2 2v2H17a2 2 0 0 0 0 4h4"
                />
                <path stroke-linecap="round" d="M17 14h.01" />
            </svg>
        ',

        default => '
            <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                width="18"
                height="18"
                stroke-width="1.8"
                class="' . $classes . '"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M14 3h7v7"
                />
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M10 14 21 3"
                />
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M21 14v4a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V6a3 3 0 0 1 3-3h4"
                />
            </svg>
        ',
    };
};

$mobileItems = array_slice($navItems, 0, 5);

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">

    <title><?= e($dashboardPageTitle) ?></title>

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <link
        rel="icon"
        href="<?= e($siteLogoUrl !== '' ? $siteLogoUrl : 'data:,') ?>"
    >

    <script src="https://cdn.tailwindcss.com"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#eef6ff',
                            100: '#d8ebff',
                            500: '#2d8cff',
                            600: '#1d74e8',
                            700: '#155fcd',
                            900: '#173f81'
                        }
                    }
                }
            }
        };
    </script>

    <style>
        html,
        body {
            width: 100%;
            max-width: 100%;
            overflow-x: hidden;
        }

        body {
            background: #f5f7fb;
        }

        .sidebar-scroll {
            scrollbar-width: thin;
            scrollbar-color: rgba(14, 165, 198, .55) transparent;
            overscroll-behavior: contain;
        }

        .sidebar-scroll::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar-scroll::-webkit-scrollbar-thumb {
            background: rgba(14, 165, 198, .7);
            border-radius: 999px;
        }

        .ts-card {
            background: #fff;
            border: 1px solid rgb(226 232 240);
            box-shadow: 0 8px 24px rgba(15, 23, 42, .05);
        }

        @media (min-width: 1024px) {
            .sidebar-scroll {
                height: 100vh;
                max-height: 100vh;
                overflow-y: auto;
            }
        }
    </style>
</head>

<body class="min-h-screen font-sans text-slate-700 antialiased">

<div class="min-h-screen">
    <div class="flex min-h-screen">

        <div
            id="sidebarBackdrop"
            class="fixed inset-0 z-30 hidden bg-slate-900/40 lg:hidden"
        ></div>

        <!-- Desktop sidebar with independent scrolling -->
        <aside
            id="sidebarPanel"
            class="sidebar-scroll fixed inset-y-0 left-0 z-40 flex w-[260px] shrink-0 -translate-x-full flex-col overflow-y-auto overscroll-contain border-r border-white/10 bg-gradient-to-b from-[#053B73] via-[#075B9A] to-[#0EA5C6] px-4 py-4 text-white shadow-xl transition-transform duration-200 lg:sticky lg:top-0 lg:bottom-auto lg:h-screen lg:max-h-screen lg:self-start lg:translate-x-0"
        >
            <div class="flex items-center justify-between">
                <a
                    href="<?= e($dashboardHref) ?>"
                    class="flex min-w-0 items-center gap-3"
                >
                    <div
                        class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-[18px] border border-gray-200 bg-white shadow-md"
                    >
                        <?php if ($siteLogoUrl !== ''): ?>
                            <img
                                src="<?= e($siteLogoUrl) ?>"
                                alt="<?= e($siteLogoAlt) ?>"
                                class="h-full w-full object-contain p-2"
                            >
                        <?php else: ?>
                            <span class="text-sm font-black text-brand-700">
                                TS
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="min-w-0">
                        <div
                            class="truncate text-sm font-extrabold uppercase tracking-[0.14em] text-white"
                        >
                            <?= e($siteName) ?>
                        </div>

                        <div
                            class="truncate text-[11px] font-medium text-cyan-100/75"
                        >
                            <?= e($workspaceLabel) ?>
                        </div>
                    </div>
                </a>

                <button
                    id="sidebarClose"
                    type="button"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-white/15 bg-white/10 text-white lg:hidden"
                >
                    ×
                </button>
            </div>

            <div
                class="mt-6 rounded-2xl border border-white/12 bg-white/10 p-3.5 backdrop-blur"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="relative flex h-11 w-11 items-center justify-center rounded-full bg-white/18 text-sm font-bold text-white ring-1 ring-white/15"
                    >
                        <?= e($userInitial) ?>

                        <span
                            class="absolute bottom-0 right-0 h-3 w-3 rounded-full border-2 border-white bg-emerald-500"
                        ></span>
                    </div>

                    <div class="min-w-0">
                        <div class="truncate text-sm font-semibold text-white">
                            <?= e($userName) ?>
                        </div>

                        <div class="truncate text-xs text-cyan-100/70">
                            <?= e($roleLabel) ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($navGroups === []): ?>
                <div
                    class="mt-6 rounded-2xl border border-amber-200/30 bg-amber-50/10 p-4 text-xs leading-5 text-amber-50"
                >
                    No sidebar menus available. Add rows in
                    <strong>sidebar_menus</strong> and assign permissions
                    through <strong>user_roles</strong>.
                </div>
            <?php endif; ?>

            <?php foreach ($navGroups as $section => $items): ?>
                <div class="mt-6">
                    <div
                        class="mb-2 px-2 text-[11px] font-semibold uppercase tracking-[0.2em] text-cyan-100/55"
                    >
                        <?= e($section) ?>
                    </div>

                    <nav class="space-y-1.5">
                        <?php foreach ($items as $item): ?>
                            <?php
                            $active = $isActive(
                                (string) $item['href'],
                                $currentPath
                            );
                            ?>

                            <a
                                href="<?= e((string) $item['href']) ?>"
                                class="group flex items-center gap-3 rounded-xl px-3 py-2.5 text-[13px] transition <?= $active
                                    ? 'bg-white/18 text-white shadow-sm ring-1 ring-white/12'
                                    : 'text-cyan-50/78 hover:bg-white/10 hover:text-white' ?>"
                            >
                                <span
                                    class="inline-flex h-9 w-9 items-center justify-center rounded-xl <?= $active
                                        ? 'bg-white text-[#075B9A]'
                                        : 'bg-white/10 text-cyan-50/75 group-hover:bg-white/15 group-hover:text-white' ?>"
                                >
                                    <?= $icon((string) $item['icon']) ?>
                                </span>

                                <span
                                    class="flex-1 truncate font-medium"
                                >
                                    <?= e((string) $item['label']) ?>
                                </span>

                                <?php if ($active): ?>
                                    <span
                                        class="h-2 w-2 rounded-full bg-cyan-200"
                                    ></span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                </div>
            <?php endforeach; ?>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header
                class="sticky top-0 z-20 border-b border-white/10 bg-gradient-to-r from-[#053B73] via-[#075B9A] to-[#0EA5C6] shadow-lg"
            >
                <div
                    class="flex min-h-[58px] items-center gap-2.5 px-4 py-2 sm:px-6 lg:px-7"
                >
                    <button
                        id="sidebarToggle"
                        type="button"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-white/15 bg-white/10 text-white lg:hidden"
                    >
                        ☰
                    </button>

                    <div class="min-w-0 flex-1">
                        <div
                            class="flex items-center gap-2 text-[12px] text-cyan-100/70"
                        >
                            <span><?= e($roleLabel) ?></span>

                            <span
                                class="h-1 w-1 rounded-full bg-cyan-100/50"
                            ></span>

                            <span
                                class="truncate text-cyan-50/85"
                            >
                                <?= e($workspaceLabel) ?>
                            </span>
                        </div>

                        <h1
                            class="truncate text-[15px] font-semibold text-white sm:text-base"
                        >
                            <?= e($title ?? 'Dashboard') ?>
                        </h1>
                    </div>

                    <?php if ($notificationsEnabled): ?>
                        <div
                            id="tsNotificationWrap"
                            class="relative"
                        >
                            <button
                                id="tsNotificationButton"
                                type="button"
                                aria-label="Open notifications"
                                aria-haspopup="true"
                                class="relative inline-flex h-10 w-10 items-center justify-center rounded-xl border border-white/15 bg-white/10 text-white shadow-sm transition hover:bg-white/20"
                            >
                                <?= $icon('bell', 'h-4 w-4') ?>

                                <span
                                    id="tsNotificationBadge"
                                    class="absolute -right-1.5 -top-1.5 hidden min-w-[20px] rounded-full border-2 border-[#075B9A] bg-rose-500 px-1.5 py-0.5 text-center text-[9px] font-black leading-4 text-white"
                                >
                                    0
                                </span>
                            </button>

                            <div
                                id="tsNotificationPanel"
                                class="fixed left-3 right-3 top-[68px] z-50 hidden overflow-hidden rounded-[22px] border border-slate-200 bg-white shadow-[0_28px_75px_rgba(15,23,42,.22)] sm:absolute sm:left-auto sm:right-0 sm:top-[calc(100%+10px)] sm:w-[390px]"
                            >
                                <div
                                    class="border-b border-slate-100 bg-gradient-to-r from-slate-950 via-blue-950 to-cyan-800 px-4 py-4 text-white"
                                >
                                    <div
                                        class="flex items-start justify-between gap-3"
                                    >
                                        <div>
                                            <div
                                                class="text-[10px] font-bold uppercase tracking-[.18em] text-cyan-200"
                                            >
                                                Activity Logs Inbox
                                            </div>

                                            <div
                                                class="mt-1 text-sm font-extrabold"
                                            >
                                                Notifications
                                            </div>
                                        </div>

                                        <span
                                            id="tsNotificationConnectionStatus"
                                            class="inline-flex items-center rounded-full bg-amber-50 px-2 py-1 text-[10px] font-semibold text-amber-700"
                                        >
                                            Syncing
                                        </span>
                                    </div>
                                </div>

                                <div
                                    class="flex items-center justify-between border-b border-slate-100 px-4 py-2.5"
                                >
                                    <button
                                        id="tsNotificationMarkAll"
                                        type="button"
                                        class="text-[11px] font-bold text-brand-700 transition hover:text-brand-900 disabled:cursor-not-allowed disabled:opacity-60"
                                    >
                                        Mark all read
                                    </button>

                                    <button
                                        id="tsNotificationRefresh"
                                        type="button"
                                        class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-slate-500 transition hover:text-slate-900"
                                    >
                                        <svg
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="1.8"
                                            class="h-3.5 w-3.5"
                                            aria-hidden="true"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M20 7v5h-5M4 17v-5h5"
                                            />
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M7.1 7A7 7 0 0 1 19 9M16.9 17A7 7 0 0 1 5 15"
                                            />
                                        </svg>

                                        Refresh
                                    </button>
                                </div>

                                <div
                                    id="tsNotificationList"
                                    class="max-h-[430px] overflow-y-auto"
                                ></div>

                                <div
                                    id="tsNotificationEmpty"
                                    class="px-6 py-10 text-center"
                                >
                                    <div
                                        class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400"
                                    >
                                        <?= $icon('bell', 'h-5 w-5') ?>
                                    </div>

                                    <div
                                        id="tsNotificationEmptyTitle"
                                        class="mt-3 text-sm font-bold text-slate-800"
                                    >
                                        Loading notifications…
                                    </div>

                                    <div
                                        id="tsNotificationEmptyText"
                                        class="mt-1 text-xs leading-5 text-slate-500"
                                    >
                                        Reading directly from activity_logs.
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div
                        class="relative"
                        style="border:none;"
                        id="profileDropdownWrap"
                    >
                        <button
                            type="button"
                            id="profileDropdownBtn"
                            class="group inline-flex h-9 items-center gap-2 rounded-xl"
                        >
                            <div
                                class="flex h-7 w-7 items-center justify-center rounded-full bg-white/18 text-[10px] font-bold text-white ring-1 ring-white/15"
                            >
                                <?= e($userInitial) ?>
                            </div>

                            <div class="hidden text-left sm:block">
                                <div
                                    class="max-w-[150px] truncate text-[12px] font-semibold text-white"
                                >
                                    <?= e($userName) ?>
                                </div>

                                <div
                                    class="max-w-[150px] truncate text-[10px] text-cyan-100/70"
                                >
                                    <?= e($roleLabel) ?>
                                </div>
                            </div>
                        </button>

                        <div
                            id="profileDropdownMenu"
                            class="fixed left-3 right-3 top-[72px] z-50 hidden overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl sm:absolute sm:left-auto sm:right-0 sm:top-[calc(100%+10px)] sm:w-[280px]"
                        >
                            <div
                                class="border-b border-slate-200 bg-slate-50 px-4 py-4"
                            >
                                <div
                                    class="text-sm font-semibold text-slate-900"
                                >
                                    <?= e($userName) ?>
                                </div>

                                <div class="text-xs text-slate-500">
                                    <?= e($roleLabel) ?>
                                </div>
                            </div>

                            <div class="p-2">
                                <a
                                    href="<?= e($profileHref) ?>"
                                    class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-[13px] text-slate-600 transition hover:bg-slate-50 hover:text-slate-900"
                                >
                                    <?= $icon('userCircle') ?>
                                    <span>My Profile</span>
                                </a>

                                <a
                                    href="<?= e($ordersHref) ?>"
                                    class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-[13px] text-slate-600 transition hover:bg-slate-50 hover:text-slate-900"
                                >
                                    <?= $icon('briefcase') ?>
                                    <span>Orders / Applications</span>
                                </a>

                                <a
                                    href="<?= e($dashboardHref) ?>"
                                    class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-[13px] text-slate-600 transition hover:bg-slate-50 hover:text-slate-900"
                                >
                                    <?= $icon('home') ?>
                                    <span>Dashboard</span>
                                </a>

                                <a
                                    href="<?= e(base_url('auth/change-password')) ?>"
                                    class="group flex items-center gap-3 rounded-xl px-3 py-2.5 text-[13px] text-slate-600 transition hover:bg-emerald-50 hover:text-emerald-700"
                                >
                                    <svg
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                        class="h-[18px] w-[18px] shrink-0"
                                        aria-hidden="true"
                                    >
                                        <rect
                                            x="4"
                                            y="10"
                                            width="16"
                                            height="11"
                                            rx="2.5"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        />
                                        <path
                                            d="M8 10V7a4 4 0 0 1 8 0v3"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        />
                                        <path
                                            d="M12 14.5v2"
                                            stroke-linecap="round"
                                        />
                                    </svg>

                                    <span>Change Password</span>
                                </a>

                                <div class="my-2 h-px bg-slate-200"></div>

                                <form
                                    method="post"
                                    action="<?= e($logoutUrl) ?>"
                                >
                                    <?= function_exists('csrf_field')
                                        ? csrf_field()
                                        : '' ?>

                                    <button
                                        type="submit"
                                        class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-[13px] text-rose-600 transition hover:bg-rose-50 hover:text-rose-700"
                                    >
                                        <svg
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="1.8"
                                            class="h-[18px] w-[18px] shrink-0"
                                            aria-hidden="true"
                                        >
                                            <path
                                                d="M10 17l5-5-5-5"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                            />
                                            <path
                                                d="M15 12H3"
                                                stroke-linecap="round"
                                            />
                                            <path
                                                d="M14 4h4a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-4"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                            />
                                        </svg>

                                        <span>Logout</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <main class="min-w-0 flex-1 p-4 pb-24 sm:p-6 lg:p-7">
                <?php
                if (
                    function_exists('flash')
                    && ($message = flash('success'))
                ):
                ?>
                    <div
                        class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700"
                    >
                        <?= e($message) ?>
                    </div>
                <?php endif; ?>

                <?php
                if (
                    function_exists('flash')
                    && ($message = flash('error'))
                ):
                ?>
                    <div
                        class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700"
                    >
                        <?= e($message) ?>
                    </div>
                <?php endif; ?>

                <div class="ts-card rounded-2xl p-4 sm:p-6">
                    <?= $content ?? '' ?>
                </div>
            </main>
        </div>
    </div>

    <?php if ($mobileItems !== []): ?>
        <nav
            class="fixed bottom-0 left-0 right-0 z-30 border-t border-slate-200 bg-white/95 px-2 py-2 shadow-xl backdrop-blur lg:hidden"
        >
            <div
                class="mx-auto grid max-w-lg grid-cols-<?= min(
                    5,
                    max(1, count($mobileItems))
                ) ?> gap-1"
            >
                <?php foreach ($mobileItems as $item): ?>
                    <?php
                    $active = $isActive(
                        (string) $item['href'],
                        $currentPath
                    );
                    ?>

                    <a
                        href="<?= e((string) $item['href']) ?>"
                        class="flex flex-col items-center justify-center rounded-xl px-1 py-2 text-[10px] <?= $active
                            ? 'bg-brand-50 text-brand-700'
                            : 'text-slate-500' ?>"
                    >
                        <?= $icon((string) $item['icon'], 'h-4 w-4') ?>

                        <span
                            class="mt-1 max-w-[64px] truncate"
                        >
                            <?= e((string) $item['label']) ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </nav>
    <?php endif; ?>
</div>

<div
    id="tsNotificationToastRegion"
    class="pointer-events-none fixed bottom-5 right-4 z-[80] flex w-[calc(100%-2rem)] max-w-sm flex-col items-end gap-3 sm:bottom-6 sm:right-6"
></div>

<script>
(function (window, document) {
    'use strict';

    const state = {
        config: null,
        timer: null,
        latestId: 0,
        initialized: false,
        firstLoadComplete: false,
        polling: false,
        items: [],
    };

    const toneClasses = {
        info: {
            icon: 'bg-blue-50 text-blue-700 ring-blue-100',
            border: 'border-blue-200',
            accent: 'bg-blue-500',
        },

        success: {
            icon: 'bg-emerald-50 text-emerald-700 ring-emerald-100',
            border: 'border-emerald-200',
            accent: 'bg-emerald-500',
        },

        amber: {
            icon: 'bg-amber-50 text-amber-700 ring-amber-100',
            border: 'border-amber-200',
            accent: 'bg-amber-500',
        },

        danger: {
            icon: 'bg-rose-50 text-rose-700 ring-rose-100',
            border: 'border-rose-200',
            accent: 'bg-rose-500',
        },

        violet: {
            icon: 'bg-violet-50 text-violet-700 ring-violet-100',
            border: 'border-violet-200',
            accent: 'bg-violet-500',
        },

        slate: {
            icon: 'bg-slate-100 text-slate-700 ring-slate-200',
            border: 'border-slate-200',
            accent: 'bg-slate-500',
        },
    };

    const elements = {};

    function cacheElements() {
        elements.wrap = document.getElementById(
            'tsNotificationWrap'
        );

        elements.button = document.getElementById(
            'tsNotificationButton'
        );

        elements.badge = document.getElementById(
            'tsNotificationBadge'
        );

        elements.panel = document.getElementById(
            'tsNotificationPanel'
        );

        elements.list = document.getElementById(
            'tsNotificationList'
        );

        elements.empty = document.getElementById(
            'tsNotificationEmpty'
        );

        elements.emptyTitle = document.getElementById(
            'tsNotificationEmptyTitle'
        );

        elements.emptyText = document.getElementById(
            'tsNotificationEmptyText'
        );

        elements.markAll = document.getElementById(
            'tsNotificationMarkAll'
        );

        elements.refresh = document.getElementById(
            'tsNotificationRefresh'
        );

        elements.status = document.getElementById(
            'tsNotificationConnectionStatus'
        );

        elements.toastRegion = document.getElementById(
            'tsNotificationToastRegion'
        );
    }

    function setStatus(label, mode) {
        if (!elements.status) {
            return;
        }

        const classes = {
            live: ['bg-emerald-50', 'text-emerald-700'],
            syncing: ['bg-amber-50', 'text-amber-700'],
            offline: ['bg-rose-50', 'text-rose-700'],
        };

        elements.status.className =
            'inline-flex items-center rounded-full px-2.5 py-1 text-[10px] font-bold';

        (
            classes[mode] || classes.syncing
        ).forEach((className) => {
            elements.status.classList.add(className);
        });

        elements.status.textContent = label;
    }

    function setEmptyState(title, message) {
        if (elements.emptyTitle) {
            elements.emptyTitle.textContent =
                title || 'No notifications yet';
        }

        if (elements.emptyText) {
            elements.emptyText.textContent =
                message || 'No matching activity_logs rows were found.';
        }
    }

    function updateBadge(count) {
        if (!elements.badge) {
            return;
        }

        const total = Math.max(0, Number(count || 0));

        elements.badge.textContent =
            total > 99 ? '99+' : String(total);

        elements.badge.classList.toggle(
            'hidden',
            total === 0
        );

        if (elements.button) {
            elements.button.setAttribute(
                'aria-label',
                total > 0
                    ? `Open notifications, ${total} unread`
                    : 'Open notifications'
            );
        }
    }

    function createElement(tag, className, text) {
        const element = document.createElement(tag);

        if (className) {
            element.className = className;
        }

        if (typeof text === 'string') {
            element.textContent = text;
        }

        return element;
    }

    function itemTone(item) {
        return toneClasses[item.tone] || toneClasses.info;
    }

    function renderList(items) {
        state.items = Array.isArray(items) ? items : [];

        if (!elements.list || !elements.empty) {
            return;
        }

        elements.list.innerHTML = '';

        elements.empty.classList.toggle(
            'hidden',
            state.items.length > 0
        );

        state.items.forEach((item) => {
            const tone = itemTone(item);

            const row = createElement(
                'button',
                `group relative flex w-full items-start gap-3 border-b border-slate-100 px-4 py-4 text-left transition hover:bg-slate-50 ${
                    item.is_read
                        ? 'bg-white'
                        : 'bg-blue-50/35'
                }`
            );

            row.type = 'button';

            row.dataset.notificationId = String(
                item.id || 0
            );

            row.dataset.notificationUrl = item.url || '';

            const unreadBar = createElement(
                'span',
                `absolute inset-y-3 left-0 w-1 rounded-r-full ${
                    item.is_read
                        ? 'bg-transparent'
                        : tone.accent
                }`
            );

            row.appendChild(unreadBar);

            const icon = createElement(
                'span',
                `inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl text-lg ring-1 ${tone.icon}`,
                item.icon || '🔔'
            );

            row.appendChild(icon);

            const content = createElement(
                'span',
                'min-w-0 flex-1'
            );

            const top = createElement(
                'span',
                'flex items-start justify-between gap-3'
            );

            const title = createElement(
                'span',
                `block truncate text-[13px] ${
                    item.is_read
                        ? 'font-bold text-slate-800'
                        : 'font-black text-slate-950'
                }`,
                item.title
            );

            const time = createElement(
                'span',
                'shrink-0 text-[10px] font-semibold text-slate-400',
                item.time_ago || 'Just now'
            );

            top.append(title, time);

            const message = createElement(
                'span',
                'mt-1.5 block text-xs leading-5 text-slate-600',
                item.message
            );

            const meta = createElement(
                'span',
                'mt-2 flex flex-wrap items-center gap-2 text-[10px] font-bold uppercase tracking-[0.12em] text-slate-400'
            );

            if (item.order_no) {
                meta.appendChild(
                    createElement(
                        'span',
                        'rounded-full bg-slate-100 px-2 py-1 text-slate-600',
                        item.order_no
                    )
                );
            }

            if (item.category) {
                meta.appendChild(
                    createElement(
                        'span',
                        '',
                        item.category
                    )
                );
            }

            if (!item.is_read) {
                meta.appendChild(
                    createElement(
                        'span',
                        'rounded-full bg-blue-100 px-2 py-1 text-blue-700',
                        'Unread'
                    )
                );
            }

            content.append(top, message, meta);
            row.appendChild(content);

            row.addEventListener('click', async function () {
                if (!item.is_read) {
                    await markRead(item.id, false);
                }

                if (item.url) {
                    window.location.href = item.url;
                }
            });

            elements.list.appendChild(row);
        });
    }

    function showToast(item) {
        if (!elements.toastRegion || !item) {
            return;
        }

        const tone = itemTone(item);

        const toast = createElement(
            'div',
            `pointer-events-auto relative w-full translate-y-3 overflow-hidden rounded-[20px] border bg-white opacity-0 shadow-[0_24px_70px_rgba(15,23,42,.20)] transition duration-300 ${tone.border}`
        );

        toast.setAttribute('role', 'status');
        toast.setAttribute('aria-live', 'polite');

        const accent = createElement(
            'span',
            `absolute inset-y-0 left-0 w-1.5 ${tone.accent}`
        );

        const inner = createElement(
            'div',
            'flex items-start gap-3 p-4 pl-5'
        );

        const icon = createElement(
            'div',
            `inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl text-lg ring-1 ${tone.icon}`,
            item.icon || '🔔'
        );

        const body = createElement(
            'div',
            'min-w-0 flex-1'
        );

        const eyebrow = createElement(
            'div',
            'text-[10px] font-black uppercase tracking-[0.18em] text-slate-400',
            item.category
        );

        const title = createElement(
            'div',
            'mt-1 text-sm font-black text-slate-900',
            item.title
        );

        const message = createElement(
            'div',
            'mt-1 text-xs leading-5 text-slate-600',
            item.message
        );

        const footer = createElement(
            'div',
            'mt-2 flex flex-wrap items-center gap-2 text-[10px] font-bold text-slate-400'
        );

        if (item.order_no) {
            footer.appendChild(
                createElement(
                    'span',
                    'rounded-full bg-slate-100 px-2 py-1 text-slate-600',
                    item.order_no
                )
            );
        }

        footer.appendChild(
            createElement(
                'span',
                '',
                item.time_ago || 'Just now'
            )
        );

        body.append(eyebrow, title, message, footer);

        const close = createElement(
            'button',
            'inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-xl text-slate-400 transition hover:bg-slate-100 hover:text-slate-700',
            '×'
        );

        close.type = 'button';
        close.setAttribute(
            'aria-label',
            'Dismiss notification'
        );

        close.addEventListener('click', function (event) {
            event.stopPropagation();
            removeToast(toast);
        });

        inner.append(icon, body, close);
        toast.append(accent, inner);

        toast.addEventListener(
            'click',
            async function (event) {
                if (event.target === close) {
                    return;
                }

                await markRead(item.id, false);

                if (item.url) {
                    window.location.href = item.url;
                }
            }
        );

        elements.toastRegion.prepend(toast);

        while (elements.toastRegion.children.length > 4) {
            elements.toastRegion.lastElementChild.remove();
        }

        window.requestAnimationFrame(function () {
            toast.classList.remove(
                'translate-y-3',
                'opacity-0'
            );
        });

        window.setTimeout(function () {
            removeToast(toast);
        }, 7000);
    }

    function removeToast(toast) {
        if (!toast || !toast.isConnected) {
            return;
        }

        toast.classList.add(
            'translate-y-3',
            'opacity-0'
        );

        window.setTimeout(function () {
            toast.remove();
        }, 300);
    }

    async function request(url, options) {
        const response = await fetch(
            url,
            Object.assign(
                {
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                },
                options || {}
            )
        );

        const raw = await response.text();
        let payload = null;

        try {
            payload = raw ? JSON.parse(raw) : null;
        } catch (error) {
            const compact = String(raw || '')
                .replace(/<[^>]*>/g, ' ')
                .replace(/\s+/g, ' ')
                .trim()
                .slice(0, 220);

            throw new Error(
                `Notification endpoint returned HTTP ${response.status} instead of JSON${
                    compact ? ': ' + compact : '.'
                }`
            );
        }

        if (
            !response.ok
            || !payload
            || !payload.ok
        ) {
            throw new Error(
                payload && payload.message
                    ? payload.message
                    : `Notification request failed with HTTP ${response.status}.`
            );
        }

        return payload;
    }

    function formBody(values) {
        const body = new URLSearchParams();

        Object.keys(values || {}).forEach(function (key) {
            body.set(key, String(values[key]));
        });

        if (
            state.config
            && state.config.csrfToken
        ) {
            body.set(
                '_csrf',
                state.config.csrfToken
            );
        }

        return body.toString();
    }

    async function markRead(id, refreshAfter) {
        if (
            !state.config
            || !state.config.markReadUrl
            || !id
        ) {
            return;
        }

        try {
            const payload = await request(
                state.config.markReadUrl,
                {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type':
                            'application/x-www-form-urlencoded; charset=UTF-8',
                        'X-Requested-With':
                            'XMLHttpRequest',
                    },
                    body: formBody({ id: id }),
                }
            );

            updateBadge(payload.unread_count);

            state.items = state.items.map(function (item) {
                return Number(item.id) === Number(id)
                    ? Object.assign(
                        {},
                        item,
                        { is_read: true }
                    )
                    : item;
            });

            renderList(state.items);

            if (refreshAfter) {
                await poll(false);
            }
        } catch (error) {
            console.error(
                'Unable to mark notification read:',
                error
            );
        }
    }

    async function markAllRead() {
        if (
            !state.config
            || !state.config.markAllReadUrl
        ) {
            return;
        }

        if (elements.markAll) {
            elements.markAll.disabled = true;
            elements.markAll.textContent = 'Updating…';
        }

        try {
            const payload = await request(
                state.config.markAllReadUrl,
                {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type':
                            'application/x-www-form-urlencoded; charset=UTF-8',
                        'X-Requested-With':
                            'XMLHttpRequest',
                    },
                    body: formBody({}),
                }
            );

            state.items = state.items.map(function (item) {
                return Object.assign(
                    {},
                    item,
                    { is_read: true }
                );
            });

            renderList(state.items);
            updateBadge(payload.unread_count || 0);
        } catch (error) {
            console.error(
                'Unable to mark all notifications read:',
                error
            );
        } finally {
            if (elements.markAll) {
                elements.markAll.disabled = false;
                elements.markAll.textContent = 'Mark all read';
            }
        }
    }

    async function poll(showLoading) {
        if (
            !state.config
            || !state.config.pollUrl
            || state.polling
            || document.hidden
        ) {
            return;
        }

        state.polling = true;

        if (showLoading) {
            setStatus('Syncing', 'syncing');
        }

        try {
            const url = new URL(
                state.config.pollUrl,
                window.location.origin
            );

            url.searchParams.set(
                'limit',
                String(state.config.limit || 30)
            );

            if (state.latestId > 0) {
                url.searchParams.set(
                    'since_id',
                    String(state.latestId)
                );
            }

            const payload = await request(url.toString());

            if (
                payload.source_table !== 'activity_logs'
                || payload.delivery !== 'activity_logs_only_toast'
            ) {
                throw new Error(
                    'Rejected notification payload: activity_logs is the only permitted source.'
                );
            }

            const items = Array.isArray(payload.items)
                ? payload.items.filter(
                    (item) =>
                        Number(item && item.id) > 0
                        && String(item.action || '') !== ''
                        && String(item.message) !== ''
                )
                : [];

            const newItems = Array.isArray(payload.new_items)
                ? payload.new_items.filter(
                    (item) =>
                        Number(item && item.id) > 0
                        && String(item.action || '') !== ''
                        && String(item.message) !== ''
                )
                : [];

            if (items.length === 0) {
                const currentUserId = Number(
                    payload.current_user_id || 0
                );

                const matchedCount = Number(
                    payload.matched_count || 0
                );

                setEmptyState(
                    matchedCount > 0
                        ? 'No recent notifications'
                        : 'No matching activity logs',

                    matchedCount > 0
                        ? `${matchedCount} activity_logs row(s) matched, but none were returned in this page.`
                        : `No activity_logs rows match session user ID ${currentUserId || 'unknown'}.`
                );
            } else {
                setEmptyState(
                    'No notifications yet',
                    'No matching activity_logs rows were found.'
                );
            }

            renderList(items);
            updateBadge(payload.unread_count);

            if (state.firstLoadComplete) {
                newItems.forEach(function (item) {
                    showToast(item);
                });
            }

            state.latestId = Math.max(
                state.latestId,
                Number(payload.latest_id || 0)
            );

            state.firstLoadComplete = true;

            setStatus('Live', 'live');

            if (
                Number(payload.poll_interval_ms || 0)
                >= 5000
            ) {
                state.config.pollIntervalMs = Number(
                    payload.poll_interval_ms
                );
            }
        } catch (error) {
            setStatus('Offline', 'offline');

            state.items = [];
            renderList([]);

            setEmptyState(
                'Notification connection failed',
                error && error.message
                    ? error.message
                    : 'Unable to reach /notifications/poll.'
            );

            console.error(
                'Notification polling failed:',
                error
            );
        } finally {
            state.polling = false;
            scheduleNextPoll();
        }
    }

    function scheduleNextPoll() {
        window.clearTimeout(state.timer);

        if (!state.config) {
            return;
        }

        state.timer = window.setTimeout(function () {
            poll(false);
        }, Math.max(
            5000,
            Number(state.config.pollIntervalMs || 12000)
        ));
    }

    function bindEvents() {
        if (
            elements.button
            && elements.panel
            && elements.wrap
        ) {
            elements.button.addEventListener(
                'click',
                function (event) {
                    event.stopPropagation();
                    elements.panel.classList.toggle('hidden');
                }
            );

            document.addEventListener(
                'click',
                function (event) {
                    if (
                        !elements.wrap.contains(event.target)
                    ) {
                        elements.panel.classList.add('hidden');
                    }
                }
            );
        }

        if (elements.markAll) {
            elements.markAll.addEventListener(
                'click',
                markAllRead
            );
        }

        if (elements.refresh) {
            elements.refresh.addEventListener(
                'click',
                function () {
                    poll(true);
                }
            );
        }

        document.addEventListener(
            'visibilitychange',
            function () {
                if (!document.hidden) {
                    poll(true);
                }
            }
        );

        window.addEventListener(
            'focus',
            function () {
                poll(false);
            }
        );
    }

    function init(config) {
        if (state.initialized) {
            return;
        }

        state.config = Object.assign(
            {
                limit: 30,
                pollIntervalMs: 12000,
            },
            config || {}
        );

        cacheElements();

        if (
            !elements.button
            || !elements.panel
            || !elements.toastRegion
        ) {
            return;
        }

        state.initialized = true;

        bindEvents();
        setStatus('Syncing', 'syncing');
        poll(true);
    }

    window.TaxSaathiActivityNotifications = {
        init: init,

        refresh: function () {
            return poll(true);
        },
    };
}(window, document));

<?php if ($notificationsEnabled): ?>

window.TaxSaathiActivityNotifications
    && window.TaxSaathiActivityNotifications.init({
        pollUrl: <?= json_encode($notificationsPollUrl) ?>,
        markReadUrl: <?= json_encode($notificationsMarkReadUrl) ?>,
        markUnreadUrl: <?= json_encode($notificationsMarkUnreadUrl) ?>,
        markAllReadUrl: <?= json_encode($notificationsMarkAllReadUrl) ?>,
        csrfToken: <?= json_encode($csrfTokenForJs) ?>,
        pollIntervalMs: 12000,
        limit: 30
    });

<?php endif; ?>

(function () {
    const panel = document.getElementById(
        'sidebarPanel'
    );

    const backdrop = document.getElementById(
        'sidebarBackdrop'
    );

    const toggle = document.getElementById(
        'sidebarToggle'
    );

    const close = document.getElementById(
        'sidebarClose'
    );

    function openSidebar() {
        if (!panel || !backdrop) {
            return;
        }

        panel.classList.remove('-translate-x-full');
        backdrop.classList.remove('hidden');
    }

    function closeSidebar() {
        if (!panel || !backdrop) {
            return;
        }

        panel.classList.add('-translate-x-full');
        backdrop.classList.add('hidden');
    }

    if (toggle) {
        toggle.addEventListener(
            'click',
            openSidebar
        );
    }

    if (close) {
        close.addEventListener(
            'click',
            closeSidebar
        );
    }

    if (backdrop) {
        backdrop.addEventListener(
            'click',
            closeSidebar
        );
    }

    const btn = document.getElementById(
        'profileDropdownBtn'
    );

    const menu = document.getElementById(
        'profileDropdownMenu'
    );

    const wrap = document.getElementById(
        'profileDropdownWrap'
    );

    if (btn && menu && wrap) {
        btn.addEventListener(
            'click',
            function (event) {
                event.stopPropagation();
                menu.classList.toggle('hidden');
            }
        );

        document.addEventListener(
            'click',
            function (event) {
                if (!wrap.contains(event.target)) { 
                    menu.classList.add('hidden');
                }
            }
        );
    }
})();
</script>

<?php if (
    $supportChatWidgetEnabled
    && is_file($supportChatWidgetPath)
): ?>
    <?php require $supportChatWidgetPath; ?>
<?php endif; ?>

</body>
</html>
