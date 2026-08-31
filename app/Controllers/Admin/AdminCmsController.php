<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Services\AuditService;
use App\Services\CmsService;
use App\Services\UploadService;
use Throwable;

final class AdminCmsController extends Controller
{
    public function index(): string
    {
        $cms = new CmsService($this->db);
        $settings = $cms->all();

        return $this->view('admin/cms/index', [
            'title' => 'CMS & Asset Management Hub — Orion Bets',
            'cms' => $settings,
        ], 'admin');
    }

    public function update(): never
    {
        $cms = new CmsService($this->db);
        $uploadService = new UploadService();
        $tab = (string) $this->request->post('tab', 'hero');

        $fields = [
            // Hero & Banner Controls
            'hero_headline' => 'text',
            'hero_subheadline' => 'textarea',
            'hero_cta_text' => 'text',
            'hero_cta_url' => 'text',
            'hero_banner_url' => 'image_url',
            'kickoff_countdown_at' => 'text',
            'kickoff_kicker' => 'text',
            'kickoff_title_pre' => 'text',
            'kickoff_title_live' => 'text',
            'kickoff_sub_pre' => 'text',
            'kickoff_sub_live' => 'text',
            'kickoff_cta_pre' => 'text',
            'kickoff_cta_live' => 'text',

            // Branding & Assets
            'site_logo_url' => 'image_url',
            'site_favicon_url' => 'image_url',
            'promo_banner_text' => 'text',
            'promo_banner_url' => 'text',
            'promo_banner_enabled' => 'text',

            // About Us & Marketing Blocks
            'about_hero_headline' => 'text',
            'story_1_eyebrow' => 'text',
            'story_1_title' => 'text',
            'story_1_body' => 'textarea',
            'story_1_scrawl' => 'text',
            'story_2_eyebrow' => 'text',
            'story_2_title' => 'text',
            'story_2_body' => 'textarea',
            'story_3_eyebrow' => 'text',
            'story_3_title' => 'text',
            'story_3_body' => 'textarea',
            'valprop_title' => 'text',
            'valprop_subtitle' => 'text',
            'valprop_body' => 'textarea',
            'footer_disclaimer' => 'textarea',
            'footer_text' => 'text',
        ];

        $updatedKeys = [];
        foreach ($fields as $key => $type) {
            if ($this->request->has($key)) {
                $val = trim((string) $this->request->post($key, ''));
                $cms->put($key, $val, $type);
                $updatedKeys[] = $key;
            }
        }

        // Handle Image File Uploads
        if ($tab === 'hero') {
            if ($this->request->post('remove_hero_banner') === '1') {
                $old = $cms->get('hero_banner_url');
                $uploadService->deleteFile($old);
                $cms->put('hero_banner_url', '', 'image_url');
                $updatedKeys[] = 'hero_banner_url';
            } elseif ($this->request->hasFile('hero_banner_file')) {
                try {
                    $uploadedPath = $uploadService->uploadImage($this->request->file('hero_banner_file'), 'banners');
                    $old = $cms->get('hero_banner_url');
                    $uploadService->deleteFile($old);
                    $cms->put('hero_banner_url', $uploadedPath, 'image_url');
                    $updatedKeys[] = 'hero_banner_url';
                } catch (Throwable $e) {
                    $this->flash('error', 'Hero banner upload failed: ' . $e->getMessage());
                    $this->redirect('/admin/cms#tab-hero');
                }
            }
        }

        if ($tab === 'branding') {
            // Site Logo
            if ($this->request->post('remove_site_logo') === '1') {
                $old = $cms->get('site_logo_url');
                $uploadService->deleteFile($old);
                $cms->put('site_logo_url', '', 'image_url');
                $updatedKeys[] = 'site_logo_url';
            } elseif ($this->request->hasFile('site_logo_file')) {
                try {
                    $uploadedPath = $uploadService->uploadImage($this->request->file('site_logo_file'), 'branding');
                    $old = $cms->get('site_logo_url');
                    $uploadService->deleteFile($old);
                    $cms->put('site_logo_url', $uploadedPath, 'image_url');
                    $updatedKeys[] = 'site_logo_url';
                } catch (Throwable $e) {
                    $this->flash('error', 'Logo upload failed: ' . $e->getMessage());
                    $this->redirect('/admin/cms#tab-branding');
                }
            }

            // Site Favicon
            if ($this->request->post('remove_site_favicon') === '1') {
                $old = $cms->get('site_favicon_url');
                $uploadService->deleteFile($old);
                $cms->put('site_favicon_url', '', 'image_url');
                $updatedKeys[] = 'site_favicon_url';
            } elseif ($this->request->hasFile('site_favicon_file')) {
                try {
                    $uploadedPath = $uploadService->uploadImage($this->request->file('site_favicon_file'), 'branding');
                    $old = $cms->get('site_favicon_url');
                    $uploadService->deleteFile($old);
                    $cms->put('site_favicon_url', $uploadedPath, 'image_url');
                    $updatedKeys[] = 'site_favicon_url';
                } catch (Throwable $e) {
                    $this->flash('error', 'Favicon upload failed: ' . $e->getMessage());
                    $this->redirect('/admin/cms#tab-branding');
                }
            }

            // Handle promo_banner_enabled toggle specifically if form is branding
            if (!$this->request->has('promo_banner_enabled')) {
                $cms->put('promo_banner_enabled', '0', 'text');
            }
        }

        (new AuditService($this->db))->log($this->auth->id(), 'cms_settings_updated', 'cms', $tab, $this->request, [
            'tab' => $tab,
            'updated_keys' => $updatedKeys,
        ]);

        $this->flash('success', 'CMS settings & assets saved successfully.');
        $this->redirect('/admin/cms#' . urlencode($tab));
    }
}
