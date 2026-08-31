<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Repositories\ContactRepository;
use App\Repositories\FaqRepository;
use App\Services\SettingsService;

final class AdminContentController extends Controller
{
    public function faqs(): string
    {
        return $this->view('admin/faqs/index', [
            'title' => 'FAQs',
            'faqs' => (new FaqRepository($this->db))->all(),
        ], 'admin');
    }

    public function storeFaq(): never
    {
        (new FaqRepository($this->db))->create([
            'category' => $this->request->post('category'),
            'question' => $this->request->post('question'),
            'answer' => $this->request->post('answer'),
            'sort_order' => (int) $this->request->post('sort_order', 0),
            'is_published' => 1,
        ]);
        $this->flash('success', 'FAQ added.');
        $this->redirect('/admin/faqs');
    }

    public function deleteFaq(string $id): never
    {
        (new FaqRepository($this->db))->delete((int) $id);
        $this->flash('success', 'FAQ removed.');
        $this->redirect('/admin/faqs');
    }

    public function messages(): string
    {
        return $this->view('admin/contact/index', [
            'title' => 'Inbox',
            'messages' => (new ContactRepository($this->db))->all(),
        ], 'admin');
    }

    public function updateMessage(string $id): never
    {
        (new ContactRepository($this->db))->updateStatus(
            (int) $id,
            (string) $this->request->post('status', 'read'),
            (string) $this->request->post('admin_notes')
        );
        $this->flash('success', 'Message updated.');
        $this->redirect('/admin/messages');
    }

    public function settings(): string
    {
        $legal = $this->db->fetchAll('SELECT * FROM legal_pages ORDER BY FIELD(slug, \'cookies\', \'privacy\', \'terms\', \'disclaimer\'), slug');
        $slugs = array_column($legal, 'slug');
        if (!in_array('cookies', $slugs, true)) {
            array_unshift($legal, [
                'slug' => 'cookies',
                'title' => 'Cookie Policy',
                'content' => '',
            ]);
        }

        return $this->view('admin/settings/index', [
            'title' => 'Site settings',
            'settings' => settings(),
            'legal' => $legal,
        ], 'admin');
    }

    public function updateSettings(): never
    {
        $service = new SettingsService($this->db);
        $form = (string) $this->request->post('form', 'site');
        if ($form === 'cookie_consent') {
            foreach (array_keys(cookie_consent_defaults()) as $key) {
                $service->put($key, (string) $this->request->post($key, ''));
            }
            $this->flash('success', 'Cookie consent copy saved.');
            $this->redirect('/admin/settings#cookie-consent');
        }

        $keys = [
            'site_name', 'tagline', 'primary_color', 'contact_email', 'timezone',
            'social_x', 'social_instagram', 'social_youtube', 'social_discord', 'countdown_label',
            'countdown_at', 'footer_text', 'disclaimer', 'seo_title', 'seo_description',
            'dark_mode_default',
        ];
        foreach ($keys as $key) {
            $service->put($key, (string) $this->request->post($key, ''));
        }
        $this->flash('success', 'Settings saved.');
        $this->redirect('/admin/settings');
    }

    public function updateLegal(): never
    {
        $service = new SettingsService($this->db);
        $service->saveLegal(
            $slug = (string) $this->request->post('slug'),
            (string) $this->request->post('title'),
            (string) $this->request->post('content')
        );
        $this->flash('success', $slug === 'cookies' ? 'Cookie Policy updated.' : 'Legal page updated.');
        $this->redirect('/admin/settings' . ($slug === 'cookies' ? '#cookie-policy' : '#legal-pages'));
    }
}
