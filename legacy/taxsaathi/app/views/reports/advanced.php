<?php
declare(strict_types=1);

$filters = is_array($filters ?? null) ? $filters : [];
$rows = is_array($rows ?? null) ? $rows : [];
$summary = is_array($summary ?? null) ? $summary : [];
$paymentStatuses = is_array($paymentStatuses ?? null) ? $paymentStatuses : [
    'pending' => 'Pending',
    'pending_review' => 'Pending Review',
    'verified' => 'Verified',
    'paid' => 'Paid',
    'partial' => 'Partial',
    'unpaid' => 'Unpaid',
    'failed' => 'Failed',
];

$scopeLabel = (string) ($scopeLabel ?? 'Report scope');
$isAdminOrManager = (bool) ($isAdminOrManager ?? false);
$isExecutive = (bool) ($isExecutive ?? false);
$isPartner = (bool) ($isPartner ?? false);
$partners = is_array($partners ?? null) ? array_values($partners) : [];
$canFilterByPartner = (bool) ($canFilterByPartner ?? false);
$selectedPartnerLabel = (string) ($selectedPartnerLabel ?? 'All accessible partners');
$executives = is_array($executives ?? null) ? array_values($executives) : [];
$canFilterByExecutive = (bool) ($canFilterByExecutive ?? false);
$selectedExecutiveLabel = (string) ($selectedExecutiveLabel ?? 'All accessible executives');

$selectedStatuses = is_array($filters['payment_statuses'] ?? null)
    ? array_values(array_map('strval', $filters['payment_statuses']))
    : [];
$dateFrom = (string) ($filters['date_from'] ?? '');
$dateTo = (string) ($filters['date_to'] ?? '');
$source = (string) ($filters['source'] ?? 'all');
$selectedPartnerId = max(0, (int) ($filters['partner_id'] ?? 0));
$selectedExecutiveId = (int) ($filters['executive_id'] ?? 0);

$money = static function (mixed $value): string {
    return '₹' . number_format((float) $value, 2);
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
    return $paymentStatuses[$key] ?? ucwords(str_replace('_', ' ', $status));
};

$statusKey = static function (string $status): string {
    $key = strtolower(trim($status));
    return preg_replace('/[^a-z0-9_-]+/', '-', $key) ?: 'default';
};

$buildQuery = static function (array $extra = []) use ($filters): string {
    $params = [];

    if (!empty($filters['date_from'])) {
        $params['date_from'] = (string) $filters['date_from'];
    }

    if (!empty($filters['date_to'])) {
        $params['date_to'] = (string) $filters['date_to'];
    }

    if (!empty($filters['source'])) {
        $params['source'] = (string) $filters['source'];
    }

    if (!empty($filters['partner_id'])) {
        $params['partner_id'] = (int) $filters['partner_id'];
    }

    if ((int) ($filters['executive_id'] ?? 0) !== 0) {
        $params['executive_id'] = (int) $filters['executive_id'];
    }

    foreach ((array) ($filters['payment_statuses'] ?? []) as $status) {
        $params['payment_status'][] = (string) $status;
    }

    return http_build_query(array_merge($params, $extra));
};

$activeFilterCount = 0;
$activeFilterCount += $dateFrom !== '' ? 1 : 0;
$activeFilterCount += $dateTo !== '' ? 1 : 0;
$activeFilterCount += $source !== '' && $source !== 'all' ? 1 : 0;
$activeFilterCount += $selectedPartnerId > 0 ? 1 : 0;
$activeFilterCount += $selectedExecutiveId !== 0 ? 1 : 0;
$activeFilterCount += count($selectedStatuses);

$reportSourceLabel = static function (string $value): string {
    return match (strtolower(trim($value))) {
        'orders', 'regular_orders', 'regular' => 'Regular',
        'partner_orders', 'partner' => 'Partner',
        default => ucwords(str_replace('_', ' ', $value !== '' ? $value : 'Record')),
    };
};
?>

