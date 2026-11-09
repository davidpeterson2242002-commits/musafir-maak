<?php

namespace Tests\Unit;

use App\Services\ChatService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ChatMaskTest extends TestCase
{
    public static function masked(): array
    {
        return [
            ['كلمني على 0912345678'],
            ['رقمي +249 91 234 5678'],
            ['00249912345678 واتساب'],
            ['٠٩١٢٣٤٥٦٧٨'],
            ['091-234-5678'],
            ['0 9 1 2 3 4 5 6 7 8'],
            ['wa.me/249912345678'],
            ['https://chat.whatsapp.com/AbCdEf'],
            ['t.me/someone'],
        ];
    }

    #[DataProvider('masked')]
    public function test_masks_contact_details(string $text): void
    {
        [$out, $masked] = ChatService::mask($text);
        $this->assertTrue($masked, "Expected [$text] to be masked");
        $this->assertStringContainsString('رقم مخفي', $out);
        $this->assertDoesNotMatchRegularExpression('/\d{4}/', preg_replace('/[^\d]/', '', $out) === '' ? '' : $out);
    }

    public static function untouched(): array
    {
        return [
            ['السعر 15000 جنيه'],
            ['نتحرك الساعة 7:30 من موقف شروني'],
            ['معاي 2 شنط و 1 صغيرة'],
            ['التاريخ 2026-11-12'],
            ['المبلغ 1500000 جنيه'],
        ];
    }

    #[DataProvider('untouched')]
    public function test_leaves_normal_text(string $text): void
    {
        [$out, $masked] = ChatService::mask($text);
        $this->assertFalse($masked, "Did not expect [$text] to be masked");
        $this->assertSame($text, $out);
    }
}
