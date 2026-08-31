<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Services\EverflowService;
use Throwable;

final class EverflowController extends Controller
{
    public function ingest(): never
    {
        $payload = $this->request->json();
        if ($payload === []) {
            $payload = $this->request->all();
        }

        try {
            $tid = EverflowService::make($this->db)->ingest($this->request, $payload);
            $this->json(['ok' => true, 'transaction_id' => $tid]);
        } catch (Throwable) {
            $this->json(['ok' => false], 200);
        }
    }
}
