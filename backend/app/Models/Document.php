<?php

namespace App\Models;

use App\Enums\DocumentKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class Document extends Model
{
    protected $fillable = ['owner_type', 'owner_id', 'kind', 'disk', 'path', 'mime', 'size'];

    protected function casts(): array
    {
        return ['kind' => DocumentKind::class];
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /** Short-lived signed URL; documents are never publicly accessible. */
    public function temporaryUrl(?int $minutes = null): string
    {
        return Storage::disk($this->disk)->temporaryUrl(
            $this->path,
            now()->addMinutes($minutes ?? config('filesystems.signed_url_minutes')),
        );
    }

    protected static function booted(): void
    {
        static::deleted(fn (Document $doc) => Storage::disk($doc->disk)->delete($doc->path));
    }
}