<style>
    .ar-page {
        --ar-ink: #0f172a;
        --ar-muted: #64748b;
        --ar-line: #e7ebf1;
        --ar-soft: #f6f8fb;
        --ar-panel: rgba(255, 255, 255, .96);
        --ar-navy: #111827;
        --ar-indigo: #4f46e5;
        --ar-green: #059669;
        --ar-shadow: 0 18px 44px rgba(15, 23, 42, .07);
        --ar-shadow-soft: 0 8px 24px rgba(15, 23, 42, .045);
        display: grid;
        gap: 18px;
        color: var(--ar-ink);
    }

    .ar-page *,
    .ar-page *::before,
    .ar-page *::after {
        box-sizing: border-box;
    }

    .ar-card {
        border: 1px solid var(--ar-line);
        border-radius: 24px;
        background: var(--ar-panel);
        box-shadow: var(--ar-shadow-soft);
    }

    .ar-hero {
        position: relative;
        overflow: hidden;
        padding: 22px;
        color: #fff;
        background:
            radial-gradient(circle at 82% -20%, rgba(99, 102, 241, .55), transparent 34%),
            radial-gradient(circle at 3% 115%, rgba(14, 165, 233, .24), transparent 34%),
            linear-gradient(118deg, #0b1220 0%, #111827 50%, #1e1b4b 100%);
    }

    .ar-hero::after {
        content: "";
        position: absolute;
        inset: 0;
        pointer-events: none;
        opacity: .16;
        background-image: linear-gradient(rgba(255,255,255,.08) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.08) 1px, transparent 1px);
        background-size: 34px 34px;
        mask-image: linear-gradient(to right, transparent, #000 55%);
    }

    .ar-hero-inner {
        position: relative;
        z-index: 1;
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 24px;
    }

    .ar-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin: 0;
        color: #c7d2fe;
        font-size: 10px;
        font-weight: 800;
        letter-spacing: .2em;
        text-transform: uppercase;
    }

    .ar-eyebrow::before {
        content: "";
        width: 20px;
        height: 2px;
        border-radius: 999px;
        background: #818cf8;
    }

    .ar-title {
        margin: 8px 0 0;
        font-size: clamp(24px, 3vw, 34px);
        line-height: 1.05;
        letter-spacing: -.045em;
        font-weight: 850;
    }

    .ar-subtitle {
        max-width: 760px;
        margin: 9px 0 0;
        color: #cbd5e1;
        font-size: 13px;
        line-height: 1.65;
    }

    .ar-scope-stack {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: 7px;
        max-width: 500px;
    }

    .ar-scope-pill {
        display: inline-flex;
        align-items: center;
        min-height: 30px;
        padding: 6px 10px;
        border: 1px solid rgba(255,255,255,.13);
        border-radius: 999px;
        background: rgba(255,255,255,.075);
        color: #eef2ff;
        backdrop-filter: blur(10px);
        font-size: 10px;
        font-weight: 750;
        letter-spacing: .02em;
        white-space: nowrap;
    }

    .ar-scope-pill strong {
        margin-right: 4px;
        color: #a5b4fc;
        font-weight: 800;
    }

    .ar-filter-shell {
        overflow: hidden;
    }

    .ar-filter-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        padding: 13px 16px;
        border-bottom: 1px solid var(--ar-line);
        background: linear-gradient(180deg, #fff, #fbfcfe);
    }

    .ar-filter-title {
        display: flex;
        align-items: center;
        gap: 10px;
        min-width: 0;
    }

    .ar-filter-icon {
        display: grid;
        width: 34px;
        height: 34px;
        place-items: center;
        flex: 0 0 auto;
        border-radius: 11px;
        background: #111827;
        color: #fff;
        box-shadow: 0 8px 20px rgba(15, 23, 42, .16);
    }

    .ar-filter-icon svg {
        width: 17px;
        height: 17px;
    }

    .ar-filter-heading {
        margin: 0;
        font-size: 13px;
        font-weight: 850;
        letter-spacing: -.015em;
    }

    .ar-filter-caption {
        margin: 2px 0 0;
        color: var(--ar-muted);
        font-size: 11px;
    }

    .ar-filter-count {
        display: inline-flex;
        align-items: center;
        min-height: 28px;
        padding: 5px 10px;
        border: 1px solid #dbe3ef;
        border-radius: 999px;
        background: #f8fafc;
        color: #475569;
        font-size: 10px;
        font-weight: 800;
        white-space: nowrap;
    }

    .ar-filter-form {
        padding: 15px;
        background:
            radial-gradient(circle at 100% 0%, rgba(79, 70, 229, .045), transparent 30%),
            #fff;
    }

    .ar-filter-grid {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 10px;
    }

    .ar-field {
        display: grid;
        gap: 5px;
        min-width: 0;
    }

    .ar-field-label {
        color: #475569;
        font-size: 10px;
        font-weight: 800;
        letter-spacing: .06em;
        text-transform: uppercase;
    }

    .ar-control,
    .ar-locked-control {
        width: 100%;
        height: 40px;
        min-width: 0;
        border: 1px solid #dfe5ee;
        border-radius: 12px;
        background: #fff;
        color: #1e293b;
        padding: 0 11px;
        font-size: 12px;
        font-weight: 650;
        outline: none;
        transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease;
    }

    .ar-control:hover {
        border-color: #cbd5e1;
    }

    .ar-control:focus {
        border-color: #818cf8;
        box-shadow: 0 0 0 4px rgba(99, 102, 241, .10);
    }

    .ar-locked-control {
        display: flex;
        align-items: center;
        overflow: hidden;
        background: #f8fafc;
        color: #64748b;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .ar-status-filter {
        grid-column: 1 / -1;
        display: grid;
        gap: 7px;
        padding-top: 3px;
    }

    .ar-status-filter-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }

    .ar-status-filter-hint {
        color: #94a3b8;
        font-size: 10px;
    }

    .ar-status-options {
        display: flex;
        flex-wrap: wrap;
        gap: 7px;
    }

    .ar-check {
        cursor: pointer;
        user-select: none;
    }

    .ar-check input {
        position: absolute;
        width: 1px;
        height: 1px;
        opacity: 0;
        pointer-events: none;
    }

    .ar-check span {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        min-height: 31px;
        padding: 6px 10px;
        border: 1px solid #e1e7ef;
        border-radius: 10px;
        background: #fff;
        color: #64748b;
        font-size: 10px;
        font-weight: 800;
        transition: all .18s ease;
    }

    .ar-check span::before {
        content: "";
        width: 6px;
        height: 6px;
        border-radius: 999px;
        background: #cbd5e1;
        box-shadow: 0 0 0 3px #f1f5f9;
    }

    .ar-check:hover span {
        border-color: #c7d2fe;
        color: #4338ca;
        transform: translateY(-1px);
    }

    .ar-check input:checked + span {
        border-color: #111827;
        background: #111827;
        color: #fff;
        box-shadow: 0 8px 18px rgba(15, 23, 42, .13);
    }

    .ar-check input:checked + span::before {
        background: #34d399;
        box-shadow: 0 0 0 3px rgba(52, 211, 153, .16);
    }

    .ar-filter-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 8px;
        margin-top: 13px;
        padding-top: 12px;
        border-top: 1px solid #edf0f4;
    }

    .ar-btn {
        display: inline-flex;
        height: 38px;
        align-items: center;
        justify-content: center;
        gap: 8px;
        border: 1px solid transparent;
        border-radius: 11px;
        padding: 0 14px;
        font-size: 11px;
        font-weight: 800;
        text-decoration: none;
        transition: transform .18s ease, box-shadow .18s ease, background .18s ease;
    }

    .ar-btn:hover {
        transform: translateY(-1px);
    }

    .ar-btn svg {
        width: 15px;
        height: 15px;
    }

    .ar-btn-secondary {
        border-color: #dfe5ee;
        background: #fff;
        color: #475569;
    }

    .ar-btn-secondary:hover {
        background: #f8fafc;
    }

    .ar-btn-dark {
        background: #111827;
        color: #fff;
        box-shadow: 0 8px 20px rgba(15, 23, 42, .14);
    }

    .ar-btn-dark:hover {
        background: #020617;
    }

    .ar-btn-export {
        background: linear-gradient(135deg, #047857, #059669);
        color: #fff;
        box-shadow: 0 8px 20px rgba(5, 150, 105, .18);
    }

    .ar-metrics {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 10px;
    }

    .ar-metric {
        position: relative;
        overflow: hidden;
        min-height: 88px;
        padding: 14px 15px;
        border: 1px solid var(--ar-line);
        border-radius: 18px;
        background: #fff;
        box-shadow: var(--ar-shadow-soft);
    }

    .ar-metric::after {
        content: "";
        position: absolute;
        right: -22px;
        bottom: -30px;
        width: 82px;
        height: 82px;
        border-radius: 999px;
        background: rgba(99,102,241,.055);
    }

    .ar-metric-label {
        color: #94a3b8;
        font-size: 9px;
        font-weight: 850;
        letter-spacing: .13em;
        text-transform: uppercase;
    }

    .ar-metric-value {
        position: relative;
        z-index: 1;
        margin-top: 7px;
        font-size: clamp(20px, 2.2vw, 27px);
        line-height: 1;
        letter-spacing: -.04em;
        font-weight: 900;
    }

    .ar-metric-note {
        margin-top: 7px;
        color: #94a3b8;
        font-size: 10px;
    }

    .ar-metric-discount {
        border-color: #d1fae5;
        background: linear-gradient(145deg, #fff, #f0fdf8);
    }

    .ar-metric-discount .ar-metric-label,
    .ar-metric-discount .ar-metric-value {
        color: #047857;
    }

    .ar-metric-payable {
        border-color: #111827;
        background: linear-gradient(135deg, #111827, #1e293b);
        color: #fff;
    }

    .ar-metric-payable .ar-metric-label,
    .ar-metric-payable .ar-metric-note {
        color: #cbd5e1;
    }

    .ar-status-card {
        overflow: hidden;
    }

    .ar-status-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 10px 13px;
        border-bottom: 1px solid var(--ar-line);
        background: #fbfcfe;
    }

    .ar-status-title-wrap {
        display: flex;
        align-items: center;
        gap: 9px;
    }

    .ar-status-mark {
        width: 7px;
        height: 26px;
        border-radius: 999px;
        background: linear-gradient(#6366f1, #0ea5e9);
    }

    .ar-status-kicker {
        margin: 0;
        color: #94a3b8;
        font-size: 8px;
        font-weight: 850;
        letter-spacing: .17em;
        text-transform: uppercase;
    }

    .ar-status-heading {
        margin: 1px 0 0;
        font-size: 13px;
        font-weight: 900;
        letter-spacing: -.02em;
    }

    .ar-status-meta {
        color: #64748b;
        font-size: 9px;
        font-weight: 700;
    }

    .ar-status-strip {
        display: flex;
        min-height: 50px;
        overflow-x: auto;
        scrollbar-width: thin;
    }

    .ar-status-item {
        position: relative;
        display: grid;
        grid-template-columns: auto auto;
        grid-template-rows: auto auto;
        align-content: center;
        column-gap: 8px;
        min-width: 158px;
        flex: 1 0 158px;
        padding: 8px 12px 8px 15px;
        border-right: 1px solid #edf0f4;
        background: #fff;
    }

    .ar-status-item:last-child {
        border-right: 0;
    }

    .ar-status-item::before {
        content: "";
        position: absolute;
        left: 0;
        top: 10px;
        bottom: 10px;
        width: 3px;
        border-radius: 999px;
        background: #94a3b8;
    }

    .ar-status-name {
        align-self: end;
        color: #475569;
        font-size: 9px;
        font-weight: 850;
        letter-spacing: .04em;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .ar-status-count {
        grid-row: 1 / 3;
        grid-column: 2;
        align-self: center;
        justify-self: end;
        color: #0f172a;
        font-size: 18px;
        font-weight: 900;
        letter-spacing: -.04em;
    }

    .ar-status-amount {
        color: #64748b;
        font-size: 10px;
        font-weight: 750;
        white-space: nowrap;
    }

    .ar-status-item[data-status="paid"]::before,
    .ar-status-item[data-status="verified"]::before { background: #10b981; }
    .ar-status-item[data-status="pending"]::before,
    .ar-status-item[data-status="pending_review"]::before { background: #f59e0b; }
    .ar-status-item[data-status="partial"]::before { background: #3b82f6; }
    .ar-status-item[data-status="unpaid"]::before,
    .ar-status-item[data-status="failed"]::before { background: #f43f5e; }

    .ar-table-card {
        overflow: hidden;
    }

    .ar-table-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 13px 15px;
        border-bottom: 1px solid var(--ar-line);
        background: #fff;
    }

    .ar-table-kicker {
        margin: 0;
        color: #94a3b8;
        font-size: 9px;
        font-weight: 850;
        letter-spacing: .17em;
        text-transform: uppercase;
    }

    .ar-table-title {
        margin: 2px 0 0;
        font-size: 14px;
        font-weight: 900;
        letter-spacing: -.02em;
    }

    .ar-table-wrap {
        overflow-x: auto;
    }

    .ar-table {
        width: 100%;
        min-width: 1080px;
        border-collapse: collapse;
        text-align: left;
        font-size: 11px;
    }

    .ar-table thead {
        background: #111827;
        color: #fff;
    }

    .ar-table th {
        padding: 10px 12px;
        font-size: 9px;
        font-weight: 850;
        letter-spacing: .11em;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .ar-table td {
        padding: 9px 12px;
        border-bottom: 1px solid #edf0f4;
        color: #475569;
        vertical-align: middle;
    }

    .ar-table tbody tr {
        transition: background .16s ease;
    }

    .ar-table tbody tr:hover {
        background: #f8faff;
    }

    .ar-order-no {
        color: #0f172a;
        font-weight: 850;
    }

    .ar-truncate {
        display: block;
        max-width: 205px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .ar-source {
        display: inline-flex;
        align-items: center;
        min-height: 23px;
        padding: 3px 8px;
        border: 1px solid #e2e8f0;
        border-radius: 999px;
        background: #f8fafc;
        color: #475569;
        font-size: 9px;
        font-weight: 850;
    }

    .ar-payment-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        min-height: 24px;
        padding: 3px 8px;
        border: 1px solid #e2e8f0;
        border-radius: 999px;
        background: #f8fafc;
        color: #475569;
        font-size: 9px;
        font-weight: 850;
        white-space: nowrap;
    }

    .ar-payment-badge::before {
        content: "";
        width: 6px;
        height: 6px;
        border-radius: 999px;
        background: #94a3b8;
    }

    .ar-payment-badge[data-status="paid"],
    .ar-payment-badge[data-status="verified"] {
        border-color: #bbf7d0;
        background: #f0fdf4;
        color: #047857;
    }
    .ar-payment-badge[data-status="paid"]::before,
    .ar-payment-badge[data-status="verified"]::before { background: #10b981; }

    .ar-payment-badge[data-status="pending"],
    .ar-payment-badge[data-status="pending_review"] {
        border-color: #fde68a;
        background: #fffbeb;
        color: #b45309;
    }
    .ar-payment-badge[data-status="pending"]::before,
    .ar-payment-badge[data-status="pending_review"]::before { background: #f59e0b; }

    .ar-payment-badge[data-status="partial"] {
        border-color: #bfdbfe;
        background: #eff6ff;
        color: #1d4ed8;
    }
    .ar-payment-badge[data-status="partial"]::before { background: #3b82f6; }

    .ar-payment-badge[data-status="unpaid"],
    .ar-payment-badge[data-status="failed"] {
        border-color: #fecdd3;
        background: #fff1f2;
        color: #be123c;
    }
    .ar-payment-badge[data-status="unpaid"]::before,
    .ar-payment-badge[data-status="failed"]::before { background: #f43f5e; }

    .ar-empty {
        padding: 42px 16px !important;
        color: #94a3b8 !important;
        text-align: center;
    }

    @media (max-width: 1180px) {
        .ar-filter-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    }

    @media (max-width: 900px) {
        .ar-hero-inner { align-items: flex-start; flex-direction: column; }
        .ar-scope-stack { justify-content: flex-start; max-width: none; }
        .ar-filter-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .ar-metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }

    @media (max-width: 640px) {
        .ar-page { gap: 13px; }
        .ar-card { border-radius: 18px; }
        .ar-hero { padding: 18px; }
        .ar-filter-head { align-items: flex-start; }
        .ar-filter-form { padding: 12px; }
        .ar-filter-grid { grid-template-columns: 1fr; }
        .ar-status-filter { grid-column: auto; }
        .ar-filter-actions { align-items: stretch; flex-direction: column; }
        .ar-btn { width: 100%; }
        .ar-metrics { grid-template-columns: 1fr 1fr; gap: 8px; }
        .ar-metric { min-height: 82px; padding: 12px; }
        .ar-metric-note { display: none; }
        .ar-status-head { align-items: flex-start; flex-direction: column; gap: 4px; }
        .ar-table-head { align-items: flex-start; flex-direction: column; }
    }
</style>

<section class="ar-page">
    <header class="ar-card ar-hero">
        <div class="ar-hero-inner">
            <div>
                <p class="ar-eyebrow">Advanced Reports</p>
                <h1 class="ar-title">Order Records Export</h1>
                <p class="ar-subtitle">
                    A secure, role-aware reporting workspace for date-wise order analysis, payment tracking and polished Excel exports.
                </p>
            </div>

            <div class="ar-scope-stack" aria-label="Current report scope">
                <span class="ar-scope-pill"><strong>Scope</strong><?= e($scopeLabel) ?></span>
                <span class="ar-scope-pill"><strong>Partner</strong><?= e($selectedPartnerLabel) ?></span>
                <span class="ar-scope-pill"><strong>Executive</strong><?= e($selectedExecutiveLabel) ?></span>
            </div>
        </div>
    </header>

    <div class="ar-card ar-filter-shell">
        <div class="ar-filter-head">
            <div class="ar-filter-title">
                <span class="ar-filter-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16M7 12h10M10 19h4"/>
                    </svg>
                </span>
                <div>
                    <h2 class="ar-filter-heading">Report Filters</h2>
                    <p class="ar-filter-caption">Refine the report before previewing or exporting.</p>
                </div>
            </div>
            <span class="ar-filter-count"><?= e((string) $activeFilterCount) ?> active filter<?= $activeFilterCount === 1 ? '' : 's' ?></span>
        </div>

        <form method="get" action="<?= e(base_url('reports/advanced')) ?>" class="ar-filter-form">
            <div class="ar-filter-grid">
                <label class="ar-field">
                    <span class="ar-field-label">Date From</span>
                    <input type="date" name="date_from" value="<?= e($dateFrom) ?>" class="ar-control">
                </label>

                <label class="ar-field">
                    <span class="ar-field-label">Date To</span>
                    <input type="date" name="date_to" value="<?= e($dateTo) ?>" class="ar-control">
                </label>

                <label class="ar-field">
                    <span class="ar-field-label">Record Source</span>
                    <select name="source" class="ar-control">
                        <option value="all" <?= $source === 'all' ? 'selected' : '' ?>>All Accessible Records</option>
                        <option value="orders" <?= $source === 'orders' ? 'selected' : '' ?>>Regular Orders</option>
                        <option value="partner_orders" <?= $source === 'partner_orders' ? 'selected' : '' ?>>Partner Orders</option>
                    </select>
                </label>

                <?php if ($canFilterByPartner): ?>
                    <label class="ar-field">
                        <span class="ar-field-label">Partner</span>
                        <select name="partner_id" class="ar-control">
                            <option value="0">All Accessible Partners</option>
                            <?php foreach ($partners as $partner): ?>
                                <?php
                                    $partnerId = (int) ($partner['id'] ?? 0);
                                    $partnerName = trim((string) ($partner['name'] ?? '')) ?: ('Partner #' . $partnerId);
                                    $partnerEmail = trim((string) ($partner['email'] ?? ''));
                                    $partnerOptionLabel = $partnerEmail !== '' ? $partnerName . ' · ' . $partnerEmail : $partnerName;
                                ?>
                                <option value="<?= e((string) $partnerId) ?>" <?= $selectedPartnerId === $partnerId ? 'selected' : '' ?>>
                                    <?= e($partnerOptionLabel) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                <?php else: ?>
                    <div class="ar-field">
                        <span class="ar-field-label">Partner</span>
                        <div class="ar-locked-control" title="<?= e($selectedPartnerLabel) ?>"><?= e($selectedPartnerLabel) ?></div>
                        <?php if ($selectedPartnerId > 0): ?>
                            <input type="hidden" name="partner_id" value="<?= e((string) $selectedPartnerId) ?>">
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($canFilterByExecutive): ?>
                    <label class="ar-field">
                        <span class="ar-field-label">Assigned Executive</span>
                        <select name="executive_id" class="ar-control">
                            <option value="0">All Accessible Executives</option>
                            <option value="-1" <?= $selectedExecutiveId === -1 ? 'selected' : '' ?>>Unassigned Orders</option>
                            <?php foreach ($executives as $executive): ?>
                                <?php
                                    $executiveId = (int) ($executive['id'] ?? 0);
                                    $executiveName = trim((string) ($executive['name'] ?? '')) ?: ('Executive #' . $executiveId);
                                    $executiveEmail = trim((string) ($executive['email'] ?? ''));
                                    $executiveOptionLabel = $executiveEmail !== '' ? $executiveName . ' · ' . $executiveEmail : $executiveName;
                                ?>
                                <option value="<?= e((string) $executiveId) ?>" <?= $selectedExecutiveId === $executiveId ? 'selected' : '' ?>>
                                    <?= e($executiveOptionLabel) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                <?php else: ?>
                    <div class="ar-field">
                        <span class="ar-field-label">Assigned Executive</span>
                        <div class="ar-locked-control" title="<?= e($selectedExecutiveLabel) ?>"><?= e($selectedExecutiveLabel) ?></div>
                        <?php if ($selectedExecutiveId !== 0): ?>
                            <input type="hidden" name="executive_id" value="<?= e((string) $selectedExecutiveId) ?>">
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="ar-status-filter">
                    <div class="ar-status-filter-head">
                        <span class="ar-field-label">Payment Status</span>
                        <span class="ar-status-filter-hint">Select one or multiple statuses</span>
                    </div>
                    <div class="ar-status-options">
                        <?php foreach ($paymentStatuses as $key => $label): ?>
                            <?php $checked = in_array((string) $key, $selectedStatuses, true); ?>
                            <label class="ar-check">
                                <input type="checkbox" name="payment_status[]" value="<?= e((string) $key) ?>" <?= $checked ? 'checked' : '' ?>>
                                <span><?= e((string) $label) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="ar-filter-actions">
                <a href="<?= e(base_url('reports/advanced')) ?>" class="ar-btn ar-btn-secondary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v6h6M20 20v-6h-6M5.5 15a7 7 0 0 0 11.7 2.5L20 14M4 10l2.8-3.5A7 7 0 0 1 18.5 9"/></svg>
                    Reset
                </a>
                <button type="submit" class="ar-btn ar-btn-dark">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16M7 12h10M10 19h4"/></svg>
                    Apply Filter
                </button>
                <a href="<?= e(base_url('reports/advanced/export?' . $buildQuery())) ?>" class="ar-btn ar-btn-export">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0 4-4m-4 4-4-4M5 19h14"/></svg>
                    Download Excel
                </a>
            </div>
        </form>
    </div>

    <div class="ar-metrics">
        <article class="ar-metric">
            <div class="ar-metric-label">Total Records</div>
            <div class="ar-metric-value"><?= e((string) ($summary['total_orders'] ?? 0)) ?></div>
            <div class="ar-metric-note">Records in current scope</div>
        </article>

        <article class="ar-metric">
            <div class="ar-metric-label">Gross Amount</div>
            <div class="ar-metric-value"><?= e($money($summary['gross_amount'] ?? 0)) ?></div>
            <div class="ar-metric-note">Value before discount</div>
        </article>

        <article class="ar-metric ar-metric-discount">
            <div class="ar-metric-label">Discount</div>
            <div class="ar-metric-value"><?= e($money($summary['discount_amount'] ?? 0)) ?></div>
            <div class="ar-metric-note">Total benefit applied</div>
        </article>

        <article class="ar-metric ar-metric-payable">
            <div class="ar-metric-label">Payable Amount</div>
            <div class="ar-metric-value"><?= e($money($summary['payable_amount'] ?? 0)) ?></div>
            <div class="ar-metric-note">Net report value</div>
        </article>
    </div>

    <div class="ar-card ar-status-card">
        <div class="ar-status-head">
            <div class="ar-status-title-wrap">
                <span class="ar-status-mark" aria-hidden="true"></span>
                <div>
                    <p class="ar-status-kicker">Payment Health</p>
                    <h2 class="ar-status-heading">Status Summary</h2>
                </div>
            </div>
            <span class="ar-status-meta">Ultra-slim live summary · Excel-ready</span>
        </div>

        <div class="ar-status-strip">
            <?php foreach ($paymentStatuses as $key => $label): ?>
                <?php
                    $data = is_array($summary['by_payment_status'][$key] ?? null)
                        ? $summary['by_payment_status'][$key]
                        : [];
                ?>
                <div class="ar-status-item" data-status="<?= e($statusKey((string) $key)) ?>">
                    <span class="ar-status-name"><?= e((string) ($data['label'] ?? $label)) ?></span>
                    <strong class="ar-status-count"><?= e((string) ($data['count'] ?? 0)) ?></strong>
                    <span class="ar-status-amount"><?= e($money($data['amount'] ?? 0)) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="ar-card ar-table-card">
        <div class="ar-table-head">
            <div>
                <p class="ar-table-kicker">Live Preview</p>
                <h2 class="ar-table-title">Latest <?= e((string) count($rows)) ?> report records</h2>
            </div>
            <a href="<?= e(base_url('reports/advanced/export?' . $buildQuery())) ?>" class="ar-btn ar-btn-export">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0 4-4m-4 4-4-4M5 19h14"/></svg>
                Export Current Filter
            </a>
        </div>

        <div class="ar-table-wrap">
            <table class="ar-table">
                <thead>
                    <tr>
                        <th>Source</th>
                        <th>Order</th>
                        <th>Date</th>
                        <th>Service</th>
                        <th>Client</th>
                        <th>Partner</th>
                        <th style="text-align:right">Payable</th>
                        <th>Payment</th>
                        <th>Assigned</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php $paymentStatus = (string) ($row['payment_status'] ?? 'pending'); ?>
                        <tr>
                            <td><span class="ar-source"><?= e($reportSourceLabel((string) ($row['report_source'] ?? '-'))) ?></span></td>
                            <td><span class="ar-order-no"><?= e((string) ($row['order_no'] ?? '-')) ?></span></td>
                            <td><?= e($dateLabel($row['created_at'] ?? '')) ?></td>
                            <td><span class="ar-truncate" title="<?= e((string) ($row['service_title'] ?? '-')) ?>"><?= e((string) ($row['service_title'] ?? '-')) ?></span></td>
                            <td><span class="ar-truncate" title="<?= e((string) ($row['client_name'] ?? '-')) ?>"><?= e((string) ($row['client_name'] ?? '-')) ?></span></td>
                            <td><span class="ar-truncate" title="<?= e((string) (($row['partner_name'] ?? '') !== '' ? $row['partner_name'] : '-')) ?>"><?= e((string) (($row['partner_name'] ?? '') !== '' ? $row['partner_name'] : '-')) ?></span></td>
                            <td style="text-align:right"><span class="ar-order-no"><?= e($money($row['payable_amount'] ?? 0)) ?></span></td>
                            <td>
                                <span class="ar-payment-badge" data-status="<?= e($statusKey($paymentStatus)) ?>">
                                    <?= e($statusLabel($paymentStatus)) ?>
                                </span>
                            </td>
                            <td><span class="ar-truncate" title="<?= e((string) ($row['assigned_user_name'] ?? '')) ?>"><?= e((string) (($row['assigned_user_name'] ?? '') !== '' ? $row['assigned_user_name'] : 'Unassigned')) ?></span></td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if ($rows === []): ?>
                        <tr>
                            <td colspan="9" class="ar-empty">No report records found for the selected filters.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>