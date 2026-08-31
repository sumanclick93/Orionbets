<?php

declare(strict_types=1);

use App\Core\Database;
use App\Services\AuditService;
use App\Services\NotificationService;

final class DatabaseSeeder
{
    public function __construct(private Database $db)
    {
    }

    public function run(): void
    {
        \App\Setup\Schema::tryEnsure($this->db);

        if ($this->db->fetch("SELECT id FROM users LIMIT 1")) {
            $this->branding();
            echo "Database already seeded. Orion Bets branding refreshed.\n";
            return;
        }

        $this->roles();
        $this->users();
        $this->settings();
        $this->legal();
        $this->plans();
        $this->sportsTree();
        $this->picks();
        $this->faqs();
        $this->notifications();

        echo "Demo dataset installed. All performance figures are fictional DEMO DATA.\n";
    }

    private function branding(): void
    {
        $this->settings(true);
        $this->legal();
        $this->syncPlans();
        $this->db->query(
            "UPDATE faqs SET question = :question, answer = :answer WHERE question IN ('Is payment live?', 'How do I pay?')",
            [
                'question' => 'How do I pay?',
                'answer' => 'Each paid plan on the site is tied to an Upgrade.Chat checkout link. Click Get Access Now and pay in the on-site window — you are not sent to another website. PayPal and cards are processed by Upgrade.Chat.',
            ]
        );
    }

    private function roles(): void
    {
        $roles = [
            ['Standard user', 'user', 'Registered member'],
            ['Premium user', 'premium_user', 'Active Playbook subscriber'],
            ['Editor', 'editor', 'Research desk editor'],
            ['Admin', 'admin', 'Operations administrator'],
            ['Super admin', 'super_admin', 'Full system access'],
        ];
        foreach ($roles as [$name, $slug, $desc]) {
            $exists = $this->db->fetch('SELECT id FROM roles WHERE slug = :slug', ['slug' => $slug]);
            if ($exists) {
                continue;
            }
            $this->db->insert('roles', ['name' => $name, 'slug' => $slug, 'description' => $desc]);
        }
    }

    private function users(): void
    {
        $now = date('Y-m-d H:i:s');
        $people = [
            ['Avery', 'Quill', 'henry.w@example.net', 'DemoAdmin123!', ['super_admin', 'admin']],
            ['Jordan', 'Hale', 'yuki.t@example.com', 'DemoEditor123!', ['editor']],
            ['Sam', 'Rivera', 'leo.a@example.org', 'DemoPremium123!', ['premium_user']],
            ['Casey', 'Nguyen', 'marco.r@example.org', 'DemoUser123!', ['user']],
        ];

        foreach ($people as [$first, $last, $email, $password, $roles]) {
            $id = $this->db->insert('users', [
                'first_name' => $first,
                'last_name' => $last,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'email_verified_at' => $now,
                'timezone' => 'America/New_York',
                'theme_preference' => 'system',
                'is_active' => 1,
                'age_confirmed_at' => $now,
                'terms_accepted_at' => $now,
                'privacy_accepted_at' => $now,
            ]);

            foreach ($roles as $slug) {
                $role = $this->db->fetch('SELECT id FROM roles WHERE slug = :slug', ['slug' => $slug]);
                $this->db->insert('user_roles', ['user_id' => $id, 'role_id' => $role['id']]);
            }

            foreach (['email' => ['daily_pick', 'pick_result', 'subscription', 'account'], 'in_app' => ['daily_pick', 'pick_result', 'subscription', 'account']] as $channel => $events) {
                foreach ($events as $event) {
                    $this->db->insert('notification_preferences', [
                        'user_id' => $id,
                        'channel' => $channel,
                        'event_type' => $event,
                        'enabled' => 1,
                    ]);
                }
            }
        }
    }

