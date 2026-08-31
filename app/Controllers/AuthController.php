<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Mailer;
use App\Core\Validator;
use App\Repositories\UserRepository;
use App\Services\AuditService;
use App\Services\AuthService;
use App\Services\DiscordService;
use App\Services\EverflowService;

final class AuthController extends Controller
{
    private function service(): AuthService
    {
        return new AuthService(
            $this->db,
            new UserRepository($this->db),
            new Mailer(),
            new AuditService($this->db)
        );
    }

    public function showLogin(): string
    {
        $this->rememberIntendedFromQuery();

        return $this->view('auth/login', [
            'title' => 'Sign in — Orion Bets',
            'metaDescription' => 'Sign in to your Orion Bets desk.',
        ], 'auth');
    }

    public function login(): never
    {
        $v = Validator::make($this->request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);
        if ($v->fails()) {
            $this->errors($v->errors());
            $this->oldInput($this->request->all());
            $this->redirect('/login');
        }

        $result = $this->service()->attempt(
            (string) $this->request->post('email'),
            (string) $this->request->post('password'),
            $this->request
        );

        if (!$result['ok']) {
            $this->flash('error', $result['error']);
            $this->oldInput(['email' => $this->request->post('email')]);
            $this->redirect('/login');
        }

        $this->auth->login($result['user'], (bool) $this->request->post('remember'));
        $this->redirectIntended('/dashboard');
    }

    public function showRegister(): string
    {
        $this->rememberIntendedFromQuery();

        $queryEmail = strtolower(trim((string) $this->request->query('email', '')));
        if ($queryEmail === '' || filter_var($queryEmail, FILTER_VALIDATE_EMAIL) === false) {
            $queryEmail = '';
        }

        if ($queryEmail !== '') {
            $this->session->set('claim_email', $queryEmail);
        } else {
            $this->session->forget('claim_email');
        }

        $prefillEmail = $queryEmail !== '' ? $queryEmail : (string) old('email', '');

        return $this->view('auth/register', [
            'title' => 'Create account — Orion Bets',
            'metaDescription' => 'Create an Orion Bets account. 21+ informational research access only.',
            'prefillEmail' => $prefillEmail,
            'emailLocked' => $queryEmail !== '',
        ], 'auth');
    }

    public function register(): never
    {
        $input = $this->request->all();
        $lockedEmail = $this->lockedClaimEmail();
        if ($lockedEmail !== '') {
            $input['email'] = $lockedEmail;
        }

        $v = Validator::make($input, [
            'first_name' => 'required|max:80',
            'last_name' => 'required|max:80',
            'email' => 'required|email',
            'password' => 'required|min:10|confirmed',
            'age' => 'accepted',
            'terms' => 'accepted',
            'privacy' => 'accepted',
        ], [
            'age' => 'the 21+ confirmation',
            'terms' => 'the terms',
            'privacy' => 'the privacy policy',
            'password' => 'Password',
        ]);

        if ($v->fails()) {
            $this->errors($v->errors());
            $this->oldInput($input);
            $this->redirect($this->registerReturnPath($lockedEmail !== '' ? $lockedEmail : (string) ($input['email'] ?? '')));
        }

        $result = $this->service()->register($input, $this->request);
        if (!$result['ok'] || empty($result['user'])) {
            $this->errors(['email' => [(string) ($result['error'] ?? 'An account with this email already exists. Please log in.')]]);
            $this->oldInput($input);
            $this->redirect($this->registerReturnPath((string) ($input['email'] ?? '')));
        }

        $this->session->forget('claim_email');

        $this->auth->login($result['user']);
        if (!empty($result['claimed'])) {
            $this->flash('success', 'Welcome. Your guest checkout is now your account — subscriptions and payment history are intact.');
        } else {
            $this->everflowLead($result['user']);
            $this->flash('success', 'Welcome. Check your inbox (or mail log) to verify email.');
        }
        $this->redirectIntended('/dashboard');
    }

    public function logout(): never
    {
        (new AuditService($this->db))->log($this->auth->id(), 'logout', 'user', (string) $this->auth->id(), $this->request);
        $this->auth->logout();
        $this->redirect('/');
    }

    public function showForgot(): string
    {
        return $this->view('auth/forgot', ['title' => 'Reset password — Orion Bets'], 'auth');
    }

