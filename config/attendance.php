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

        /*
         * The device's COM key, if one is set on it: Menu > Comm > Security >
         * Comm Key. A device with a key refuses every command until the client
         * proves it knows it, and it does so quietly - the connection looks
         * healthy and the punches simply come back empty, which reads as
         * "nobody clocked in" rather than as a failure.
         *
         * Leave it unset when the device's key is 0, which is the default and
         * means no key at all.
         */
        'comm_key' => env('ZKTECO_COMM_KEY'),
    ],

    /*
     * ZKTime / Attendance Management Program stores downloaded logs in an
     * Access database. When direct device pull does not match a scanner model,
     * HR can download logs in ZKTime first, then HRIS imports this database.
     */
    'zktime' => [
        'mdb_path' => env('ZKTIME_MDB_PATH', 'C:\\Program Files (x86)\\ZKTeco\\att2000.mdb'),
    ],

];
