<?php
$settings = is_array($settings ?? null) ? $settings : [];

$resolveSettingAssetUrl = static function (?string $path): string {
    $path = trim((string) $path);

    if ($path === '') {
        return '';
    }

    if (preg_match('~^(https?:)?//~i', $path) === 1 || str_starts_with($path, 'data:')) {
        return $path;
    }

    return base_url(ltrim($path, '/'));
};

$currentLogoPathRaw = trim((string) ($settings['site_logo'] ?? ''));
$currentLogoPath = $currentLogoPathRaw !== '' ? $currentLogoPathRaw : 'uploads/logo3.png';
$currentLogoAlt = trim((string) ($settings['site_logo_alt'] ?? 'Tax Saathi Logo'));
$currentLogoUrl = $resolveSettingAssetUrl($currentLogoPath);
$logoManagedKeys = ['site_logo', 'site_logo_alt'];
$textareaSettingKeys = ['hero_subheading', 'why_text', 'contact_address'];
?>
<section class="overflow-hidden rounded-[20px] border border-slate-200 bg-white shadow-[0_12px_26px_rgba(15,23,42,0.05)]">
    <div class="border-b border-slate-100 px-4 py-4">
        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Website Control</p>
        <h2 class="mt-0.5 text-[1.15rem] font-bold tracking-[-0.03em] text-slate-900">Website Settings</h2>
    </div>

    <form method="post" action="<?= e(base_url('admin/content/save-settings')) ?>" enctype="multipart/form-data" class="grid grid-cols-1 gap-3 p-4 md:grid-cols-2">
        <?= csrf_field() ?>

        <div class="md:col-span-2 overflow-hidden rounded-[20px] border border-slate-200 bg-gradient-to-br from-slate-50 via-white to-blue-50/40 p-4">
            <div class="grid gap-4 lg:grid-cols-[220px_1fr] lg:items-center">
                <div class="flex flex-col items-center justify-center rounded-[18px] border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex h-28 w-28 items-center justify-center overflow-hidden rounded-[24px] border border-slate-200 bg-slate-50 shadow-inner">
                        <?php if ($currentLogoUrl !== ''): ?>
                            <img
                                src="<?= e($currentLogoUrl) ?>"
                                alt="<?= e($currentLogoAlt !== '' ? $currentLogoAlt : 'Current logo') ?>"
                                class="h-full w-full object-contain p-3"
                            >
                        <?php else: ?>
                            <span class="text-xl font-black tracking-[0.18em] text-slate-400">LOGO</span>
                        <?php endif; ?>
                    </div>

                    <p class="mt-3 max-w-[180px] truncate text-center text-xs font-semibold text-slate-600">
                        <?= e($currentLogoPath !== '' ? basename($currentLogoPath) : 'No logo selected') ?>
                    </p>
                </div>

                <div class="grid gap-3">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Brand Identity</p>
                        <h3 class="mt-0.5 text-[1rem] font-bold tracking-[-0.03em] text-slate-900">Manage Website Logo</h3>
                        <p class="mt-1 text-sm leading-6 text-slate-500">Upload once here and the selected logo will automatically appear in the website header, mobile menu, footer, and favicon area.</p>
                    </div>

                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <label class="grid gap-2">
                            <span class="text-[12px] font-semibold uppercase tracking-[0.14em] text-slate-500">Logo Image</span>
                            <input
                                class="block w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 file:mr-4 file:rounded-full file:border-0 file:bg-brand-50 file:px-4 file:py-2 file:text-xs file:font-bold file:text-brand-700 hover:file:bg-brand-100"
                                type="file"
                                name="site_logo_file"
                                accept=".jpg,.jpeg,.png,.webp,.gif,.svg,image/*"
                            >
                            <span class="text-xs text-slate-500">Allowed: JPG, PNG, WEBP, GIF, SVG. Max 3MB.</span>
                        </label>

                        <label class="grid gap-2">
                            <span class="text-[12px] font-semibold uppercase tracking-[0.14em] text-slate-500">Logo Alt Text</span>
                            <input
                                class="h-11 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm text-slate-700 outline-none transition focus:border-brand-300 focus:ring-4 focus:ring-brand-100/60"
                                type="text"
                                name="settings[site_logo_alt]"
                                value="<?= e($currentLogoAlt) ?>"
                                placeholder="Tax Saathi Logo"
                            >
                        </label>
                    </div>

                    <?php if ($currentLogoPathRaw !== ''): ?>
                        <label class="inline-flex w-fit items-center gap-2.5 rounded-2xl border border-rose-100 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">
                            <input type="checkbox" name="remove_site_logo" value="1" class="h-4 w-4 rounded border-rose-300 text-rose-600 focus:ring-rose-500">
                            <span>Remove current logo and use default fallback</span>
                        </label>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php foreach ($settings as $key => $value): ?>
            <?php if (in_array((string) $key, $logoManagedKeys, true)) { continue; } ?>
            <label class="grid gap-2 <?= in_array($key, $textareaSettingKeys, true) ? 'md:col-span-2' : '' ?>">
                <span class="text-[12px] font-semibold uppercase tracking-[0.14em] text-slate-500">
                    <?= e(ucwords(str_replace('_', ' ', $key))) ?>
                </span>

                <?php if (in_array($key, $textareaSettingKeys, true)): ?>
                    <textarea
                        class="min-h-[110px] w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-brand-300 focus:bg-white focus:ring-4 focus:ring-brand-100/60"
                        name="settings[<?= e($key) ?>]"
                        rows="3"
                    ><?= e($value) ?></textarea>
                <?php else: ?>
                    <input
                        class="h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700 outline-none transition focus:border-brand-300 focus:bg-white focus:ring-4 focus:ring-brand-100/60"
                        type="text"
                        name="settings[<?= e($key) ?>]"
                        value="<?= e($value) ?>"
                    >
                <?php endif; ?>
            </label>
        <?php endforeach; ?>

        <button
            class="inline-flex h-11 items-center justify-center rounded-2xl bg-slate-900 px-5 text-sm font-semibold text-white shadow-[0_10px_24px_rgba(15,23,42,0.14)] transition hover:-translate-y-[1px] hover:bg-slate-800 md:col-span-2"
            type="submit"
        >
            Save Website Settings
        </button>
    </form>
</section>
