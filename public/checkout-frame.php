<?php

declare(strict_types=1);

header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');

$url = trim((string) ($_GET['u'] ?? ''));
$session = preg_replace('/[^a-zA-Z0-9]/', '', (string) ($_GET['sid'] ?? '')) ?? '';
$returnUrl = trim((string) ($_GET['r'] ?? ''));
$discordMode = strtolower(trim((string) ($_GET['mode'] ?? ''))) === 'discord';

if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
    http_response_code(400);
    header('Content-Type: text/html; charset=utf-8');
    echo '<p style="font-family:sans-serif;padding:2rem;">Checkout link is missing.</p>';
    exit;
}

$parts = parse_url($url);
$scheme = strtolower((string) ($parts['scheme'] ?? ''));
$host = strtolower((string) ($parts['host'] ?? ''));
if ($scheme !== 'https' || !in_array($host, ['upgrade.chat', 'www.upgrade.chat'], true)) {
    http_response_code(400);
    header('Content-Type: text/html; charset=utf-8');
    echo '<p style="font-family:sans-serif;padding:2rem;">Only Upgrade.Chat checkout can open here.</p>';
    exit;
}

if ($returnUrl !== '' && filter_var($returnUrl, FILTER_VALIDATE_URL)) {
    $returnHost = strtolower((string) (parse_url($returnUrl, PHP_URL_HOST) ?? ''));
    $hostHeader = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $allowedHosts = array_filter([
        $hostHeader,
        explode(':', $hostHeader)[0] ?? '',
        'orionbets.co',
        'www.orionbets.co',
        'orionbets.com',
        'www.orionbets.com',
        'localhost',
        '127.0.0.1',
    ]);
    if ($returnHost !== '' && !in_array($returnHost, $allowedHosts, true)) {
        $returnUrl = '';
    }
}

$html = fetch_upgrade_chat($url, $session);
if ($html === false || $html === '') {
    http_response_code(502);
    header('Content-Type: text/html; charset=utf-8');
    echo '<p style="font-family:sans-serif;padding:2rem;color:#fff;background:#05070b;">Upgrade.Chat could not be loaded in checkout.</p>';
    exit;
}
if (upgrade_chat_product_missing($html, $url)) {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    echo '<p style="font-family:sans-serif;padding:2rem;color:#fff;background:#05070b;line-height:1.6;">This plan\'s Upgrade.Chat product was not found. Open the product in Upgrade.Chat, copy its checkout URL, and paste it on Admin → Subscriptions.</p>';
    exit;
}

