<?php

namespace App\Support;

class LockerRoomParser
{
    /**
     * Parse locker room tokens such as "1-8", "CH", or "BR" into a unique list.
     *
     * @return array<int, string>
     */
    public static function parse(string $value): array
    {
        $value = trim($value);

        if ($value === '') {
            return [];
        }

        $parts = preg_split('/\s*[\/,]\s*|\s+/', $value) ?: [];
        $rooms = [];
        $seen = [];

        foreach ($parts as $part) {
            $token = trim($part);

            if ($token === '') {
                continue;
            }

            if (preg_match('/^(\d+)\s*-\s*(\d+)$/', $token, $matches)) {
                $start = (int) $matches[1];
                $end = (int) $matches[2];

                if ($start > $end) {
                    [$start, $end] = [$end, $start];
                }

                for ($i = $start; $i <= $end; $i++) {
                    $room = (string) $i;
                    if (!isset($seen[$room])) {
                        $seen[$room] = true;
                        $rooms[] = $room;
                    }
                }

                continue;
            }

            if (preg_match('/^[a-z0-9]+$/i', $token)) {
                $room = ctype_alpha($token) ? strtoupper($token) : $token;
                if (!isset($seen[$room])) {
                    $seen[$room] = true;
                    $rooms[] = $room;
                }
            }
        }

        return $rooms;
    }

    /**
     * Extract locker rooms from a title string containing parentheses.
     *
     * @return array<int, string>
     */
    public static function extractFromTitle(string $title): array
    {
        if (!preg_match_all('/\(([^)]+)\)/', $title, $matches)) {
            return [];
        }

        $rooms = [];
        $seen = [];

        foreach ($matches[1] as $match) {
            foreach (self::parse($match) as $room) {
                if (!isset($seen[$room])) {
                    $seen[$room] = true;
                    $rooms[] = $room;
                }
            }
        }

        return $rooms;
    }
}
