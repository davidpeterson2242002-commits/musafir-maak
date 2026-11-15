<?php

namespace Tests\Feature\Api;

use App\Enums\BookingStatus;
use App\Enums\NotificationEvent;
use App\Models\Admin;
use App\Models\Booking;
use App\Models\MetricMinute;
use App\Models\RouteAlert;
use App\Models\SearchLog;
use App\Models\UserNotification;
use App\Services\Notifier;
use App\Support\ApiMetrics;
use Database\Factories\TripFactory;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsTrips;
use Tests\TestCase;

class InboxAndRouteDemandTest extends TestCase
{
    use BuildsTrips;

    public function test_every_notification_lands_in_the_inbox_except_chat(): void
    {
        $user = $this->verifiedPassenger();
        $notifier = app(Notifier::class);
        $notifier->send($user, NotificationEvent::RouteAlertMatch, 'رحلة جديدة', 'الخرطوم ← مدني', ['trip_id' => 7]);
        $notifier->send($user, NotificationEvent::NewChatMessage, 'رسالة', 'سلام', ['conversation_id' => 3]);

        Sanctum::actingAs($user);
        $this->getJson('/api/v1/me/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('unread', 1)
            ->assertJsonPath('data.0.title', 'رحلة جديدة')
            ->assertJsonPath('data.0.data.trip_id', 7)
            ->assertJsonPath('data.0.data.event', 'route_alert_match')
            ->assertJsonPath('data.0.read', false);
        $this->getJson('/api/v1/me/badges')->assertJsonPath('unread_notifications', 1);

        $this->postJson('/api/v1/me/notifications/read')->assertJsonPath('marked', 1);
        $this->getJson('/api/v1/me/badges')->assertJsonPath('unread_notifications', 0);
    }

    public function test_inbox_is_private_and_paged(): void
    {
        $user = $this->verifiedPassenger();
        $other = $this->verifiedPassenger();
        foreach (range(1, 35) as $i) {
            UserNotification::create(['user_id' => $user->id, 'event' => 'broadcast', 'title' => "n$i", 'body' => 'x']);
        }
        UserNotification::create(['user_id' => $other->id, 'event' => 'broadcast', 'title' => 'theirs', 'body' => 'x']);

        Sanctum::actingAs($user);
        $page = $this->getJson('/api/v1/me/notifications')->assertJsonCount(30, 'data')->assertJsonPath('has_more', true);
        $this->getJson('/api/v1/me/notifications?before_id='.$page->json('data.29.id'))->assertJsonCount(5, 'data');

        $theirs = UserNotification::where('user_id', $other->id)->value('id');
        $this->postJson('/api/v1/me/notifications/read', ['ids' => [$theirs]])->assertJsonPath('marked', 0);
    }

    public function test_closed_route_search_is_logged_and_reopen_alert_fires_when_opened(): void
    {
        $route = TripFactory::launchRoute();
        $route->update(['enabled' => false]);
        $passenger = $this->verifiedPassenger();
        Sanctum::actingAs($passenger);

        $query = ['origin_city_id' => $route->origin_city_id, 'destination_city_id' => $route->destination_city_id,
            'date' => now(config('app.display_timezone'))->addDay()->toDateString()];
        $this->getJson('/api/v1/trips/search?'.http_build_query($query))
            ->assertJsonPath('closed', true)->assertJsonPath('reopen_alert', false);
        $this->assertTrue(SearchLog::latest('id')->first()->closed);

        $this->postJson('/api/v1/route-alerts', [...array_slice($query, 0, 2, true), 'kind' => 'reopen'])
            ->assertCreated()->assertJsonPath('data.kind', 'reopen');
        $this->getJson('/api/v1/trips/search?'.http_build_query($query))->assertJsonPath('reopen_alert', true);
        // Asking twice doesn't create a second alert.
        $this->postJson('/api/v1/route-alerts', [...array_slice($query, 0, 2, true), 'kind' => 'reopen'])->assertSuccessful();
        $this->assertSame(1, RouteAlert::where('kind', 'reopen')->count());

        // A trip posted meanwhile doesn't match a reopen alert as a trip alert.
        $this->assertSame('passenger', RouteAlert::first()->role);

        $route->update(['enabled' => true]);

        $alert = RouteAlert::first();
        $this->assertFalse($alert->active);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $passenger->id, 'event' => 'route_reopened']);
    }

    public function test_reopen_alert_allowed_on_a_closed_city(): void
    {
        $route = TripFactory::launchRoute();
        $route->destination->update(['enabled' => false]);
        Sanctum::actingAs($this->verifiedPassenger());

        $this->postJson('/api/v1/route-alerts', ['origin_city_id' => $route->origin_city_id, 'destination_city_id' => $route->destination_city_id])
            ->assertJsonValidationErrors('destination_city_id');
        $this->postJson('/api/v1/route-alerts', ['origin_city_id' => $route->origin_city_id, 'destination_city_id' => $route->destination_city_id, 'kind' => 'reopen', 'role' => 'driver'])
            ->assertCreated();

        $this->getJson('/api/v1/cities?all=1')->assertJsonFragment(['id' => $route->destination_city_id, 'available' => false]);
        $this->assertNotContains($route->destination_city_id, collect($this->getJson('/api/v1/cities')->json('data'))->pluck('id'));

        $route->destination->update(['enabled' => true]);
        $this->assertFalse(RouteAlert::first()->active);
    }

    public function test_closed_popular_routes_are_listed_for_home(): void
    {
        $route = TripFactory::launchRoute();
        $route->update(['enabled' => false, 'is_popular' => true]);

        $this->getJson('/api/v1/routes?popular=1')->assertJsonFragment(['id' => $route->id, 'open' => false]);
    }

    public function test_booking_shows_cancel_policy(): void
    {
        $trip = $this->trip(['departs_at' => now()->addHour()]);
        $booking = Booking::factory()->accepted()->create(['trip_id' => $trip->id]);
        Sanctum::actingAs($booking->passenger);

        $this->getJson("/api/v1/bookings/{$booking->id}")
            ->assertJsonPath('data.can_cancel', true)
            ->assertJsonPath('data.cancel_policy.late_now', true)
            ->assertJsonPath('data.cancel_policy.late_window_hours', settings()->int('cancellation.late_window_hours'));

        $booking->update(['status' => BookingStatus::Pending]);
        $this->getJson("/api/v1/bookings/{$booking->id}")->assertJsonPath('data.cancel_policy.late_now', false);
    }

    public function test_api_requests_are_counted_for_the_live_dashboard(): void
    {
        Sanctum::actingAs($this->verifiedPassenger());
        $this->getJson('/api/v1/me/badges')->assertOk();
        $this->getJson('/api/v1/config')->assertOk();

        $this->assertGreaterThanOrEqual(2, ApiMetrics::minute(now())['requests']);

        $this->travel(1)->minutes();
        ApiMetrics::flush();
        $row = MetricMinute::orderByDesc('minute')->first();
        $this->assertGreaterThanOrEqual(2, $row->requests);

        settings()->set('ops.capacity_rps', 20);
        $snap = ApiMetrics::snapshot();
        $this->assertSame(1, $snap['online']);
        $this->assertNotNull($snap['capacity_users']);
        $this->assertContains($snap['status'], ['green', 'amber', 'red']);
    }

    public function test_dashboard_tabs_render(): void
    {
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        $this->get('/admin')->assertOk()->assertSee('الآن')->assertSee('الطرق والطلب');
        \Livewire\Livewire::test(\App\Filament\Widgets\LiveHealth::class)->assertOk()->assertSee('مستخدمين هسي');
        \Livewire\Livewire::test(\App\Filament\Widgets\ClosedRouteDemand::class)->assertOk();
        \Livewire\Livewire::test(\App\Filament\Widgets\TopRoutes::class)->assertOk();
        \Livewire\Livewire::test(\App\Filament\Widgets\TrafficChart::class)->assertOk();
        \Livewire\Livewire::test(\App\Filament\Widgets\ActivityChart::class)->assertOk();
        \Livewire\Livewire::test(\App\Filament\Widgets\OverviewStats::class)->assertOk();
    }
}
