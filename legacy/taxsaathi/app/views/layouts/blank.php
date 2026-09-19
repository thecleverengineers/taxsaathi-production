<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= e($title ?? setting('site_name', 'Tax Saathi')) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="data:,">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="blank-page">
    <?= $content ?>
</body>
</html>
