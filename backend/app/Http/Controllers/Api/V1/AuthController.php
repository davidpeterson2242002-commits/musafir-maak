<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RequestOtpRequest;
use App\Http\Requests\Api\V1\VerifyOtpRequest;
use App\Http\Resources\V1\MeResource;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private OtpService $otp) {}

    /** POST /api/v1/auth/otp — send a code via WhatsApp. */
    public function requestOtp(RequestOtpRequest $request): JsonResponse
    {
        $phone = $request->validated('phone');
        $result = $this->otp->issue($phone, $request->ip());

        $payload = [
            'message' => 'تم إرسال رمز التحقق إلى واتساب.',
            'phone' => $phone,
            'resend_after' => $result['resend_after'],
            'expires_at' => $result['code']->expires_at->toIso8601String(),
        ];

        // Local development, or a number the admin listed as a test number
        // (setting otp.test_phones) while WhatsApp isn't connected.
        $localDebug = app()->environment('local') && config('services.otp.expose_in_response');
        if ($localDebug || $this->otp->isTestPhone($phone)) {
            $payload['debug_code'] = $result['plain'];
        }

        return response()->json($payload);
    }

    /** POST /api/v1/auth/verify — verify code, create user if new, return a token. */
    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $phone = $request->validated('phone');
        $this->otp->verify($phone, $request->validated('code'));

        $user = User::withTrashed()->where('phone', $phone)->first();

        if ($user?->trashed()) {
            // A deleted account signing up again starts fresh with the same phone.
            $user->restore();
            $user->forceFill([
                'name' => null, 'email' => null, 'gender' => null, 'city_id' => null,
                'photo_path' => null, 'terms_accepted_at' => null, 'whatsapp_phone' => null,
            ])->save();
        }

        $isNew = $user === null;
        $user ??= User::create(['phone' => $phone]);

        if ($user->isBanned()) {
            return response()->json(['message' => 'تم حظر هذا الحساب. للاستفسار تواصل مع الدعم.', 'code' => 'account_banned'], 403);
        }

        $user->forceFill(['phone_verified_at' => $user->phone_verified_at ?? now(), 'last_seen_at' => now()])->save();

        $token = $user->createToken($request->validated('device_name') ?: 'mobile')->plainTextToken;

        return response()->json([
            'token' => $token,
            'is_new' => $isNew,
            'user' => new MeResource($user->load(['city.state', 'driverProfile'])),
        ]);
    }

    /** POST /api/v1/auth/logout — revoke the current token. */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'تم تسجيل الخروج.']);
    }
}
