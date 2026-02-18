<?php

namespace App\Support;

class OrganizationLogoMapper
{
    /**
     * Map of organization names/keywords to their logo filenames.
     * Logo files should be placed in public/images/logos/
     */
    private static array $logoMap = [
        // Spokane JR Chiefs / SAYHA
        'jr chiefs' => 'sayha_logo.png',
        'jr. chiefs' => 'sayha_logo.png',
        'sayha' => 'sayha_logo.png',

        // Lilac City Figure Skating Club
        'lcfsc' => 'lcfsc_logo.png',
        'lilac city' => 'lcfsc_logo.png',
        'figure skating' => 'lcfsc_logo.png',

        // Old Timers Hockey Association
        'old timers' => 'old-timers_logo.png',
        'the jets' => 'jets_logo.png',
        'jets' => 'jets_logo.png',
        'the grinders' => 'grinders_logo.png',
        'grinders' => 'grinders_logo.png',

        // Spokane Braves
        'spokane braves' => 'braves_logo.png',
        'braves' => 'braves_logo.png',

        // Gonzaga University
        'gonzaga university' => 'gonzaga_logo.png',
        'gonzaga' => 'gonzaga_logo.png',

        // Eagles Ice Arena
        'eagles' => 'eagles_logo.png',
        'eagles ice arena' => 'eagles_logo.png',
    ];

    /**
     * Priority order for matching (more specific matches first)
     */
    private static array $priorityOrder = [
        'eagles ice arena',
        'gonzaga university',
        'jr. chiefs',
        'jr chiefs',
        'lilac city',
        'spokane braves',
        'old timers',
        'the jets',
        'the grinders',
        'figure skating',
        'lcfsc',
        'sayha',
        'braves',
        'gonzaga',
        'eagles',
        'jets',
        'grinders',
    ];

    /**
     * Extract organization/customer from customer field or event title and return logo filename
     *
     * @param string|null $customer Customer name/field from event data
     * @param string|null $title Event title/description (fallback)
     * @return string|null Logo filename or null if no match found
     */
    public static function getLogoFromCustomer(?string $customer, ?string $title = null): ?string
    {
        // Try customer field first (primary source)
        if ($customer) {
            $normalized = strtolower(trim($customer));
            foreach (self::$priorityOrder as $keyword) {
                if (str_contains($normalized, $keyword)) {
                    return self::$logoMap[$keyword] ?? null;
                }
            }
        }

        // Fallback to title if customer didn't match
        if ($title) {
            $normalized = strtolower(trim($title));
            foreach (self::$priorityOrder as $keyword) {
                if (str_contains($normalized, $keyword)) {
                    return self::$logoMap[$keyword] ?? null;
                }
            }
        }

        return null;
    }

    /**
     * Extract organization/customer from event title and return logo filename
     * @deprecated Use getLogoFromCustomer() instead
     *
     * @param string $title Event title/description
     * @return string|null Logo filename or null if no match found
     */
    public static function getLogoFromTitle(string $title): ?string
    {
        return self::getLogoFromCustomer(null, $title);
    }

    /**
     * Get the full asset path for a logo based on customer and title
     *
     * @param string|null $customer Customer name/field from event data
     * @param string|null $title Event title/description (fallback)
     * @return string|null Full asset URL or null if no match
     */
    public static function getLogoPath(?string $customer, ?string $title = null): ?string
    {
        $filename = self::getLogoFromCustomer($customer, $title);

        return $filename ? asset('images/logos/' . $filename) : null;
    }

    /**
     * Get logo path from title (convenience method for backward compatibility)
     * @deprecated Use getLogoPath() with customer parameter instead
     *
     * @param string $title Event title/description
     * @return string|null Full asset URL or null if no match
     */
    public static function getLogoPathFromTitle(string $title): ?string
    {
        return self::getLogoPath(null, $title);
    }

    /**
     * Get organization name from customer field or title
     *
     * @param string|null $customer Customer name/field from event data
     * @param string|null $title Event title/description (fallback)
     * @return string|null Organization name or null if no match
     */
    public static function getOrganizationName(?string $customer, ?string $title = null): ?string
    {
        $nameMap = [
            'jr. chiefs' => 'Spokane JR Chiefs (SAYHA)',
            'jr chiefs' => 'Spokane JR Chiefs (SAYHA)',
            'sayha' => 'Spokane JR Chiefs (SAYHA)',
            'lcfsc' => 'Lilac City Figure Skating Club',
            'lilac city' => 'Lilac City Figure Skating Club',
            'figure skating' => 'Lilac City Figure Skating Club',
            'old timers' => 'Old Timers Hockey Association',
            'the jets' => 'The Jets (Old Timers)',
            'jets' => 'The Jets (Old Timers)',
            'the grinders' => 'The Grinders (Old Timers)',
            'grinders' => 'The Grinders (Old Timers)',
            'spokane braves' => 'Spokane Braves',
            'braves' => 'Spokane Braves',
            'gonzaga university' => 'Gonzaga University',
            'gonzaga' => 'Gonzaga University',
            'eagles ice arena' => 'Eagles Ice Arena',
            'eagles' => 'Eagles Ice Arena',
        ];

        // Try customer field first
        if ($customer) {
            $normalized = strtolower(trim($customer));
            foreach (self::$priorityOrder as $keyword) {
                if (str_contains($normalized, $keyword)) {
                    return $nameMap[$keyword] ?? null;
                }
            }
        }

        // Fallback to title
        if ($title) {
            $normalized = strtolower(trim($title));
            foreach (self::$priorityOrder as $keyword) {
                if (str_contains($normalized, $keyword)) {
                    return $nameMap[$keyword] ?? null;
                }
            }
        }

        return null;
    }
}
