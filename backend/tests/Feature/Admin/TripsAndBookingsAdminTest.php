<?php

namespace Tests\Feature\Admin;

use App\Enums\AdminRole;
use App\Filament\Resources\Bookings\Pages\ListBookings;
use App\Filament\Resources\Bookings\Pages\ViewBooking;
use App\Filament\Resources\Trips\Pages\ViewTrip;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Widgets\ZeroResultSearches;
use App\Models\Admin;
use App\Models\Booking;
use App\Models\SearchLog;
use App\Models\User;
use App\Services\BookingService;
use Database\Factories\TripFactory;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Concerns\BuildsTrips;
use Tests\TestCase;

class TripsAndBookingsAdminTest extends TestCase
{
    use BuildsTrips;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
    }

    public function test_support_opens_trips_and_bookings(): void
    {
        $booking = Booking::factory()->accepted()->create();
        Booking::factory()->create(['trip_id' => $booking->trip_id]); // several bookings: catches lazy loading
        $this->actingAs(Admin::factory()->role(AdminRole::Support)->create(), 'admin');

        $this->get('/admin/trips')->assertOk();
        $this->get('/admin/trips/'.$booking->trip_id)->assertOk()->assertSee($booking->passenger->name);
        $this->get('/admin/bookings')->assertOk();
        $this->get('/admin/bookings/'.$booking->id)->assertOk();
        $this->get('/admin/users/'.$booking->passenger_id)->assertOk();
    }

    public function test_reviewer_cannot_open_trips(): void
    {
        $this->actingAs(Admin::factory()->role(AdminRole::Reviewer)->create(), 'admin');
        $this->get('/admin/trips')->assertForbidden();
        $this->get('/admin/bookings')->assertForbidden();
    }

    public function test_admin_cancels_a_trip(): void
    {
        $booking = Booking::factory()->accepted()->create();
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        Livewire::test(ViewTrip::class, ['record' => $booking->trip_id])
            ->callAction('cancel', ['reason' => 'الطريق مقفول'])
            ->assertHasNoActionErrors();

        $this->assertSame('cancelled', $booking->trip->fresh()->status->value);
        $this->assertSame('cancelled_by_driver', $booking->fresh()->status->value);
        $this->assertDatabaseHas('audit_logs', ['action' => 'trip.cancelled', 'admin_id' => $admin->id]);
    }

    public function test_admin_hides_a_trip_from_search(): void
    {
        $trip = $this->trip();
        $this->actingAs(Admin::factory()->create(), 'admin');

        Livewire::test(ViewTrip::class, ['record' => $trip->id])->callAction('toggle_hidden');
        $this->assertTrue($trip->fresh()->hidden_by_admin);
    }

    public function test_dispute_tab_and_resolution(): void
    {
        $trip = $this->trip(['departs_at' => now()->subHour(), 'status' => 'departed']);
        $booking = Booking::factory()->accepted()->create(['trip_id' => $trip->id]);
        app(BookingService::class)->markNoShow($booking->fresh());
        app(BookingService::class)->disputeNoShow($booking->fresh(), 'كنت حاضر في الموقف');
        $other = Booking::factory()->create();
        $this->actingAs(Admin::factory()->role(AdminRole::Support)->create(), 'admin');

        Livewire::test(ListBookings::class)->set('activeTab', 'disputes')
            ->assertCanSeeTableRecords([$booking])->assertCanNotSeeTableRecords([$other]);

        Livewire::test(ViewBooking::class, ['record' => $booking->id])
            ->assertActionVisible('confirm_no_show')
            ->callAction('overturn_no_show', ['note' => 'السائق أكد أنه خطأ'])
            ->assertHasNoActionErrors();

        $this->assertSame('overturned', $booking->fresh()->no_show_resolution);
        $this->assertSame('completed', $booking->fresh()->status->value);
    }

    public function test_lift_booking_restriction(): void
    {
        $user = User::factory()->create(['booking_restricted_until' => now()->addDays(5)]);
        $this->actingAs(Admin::factory()->create(), 'admin');

        Livewire::test(ViewUser::class, ['record' => $user->id])
            ->callAction('lift_booking_restriction', ['reason' => 'ظروف طارئة'])
            ->assertHasNoActionErrors();

        $this->assertFalse($user->fresh()->isBookingRestricted());
    }

    public function test_zero_result_searches_widget(): void
    {
        $route = TripFactory::launchRoute();
        SearchLog::create(['origin_city_id' => $route->origin_city_id, 'destination_city_id' => $route->destination_city_id, 'results_count' => 0]);
        SearchLog::create(['origin_city_id' => $route->origin_city_id, 'destination_city_id' => $route->destination_city_id, 'results_count' => 0]);
        SearchLog::create(['origin_city_id' => $route->origin_city_id, 'destination_city_id' => $route->destination_city_id, 'results_count' => 3]);
        $this->actingAs(Admin::factory()->create(), 'admin');

        Livewire::test(ZeroResultSearches::class)->assertSee('الخرطوم ← ود مدني')->assertSee('2');
    }
}
