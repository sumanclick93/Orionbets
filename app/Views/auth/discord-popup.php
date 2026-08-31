<?php
$ok = !empty($ok);
$redirect = (string) ($redirect ?? ($ok ? '/dashboard' : '/login'));
$message = (string) ($message ?? ($ok ? 'Signed in with Discord.' : 'Discord sign-in could not be completed.'));
$user = is_array($user ?? null) ? $user : null;
$event = $ok ? 'DISCORD_AUTH_SUCCESS' : 'DISCORD_AUTH_ERROR';
$payload = json_encode([
    'type' => $event,
    'event' => $event,
    'source' => 'orionbets:discord-auth',
    'status' => $ok ? 'ok' : 'error',
    'redirect' => $redirect,
    'csrf' => (string) ($csrf ?? csrf_token()),
    'user' => $user,
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_UNESCAPED_SLASHES);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($ok ? 'Signed in' : 'Sign-in failed') ?> — Orion Bets</title>
    <style>
        html, body { margin: 0; min-height: 100%; background: #0B0D10; color: #EAE6DC; font: 15px/1.45 Archivo, Helvetica, sans-serif; }
        body { display: grid; place-items: center; padding: 1.5rem; }
        .card { width: min(22rem, 100%); text-align: center; }
        .mark { width: 2.5rem; height: 2.5rem; margin: 0 auto 1rem; border: 1px solid #262B33; display: grid; place-items: center; color: <?= $ok ? '#4CC27E' : '#E07A72' ?>; font-size: 1.1rem; }
        p { margin: 0 0 1rem; color: #A8A499; }
        a { color: #EAE6DC; }
    </style>
</head>
<body>
    <div class="card">
        <div class="mark" aria-hidden="true"><?= $ok ? '✓' : '×' ?></div>
        <p><?= e($message) ?></p>
        <p><a href="<?= e($redirect) ?>">Continue</a></p>
    </div>
    <script>
      (function () {
        var payload = <?= $payload ?>;
        try {
          if (window.opener && !window.opener.closed) {
            window.opener.postMessage(payload, window.location.origin);
          }
        } catch (err) {}
        try {
          window.localStorage.setItem('orionbets:discord-auth', JSON.stringify(payload));
        } catch (err) {}
        window.close();
        window.setTimeout(function () {
          window.location.replace(payload.redirect || '/dashboard');
        }, 400);
      })();
    </script>
</body>
</html>
