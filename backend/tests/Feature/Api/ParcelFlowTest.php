<?php

namespace Tests\Feature\Api;

use App\Models\Admin;
use App\Models\LedgerEntry;
use App\Models\Parcel;
use App\Models\Trip;
use App\Models\UserBlock;
use App\Services\ParcelService;
use App\Services\TripService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsTrips;
use Tests\TestCase;

/** Parcels / ارسالية (spec 5.6). */
class ParcelFlowTest extends TestCase
{
    use BuildsTrips;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function parcelTrip(array $attrs = []): Trip
    {
        return $this->trip($attrs + [
            'accepts_parcels' => true, 'max_parcels' => 2,
            'parcel_price_small' => 3000, 'parcel_price_medium' => 5000, 'parcel_price_large' => 8000,
            'departs_at' => now()->addDays(2),
        ]);
    }

    private function send(Trip $trip, array $overrides = []): TestResponse
    {
        return $this->post("/api/v1/trips/{$trip->id}/parcels", $overrides + [
            'description' => 'ملابس وأدوية', 'size' => 'medium', 'photo' => UploadedFile::fake()->image('p.jpg'),
            'receiver_name' => 'عائشة', 'receiver_phone' => '0912345678', 'payer' => 'receiver', 'accept_prohibited' => '1',
        ], ['Accept' => 'application/json']);
    }

    private function asDriver(Trip $trip): void
    {
        Sanctum::actingAs($trip->driverProfile->user);
    }

    private function accepted(?Trip $trip = null): Parcel
    {
        $trip ??= $this->parcelTrip();
        $parcel = Parcel::factory()->create(['trip_id' => $trip->id]);

        return app(ParcelService::class)->accept($parcel);
    }

    public function test_sender_requests_a_parcel(): void
    {
        $trip = $this->parcelTrip();
        Sanctum::actingAs($this->verifiedPassenger());

        $this->send($trip)->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.price', 5000)
            ->assertJsonPath('data.receiver_phone', '+249912345678')
            ->assertJsonPath('data.delivery_code', null)
            ->assertJsonPath('data.conversation_id', fn ($id) => $id > 0);

        $this->whatsapp()->assertSentTo($trip->driverProfile->user->phone, 'parcel_requested');
        Storage::disk('local')->assertExists(Parcel::first()->photo_path);
    }

    public function test_request_validation_and_rules(): void
    {
        $trip = $this->parcelTrip();
        Sanctum::actingAs($this->verifiedPassenger());

        $this->send($trip, ['accept_prohibited' => '0'])->assertJsonValidationErrors('accept_prohibited');
        $this->send($trip, ['receiver_phone' => '123'])->assertJsonValidationErrors('receiver_phone');
        $this->send($trip, ['size' => 'huge'])->assertJsonValidationErrors('size');
        $this->send($trip, ['photo' => null])->assertJsonValidationErrors('photo');

        $this->send($this->trip())->assertJsonPath('code', 'parcels_unavailable');

        Sanctum::actingAs($this->verifiedPassenger(['id_verification_status' => 'none']));
        $this->send($trip)->assertJsonPath('code', 'id_verification_required');

        $this->asDriver($trip);
        $this->send($trip)->assertJsonPath('code', 'own_trip');

        $blocked = $this->verifiedPassenger();
        UserBlock::create(['blocker_id' => $trip->driverProfile->user_id, 'blocked_id' => $blocked->id]);
        Sanctum::actingAs($blocked);
        $this->send($trip)->assertJsonPath('code', 'blocked');
    }

