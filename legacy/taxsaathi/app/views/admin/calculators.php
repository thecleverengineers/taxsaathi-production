<section class="grid cols-2 mobile-1 gap-lg">
    <div class="card">
        <div class="section-head left"><span class="eyebrow">Calculator Settings</span><h2>Global Settings</h2></div>
        <form method="post" action="<?= e(base_url('admin/calculators/save-settings')) ?>" class="grid cols-2 mobile-1 gap-md">
            <?= csrf_field() ?>
            <?php foreach ($settings as $key => $value): ?>
                <label class="field">
                    <span><?= e(ucwords(str_replace('_', ' ', $key))) ?></span>
                    <input class="input" type="text" name="settings[<?= e($key) ?>]" value="<?= e($value) ?>">
                </label>
            <?php endforeach; ?>
            <button class="btn btn-dark cols-span-2" type="submit">Save Settings</button>
        </form>
    </div>

    <div class="card">
        <div class="section-head left"><span class="eyebrow">Tax Rules</span><h2>Add Rule</h2></div>
        <form method="post" action="<?= e(base_url('admin/calculators/save-rule')) ?>" class="grid cols-2 mobile-1 gap-md">
            <?= csrf_field() ?>
            <input class="input" type="text" name="financial_year" placeholder="2025-26" required>
            <select class="input" name="regime"><option value="new">New</option><option value="old">Old</option></select>
            <input class="input" type="number" step="0.01" name="income_from" placeholder="Income from">
            <input class="input" type="number" step="0.01" name="income_to" placeholder="Income to">
            <input class="input" type="number" step="0.01" name="rate_percent" placeholder="Rate %">
            <button class="btn btn-dark cols-span-2" type="submit">Add Rule</button>
        </form>

        <hr class="my-lg">
        <?php foreach ($rules as $rule): ?>
            <div class="list-item">
                <div>
                    <strong><?= e($rule['financial_year']) ?> • <?= e(strtoupper($rule['regime'])) ?></strong>
                    <p class="muted"><?= e(format_money($rule['income_from'])) ?> to <?= $rule['income_to'] !== null ? e(format_money($rule['income_to'])) : 'Above' ?> • <?= e((string) $rule['rate_percent']) ?>%</p>
                </div>
                <form method="post" action="<?= e(base_url('admin/calculators/delete-rule')) ?>" class="inline-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= e((string) $rule['id']) ?>">
                    <button class="btn btn-danger btn-sm" type="submit">Delete</button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
</section>
