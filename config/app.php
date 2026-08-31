<?php

declare(strict_types=1);

use App\Core\Env;

return [
    'name' => Env::get('APP_NAME', 'Orion Bets'),
    'env' => Env::get('APP_ENV', 'local'),
    'debug' => Env::bool('APP_DEBUG'),
    'url' => Env::get('APP_URL', 'https://orionbets.co'),
    'timezone' => Env::get('APP_TIMEZONE', 'UTC'),
    'key' => Env::get('APP_KEY', ''),
    'discord' => [
        'client_id' => Env::get('DISCORD_CLIENT_ID', ''),
        'client_secret' => Env::get('DISCORD_CLIENT_SECRET', ''),
        'redirect_uri' => Env::get('DISCORD_REDIRECT_URI', ''),
    ],
    'paypal' => [
        'client_id' => Env::get('PAYPAL_CLIENT_ID', ''),
        'client_secret' => Env::get('PAYPAL_CLIENT_SECRET', ''),
        'env' => Env::get('PAYPAL_ENV', 'sandbox'),
    ],
    'action_network' => [
        'user_id' => Env::get('ACTION_NETWORK_USER_ID', ''),
        'api_key' => Env::get('ACTION_NETWORK_API_KEY', ''),
        'leagues' => Env::get('ACTION_NETWORK_LEAGUES', 'nfl,ncaaf,nba,ncaab,mlb,nhl,soccer,wnba,ufc,pga,tennis'),
        'base_url' => Env::get('ACTION_NETWORK_BASE_URL', 'https://api.actionnetwork.com/web/v1'),
    ],
];
