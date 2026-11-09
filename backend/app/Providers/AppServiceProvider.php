<?php

namespace App\Providers;

use App\Contracts\OtpSender;
use App\Contracts\PushGateway;
use App\Contracts\WhatsAppGateway;
use App\Gateways\FakePushGateway;
use App\Gateways\FakeWhatsAppGateway;
use App\Gateways\LogPushGateway;
use App\Gateways\LogWhatsAppGateway;
use App\Gateways\WhatsAppOtpSender;
use App\Models\Admin;
use App\Models\City;
use App\Models\DriverProfile;
use App\Models\Setting;
use App\Models\State;
use App\Models\TravelRoute;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VerificationReview;
use App\Support\Settings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Settings::class);

        $this->app->singleton(WhatsAppGateway::class, fn () => match (config('services.whatsapp.driver')) {
            'log' => new LogWhatsAppGateway,
            'fake' => new FakeWhatsAppGateway,
            default => throw new InvalidArgumentException('Unknown WhatsApp driver: '.config('services.whatsapp.driver')),
        });

        $this->app->singleton(PushGateway::class, fn () => match (config('services.push.driver')) {
            'log' => new LogPushGateway,
            'fake' => new FakePushGateway,
            default => throw new InvalidArgumentException('Unknown push driver: '.config('services.push.driver')),
        });

        $this->app->singleton(OtpSender::class, WhatsAppOtpSender::class);
    }

    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

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
        ]);

        // Per-IP cap on OTP requests, on top of the per-phone limits in settings.
        RateLimiter::for('otp', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));
    }
}
