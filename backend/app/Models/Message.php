<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['conversation_id', 'sender_id', 'body', 'original_body', 'was_masked', 'flagged', 'flag_reason'];

    protected $hidden = ['original_body'];

    protected function casts(): array
    {
        return ['original_body' => 'encrypted', 'was_masked' => 'boolean', 'flagged' => 'boolean'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id')->withTrashed();
    }
}
