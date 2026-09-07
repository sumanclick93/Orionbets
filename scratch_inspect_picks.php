<?php
$userId = '4690304';
$url = "https://api.actionnetwork.com/mobile/v1/users/{$userId}/picks?page=1&limit=50";
$ua = 'ActionNetwork/3.0.0 (com.actionnetwork.app; build:1; iOS 16.0.0) Alamofire/5.4.0';

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json, text/plain, */*',
        'User-Agent: ' . $ua,
        'Referer: https://www.actionnetwork.com/my-action',
        'Origin: https://www.actionnetwork.com'
    ]
]);
$res = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
echo "Status: {$status}\n";
$json = json_decode($res, true);
echo "Root keys: " . implode(', ', array_keys($json ?? [])) . "\n";
if (isset($json['picks'])) {
    echo "picks count: " . count($json['picks']) . "\n";
    echo "First pick sample: " . json_encode($json['picks'][0], JSON_PRETTY_PRINT) . "\n";
} elseif (isset($json['items'])) {
    echo "items count: " . count($json['items']) . "\n";
    echo "First item sample: " . json_encode($json['items'][0], JSON_PRETTY_PRINT) . "\n";
} else {
    echo "Full json sample: " . substr(json_encode($json, JSON_PRETTY_PRINT), 0, 1000) . "\n";
}
