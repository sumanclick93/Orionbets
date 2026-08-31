-- EDGEPLAY schema
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS roles (
    id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    slug VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(80) NOT NULL,
    last_name VARCHAR(80) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    discord_id VARCHAR(64) NULL UNIQUE,
    avatar VARCHAR(255) NULL,
    password_hash VARCHAR(255) NOT NULL,
    email_verified_at DATETIME NULL,
    timezone VARCHAR(64) NOT NULL DEFAULT 'UTC',
    theme_preference VARCHAR(20) NOT NULL DEFAULT 'system',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    is_guest TINYINT(1) NOT NULL DEFAULT 0,
    age_confirmed_at DATETIME NULL,
    terms_accepted_at DATETIME NULL,
    privacy_accepted_at DATETIME NULL,
    remember_token VARCHAR(64) NULL,
    last_login_at DATETIME NULL,
    last_login_ip VARCHAR(45) NULL,
    checkout_cookie VARCHAR(64) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    INDEX idx_users_email (email),
    INDEX idx_users_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_roles (
    user_id INT UNSIGNED NOT NULL,
    role_id TINYINT UNSIGNED NOT NULL,
    PRIMARY KEY (user_id, role_id),
    CONSTRAINT fk_user_roles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_roles_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sports (
    id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    slug VARCHAR(80) NOT NULL UNIQUE,
    icon VARCHAR(40) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order SMALLINT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS leagues (
    id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sport_id SMALLINT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(120) NOT NULL,
    country VARCHAR(80) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_league_slug (sport_id, slug),
    CONSTRAINT fk_leagues_sport FOREIGN KEY (sport_id) REFERENCES sports(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS teams (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sport_id SMALLINT UNSIGNED NOT NULL,
    league_id SMALLINT UNSIGNED NULL,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(120) NOT NULL,
    abbreviation VARCHAR(12) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_teams_sport FOREIGN KEY (sport_id) REFERENCES sports(id) ON DELETE CASCADE,
    CONSTRAINT fk_teams_league FOREIGN KEY (league_id) REFERENCES leagues(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sport_id SMALLINT UNSIGNED NOT NULL,
    league_id SMALLINT UNSIGNED NULL,
    home_team_id INT UNSIGNED NULL,
    away_team_id INT UNSIGNED NULL,
    name VARCHAR(190) NOT NULL,
    venue VARCHAR(190) NULL,
    event_at DATETIME NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'scheduled',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_events_at (event_at),
    CONSTRAINT fk_events_sport FOREIGN KEY (sport_id) REFERENCES sports(id),
    CONSTRAINT fk_events_league FOREIGN KEY (league_id) REFERENCES leagues(id) ON DELETE SET NULL,
    CONSTRAINT fk_events_home FOREIGN KEY (home_team_id) REFERENCES teams(id) ON DELETE SET NULL,
    CONSTRAINT fk_events_away FOREIGN KEY (away_team_id) REFERENCES teams(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS picks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(190) NOT NULL UNIQUE,
    title VARCHAR(190) NOT NULL,
    sport_id SMALLINT UNSIGNED NOT NULL,
    league_id SMALLINT UNSIGNED NULL,
    event_id INT UNSIGNED NULL,
    analysis MEDIUMTEXT NOT NULL,
    analysis_excerpt TEXT NULL,
    key_factors JSON NULL,
    supporting_stats JSON NULL,
    historical_context TEXT NULL,
    confidence TINYINT UNSIGNED NOT NULL DEFAULT 50,
    status VARCHAR(30) NOT NULL DEFAULT 'scheduled',
    is_premium TINYINT(1) NOT NULL DEFAULT 1,
    published_at DATETIME NULL,
    scheduled_at DATETIME NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    INDEX idx_picks_status (status),
    INDEX idx_picks_sport (sport_id),
    INDEX idx_picks_published (published_at),
    CONSTRAINT fk_picks_sport FOREIGN KEY (sport_id) REFERENCES sports(id),
    CONSTRAINT fk_picks_league FOREIGN KEY (league_id) REFERENCES leagues(id) ON DELETE SET NULL,
    CONSTRAINT fk_picks_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE SET NULL,
    CONSTRAINT fk_picks_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pick_results (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pick_id INT UNSIGNED NOT NULL UNIQUE,
    result VARCHAR(20) NOT NULL,
    units DECIMAL(8,2) NOT NULL DEFAULT 0,
    closing_notes TEXT NULL,
    recorded_at DATETIME NOT NULL,
    recorded_by INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_results_pick FOREIGN KEY (pick_id) REFERENCES picks(id) ON DELETE CASCADE,
    CONSTRAINT fk_results_user FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS performance_metrics (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    period_type VARCHAR(20) NOT NULL,
    period_start DATE NULL,
    period_end DATE NULL,
    sport_id SMALLINT UNSIGNED NULL,
    league_id SMALLINT UNSIGNED NULL,
    total_picks INT NOT NULL DEFAULT 0,
    wins INT NOT NULL DEFAULT 0,
    losses INT NOT NULL DEFAULT 0,
    pushes INT NOT NULL DEFAULT 0,
    win_rate DECIMAL(6,2) NOT NULL DEFAULT 0,
    roi DECIMAL(8,2) NOT NULL DEFAULT 0,
    units DECIMAL(10,2) NOT NULL DEFAULT 0,
    avg_confidence DECIMAL(5,2) NOT NULL DEFAULT 0,
    current_streak INT NOT NULL DEFAULT 0,
    best_streak INT NOT NULL DEFAULT 0,
    is_demo TINYINT(1) NOT NULL DEFAULT 1,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subscription_plans (
    id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    slug VARCHAR(80) NOT NULL UNIQUE,
    description TEXT NULL,
    price_cents INT UNSIGNED NOT NULL DEFAULT 0,
    currency VARCHAR(8) NOT NULL DEFAULT 'USD',
    billing_interval VARCHAR(20) NOT NULL DEFAULT 'month',
    features JSON NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    sort_order SMALLINT NOT NULL DEFAULT 0,
    badge VARCHAR(40) NULL,
    payment_url VARCHAR(500) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subscriptions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    plan_id SMALLINT UNSIGNED NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NULL,
    renews_at DATETIME NULL,
    cancelled_at DATETIME NULL,
    cancel_reason TEXT NULL,
    provider VARCHAR(40) NOT NULL DEFAULT 'demo',
    provider_subscription_id VARCHAR(120) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_subs_user (user_id),
    INDEX idx_subs_status (status),
    CONSTRAINT fk_subs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_subs_plan FOREIGN KEY (plan_id) REFERENCES subscription_plans(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subscription_transactions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subscription_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    amount_cents INT NOT NULL DEFAULT 0,
    currency VARCHAR(8) NOT NULL DEFAULT 'USD',
    status VARCHAR(30) NOT NULL DEFAULT 'completed',
    provider VARCHAR(40) NOT NULL DEFAULT 'demo',
    provider_transaction_id VARCHAR(120) NULL,
    description VARCHAR(255) NULL,
    payload JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tx_sub FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE CASCADE,
    CONSTRAINT fk_tx_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS checkout_sessions (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS webhook_events (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    type VARCHAR(60) NOT NULL,
    title VARCHAR(190) NOT NULL,
    body TEXT NULL,
    data JSON NULL,
    read_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notif_user (user_id, read_at),
    CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_preferences (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    channel VARCHAR(20) NOT NULL,
    event_type VARCHAR(60) NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_pref (user_id, channel, event_type),
    CONSTRAINT fk_pref_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS faqs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category VARCHAR(60) NOT NULL,
    question VARCHAR(255) NOT NULL,
    answer MEDIUMTEXT NOT NULL,
    sort_order SMALLINT NOT NULL DEFAULT 0,
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contact_messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL,
    subject VARCHAR(190) NOT NULL,
    message TEXT NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'new',
    admin_notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_settings (
    id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(80) NOT NULL UNIQUE,
    setting_value MEDIUMTEXT NULL,
    type VARCHAR(20) NOT NULL DEFAULT 'text',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS legal_pages (
    id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(50) NOT NULL UNIQUE,
    title VARCHAR(120) NOT NULL,
    content MEDIUMTEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    action VARCHAR(80) NOT NULL,
    entity_type VARCHAR(80) NULL,
    entity_id VARCHAR(80) NULL,
    ip VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    metadata JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_action (action),
    INDEX idx_audit_user (user_id),
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
    email VARCHAR(190) NOT NULL,
    token VARCHAR(64) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pr_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_verifications (
    user_id INT UNSIGNED NOT NULL,
    token VARCHAR(64) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ev_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_la_email (email, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS schema_migrations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    migration VARCHAR(190) NOT NULL UNIQUE,
    ran_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS geo_restrictions (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
