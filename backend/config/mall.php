<?php

return [
    'platform_domain' => env('MALL_PLATFORM_DOMAIN', 'platform.local'),

    'reserved_subdomains' => ['www', 'api', 'admin', 'mail', 'cdn', 'static', 'assets'],

    'shop_header' => 'X-Shop-Subdomain',
];
