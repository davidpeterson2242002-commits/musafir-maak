<?php

namespace Tests\Feature\Api;

use App\Models\City;
use App\Models\State;
use App\Models\TravelRoute;
use Tests\TestCase;

class GeoAndConfigTest extends TestCase
{
    public function test_cities_lists_only_available_cities(): void
    {
        $total = City::count();
        City::where('name_en', 'Suakin')->update(['enabled' => false]);
        State::where('name_en', 'North Darfur')->update(['enabled' => false]); // hides El Fasher

        $this->getJson('/api/v1/cities')
            ->assertOk()
            ->assertJsonCount($total - 2, 'data')
            ->assertJsonMissing(['name_en' => 'Suakin'])
            ->assertJsonMissing(['name_en' => 'El Fasher']);
    }

    public function test_popular_routes_are_the_launch_routes_both_ways(): void
    {
        $names = collect($this->getJson('/api/v1/routes?popular=1')->assertOk()->json('data'))
            ->map(fn ($r) => $r['origin']['name_en'].'→'.$r['destination']['name_en'])->sort()->values()->all();

        $this->assertSame([
            'Atbara→Khartoum', 'Khartoum→Atbara', 'Khartoum→Port Sudan', 'Khartoum→Wad Madani',
            'Port Sudan→Khartoum', 'Wad Madani→Khartoum',
        ], $names);
    }

    public function test_closed_route_shows_closure_message(): void
    {
        $route = TravelRoute::first();
        $route->update(['enabled' => false, 'closure_message' => 'الطريق مقفول بسبب الأمطار']);

        $data = collect($this->getJson('/api/v1/routes')->json('data'))->firstWhere('id', $route->id);
        $this->assertFalse($data['open']);
        $this->assertSame('الطريق مقفول بسبب الأمطار', $data['closure_message']);
    }

    public function test_route_is_closed_when_a_city_is_disabled(): void
    {
        $route = TravelRoute::first();
        $route->destination->update(['enabled' => false]);

        $data = collect($this->getJson('/api/v1/routes')->json('data'))->firstWhere('id', $route->id);
        $this->assertFalse($data['open']);
        $this->assertSame('هذا الطريق موقوف مؤقتاً', $data['closure_message']);
    }

    public function test_config_exposes_only_public_settings(): void
    {
        $response = $this->getJson('/api/v1/config')->assertOk();

        $this->assertArrayHasKey('content.prohibited_items', $response->json('settings'));
        $this->assertArrayHasKey('otp.resend_seconds', $response->json('settings'));
        $this->assertArrayNotHasKey('fees.model', $response->json('settings'));
        $this->assertArrayNotHasKey('settlement.balance_limit', $response->json('settings'));
    }
}
