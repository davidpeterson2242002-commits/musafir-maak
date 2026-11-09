<?php

namespace App\Filament\Resources\Ledger\Pages;

use App\Filament\Resources\Ledger\LedgerResource;
use App\Models\LedgerEntry;
use App\Services\AuditLogger;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListLedger extends ListRecords
{
    protected static string $resource = LedgerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('تصدير CSV')->icon(Heroicon::OutlinedArrowDownTray)->color('gray')
                ->action(fn () => $this->exportCsv()),
        ];
    }

    /** Exports the rows matching the current filters/search. */
    public function exportCsv(): StreamedResponse
    {
        $query = $this->getFilteredSortedTableQuery()->with(['driverProfile.user', 'admin']);
        app(AuditLogger::class)->log('ledger.exported', null, ['rows' => (clone $query)->count()]);

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows Arabic correctly
            fputcsv($out, ['id', 'created_at', 'driver', 'phone', 'type', 'bucket', 'amount', 'balance_after', 'wallet_after', 'reference', 'note', 'admin']);

            $query->chunk(500, function ($entries) use ($out) {
                foreach ($entries as $e) {
                    /** @var LedgerEntry $e */
                    fputcsv($out, [
                        $e->id, local_time($e->created_at)->format('Y-m-d H:i:s'), $e->driverProfile->user?->name, $e->driverProfile->user?->phone,
                        $e->type->value, $e->bucket, $e->amount, $e->balance_after, $e->wallet_after,
                        $e->reference_type ? "{$e->reference_type}#{$e->reference_id}" : '', $e->note, $e->admin?->name,
                    ]);
                }
            });

            fclose($out);
        }, 'ledger-'.now(config('app.display_timezone'))->format('Y-m-d-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