    private function settings(bool $update = false): void
    {
        $items = [
            'site_name' => 'Orion Bets',
            'tagline' => 'Our best bets, sent before kickoff.',
            'primary_color' => '#EAE6DC',
            'contact_email' => 'desk@orionbets.local',
            'timezone' => 'America/Los_Angeles',
            'social_x' => 'https://x.com',
            'social_instagram' => 'https://instagram.com',
            'social_youtube' => 'https://youtube.com',
            'social_discord' => 'https://discord.gg/54dg5xm6P',
            'countdown_label' => 'Season One kicks off in',
            'countdown_at' => '2026-09-09 20:20:00',
            'footer_text' => 'Daily picks. Public record. No excuses. The Playbook is a daily picks subscription.',
            'disclaimer' => '21+. Informational use only, not betting advice. Orion Bets publishes daily picks and a public record. It does not operate a sportsbook, accept wagers, or hold gambling wallets. 1-800-GAMBLER.',
            'seo_title' => 'Orion Bets',
            'seo_description' => 'Our best bets, sent before kickoff. Daily picks. Public record. No excuses. The Playbook is a daily picks subscription.',
            'dark_mode_default' => 'dark',
            'logo_text' => 'Orion Bets',
        ] + cookie_consent_defaults();

        foreach ($items as $key => $value) {
            $existing = $this->db->fetch('SELECT id FROM site_settings WHERE setting_key = :k', ['k' => $key]);
            $type = str_contains($key, 'disclaimer') || str_contains($key, 'footer') || in_array($key, ['cookie_copy', 'cookie_deny'], true) ? 'textarea' : 'text';
            if ($existing) {
                if ($update && !str_starts_with($key, 'cookie_')) {
                    $this->db->update('site_settings', ['setting_value' => $value, 'type' => $type], 'id = :id', ['id' => $existing['id']]);
                }
                continue;
            }
            $this->db->insert('site_settings', [
                'setting_key' => $key,
                'setting_value' => $value,
                'type' => $type,
            ]);
        }
    }

    private function legal(): void
    {
        $disclaimer = <<<TXT
Orion Bets provides sports analytics and informational content only. It does not operate a sportsbook or facilitate wagering. Nothing on this site is betting advice, a solicitation to wager, or a prediction of guaranteed outcomes.

If you choose to use third-party sportsbooks, you do so independently. Orion Bets is not affiliated with any operator and does not process deposits, withdrawals, or wagers.

21+ only. Informational use only, not betting advice. If you or someone you know has a gambling problem, call 1-800-GAMBLER.
TXT;

        $pages = [
            ['privacy', 'Privacy Policy', 'Orion Bets collects account details you provide (name, email, preferences) to operate the subscription service. We do not sell personal data. Session cookies keep you signed in and remember theme preference. Contact desk@orionbets.local to request deletion.'],
            ['terms', 'Terms of Use', "By creating an account you confirm you are 21 or older and agree that Orion Bets is an informational analytics product. Subscription fees pay for research access, not for placing wagers. We may suspend accounts that misuse the service. Demo performance figures are labeled as such and are not live historical results.\n\n" . $disclaimer],
            ['disclaimer', 'Informational Disclaimer', $disclaimer],
            ['cookies', 'Cookie Policy', "Orion Bets does not treat cookies as accepted until you click Allow cookies. The site stays locked if you decline.\n\nWe use essential cookies for authentication, CSRF protection, and the consent record itself. Theme preference is stored in your browser. Analytics cookies are not required for core use. Third-party fonts are loaded from Google Fonts."],
        ];

        foreach ($pages as [$slug, $title, $content]) {
            $existing = $this->db->fetch('SELECT id FROM legal_pages WHERE slug = :slug', ['slug' => $slug]);
            if ($existing) {
                continue;
            }
            $this->db->insert('legal_pages', compact('slug', 'title', 'content'));
        }
    }

