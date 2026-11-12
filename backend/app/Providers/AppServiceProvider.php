<?php

namespace App\Providers;

use App\Contracts\OtpSender;
use App\Contracts\PushGateway;
use App\Contracts\WhatsAppGateway;
use App\Events\BookingCancelled;
use App\Events\NoShowConfirmed;
use App\Events\TripCancelled;
use App\Gateways\FakePushGateway;
use App\Gateways\FakeWhatsAppGateway;
use App\Gateways\FcmPushGateway;
use App\Gateways\LogPushGateway;
use App\Gateways\LogWhatsAppGateway;
use App\Gateways\MetaWhatsAppGateway;
use App\Gateways\WhatsAppOtpSender;
use App\Models\Admin;
use App\Models\Booking;
use App\Models\Broadcast;
use App\Models\City;
use App\Models\Conversation;
use App\Models\DriverProfile;
use App\Models\LedgerEntry;
use App\Models\Message;
use App\Models\NotificationChannel;
use App\Models\Parcel;
use App\Models\PaymentMethod;
use App\Models\Rating;
use App\Models\Report;
use App\Models\Setting;
use App\Models\SettlementRequest;
use App\Models\State;
use App\Models\TravelRoute;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VerificationReview;
use App\Services\LedgerService;
use App\Support\ChannelMatrix;
use App\Support\Settings;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Settings::class);
        $this->app->singleton(ChannelMatrix::class);

        $this->app->singleton(WhatsAppGateway::class, fn () => match (config('services.whatsapp.driver')) {
            'log' => new LogWhatsAppGateway,
            'fake' => new FakeWhatsAppGateway,
            'meta' => new MetaWhatsAppGateway(
                (string) config('services.whatsapp.token'),
                (string) config('services.whatsapp.phone_number_id'),
                (string) config('services.whatsapp.graph_version'),
                config('whatsapp'),
                (int) config('services.whatsapp.timeout'),
            ),
            default => throw new InvalidArgumentException('Unknown WhatsApp driver: '.config('services.whatsapp.driver')),
        });

        $this->app->singleton(PushGateway::class, fn () => match (config('services.push.driver')) {
            'log' => new LogPushGateway,
            'fake' => new FakePushGateway,
            'fcm' => FcmPushGateway::fromConfig(config('services.push')),
            default => throw new InvalidArgumentException('Unknown push driver: '.config('services.push.driver')),
        });

        $this->app->singleton(OtpSender::class, WhatsAppOtpSender::class);
    }

    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

        // Admin lists refresh themselves while open (no manual reload after each change).
        \Filament\Tables\Table::configureUsing(fn (\Filament\Tables\Table $table) => $table->poll('15s'));

        // Short, stable type names in polymorphic columns (documents, reviews, audit log, tokens).
        Relation::enforceMorphMap([
            'user' => User::class,
            'admin' => Admin::class,
            'driver_profile' => DriverProfile::class,
            'vehicle' => Vehicle::class,
            'setting' => Setting::class,
            'state' => State::class,
            'city' => City::class,
            'route' => TravelRoute::class,
            'verification_review' => VerificationReview::class,
            'trip' => Trip::class,
            'booking' => Booking::class,
            'parcel' => Parcel::class,
            'conversation' => Conversation::class,
            'message' => Message::class,
            'rating' => Rating::class,
            'report' => Report::class,
            'settlement_request' => SettlementRequest::class,
            'payment_method' => PaymentMethod::class,
            'ledger_entry' => LedgerEntry::class,
            'notification_channel' => NotificationChannel::class,
            'broadcast' => Broadcast::class,
        ]);

        // Admin shows Western digits (as used in Sudan for money, phones and references), not Arabic-Indic.
        Table::configureUsing(fn (Table $table) => $table->defaultNumberLocale('en'));
        Schema::configureUsing(fn (Schema $schema) => $schema->defaultNumberLocale('en'));

        // Commission reversals (spec 5.3/5.4/5.5). Listeners are idempotent.
        Event::listen(BookingCancelled::class, fn ($e) => app(LedgerService::class)
            ->reverseBooking($e->booking, 'إلغاء الراكب للحجز #'.$e->booking->id));
        Event::listen(NoShowConfirmed::class, fn ($e) => app(LedgerService::class)
            ->reverseBooking($e->booking, 'عدم حضور الراكب — الحجز #'.$e->booking->id));
        Event::listen(TripCancelled::class, function ($e) {
            foreach (Booking::whereIn('id', $e->bookingIds)->get() as $booking) {
                app(LedgerService::class)->reverseBooking($booking, 'إلغاء الرحلة — الحجز #'.$booking->id);
            }
        });

        // Login endpoints (code request, code check, trusted-phone login). Counted per phone
        // number and, more loosely, per connection — a family or office shares one IP.
        // Admin-listed test numbers are not limited. The per-phone code limits in settings
        // (resend wait, hourly cap, attempts) apply on top.
        RateLimiter::for('otp', function (Request $request) {
            $phone = \App\Support\Phone::normalize((string) $request->input('phone')) ?? (string) $request->input('phone');
            if ($phone !== '' && app(\App\Services\OtpService::class)->isTestPhone($phone)) {
                return Limit::none();
            }

            return [
                Limit::perMinute(10)->by('phone:'.$phone),
                Limit::perMinute(40)->by('ip:'.$request->ip()),
            ];
        });
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));
    }
}
