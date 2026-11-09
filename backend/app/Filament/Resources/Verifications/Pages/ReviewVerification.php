<?php

namespace App\Filament\Resources\Verifications\Pages;

use App\Filament\Resources\Verifications\VerificationResource;
use App\Models\Admin;
use App\Models\DriverProfile;
use App\Models\VerificationReview;
use App\Services\VerificationService;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/** Review screen: documents side by side + approve / reject / request info. */
class ReviewVerification extends ViewRecord
{
    protected static string $resource = VerificationResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $admin = $this->admin();
        $review = $this->review();

        if ($review->isClaimedByOther($admin)) {
            Notification::make()
                ->title('يراجع هذا الطلب الآن: '.$review->assignee?->name)
                ->body('تأكد قبل اتخاذ قرار حتى لا تتكرر المراجعة.')
                ->warning()->send();
        } else {
            app(VerificationService::class)->claim($review, $admin);
        }
    }

    public function getTitle(): string
    {
        $owner = VerificationResource::ownerOf($this->review());

        return $this->review()->kindLabel().' — '.($owner?->name ?? '');
    }

    protected function getHeaderActions(): array
    {
        $pending = fn () => $this->review()->isPending();

        return [
            Action::make('approve')
                ->label('قبول')->color('success')->icon(Heroicon::OutlinedCheckCircle)
                ->visible($pending)
                ->requiresConfirmation()
                ->modalHeading('قبول الطلب')
                ->modalDescription(fn () => match (true) {
                    $this->review()->subject instanceof DriverProfile => 'سيصبح السائق نشطاً، وتُعتمد عربياته وهويته.',
                    default => 'سيتم اعتماد المستندات وإشعار المستخدم.',
                })
                ->schema([Textarea::make('note')->label('ملاحظة (اختياري)')->maxLength(500)])
                ->action(fn (array $data) => $this->decide(fn (VerificationService $s) => $s->approve($this->review(), $this->admin(), $data['note'] ?? null), 'تم قبول الطلب')),

            Action::make('reject')
                ->label('رفض')->color('danger')->icon(Heroicon::OutlinedXCircle)
                ->visible($pending)
                ->modalHeading('رفض الطلب')
                ->modalDescription('يستطيع المستخدم تعديل المستندات وإعادة الإرسال.')
                ->schema([
                    CheckboxList::make('reasons')->label('الأسباب')
                        ->options(fn () => collect(settings()->list('verification.rejection_reasons'))->mapWithKeys(fn ($r) => [$r => $r]))
                        ->required(),
                    Textarea::make('note')->label('ملاحظة للمستخدم (اختياري)')->maxLength(500),
                    Toggle::make('identity_ok')->label('الهوية سليمة — وثّقها رغم رفض طلب السائق')
                        ->visible(fn () => $this->review()->subject instanceof DriverProfile && $this->review()->id_document_type !== null),
                ])
                ->action(fn (array $data) => $this->decide(
                    fn (VerificationService $s) => $s->reject($this->review(), $this->admin(), $data['reasons'], $data['note'] ?? null, identityOk: (bool) ($data['identity_ok'] ?? false)),
                    'تم رفض الطلب وإشعار المستخدم',
                )),

            Action::make('request_info')
                ->label('طلب معلومات')->color('gray')->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
                ->visible($pending)
                ->schema([Textarea::make('note')->label('ما المطلوب من المستخدم؟')->required()->maxLength(500)])
                ->action(fn (array $data) => $this->decide(
                    fn (VerificationService $s) => $s->reject($this->review(), $this->admin(), [], $data['note'], infoRequested: true),
                    'تم إرسال الطلب للمستخدم',
                )),

            Action::make('next')
                ->label('التالي في القائمة')->color('gray')->icon(Heroicon::OutlinedForward)
                ->url(fn () => ($next = $this->nextPending()) ? VerificationResource::getUrl('view', ['record' => $next]) : VerificationResource::getUrl())
                ->visible(fn () => ! $this->review()->isPending()),
        ];
    }

    private function decide(\Closure $callback, string $success): void
    {
        try {
            $callback(app(VerificationService::class));
        } catch (RuntimeException|ValidationException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        $this->record = $this->review()->fresh();
        Notification::make()->title($success)->success()->send();

        if ($next = $this->nextPending()) {
            $this->redirect(VerificationResource::getUrl('view', ['record' => $next]));
        } else {
            $this->redirect(VerificationResource::getUrl());
        }
    }

    private function nextPending(): ?VerificationReview
    {
        return VerificationReview::pending()
            ->whereKeyNot($this->review()->id)
            ->where(fn ($q) => $q->whereNull('assigned_to')
                ->orWhere('assigned_to', $this->admin()->id)
                ->orWhere('assigned_at', '<', now()->subMinutes(VerificationReview::CLAIM_MINUTES)))
            ->orderByDesc('priority')->orderBy('created_at')
            ->first();
    }

    private function review(): VerificationReview
    {
        return $this->getRecord();
    }

    private function admin(): Admin
    {
        return Auth::guard('admin')->user();
    }
}
