<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\State;
use App\Models\TravelRoute;
use Illuminate\Database\Seeder;

/**
 * Sudan's 18 states and main cities, plus the launch routes.
 * Khartoum, Omdurman and Bahri are one city ("الخرطوم") so a search for
 * Khartoum → Madani finds every trip from the capital; the exact area is a pickup point.
 * Idempotent: safe to re-run.
 */
class GeoSeeder extends Seeder
{
    public const STATES = [
        ['الخرطوم', 'Khartoum', [['الخرطوم', 'Khartoum']]],
        ['الجزيرة', 'Al Jazirah', [['ود مدني', 'Wad Madani'], ['الحصاحيصا', 'Al Hasahisa'], ['المناقل', 'Al Managil'], ['رفاعة', 'Rufaa']]],
        ['نهر النيل', 'River Nile', [['عطبرة', 'Atbara'], ['الدامر', 'Ed Damer'], ['شندي', 'Shendi'], ['بربر', 'Berber']]],
        ['البحر الأحمر', 'Red Sea', [['بورتسودان', 'Port Sudan'], ['سواكن', 'Suakin']]],
        ['كسلا', 'Kassala', [['كسلا', 'Kassala'], ['حلفا الجديدة', 'New Halfa']]],
        ['القضارف', 'Gedaref', [['القضارف', 'Gedaref']]],
        ['سنار', 'Sennar', [['سنار', 'Sennar'], ['سنجة', 'Singa']]],
        ['النيل الأبيض', 'White Nile', [['كوستي', 'Kosti'], ['ربك', 'Rabak'], ['الدويم', 'Ad Douiem']]],
        ['النيل الأزرق', 'Blue Nile', [['الدمازين', 'Ad Damazin'], ['الروصيرص', 'Ar Roseires']]],
        ['الشمالية', 'Northern', [['دنقلا', 'Dongola'], ['مروي', 'Merowe'], ['كريمة', 'Karima'], ['وادي حلفا', 'Wadi Halfa']]],
        ['شمال كردفان', 'North Kordofan', [['الأبيض', 'El Obeid'], ['أم روابة', 'Um Ruwaba']]],
        ['جنوب كردفان', 'South Kordofan', [['كادقلي', 'Kadugli'], ['الدلنج', 'Dilling']]],
        ['غرب كردفان', 'West Kordofan', [['الفولة', 'Al Fula'], ['النهود', 'En Nahud']]],
        ['شمال دارفور', 'North Darfur', [['الفاشر', 'El Fasher']]],
        ['جنوب دارفور', 'South Darfur', [['نيالا', 'Nyala']]],
        ['شرق دارفور', 'East Darfur', [['الضعين', 'Ed Daein']]],
        ['وسط دارفور', 'Central Darfur', [['زالنجي', 'Zalingei']]],
        ['غرب دارفور', 'West Darfur', [['الجنينة', 'El Geneina']]],
    ];

    /** [origin_en, destination_en, popular] — each is seeded in both directions. */
    public const ROUTES = [
        ['Khartoum', 'Wad Madani', true],
        ['Khartoum', 'Atbara', true],
        ['Khartoum', 'Port Sudan', true],
        ['Khartoum', 'Shendi', false],
        ['Khartoum', 'Kassala', false],
        ['Khartoum', 'Gedaref', false],
        ['Khartoum', 'Kosti', false],
        ['Khartoum', 'Sennar', false],
        ['Khartoum', 'Dongola', false],
        ['Khartoum', 'El Obeid', false],
        ['Atbara', 'Port Sudan', false],
        ['Kassala', 'Port Sudan', false],
        ['Wad Madani', 'Gedaref', false],
        ['Wad Madani', 'Sennar', false],
    ];

    public function run(): void
    {
        foreach (self::STATES as $stateSort => [$stateAr, $stateEn, $cities]) {
            $state = State::updateOrCreate(['name_en' => $stateEn], ['name_ar' => $stateAr, 'sort' => $stateSort]);

            foreach ($cities as $citySort => [$cityAr, $cityEn]) {
                City::updateOrCreate(
                    ['state_id' => $state->id, 'name_en' => $cityEn],
                    ['name_ar' => $cityAr, 'sort' => $citySort],
                );
            }
        }

        $ids = City::pluck('id', 'name_en');
        $sort = 0;

        foreach (self::ROUTES as [$from, $to, $popular]) {
            foreach ([[$from, $to], [$to, $from]] as [$a, $b]) {
                TravelRoute::firstOrCreate(
                    ['origin_city_id' => $ids[$a], 'destination_city_id' => $ids[$b]],
                    ['is_popular' => $popular, 'sort' => $sort++],
                );
            }
        }
    }
}
