<?php

namespace App\Services;

use App\Enums\DocumentKind;
use App\Models\Document;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Stores private uploads on the private disk; one current file per owner + kind. */
class DocumentStore
{
    public function put(Model $owner, DocumentKind $kind, UploadedFile $file): Document
    {
        $disk = config('filesystems.private_disk');
        $dir = sprintf('documents/%s/%d', $owner->getMorphClass(), $owner->getKey());
        $name = $kind->value.'-'.Str::random(24).'.'.($file->guessExtension() ?: 'jpg');
        $path = $file->storeAs($dir, $name, ['disk' => $disk]);

        return DB::transaction(function () use ($owner, $kind, $file, $disk, $path) {
            // Deleting the old row also deletes its file (Document::booted).
            $owner->documents()->where('kind', $kind)->get()->each->delete();

            $doc = $owner->documents()->create([
                'kind' => $kind,
                'disk' => $disk,
                'path' => $path,
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);

            $owner->unsetRelation('documents');

            return $doc;
        });
    }
}