    private function planCatalog(): array
    {
        return [
            [
                'name' => 'Free',
                'slug' => 'free',
                'description' => 'Public record and limited daily notes.',
                'price_cents' => 0,
                'billing_interval' => 'month',
                'features' => json_encode(['Public record', 'Limited daily notes', 'Member desk']),
                'is_active' => 1,
                'is_featured' => 0,
                'sort_order' => 1,
                'badge' => null,
            ],
            [
                'name' => 'The Month-to-Month Pass',
                'slug' => 'premium',
                'description' => 'Get month-to-month access to daily picks from a model with a verified 59% win rate, tracked publicly on Action Network. No contracts, no lock-in. Cancel whenever you want to sit on the sideline.',
                'price_cents' => 4999,
                'billing_interval' => 'month',
                'features' => json_encode(['Daily picks — the play, the price, the size', 'Public record', 'Cancel anytime']),
                'is_active' => 1,
                'is_featured' => 0,
                'sort_order' => 2,
                'badge' => null,
            ],
            [
                'name' => 'The Season Pass',
                'slug' => 'founders',
                'description' => 'Get the full season pass. One commitment gets you daily picks and season-long analysis from a model with a verified 59% win rate, tracked publicly on Action Network. Lock in before kickoff and you\'re covered every week through the final whistle, at a better rate than paying month to month.',
                'price_cents' => 26900,
                'billing_interval' => 'season',
                'features' => json_encode(['Full NFL season coverage', 'Daily picks before kickoff', 'Better rate than month to month', 'Founders price never moves']),
                'is_active' => 1,
                'is_featured' => 1,
                'sort_order' => 3,
                'badge' => 'Founders rate',
            ],
            [
                'name' => 'The Annual Pass',
                'slug' => 'annual',
                'description' => 'All sports, all year. Daily picks across every sport we track, 365 days a year, from a model with a verified 59% win rate, tracked publicly on Action Network with our best rate on the ladder. The margins don\'t take an offseason, so neither do we.',
                'price_cents' => 49900,
                'billing_interval' => 'year',
                'features' => json_encode(['All sports, all year', 'Best rate on the ladder', 'Daily picks 365 days', 'Public Action Network record']),
                'is_active' => 1,
                'is_featured' => 0,
                'sort_order' => 4,
                'badge' => 'Best rate',
            ],
        ];
    }

    private function syncPlans(): void
    {
        foreach ($this->planCatalog() as $plan) {
            $existing = $this->db->fetch('SELECT id FROM subscription_plans WHERE slug = :slug', ['slug' => $plan['slug']]);
            $row = $plan + ['currency' => 'USD'];
            if ($existing) {
                unset($row['payment_url']);
                $this->db->update('subscription_plans', $row, 'id = :id', ['id' => $existing['id']]);
            } else {
                $row['payment_url'] = null;
                $this->db->insert('subscription_plans', $row);
            }
        }
    }

    private function plans(): void
    {
        $this->syncPlans();

        $premiumUser = $this->db->fetch("SELECT id FROM users WHERE email = 'leo.a@example.org'");
        $plan = $this->db->fetch("SELECT id FROM subscription_plans WHERE slug = 'premium'");
        $subId = $this->db->insert('subscriptions', [
            'user_id' => $premiumUser['id'],
            'plan_id' => $plan['id'],
            'status' => 'active',
            'starts_at' => date('Y-m-d H:i:s', strtotime('-20 days')),
            'ends_at' => date('Y-m-d H:i:s', strtotime('+10 days')),
            'renews_at' => date('Y-m-d H:i:s', strtotime('+10 days')),
            'provider' => 'demo',
            'provider_subscription_id' => 'demo_sub_1001',
        ]);
        $this->db->insert('subscription_transactions', [
            'subscription_id' => $subId,
            'user_id' => $premiumUser['id'],
            'amount_cents' => 4900,
            'currency' => 'USD',
            'status' => 'completed',
            'provider' => 'demo',
            'provider_transaction_id' => 'demo_tx_1001',
            'description' => 'Demo Premium (no live payment processed)',
        ]);
    }

