<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\RouteAlertResource;
use App\Models\RouteAlert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/** Route alerts (P12). */
class RouteAlertController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return RouteAlertResource::collection(
            $request->user()->routeAlerts()->with(['origin', 'destination'])->latest()->get()
        );
    }

    /** POST /route-alerts {origin_city_id, destination_city_id, date?} */
    public function store(Request $request): RouteAlertResource
    {
        $data = $request->validate([
            'origin_city_id' => ['required', 'integer', Rule::exists('cities', 'id')->where('enabled', true)],
            'destination_city_id' => ['required', 'integer', 'different:origin_city_id', Rule::exists('cities', 'id')->where('enabled', true)],
            'date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:'.now(config('app.display_timezone'))->toDateString()],
        ], [], ['origin_city_id' => 'مدينة الانطلاق', 'destination_city_id' => 'مدينة الوصول', 'date' => 'التاريخ']);

        $user = $request->user();
        if ($user->routeAlerts()->where('active', true)->count() >= settings()->int('route_alerts.max_per_user')) {
            throw new BusinessRuleException('وصلت للحد الأقصى من التنبيهات. احذف تنبيهاً قديماً أولاً.', 'too_many_alerts');
        }

        $alert = $user->routeAlerts()->updateOrCreate(
            ['origin_city_id' => $data['origin_city_id'], 'destination_city_id' => $data['destination_city_id'], 'date' => $data['date'] ?? null],
            ['active' => true],
        );

        return new RouteAlertResource($alert->load(['origin', 'destination']));
    }

    /** DELETE /route-alerts/{alert} */
    public function destroy(Request $request, RouteAlert $alert): JsonResponse
    {
        abort_unless($alert->user_id === $request->user()->id, 404);
        $alert->delete();

        return response()->json(['message' => 'تم حذف التنبيه.']);
    }
}
