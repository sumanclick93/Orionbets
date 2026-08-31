<?php

declare(strict_types=1);

/**
 * One-time cPanel installer. Delete this file after setup succeeds.
 */

use App\Core\Database;
use App\Core\Env;
use App\Setup\Installer;

$app = require dirname(__DIR__) . '/app/bootstrap.php';

$lockFile = STORAGE_PATH . '/setup.lock';
$alreadyLocked = is_file($lockFile);
$notice = null;
$error = null;
$ran = false;
$logs = [];
$envPath = Env::loadedPath();

$host = (string) Env::get('DB_HOST', 'localhost');
$database = (string) Env::get('DB_DATABASE', '');
$username = (string) Env::get('DB_USERNAME', '');
$appUrl = (string) Env::get('APP_URL', '');

if ($host === 'db' || $host === 'root' || $host === '') {
    $host = 'localhost';
}
if ($username === 'root') {
    $username = '';
}
if ($database === 'edgeplay') {
    $database = '';
}
if ($appUrl === '' || str_contains($appUrl, 'localhost') || str_contains($appUrl, 'cpanel.site')) {
    $requestHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($requestHost === '' || str_contains($requestHost, 'cpanel.site') || str_ends_with($requestHost, 'orionbets.co')) {
        $appUrl = 'https://orionbets.co';
    } else {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $appUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? '');
    }
}

$action = (string) ($_POST['action'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$alreadyLocked) {
    try {
        if ($action === 'save') {
            $envPath = Installer::saveEnv($_POST);
            Database::getInstance()->disconnect();
            $host = (string) Env::get('DB_HOST', 'localhost');
            $database = (string) Env::get('DB_DATABASE', '');
            $username = (string) Env::get('DB_USERNAME', '');
            $appUrl = (string) Env::get('APP_URL', $appUrl);
            $notice = 'Saved database settings to ' . $envPath;
        } elseif ($action === 'install') {
            $logs = Installer::setup();
            file_put_contents($lockFile, date('c') . PHP_EOL);
            $alreadyLocked = true;
            $ran = true;
            $notice = 'Setup finished. Delete setup.php from File Manager now.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
} elseif ($alreadyLocked) {
    $notice = 'Setup already ran. Delete this file from File Manager.';
}

$connected = false;
$seeded = false;

try {
    $db = Database::getInstance();
    $db->pdo();
    $connected = true;
    $seeded = $db->tableExists('users') && (bool) $db->fetch('SELECT id FROM users LIMIT 1');
} catch (Throwable $e) {
    if ($error === null) {
        $error = $e->getMessage();
    }
}

header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Orion Bets setup</title>
    <style>
        body { font-family: Georgia, serif; background: #111; color: #EAE6DC; margin: 0; padding: 48px 20px; }
        main { max-width: 640px; margin: 0 auto; }
        h1 { font-size: 1.6rem; }
        p, li, label { line-height: 1.5; }
        code { background: #1c1c1c; padding: 2px 6px; }
        .box { border: 1px solid #3a3a3a; padding: 16px 20px; margin: 20px 0; }
        .ok { color: #b7d7a8; }
        .bad { color: #e8a0a0; }
        label { display: block; margin: 12px 0 4px; }
        input { width: 100%; box-sizing: border-box; padding: 8px; font: inherit; background: #1c1c1c; color: #EAE6DC; border: 1px solid #3a3a3a; }
        button, .btn { background: #EAE6DC; color: #111; border: 0; padding: 10px 18px; font: inherit; cursor: pointer; text-decoration: none; display: inline-block; margin-top: 16px; }
    </style>
</head>
<body>
<main>
    <p>Orion Bets</p>
    <h1>Database setup</h1>

    <div class="box">
        <p>Env file: <code><?= htmlspecialchars($envPath ?: 'not found — fill the form below') ?></code></p>
        <p class="<?= $connected ? 'ok' : 'bad' ?>"><?= $connected ? 'MySQL connection works.' : 'MySQL connection failed.' ?></p>
        <?php if ($error && !$ran): ?>
            <p class="bad"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <?php if ($notice): ?>
            <p class="ok"><?= htmlspecialchars($notice) ?></p>
        <?php endif; ?>
        <?php foreach ($logs as $line): ?>
            <p><?= htmlspecialchars($line) ?></p>
        <?php endforeach; ?>
    </div>

    <?php if ($alreadyLocked || ($connected && $seeded)): ?>
        <p><a class="btn" href="/">Open the site</a></p>
        <p>Delete <code>setup.php</code> from File Manager.</p>
    <?php elseif ($connected): ?>
        <form method="post">
            <p>Connection is good. This will create tables and load demo data.</p>
            <input type="hidden" name="action" value="install">
            <button type="submit">Run migrations and seed</button>
        </form>
    <?php else: ?>
        <p>In cPanel → <strong>MySQL Databases</strong>, create a database and user, then add the user to the database with ALL PRIVILEGES. Paste the <strong>full</strong> names here (they look like <code>account_edgeplay</code>).</p>
        <form method="post">
            <input type="hidden" name="action" value="save">
            <label>DB_HOST</label>
            <input name="db_host" value="<?= htmlspecialchars($host) ?>" required>
            <label>DB_DATABASE</label>
            <input name="db_database" value="<?= htmlspecialchars($database) ?>" placeholder="account_edgeplay" required>
            <label>DB_USERNAME</label>
            <input name="db_username" value="<?= htmlspecialchars($username) ?>" placeholder="account_dbuser" required>
            <label>DB_PASSWORD</label>
            <input name="db_password" type="password" required>
            <label>APP_URL</label>
            <input name="app_url" value="<?= htmlspecialchars($appUrl) ?>">
            <button type="submit">Save and test connection</button>
        </form>
    <?php endif; ?>
</main>
</body>
</html>
