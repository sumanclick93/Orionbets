<?php
// Test mobile v2 profile endpoint vs web v1 profile endpoint

$userId = '4690304';

function fetchUrl($url, $isMobile = false) {
    $ch = curl_init($url);
    $headers = [
        'Accept: application/json, text/plain, */*',
        'Referer: https://www.actionnetwork.com/my-action',
        'Origin: https://www.actionnetwork.com',
    ];
    if ($isMobile) {
        $headers[] = 'User-Agent: ActionNetwork/3.0.0 (com.actionnetwork.app; build:1; iOS 16.0.0) Alamofire/5.4.0';
    } else {
        $headers[] = 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36';
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['status' => $status, 'data' => json_decode($body, true), 'body' => $body];
}

$urls = [
    'mobile_v2_profile' => "https://api.actionnetwork.com/mobile/v2/users/{$userId}/profile",
    'mobile_v1_profile' => "https://api.actionnetwork.com/mobile/v1/users/{$userId}/profile",
    'web_v1_profile' => "https://api.actionnetwork.com/web/v1/users/{$userId}/profile",
    'web_v2_profile' => "https://api.actionnetwork.com/web/v2/users/{$userId}/profile",
];

foreach ($urls as $name => $url) {
    $res = fetchUrl($url, str_contains($name, 'mobile'));
    echo "=== {$name} ({$url}) ===\n";
    echo "Status: {$res['status']}\n";
    if ($res['status'] === 200 && is_array($res['data'])) {
        echo json_encode($res['data'], JSON_PRETTY_PRINT) . "\n\n";
    } else {
        echo "Error body: " . substr($res['body'], 0, 300) . "\n\n";
    }
}