$query = [];
parse_str((string) ($parts['query'] ?? ''), $query);
$guestEmail = filter_var((string) ($query['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$guestEmail = is_string($guestEmail) ? $guestEmail : '';
$guestName = trim((string) ($query['name'] ?? ''));

$html = preg_replace('/<meta[^>]+http-equiv=["\']Content-Security-Policy["\'][^>]*>/i', '', $html) ?? $html;
$html = preg_replace('#(\s(?:href|src|action)=["\'])/(?!/)#i', '$1https://upgrade.chat/', $html) ?? $html;
$html = preg_replace('/"guest_checkout_enabled"\s*:\s*false/', '"guest_checkout_enabled":true', $html) ?? $html;
$html = preg_replace('/"email_checkout"\s*:\s*false/', '"email_checkout":true', $html) ?? $html;

$guestEmailJs = json_encode($guestEmail, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$guestNameJs = json_encode($guestName, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$sessionJs = json_encode($session, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$returnJs = json_encode($returnUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$discordModeJs = $discordMode ? 'true' : 'false';
$ucPath = ($parts['path'] ?? '/') . (isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '') . (isset($parts['fragment']) && $parts['fragment'] !== '' ? '#' . $parts['fragment'] : '');
$ucPathJs = json_encode($ucPath, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$keep = <<<HTML
<script>
(function () {
  var prefix = window.location.protocol + '//' + window.location.host + '/checkout-frame.php?u=';
  var sid = {$sessionJs};
  var ret = {$returnJs};
  var guestEmail = {$guestEmailJs};
  var guestName = {$guestNameJs};
  var discordMode = {$discordModeJs};
  var ucPath = {$ucPathJs};
  var upgraded = false;

  (function patchHistory() {
    var rawReplace = History.prototype.replaceState;
    var rawPush = History.prototype.pushState;
    function localUrl(url) {
      if (url == null || url === '') return url;
      try {
        var next = new URL(String(url), window.location.href);
        if (next.hostname === 'upgrade.chat' || next.hostname === 'www.upgrade.chat' || next.origin !== window.location.origin) {
          return next.pathname + next.search + next.hash;
        }
      } catch (err) {}
      return url;
    }
    History.prototype.replaceState = function (state, title, url) {
      if (arguments.length > 2) {
        try { return rawReplace.call(this, state, title, localUrl(url)); }
        catch (err) { try { return rawReplace.call(this, state, title); } catch (e2) {} }
        return;
      }
      return rawReplace.apply(this, arguments);
    };
    History.prototype.pushState = function (state, title, url) {
      if (arguments.length > 2) {
        try { return rawPush.call(this, state, title, localUrl(url)); }
        catch (err) { try { return rawPush.call(this, state, title); } catch (e2) {} }
        return;
      }
      return rawPush.apply(this, arguments);
    };
    try {
      if (ucPath && window.location.pathname.indexOf('/checkout-frame.php') !== -1) {
        rawReplace.call(history, history.state, '', ucPath);
      }
    } catch (err) {}
  })();

  function notify(event, extra) {
    try {
      var payload = Object.assign({ source: 'orion-checkout', event: event, token: sid }, extra || {});
      if (window.parent && window.parent !== window) window.parent.postMessage(payload, '*');
    } catch (err) {}
  }

  function patchNode(node) {
    if (!node || typeof node !== 'object') return;
    if (Object.prototype.hasOwnProperty.call(node, 'guest_checkout_enabled')) node.guest_checkout_enabled = true;
    if (Object.prototype.hasOwnProperty.call(node, 'email_checkout')) node.email_checkout = true;
    Object.keys(node).forEach(function (key) {
      if (node[key] && typeof node[key] === 'object') patchNode(node[key]);
    });
  }

  function patchJsonText(text) {
    try {
      var data = JSON.parse(text);
      patchNode(data);
      return JSON.stringify(data);
    } catch (err) {
      return text;
    }
  }

  function patchNextData() {
    var dataEl = document.getElementById('__NEXT_DATA__');
    if (!dataEl || !dataEl.textContent) return;
    try {
      var data = JSON.parse(dataEl.textContent);
      patchNode(data);
      dataEl.textContent = JSON.stringify(data);
    } catch (err) {}
  }

  function hostOf(raw) {
    try { return new URL(raw, 'https://upgrade.chat/').hostname; } catch (err) { return ''; }
  }

  function isProcessor(raw) {
    var host = hostOf(raw);
    return host === 'paypal.com' || host.endsWith('.paypal.com') || host === 'stripe.com' || host.endsWith('.stripe.com') || host === 'js.stripe.com';
  }

  function isAuth(raw) {
    try {
      var next = new URL(raw, 'https://upgrade.chat/');
      if (discordMode && hostOf(raw).indexOf('discord') !== -1) return false;
      return /oauth|login|signin|auth/i.test(next.pathname) || hostOf(raw).indexOf('discord') !== -1;
    } catch (err) { return false; }
  }

  function isProductPath(path) {
    return /\/p\/[0-9a-f-]{8,}/i.test(path || '');
  }

  function isMarketingPath(path) {
    path = (path || '/').toLowerCase();
    if (path === '/' || path === '') return true;
    return /\/(affiliates|login|register|support|merchants|become|docs|blog|pricing|features)(\/|$)/i.test(path);
  }

  function isSuccess(raw) {
    try {
      var next = new URL(raw, window.location.href);
      var host = (next.hostname || '').toLowerCase();
      var path = (next.pathname || '').toLowerCase();
      if (path.indexOf('/checkout-frame.php') !== -1) return false;
      if (path === '/thank-you' || path.indexOf('/thank-you/') === 0) return true;
      if (path === '/checkout/complete' || path.indexOf('/checkout/complete') === 0) return true;
      if ((host === 'upgrade.chat' || host === 'www.upgrade.chat') && /\/(thank|success|complete|paid|receipt|confirmed)(\/|$)/i.test(path)) return true;
      return false;
    } catch (err) { return false; }
  }

  function toUpgrade(raw) {
    try {
      var next = new URL(raw, window.location.href);
      if (next.origin === window.location.origin && next.pathname.indexOf('/checkout-frame.php') === -1 && next.pathname.indexOf('/checkout-uc-proxy.php') === -1) {
        return 'https://upgrade.chat' + next.pathname + next.search + next.hash;
      }
    } catch (err) {}
    return raw;
  }

  function isUcHost(host) {
    return host === 'upgrade.chat' || host === 'www.upgrade.chat' || host === 'api.upgrade.chat';
  }

  function proxied(raw) {
    try {
      var abs = toUpgrade(raw);
      var next = new URL(abs, 'https://upgrade.chat/');
      if (next.origin === window.location.origin) return abs;
      if (isProcessor(next.href)) return abs;
      if (isUcHost(next.hostname) && next.protocol === 'https:') {
        var extra = sid ? '&sid=' + encodeURIComponent(sid) : '';
        return window.location.protocol + '//' + window.location.host + '/checkout-uc-proxy.php?u=' + encodeURIComponent(next.href) + extra;
      }
    } catch (err) {}
    return raw;
  }

  function rewriteFetchInput(input) {
    if (typeof input === 'string') return proxied(input);
    if (typeof URL !== 'undefined' && input instanceof URL) return proxied(input.href);
    if (input && typeof input === 'object' && input.url) return new Request(proxied(input.url), input);
    return input;
  }

  function wrapped(raw) {
    try {
      var next = new URL(raw, 'https://upgrade.chat/');
      if (isProcessor(next.href)) return null;
      if (isAuth(next.href)) return null;
      if (isMarketingPath(next.pathname)) return null;
      if (next.protocol === 'https:' && (next.hostname === 'upgrade.chat' || next.hostname === 'www.upgrade.chat')) {
        var extra = sid ? '&sid=' + encodeURIComponent(sid) : '';
        if (ret) extra += '&r=' + encodeURIComponent(ret);
        return prefix + encodeURIComponent(next.href) + extra;
      }
    } catch (err) {}
    return null;
  }

  function finish() {
    notify('complete');
    if (window.parent && window.parent !== window) return;
    if (ret) window.location.replace(ret);
  }

  function stay(url) {
    if (isSuccess(url) && ret) {
      finish();
      return true;
    }
    if (isProcessor(url)) {
      notify('processor');
      return false;
    }
    try {
      var dest = new URL(url, 'https://upgrade.chat/');
      if (isMarketingPath(dest.pathname) || isAuth(dest.href)) {
        return true;
      }
    } catch (err) {}
    var next = wrapped(url);
    if (!next) return false;
    window.location.replace(next);
    return true;
  }

  if (window.fetch) {
    var nativeFetch = window.fetch;
    window.fetch = function (input, init) {
      try {
        input = rewriteFetchInput(input);
      } catch (err) {}
      return nativeFetch.call(this, input, init).then(function (res) {
        var type = (res.headers && res.headers.get('content-type')) || '';
        if (type.indexOf('json') === -1) return res;
        return res.text().then(function (text) {
          return new Response(patchJsonText(text), {
            status: res.status,
            statusText: res.statusText,
            headers: res.headers
          });
        });
      });
    };
  }

  if (window.XMLHttpRequest) {
    var open = XMLHttpRequest.prototype.open;
    XMLHttpRequest.prototype.open = function (method, url) {
      arguments[1] = proxied(url);
      return open.apply(this, arguments);
    };
  }

  var nativeOpen = window.open;
  window.open = function (raw) {
    if (!raw) return null;
    if (isProcessor(raw)) {
      notify('processor');
      window.location.href = raw;
      return window;
    }
    if (stay(raw)) return window;
    return nativeOpen ? nativeOpen.apply(window, arguments) : null;
  };

  function setInput(el, value) {
    if (!el || !value) return;
    var proto = window.HTMLInputElement && window.HTMLInputElement.prototype;
    var desc = proto && Object.getOwnPropertyDescriptor(proto, 'value');
    if (desc && desc.set) desc.set.call(el, value);
    else el.value = value;
    el.dispatchEvent(new Event('input', { bubbles: true }));
    el.dispatchEvent(new Event('change', { bubbles: true }));
  }

  function textOf(el) {
    return ((el && (el.innerText || el.textContent)) || '').replace(/\\s+/g, ' ').trim().toLowerCase();
  }

  function fillGuest() {
    if (!guestEmail) return;
    document.querySelectorAll('input[type="email"], input[name*="email" i], input[autocomplete="email"]').forEach(function (input) {
      if (!input.value) setInput(input, guestEmail);
    });
    if (guestName) {
      document.querySelectorAll('input[name*="name" i], input[autocomplete="name"]').forEach(function (input) {
        if (!input.value) setInput(input, guestName);
      });
    }
  }

  function clickProfileMissing() {
    Array.prototype.slice.call(document.querySelectorAll('button, [role="button"]')).forEach(function (el) {
      var text = textOf(el);
      if (!text || el.getAttribute('data-ob-profile-clicked') === '1') return;
      var isSure = text.indexOf('yes') !== -1 && text.indexOf('sure') !== -1;
      if (isSure) {
        el.setAttribute('data-ob-profile-clicked', '1');
        el.click();
      }
    });
  }

  function clickGuest() {
    var hasEmail = !!document.querySelector('input[type="email"]');
    var clicked = false;
    Array.prototype.slice.call(document.querySelectorAll('button, a, [role="button"]')).forEach(function (el) {
      var text = textOf(el);
      var href = ((el.getAttribute && el.getAttribute('href')) || el.href || '').toLowerCase();
      if (href.indexOf('discord') !== -1 || (text.indexOf('discord') !== -1 && (text.indexOf('login') !== -1 || text.indexOf('continue') !== -1 || text.indexOf('sign') !== -1))) {
        el.style.display = 'none';
        return;
      }
      if (!text || clicked || el.getAttribute('data-ob-guest-clicked') === '1') return;
      var isGuest = text.indexOf('guest') !== -1 || text.indexOf('continue without') !== -1 || text.indexOf('without discord') !== -1 || text.indexOf('continue with email') !== -1 || text.indexOf('pay with email') !== -1;
      var isContinue = hasEmail && (text === 'continue' || text === 'submit' || text === 'continue as guest');
      if (isGuest || isContinue) {
        el.setAttribute('data-ob-guest-clicked', '1');
        clicked = true;
        el.click();
      }
    });
  }

  function clickUpgrade() {
    if (upgraded) return true;
    var nodes = document.querySelectorAll('button, [role="button"], input[type="submit"]');
    for (var i = 0; i < nodes.length; i++) {
      var el = nodes[i];
      if (el.closest && el.closest('header, nav, [role="navigation"]')) continue;
      var text = textOf(el);
      if (!text) continue;
      if (text.indexOf('discord') !== -1 || text.indexOf('login') !== -1 || text.indexOf('affiliate') !== -1) continue;
      if (text.indexOf('chat') !== -1) continue;
      if (text === 'upgrade' || text === 'pay now' || text === 'pay' || text === 'continue to payment' || text === 'continue to pay') {
        upgraded = true;
        notify('upgrade-click');
        el.click();
        return true;
      }
    }
    return false;
  }

  document.addEventListener('click', function (e) {
    var a = e.target && e.target.closest ? e.target.closest('a[href]') : null;
    if (!a) return;
    var href = a.getAttribute('href') || a.href || '';
    if (/discord\\.com|discordapp\\.com/i.test(href)) {
      e.preventDefault();
      e.stopPropagation();
      fillGuest();
      clickGuest();
      return;
    }
    if (isProcessor(href)) {
      notify('processor');
      return;
    }
    if (stay(href)) {
      e.preventDefault();
      e.stopPropagation();
    }
  }, true);

  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form || !form.action) return;
    if (isProcessor(form.action)) {
      notify('processor');
      return;
    }
    if (stay(form.action)) {
      e.preventDefault();
      e.stopPropagation();
    }
  }, true);

  function watch() {
    patchNextData();
    fillGuest();
    notify('ready');
    if (document.body) {
      new MutationObserver(function () {
        fillGuest();
        clickGuest();
        clickProfileMissing();
        clickUpgrade();
      }).observe(document.body, { childList: true, subtree: true });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', watch);
  } else {
    watch();
  }

  var tries = 0;
  var timer = window.setInterval(function () {
    tries += 1;
    fillGuest();
    clickGuest();
    clickProfileMissing();
    clickUpgrade();
    if (isSuccess(window.location.href)) finish();
    if (tries > 80) window.clearInterval(timer);
  }, 350);
})();
</script>
HTML;

$headBits = '<base href="https://upgrade.chat/">'
    . '<style>a[href*="discord.com"],a[href*="discordapp.com"],a[href*="/login"],a[href*="/register"]{display:none!important}</style>'
    . $keep;

if (stripos($html, '<head') !== false) {
    $html = preg_replace('/<head([^>]*)>/i', '<head$1>' . $headBits, $html, 1) ?? ($headBits . $html);
} else {
    $html = $headBits . $html;
}

header('Content-Type: text/html; charset=utf-8');
echo $html;

function upgrade_chat_product_missing(string $html, string $url): bool
{
    if (!preg_match('#/p/([0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12})#', $url, $want)) {
        return false;
    }
    if (!preg_match('/<script id="__NEXT_DATA__"[^>]*>(.*?)<\/script>/s', $html, $match)) {
        return false;
    }
    $data = json_decode($match[1], true);
    $products = is_array($data) ? ($data['props']['pageProps']['account']['products'] ?? null) : null;
    if (!is_array($products) || $products === []) {
        return false;
    }
    $id = strtolower($want[1]);
    foreach ($products as $product) {
        if (strtolower((string) ($product['uuid'] ?? '')) === $id) {
            return false;
        }
    }

    return true;
}

function fetch_upgrade_chat(string $url, string $sid = ''): string|false
{
    $headers = [
        'Accept: text/html,application/xhtml+xml',
        'Accept-Language: en-US,en;q=0.9',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Origin: https://upgrade.chat',
        'Referer: https://upgrade.chat/',
    ];
    $cookieFile = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'orion-uc-' . ($sid !== '' ? $sid : 'anon') . '.txt';

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 4,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_COOKIEFILE => $cookieFile,
            CURLOPT_COOKIEJAR => $cookieFile,
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body === false || $code >= 400) {
            return false;
        }
        return $body;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => implode("\r\n", $headers),
            'timeout' => 12,
            'follow_location' => 1,
        ],
    ]);
    $body = @file_get_contents($url, false, $context);
    return $body === false ? false : $body;
}
