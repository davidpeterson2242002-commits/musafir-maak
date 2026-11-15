<?php

namespace App\Filament\Resources\NotificationChannels\Pages;

use App\Enums\NotificationEvent;
use App\Filament\Resources\NotificationChannels\NotificationChannelResource;
use App\Models\NotificationChannel;
use App\Enums\NotificationChannelType;
use App\Jobs\DeliverNotification;
use App\Models\NotificationLog;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\FirebaseSetup;
use App\Services\MailSetup;
use Filament\Forms\Components\Select;
use App\Support\Phone;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Throwable;
use Filament\Resources\Pages\ManageRecords;

class ManageNotificationChannels extends ManageRecords
{
    protected static string $resource = NotificationChannelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('firebase')
                ->label(fn () => app(FirebaseSetup::class)->status() ? 'Firebase: مفعّل ✓' : 'إعداد Firebase (الإشعارات الفورية)')
                ->icon('heroicon-o-key')
                ->color(fn () => app(FirebaseSetup::class)->status() ? 'success' : 'warning')
                ->modalHeading('مفتاح Firebase للإشعارات الفورية')
                ->modalDescription(function () {
                    $status = app(FirebaseSetup::class)->status();

                    return ($status
                        ? "المفتاح الحالي: مشروع {$status['project_id']} ({$status['client_email']}). الصق مفتاحاً جديداً لاستبداله.\n\n"
                        : "لم يُضَف مفتاح بعد، فالإشعارات الفورية متوقفة.\n\n")
                        .'افتح ملف المفتاح (JSON) الذي نزل من Firebase بالمفكرة (Notepad)، انسخ كل محتواه والصقه هنا. يُحفظ مشفّراً ولا يظهر مرة أخرى.';
                })
                ->schema([
                    Textarea::make('json')->label('محتوى ملف المفتاح (JSON)')->rows(10)->required()
                        ->extraInputAttributes(['dir' => 'ltr', 'style' => 'font-family: monospace']),
                ])
                ->modalSubmitActionLabel('حفظ المفتاح')
                ->action(function (array $data) {
                    $saved = app(FirebaseSetup::class)->save($data['json'], 'mountedActions.0.data.json');
                    Notification::make()->title('تم حفظ مفتاح Firebase')->body("المشروع: {$saved['project_id']}. جرّب «إشعار تجريبي» الآن.")->success()->send();
                })
                ->extraModalFooterActions(fn () => app(FirebaseSetup::class)->status() ? [
                    Action::make('removeFirebase')->label('حذف المفتاح')->color('danger')->requiresConfirmation()
                        ->modalDescription('ستتوقف الإشعارات الفورية حتى يُضاف مفتاح جديد.')
                        ->action(function () {
                            app(FirebaseSetup::class)->remove();
                            Notification::make()->title('تم حذف مفتاح Firebase')->warning()->send();
                        })
                        ->cancelParentActions(),
                ] : []),
            Action::make('mail')
                ->label(fn () => app(MailSetup::class)->current() ? 'البريد: مفعّل ✓' : 'إعداد البريد (رموز الدخول)')
                ->icon('heroicon-o-envelope')
                ->color(fn () => app(MailSetup::class)->current() ? 'success' : 'warning')
                ->modalHeading('البريد الصادر (SMTP)')
                ->modalDescription('رموز الدخول والكشوفات تُرسل من هذا البريد. في Hostinger: hPanel → Emails → أنشئ بريد مثل noreply@statement-ds.com ثم اكتب بياناته هنا. كلمة المرور تُحفظ مشفّرة.')
                ->fillForm(fn () => array_merge(
                    ['host' => 'smtp.hostinger.com', 'port' => 465, 'encryption' => 'ssl', 'from_name' => 'مسافر معاك'],
                    collect(app(MailSetup::class)->current() ?? [])->except('password')->all(),
                ))
                ->schema([
                    TextInput::make('host')->label('خادم SMTP')->required()->extraInputAttributes(['dir' => 'ltr']),
                    TextInput::make('port')->label('المنفذ')->numeric()->required(),
                    Select::make('encryption')->label('التشفير')->options(['ssl' => 'SSL (منفذ 465)', 'tls' => 'TLS (منفذ 587)'])->required(),
                    TextInput::make('username')->label('البريد (اسم المستخدم)')->email()->required()->extraInputAttributes(['dir' => 'ltr']),
                    TextInput::make('password')->label('كلمة المرور')->password()->revealable()
                        ->helperText(fn () => app(MailSetup::class)->current() ? 'اتركها فارغة للإبقاء على الحالية.' : null),
                    TextInput::make('from_address')->label('عنوان المرسل (اختياري)')->email()->extraInputAttributes(['dir' => 'ltr']),
                    TextInput::make('from_name')->label('اسم المرسل'),
                ])
                ->modalSubmitActionLabel('حفظ')
                ->action(function (array $data) {
                    app(MailSetup::class)->save($data);
                    Notification::make()->title('تم حفظ إعدادات البريد')->body('جرّب «بريد تجريبي» للتأكد.')->success()->send();
                }),
            Action::make('testMail')
                ->label('بريد تجريبي')->icon('heroicon-o-envelope-open')->color('gray')
                ->schema([TextInput::make('to')->label('أرسل إلى')->email()->required()->extraInputAttributes(['dir' => 'ltr'])])
                ->action(function (array $data) {
                    $error = app(MailSetup::class)->test($data['to']);
                    $error === null
                        ? Notification::make()->title('تم الإرسال')->body('افحص صندوق الوارد (وأيضاً Spam).')->success()->send()
                        : Notification::make()->title('فشل الإرسال')->body(mb_substr($error, 0, 300))->danger()->persistent()->send();
                }),
            Action::make('testPush')
                ->label('إشعار تجريبي')->icon('heroicon-o-paper-airplane')->color('gray')
                ->modalDescription('يُرسل إشعاراً فورياً لكل أجهزة هذا المستخدم. يجب أن يكون قد سجّل الدخول في التطبيق من نسخة تدعم الإشعارات.')
                ->schema([TextInput::make('phone')->label('رقم هاتف المستخدم')->required()->extraInputAttributes(['dir' => 'ltr'])])
                ->action(function (array $data) {
                    $phone = Phone::normalize($data['phone']);
                    $user = $phone ? User::where('phone', $phone)->first() : null;
                    if ($user === null) {
                        Notification::make()->title('لا يوجد مستخدم بهذا الرقم')->danger()->send();

                        return;
                    }
                    try {
                        app()->call([new DeliverNotification($user->id, 'broadcast', NotificationChannelType::Push->value,
                            'رسالة تجريبية', 'الإشعارات الفورية تعمل في مسافر معاك ✓', ['test' => true]), 'handle']);
                        $log = NotificationLog::where('user_id', $user->id)->where('channel', 'push')->latest('id')->first();
                        $log?->status === NotificationLog::SKIPPED
                            ? Notification::make()->title('لم يُرسل')->body((string) $log->error)->warning()->send()
                            : Notification::make()->title(app(FirebaseSetup::class)->active() ? 'تم الإرسال عبر Firebase' : 'سُجّل فقط (Firebase غير مفعّل)')->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()->title('فشل الإرسال')->body($e->getMessage())->danger()->send();
                    }
                }),
            Action::make('reset')
                ->label('إرجاع الإعدادات الافتراضية')->color('gray')
                ->requiresConfirmation()
                ->modalDescription('تُرجَع كل الإشعارات إلى القنوات الافتراضية.')
                ->action(function () {
                    foreach (NotificationEvent::configurable() as $event) {
                        NotificationChannel::updateOrCreate(['event' => $event->value], $event->defaultChannels());
                    }
                    app(AuditLogger::class)->log('notification_channels.reset');
                    Notification::make()->title('تم إرجاع القنوات الافتراضية')->success()->send();
                }),
        ];
    }
}
