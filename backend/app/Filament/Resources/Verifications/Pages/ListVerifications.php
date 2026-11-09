<?php

namespace App\Filament\Resources\Verifications\Pages;

use App\Enums\ReviewStatus;
use App\Filament\Resources\Verifications\VerificationResource;
use App\Models\VerificationReview;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListVerifications extends ListRecords
{
    protected static string $resource = VerificationResource::class;

    public function getTabs(): array
    {
        $pending = fn (?string $type = null) => VerificationReview::pending()
            ->when($type, fn ($q) => $q->where('subject_type', $type))->count() ?: null;

        return [
            'pending' => Tab::make('المعلّقة')->badge($pending())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ReviewStatus::Pending)),
            'passenger_ids' => Tab::make('هويات الركاب')->badge($pending('user'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ReviewStatus::Pending)->where('subject_type', 'user')),
            'drivers' => Tab::make('السائقون')->badge($pending('driver_profile'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ReviewStatus::Pending)->where('subject_type', 'driver_profile')),
            'vehicles' => Tab::make('العربيات')->badge($pending('vehicle'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ReviewStatus::Pending)->where('subject_type', 'vehicle')),
            'history' => Tab::make('السجل')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', '!=', ReviewStatus::Pending)->reorder()->orderByDesc('reviewed_at')),
        ];
    }
}
