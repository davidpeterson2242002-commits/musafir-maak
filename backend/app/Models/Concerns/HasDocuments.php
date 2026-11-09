<?php

namespace App\Models\Concerns;

use App\Enums\DocumentKind;
use App\Models\Document;
use App\Models\VerificationReview;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/** Owner of private documents and of verification reviews (user, driver profile, vehicle). */
trait HasDocuments
{
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'owner');
    }

    public function document(DocumentKind $kind): ?Document
    {
        return $this->documents->firstWhere('kind', $kind);
    }

    /** @param array<int, DocumentKind> $kinds */
    public function missingDocuments(array $kinds): array
    {
        $have = $this->documents->pluck('kind')->all();

        return array_values(array_filter($kinds, fn (DocumentKind $k) => ! in_array($k, $have, true)));
    }

    public function verificationReviews(): MorphMany
    {
        return $this->morphMany(VerificationReview::class, 'subject');
    }

    public function latestReview(): MorphOne
    {
        return $this->morphOne(VerificationReview::class, 'subject')->latestOfMany();
    }
}
