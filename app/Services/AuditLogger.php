<?php

namespace App\Services;

use App\Models\AuditLog;

/**
 * Helper para registrar eventos en la tabla audit_logs. Acciones típicas:
 * login, logout, create, update, delete, revoke_access, grant_plan,
 * enable_2fa, disable_2fa, change_permissions.
 */
class AuditLogger
{
    public static function log(
        string $action,
        ?string $entityType = null,
        $entityId = null,
        ?string $detail = null,
        ?int $userId = null
    ): ?AuditLog {
        try {
            $userId = $userId ?? auth()->id();

            return AuditLog::create([
                'user_id' => $userId,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId !== null ? (string) $entityId : null,
                'detail' => $detail,
                'ip_address' => request()->ip(),
            ]);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }
}
