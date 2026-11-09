<?php

namespace Database\Factories;

use App\Enums\ParcelStatus;
use App\Models\Parcel;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Http\UploadedFile;

/** @extends Factory<Parcel> */
class ParcelFactory extends Factory
{
    public function definition(): array
    {
        return [
            'trip_id' => fn () => Trip::factory()->create([
                'accepts_parcels' => true, 'max_parcels' => 3,
                'parcel_price_small' => 3000, 'parcel_price_medium' => 5000, 'parcel_price_large' => 8000,
            ])->id,
            'sender_id' => fn () => User::factory()->create(['id_verification_status' => 'verified'])->id,
            'description' => 'ملابس وأدوية',
            'size' => 'medium',
            'photo_disk' => 'local',
            'photo_path' => fn () => UploadedFile::fake()->image('p.jpg')->store('parcels', ['disk' => 'local']),
            'receiver_name' => 'عائشة',
            'receiver_phone' => '+2499'.fake()->numerify('########'),
            'payer' => 'receiver',
            'price' => 5000,
            'status' => ParcelStatus::Pending,
            'prohibited_ack_at' => now(),
            'expires_at' => now()->addHours(12),
        ];
    }
}
