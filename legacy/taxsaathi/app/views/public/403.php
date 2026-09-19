<section class="blank-state">
    <div class="container card center">
        <h1>403</h1>
        <p>You do not have access to this section.</p>
        <?php if (!empty($permission)): ?><p class="muted">Required permission: <?= e($permission) ?></p><?php endif; ?>
        <a class="btn btn-dark" href="<?= e(base_url('dashboard')) ?>">Back to Dashboard</a>
    </div>
</section>
