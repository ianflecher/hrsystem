<?php

return [

    /*
     * The biometric scanner.
     *
     * The device needs a fixed address on the office network - if it is on DHCP
     * its IP will change and the pull will simply stop working one day with no
     * obvious cause. Reserve it on the router, or set a static IP on the device.
     *
     * Leave ZKTECO_HOST unset and the pull is disabled; attendance can still be
     * brought in by uploading an export, which is the fallback.
     */
    'zkteco' => [
        'host' => env('ZKTECO_HOST'),
        'port' => env('ZKTECO_PORT', 4370),
    ],

];
