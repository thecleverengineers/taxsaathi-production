<section class="page-banner">
    <div class="container">
        <span class="eyebrow">Services</span>
        <h1>Filing services and fees</h1>
        <p>Every service listed here, including filing fee, turnaround time, and required documents.</p>
    </div>
</section>

<section class="section">
    <div class="container grid cols-3">
        <?php foreach ($services as $service): ?>
            <article class="card service-card">
                <div class="service-icon"><?= e($service['icon'] ?: 'TS') ?></div>
                <h3><?= e($service['title']) ?></h3>
                <p><?= e($service['excerpt']) ?></p>
                <p class="muted"><?= e($service['description']) ?></p>
                <div class="service-meta">
                    <span><?= e($service['turnaround_days']) ?> days</span>
                    <strong><?= e(format_money($service['filing_fee'])) ?></strong>
                </div>
                <?php if (is_logged_in() && current_user_is_client()): ?>
                    <a class="btn btn-dark w-full" href="<?= e(base_url('service-order?service=' . urlencode((string) $service['slug']))) ?>">Continue</a>
                <?php else: ?>
                    <a class="btn btn-light w-full" href="<?= e(base_url('auth')) ?>">Login to Continue</a>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
</section>
