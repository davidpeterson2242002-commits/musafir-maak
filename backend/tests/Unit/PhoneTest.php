<?php

namespace Tests\Unit;

use App\Support\Phone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhoneTest extends TestCase
{
    public static function valid(): array
    {
        return [
            ['0912345678', '+249912345678'],
            ['912345678', '+249912345678'],
            ['+249 91 234 5678', '+249912345678'],
            ['249912345678', '+249912345678'],
            ['00249912345678', '+249912345678'],
            ['0123-456-789', '+249123456789'],
            ['٠٩١٢٣٤٥٦٧٨', '+249912345678'],
        ];
    }

    #[DataProvider('valid')]
    public function test_normalizes_sudanese_numbers(string $input, string $expected): void
    {
        $this->assertSame($expected, Phone::normalize($input));
    }

    public function test_rejects_invalid_numbers(): void
    {
        foreach (['', '12345', '0812345678', '+20123456789', '09123456789', 'abc'] as $bad) {
            $this->assertNull(Phone::normalize($bad), "Expected [$bad] to be invalid");
        }
    }
}