    private function sportsTree(): void
    {
        $sports = [
            ['NFL', 'nfl', 'football', 1],
            ['NBA', 'nba', 'basketball', 2],
            ['MLB', 'mlb', 'baseball', 3],
            ['NHL', 'nhl', 'hockey', 4],
            ['Soccer', 'soccer', 'soccer', 5],
            ['Tennis', 'tennis', 'tennis', 6],
        ];
        $sportIds = [];
        foreach ($sports as [$name, $slug, $icon, $order]) {
            $sportIds[$slug] = $this->db->insert('sports', compact('name', 'slug', 'icon') + ['is_active' => 1, 'sort_order' => $order]);
        }

        $leagues = [
            ['nfl', 'National Football League', 'nfl', 'USA'],
            ['nba', 'National Basketball Association', 'nba', 'USA'],
            ['mlb', 'Major League Baseball', 'mlb', 'USA'],
            ['nhl', 'National Hockey League', 'nhl', 'USA'],
            ['soccer', 'Premier League', 'epl', 'England'],
            ['soccer', 'La Liga', 'laliga', 'Spain'],
            ['tennis', 'ATP Tour', 'atp', 'International'],
        ];
        $leagueIds = [];
        foreach ($leagues as [$sport, $name, $slug, $country]) {
            $leagueIds[$slug] = $this->db->insert('leagues', [
                'sport_id' => $sportIds[$sport],
                'name' => $name,
                'slug' => $slug,
                'country' => $country,
                'is_active' => 1,
            ]);
        }

        $teams = [
            ['nfl', 'nfl', 'Harbor Hawks', 'harbor-hawks', 'HHK'],
            ['nfl', 'nfl', 'Iron Range', 'iron-range', 'IRN'],
            ['nba', 'nba', 'Metro Lynx', 'metro-lynx', 'LYX'],
            ['nba', 'nba', 'Canyon Suns', 'canyon-suns', 'CYS'],
            ['mlb', 'mlb', 'River Pilots', 'river-pilots', 'RVP'],
            ['mlb', 'mlb', 'Coastal Oaks', 'coastal-oaks', 'COA'],
            ['nhl', 'nhl', 'North Pier', 'north-pier', 'NPR'],
            ['nhl', 'nhl', 'Ash Wolves', 'ash-wolves', 'ASH'],
            ['soccer', 'epl', 'Kingswell FC', 'kingswell', 'KFC'],
            ['soccer', 'epl', 'Eastmere United', 'eastmere', 'EAS'],
            ['tennis', 'atp', 'Elena Voss', 'elena-voss', 'EV'],
            ['tennis', 'atp', 'Marco Pell', 'marco-pell', 'MP'],
        ];
        $teamIds = [];
        foreach ($teams as [$sport, $league, $name, $slug, $abbr]) {
            $teamIds[$slug] = $this->db->insert('teams', [
                'sport_id' => $sportIds[$sport],
                'league_id' => $leagueIds[$league],
                'name' => $name,
                'slug' => $slug,
                'abbreviation' => $abbr,
            ]);
        }

        $events = [
            ['nfl', 'nfl', 'harbor-hawks', 'iron-range', 'Harbor Hawks at Iron Range', '+2 days 19:25'],
            ['nba', 'nba', 'metro-lynx', 'canyon-suns', 'Metro Lynx vs Canyon Suns', '+1 day 21:00'],
            ['mlb', 'mlb', 'river-pilots', 'coastal-oaks', 'River Pilots at Coastal Oaks', '+3 days 19:10'],
            ['nhl', 'nhl', 'north-pier', 'ash-wolves', 'North Pier vs Ash Wolves', '+2 days 20:00'],
            ['soccer', 'epl', 'kingswell', 'eastmere', 'Kingswell FC vs Eastmere United', '+4 days 12:30'],
            ['tennis', 'atp', 'elena-voss', 'marco-pell', 'Voss vs Pell — Exhibition hard court', '+5 days 16:00'],
            ['nfl', 'nfl', 'iron-range', 'harbor-hawks', 'Iron Range at Harbor Hawks', '-12 days 16:25'],
            ['nba', 'nba', 'canyon-suns', 'metro-lynx', 'Canyon Suns at Metro Lynx', '-8 days 20:00'],
            ['mlb', 'mlb', 'coastal-oaks', 'river-pilots', 'Coastal Oaks vs River Pilots', '-20 days 13:05'],
            ['soccer', 'epl', 'eastmere', 'kingswell', 'Eastmere United vs Kingswell FC', '-6 days 10:00'],
        ];

        foreach ($events as [$sport, $league, $home, $away, $name, $when]) {
            $this->db->insert('events', [
                'sport_id' => $sportIds[$sport],
                'league_id' => $leagueIds[$league],
                'home_team_id' => $teamIds[$home],
                'away_team_id' => $teamIds[$away],
                'name' => $name,
                'venue' => 'Demo venue',
                'event_at' => date('Y-m-d H:i:s', strtotime($when)),
                'status' => str_contains($when, '-') ? 'completed' : 'scheduled',
            ]);
        }
    }

