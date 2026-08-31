<?php

declare(strict_types=1);

header('Content-Type: application/xml; charset=UTF-8');

$base = rtrim(function_exists('web_base_url') ? web_base_url() : (string) env_get('APP_URL', 'https://orionbets.co'), '/');
$pages = [
    '/',
    '/how-it-works',
    '/picks',
    '/performance',
    '/pricing',
    '/about',
    '/affiliates',
    '/faq',
    '/contact',
    '/privacy',
    '/terms',
    '/disclaimer',
    '/cookies',
];

echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;
foreach ($pages as $page) {
    echo '  <url><loc>' . htmlspecialchars($base . $page, ENT_XML1) . '</loc><changefreq>daily</changefreq></url>' . PHP_EOL;
}
echo '</urlset>';
