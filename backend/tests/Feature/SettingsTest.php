<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Support\Settings;
use App\Support\SettingsRegistry;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    public function test_every_registry_key_is_seeded(): void
    {
        $this->assertSame(count(SettingsRegistry::definitions()), Setting::count());
    }

    public function test_spec_defaults(): void
    {
        $this->assertSame('percentage', settings('fees.model'));
        $this->assertSame(3, settings()->int('drivers.free_bookings'));
        $this->assertSame(7, settings()->int('settlement.cycle_days'));
        $this->assertSame(12, settings()->int('booking.response_timeout_hours'));
        $this->assertSame(60, settings()->int('booking.reminder_interval_minutes'));
        $this->assertTrue(settings()->bool('verification.allow_first_booking_while_pending'));
        $this->assertSame('optional', settings('wallet.mode'));
    }

    public function test_values_are_typed(): void
    {
        $this->assertIsInt(settings('settlement.balance_limit'));
        $this->assertIsFloat(settings('fees.percent.car'));
        $this->assertIsBool(settings('routes.auto_create'));
        $this->assertIsArray(settings('content.prohibited_items'));
    }

    public function test_values_are_cached_and_flushed_on_save(): void
    {
        settings()->all(); // warm cache

        DB::enableQueryLog();
        app(Settings::class)->int('otp.length');
        $this->assertCount(0, DB::getQueryLog(), 'settings should be served from cache');

        Setting::where('key', 'otp.length')->first()->update(['value' => 4]);
        $this->assertSame(4, settings()->int('otp.length'));
    }

    public function test_missing_row_falls_back_to_registry_default(): void
    {
        Setting::where('key', 'booking.response_timeout_hours')->delete();
        $this->assertSame(12, settings()->int('booking.response_timeout_hours'));
    }

    public function test_unknown_key_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        settings('does.not.exist');
    }

    public function test_seeder_does_not_overwrite_admin_changes(): void
    {
        settings()->set('drivers.free_bookings', 5);
        $this->artisan('settings:sync')->assertSuccessful();
        $this->assertSame(5, settings()->int('drivers.free_bookings'));
    }

    public function test_change_by_admin_is_audited(): void
    {
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        settings()->set('booking.response_timeout_hours', 6);

        $log = AuditLog::latest('id')->first();
        $this->assertSame($admin->id, $log->admin_id);
        $this->assertSame('updated', $log->action);
        $this->assertSame(['old' => 12, 'new' => 6], $log->changes['value']);
    }

    public function test_changes_without_admin_are_not_audited(): void
    {
        settings()->set('booking.response_timeout_hours', 6);
        $this->assertSame(0, AuditLog::count());
    }
}