    private function picks(): void
    {
        $admin = $this->db->fetch("SELECT id FROM users WHERE email = 'henry.w@example.net'");
        $events = $this->db->fetchAll('SELECT * FROM events ORDER BY id ASC');
        $samples = [
            ['Night desk: Hawks travel well in cold', 'published', 1, 72, 'The overnight model liked Harbor Hawks on the road when Iron Range plays from behind after bye weeks. This is a research note, not a wager ticket.'],
            ['Pace watch: Lynx at home after two road legs', 'published', 0, 64, 'Free note. Canyon Suns have posted quieter first quarters on the second night of a back-to-back in this demo set.'],
            ['Pilots in a pitcher-friendly park', 'scheduled', 1, 58, 'Scheduled for morning publish. Coastal Oaks park factors compress extra-base hits in the demo sample.'],
            ['Pier matchup: special teams and zone starts', 'published', 1, 69, 'North Pier generating shot quality from the weak side in the last six demo games.'],
            ['Kingswell rest advantage', 'published', 1, 61, 'Eastmere on a midweek turnaround. Informational only.'],
            ['Voss serve +1 in first-set holds', 'published', 1, 55, 'Hard-court first-serve percentage is the entire note. Exhibition sample is small.'],
            ['Range home after travel — archived', 'won', 1, 70, 'Completed demo result. Not a live historical claim.'],
            ['Suns transition defense faded late', 'lost', 1, 66, 'Completed demo result. The note missed the late-clock adjustment.'],
            ['Oaks vs left-handed starters', 'push', 0, 52, 'Completed demo. Weather shortened the sample.'],
            ['Eastmere set-piece volume', 'won', 1, 63, 'Completed demo result for the public board.'],
        ];

        foreach ($events as $i => $event) {
            $sample = $samples[$i];
            $status = $sample[1];
            $published = in_array($status, ['published', 'won', 'lost', 'push'], true)
                ? date('Y-m-d H:i:s', strtotime($event['event_at'] . ' -8 hours'))
                : null;

            $id = $this->db->insert('picks', [
                'slug' => $this->slug($sample[0]) . '-' . ($i + 1),
                'title' => $sample[0],
                'sport_id' => $event['sport_id'],
                'league_id' => $event['league_id'],
                'event_id' => $event['id'],
                'analysis' => $sample[4] . "\n\nKey factors were assembled from publicly described demo inputs: rest, travel, pace, and matchup context. Orion Bets does not place wagers and this write-up is informational.\n\nDEMO DATA. Fictional event and research note for product demonstration.",
                'analysis_excerpt' => $sample[4],
                'key_factors' => json_encode(['Rest & travel', 'Pace / tempo', 'Matchup history (demo)', 'Weather / surface']),
                'supporting_stats' => json_encode([
                    ['label' => 'Model lean', 'value' => $sample[3] . '/100'],
                    ['label' => 'Sample size', 'value' => 'Demo only'],
                    ['label' => 'Desk confidence', 'value' => $sample[3] . '%'],
                ]),
                'historical_context' => 'This matchup is part of the Orion Bets demo catalog. Figures are illustrative and must not be read as a live public record.',
                'confidence' => $sample[3],
                'status' => in_array($status, ['won', 'lost', 'push'], true) ? $status : $status,
                'is_premium' => $sample[2],
                'published_at' => $published,
                'scheduled_at' => $status === 'scheduled' ? date('Y-m-d H:i:s', strtotime('+6 hours')) : $published,
                'created_by' => $admin['id'],
                'updated_by' => $admin['id'],
            ]);

            if (in_array($status, ['won', 'lost', 'push'], true)) {
                $units = $status === 'won' ? 1.10 : ($status === 'lost' ? -1.05 : 0);
                $this->db->insert('pick_results', [
                    'pick_id' => $id,
                    'result' => $status,
                    'units' => $units,
                    'closing_notes' => 'Demo settlement only. Not a live sportsbook grade.',
                    'recorded_at' => date('Y-m-d H:i:s', strtotime($event['event_at'] . ' +4 hours')),
                    'recorded_by' => $admin['id'],
                ]);
            }
        }

        $this->db->insert('performance_metrics', [
            'period_type' => 'all',
            'period_start' => date('Y-m-d', strtotime('-90 days')),
            'period_end' => date('Y-m-d'),
            'total_picks' => 4,
            'wins' => 2,
            'losses' => 1,
            'pushes' => 1,
            'win_rate' => 66.67,
            'roi' => 1.67,
            'units' => 0.05,
            'avg_confidence' => 62.75,
            'current_streak' => 1,
            'best_streak' => 1,
            'is_demo' => 1,
        ]);
    }

