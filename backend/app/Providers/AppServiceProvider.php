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
use App\Support\Settings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
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

        // Per-IP cap on OTP requests, on top of the per-phone limits in settings.
        RateLimiter::for('otp', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));
    }
}
