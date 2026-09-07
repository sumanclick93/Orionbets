<?php
$userId = '4690304';

function getUrl($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json, text/plain, */*',
            'User-Agent: ActionNetwork/3.0.0 (com.actionnetwork.app; build:1; iOS 16.0.0) Alamofire/5.4.0',
            'Referer: https://www.actionnetwork.com/my-action',
            'Origin: https://www.actionnetwork.com',
        ],
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['status' => $status, 'data' => json_decode($body, true)];
}

$endpoints = [
    "https://api.actionnetwork.com/mobile/v2/users/{$userId}/profile",
    "https://api.actionnetwork.com/mobile/v2/users/{$userId}/summary",
    "https://api.actionnetwork.com/mobile/v2/users/{$userId}/metrics",
    "https://api.actionnetwork.com/mobile/v2/users/{$userId}/leaderboard",
    "https://api.actionnetwork.com/mobile/v1/users/{$userId}/summary",
    "https://api.actionnetwork.com/mobile/v1/users/{$userId}/metrics",
    "https://api.actionnetwork.com/mobile/v1/users/{$userId}/leaderboard",
    "https://api.actionnetwork.com/mobile/v1/users/{$userId}/badge",
    "https://api.actionnetwork.com/mobile/v1/users/{$userId}/badges",
    "https://api.actionnetwork.com/mobile/v2/users/{$userId}/badge",
    "https://api.actionnetwork.com/mobile/v2/users/{$userId}/badges",
];

foreach ($endpoints as $url) {
    $res = getUrl($url);
    echo "URL: {$url} -> Status: {$res['status']}\n";
    if ($res['status'] === 200 && is_array($res['data'])) {
        echo json_encode($res['data'], JSON_PRETTY_PRINT) . "\n\n";
    }
}
