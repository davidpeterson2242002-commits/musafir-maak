<?php

namespace App\Models\Concerns;

use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;

/**
 * Records create/update/delete of admin-managed models in audit_logs.
 * Only logs when the change is made by an authenticated admin (or explicitly
 * via AuditLogger), so system jobs and app users don't flood the log.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn (Model $model) => app(AuditLogger::class)->modelEvent('created', $model));
        static::updated(fn (Model $model) => app(AuditLogger::class)->modelEvent('updated', $model));
        static::deleted(fn (Model $model) => app(AuditLogger::class)->modelEvent('deleted', $model));
    }

    /** Attributes never written to the audit log. */
    public function auditExclude(): array
    {
        return ['password', 'remember_token', 'updated_at', 'created_at'];
    }
}
