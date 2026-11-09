<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnforcementAction extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'action', 'until', 'reason', 'report_id', 'admin_id'];

    protected function casts(): array
    {
        return ['until' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function actionLabel(): string
    {
        return match ($this->action) {
            'warn' => 'تنبيه',
            'suspend' => 'إيقاف مؤقت',
            'ban' => 'حظر',
            'reactivate' => 'إعادة تفعيل',
            default => $this->action,
        };
    }
}
