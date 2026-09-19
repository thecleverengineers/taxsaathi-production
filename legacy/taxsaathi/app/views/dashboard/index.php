<?php
declare(strict_types=1);

$statCards = is_array($statCards ?? null) ? array_values($statCards) : [];
$dashboardSections = is_array($dashboardSections ?? null) ? array_values($dashboardSections) : [];
$quickActions = is_array($quickActions ?? null) ? array_values($quickActions) : [];
$recentOrders = is_array($recentOrders ?? null) ? array_values($recentOrders) : [];
$statusSummary = is_array($statusSummary ?? null) ? array_values($statusSummary) : [];
$chartData = is_array($chartData ?? null) ? $chartData : [];
$analytics = is_array($analytics ?? null) ? $analytics : [];
$filters = is_array($filters ?? null) ? $filters : [];
$paymentStatuses = is_array($paymentStatuses ?? null) ? $paymentStatuses : [];
$partners = is_array($partners ?? null) ? array_values($partners) : [];
$executives = is_array($executives ?? null) ? array_values($executives) : [];
$roleNames = is_array($roleNames ?? null) ? array_values($roleNames) : [];
$canFilterByPartner = (bool) ($canFilterByPartner ?? false);
$canFilterByExecutive = (bool) ($canFilterByExecutive ?? false);
$isAdminOrManager = (bool) ($isAdminOrManager ?? false);
$isExecutive = (bool) ($isExecutive ?? false);
$isPartner = (bool) ($isPartner ?? false);
$isClient = (bool) ($isClient ?? false);
$isMultiRole = (bool) ($isMultiRole ?? false);

$monthly = is_array($chartData['monthly'] ?? null) ? array_values($chartData['monthly']) : [];
$paymentChart = is_array($chartData['payment'] ?? null) ? array_values($chartData['payment']) : [];
$orderStatusChart = is_array($chartData['order_status'] ?? null) ? array_values($chartData['order_status']) : [];
$serviceChart = is_array($chartData['services'] ?? null) ? array_values($chartData['services']) : [];
$sourceChart = is_array($chartData['sources'] ?? null) ? array_values($chartData['sources']) : [];

$money = static function (mixed $value): string {
    if (function_exists('format_money')) {
        return (string) format_money($value);
    }

    return '₹' . number_format((float) $value, 2);
};

$compactMoney = static function (mixed $value): string {
    $amount = (float) $value;
    $absolute = abs($amount);

    if ($absolute >= 10000000) {
        return '₹' . number_format($amount / 10000000, 2) . ' Cr';
    }
    if ($absolute >= 100000) {
        return '₹' . number_format($amount / 100000, 2) . ' L';
    }
    if ($absolute >= 1000) {
        return '₹' . number_format($amount / 1000, 1) . ' K';
    }

    return '₹' . number_format($amount, 0);
};

$dateLabel = static function (mixed $value): string {
    $raw = trim((string) $value);
    if ($raw === '' || $raw === '0000-00-00' || $raw === '0000-00-00 00:00:00') {
        return '-';
    }
    $time = strtotime($raw);
    return $time ? date('d M Y', $time) : $raw;
};

$statusLabel = static function (string $status) use ($paymentStatuses): string {
    $key = strtolower(trim($status));
    $key = str_replace([' ', '-'], '_', $key);
    return $paymentStatuses[$key] ?? ucwords(str_replace('_', ' ', $key));
};

$statusTone = static function (string $status): string {
    return match (strtolower(trim($status))) {
        'paid', 'verified', 'success', 'completed', 'approved', 'closed' => 'dbx-pill--success',
        'pending', 'pending_review', 'waiting_for_payment', 'processing', 'work_in_progress', 'partial' => 'dbx-pill--warning',
        'unpaid', 'failed', 'cancelled', 'rejected', 'overdue' => 'dbx-pill--danger',
        default => 'dbx-pill--neutral',
    };
};

$iconSvg = static function (string $icon): string {
    return match ($icon) {
        'revenue' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 2v20M17 5.5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" stroke-linecap="round"/></svg>',
        'paid' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 6 9 17l-5-5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'pending' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'completed' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M7 3h10v4H7zM5 7h14v14H5z"/><path d="m8 14 2.5 2.5L16 11" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'average' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19V9m5 10V5m5 14v-7m5 7V3" stroke-linecap="round"/></svg>',
        default => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 5h16v14H4z"/><path d="M8 9h8M8 13h8M8 17h5" stroke-linecap="round"/></svg>',
    };
};

$activeFilters = 0;
foreach (['date_from', 'date_to', 'partner_id', 'executive_id'] as $filterKey) {
    if (!empty($filters[$filterKey])) {
        $activeFilters++;
    }
}
if (($filters['source'] ?? 'all') !== 'all') {
    $activeFilters++;
}
if (($filters['payment_status'] ?? 'all') !== 'all') {
    $activeFilters++;
}

$maxMonthlyOrders = 1;
$maxMonthlyAmount = 1.0;
foreach ($monthly as $item) {
    $maxMonthlyOrders = max($maxMonthlyOrders, (int) ($item['orders'] ?? 0));
    $maxMonthlyAmount = max($maxMonthlyAmount, (float) ($item['amount'] ?? 0));
}

$paymentTotal = 0;
foreach ($paymentChart as $item) {
    $paymentTotal += (int) ($item['total'] ?? $item['count'] ?? 0);
}

$paymentColors = [
    'pending' => '#F59E0B',
    'pending_review' => '#FB923C',
    'verified' => '#06B6D4',
    'paid' => '#10B981',
    'partial' => '#6366F1',
    'unpaid' => '#EF4444',
    'failed' => '#BE123C',
];

$donutStops = [];
$cursor = 0.0;
if ($paymentTotal > 0) {
    foreach ($paymentChart as $item) {
        $count = (int) ($item['total'] ?? $item['count'] ?? 0);
        if ($count <= 0) {
            continue;
        }
        $key = strtolower((string) ($item['status'] ?? 'unknown'));
        $color = $paymentColors[$key] ?? '#94A3B8';
        $start = $cursor;
        $cursor += ($count / $paymentTotal) * 100;
        $donutStops[] = $color . ' ' . number_format($start, 2, '.', '') . '% ' . number_format($cursor, 2, '.', '') . '%';
    }
}
$donutGradient = $donutStops !== [] ? 'conic-gradient(' . implode(', ', $donutStops) . ')' : 'conic-gradient(#e2e8f0 0 100%)';

$maxOrderStatus = 1;
foreach ($orderStatusChart as $item) {
    $maxOrderStatus = max($maxOrderStatus, (int) ($item['total'] ?? 0));
}

$maxServiceAmount = 1.0;
foreach ($serviceChart as $item) {
    $maxServiceAmount = max($maxServiceAmount, (float) ($item['amount'] ?? 0));
}

$totalSourceRecords = 0;
foreach ($sourceChart as $item) {
    $totalSourceRecords += (int) ($item['total'] ?? 0);
}
?>

<style>
/*
 * Premium responsive dashboard layer
 * - Desktop: dense analytics workspace
 * - Tablet: balanced two-column information architecture
 * - Android/iOS: touch-first controls, safe-area support and card-based tables
 */
