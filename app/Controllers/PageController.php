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

        return $this->view('affiliates/index', [
            'title' => 'Affiliate Program — Orion Bets',
            'metaDescription' => 'Monetize your sports betting traffic. Promote a picks product with a 59% verified win rate across every sport and 68% in the NFL. 20% recurring commission.',
            'signupUrl' => $signupUrl,
            'portalUrl' => $portalUrl,
            'supportEmail' => $supportEmail,
            'actionNetworkUrl' => 'https://app.actionnetwork.com/4zu6/oharfju5',
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