    public function test_accept_generates_code_and_messages_receiver(): void
    {
        $trip = $this->parcelTrip();
        $trip->driverProfile->forceFill(['free_bookings_remaining' => 0])->save();
        $parcel = Parcel::factory()->create(['trip_id' => $trip->id, 'receiver_phone' => '+249911111111', 'payer' => 'receiver', 'price' => 5000]);
        $this->asDriver($trip);

        $this->getJson('/api/v1/driver/parcels')->assertJsonPath('data.0.fee', ['amount' => 500, 'free' => false])
            ->assertJsonPath('data.0.receiver_phone', null);
        $this->postJson("/api/v1/driver/parcels/{$parcel->id}/accept")->assertOk()
            ->assertJsonPath('data.status', 'accepted')
            ->assertJsonMissingPath('data.delivery_code')
            ->assertJsonPath('data.receiver_phone', '+249911111111');

        $code = $parcel->fresh()->delivery_code;
        $this->assertMatchesRegularExpression('/^\d{4}$/', $code);
        $this->assertNotSame($code, \DB::table('parcels')->value('delivery_code'), 'stored encrypted');

        $msg = collect($this->whatsapp()->sent)->firstWhere('template', 'parcel_delivery_code');
        $this->assertSame('+249911111111', $msg['phone']);
        $this->assertStringContainsString($code, $msg['text']);
        $this->assertStringContainsString('5,000', $msg['text']);
        $this->assertStringContainsString($trip->vehicle->plate_number, $msg['text']);

        $this->assertSame(500, $trip->driverProfile->fresh()->balance_owed, '10% parcel commission');

        Sanctum::actingAs($parcel->sender);
        $this->getJson("/api/v1/parcels/{$parcel->id}")->assertJsonPath('data.delivery_code', $code)
            ->assertJsonPath('data.receiver_notified', true)
            ->assertJsonPath('data.contact.plate_number', $trip->vehicle->plate_number);
    }

    public function test_free_bookings_cover_parcels(): void
    {
        $parcel = $this->accepted();
        $this->assertTrue($parcel->fresh()->fee_free);
        $this->assertSame(2, $parcel->trip->driverProfile->fresh()->free_bookings_remaining);
    }

    public function test_parcel_slots_are_limited(): void
    {
        $trip = $this->parcelTrip(['max_parcels' => 1]);
        $this->accepted($trip);
        $second = Parcel::factory()->create(['trip_id' => $trip->id]);
        $this->asDriver($trip);

        $this->postJson("/api/v1/driver/parcels/{$second->id}/accept")->assertJsonPath('code', 'parcels_full');

        Sanctum::actingAs($this->verifiedPassenger());
        $this->send($trip)->assertJsonPath('code', 'parcels_unavailable');
        $this->getJson('/api/v1/trips/search?'.http_build_query([
            'origin_city_id' => $trip->route->origin_city_id, 'destination_city_id' => $trip->route->destination_city_id,
            'date' => local_time($trip->departs_at)->toDateString(), 'kind' => 'parcel',
        ]))->assertJsonCount(0, 'data');
    }

    public function test_receiver_whatsapp_failure_does_not_block_acceptance(): void
    {
        $trip = $this->parcelTrip();
        $parcel = Parcel::factory()->create(['trip_id' => $trip->id]);
        $this->whatsapp()->shouldFail = true;

        app(ParcelService::class)->accept($parcel);

        $this->assertSame('accepted', $parcel->fresh()->status->value);
        $this->assertNull($parcel->fresh()->receiver_notified_at);
    }