.dbx {
    --dbx-ink: #0b1220;
    --dbx-ink-soft: #334155;
    --dbx-muted: #64748b;
    --dbx-faint: #94a3b8;
    --dbx-line: rgba(148, 163, 184, .22);
    --dbx-line-strong: rgba(148, 163, 184, .34);
    --dbx-surface: rgba(255, 255, 255, .96);
    --dbx-surface-soft: #f8fafc;
    --dbx-navy: #06142c;
    --dbx-blue: #0b4f82;
    --dbx-cyan: #06b6d4;
    --dbx-indigo: #4f46e5;
    --dbx-emerald: #059669;
    --dbx-radius-xl: 28px;
    --dbx-radius-lg: 22px;
    --dbx-radius-md: 16px;
    --dbx-shadow-card: 0 18px 50px rgba(15, 23, 42, .07), 0 2px 8px rgba(15, 23, 42, .025);
    --dbx-shadow-hover: 0 24px 60px rgba(15, 23, 42, .11), 0 4px 12px rgba(15, 23, 42, .035);
    width: 100%;
    max-width: 1680px;
    margin-inline: auto;
    padding-bottom: max(4px, env(safe-area-inset-bottom));
    display: grid;
    gap: 18px;
    color: var(--dbx-ink);
    font-family: Inter, ui-sans-serif, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    font-synthesis: none;
    -webkit-font-smoothing: antialiased;
    text-rendering: optimizeLegibility;
}

.dbx *,
.dbx *::before,
.dbx *::after {
    box-sizing: border-box;
}

.dbx a {
    color: inherit;
    text-decoration: none;
}

.dbx button,
.dbx input,
.dbx select {
    font: inherit;
}

.dbx-card {
    border: 1px solid var(--dbx-line);
    background: var(--dbx-surface);
    border-radius: var(--dbx-radius-lg);
    box-shadow: var(--dbx-shadow-card);
    backdrop-filter: blur(18px);
    -webkit-backdrop-filter: blur(18px);
}

.dbx-hero {
    position: relative;
    isolation: isolate;
    overflow: hidden;
    padding: clamp(24px, 3vw, 38px);
    min-height: 180px;
    display: grid;
    align-items: end;
    color: #fff;
    border: 1px solid rgba(255, 255, 255, .12);
    border-radius: 32px;
    background:
        radial-gradient(circle at 88% 8%, rgba(34, 211, 238, .35), transparent 27%),
        radial-gradient(circle at 72% 108%, rgba(99, 102, 241, .34), transparent 38%),
        linear-gradient(130deg, #050d1e 0%, #08284d 46%, #075b72 100%);
    box-shadow: 0 30px 80px rgba(4, 35, 66, .24);
}

.dbx-hero::before {
    content: "";
    position: absolute;
    z-index: -2;
    width: 240px;
    height: 240px;
    right: -70px;
    top: -100px;
    border-radius: 50%;
    border: 1px solid rgba(255, 255, 255, .11);
    box-shadow:
        0 0 0 36px rgba(255, 255, 255, .025),
        0 0 0 76px rgba(255, 255, 255, .018);
}

.dbx-hero::after {
    content: "";
    position: absolute;
    z-index: -1;
    inset: 0;
    pointer-events: none;
    background-image:
        linear-gradient(rgba(255, 255, 255, .035) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255, 255, 255, .035) 1px, transparent 1px);
    background-size: 30px 30px;
    -webkit-mask-image: linear-gradient(to left, #000, transparent 76%);
    mask-image: linear-gradient(to left, #000, transparent 76%);
}

.dbx-hero__grid {
    position: relative;
    z-index: 1;
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(260px, auto);
    gap: 30px;
    align-items: end;
}

.dbx-eyebrow {
    font-size: 10px;
    font-weight: 900;
    letter-spacing: .25em;
    text-transform: uppercase;
    color: #a5f3fc;
}

.dbx-title {
    max-width: 850px;
    margin: 9px 0 0;
    font-size: clamp(30px, 3.4vw, 48px);
    line-height: 1.02;
    letter-spacing: -.05em;
    font-weight: 900;
    text-wrap: balance;
}

.dbx-subtitle {
    max-width: 780px;
    margin: 12px 0 0;
    color: rgba(226, 232, 240, .84);
    font-size: clamp(13px, 1vw, 15px);
    line-height: 1.72;
}

.dbx-badges {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 8px;
}

.dbx-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-height: 34px;
    padding: 7px 12px;
    border: 1px solid rgba(255, 255, 255, .15);
    border-radius: 999px;
    background: rgba(255, 255, 255, .09);
    color: #f8fafc;
    font-size: 11px;
    line-height: 1.2;
    font-weight: 760;
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
}

.dbx-badge i {
    width: 7px;
    height: 7px;
    flex: 0 0 auto;
    border-radius: 50%;
    background: #67e8f9;
    box-shadow: 0 0 0 4px rgba(103, 232, 249, .12), 0 0 16px rgba(103, 232, 249, .65);
}

.dbx-filter {
    padding: 16px;
    background: linear-gradient(180deg, rgba(255, 255, 255, .99), rgba(248, 250, 252, .94));
}

.dbx-filter__head,
.dbx-panel__head,
.dbx-table-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
}

.dbx-filter__head {
    margin-bottom: 13px;
}

.dbx-filter__title {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
    font-size: 13px;
    font-weight: 900;
}

