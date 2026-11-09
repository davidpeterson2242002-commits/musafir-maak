<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\BookingStatus;
use App\Enums\TripStatus;
use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RegisterDeviceRequest;
use App\Http\Requests\Api\V1\UpdateProfileRequest;
use App\Http\Resources\V1\MeResource;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    /** GET /api/v1/me */
    public function show(Request $request): MeResource
    {
        return new MeResource($request->user()->load(['city.state', 'driverProfile']));
    }

    /** PUT /api/v1/me — first-time profile setup and later edits. */
    public function update(UpdateProfileRequest $request): MeResource
    {
        $user = $request->user();
        $data = $request->safe()->except('accept_terms');

        if ($request->boolean('accept_terms') && ! $user->terms_accepted_at) {
            $data['terms_accepted_at'] = now();
        }

        $user->fill($data)->save();

        return new MeResource($user->load(['city.state', 'driverProfile']));
    }

    /** POST /api/v1/me/photo — upload/replace profile photo (private storage). */
    public function updatePhoto(Request $request): MeResource
    {
        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $user = $request->user();
        $disk = Storage::disk(config('filesystems.private_disk'));

        $path = $request->file('photo')->store("users/{$user->id}/photo", ['disk' => config('filesystems.private_disk')]);

        if ($user->photo_path) {
            $disk->delete($user->photo_path);
        }

        $user->update(['photo_path' => $path]);

        return new MeResource($user->load(['city.state', 'driverProfile']));
    }

    /** DELETE /api/v1/me — delete account (soft delete; data retained for safety/audit). */
    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->bookings()->whereIn('status', BookingStatus::active())->exists()) {
            throw new BusinessRuleException('ألغِ حجوزاتك القائمة قبل حذف الحساب.', 'has_active_bookings');
        }
        if ($user->driverProfile()->first()?->trips()->whereIn('status', [TripStatus::Scheduled, TripStatus::Departed])->exists()) {
            throw new BusinessRuleException('ألغِ رحلاتك القادمة قبل حذف الحساب.', 'has_active_trips');
        }
        if (($user->driverProfile()->first()?->balance_owed ?? 0) > 0) {
            throw new BusinessRuleException('سدّد رصيدك المستحق قبل حذف الحساب.', 'has_balance_owed');
        }

        $user->tokens()->delete();
        $user->deviceTokens()->delete();
        $user->delete();

        return response()->json(['message' => 'تم حذف الحساب.']);
    }

    /** POST /api/v1/devices — register an FCM token for push. */
    public function registerDevice(RegisterDeviceRequest $request): JsonResponse
    {
        DeviceToken::updateOrCreate(
            ['token' => $request->validated('token')],
            ['user_id' => $request->user()->id, 'platform' => $request->validated('platform'), 'last_used_at' => now()],
        );

        return response()->json(['message' => 'ok']);
    }

    /** DELETE /api/v1/devices — unregister a token (on logout). */
    public function unregisterDevice(Request $request): JsonResponse
    {
        $request->validate(['token' => ['required', 'string']]);
        $request->user()->deviceTokens()->where('token', $request->input('token'))->delete();

        return response()->json(['message' => 'ok']);
    }
}
