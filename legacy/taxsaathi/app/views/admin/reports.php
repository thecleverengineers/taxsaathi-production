<section class="grid cols-4 mobile-2 gap-md">
    <div class="stat-card"><span>Total Revenue</span><strong><?= e(format_money($totals['total_revenue'])) ?></strong></div>
    <div class="stat-card"><span>Pending Orders</span><strong><?= e((string) $totals['pending_orders']) ?></strong></div>
    <div class="stat-card"><span>Completed Orders</span><strong><?= e((string) $totals['completed_orders']) ?></strong></div>
    <div class="stat-card"><span>New Leads</span><strong><?= e((string) $totals['new_leads']) ?></strong></div>
</section>

<section class="grid cols-2 mobile-1 gap-lg mt-lg">
    <div class="card">
        <div class="section-head left"><h3>Monthly Order Summary</h3></div>
        <?php foreach ($monthly as $row): ?>
            <div class="list-row">
                <span><?= e($row['period']) ?></span>
                <strong><?= e((string) $row['total_orders']) ?> • <?= e(format_money($row['order_value'])) ?></strong>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="card">
        <div class="section-head left"><h3>Latest Leads</h3></div>
        <?php foreach ($leads as $lead): ?>
            <div class="list-item">
                <div>
                    <strong><?= e($lead['name']) ?></strong>
                    <p class="muted"><?= e($lead['phone']) ?> • <?= e($lead['service']) ?></p>
                </div>
                <span><?= render_status_badge((string) $lead['status']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</section>
