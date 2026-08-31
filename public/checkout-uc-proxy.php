<?php

declare(strict_types=1);

header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

$url = trim((string) ($_GET['u'] ?? ''));
if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo '{"error":"Checkout proxy URL is missing."}';
    exit;
}

$parts = parse_url($url);
$scheme = strtolower((string) ($parts['scheme'] ?? ''));
$host = strtolower((string) ($parts['host'] ?? ''));
$allowed = ['upgrade.chat', 'www.upgrade.chat', 'api.upgrade.chat'];
if ($scheme !== 'https' || !in_array($host, $allowed, true)) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo '{"error":"Only Upgrade.Chat APIs can be proxied."}';
    exit;
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$sid = preg_replace('/[^a-zA-Z0-9]/', '', (string) ($_GET['sid'] ?? '')) ?? '';
$cookieFile = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'orion-uc-' . ($sid !== '' ? $sid : 'anon') . '.txt';
$pageOrigin = $host === 'www.upgrade.chat' ? 'https://www.upgrade.chat' : 'https://upgrade.chat';

$incoming = function_exists('getallheaders') ? getallheaders() : [];
$forward = [
    'Accept: ' . (is_string($incoming['Accept'] ?? $incoming['accept'] ?? null) ? ($incoming['Accept'] ?? $incoming['accept']) : 'application/json, text/plain, */*'),
    'Accept-Language: ' . (is_string($incoming['Accept-Language'] ?? $incoming['accept-language'] ?? null) ? ($incoming['Accept-Language'] ?? $incoming['accept-language']) : 'en-US,en;q=0.9'),
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    'Origin: ' . $pageOrigin,
    'Referer: ' . $pageOrigin . '/',
];

$contentType = (string) ($incoming['Content-Type'] ?? $incoming['content-type'] ?? ($_SERVER['CONTENT_TYPE'] ?? ''));
if ($contentType !== '') {
    $forward[] = 'Content-Type: ' . $contentType;
}

$auth = (string) ($incoming['Authorization'] ?? $incoming['authorization'] ?? '');
if ($auth !== '') {
    $forward[] = 'Authorization: ' . $auth;
}

$body = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true) ? (file_get_contents('php://input') ?: '') : '';

if (!function_exists('curl_init')) {
    http_response_code(502);
    header('Content-Type: application/json; charset=utf-8');
    echo '{"error":"Checkout proxy requires cURL."}';
    exit;
}

$ch = curl_init($url);
$options = [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 3,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_CONNECTTIMEOUT => 8,
    CURLOPT_CUSTOMREQUEST => $method,
    CURLOPT_HTTPHEADER => $forward,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_HEADER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_COOKIEFILE => $cookieFile,
    CURLOPT_COOKIEJAR => $cookieFile,
];
if ($body !== '' || in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
    $options[CURLOPT_POSTFIELDS] = $body;
}
curl_setopt_array($ch, $options);
$response = curl_exec($ch);
$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$error = curl_error($ch);
curl_close($ch);

if ($response === false) {
    http_response_code(502);
    header('Content-Type: application/json; charset=utf-8');
    echo '{"error":"Upgrade.Chat could not be reached."}';
    exit;
}

$rawHeaders = substr($response, 0, $headerSize);
$payload = substr($response, $headerSize);
$pass = ['content-type', 'cache-control'];
foreach (explode("\r\n", $rawHeaders) as $line) {
    $partsHeader = explode(':', $line, 2);
    if (count($partsHeader) !== 2) {
        continue;
    }
    $name = strtolower(trim($partsHeader[0]));
    $value = trim($partsHeader[1]);
    if (in_array($name, $pass, true) && $value !== '') {
        header($name . ': ' . $value, false);
    }
}

http_response_code($code > 0 ? $code : 502);
echo $payload;