    private function faqs(): void
    {
        $rows = [
            ['General', 'What is Orion Bets?', 'A daily picks subscription. Every morning you get our best bets — the play, the price, the size. Every result is posted publicly, win or lose. Informational use only, not betting advice.'],
            ['General', 'Do you place bets for me?', 'No. We never accept wagers, deposits, or withdrawals. This is a research subscription, not a sportsbook.'],
            ['Account', 'How do I verify my email?', 'After registration we store a verification token and log a verification message. Open the link from your email log in local development.'],
            ['Subscriptions', 'How do I pay?', 'Each paid plan on the site is tied to an Upgrade.Chat checkout link. Click Get Access Now and pay in the on-site window — you are not sent to another website. PayPal and cards are processed by Upgrade.Chat.'],
            ['The Playbook', 'What lands in my inbox?', 'The game, and the odds as we see them — sent before kickoff. If a call cannot be explained in a sentence, it does not go out.'],
            ['Performance', 'Are the numbers real?', 'The live Orion Bets record is tracked on Action Network. Seeded figures in this demo are labeled DEMO DATA.'],
            ['Notifications', 'Can I turn emails off?', 'Yes. Use Account → Settings to toggle daily pick, result, subscription, and account alerts.'],
            ['Privacy', 'Can I delete my account?', 'Yes from Account Settings. The record is deactivated and personal login is revoked.'],
        ];
        foreach ($rows as $i => [$category, $question, $answer]) {
            $this->db->insert('faqs', [
                'category' => $category,
                'question' => $question,
                'answer' => $answer,
                'sort_order' => $i + 1,
                'is_published' => 1,
            ]);
        }
    }

    private function notifications(): void
    {
        $premium = $this->db->fetch("SELECT id FROM users WHERE email = 'leo.a@example.org'");
        $this->db->insert('notifications', [
            'user_id' => $premium['id'],
            'type' => 'daily_pick',
            'title' => 'Morning Playbook is up',
            'body' => 'Two premium notes published for tonight’s demo slate.',
            'data' => json_encode(['demo' => true]),
        ]);
    }

    private function slug(string $title): string
    {
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $title) ?? $title, '-'));
        return $slug !== '' ? $slug : 'note';
    }
}
