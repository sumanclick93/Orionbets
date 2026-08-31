<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <link rel="icon" href="<?= e(asset('icons/favicon.svg')) ?>" type="image/svg+xml">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= e(($title ?? 'Unavailable') . ' — ' . site_name()) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>?v=20260821n">
    <script>
      (function () {
        var stored = localStorage.getItem('edgeplay-theme');
        var mode = stored || 'dark';
        if (mode === 'system') mode = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        document.documentElement.setAttribute('data-theme', mode);
      })();
    </script>
</head>
<body class="is-blocked">
<main class="geo-blocked">
    <?= $content ?>
</main>
</body>
</html>
