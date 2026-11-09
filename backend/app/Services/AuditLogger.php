<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    /** Called by the Auditable trait. Logs only changes made by an authenticated admin. */
    public function modelEvent(string $action, Model $model): void
    {
        $admin = $this->currentAdmin();
        if ($admin === null) {
            return;
        }

        $exclude = method_exists($model, 'auditExclude') ? $model->auditExclude() : [];

        $changes = match ($action) {
            // Values go through the model's casts (json, enums, dates) so the log is readable.
            'created' => collect(array_keys($model->getAttributes()))
                ->diff($exclude)
                ->mapWithKeys(fn ($k) => [$k => ['old' => null, 'new' => $model->getAttribute($k)]])->all(),
            'updated' => collect(array_keys($model->getChanges()))
                ->diff($exclude)
                ->mapWithKeys(fn ($k) => [$k => ['old' => $model->getOriginal($k), 'new' => $model->getAttribute($k)]])->all(),
            'deleted' => collect(array_keys($model->getAttributes()))
                ->diff($exclude)
                ->mapWithKeys(fn ($k) => [$k => ['old' => $model->getAttribute($k), 'new' => null]])->all(),
        };

        if ($action === 'updated' && $changes === []) {
            return;
        }

        $this->log($action, $model, $changes, $admin);
    }

    /** Log a custom admin action (e.g. "user.suspended", "balance.adjusted"). */
    public function log(string $action, ?Model $subject = null, array $changes = [], ?Admin $admin = null): AuditLog
    {
        $admin ??= $this->currentAdmin();

        return AuditLog::create([
            'admin_id' => $admin?->id,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'changes' => $this->normalize($changes) ?: null,
            'ip_address' => request()?->ip(),
        ]);
    }

    private function currentAdmin(): ?Admin
    {
        $user = Auth::guard('admin')->user();

        return $user instanceof Admin ? $user : null;
    }

    /** Make enum/date values JSON friendly. */
    private function normalize(array $changes): array
    {
        array_walk_recursive($changes, function (&$value) {
            if ($value instanceof \BackedEnum) {
                $value = $value->value;
            } elseif ($value instanceof \DateTimeInterface) {
                $value = $value->format(DATE_ATOM);
            }
        });

        return $changes;
    }
}