    public function test_pickup_photo_then_delivery_with_code(): void
    {
        $parcel = $this->accepted();
        $code = $parcel->fresh()->delivery_code;
        $this->asDriver($parcel->trip);

        $this->postJson("/api/v1/driver/parcels/{$parcel->id}/deliver", ['code' => $code])->assertJsonPath('code', 'not_picked_up');
        $this->post("/api/v1/driver/parcels/{$parcel->id}/pickup", ['photo' => UploadedFile::fake()->image('x.jpg')], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('data.status', 'picked_up')->assertJsonPath('data.pickup_photo_url', fn ($u) => filled($u));

        $wrong = $code === '0000' ? '1111' : '0000';
        $this->postJson("/api/v1/driver/parcels/{$parcel->id}/deliver", ['code' => $wrong])
            ->assertJsonPath('code', 'wrong_code')->assertJsonPath('attempts_left', 4);
        $this->postJson("/api/v1/driver/parcels/{$parcel->id}/deliver", ['code' => $code])->assertOk()->assertJsonPath('data.status', 'delivered');

        Sanctum::actingAs($parcel->sender);
        $this->getJson("/api/v1/parcels/{$parcel->id}")->assertJsonPath('data.status', 'delivered')->assertJsonPath('data.delivery_code', null);
    }

    public function test_delivery_code_locks_after_max_attempts(): void
    {
        settings()->set('parcels.code_max_attempts', 2);
        $parcel = $this->accepted();
        app(ParcelService::class)->pickUp($parcel->fresh(), UploadedFile::fake()->image('x.jpg'));
        $code = $parcel->fresh()->delivery_code;
        $wrong = $code === '0000' ? '1111' : '0000';
        $this->asDriver($parcel->trip);

        $this->postJson("/api/v1/driver/parcels/{$parcel->id}/deliver", ['code' => $wrong])->assertJsonPath('code', 'wrong_code');
        $this->postJson("/api/v1/driver/parcels/{$parcel->id}/deliver", ['code' => $wrong])->assertJsonPath('code', 'code_locked');
        $this->postJson("/api/v1/driver/parcels/{$parcel->id}/deliver", ['code' => $code])->assertJsonPath('code', 'code_locked');
    }

    public function test_driver_refuses_at_pickup_and_fee_is_reversed(): void
    {
        $trip = $this->parcelTrip();
        $trip->driverProfile->forceFill(['free_bookings_remaining' => 0])->save();
        $parcel = $this->accepted($trip);
        $this->asDriver($trip);

        $this->postJson("/api/v1/driver/parcels/{$parcel->id}/refuse", ['reason' => 'المحتوى لا يطابق الوصف'])->assertOk()
            ->assertJsonPath('data.status', 'refused');
        $this->assertSame(0, $trip->driverProfile->fresh()->balance_owed);
    }

    public function test_sender_cancels_before_pickup_only(): void
    {
        $trip = $this->parcelTrip();
        $trip->driverProfile->forceFill(['free_bookings_remaining' => 0])->save();
        $parcel = $this->accepted($trip);
        Sanctum::actingAs($parcel->sender);

        $this->postJson("/api/v1/parcels/{$parcel->id}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->assertSame(0, $trip->driverProfile->fresh()->balance_owed);

        $picked = $this->accepted($trip);
        app(ParcelService::class)->pickUp($picked->fresh(), UploadedFile::fake()->image('x.jpg'));
        Sanctum::actingAs($picked->sender);
        $this->postJson("/api/v1/parcels/{$picked->id}/cancel")->assertJsonPath('code', 'not_cancellable');
    }

    public function test_trip_cancellation_cancels_parcels(): void
    {
        $trip = $this->parcelTrip();
        $trip->driverProfile->forceFill(['free_bookings_remaining' => 0])->save();
        $parcel = $this->accepted($trip);

        app(TripService::class)->cancel($trip->fresh(), 'عطل');

        $this->assertSame('cancelled', $parcel->fresh()->status->value);
        $this->assertSame(0, $trip->driverProfile->fresh()->balance_owed);
    }

    public function test_dispute_and_admin_resolution(): void
    {
        $trip = $this->parcelTrip();
        $trip->driverProfile->forceFill(['free_bookings_remaining' => 0])->save();
        $parcel = $this->accepted($trip);
        Sanctum::actingAs($parcel->sender);

        $this->postJson("/api/v1/parcels/{$parcel->id}/dispute", ['details' => 'لم تصل'])->assertJsonPath('code', 'not_disputable');
        app(ParcelService::class)->pickUp($parcel->fresh(), UploadedFile::fake()->image('x.jpg'));
        $this->postJson("/api/v1/parcels/{$parcel->id}/dispute", ['details' => 'مرت ثلاثة أيام ولم تصل'])->assertOk()->assertJsonPath('data.status', 'disputed');

        app(ParcelService::class)->resolveDispute($parcel->fresh(), 'cancelled', Admin::factory()->create(), 'السائق لم يسلّم');
        $this->assertSame('cancelled', $parcel->fresh()->status->value);
        $this->assertSame(0, $trip->driverProfile->fresh()->balance_owed);
        $this->assertSame(1, LedgerEntry::where('type', 'fee_reversal')->where('reference_type', 'parcel')->count());
    }

    public function test_unanswered_parcel_expires(): void
    {
        $parcel = Parcel::factory()->create();
        $this->travel(13)->hours();
        $this->artisan('musafir:tick');

        $this->assertSame('expired', $parcel->fresh()->status->value);
    }

    public function test_driver_trip_view_lists_parcels(): void
    {
        $parcel = $this->accepted();
        Parcel::factory()->create(['trip_id' => $parcel->trip_id]);
        $this->asDriver($parcel->trip);

        $this->getJson("/api/v1/driver/trips/{$parcel->trip_id}")->assertJsonCount(2, 'data.parcels')->assertJsonPath('data.parcel_slots_left', 1);
    }
}
