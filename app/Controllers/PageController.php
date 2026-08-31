<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class PageController extends Controller
{
    public function howItWorks(): string
    {
        return $this->view('how-it-works/index', [
            'title' => 'How the desk works — Orion Bets',
            'metaDescription' => 'The system runs overnight. In the morning our desk writes it up in plain English — the game, and the odds as we see them. Sent before kickoff.',
        ]);
    }

    public function about(): string
    {
        return $this->view('about/index', [
            'title' => 'About Us — Orion Bets',
            'metaDescription' => 'Victory is found in the margins. Built from a bettor\'s edge in Las Vegas. Daily picks, public record, no excuses.',
        ]);
    }

    public function affiliates(): string
    {
        $signupUrl = cms('everflow_signup_url')
            ?: (string) env_get('EVERFLOW_SIGNUP_URL', '')
            ?: 'https://orionbets.everflowclient.io/affiliate/signup';

        $portalUrl = cms('everflow_portal_url')
            ?: (string) env_get('EVERFLOW_PORTAL_URL', '')
            ?: 'https://orionbets.everflowclient.io/';

        $supportEmail = cms('affiliate_support_email')
            ?: (string) env_get('AFFILIATE_SUPPORT_EMAIL', '')
            ?: 'support@orionbets.co';

        $actionNetworkUrl = cms('affiliate_action_network_url')
            ?: 'https://app.actionnetwork.com/4zu6/oharfju5';

        return $this->view('affiliates/index', [
            'title' => 'Affiliate Program — Orion Bets',
            'metaDescription' => 'Monetize your sports betting traffic. Promote a picks product with a 59% verified win rate across every sport and 68% in the NFL. 20% recurring commission.',
            'signupUrl' => $signupUrl,
            'portalUrl' => $portalUrl,
            'supportEmail' => $supportEmail,
            'actionNetworkUrl' => $actionNetworkUrl,
            'heroEyebrow' => cms('affiliate_hero_eyebrow', 'OrionBets Affiliate Program'),
            'heroTitle' => cms('affiliate_hero_title', 'Monetize your sports betting traffic.'),
            'heroDescription' => cms('affiliate_hero_description', 'Promote a picks product with a 59% verified win rate across every sport — and 68% in the NFL. Both publicly tracked on Action Network, where your audience can check them without taking your word for it. Competitive commissions with no earnings cap — 20% of every monthly subscription for the first four months.'),
            'commissionHeadline' => cms('affiliate_commission_headline', '20'),
            'commissionSub' => cms('affiliate_commission_sub', 'Recurring on monthly plans · up to 4 months'),
            'rate1Title' => cms('affiliate_rate_1_title', '20%'),
            'rate1Sub' => cms('affiliate_rate_1_sub', 'Of every monthly subscription first four months'),
            'rate2Title' => cms('affiliate_rate_2_title', '$49.99'),
            'rate2Sub' => cms('affiliate_rate_2_sub', 'What a subscription costs the only product'),
            'rate3Title' => cms('affiliate_rate_3_title', 'No cap'),
            'rate3Sub' => cms('affiliate_rate_3_sub', 'On what you can earn however many you refer'),
            'whyTitle' => cms('affiliate_why_title', 'Why partner with OrionBets'),
            'bandTitle' => cms('affiliate_band_title', 'Get in the game.'),
            'bandSub' => cms('affiliate_band_sub', 'No earnings cap · 68% NFL, 59% across every sport · signup in minutes'),
        ]);
    }

    public function cookies(): string
    {
        return $this->legal('cookies');
    }

    public function privacy(): string
    {
        return $this->legal('privacy');
    }

    public function terms(): string
    {
        return $this->legal('terms');
    }

    public function disclaimer(): string
    {
        return $this->legal('disclaimer');
    }

    private function legal(string $slug): string
    {
        $page = $this->db->fetch('SELECT * FROM legal_pages WHERE slug = :slug', ['slug' => $slug]);
        if (!$page) {
            $page = ['title' => ucfirst($slug), 'content' => settings('disclaimer') ?: 'Informational content only.'];
        }

        return $this->view('legal/show', [
            'title' => $page['title'] . ' — Orion Bets',
            'metaDescription' => excerpt($page['content'], 150),
            'page' => $page,
        ]);
    }
}