.dbx-filter__title span {
    width: 32px;
    height: 32px;
    flex: 0 0 auto;
    display: grid;
    place-items: center;
    border: 1px solid rgba(99, 102, 241, .12);
    border-radius: 11px;
    color: #4f46e5;
    background: linear-gradient(145deg, #f5f3ff, #eef2ff);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, .9);
}

.dbx-filter__count,
.dbx-panel__meta {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 28px;
    padding: 6px 10px;
    border: 1px solid rgba(148, 163, 184, .14);
    border-radius: 999px;
    background: #f1f5f9;
    color: #64748b;
    font-size: 10px;
    line-height: 1;
    font-weight: 850;
    white-space: nowrap;
}

.dbx-filter__count {
    letter-spacing: .07em;
    text-transform: uppercase;
}

.dbx-filter__grid {
    display: grid;
    grid-template-columns: repeat(6, minmax(135px, 1fr));
    gap: 10px;
    align-items: end;
}

.dbx-field {
    min-width: 0;
    display: grid;
    gap: 6px;
}

.dbx-field label,
.dbx-field > span {
    padding-left: 2px;
    color: #64748b;
    font-size: 9px;
    line-height: 1.2;
    font-weight: 900;
    letter-spacing: .1em;
    text-transform: uppercase;
}

.dbx-control {
    width: 100%;
    min-width: 0;
    height: 42px;
    padding: 0 12px;
    border: 1px solid #dbe4ee;
    border-radius: 13px;
    outline: none;
    background: rgba(255, 255, 255, .98);
    color: #1e293b;
    font-size: 12px;
    font-weight: 650;
    box-shadow: inset 0 1px 1px rgba(15, 23, 42, .02);
    transition: border-color .2s ease, box-shadow .2s ease, background .2s ease;
}

.dbx-control:focus {
    border-color: #38bdf8;
    box-shadow: 0 0 0 4px rgba(56, 189, 248, .12), 0 7px 18px rgba(15, 23, 42, .04);
}

.dbx-control[disabled] {
    cursor: not-allowed;
    background: #f1f5f9;
    color: #64748b;
}

.dbx-filter__actions {
    display: flex;
    align-items: flex-end;
    gap: 8px;
}

.dbx-btn {
    min-width: 0;
    height: 42px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 0 15px;
    border: 0;
    border-radius: 13px;
    cursor: pointer;
    white-space: nowrap;
    font-size: 11px;
    line-height: 1;
    font-weight: 900;
    transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease, background .2s ease;
    -webkit-tap-highlight-color: transparent;
    touch-action: manipulation;
}

.dbx-btn--ghost {
    border: 1px solid #dbe4ee;
    background: #fff;
    color: #475569;
}

.dbx-btn--primary {
    color: #fff;
    background: linear-gradient(135deg, #0f172a, #1e3a5f);
    box-shadow: 0 10px 22px rgba(15, 23, 42, .17);
}

.dbx-kpis {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 12px;
}

.dbx-kpi {
    --accent: #4f46e5;
    --tint: #eef2ff;
    position: relative;
    isolation: isolate;
    overflow: hidden;
    min-width: 0;
    min-height: 138px;
    padding: 17px;
    transition: transform .22s ease, box-shadow .22s ease, border-color .22s ease;
    -webkit-tap-highlight-color: transparent;
}

.dbx-kpi::before {
    content: "";
    position: absolute;
    z-index: 1;
    inset: 0 0 auto;
    height: 3px;
    background: linear-gradient(90deg, var(--accent), color-mix(in srgb, var(--accent) 55%, #fff));
}

.dbx-kpi::after {
    content: "";
    position: absolute;
    z-index: -1;
    width: 110px;
    height: 110px;
    right: -52px;
    bottom: -60px;
    border-radius: 50%;
    background: var(--tint);
    opacity: .85;
}

.dbx-kpi__top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.dbx-kpi__icon {
    width: 38px;
    height: 38px;
    flex: 0 0 auto;
    display: grid;
    place-items: center;
    border: 1px solid color-mix(in srgb, var(--accent) 15%, transparent);
    border-radius: 12px;
    color: var(--accent);
    background: var(--tint);
}

.dbx-kpi__icon svg {
    width: 19px;
    height: 19px;
}

.dbx-kpi__label {
    min-width: 0;
    color: #64748b;
    font-size: 9px;
    line-height: 1.3;
    font-weight: 900;
    letter-spacing: .11em;
    text-transform: uppercase;
}

.dbx-kpi__value {
    min-width: 0;
    margin-top: 16px;
    overflow-wrap: anywhere;
    color: #0f172a;
    font-size: clamp(22px, 2vw, 30px);
    line-height: 1;
    font-weight: 900;
    letter-spacing: -.045em;
}

.dbx-kpi__note {
    margin-top: 9px;
    overflow: hidden;
    color: #94a3b8;
    font-size: 10px;
    line-height: 1.45;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.dbx-kpi--emerald { --accent: #059669; --tint: #ecfdf5; }
.dbx-kpi--amber { --accent: #d97706; --tint: #fffbeb; }
.dbx-kpi--cyan { --accent: #0891b2; --tint: #ecfeff; }
.dbx-kpi--violet { --accent: #7c3aed; --tint: #f5f3ff; }
.dbx-kpi--slate { --accent: #334155; --tint: #f1f5f9; }
.dbx-kpi--indigo { --accent: #4f46e5; --tint: #eef2ff; }

.dbx-status-strip {
    display: flex;
    align-items: stretch;
    overflow-x: auto;
    overscroll-behavior-inline: contain;
    scrollbar-width: thin;
    scrollbar-color: #cbd5e1 transparent;
    scroll-snap-type: x proximity;
    -webkit-overflow-scrolling: touch;
}

.dbx-status-strip::-webkit-scrollbar,
.dbx-table-scroll::-webkit-scrollbar,
.dbx-month-chart::-webkit-scrollbar {
    width: 7px;
    height: 7px;
}

.dbx-status-strip::-webkit-scrollbar-thumb,
.dbx-table-scroll::-webkit-scrollbar-thumb,
.dbx-month-chart::-webkit-scrollbar-thumb {
    border-radius: 999px;
    background: #cbd5e1;
}

.dbx-status-item {
    min-width: 155px;
    flex: 1 0 auto;
    padding: 13px 15px;
    border-right: 1px solid #edf2f7;
    scroll-snap-align: start;
}

.dbx-status-item:last-child {
    border-right: 0;
}

.dbx-status-item__top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 9px;
}

.dbx-status-item__name {
    color: #475569;
    font-size: 10px;
    line-height: 1.35;
    font-weight: 850;
}

.dbx-status-item__count {
    color: #0f172a;
    font-size: 16px;
    line-height: 1;
    font-weight: 900;
}

.dbx-status-item__amount {
    margin-top: 5px;
    color: #94a3b8;
    font-size: 9px;
    font-weight: 650;
}

.dbx-dot {
    width: 8px;
    height: 8px;
    flex: 0 0 auto;
    border-radius: 50%;
    background: #94a3b8;
}

.dbx-dot--paid,
.dbx-dot--verified { background: #10b981; }
.dbx-dot--pending,
.dbx-dot--pending_review { background: #f59e0b; }
.dbx-dot--partial { background: #6366f1; }
.dbx-dot--unpaid,
.dbx-dot--failed { background: #ef4444; }

.dbx-main-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.55fr) minmax(330px, .85fr);
    gap: 15px;
}

.dbx-secondary-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 15px;
}

.dbx-panel {
    min-width: 0;
    padding: 19px;
}

.dbx-panel__head {
    align-items: flex-start;
    margin-bottom: 17px;
}

.dbx-panel__eyebrow {
    color: #94a3b8;
    font-size: 9px;
    line-height: 1.2;
    font-weight: 900;
    letter-spacing: .13em;
    text-transform: uppercase;
}

.dbx-panel__title {
    margin-top: 5px;
    color: #0f172a;
    font-size: 17px;
    line-height: 1.25;
    font-weight: 900;
    letter-spacing: -.025em;
}

.dbx-month-chart {
    height: 230px;
    display: flex;
    align-items: end;
    gap: 8px;
    overflow-x: auto;
    overscroll-behavior-inline: contain;
    padding: 17px 7px 2px;
    border-top: 1px solid #f1f5f9;
    background: repeating-linear-gradient(to top, transparent 0, transparent 44px, #f1f5f9 45px);
    scroll-snap-type: x proximity;
    -webkit-overflow-scrolling: touch;
}

.dbx-month {
    min-width: 29px;
    height: 100%;
    flex: 1 0 29px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-end;
    gap: 7px;
    scroll-snap-align: center;
}

.dbx-month__barbox {
    width: 100%;
    height: 155px;
    display: flex;
    align-items: end;
    justify-content: center;
}

.dbx-month__bar {
    position: relative;
    width: min(27px, 72%);
    min-height: 4px;
    border-radius: 9px 9px 3px 3px;
    background: linear-gradient(180deg, #22d3ee 0%, #4f46e5 100%);
    box-shadow: 0 10px 20px rgba(79, 70, 229, .17);
    transition: transform .22s ease, filter .22s ease;
}

.dbx-month__value {
    color: #475569;
    font-size: 9px;
    line-height: 1;
    font-weight: 900;
}

.dbx-month__label {
    min-height: 27px;
    color: #94a3b8;
    font-size: 8px;
    line-height: 1;
    font-weight: 850;
    white-space: nowrap;
    transform: rotate(-35deg);
    transform-origin: center;
}

.dbx-donut-wrap {
    display: grid;
    grid-template-columns: 154px minmax(0, 1fr);
    gap: 22px;
    align-items: center;
}

.dbx-donut {
    width: 152px;
    height: 152px;
    display: grid;
    place-items: center;
    border-radius: 50%;
    background: var(--donut);
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .45), 0 14px 35px rgba(15, 23, 42, .08);
}

.dbx-donut::before {
    content: "";
    grid-area: 1 / 1;
    width: 94px;
    height: 94px;
    border: 1px solid #eef2f7;
    border-radius: 50%;
    background: #fff;
    box-shadow: 0 7px 20px rgba(15, 23, 42, .05);
}

.dbx-donut__center {
    position: relative;
    grid-area: 1 / 1;
    text-align: center;
}

.dbx-donut__center strong {
    display: block;
    color: #0f172a;
    font-size: 26px;
    line-height: 1;
    font-weight: 900;
    letter-spacing: -.04em;
}

.dbx-donut__center span {
    display: block;
    margin-top: 5px;
    color: #94a3b8;
    font-size: 9px;
    font-weight: 850;
    letter-spacing: .09em;
    text-transform: uppercase;
}

.dbx-legend {
    min-width: 0;
    display: grid;
    gap: 9px;
}

.dbx-legend__row {
    min-width: 0;
    display: grid;
    grid-template-columns: 9px minmax(0, 1fr) auto;
    gap: 9px;
    align-items: center;
    font-size: 10px;
}

.dbx-legend__swatch {
    width: 9px;
    height: 9px;
    border-radius: 3px;
}

.dbx-legend__label {
    min-width: 0;
    overflow: hidden;
    color: #475569;
    font-weight: 750;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.dbx-legend__value {
    color: #0f172a;
    font-weight: 900;
}

.dbx-bars {
    display: grid;
    gap: 13px;
}

.dbx-bar-row {
    min-width: 0;
    display: grid;
    gap: 7px;
}

.dbx-bar-row__head {
    min-width: 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 11px;
}

.dbx-bar-row__label {
    min-width: 0;
    overflow: hidden;
    color: #475569;
    font-size: 10px;
    line-height: 1.35;
    font-weight: 800;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.dbx-bar-row__value {
    flex: 0 0 auto;
    color: #0f172a;
    font-size: 10px;
    font-weight: 900;
}

.dbx-bar-track {
    height: 8px;
    overflow: hidden;
    border-radius: 999px;
    background: #eef2f7;
    box-shadow: inset 0 1px 2px rgba(15, 23, 42, .04);
}

.dbx-bar-fill {
    min-width: 3px;
    height: 100%;
    border-radius: inherit;
    background: linear-gradient(90deg, #0891b2, #4f46e5);
    box-shadow: 0 0 15px rgba(79, 70, 229, .18);
}

.dbx-bar-fill--service {
    background: linear-gradient(90deg, #059669, #14b8a6);
    box-shadow: 0 0 15px rgba(20, 184, 166, .17);
}

.dbx-source-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
}

.dbx-source {
    min-width: 0;
    padding: 14px;
    border: 1px solid #edf2f7;
    border-radius: 17px;
    background: linear-gradient(145deg, #fbfdff, #f8fafc);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, .9);
}

.dbx-source__label {
    color: #64748b;
    font-size: 9px;
    line-height: 1.3;
    font-weight: 900;
    letter-spacing: .08em;
    text-transform: uppercase;
}

.dbx-source__value {
    min-width: 0;
    margin-top: 7px;
    overflow-wrap: anywhere;
    color: #0f172a;
    font-size: 22px;
    line-height: 1.05;
    font-weight: 900;
    letter-spacing: -.035em;
}

.dbx-source__amount {
    margin-top: 5px;
    color: #94a3b8;
    font-size: 10px;
    line-height: 1.4;
}

.dbx-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 9px;
}

.dbx-action {
    min-height: 42px;
    display: inline-flex;
    align-items: center;
    gap: 9px;
    padding: 7px 14px;
    border: 1px solid #dbe4ee;
    border-radius: 13px;
    background: #fff;
    color: #334155;
    font-size: 11px;
    line-height: 1.25;
    font-weight: 850;
    transition: transform .2s ease, border-color .2s ease, background .2s ease, color .2s ease;
    -webkit-tap-highlight-color: transparent;
}

.dbx-action__icon {
    width: 24px;
    height: 24px;
    flex: 0 0 auto;
    display: grid;
    place-items: center;
    border-radius: 8px;
    background: #f1f5f9;
    color: #475569;
}

.dbx-sections {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(290px, 1fr));
    gap: 15px;
}

.dbx-section {
    min-width: 0;
    padding: 18px;
}

.dbx-section__title {
    color: #0f172a;
    font-size: 15px;
    line-height: 1.3;
    font-weight: 900;
    letter-spacing: -.02em;
}

.dbx-section__subtitle {
    margin-top: 5px;
    color: #94a3b8;
    font-size: 10px;
    line-height: 1.45;
}

.dbx-section__stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 9px;
    margin-top: 14px;
}

.dbx-mini {
    min-width: 0;
    padding: 12px;
    border: 1px solid #eef2f7;
    border-radius: 15px;
    background: #f8fafc;
    transition: transform .2s ease, border-color .2s ease, background .2s ease;
}

.dbx-mini__label {
    min-width: 0;
    overflow: hidden;
    color: #94a3b8;
    font-size: 8px;
    line-height: 1.3;
    font-weight: 900;
    letter-spacing: .08em;
    text-overflow: ellipsis;
    text-transform: uppercase;
    white-space: nowrap;
}

.dbx-mini__value {
    min-width: 0;
    margin-top: 7px;
    overflow-wrap: anywhere;
    color: #0f172a;
    font-size: 18px;
    line-height: 1;
    font-weight: 900;
    letter-spacing: -.025em;
}

.dbx-table-wrap {
    overflow: hidden;
}

.dbx-table-head {
    padding: 17px 19px;
    border-bottom: 1px solid #eef2f7;
}

.dbx-table-scroll {
    overflow: auto;
    overscroll-behavior: contain;
    -webkit-overflow-scrolling: touch;
}

.dbx-table {
    width: 100%;
    min-width: 1020px;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 11px;
}

.dbx-table th {
    position: sticky;
    z-index: 2;
    top: 0;
    padding: 12px 13px;
    background: #0f172a;
    color: #cbd5e1;
    text-align: left;
    font-size: 8px;
    line-height: 1.2;
    font-weight: 900;
    letter-spacing: .11em;
    text-transform: uppercase;
    white-space: nowrap;
}

.dbx-table td {
    padding: 12px 13px;
    border-bottom: 1px solid #f1f5f9;
    background: rgba(255, 255, 255, .88);
    color: #475569;
    line-height: 1.45;
    vertical-align: middle;
}

.dbx-table tbody tr:last-child td {
    border-bottom: 0;
}

.dbx-table .num {
    color: #0f172a;
    text-align: right;
    font-variant-numeric: tabular-nums;
    font-weight: 900;
    white-space: nowrap;
}

.dbx-order {
    color: #0f172a;
    font-weight: 900;
    white-space: nowrap;
}

.dbx-muted {
    color: #94a3b8;
}

.dbx-pill {
    min-height: 22px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 4px 9px;
    border: 1px solid transparent;
    border-radius: 999px;
    font-size: 8px;
    line-height: 1.15;
    font-weight: 900;
    white-space: nowrap;
}

.dbx-pill--success {
    border-color: rgba(16, 185, 129, .14);
    background: #ecfdf5;
    color: #047857;
}

.dbx-pill--warning {
    border-color: rgba(245, 158, 11, .16);
    background: #fffbeb;
    color: #b45309;
}

.dbx-pill--danger {
    border-color: rgba(244, 63, 94, .14);
    background: #fff1f2;
    color: #be123c;
}

.dbx-pill--neutral {
    border-color: rgba(100, 116, 139, .12);
    background: #f1f5f9;
    color: #475569;
}

.dbx-empty {
    width: 100%;
    padding: 40px 20px;
    color: #94a3b8;
    text-align: center;
    font-size: 12px;
    line-height: 1.55;
}

.dbx :focus-visible {
    outline: 3px solid rgba(14, 165, 233, .35);
    outline-offset: 3px;
}

/* Desktop and precision-pointer polish */
@media (hover: hover) and (pointer: fine) {
    .dbx-card,
    .dbx-kpi,
    .dbx-action,
    .dbx-mini,
    .dbx-btn,
    .dbx-month__bar,
    .dbx-table tbody tr td {
        transition-duration: .2s;
    }

    .dbx-kpi:hover {
        transform: translateY(-4px);
        border-color: color-mix(in srgb, var(--accent) 24%, #e2e8f0);
        box-shadow: var(--dbx-shadow-hover);
    }

    .dbx-btn:hover,
    .dbx-action:hover,
    .dbx-mini:hover {
        transform: translateY(-2px);
    }

    .dbx-btn--ghost:hover {
        border-color: #a5b4fc;
        background: #f8faff;
        color: #4338ca;
    }

    .dbx-btn--primary:hover {
        box-shadow: 0 14px 28px rgba(15, 23, 42, .23);
    }

    .dbx-action:hover,
    .dbx-mini:hover {
        border-color: #c7d2fe;
        background: #f5f7ff;
        color: #4338ca;
    }

    .dbx-month__bar:hover {
        filter: brightness(1.07) saturate(1.08);
        transform: translateY(-3px);
    }

    .dbx-table tbody tr:hover td {
        background: #f8fbff;
    }
}

/* Wide laptop */
@media (max-width: 1400px) {
    .dbx-kpis {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .dbx-filter__grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .dbx-filter__actions {
        grid-column: auto;
    }

    .dbx-main-grid {
        grid-template-columns: minmax(0, 1.35fr) minmax(310px, .85fr);
    }
}

/* Tablet landscape / compact laptop */
@media (max-width: 1100px) {
    .dbx {
        gap: 15px;
    }

    .dbx-hero__grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }

    .dbx-badges {
        justify-content: flex-start;
    }

    .dbx-main-grid {
        grid-template-columns: 1fr;
    }

    .dbx-donut-wrap {
        grid-template-columns: 180px minmax(0, 1fr);
    }

    .dbx-section__stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

/* Tablet portrait */
@media (max-width: 820px) {
    .dbx-filter__grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .dbx-filter__actions {
        grid-column: 1 / -1;
    }

    .dbx-filter__actions .dbx-btn {
        flex: 1 1 0;
    }

    .dbx-kpis {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .dbx-secondary-grid {
        grid-template-columns: 1fr;
    }
}

/* Android + iOS phone layout */
@media (max-width: 767px) {
    .dbx {
        gap: 12px;
        padding-bottom: max(10px, env(safe-area-inset-bottom));
    }

    .dbx-card {
        border-radius: 20px;
        box-shadow: 0 12px 34px rgba(15, 23, 42, .065), 0 1px 5px rgba(15, 23, 42, .025);
    }

    .dbx-hero {
        min-height: 0;
        padding: 23px 19px 21px;
        border-radius: 25px;
        background:
            radial-gradient(circle at 100% 0%, rgba(34, 211, 238, .34), transparent 35%),
            radial-gradient(circle at 65% 115%, rgba(99, 102, 241, .32), transparent 44%),
            linear-gradient(145deg, #050d1e 0%, #092d54 54%, #086377 100%);
    }

    .dbx-hero::before {
        width: 170px;
        height: 170px;
        right: -82px;
        top: -78px;
    }

    .dbx-hero::after {
        background-size: 24px 24px;
        opacity: .85;
    }

    .dbx-title {
        margin-top: 8px;
        font-size: clamp(27px, 9vw, 36px);
        line-height: 1.04;
    }

    .dbx-subtitle {
        margin-top: 10px;
        font-size: 13px;
        line-height: 1.62;
    }

    .dbx-badges {
        gap: 7px;
    }

    .dbx-badge {
        min-height: 32px;
        padding: 6px 10px;
        font-size: 10px;
    }

    .dbx-filter {
        padding: 14px;
    }

    .dbx-filter__head {
        margin-bottom: 12px;
    }

    .dbx-filter__grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 11px 9px;
    }

    .dbx-field label,
    .dbx-field > span {
        font-size: 9px;
    }

    /* 16px prevents Safari from zooming into form controls. */
    .dbx-control {
        height: 48px;
        padding-inline: 12px;
        border-radius: 14px;
        font-size: 16px;
    }

    .dbx-filter__actions {
        position: relative;
        z-index: 2;
        display: grid;
        grid-template-columns: minmax(0, .8fr) minmax(0, 1.2fr);
        gap: 9px;
    }

    .dbx-btn {
        width: 100%;
        min-height: 48px;
        height: 48px;
        padding-inline: 12px;
        border-radius: 14px;
        font-size: 12px;
    }

    .dbx-kpis {
        gap: 10px;
    }

    .dbx-kpi {
        min-height: 126px;
        padding: 15px;
        border-radius: 19px;
    }

    .dbx-kpi__icon {
        width: 35px;
        height: 35px;
    }

    .dbx-kpi__value {
        margin-top: 14px;
        font-size: clamp(21px, 7vw, 27px);
    }

    .dbx-kpi__note {
        margin-top: 7px;
        font-size: 9px;
    }

    .dbx-status-strip {
        margin-inline: 0;
        border-radius: 20px;
        scroll-padding-inline: 12px;
        scrollbar-width: none;
    }

    .dbx-status-strip::-webkit-scrollbar,
    .dbx-month-chart::-webkit-scrollbar {
        display: none;
    }

    .dbx-status-item {
        min-width: min(72vw, 220px);
        padding: 14px 15px;
    }

    .dbx-panel {
        padding: 16px;
    }

    .dbx-panel__head {
        margin-bottom: 15px;
    }

    .dbx-panel__title {
        font-size: 16px;
    }

    .dbx-month-chart {
        height: 218px;
        gap: 10px;
        margin-inline: -4px;
        padding-inline: 4px;
        scroll-padding-inline: 8px;
        scrollbar-width: none;
    }

    .dbx-month {
        min-width: 43px;
        flex-basis: 43px;
    }

    .dbx-month__label {
        transform: none;
        min-height: auto;
        font-size: 8px;
    }

    .dbx-donut-wrap {
        grid-template-columns: 1fr;
        justify-items: center;
        gap: 18px;
    }

    .dbx-legend {
        width: 100%;
    }

    .dbx-legend__row {
        min-height: 29px;
        padding: 5px 8px;
        border-radius: 9px;
        background: #f8fafc;
    }

    .dbx-source {
        padding: 13px;
        border-radius: 15px;
    }

    .dbx-actions {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .dbx-action {
        min-width: 0;
        min-height: 48px;
        padding: 8px 11px;
        border-radius: 14px;
    }

    .dbx-sections {
        grid-template-columns: 1fr;
        gap: 12px;
    }

    .dbx-table-head {
        align-items: center;
        padding: 15px 16px;
    }

    .dbx-table-head .dbx-btn {
        width: auto;
        min-width: 105px;
        height: 42px;
        min-height: 42px;
    }

    /* Mobile order list: convert the wide data table into readable cards. */
    .dbx-table-scroll {
        overflow: visible;
        padding: 11px;
        background: #f8fafc;
    }

    .dbx-table,
    .dbx-table tbody,
    .dbx-table tr,
    .dbx-table td {
        width: 100%;
        min-width: 0;
        display: block;
    }

    .dbx-table {
        border-collapse: separate;
        font-size: 11px;
    }

    .dbx-table thead {
        position: absolute;
        width: 1px;
        height: 1px;
        padding: 0;
        margin: -1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        white-space: nowrap;
        border: 0;
    }

    .dbx-table tbody {
        display: grid;
        gap: 11px;
    }

    .dbx-table tbody tr {
        overflow: hidden;
        border: 1px solid rgba(148, 163, 184, .2);
        border-radius: 18px;
        background: #fff;
        box-shadow: 0 9px 24px rgba(15, 23, 42, .055);
    }

    .dbx-table td {
        min-height: 42px;
        padding: 10px 13px;
        display: grid;
        grid-template-columns: minmax(92px, .88fr) minmax(0, 1.25fr);
        align-items: center;
        gap: 12px;
        border-bottom: 1px solid #f1f5f9;
        background: transparent;
        text-align: left;
        overflow-wrap: anywhere;
    }

    .dbx-table td::before {
        content: attr(data-label);
        color: #94a3b8;
        font-size: 8px;
        line-height: 1.3;
        font-weight: 900;
        letter-spacing: .09em;
        text-transform: uppercase;
    }

    .dbx-table td:last-child {
        border-bottom: 0;
    }

    .dbx-table td:nth-child(1),
    .dbx-table td:nth-child(2) {
        background: #fbfdff;
    }

    .dbx-table .num {
        text-align: left;
        white-space: normal;
    }

    .dbx-table td[colspan] {
        min-height: 110px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 0;
        text-align: center;
    }

    .dbx-table td[colspan]::before {
        content: none;
    }
}

/* Smaller phones */
@media (max-width: 480px) {
    .dbx-filter__grid {
        grid-template-columns: 1fr;
    }

    .dbx-filter__actions {
        grid-column: auto;
    }

    .dbx-source-grid {
        grid-template-columns: 1fr;
    }

    .dbx-section__stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .dbx-table-head {
        align-items: flex-start;
    }

    .dbx-table-head .dbx-btn {
        min-width: 96px;
        padding-inline: 10px;
        font-size: 10px;
    }
}

/* Very narrow Android devices and split-screen mode */
@media (max-width: 360px) {
    .dbx-kpis,
    .dbx-actions,
    .dbx-filter__actions {
        grid-template-columns: 1fr;
    }

    .dbx-kpi {
        min-height: 116px;
    }

    .dbx-table td {
        grid-template-columns: 82px minmax(0, 1fr);
        gap: 9px;
        padding-inline: 11px;
    }
}

/* Platform-specific refinements set by the tiny script below. */
.dbx[data-platform="ios"] {
    padding-left: max(0px, env(safe-area-inset-left));
    padding-right: max(0px, env(safe-area-inset-right));
}

.dbx[data-platform="ios"] .dbx-status-strip,
.dbx[data-platform="ios"] .dbx-month-chart,
.dbx[data-platform="ios"] .dbx-table-scroll {
    -webkit-overflow-scrolling: touch;
}

.dbx[data-platform="android"] .dbx-btn,
.dbx[data-platform="android"] .dbx-action,
.dbx[data-platform="android"] .dbx-control {
    touch-action: manipulation;
}

@media (prefers-reduced-motion: reduce) {
    .dbx *,
    .dbx *::before,
    .dbx *::after {
        scroll-behavior: auto !important;
        animation-duration: .01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: .01ms !important;
    }
}
</style>

<div class="dbx">
    <section class="dbx-hero">
        <div class="dbx-hero__grid">
            <div>
                <div class="dbx-eyebrow"><?= e((string) ($workspaceLabel ?? 'Workspace')) ?></div>
                <h1 class="dbx-title"><?= e((string) ($dashboardTitle ?? 'Dashboard')) ?></h1>
                <p class="dbx-subtitle"><?= e((string) ($dashboardSubtitle ?? '')) ?></p>
            </div>
            <div class="dbx-badges">
                <span class="dbx-badge"><i></i><?= e((string) ($scopeLabel ?? 'Accessible records')) ?></span>
                <span class="dbx-badge"><?= e((string) ($periodLabel ?? 'All-time analytics')) ?></span>
                <?php foreach ($roleNames as $roleName): ?>
                    <span class="dbx-badge"><?= e((string) $roleName) ?></span>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <form method="get" action="<?= e(base_url('dashboard')) ?>" class="dbx-card dbx-filter">
        <div class="dbx-filter__head">
            <div class="dbx-filter__title">
                <span>
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M4 5h16M7 12h10M10 19h4" stroke-linecap="round"/></svg>
                </span>
                Analytics Filters
            </div>
            <div class="dbx-filter__count"><?= e((string) $activeFilters) ?> active</div>
        </div>

        <div class="dbx-filter__grid">
            <div class="dbx-field">
                <label for="dbxDateFrom">Date From</label>
                <input id="dbxDateFrom" class="dbx-control" type="date" name="date_from" value="<?= e((string) ($filters['date_from'] ?? '')) ?>">
            </div>
            <div class="dbx-field">
                <label for="dbxDateTo">Date To</label>
                <input id="dbxDateTo" class="dbx-control" type="date" name="date_to" value="<?= e((string) ($filters['date_to'] ?? '')) ?>">
            </div>
            <div class="dbx-field">
                <label for="dbxSource">Source</label>
                <select id="dbxSource" class="dbx-control" name="source">
                    <option value="all" <?= ($filters['source'] ?? 'all') === 'all' ? 'selected' : '' ?>>All Accessible Records</option>
                    <option value="client" <?= ($filters['source'] ?? '') === 'client' ? 'selected' : '' ?>>Client Orders</option>
                    <option value="partner" <?= ($filters['source'] ?? '') === 'partner' ? 'selected' : '' ?>>Partner Orders</option>
                </select>
            </div>
            <div class="dbx-field">
                <label for="dbxPayment">Payment Status</label>
                <select id="dbxPayment" class="dbx-control" name="payment_status">
                    <option value="all">All Payment Statuses</option>
                    <?php foreach ($paymentStatuses as $statusKey => $statusName): ?>
                        <option value="<?= e((string) $statusKey) ?>" <?= ($filters['payment_status'] ?? 'all') === $statusKey ? 'selected' : '' ?>><?= e((string) $statusName) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if ($canFilterByPartner): ?>
                <div class="dbx-field">
                    <label for="dbxPartner">Partner</label>
                    <select id="dbxPartner" class="dbx-control" name="partner_id">
                        <option value="0">All Partners</option>
                        <?php foreach ($partners as $partner): ?>
                            <?php
                                $partnerId = (int) ($partner['id'] ?? 0);
                                $partnerName = trim((string) ($partner['name'] ?? '')) ?: ('Partner #' . $partnerId);
                            ?>
                            <option value="<?= e((string) $partnerId) ?>" <?= (int) ($filters['partner_id'] ?? 0) === $partnerId ? 'selected' : '' ?>><?= e($partnerName) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <?php if ($canFilterByExecutive): ?>
                <div class="dbx-field">
                    <label for="dbxExecutive">Assigned Executive</label>
                    <select id="dbxExecutive" class="dbx-control" name="executive_id">
                        <option value="0">All Executives</option>
                        <option value="-1" <?= (int) ($filters['executive_id'] ?? 0) === -1 ? 'selected' : '' ?>>Unassigned Orders</option>
                        <?php foreach ($executives as $executive): ?>
                            <?php
                                $executiveId = (int) ($executive['id'] ?? 0);
                                $executiveName = trim((string) ($executive['name'] ?? '')) ?: ('Executive #' . $executiveId);
                            ?>
                            <option value="<?= e((string) $executiveId) ?>" <?= (int) ($filters['executive_id'] ?? 0) === $executiveId ? 'selected' : '' ?>><?= e($executiveName) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <div class="dbx-filter__actions">
                <a href="<?= e(base_url('dashboard')) ?>" class="dbx-btn dbx-btn--ghost">Reset</a>
                <button type="submit" class="dbx-btn dbx-btn--primary">Apply Analytics</button>
            </div>
        </div>
    </form>

    <?php if ($statCards !== []): ?>
        <section class="dbx-kpis">
            <?php foreach ($statCards as $card): ?>
                <?php $tone = preg_replace('/[^a-z]/', '', strtolower((string) ($card['tone'] ?? 'indigo'))) ?: 'indigo'; ?>
                <a href="<?= e((string) ($card['href'] ?? '#')) ?>" class="dbx-card dbx-kpi dbx-kpi--<?= e($tone) ?>">
                    <div class="dbx-kpi__top">
                        <div class="dbx-kpi__label"><?= e((string) ($card['label'] ?? 'Metric')) ?></div>
                        <div class="dbx-kpi__icon"><?= $iconSvg((string) ($card['icon'] ?? 'orders')) ?></div>
                    </div>
                    <div class="dbx-kpi__value"><?= e((string) ($card['value'] ?? 0)) ?></div>
                    <div class="dbx-kpi__note"><?= e((string) ($card['note'] ?? '')) ?></div>
                </a>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <section class="dbx-card dbx-status-strip" aria-label="Payment status summary">
        <?php if ($statusSummary === []): ?>
            <div class="dbx-empty" style="width:100%">No payment status data available.</div>
        <?php else: ?>
            <?php foreach ($statusSummary as $row): ?>
                <?php $key = strtolower((string) ($row['status'] ?? 'unknown')); ?>
                <div class="dbx-status-item">
                    <div class="dbx-status-item__top">
                        <div style="display:flex;align-items:center;gap:7px">
                            <span class="dbx-dot dbx-dot--<?= e(preg_replace('/[^a-z_]/', '', $key) ?: 'unknown') ?>"></span>
                            <span class="dbx-status-item__name"><?= e((string) ($row['label'] ?? $statusLabel($key))) ?></span>
                        </div>
                        <strong class="dbx-status-item__count"><?= e((string) ($row['total'] ?? $row['count'] ?? 0)) ?></strong>
                    </div>
                    <div class="dbx-status-item__amount"><?= e($compactMoney($row['amount'] ?? 0)) ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <div class="dbx-main-grid">
        <section class="dbx-card dbx-panel">
            <div class="dbx-panel__head">
                <div>
                    <div class="dbx-panel__eyebrow">12-Month Trend</div>
                    <div class="dbx-panel__title">Order Activity</div>
                </div>
                <span class="dbx-panel__meta"><?= e((string) ($analytics['total_records'] ?? 0)) ?> records</span>
            </div>

            <?php if ($monthly === []): ?>
                <div class="dbx-empty">No monthly trend available.</div>
            <?php else: ?>
                <div class="dbx-month-chart">
                    <?php foreach ($monthly as $item): ?>
                        <?php
                            $orders = (int) ($item['orders'] ?? 0);
                            $amount = (float) ($item['amount'] ?? 0);
                            $height = $orders > 0 ? max(4, ($orders / $maxMonthlyOrders) * 100) : 2;
                        ?>
                        <div class="dbx-month" title="<?= e((string) ($item['label'] ?? '')) ?> · <?= e((string) $orders) ?> orders · <?= e($money($amount)) ?>">
                            <span class="dbx-month__value"><?= e((string) $orders) ?></span>
                            <div class="dbx-month__barbox">
                                <div class="dbx-month__bar" style="height:<?= e(number_format($height, 2, '.', '')) ?>%"></div>
                            </div>
                            <span class="dbx-month__label"><?= e((string) ($item['label'] ?? '')) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="dbx-card dbx-panel">
            <div class="dbx-panel__head">
                <div>
                    <div class="dbx-panel__eyebrow">Payment Health</div>
                    <div class="dbx-panel__title">Collection Distribution</div>
                </div>
                <span class="dbx-panel__meta"><?= e(number_format((float) ($analytics['collection_rate'] ?? 0), 1)) ?>%</span>
            </div>

            <div class="dbx-donut-wrap">
                <div class="dbx-donut" style="--donut:<?= e($donutGradient) ?>">
                    <div class="dbx-donut__center">
                        <strong><?= e((string) $paymentTotal) ?></strong>
                        <span>Records</span>
                    </div>
                </div>
                <div class="dbx-legend">
                    <?php foreach ($paymentChart as $item): ?>
                        <?php
                            $key = strtolower((string) ($item['status'] ?? 'unknown'));
                            $color = $paymentColors[$key] ?? '#94A3B8';
                        ?>
                        <div class="dbx-legend__row">
                            <span class="dbx-legend__swatch" style="background:<?= e($color) ?>"></span>
                            <span class="dbx-legend__label"><?= e((string) ($item['label'] ?? $statusLabel($key))) ?></span>
                            <span class="dbx-legend__value"><?= e((string) ($item['total'] ?? $item['count'] ?? 0)) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    </div>

    <div class="dbx-secondary-grid">
        <section class="dbx-card dbx-panel">
            <div class="dbx-panel__head">
                <div>
                    <div class="dbx-panel__eyebrow">Workflow</div>
                    <div class="dbx-panel__title">Order Status Distribution</div>
                </div>
            </div>
            <div class="dbx-bars">
                <?php if ($orderStatusChart === []): ?>
                    <div class="dbx-empty">No order status data.</div>
                <?php endif; ?>
                <?php foreach ($orderStatusChart as $item): ?>
                    <?php
                        $total = (int) ($item['total'] ?? 0);
                        $width = $maxOrderStatus > 0 ? ($total / $maxOrderStatus) * 100 : 0;
                    ?>
                    <div class="dbx-bar-row">
                        <div class="dbx-bar-row__head">
                            <span class="dbx-bar-row__label"><?= e((string) ($item['label'] ?? $statusLabel((string) ($item['status'] ?? 'unknown')))) ?></span>
                            <span class="dbx-bar-row__value"><?= e((string) $total) ?></span>
                        </div>
                        <div class="dbx-bar-track"><div class="dbx-bar-fill" style="width:<?= e(number_format($width, 2, '.', '')) ?>%"></div></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="dbx-card dbx-panel">
            <div class="dbx-panel__head">
                <div>
                    <div class="dbx-panel__eyebrow">Service Intelligence</div>
                    <div class="dbx-panel__title">Top Services by Value</div>
                </div>
                <span class="dbx-panel__meta"><?= e((string) ($analytics['unique_services'] ?? 0)) ?> services</span>
            </div>
            <div class="dbx-bars">
                <?php if ($serviceChart === []): ?>
                    <div class="dbx-empty">No service analytics available.</div>
                <?php endif; ?>
                <?php foreach ($serviceChart as $item): ?>
                    <?php
                        $amount = (float) ($item['amount'] ?? 0);
                        $width = $maxServiceAmount > 0 ? ($amount / $maxServiceAmount) * 100 : 0;
                    ?>
                    <div class="dbx-bar-row">
                        <div class="dbx-bar-row__head">
                            <span class="dbx-bar-row__label"><?= e((string) ($item['label'] ?? 'Service')) ?></span>
                            <span class="dbx-bar-row__value"><?= e($compactMoney($amount)) ?></span>
                        </div>
                        <div class="dbx-bar-track"><div class="dbx-bar-fill dbx-bar-fill--service" style="width:<?= e(number_format($width, 2, '.', '')) ?>%"></div></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </div>

    <div class="dbx-secondary-grid">
        <section class="dbx-card dbx-panel">
            <div class="dbx-panel__head">
                <div>
                    <div class="dbx-panel__eyebrow">Record Sources</div>
                    <div class="dbx-panel__title">Source Mix</div>
                </div>
            </div>
            <div class="dbx-source-grid">
                <?php if ($sourceChart === []): ?>
                    <div class="dbx-empty" style="grid-column:1/-1">No source data available.</div>
                <?php endif; ?>
                <?php foreach ($sourceChart as $sourceItem): ?>
                    <?php
                        $sourceTotal = (int) ($sourceItem['total'] ?? 0);
                        $sourcePercent = $totalSourceRecords > 0 ? ($sourceTotal / $totalSourceRecords) * 100 : 0;
                    ?>
                    <div class="dbx-source">
                        <div class="dbx-source__label"><?= e((string) ($sourceItem['label'] ?? 'Source')) ?></div>
                        <div class="dbx-source__value"><?= e((string) $sourceTotal) ?></div>
                        <div class="dbx-source__amount"><?= e(number_format($sourcePercent, 1)) ?>% · <?= e($compactMoney($sourceItem['amount'] ?? 0)) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="dbx-card dbx-panel">
            <div class="dbx-panel__head">
                <div>
                    <div class="dbx-panel__eyebrow">Smart Insight</div>
                    <div class="dbx-panel__title">Performance Snapshot</div>
                </div>
            </div>
            <div class="dbx-source-grid">
                <div class="dbx-source">
                    <div class="dbx-source__label">Completion Rate</div>
                    <div class="dbx-source__value"><?= e(number_format((float) ($analytics['completion_rate'] ?? 0), 1)) ?>%</div>
                    <div class="dbx-source__amount"><?= e((string) ($analytics['completed_records'] ?? 0)) ?> completed</div>
                </div>
                <div class="dbx-source">
                    <div class="dbx-source__label">Collection Rate</div>
                    <div class="dbx-source__value"><?= e(number_format((float) ($analytics['collection_rate'] ?? 0), 1)) ?>%</div>
                    <div class="dbx-source__amount"><?= e($compactMoney($analytics['paid_amount'] ?? 0)) ?> collected</div>
                </div>
                <div class="dbx-source">
                    <div class="dbx-source__label">Top Service</div>
                    <div class="dbx-source__value" style="font-size:14px;line-height:1.3"><?= e((string) (($analytics['top_service']['label'] ?? '') ?: 'No data')) ?></div>
                    <div class="dbx-source__amount"><?= e((string) ($analytics['top_service']['total'] ?? 0)) ?> record(s)</div>
                </div>
                <div class="dbx-source">
                    <div class="dbx-source__label">Average Value</div>
                    <div class="dbx-source__value" style="font-size:18px"><?= e($compactMoney($analytics['average_order_value'] ?? 0)) ?></div>
                    <div class="dbx-source__amount">Per accessible order</div>
                </div>
            </div>
        </section>
    </div>

    <?php if ($quickActions !== []): ?>
        <section class="dbx-card dbx-panel">
            <div class="dbx-panel__head" style="margin-bottom:12px">
                <div>
                    <div class="dbx-panel__eyebrow">Shortcuts</div>
                    <div class="dbx-panel__title">Quick Actions</div>
                </div>
            </div>
            <div class="dbx-actions">
                <?php foreach ($quickActions as $action): ?>
                    <a href="<?= e((string) ($action['href'] ?? '#')) ?>" class="dbx-action">
                        <span class="dbx-action__icon">↗</span>
                        <?= e((string) ($action['label'] ?? 'Open')) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($dashboardSections !== []): ?>
        <div class="dbx-sections">
            <?php foreach ($dashboardSections as $section): ?>
                <?php $sectionCards = is_array($section['statCards'] ?? null) ? array_values($section['statCards']) : []; ?>
                <section class="dbx-card dbx-section">
                    <div class="dbx-section__title"><?= e((string) ($section['title'] ?? 'Workspace Overview')) ?></div>
                    <div class="dbx-section__subtitle"><?= e((string) ($section['subtitle'] ?? '')) ?></div>
                    <?php if ($sectionCards !== []): ?>
                        <div class="dbx-section__stats">
                            <?php foreach ($sectionCards as $sectionCard): ?>
                                <a class="dbx-mini" href="<?= e((string) ($sectionCard['href'] ?? '#')) ?>">
                                    <div class="dbx-mini__label"><?= e((string) ($sectionCard['label'] ?? 'Metric')) ?></div>
                                    <div class="dbx-mini__value"><?= e((string) ($sectionCard['value'] ?? 0)) ?></div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <section class="dbx-card dbx-table-wrap">
        <div class="dbx-table-head">
            <div>
                <div class="dbx-panel__eyebrow">Latest Activity</div>
                <div class="dbx-panel__title">Recent Accessible Orders</div>
            </div>
            <a class="dbx-btn dbx-btn--ghost" href="<?= e((string) ($primaryOrdersUrl ?? base_url('dashboard'))) ?>">View Records</a>
        </div>
        <div class="dbx-table-scroll" role="region" aria-label="Recent accessible orders" tabindex="0">
            <table class="dbx-table">
                <thead>
                    <tr>
                        <th>Source</th>
                        <th>Order</th>
                        <th>Date</th>
                        <th>Client / Partner</th>
                        <th>Service</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th>Assigned</th>
                        <th style="text-align:right">Payable</th>
                        <th style="text-align:right">Collected</th>
                        <th style="text-align:right">Outstanding</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentOrders as $order): ?>
                        <?php
                            $source = (string) ($order['report_source'] ?? 'client');
                            $owner = trim((string) ($order['client_name'] ?? ''));
                            if ($source === 'partner') {
                                $owner = trim((string) ($order['partner_name'] ?? '')) ?: 'Partner';
                            }
                        ?>
                        <tr>
                            <td data-label="Source"><span class="dbx-pill dbx-pill--neutral"><?= e($source === 'partner' ? 'Partner' : 'Client') ?></span></td>
                            <td data-label="Order" class="dbx-order"><?= e((string) ($order['order_no'] ?? ('#' . ($order['id'] ?? '')))) ?></td>
                            <td data-label="Date"><?= e($dateLabel($order['created_at'] ?? '')) ?></td>
                            <td data-label="Client / Partner"><?= e($owner !== '' ? $owner : '-') ?></td>
                            <td data-label="Service"><?= e((string) ($order['service_title'] ?? 'Service')) ?></td>
                            <td data-label="Status"><span class="dbx-pill <?= e($statusTone((string) ($order['status'] ?? 'unknown'))) ?>"><?= e($statusLabel((string) ($order['status'] ?? 'unknown'))) ?></span></td>
                            <td data-label="Payment"><span class="dbx-pill <?= e($statusTone((string) ($order['payment_status'] ?? 'pending'))) ?>"><?= e($statusLabel((string) ($order['payment_status'] ?? 'pending'))) ?></span></td>
                            <td data-label="Assigned"><?= e(trim((string) ($order['assigned_user_name'] ?? '')) ?: '—') ?></td>
                            <td data-label="Payable" class="num"><?= e($money($order['payable_amount'] ?? 0)) ?></td>
                            <td data-label="Collected" class="num"><?= e($money($order['collected_amount'] ?? 0)) ?></td>
                            <td data-label="Outstanding" class="num"><?= e($money($order['outstanding_amount'] ?? 0)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($recentOrders === []): ?>
                        <tr><td colspan="11" class="dbx-empty">No accessible records found for the selected analytics filters.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<script>
(function () {
    'use strict';

    var dashboard = document.querySelector('.dbx');
    if (!dashboard) {
        return;
    }

    var ua = navigator.userAgent || '';
    var isIOS = /iPad|iPhone|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    var isAndroid = /Android/i.test(ua);

    dashboard.dataset.platform = isIOS ? 'ios' : (isAndroid ? 'android' : 'desktop');
})();
</script>