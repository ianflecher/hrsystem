<?php

namespace App\Services\Attendance;

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
        private readonly ?string $commKey = null,
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

        return new self(
            $host,
            (int) config('attendance.zkteco.port', 4370),
            config('attendance.zkteco.comm_key'),
        );
    }

    /**
     * @return array<int, array{biometric_id: string, timestamp: string}>
     */
    public function punches(): array
    {
        if (! extension_loaded('sockets')) {
            throw new RuntimeException('Enable the PHP sockets extension to connect to the scanner, or upload an export.');
        }

        $device = new ZktecoDevice($this->host, $this->port, $this->commKey);

        if (! $device->connect()) {
            throw new RuntimeException(
                "The scanner at {$this->host}:{$this->port} did not answer. ".
                'If it shows up on the network but stays silent, it is most likely set to '.
                'push rather than pull - check Menu > Comm > Cloud Server (ADMS) on the '.
                'device, and clear it to pull from here. Otherwise check it is switched on '.
                'and still at that address. Attendance can be uploaded from its export in '.
                'the meantime.'
            );
        }

        try {
            // Reading is quicker with the keypad disabled, and it stops a scan
            // landing halfway through the transfer.
            $device->disableDevice();

            // Patient now that we know it is there. The handshake was short on
            // purpose; the log read must not be, or it truncates silently and
            // returns nothing at all.
            $device->setReadTimeout(60);
            // Our own decoder: the library's misreads this device's records.
            $raw = $device->readAttendance();
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
            // The badge (enrolment number), not the device's internal uid, which
            // is not stable across a reset.
            $bio = (string) ($row['biometric_id'] ?? $row['id'] ?? '');
            $stamp = $row['timestamp'] ?? null;

            if ($bio === '' || ! $stamp) {
                continue;
            }

            // The key pressed goes along with it: dropping it here meant every
            // punch was placed by counting, whatever the person chose.
            $punches[] = ['biometric_id' => $bio, 'timestamp' => $stamp]
                + (isset($row['state']) ? ['state' => (int) $row['state']] : []);
        }

        return $punches;
    }
}
