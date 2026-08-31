<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Validator;
use App\Repositories\FaqRepository;
use App\Repositories\ContactRepository;
use App\Services\AuditService;
use App\Services\EverflowService;

final class ContentController extends Controller
{
    public function faq(): string
    {
        $q = trim((string) $this->request->query('q', ''));
        $faqs = (new FaqRepository($this->db))->published();
        if ($q !== '') {
            $faqs = array_values(array_filter($faqs, static function ($row) use ($q) {
                return stripos($row['question'] . ' ' . $row['answer'], $q) !== false;
            }));
        }

        $grouped = [];
        foreach ($faqs as $faq) {
            $grouped[$faq['category']][] = $faq;
        }

        return $this->view('faq/index', [
            'title' => 'FAQ — Orion Bets',
            'metaDescription' => 'Answers about Orion Bets accounts, subscriptions, analysis notes, and informational disclaimers.',
            'grouped' => $grouped,
            'q' => $q,
        ]);
    }

    public function contact(): string
    {
        return $this->view('contact/index', [
            'title' => 'Contact the desk — Orion Bets',
            'metaDescription' => 'Write to the Orion Bets research desk. We do not take wagers or process gambling payments.',
        ]);
    }

    public function submitContact(): never
    {
        $v = Validator::make($this->request->all(), [
            'name' => 'required|max:120',
            'email' => 'required|email',
            'subject' => 'required|max:190',
            'message' => 'required|min:10',
        ]);

        if ($v->fails()) {
            $this->errors($v->errors());
            $this->oldInput($this->request->all());
            $this->redirect('/contact');
        }

        $id = (new ContactRepository($this->db))->create([
            'name' => trim((string) $this->request->post('name')),
            'email' => strtolower(trim((string) $this->request->post('email'))),
            'subject' => trim((string) $this->request->post('subject')),
            'message' => trim((string) $this->request->post('message')),
            'status' => 'new',
        ]);

        try {
            EverflowService::make($this->db)->trackFunnel('contact', $this->request, [
                'email' => strtolower(trim((string) $this->request->post('email'))),
                'order_id' => 'contact-' . $id,
                'event_type' => 'contact',
                'amount' => 0,
            ]);
        } catch (\Throwable) {
        }

        (new AuditService($this->db))->log($this->auth->id(), 'contact_submitted', 'contact_message', (string) $id, $this->request);
        $this->flash('success', 'Message received. The desk will reply by email.');
        $this->redirect('/contact');
    }
}
