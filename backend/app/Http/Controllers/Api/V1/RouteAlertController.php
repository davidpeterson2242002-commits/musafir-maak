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

    /**
     * POST /route-alerts {origin_city_id, destination_city_id, date?, kind?: trips|reopen, role?: passenger|driver}
     * kind=reopen: "tell me when this closed route opens" — allowed on closed routes and cities.
     */
    public function store(Request $request): RouteAlertResource
    {
        $reopen = $request->input('kind') === 'reopen';
        $city = $reopen ? Rule::exists('cities', 'id') : Rule::exists('cities', 'id')->where('enabled', true);
        $data = $request->validate([
            'origin_city_id' => ['required', 'integer', $city],
            'destination_city_id' => ['required', 'integer', 'different:origin_city_id', $city],
            'date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:'.now(config('app.display_timezone'))->toDateString()],
            'kind' => ['nullable', Rule::in(['trips', 'reopen'])],
            'role' => ['nullable', Rule::in(['passenger', 'driver'])],
        ], [], ['origin_city_id' => 'مدينة الانطلاق', 'destination_city_id' => 'مدينة الوصول', 'date' => 'التاريخ']);

        $user = $request->user();
        $kind = $reopen ? 'reopen' : 'trips';
        $key = ['origin_city_id' => $data['origin_city_id'], 'destination_city_id' => $data['destination_city_id'], 'kind' => $kind,
            'date' => $reopen ? null : ($data['date'] ?? null)];

        if (! $user->routeAlerts()->where($key)->where('active', true)->exists()
            && $user->routeAlerts()->where('active', true)->count() >= settings()->int('route_alerts.max_per_user')) {
            throw new BusinessRuleException('وصلت للحد الأقصى من التنبيهات. احذف تنبيهاً قديماً أولاً.', 'too_many_alerts');
        }

        $alert = $user->routeAlerts()->updateOrCreate($key, [
            'active' => true,
            'role' => $reopen ? ($data['role'] ?? ($user->driverProfile ? 'driver' : 'passenger')) : null,
        ]);

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
