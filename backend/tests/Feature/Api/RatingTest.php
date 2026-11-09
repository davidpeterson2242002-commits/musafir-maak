<?php

namespace Tests\Feature\Api;

use App\Contracts\PushGateway;
use App\Models\Admin;
use App\Models\Booking;
use App\Models\Rating;
use App\Services\RatingService;
use App\Services\TripService;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsTrips;
use Tests\TestCase;

/** Ratings (spec 5.9). */
class RatingTest extends TestCase
{
    use BuildsTrips;

    private function completedBooking(): Booking
    {
        $trip = $this->trip(['departs_at' => now()->subHours(3), 'status' => 'departed']);

        return Booking::factory()->create(['trip_id' => $trip->id, 'status' => 'completed', 'completed_at' => now()])
            ->load('trip.driverProfile.user', 'passenger');
    }

    private function rate(Booking $b, int $stars, array $extra = []): TestResponse
    {
        return $this->postJson('/api/v1/ratings', $extra + ['subject_type' => 'booking', 'subject_id' => $b->id, 'stars' => $stars]);
    }

    public function test_both_sides_rate_each_other(): void
    {
        $b = $this->completedBooking();
        $driver = $b->trip->driverProfile;

        Sanctum::actingAs($b->passenger);
        $this->rate($b, 4, ['comment' => 'سواق ممتاز', 'tags' => ['punctual', 'safe_driving', 'not_a_tag']])->assertCreated()
            ->assertJsonPath('data.stars', 4)->assertJsonCount(2, 'data.tags');
        $this->rate($b, 5)->assertJsonPath('code', 'already_rated');

        Sanctum::actingAs($driver->user);
        $this->rate($b, 2, ['tags' => ['late']])->assertCreated();

        $this->assertSame(['4.00', 1], [(string) $driver->fresh()->rating_avg, $driver->fresh()->rating_count]);
        $this->assertSame(['2.00', 1], [(string) $b->passenger->fresh()->rating_avg, $b->passenger->fresh()->rating_count]);
    }

    public function test_rating_rules(): void
    {
        $pending = Booking::factory()->create();
        Sanctum::actingAs($pending->passenger);
        $this->rate($pending, 5)->assertJsonPath('code', 'not_ratable');

        $b = $this->completedBooking();
        Sanctum::actingAs($this->verifiedPassenger());
        $this->rate($b, 5)->assertNotFound();

        Sanctum::actingAs($b->passenger);
        $this->rate($b, 6)->assertJsonValidationErrors('stars');
        $this->travel(15)->days();
        $this->rate($b, 5)->assertJsonPath('code', 'rating_window_closed');
    }

    public function test_no_show_only_driver_rates(): void
    {
        $trip = $this->trip(['departs_at' => now()->subHour(), 'status' => 'departed']);
        $b = Booking::factory()->create(['trip_id' => $trip->id, 'status' => 'no_show']);
        $b->forceFill(['no_show_at' => now()])->save();

        Sanctum::actingAs($b->passenger);
        $this->rate($b, 1)->assertJsonPath('code', 'not_ratable');

        Sanctum::actingAs($trip->driverProfile->user);
        $this->rate($b, 1)->assertCreated();
    }

    public function test_low_rating_flags_driver_for_review(): void
    {
        settings()->set('ratings.review_min_trips', 3);
        $driverProfile = null;

        foreach ([2, 3, 2] as $i => $stars) {
            $b = $this->completedBooking();
            if ($driverProfile) {
                $b->trip->update(['driver_profile_id' => $driverProfile->id]);
                $b->load('trip.driverProfile');
            }
            $driverProfile = $b->trip->driverProfile;
            app(RatingService::class)->rate($b->passenger, $b, $stars);
            $i < 2 ? $this->assertNull($driverProfile->fresh()->low_rating_flagged_at) : null;
        }

        $this->assertNotNull($driverProfile->fresh()->low_rating_flagged_at);

        // Hiding an unfair review recalculates and can clear the flag.
        Rating::where('stars', 2)->get()->each(fn ($r) => app(RatingService::class)->setHidden($r, true, Admin::factory()->create()));
        $this->assertNull($driverProfile->fresh()->low_rating_flagged_at);
        $this->assertSame(1, $driverProfile->fresh()->rating_count);
    }

    public function test_pending_ratings_and_prompt_after_trip(): void
    {
        $trip = $this->trip(['departs_at' => now()->subHour()]);
        $booking = Booking::factory()->accepted()->create(['trip_id' => $trip->id]);
        $booking->passenger->deviceTokens()->create(['token' => 'pas', 'platform' => 'android']);
        $trip->forceFill(['status' => 'departed', 'departed_at' => now()])->save();

        app(TripService::class)->complete($trip->fresh());

        $this->assertTrue(collect(app(PushGateway::class)->sent)->contains(fn ($m) => $m['data']['event'] === 'rate_trip'));

        Sanctum::actingAs($booking->passenger);
        $this->getJson('/api/v1/ratings/pending')->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.rate_as', 'passenger')->assertJsonPath('data.0.ratee.id', $trip->driverProfile->user_id);

        Sanctum::actingAs($trip->driverProfile->user);
        $this->getJson('/api/v1/ratings/pending')->assertJsonPath('data.0.rate_as', 'driver');
    }

    public function test_public_profile_shows_reviews(): void
    {
        $b = $this->completedBooking();
        app(RatingService::class)->rate($b->passenger, $b, 5, 'رحلة مريحة', ['clean_car']);
        Sanctum::actingAs($this->verifiedPassenger());

        $this->getJson('/api/v1/users/'.$b->trip->driverProfile->user_id)->assertOk()
            ->assertJsonPath('driver.rating_avg', 5)
            ->assertJsonPath('reviews.as_driver.0.comment', 'رحلة مريحة')
            ->assertJsonPath('reviews.as_driver.0.tags.0.label', 'عربية نظيفة')
            ->assertJsonMissingPath('data.phone')
            ->assertJsonPath('blocked_by_me', false);
    }
}
