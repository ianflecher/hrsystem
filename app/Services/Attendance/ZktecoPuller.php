<?php

namespace App\Services\Attendance;

use Rats\Zkteco\Lib\ZKTeco;
use RuntimeException;

/**
 * Fetches punches from a ZKTeco scanner over the office network.
 *
 * The device listens on UDP 4370 and speaks a binary protocol; rats/zkteco
 * handles that. What matters here is that a failure to reach it is reported
 * plainly rather than left as an exception from three layers down, because the
 * usual causes are mundane - the device is off, on another subnet, or its IP
 * changed - and HR needs to be told which.
 *
 * NOT VERIFIED AGAINST HARDWARE. This was written without a device to test
 * against, so the first run on the real network is the real test. The file
 * import exists so attendance is never blocked on this working.
 */
class ZktecoPuller
{
    public function __construct(
        private readonly string $host,
        private readonly int $port = 4370,
    ) {
    }

    public static function fromConfig(): self
    {
        $host = config('attendance.zkteco.host');

        if (! $host) {
            throw new RuntimeException(
                'No scanner address configured. Set ZKTECO_HOST in .env to the device IP.'
            );
        }

        return new self($host, (int) config('attendance.zkteco.port', 4370));
    }

    /**
     * @return array<int, array{biometric_id: string, timestamp: string}>
     */
    public function punches(): array
    {
        if (! extension_loaded('sockets')) {
            throw new RuntimeException('Enable the PHP sockets extension to connect to the scanner, or upload an export.');
        }

        $device = new ZKTeco($this->host, $this->port);

        if (! $device->connect()) {
            throw new RuntimeException(
                "Could not reach the scanner at {$this->host}:{$this->port}. ".
                'Check it is switched on, on the same network as this server, and still at that address.'
            );
        }

        try {
            // Reading is quicker with the keypad disabled, and it stops a scan
            // landing halfway through the transfer.
            $device->disableDevice();
            $raw = $device->getAttendance();
        } finally {
            try {
                $device->enableDevice();
            } finally {
                $device->disconnect();
            }
        }

        if (! is_array($raw)) {
            throw new RuntimeException('The scanner did not return attendance records. Try again or upload an export.');
        }

        $punches = [];

        foreach ($raw as $row) {
            // The device reports the enrolment number as 'id'; 'uid' is its own
            // internal row number and is not stable across a device reset.
            $bio = (string) ($row['id'] ?? '');
            $stamp = $row['timestamp'] ?? null;

            if ($bio === '' || ! $stamp) {
                continue;
            }

            $punches[] = ['biometric_id' => $bio, 'timestamp' => $stamp];
        }

        return $punches;
    }
}
