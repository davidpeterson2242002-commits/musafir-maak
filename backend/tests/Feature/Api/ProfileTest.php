<?php

namespace Tests\Feature\Api;

use App\Models\City;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    public function test_first_profile_setup_requires_name_gender_city_and_terms(): void
    {
        Sanctum::actingAs(User::factory()->fresh()->create());

        $this->putJson('/api/v1/me', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'gender', 'city_id', 'accept_terms']);
    }

    public function test_profile_setup_completes_profile(): void
    {
        $user = User::factory()->fresh()->create();
        Sanctum::actingAs($user);
        $city = City::where('name_en', 'Wad Madani')->first();

        $this->putJson('/api/v1/me', [
            'name' => 'محمد أحمد', 'gender' => 'male', 'city_id' => $city->id,
            'email' => 'm@example.com', 'whatsapp_phone' => '0123456789', 'accept_terms' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.profile_completed', true)
            ->assertJsonPath('data.city.name', 'ود مدني')
            ->assertJsonPath('data.whatsapp_phone', '+249123456789');

        $this->assertNotNull($user->fresh()->terms_accepted_at);
    }

    public function test_disabled_city_cannot_be_chosen(): void
    {
        Sanctum::actingAs(User::factory()->fresh()->create());
        $city = City::first();
        $city->update(['enabled' => false]);

        $this->putJson('/api/v1/me', ['name' => 'سارة', 'gender' => 'female', 'city_id' => $city->id, 'accept_terms' => true])
            ->assertJsonValidationErrors('city_id');
    }

    public function test_gender_cannot_be_changed_after_setup(): void
    {
        $user = User::factory()->create(['gender' => 'male']);
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/me', ['gender' => 'female'])->assertJsonValidationErrors('gender');
        $this->putJson('/api/v1/me', ['name' => 'اسم جديد'])->assertOk()->assertJsonPath('data.name', 'اسم جديد');
    }

    public function test_whatsapp_defaults_to_phone(): void
    {
        Sanctum::actingAs(User::factory()->create(['phone' => '+249912345678', 'whatsapp_phone' => null]));
        $this->getJson('/api/v1/me')->assertJsonPath('data.whatsapp_phone', '+249912345678');
    }

    public function test_photo_upload_goes_to_private_disk(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->post('/api/v1/me/photo', ['photo' => UploadedFile::fake()->image('me.jpg')], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.photo_url', fn ($url) => filled($url));

        Storage::disk('local')->assertExists($user->fresh()->photo_path);
    }

    public function test_device_token_registration_is_idempotent_and_moves_between_users(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        Sanctum::actingAs($a);
        $this->postJson('/api/v1/devices', ['token' => 'tok-1', 'platform' => 'android'])->assertOk();
        $this->postJson('/api/v1/devices', ['token' => 'tok-1', 'platform' => 'android'])->assertOk();
        $this->assertDatabaseCount('device_tokens', 1);

        Sanctum::actingAs($b);
        $this->postJson('/api/v1/devices', ['token' => 'tok-1', 'platform' => 'android'])->assertOk();
        $this->assertDatabaseHas('device_tokens', ['token' => 'tok-1', 'user_id' => $b->id]);
    }

    public function test_delete_account_soft_deletes_and_revokes(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('t')->plainTextToken;

        $this->deleteJson('/api/v1/me', [], ['Authorization' => "Bearer $token"])->assertOk();
        $this->assertSoftDeleted($user);
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_deleted_account_can_sign_up_again_fresh(): void
    {
        $user = User::factory()->create(['phone' => '+249912345678', 'name' => 'قديم']);
        $user->delete();

        $this->postJson('/api/v1/auth/otp', ['phone' => '0912345678'])->assertOk();
        $code = $this->whatsapp()->last()['params']['code'];

        $this->postJson('/api/v1/auth/verify', ['phone' => '0912345678', 'code' => $code])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.name', null)
            ->assertJsonPath('user.profile_completed', false);
    }

    public function test_suspension_auto_lifts_when_expired(): void
    {
        $user = User::factory()->create(['status' => 'suspended', 'suspended_until' => now()->addDay()]);
        $this->assertTrue($user->isSuspended());

        $this->travel(2)->days();
        $this->assertFalse($user->fresh()->isSuspended());
        $this->assertSame('active', $user->fresh()->status->value);
    }
}