    public function forgot(): never
    {
        $v = Validator::make($this->request->all(), ['email' => 'required|email']);
        if ($v->fails()) {
            $this->errors($v->errors());
            $this->redirect('/forgot-password');
        }
        $this->service()->requestReset((string) $this->request->post('email'), $this->request);
        $this->flash('success', 'If that email exists, a reset link was sent.');
        $this->redirect('/forgot-password');
    }

    public function showReset(): string
    {
        return $this->view('auth/reset', [
            'title' => 'Choose a new password — Orion Bets',
            'token' => (string) $this->request->query('token', ''),
        ], 'auth');
    }

    public function reset(): never
    {
        $v = Validator::make($this->request->all(), [
            'token' => 'required',
            'password' => 'required|min:10|confirmed',
        ]);
        if ($v->fails()) {
            $this->errors($v->errors());
            $this->redirect('/reset-password?token=' . urlencode((string) $this->request->post('token')));
        }

        $ok = $this->service()->resetPassword(
            (string) $this->request->post('token'),
            (string) $this->request->post('password'),
            $this->request
        );

        if (!$ok) {
            $this->flash('error', 'That reset link is invalid or expired.');
            $this->redirect('/forgot-password');
        }

        $this->flash('success', 'Password updated. Sign in.');
        $this->redirect('/login');
    }

    public function verify(): string
    {
        $token = (string) $this->request->query('token', '');
        $ok = $token !== '' && $this->service()->verifyEmail($token);

        return $this->view('auth/verify', [
            'title' => 'Email verification — Orion Bets',
            'ok' => $ok,
        ], 'auth');
    }

    public function discordRedirect(): never
    {
        $this->rememberIntendedFromQuery();
        $this->rememberDiscordPopup(discord_oauth_is_popup());

        if ($this->auth->check()) {
            $user = $this->auth->user();
            if (!empty($user['discord_id'])) {
                $this->finishDiscordAuth(true, $this->consumeIntended('/account/settings'), 'Your Discord account is already connected.');
            }
        }

        $discord = new DiscordService();
        if (!$discord->configured()) {
            $this->finishDiscordAuth(
                false,
                $this->auth->check() ? '/account/settings' : '/login',
                \App\Core\Env::bool('APP_DEBUG')
                    ? 'Discord sign-in is not configured. Set DISCORD_CLIENT_ID and DISCORD_CLIENT_SECRET in .env.'
                    : 'Discord sign-in is not available right now.'
            );
        }

        $state = bin2hex(random_bytes(16));
        $_SESSION['discord_oauth_state'] = $state;
        $this->session->set('discord_oauth_state', $state);
        $this->redirect($discord->getAuthorizationUrl($state));
    }

    public function discordCallback(): never
    {
        $error = (string) $this->request->query('error', '');
        if ($error !== '') {
            $this->finishDiscordAuth(false, $this->auth->check() ? '/account/settings' : '/login', 'Discord connection was cancelled.');
        }

        $state = (string) $this->request->query('state', '');
        $expected = (string) ($_SESSION['discord_oauth_state'] ?? $this->session->get('discord_oauth_state', ''));
        unset($_SESSION['discord_oauth_state']);
        $this->session->forget('discord_oauth_state');

        if ($expected === '' || $state === '' || !hash_equals($expected, $state)) {
            $this->finishDiscordAuth(false, $this->auth->check() ? '/account/settings' : '/login', 'Discord connection could not be verified. Please try again.');
        }

        $code = (string) $this->request->query('code', '');
        if ($code === '') {
            $this->finishDiscordAuth(false, $this->auth->check() ? '/account/settings' : '/login', 'Discord did not return an authorization code.');
        }

        $discord = new DiscordService();
        $token = $discord->getAccessToken($code);
        $access = is_array($token) ? (string) ($token['access_token'] ?? '') : '';
        if ($access === '') {
            $this->finishDiscordAuth(false, $this->auth->check() ? '/account/settings' : '/login', 'Could not complete Discord authentication. Please try again.');
        }

        $profile = $discord->getUserProfile($access);
        if (!$profile || empty($profile['id'])) {
            $this->finishDiscordAuth(false, $this->auth->check() ? '/account/settings' : '/login', 'Could not load your Discord profile.');
        }

        $result = $this->service()->loginWithDiscord($profile, $this->request, $this->auth->user());
        if (!$result['ok'] || empty($result['user'])) {
            $this->finishDiscordAuth(false, $this->auth->check() ? '/account/settings' : '/login', (string) ($result['error'] ?? 'Could not authenticate with Discord.'));
        }

        $this->auth->login($result['user']);
        if (!empty($result['created'])) {
            $this->everflowLead($result['user']);
        }
        $claimed = !empty($result['claimed']);
        $linked = !empty($result['linked']);

        $message = 'Signed in with Discord.';
        if ($linked) {
            $message = 'Discord account connected successfully.';
        } elseif ($claimed) {
            $message = 'Signed in with Discord. Your guest checkout is now this account — subscriptions and payment history are intact.';
        }

        $fallback = $linked ? '/account/settings' : '/dashboard';
        $this->finishDiscordAuth(
            true,
            $this->consumeIntended($fallback),
            $message
        );
    }

