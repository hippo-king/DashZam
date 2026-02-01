<?php

namespace Tests\Unit;

use App\Support\LockerRoomParser;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LockerRoomParserTest extends TestCase
{
    #[DataProvider('parseCases')]
    public function test_parse_locker_rooms(string $input, array $expected): void
    {
        $this->assertSame($expected, LockerRoomParser::parse($input));
    }

    public static function parseCases(): array
    {
        return [
            'range' => ['1-8', ['1', '2', '3', '4', '5', '6', '7', '8']],
            'single alpha' => ['CH', ['CH']],
            'single alpha lowercase' => ['br', ['BR']],
            'mixed' => ['1-3, CH / BR', ['1', '2', '3', 'CH', 'BR']],
            'duplicates' => ['2, 2, 3', ['2', '3']],
        ];
    }
}
