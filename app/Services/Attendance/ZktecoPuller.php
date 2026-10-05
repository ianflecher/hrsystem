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
     * One retry on a fresh connection: a download cut off halfway, or a
     * device still finishing the last session, usually answers the second
     * time. Two failures in a row are reported as they are.
     *
     * @return array<int, array{biometric_id: string, timestamp: string}>
     */
    public function punches(): array
    {
        try {
            return $this->pullOnce();
        } catch (RuntimeException $e) {
            // Only a quick refusal is worth a second try. A wrong COM key
            // will not fix itself, and a download that has already stalled
            // for minutes only stalls again - retrying it doubled the wait.
            if (! str_contains($e->getMessage(), 'did not answer')) {
                throw $e;
            }
            sleep(5);

            return $this->pullOnce();
        }
    }

    private function pullOnce(): array
    {
        if (! extension_loaded('sockets')) {
            throw new RuntimeException('Enable the PHP sockets extension to connect to the scanner, or upload an export.');
        }

        $device = new ZktecoDevice($this->host, $this->port, $this->commKey);

        if (! $device->connect()) {
            throw new RuntimeException(
                // "did not answer" is what the retry above looks for.
                "The biometric scanner did not answer - it has no Wi-Fi connection. ".
                "To check, open Command Prompt (cmd) and type: ping {$this->host}. ".
                'If it replies, the connection is back and the next sync will work again - or sync by hand now: '.
                'paste this into cmd: cd /d D:\\GitHub\\hris && C:\\xampp\\php\\php.exe artisan attendance:sync --full . '.
                "If it says \"Request timed out\" or \"Destination host unreachable\", the scanner's Wi-Fi needs to be fixed."
            );
        }

        try {
            // The keypad stays usable while the log is read. Locking it made a
            // stalled download stop people clocking in for as long as the
            // stall lasted; a punch made mid-read is kept by the device and
            // arrives with the next sync.

            // Sixty seconds of silence per packet. This device pauses mid-way
            // through a large log - ten and then thirty both cut the download
            // off, and only sixty has brought all hundred thousand punches
            // home (in about two minutes). A device that has truly stopped
            // still ends the run, only slowly.
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