    private function lockedClaimEmail(): string
    {
        $candidates = [
            (string) $this->request->query('email', ''),
            (string) $this->session->get('claim_email', ''),
        ];
        foreach ($candidates as $email) {
            $email = strtolower(trim($email));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $email;
            }
        }

        return '';
    }

    private function registerReturnPath(string $email): string
    {
        $email = strtolower(trim($email));
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return '/register';
        }

        return '/register?' . http_build_query(['email' => $email, 'next' => '/dashboard']);
    }

    private function rememberIntendedFromQuery(): void
    {
        $next = intended_path((string) $this->request->query('next', ''));
        if ($next !== null) {
            $this->session->set('intended_url', $next);
        }
    }

    private function rememberDiscordPopup(bool $popup): void
    {
        if ($popup) {
            $_SESSION['discord_oauth_popup'] = '1';
            $this->session->set('discord_oauth_popup', '1');
            return;
        }

        unset($_SESSION['discord_oauth_popup']);
        $this->session->forget('discord_oauth_popup');
    }

    private function consumeIntended(string $fallback): string
    {
        $intended = intended_path((string) $this->session->get('intended_url', ''));
        $this->session->forget('intended_url');
        return $intended ?? $fallback;
    }

    private function finishDiscordAuth(bool $ok, string $redirect, ?string $message = null): never
    {
        $popup = discord_oauth_is_popup();
        $this->rememberDiscordPopup(false);

        if ($message !== null && $message !== '') {
            $this->flash($ok ? 'success' : 'error', $message);
        }

        if ($ok) {
            $redirect = intended_path($redirect) ?? ($this->auth->check() ? '/account/settings' : '/dashboard');
        } else {
            $redirect = $this->auth->check()
                ? '/account/settings'
                : ($redirect === '/register' ? '/register' : '/login');
        }

        if ($popup) {
            $this->response->html($this->view('auth/discord-popup', [
                'ok' => $ok,
                'redirect' => $redirect,
                'message' => $message ?: ($ok ? 'Signed in with Discord.' : 'Discord sign-in could not be completed.'),
                'user' => $ok ? $this->publicDiscordUser($this->auth->user()) : null,
                'csrf' => csrf_token(),
            ], null));
        }

        $this->redirect($redirect);
    }

    private function redirectIntended(string $fallback): never
    {
        $this->redirect($this->consumeIntended($fallback));
    }

    /**
     * @param array<string, mixed>|null $user
     * @return array<string, string>|null
     */
    private function publicDiscordUser(?array $user): ?array
    {
        if (!$user) {
            return null;
        }

        $first = trim((string) ($user['first_name'] ?? ''));
        $last = trim((string) ($user['last_name'] ?? ''));

        return [
            'first_name' => $first,
            'last_name' => $last,
            'name' => trim($first . ' ' . $last),
            'email' => (string) ($user['email'] ?? ''),
            'discord_id' => (string) ($user['discord_id'] ?? ''),
        ];
    }

    /**
     * CPL postback for a brand-new signup. Skips silently when no Everflow click or lead event id.
     *
     * @param array<string, mixed> $user
     */
    private function everflowLead(array $user): void
    {
        try {
            $userId = (int) ($user['id'] ?? 0);
            EverflowService::make($this->db)->trackFunnel('lead', $this->request, [
                'user_id' => $userId > 0 ? $userId : null,
                'email' => (string) ($user['email'] ?? ''),
                'order_id' => 'lead-' . $userId,
                'event_type' => 'signup',
                'amount' => 0,
            ]);
        } catch (\Throwable) {
        }
    }
}
