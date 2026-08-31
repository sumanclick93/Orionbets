<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Request;

final class AuditService
{
    public function __construct(private Database $db)
    {
    }

    public function log(?int $userId, string $action, ?string $entity = null, ?string $entityId = null, ?Request $request = null, array $meta = []): void
    {
        if (!$this->db->tableExists('audit_logs')) {
            return;
        }

        $this->db->insert('audit_logs', [
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entity,
            'entity_id' => $entityId,
            'ip' => $request?->ip(),
            'user_agent' => substr((string) $request?->userAgent(), 0, 255) ?: null,
            'metadata' => $meta ? json_encode($meta) : null,
        ]);
    }
}
