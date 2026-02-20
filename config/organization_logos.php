<?php

return [
    /**
     * Mapping of external numeric customer IDs (from the events API) to a
     * display name that the existing OrganizationLogoMapper will match on.
     *
     * Update this file if new customer IDs should be recognized by the UI
     * when the payload only includes numeric `customer_id` values.
     */
    'customer_id_map' => [
        // id => organization display name
        3  => 'SAYHA',
        4  => 'Spokane Braves',
        6  => 'LCFSC',
        7  => 'Old Timers',
        8  => 'Eagles Ice Arena',
        9  => 'Gonzaga University',
        17 => 'Spokane Chiefs',
    ],
];
