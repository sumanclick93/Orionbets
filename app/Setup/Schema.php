<?php

declare(strict_types=1);

namespace App\Setup;

use App\Core\Database;
use Throwable;

final class Schema
{
    public static function ensure(Database $db): void
    {
        if ($db->tableExists('subscription_plans') && !$db->columnExists('subscription_plans', 'payment_url')) {
            $db->pdo()->exec(
                'ALTER TABLE subscription_plans ADD COLUMN payment_url VARCHAR(500) NULL AFTER badge'
            );
        }

        if ($db->tableExists('users') && !$db->columnExists('users', 'discord_id')) {
            $db->pdo()->exec(
                'ALTER TABLE users ADD COLUMN discord_id VARCHAR(64) NULL UNIQUE AFTER email'
            );
        }

        if (
            $db->tableExists('users')
            && $db->columnExists('users', 'discord_id')
            && !$db->indexExists('users', 'discord_id')
            && !$db->indexExists('users', 'uq_users_discord_id')
        ) {
            $db->pdo()->exec('ALTER TABLE users ADD UNIQUE KEY uq_users_discord_id (discord_id)');
        }

        if ($db->tableExists('users') && !$db->columnExists('users', 'is_guest')) {
            $db->pdo()->exec(
                'ALTER TABLE users ADD COLUMN is_guest TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active'
            );
        }

        if ($db->tableExists('users') && !$db->columnExists('users', 'avatar')) {
            $after = $db->columnExists('users', 'discord_id') ? '`discord_id`' : '`email`';
            $db->pdo()->exec(
                "ALTER TABLE users ADD COLUMN avatar VARCHAR(255) NULL AFTER {$after}"
            );
        }

        if ($db->tableExists('roles')) {
            $roles = [
                ['name' => 'Free Member', 'slug' => 'user', 'description' => 'Registered member — free baseline access'],
                ['name' => 'Paid Member', 'slug' => 'premium_user', 'description' => 'Active Playbook subscriber'],
            ];
            foreach ($roles as $role) {
                $exists = $db->fetch('SELECT id FROM roles WHERE slug = :slug', ['slug' => $role['slug']]);
                if (!$exists) {
                    $db->insert('roles', $role);
                }
            }
        }

        if ($db->tableExists('users') && !$db->columnExists('users', 'checkout_cookie')) {
            $db->pdo()->exec(
                'ALTER TABLE users ADD COLUMN checkout_cookie VARCHAR(64) NULL AFTER last_login_ip'
            );
        }

        if ($db->tableExists('subscription_transactions') && !$db->columnExists('subscription_transactions', 'payload')) {
            $db->pdo()->exec(
                'ALTER TABLE subscription_transactions ADD COLUMN payload JSON NULL AFTER description'
            );
        }

        if (!$db->tableExists('checkout_sessions')) {
            $db->pdo()->exec(
                "CREATE TABLE checkout_sessions (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    token VARCHAR(64) NOT NULL UNIQUE,
                    user_id INT UNSIGNED NULL,
                    plan_id SMALLINT UNSIGNED NULL,
                    email VARCHAR(190) NOT NULL,
                    name VARCHAR(160) NOT NULL,
                    payment_url VARCHAR(500) NOT NULL,
                    product_id VARCHAR(80) NULL,
                    browser_cookie VARCHAR(64) NULL,
                    ip VARCHAR(45) NULL,
                    user_agent VARCHAR(255) NULL,
                    status VARCHAR(30) NOT NULL DEFAULT 'pending',
                    provider VARCHAR(40) NOT NULL DEFAULT 'upgradechat',
                    provider_order_id VARCHAR(120) NULL,
                    provider_transaction_id VARCHAR(120) NULL,
                    payload JSON NULL,
                    completed_at DATETIME NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_checkout_email (email),
                    INDEX idx_checkout_status (status),
                    INDEX idx_checkout_order (provider_order_id),
                    INDEX idx_checkout_cookie (browser_cookie)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        if (!$db->tableExists('webhook_events')) {
            $db->pdo()->exec(
                "CREATE TABLE webhook_events (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    provider VARCHAR(40) NOT NULL,
                    event_id VARCHAR(120) NULL,
                    event_type VARCHAR(80) NULL,
                    status VARCHAR(30) NOT NULL DEFAULT 'received',
                    payload JSON NULL,
                    error TEXT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_webhook_event (provider, event_id),
                    INDEX idx_webhook_type (event_type)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        if (!$db->tableExists('geo_restrictions')) {
            $db->pdo()->exec(
                "CREATE TABLE geo_restrictions (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    scope VARCHAR(20) NOT NULL,
                    country_code CHAR(2) NOT NULL,
                    country_name VARCHAR(120) NOT NULL,
                    state_code VARCHAR(20) NOT NULL DEFAULT '',
                    state_name VARCHAR(120) NOT NULL DEFAULT '',
                    city_name VARCHAR(120) NOT NULL DEFAULT '',
                    restricted TINYINT(1) NOT NULL DEFAULT 1,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_geo_rule (scope, country_code, state_code, city_name),
                    INDEX idx_geo_country (country_code),
                    INDEX idx_geo_scope (scope)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        self::ensureCmsSettings($db);
        self::ensureActionNetwork($db);
    }

    public static function tryEnsure(Database $db): void
    {
        try {
            self::ensure($db);
        } catch (Throwable) {
            // Setup or a missing database should not block the request.
        }
        try {
            self::ensureCmsSettings($db);
        } catch (Throwable) {
            // CMS settings must still be attempted even if an earlier step failed.
        }
        try {
            self::ensureEverflow($db);
        } catch (Throwable) {
            // Everflow columns must still be attempted even if an earlier ALTER failed.
        }
        try {
            self::ensureActionNetwork($db);
        } catch (Throwable) {
            // Action Network columns must still be attempted even if an earlier ALTER failed.
        }
    }

    public static function ensureCmsSettings(Database $db): void
    {
        if (!$db->tableExists('cms_settings')) {
            $db->pdo()->exec(
                "CREATE TABLE IF NOT EXISTS cms_settings (
                    `key` VARCHAR(128) NOT NULL PRIMARY KEY,
                    `value` LONGTEXT NULL,
                    `type` VARCHAR(30) NOT NULL DEFAULT 'text',
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        $defaults = [
            'hero_headline' => ['Our best bets, sent before kickoff.', 'text'],
            'hero_subheadline' => ['Daily picks. Public record. No excuses. The Playbook is a daily picks subscription. Every morning you get our best bets — the play, the price, the size — from a system trained on the success of the best bettors in the game. Every result gets posted publicly, win or lose.', 'textarea'],
            'hero_cta_text' => ['Get the picks', 'text'],
            'hero_cta_url' => ['/the-playbook', 'text'],
            'hero_banner_url' => ['', 'image_url'],
            'kickoff_countdown_at' => ['2026-09-09T20:20:00-04:00', 'text'],
            'kickoff_kicker' => ['ORIONBETS · THE PLAYBOOK · NFL', 'text'],
            'kickoff_title_pre' => ['Season One kicks off in', 'text'],
            'kickoff_title_live' => ['Season One is live.', 'text'],
            'kickoff_sub_pre' => ['Lock the founders rate before the first whistle and your price never moves.', 'text'],
            'kickoff_sub_live' => ["Today's slate is out. Every pick posted before kickoff.", 'text'],
            'kickoff_cta_pre' => ['Claim the Founders Rate', 'text'],
            'kickoff_cta_live' => ["Get Today's Picks", 'text'],
            'site_logo_url' => ['', 'image_url'],
            'site_favicon_url' => ['', 'image_url'],
            'promo_banner_text' => ['', 'text'],
            'promo_banner_url' => ['', 'text'],
            'promo_banner_enabled' => ['0', 'text'],
            'about_hero_headline' => ['Victory is found in the Margins', 'text'],
            'story_1_eyebrow' => ['01 · Who we are', 'text'],
            'story_1_title' => ["Built from a bettor's edge.", 'text'],
            'story_1_body' => ["Before we ever bet against the board, our founder priced it. We set lines in Las Vegas deciding what a game was worth, and watching the public tell us where we were wrong.\n\nWe spent years turning this instinct into rules. So we built a system to run those rules across every game, every night, at a scale no person can match.\n\nOur system works while the rest of the world sleeps. It has no favorite teams, no bad beats to avenge, and no bad nights.", 'textarea'],
            'story_1_scrawl' => ['no favorite team. no bad nights.', 'text'],
            'story_2_eyebrow' => ['02 · Our process', 'text'],
            'story_2_title' => ['What lands in your inbox.', 'text'],
            'story_2_body' => ["The system runs overnight across every sport we cover.\n\nIn the morning, someone on our desk goes through what came back and writes it up in plain English: the game, and the odds as we see them. If a call can't be explained in a sentence, it doesn't go out.\n\nEverything is sent before kickoff, with a timestamp. Once the games end, Action Network records the result. They keep the count, so we can't touch it.", 'textarea'],
            'story_3_eyebrow' => ['03 · Our value', 'text'],
            'story_3_title' => ['Six points is the whole game.', 'text'],
            'story_3_body' => ["Every bet comes with a price built in. To come out even, you have to win about 52 out of every 100. That's the wall, and most people never hear about it.\n\nOur record sits at 59.\n\nThat gap looks small written down. Across a season it decides everything, and it only holds up if the stakes stay boring and the decisions stay the same size.\n\nAccess an edge that'll help you stay ahead of the competition. The bets we'd make right in front of you before you decide. Built on a record you can trust.", 'textarea'],
            'valprop_title' => ['The only number the house needs.', 'text'],
            'valprop_subtitle' => ['Certainty is the oldest con in this business.', 'text'],
            'valprop_body' => ['To come out even you have to win about 52 of every 100. That is the wall, and most people never hear about it.', 'textarea'],
            'footer_disclaimer' => ['21+. Informational use only, not betting advice. 1-800-GAMBLER.', 'textarea'],
            'footer_text' => ['Daily picks. Public record. No excuses.', 'text'],
            'everflow_signup_url' => ['https://orionbets.everflowclient.io/affiliate/signup', 'text'],
            'everflow_portal_url' => ['https://orionbets.everflowclient.io/', 'text'],
            'affiliate_support_email' => ['support@orionbets.co', 'text'],
            'affiliate_action_network_url' => ['https://app.actionnetwork.com/4zu6/oharfju5', 'text'],
            'affiliate_hero_eyebrow' => ['OrionBets Affiliate Program', 'text'],
            'affiliate_hero_title' => ['Monetize your sports betting traffic.', 'text'],
            'affiliate_hero_description' => ['Promote a picks product with a 59% verified win rate across every sport — and 68% in the NFL. Both publicly tracked on Action Network, where your audience can check them without taking your word for it. Competitive commissions with no earnings cap — 20% of every monthly subscription for the first four months.', 'textarea'],
            'affiliate_commission_headline' => ['20', 'text'],
            'affiliate_commission_sub' => ['Recurring on monthly plans · up to 4 months', 'text'],
            'affiliate_rate_1_title' => ['20%', 'text'],
            'affiliate_rate_1_sub' => ['Of every monthly subscription first four months', 'text'],
            'affiliate_rate_2_title' => ['$49.99', 'text'],
            'affiliate_rate_2_sub' => ['What a subscription costs the only product', 'text'],
            'affiliate_rate_3_title' => ['No cap', 'text'],
            'affiliate_rate_3_sub' => ['On what you can earn however many you refer', 'text'],
            'affiliate_why_title' => ['Why partner with OrionBets', 'text'],
            'affiliate_band_title' => ['Get in the game.', 'text'],
            'affiliate_band_sub' => ['No earnings cap · 68% NFL, 59% across every sport · signup in minutes', 'text'],
        ];

        foreach ($defaults as $key => [$value, $type]) {
            try {
                $exists = $db->fetch('SELECT `key` FROM `cms_settings` WHERE `key` = :k LIMIT 1', ['k' => $key]);
                if (!$exists) {
                    $db->insert('cms_settings', [
                        'key' => $key,
                        'value' => $value,
                        'type' => $type,
                    ]);
                }
            } catch (Throwable) {
            }
        }
    }

    public static function ensureActionNetwork(Database $db): void
    {
        if ($db->tableExists('events')) {
            self::addColumn($db, 'events', 'action_network_event_id', '`action_network_event_id` VARCHAR(64) NULL');
            self::addColumn($db, 'events', 'home_team', '`home_team` VARCHAR(120) NULL');
            self::addColumn($db, 'events', 'away_team', '`away_team` VARCHAR(120) NULL');
            self::addColumn($db, 'events', 'start_time', '`start_time` DATETIME NULL');
            self::addColumn($db, 'events', 'home_score', '`home_score` INT NULL');
            self::addColumn($db, 'events', 'away_score', '`away_score` INT NULL');
            self::addColumn($db, 'events', 'raw_payload', '`raw_payload` JSON NULL');
            self::addColumn($db, 'events', 'is_active', '`is_active` TINYINT(1) NOT NULL DEFAULT 1');
            self::addColumn($db, 'events', 'is_custom', '`is_custom` TINYINT(1) NOT NULL DEFAULT 0');
            self::addIndex($db, 'events', 'uq_events_an_id', 'UNIQUE KEY `uq_events_an_id` (`action_network_event_id`)');
            self::addIndex($db, 'events', 'idx_events_active', 'INDEX `idx_events_active` (`is_active`)');
            self::addIndex($db, 'events', 'idx_events_start', 'INDEX `idx_events_start` (`start_time`)');
            try {
                $db->pdo()->exec(
                    'UPDATE events SET start_time = event_at WHERE start_time IS NULL AND event_at IS NOT NULL'
                );
            } catch (Throwable) {
            }
        }

        if ($db->tableExists('picks')) {
            self::addColumn($db, 'picks', 'action_network_pick_id', '`action_network_pick_id` VARCHAR(64) NULL');
            self::addColumn($db, 'picks', 'sport', '`sport` VARCHAR(32) NULL');
            self::addColumn($db, 'picks', 'league', '`league` VARCHAR(64) NULL');
            self::addColumn($db, 'picks', 'matchup', '`matchup` VARCHAR(190) NULL');
            self::addColumn($db, 'picks', 'bet_type', '`bet_type` VARCHAR(40) NULL');
            self::addColumn($db, 'picks', 'selection_line', '`selection_line` VARCHAR(120) NULL');
            self::addColumn($db, 'picks', 'odds', '`odds` VARCHAR(20) NULL');
            self::addColumn($db, 'picks', 'units', '`units` DECIMAL(10,2) NULL');
            try {
                $db->pdo()->exec('ALTER TABLE picks MODIFY units DECIMAL(10,2) NULL');
            } catch (Throwable) {
            }
            self::addColumn($db, 'picks', 'sportsbook', '`sportsbook` VARCHAR(80) NULL');
            self::addColumn($db, 'picks', 'is_published', '`is_published` TINYINT(1) NOT NULL DEFAULT 1');
            self::addColumn($db, 'picks', 'is_active', '`is_active` TINYINT(1) NOT NULL DEFAULT 1');
            self::addColumn($db, 'picks', 'is_custom', '`is_custom` TINYINT(1) NOT NULL DEFAULT 0');
            self::addIndex($db, 'picks', 'uq_picks_an_id', 'UNIQUE KEY `uq_picks_an_id` (`action_network_pick_id`)');
            self::addIndex($db, 'picks', 'idx_picks_active', 'INDEX `idx_picks_active` (`is_active`, `is_published`)');
            self::addIndex($db, 'picks', 'idx_picks_bet_type', 'INDEX `idx_picks_bet_type` (`bet_type`)');
        }

        if ($db->tableExists('performance_metrics')) {
            self::addColumn($db, 'performance_metrics', 'period', '`period` VARCHAR(32) NULL');
            self::addColumn($db, 'performance_metrics', 'sport', '`sport` VARCHAR(32) NULL');
            self::addColumn($db, 'performance_metrics', 'roi_pct', '`roi_pct` DECIMAL(6,2) NULL');
            self::addColumn($db, 'performance_metrics', 'units_won', '`units_won` DECIMAL(8,2) NULL');
            self::addColumn($db, 'performance_metrics', 'total_bets', '`total_bets` INT NULL');
            self::addColumn($db, 'performance_metrics', 'synced_at', '`synced_at` DATETIME NULL');
            self::addIndex(
                $db,
                'performance_metrics',
                'idx_perf_period_sport',
                'INDEX `idx_perf_period_sport` (`period`, `sport`)'
            );
            try {
                $db->pdo()->exec(
                    "UPDATE performance_metrics
                     SET period = COALESCE(period, period_type, 'all'),
                         roi_pct = COALESCE(roi_pct, roi),
                         units_won = COALESCE(units_won, units),
                         total_bets = COALESCE(total_bets, total_picks)
                     WHERE period IS NULL OR roi_pct IS NULL OR units_won IS NULL OR total_bets IS NULL"
                );
            } catch (Throwable) {
            }
        }

        if (!$db->tableExists('action_network_sync_logs')) {
            $db->pdo()->exec(
                "CREATE TABLE action_network_sync_logs (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    endpoint VARCHAR(255) NOT NULL,
                    sync_type ENUM('cron', 'manual', 'backfill') NOT NULL DEFAULT 'cron',
                    items_synced INT NOT NULL DEFAULT 0,
                    status ENUM('success', 'failed') NOT NULL DEFAULT 'success',
                    error_message TEXT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_an_sync_created (created_at),
                    INDEX idx_an_sync_type (sync_type, status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        if (!$db->tableExists('action_network_sync_jobs')) {
            $db->pdo()->exec(
                "CREATE TABLE action_network_sync_jobs (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    mode VARCHAR(20) NOT NULL DEFAULT 'live',
                    status VARCHAR(20) NOT NULL DEFAULT 'running',
                    cursor_index INT NOT NULL DEFAULT 0,
                    total_steps INT NOT NULL DEFAULT 0,
                    completed_steps INT NOT NULL DEFAULT 0,
                    items_synced INT NOT NULL DEFAULT 0,
                    changed_steps INT NOT NULL DEFAULT 0,
                    steps_json LONGTEXT NOT NULL,
                    current_label VARCHAR(190) NULL,
                    started_at DATETIME NOT NULL,
                    paused_at DATETIME NULL,
                    completed_at DATETIME NULL,
                    error_message TEXT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_an_job_status (status, updated_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        if (!$db->tableExists('action_network_fingerprints')) {
            $db->pdo()->exec(
                "CREATE TABLE action_network_fingerprints (
                    fingerprint_key VARCHAR(190) NOT NULL PRIMARY KEY,
                    hash CHAR(64) NOT NULL,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        if ($db->tableExists('picks')) {
            try {
                $db->pdo()->exec('ALTER TABLE picks MODIFY analysis MEDIUMTEXT NULL');
            } catch (Throwable) {
            }
        }

        self::ensureActionNetworkCatalog($db);

        if ($db->tableExists('picks') && $db->tableExists('leagues')) {
            try {
                $db->pdo()->exec(
                    "UPDATE picks p
                     INNER JOIN leagues l ON l.id = p.league_id
                     SET p.league = l.slug
                     WHERE (p.league IS NULL OR p.league = '') AND p.league_id IS NOT NULL"
                );
                $db->pdo()->exec(
                    "UPDATE picks p
                     INNER JOIN leagues l ON l.slug = p.league
                     SET p.league_id = l.id
                     WHERE p.league_id IS NULL AND p.league IS NOT NULL AND p.league <> ''"
                );
            } catch (Throwable) {
            }
        }
    }

    private static function ensureActionNetworkCatalog(Database $db): void
    {
        if (!$db->tableExists('sports') || !$db->tableExists('leagues')) {
            return;
        }

        $catalog = [
            ['NFL', 'nfl', 'football', 1],
            ['NCAAF', 'ncaaf', 'football', 2],
            ['NBA', 'nba', 'basketball', 3],
            ['NCAAB', 'ncaab', 'basketball', 4],
            ['MLB', 'mlb', 'baseball', 5],
            ['NHL', 'nhl', 'hockey', 6],
            ['Soccer', 'soccer', 'soccer', 7],
            ['WNBA', 'wnba', 'basketball', 8],
            ['UFC', 'ufc', 'mma', 9],
            ['PGA', 'pga', 'golf', 10],
            ['Tennis', 'tennis', 'tennis', 11],
        ];

        foreach ($catalog as [$name, $slug, $icon, $order]) {
            try {
                $sport = $db->fetch('SELECT id FROM sports WHERE slug = :slug LIMIT 1', ['slug' => $slug]);
                if (!$sport) {
                    $sportId = $db->insert('sports', [
                        'name' => $name,
                        'slug' => $slug,
                        'icon' => $icon,
                        'is_active' => 1,
                        'sort_order' => $order,
                    ]);
                } else {
                    $sportId = (int) $sport['id'];
                }

                $league = $db->fetch(
                    'SELECT id FROM leagues WHERE slug = :slug AND sport_id = :sid LIMIT 1',
                    ['slug' => $slug, 'sid' => $sportId]
                );
                if (!$league) {
                    $db->insert('leagues', [
                        'sport_id' => $sportId,
                        'name' => $name,
                        'slug' => $slug,
                        'country' => 'USA',
                        'is_active' => 1,
                    ]);
                }
            } catch (Throwable) {
            }
        }
    }

    public static function ensureEverflow(Database $db): void
    {
        if ($db->tableExists('checkout_sessions')) {
            self::addColumn($db, 'checkout_sessions', 'everflow_transaction_id', '`everflow_transaction_id` VARCHAR(128) NULL');
            self::ensureVarchar($db, 'checkout_sessions', 'everflow_transaction_id', 128);
        }

        if (!$db->tableExists('everflow_clicks')) {
            $db->pdo()->exec(
                "CREATE TABLE everflow_clicks (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    transaction_id VARCHAR(128) NULL,
                    sub1 VARCHAR(255) NULL,
                    sub2 VARCHAR(255) NULL,
                    sub3 VARCHAR(255) NULL,
                    sub4 VARCHAR(255) NULL,
                    sub5 VARCHAR(255) NULL,
                    affiliate_id VARCHAR(64) NULL,
                    affid VARCHAR(64) NULL,
                    offer_id VARCHAR(64) NULL,
                    oid VARCHAR(64) NULL,
                    landing_url TEXT NULL,
                    landing_path VARCHAR(255) NULL,
                    ip_address VARCHAR(45) NULL,
                    ip VARCHAR(45) NULL,
                    user_agent VARCHAR(255) NULL,
                    email VARCHAR(190) NULL,
                    browser_cookie VARCHAR(64) NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_everflow_tid (transaction_id),
                    INDEX idx_everflow_email (email),
                    INDEX idx_everflow_cookie (browser_cookie)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        $migrated = false;
        $migrated = self::addColumn($db, 'everflow_clicks', 'sub1', '`sub1` VARCHAR(255) NULL') || $migrated;
        self::addColumn($db, 'everflow_clicks', 'sub2', '`sub2` VARCHAR(255) NULL');
        self::addColumn($db, 'everflow_clicks', 'sub3', '`sub3` VARCHAR(255) NULL');
        self::addColumn($db, 'everflow_clicks', 'sub4', '`sub4` VARCHAR(255) NULL');
        self::addColumn($db, 'everflow_clicks', 'sub5', '`sub5` VARCHAR(255) NULL');
        self::addColumn($db, 'everflow_clicks', 'affiliate_id', '`affiliate_id` VARCHAR(64) NULL');
        $migrated = self::addColumn($db, 'everflow_clicks', 'affid', '`affid` VARCHAR(64) NULL') || $migrated;
        self::addColumn($db, 'everflow_clicks', 'offer_id', '`offer_id` VARCHAR(64) NULL');
        $migrated = self::addColumn($db, 'everflow_clicks', 'oid', '`oid` VARCHAR(64) NULL') || $migrated;
        self::addColumn($db, 'everflow_clicks', 'landing_url', '`landing_url` TEXT NULL');
        self::addColumn($db, 'everflow_clicks', 'landing_path', '`landing_path` VARCHAR(255) NULL');
        $migrated = self::addColumn($db, 'everflow_clicks', 'ip_address', '`ip_address` VARCHAR(45) NULL') || $migrated;
        self::addColumn($db, 'everflow_clicks', 'ip', '`ip` VARCHAR(45) NULL');
        self::addColumn($db, 'everflow_clicks', 'user_agent', '`user_agent` VARCHAR(255) NULL');
        self::addColumn($db, 'everflow_clicks', 'email', '`email` VARCHAR(190) NULL');
        self::addColumn($db, 'everflow_clicks', 'browser_cookie', '`browser_cookie` VARCHAR(64) NULL');
        self::addColumn($db, 'everflow_clicks', 'impression_id', '`impression_id` VARCHAR(128) NULL');
        self::addColumn($db, 'everflow_clicks', 'source_id', '`source_id` VARCHAR(64) NULL');
        self::addColumn($db, 'everflow_clicks', 'creative_id', '`creative_id` VARCHAR(64) NULL');
        self::addColumn($db, 'everflow_clicks', 'click_type', "`click_type` VARCHAR(20) NULL");
        self::addIndex($db, 'everflow_clicks', 'idx_everflow_imp', 'INDEX `idx_everflow_imp` (`impression_id`)');
        self::ensureBigintId($db, 'everflow_clicks');
        self::ensureVarchar($db, 'everflow_clicks', 'transaction_id', 128);
        self::ensureVarchar($db, 'everflow_clicks', 'affiliate_id', 64);
        self::ensureVarchar($db, 'everflow_clicks', 'offer_id', 64);
        self::addIndex($db, 'everflow_clicks', 'idx_everflow_tid', 'INDEX `idx_everflow_tid` (`transaction_id`)');

        if (!$db->tableExists('everflow_postbacks')) {
            $db->pdo()->exec(
                "CREATE TABLE everflow_postbacks (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    user_id INT UNSIGNED NULL,
                    order_id VARCHAR(128) NULL,
                    order_number VARCHAR(128) NULL,
                    transaction_id VARCHAR(128) NULL,
                    everflow_transaction_id VARCHAR(128) NULL,
                    amount DECIMAL(10,2) NULL,
                    currency VARCHAR(10) NOT NULL DEFAULT 'USD',
                    event_type VARCHAR(64) NOT NULL DEFAULT 'sale',
                    kind VARCHAR(20) NOT NULL DEFAULT 'sale',
                    sub1 VARCHAR(255) NULL,
                    sub2 VARCHAR(255) NULL,
                    sub3 VARCHAR(255) NULL,
                    sub4 VARCHAR(255) NULL,
                    sub5 VARCHAR(255) NULL,
                    postback_url TEXT NULL,
                    url VARCHAR(700) NULL,
                    http_status INT NULL,
                    response_body TEXT NULL,
                    response TEXT NULL,
                    status ENUM('success', 'failed', 'pending') NOT NULL DEFAULT 'pending',
                    error_message TEXT NULL,
                    email VARCHAR(190) NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_ef_pb_user (user_id),
                    INDEX idx_ef_pb_order (order_id),
                    INDEX idx_ef_pb_order_number (order_number),
                    INDEX idx_ef_pb_tid (transaction_id),
                    INDEX idx_ef_pb_status (status),
                    UNIQUE KEY uq_everflow_postback (kind, order_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        $migrated = self::addColumn($db, 'everflow_postbacks', 'user_id', '`user_id` INT UNSIGNED NULL') || $migrated;
        $migrated = self::addColumn($db, 'everflow_postbacks', 'order_id', '`order_id` VARCHAR(128) NULL') || $migrated;
        self::addColumn($db, 'everflow_postbacks', 'order_number', '`order_number` VARCHAR(128) NULL');
        $migrated = self::addColumn($db, 'everflow_postbacks', 'transaction_id', '`transaction_id` VARCHAR(128) NULL') || $migrated;
        self::addColumn($db, 'everflow_postbacks', 'everflow_transaction_id', '`everflow_transaction_id` VARCHAR(128) NULL');
        self::addColumn($db, 'everflow_postbacks', 'amount', '`amount` DECIMAL(10,2) NULL');
        self::addColumn($db, 'everflow_postbacks', 'currency', "`currency` VARCHAR(10) NOT NULL DEFAULT 'USD'");
        self::addColumn($db, 'everflow_postbacks', 'event_type', "`event_type` VARCHAR(64) NOT NULL DEFAULT 'sale'");
        self::addColumn($db, 'everflow_postbacks', 'kind', "`kind` VARCHAR(20) NOT NULL DEFAULT 'sale'");
        self::addColumn($db, 'everflow_postbacks', 'sub1', '`sub1` VARCHAR(255) NULL');
        self::addColumn($db, 'everflow_postbacks', 'sub2', '`sub2` VARCHAR(255) NULL');
        self::addColumn($db, 'everflow_postbacks', 'sub3', '`sub3` VARCHAR(255) NULL');
        self::addColumn($db, 'everflow_postbacks', 'sub4', '`sub4` VARCHAR(255) NULL');
        self::addColumn($db, 'everflow_postbacks', 'sub5', '`sub5` VARCHAR(255) NULL');
        $migrated = self::addColumn($db, 'everflow_postbacks', 'postback_url', '`postback_url` TEXT NULL') || $migrated;
        self::addColumn($db, 'everflow_postbacks', 'url', '`url` VARCHAR(700) NULL');
        self::addColumn($db, 'everflow_postbacks', 'http_status', '`http_status` INT NULL');
        $migrated = self::addColumn($db, 'everflow_postbacks', 'response_body', '`response_body` TEXT NULL') || $migrated;
        self::addColumn($db, 'everflow_postbacks', 'response', '`response` TEXT NULL');
        $migrated = self::addColumn($db, 'everflow_postbacks', 'status', "`status` ENUM('success','failed','pending') NOT NULL DEFAULT 'pending'") || $migrated;
        self::addColumn($db, 'everflow_postbacks', 'error_message', '`error_message` TEXT NULL');
        self::addColumn($db, 'everflow_postbacks', 'email', '`email` VARCHAR(190) NULL');
        self::addIndex($db, 'everflow_postbacks', 'idx_ef_pb_order_number', 'INDEX `idx_ef_pb_order_number` (`order_number`)');
        self::ensureBigintId($db, 'everflow_postbacks');
        self::ensureVarchar($db, 'everflow_postbacks', 'order_id', 128);
        self::ensureVarchar($db, 'everflow_postbacks', 'order_number', 128);
        self::ensureVarchar($db, 'everflow_postbacks', 'transaction_id', 128);
        self::ensureVarchar($db, 'everflow_postbacks', 'everflow_transaction_id', 128);
        self::ensureVarchar($db, 'everflow_postbacks', 'currency', 10);
        self::ensureVarchar($db, 'everflow_postbacks', 'event_type', 64);
        self::ensureIntColumn($db, 'everflow_postbacks', 'http_status');
        self::addIndex($db, 'everflow_postbacks', 'idx_ef_pb_user', 'INDEX `idx_ef_pb_user` (`user_id`)');
        self::addIndex($db, 'everflow_postbacks', 'idx_ef_pb_order', 'INDEX `idx_ef_pb_order` (`order_id`)');
        self::addIndex($db, 'everflow_postbacks', 'idx_ef_pb_tid', 'INDEX `idx_ef_pb_tid` (`transaction_id`)');
        self::addIndex($db, 'everflow_postbacks', 'idx_ef_pb_status', 'INDEX `idx_ef_pb_status` (`status`)');
        if ($migrated) {
            self::backfillEverflow($db);
        }
    }

    private static function addColumn(Database $db, string $table, string $column, string $ddl): bool
    {
        try {
            if (!$db->tableExists($table) || $db->columnExists($table, $column)) {
                return false;
            }
            $db->pdo()->exec('ALTER TABLE `' . $table . '` ADD COLUMN ' . $ddl);
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private static function addIndex(Database $db, string $table, string $index, string $ddl): void
    {
        try {
            if (!$db->tableExists($table) || $db->indexExists($table, $index)) {
                return;
            }
            $db->pdo()->exec('ALTER TABLE `' . $table . '` ADD ' . $ddl);
        } catch (Throwable) {
        }
    }

    /**
     * @return array{dt:string,len:int}|null
     */
    private static function columnMeta(Database $db, string $table, string $column): ?array
    {
        try {
            $row = $db->fetch(
                'SELECT DATA_TYPE AS dt, CHARACTER_MAXIMUM_LENGTH AS len
                 FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c',
                ['t' => $table, 'c' => $column]
            );
            if (!$row) {
                return null;
            }
            return [
                'dt' => strtolower((string) ($row['dt'] ?? $row['DT'] ?? '')),
                'len' => (int) ($row['len'] ?? $row['LEN'] ?? 0),
            ];
        } catch (Throwable) {
            return null;
        }
    }

    private static function ensureVarchar(Database $db, string $table, string $column, int $length): void
    {
        $meta = self::columnMeta($db, $table, $column);
        if ($meta === null || $meta['len'] >= $length) {
            return;
        }
        try {
            $db->pdo()->exec(
                'ALTER TABLE `' . $table . '` MODIFY `' . $column . '` VARCHAR(' . $length . ') NULL'
            );
        } catch (Throwable) {
        }
    }

    private static function ensureBigintId(Database $db, string $table): void
    {
        $meta = self::columnMeta($db, $table, 'id');
        if ($meta === null || $meta['dt'] === 'bigint') {
            return;
        }
        try {
            $db->pdo()->exec(
                'ALTER TABLE `' . $table . '` MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT'
            );
        } catch (Throwable) {
        }
    }

    private static function ensureIntColumn(Database $db, string $table, string $column): void
    {
        $meta = self::columnMeta($db, $table, $column);
        if ($meta === null || $meta['dt'] === 'int' || $meta['dt'] === 'bigint') {
            return;
        }
        try {
            $db->pdo()->exec('ALTER TABLE `' . $table . '` MODIFY `' . $column . '` INT NULL');
        } catch (Throwable) {
        }
    }

    private static function backfillEverflow(Database $db): void
    {
        try {
            if (!$db->tableExists('everflow_postbacks')) {
                return;
            }
            if ($db->columnExists('everflow_postbacks', 'transaction_id') && $db->columnExists('everflow_postbacks', 'everflow_transaction_id')) {
                $db->pdo()->exec(
                    'UPDATE everflow_postbacks
                     SET transaction_id = everflow_transaction_id
                     WHERE (transaction_id IS NULL OR transaction_id = "")
                       AND everflow_transaction_id IS NOT NULL AND everflow_transaction_id <> ""'
                );
            }
            if ($db->columnExists('everflow_postbacks', 'postback_url') && $db->columnExists('everflow_postbacks', 'url')) {
                $db->pdo()->exec(
                    'UPDATE everflow_postbacks
                     SET postback_url = url
                     WHERE (postback_url IS NULL OR postback_url = "")
                       AND url IS NOT NULL AND url <> ""'
                );
            }
            if ($db->columnExists('everflow_postbacks', 'response_body') && $db->columnExists('everflow_postbacks', 'response')) {
                $db->pdo()->exec(
                    'UPDATE everflow_postbacks
                     SET response_body = response
                     WHERE (response_body IS NULL OR response_body = "")
                       AND response IS NOT NULL AND response <> ""'
                );
            }
            if ($db->columnExists('everflow_postbacks', 'status') && $db->columnExists('everflow_postbacks', 'http_status')) {
                $db->pdo()->exec(
                    "UPDATE everflow_postbacks
                     SET status = 'success'
                     WHERE status = 'pending' AND http_status >= 200 AND http_status < 400"
                );
                $db->pdo()->exec(
                    "UPDATE everflow_postbacks
                     SET status = 'failed'
                     WHERE status = 'pending' AND http_status IS NOT NULL AND (http_status < 200 OR http_status >= 400)"
                );
            }
        } catch (Throwable) {
        }

        try {
            if (!$db->tableExists('everflow_clicks')) {
                return;
            }
            if ($db->columnExists('everflow_clicks', 'ip_address') && $db->columnExists('everflow_clicks', 'ip')) {
                $db->pdo()->exec(
                    'UPDATE everflow_clicks
                     SET ip_address = ip
                     WHERE (ip_address IS NULL OR ip_address = "")
                       AND ip IS NOT NULL AND ip <> ""'
                );
            }
            if ($db->columnExists('everflow_clicks', 'affid') && $db->columnExists('everflow_clicks', 'affiliate_id')) {
                $db->pdo()->exec(
                    'UPDATE everflow_clicks
                     SET affid = affiliate_id
                     WHERE (affid IS NULL OR affid = "")
                       AND affiliate_id IS NOT NULL AND affiliate_id <> ""'
                );
            }
            if ($db->columnExists('everflow_clicks', 'oid') && $db->columnExists('everflow_clicks', 'offer_id')) {
                $db->pdo()->exec(
                    'UPDATE everflow_clicks
                     SET oid = offer_id
                     WHERE (oid IS NULL OR oid = "")
                       AND offer_id IS NOT NULL AND offer_id <> ""'
                );
            }
        } catch (Throwable) {
        }
    }
}
