<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <?= site_favicon_html() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= e(($status ?? 500) . ' — ' . site_name()) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>?v=20260821n">
    <script>
      (function () {
        var stored = localStorage.getItem('edgeplay-theme');
        var mode = stored || 'system';
        if (mode === 'system') mode = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        document.documentElement.setAttribute('data-theme', mode);
      })();
    </script>
</head>
<body class="is-marketing">
<main class="error-page">
    <?= $content ?>
    <p><a class="ob-btn" href="<?= e(url('/')) ?>">Back home</a></p>
</main>
</body>
</html>
