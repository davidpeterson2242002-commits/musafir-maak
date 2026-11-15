<?php

use App\Support\LegalTexts;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Swaps the launch drafts in for the old placeholders, never over text an admin already wrote. */
    public function up(): void
    {
        $drafts = [
            'content.terms' => LegalTexts::TERMS,
            'content.privacy' => LegalTexts::PRIVACY,
            'content.driver_terms' => LegalTexts::DRIVER_TERMS,
        ];

        foreach ($drafts as $key => $text) {
            $current = DB::table('settings')->where('key', $key)->value('value');
            if ($current === null) {
                continue; // settings:sync seeds it from the registry default
            }
            if (str_contains((string) json_decode($current), 'نص مبدئي يُستبدل قبل الإطلاق')) {
                DB::table('settings')->where('key', $key)->update(['value' => json_encode($text, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]);
            }
        }

        Cache::forget(\App\Support\Settings::CACHE_KEY);
    }

    public function down(): void {}
};
