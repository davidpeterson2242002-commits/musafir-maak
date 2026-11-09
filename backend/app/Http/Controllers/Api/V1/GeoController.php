<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\CityResource;
use App\Http\Resources\V1\RouteResource;
use App\Models\City;
use App\Models\TravelRoute;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GeoController extends Controller
{
    /** GET /api/v1/cities — enabled cities, grouped by the client by state. */
    public function cities(): AnonymousResourceCollection
    {
        $cities = City::available()
            ->with('state')
            ->join('states', 'states.id', '=', 'cities.state_id')
            ->orderBy('states.sort')->orderBy('cities.sort')->orderBy('cities.name_ar')
            ->select('cities.*')
            ->get();

        return CityResource::collection($cities);
    }

    /**
     * GET /api/v1/routes?popular=1 — admin-defined routes, including closed ones so
     * the app can show the closure message.
     */
    public function routes(Request $request): AnonymousResourceCollection
    {
        $routes = TravelRoute::query()
            ->with(['origin.state', 'destination.state'])
            ->when($request->boolean('popular'), fn ($q) => $q->where('is_popular', true)->where('enabled', true))
            ->orderByDesc('is_popular')->orderBy('sort')->orderBy('id')
            ->get();

        return RouteResource::collection($routes);
    }
}
