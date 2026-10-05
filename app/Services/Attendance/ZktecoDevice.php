<?php

namespace App\Services\Attendance;

use Rats\Zkteco\Lib\Helper\Util;
use Rats\Zkteco\Lib\ZKTeco;
use RuntimeException;

/**
 * A ZKTeco connection that can answer the device's COM key.
 *
 * ZKTeco devices can be given a communication key (Menu > Comm > Security >
 * Comm Key). When one is set, the device replies to a connection attempt with
 * CMD_ACK_UNAUTH and ignores everything afterwards until the client proves it
 * knows the key by sending CMD_AUTH.
 *
 * rats/zkteco does not do this. It has no notion of a COM key at all, and
 * worse, its checkValid() counts CMD_ACK_UNAUTH as success - there is a TODO
 * in the vendor source admitting it. So against a device with a key set, the
 * library reports a healthy connection and then every command comes back
 * empty. The pull would say it worked and bring in nothing, which reads on the
 * attendance screen as "nobody clocked in today" rather than as a failure.
 * That is the worst way for this to break: a silent zero looks like data.
 *
 * Extending rather than patching the vendor file, which composer would
 * overwrite on the next update.
 *
 * NOT VERIFIED AGAINST HARDWARE. The encoding below is the documented ZK
 * algorithm, but nobody here has a device with a key set to try it on, so the
 * first real connection is the real test. If it fails, the fallback is to set
 * the device's COM key to 0, which turns the handshake off.
 */
class ZktecoDevice extends ZKTeco
{
    /** Not in the vendor's Util, which stops at CMD_CHANGE_SPEED. */
    private const CMD_AUTH = 1102;

    public function __construct(
        string $ip,
        int $port = 4370,
        private readonly ?string $commKey = null,
        int $timeoutSeconds = 5,
    ) {
        parent::__construct($ip, $port);

        // Short for the handshake: a device that is going to answer does so in
        // milliseconds, and a minute of waiting only ever means HR staring at a
        // frozen screen before being told it failed.
        $this->setReadTimeout($timeoutSeconds);
    }

    /**
     * How long to wait for each reply.
     *
     * Two different jobs want two different answers. Connecting should give up
     * fast; reading the log should not. A 107,000-record transfer is hundreds
     * of replies, and cutting each one off at five seconds did not raise an
     * error - it made the read come back as an empty array, which is
     * indistinguishable from a device with no punches on it. That cost an
     * afternoon of looking in the wrong place.
     */
    public function setReadTimeout(int $seconds): void
    {
        socket_set_option($this->_zkclient, SOL_SOCKET, SO_RCVTIMEO,
            ['sec' => max(1, $seconds), 'usec' => 0]);
    }

    /**
     * Connects, and authenticates if the device asks us to.
     *
     * @throws RuntimeException when the device wants a key we do not have, or
     *                          the one we have is wrong. Both are worth saying
     *                          out loud rather than returning false, because
     *                          "wrong COM key" is a five-second fix and
     *                          "could not connect" sends somebody to the
     *                          network cupboard.
     */
    public function connect(): bool
    {
        if (! parent::connect()) {
            return false;
        }

        if (! $this->deviceWantsAKey()) {
            return true;
        }

        if ($this->commKey === null || $this->commKey === '') {
            throw new RuntimeException(
                'The scanner is asking for its COM key and none is set. '
                .'Put the device key in ZKTECO_COMM_KEY, or set the key to 0 on the '
                .'device itself under Menu > Comm > Security.'
            );
        }

        if (! $this->authenticate()) {
            throw new RuntimeException(
                'The scanner refused the COM key. Check it against the device under '
                .'Menu > Comm > Security > Comm Key.'
            );
        }

        return true;
    }

    /** Did the last reply say CMD_ACK_UNAUTH rather than CMD_ACK_OK? */
    private function deviceWantsAKey(): bool
    {
        if (strlen((string) $this->_data_recv) < 8) {
            return false;
        }

        $u = unpack('H2h1/H2h2', substr($this->_data_recv, 0, 8));

        return hexdec($u['h2'].$u['h1']) === Util::CMD_ACK_UNAUTH;
    }

    private function authenticate(): bool
    {
        $u = unpack('H2h1/H2h2/H2h3/H2h4/H2h5/H2h6/H2h7/H2h8', substr($this->_data_recv, 0, 8));
        $replyId = hexdec($u['h8'].$u['h7']);

        $buf = Util::createHeader(
            self::CMD_AUTH, 0, $this->_session_id, $replyId,
            self::encodeKey((int) $this->commKey, $this->_session_id),
        );

        socket_sendto($this->_zkclient, $buf, strlen($buf), 0, $this->_ip, $this->_port);

        try {
            @socket_recvfrom($this->_zkclient, $this->_data_recv, 1024, 0, $this->_ip, $this->_port);
        } catch (\Throwable) {
            return false;
        }

        if (strlen((string) $this->_data_recv) < 8) {
            return false;
        }

        $u = unpack('H2h1/H2h2', substr($this->_data_recv, 0, 8));

        // Not Util::checkValid(), which would accept the refusal as success.
        return hexdec($u['h2'].$u['h1']) === Util::CMD_ACK_OK;
    }

    /**
     * The COM key as the device expects it on the wire.
     *
     * ZK's own scheme: the key's bits are walked into an integer, the session
     * id is added so a captured packet cannot be replayed against another
     * session, the four bytes are XORed against "ZKSO", the halves are
     * swapped, and three of the bytes are XORed against a tick count.
     */
    private static function encodeKey(int $key, int $sessionId, int $ticks = 50): string
    {
        $k = 0;

        for ($i = 0; $i < 32; $i++) {
            $k = ($key & (1 << $i)) ? (($k << 1) | 1) : ($k << 1);
            $k &= 0xFFFFFFFF;
        }

        $k = ($k + $sessionId) & 0xFFFFFFFF;

        $b = unpack('C4', pack('V', $k));

        $x = pack('C4',
            $b[1] ^ ord('Z'),
            $b[2] ^ ord('K'),
            $b[3] ^ ord('S'),
            $b[4] ^ ord('O'),
        );

        // Swap the two 16-bit halves.
        $h = unpack('v2', $x);
        $x = pack('v2', $h[2], $h[1]);

        $b = unpack('C4', $x);
        $tick = $ticks & 0xFF;

        // The third byte is the tick itself, not the third byte of the key.
        // Getting that wrong produces a packet the device rejects for a
        // reason it cannot tell you apart from a wrong key, which is how it
        // read as "the scanner refused the COM key" when the key was right.
        return pack('C4', $b[1] ^ $tick, $b[2] ^ $tick, $tick, $b[4] ^ $tick);
    }

    /**
     * The attendance log, decoded for this device's record format.
     *
     * rats/zkteco reads 40-byte records at fixed offsets. This MB560-VL
     * (firmware 6.60) writes 49-byte records, so the library read every
     * timestamp from the wrong four bytes and produced dates from 2000 to
     * 2133 - and different totals on every pull, as the misalignment drifted.
     *
     * The layout was measured, not assumed: three punches known from ZKTime
     * were located in the raw stream, each badge sitting 25 bytes before its
     * timestamp and the records spaced a whole multiple of 49 apart. A
     * 12-byte header precedes them.
     *
     *   bytes 0-1   internal uid
     *   bytes 2-25  badge number, null padded
     *   byte  26    status
     *   bytes 27-30 timestamp
     *   byte  31    punch type
     *
     * The record width is still checked rather than trusted: whichever of 49
     * or 40 produces believable dates for the first records is used, so a
     * device on the older format is not silently misread the same way.
     *
     * @return list<array{biometric_id: string, timestamp: string}>
     */
    /**
     * What the scanner holds, from its own counter (CMD_GET_FREE_SIZES): people,
     * fingerprints and attendance records. Null when it does not say.
     *
     * @return array{people: int, fingerprints: int, records: int}|null
     */
    public function counts(): ?array
    {
        $this->_command(50, '');
        $body = substr((string) $this->_data_recv, 8, 80);
        if (strlen($body) < 40) {
            return null;
        }
        $v = array_values(unpack('V*', $body));

        return ['people' => (int) $v[4], 'fingerprints' => (int) $v[6], 'records' => (int) $v[8]];
    }

    public function readAttendance(): array
    {
        // A cleared log sends nothing, which looks just like a dropped download:
        // ask first, and an empty log is simply no punches.
        if (($this->counts()['records'] ?? null) === 0) {
            return [];
        }
        $this->_command(Util::CMD_ATT_LOG_RRQ, '', Util::COMMAND_TYPE_DATA);

        // Three silent waits, not the library's ten: at sixty seconds each a
        // stalled download used to hold the sync - and the keypad - for ten
        // minutes or more before giving up.
        return self::decodeLog((string) Util::recData($this, 3));
    }

    /**
     * The raw attendance stream into punches. Separate from the read so it can
     * be tested against real bytes without a device on the network.
     *
     * @return list<array{biometric_id: string, timestamp: string}>
     */
    public static function decodeLog(string $raw): array
    {
        // Nothing, or less than one record: the download timed out or was cut
        // off - not a format problem, and not worth sending anyone to check one.
        if (strlen($raw) < 12 + 40) {
            throw new \RuntimeException(
                'The scanner did not send its attendance log in time - the download came back empty, usually a weak Wi-Fi connection on the biometric scanner. '
                .'To check, open Command Prompt (cmd) and type: ping 192.168.150.250. '
                .'If it replies, sync by hand now - paste this into cmd: cd /d D:\\GitHub\\hris && C:\\xampp\\php\\php.exe artisan attendance:sync --full . '
                .'If it says "Request timed out" or "Destination host unreachable", the scanner\'s Wi-Fi needs to be fixed. '
                .'If it keeps failing even with a reply, restart the scanner and sync again.'
            );
        }

        // This device's 49-byte records, read one by one and each one checked.
        // The log comes over UDP in pieces, and a piece that never arrives
        // shifts every record after it: read blindly, those decode to dates
        // from nowhere. A record that does not look like a punch is skipped
        // byte by byte until the records line up again, so one lost piece
        // costs only the punches in it - the next sync brings those back.
        $punches = self::decodeChecked($raw);
        if ($punches !== null) {
            return $punches;
        }

        foreach ([[10, 40, 4, 29]] as [$header, $width, $badgeAt, $timeAt]) {
            if (! self::looksRight($raw, $header, $width, $timeAt)) {
                continue;
            }

            $punches = [];

            for ($o = $header; $o + $width <= strlen($raw); $o += $width) {
                $badge = rtrim(substr($raw, $o + $badgeAt, 24), "\0");
                $when = self::decodeTime(unpack('V', substr($raw, $o + $timeAt, 4))[1]);

                // An empty slot decodes to the device's epoch; it is not a punch.
                if ($badge === '' || str_starts_with($when, '2000-')) {
                    continue;
                }

                // Byte 31 on this format is the key pressed: 0 check in, 1
                // check out, 2 break out, 3 break in, 4 and 5 the second break.
                $punches[] = ['biometric_id' => $badge, 'timestamp' => $when]
                    + ($width === 49 ? ['state' => ord($raw[$o + 31])] : []);
            }

            return $punches;
        }

        throw new \RuntimeException(
            'The scanner returned attendance in a format this does not recognise. '
            .'Nothing was imported rather than risk importing wrong dates.'
        );
    }

    /**
     * The 49-byte layout, record by record, keeping only records that are
     * plainly punches. Null when the stream is not this layout at all.
     *
     * @return list<array{biometric_id: string, timestamp: string, state: int}>|null
     */
    private static function decodeChecked(string $raw): ?array
    {
        $width = 49;
        $length = strlen($raw);
        $punches = [];
        $skipped = 0;
        $o = 12;

        while ($o + $width <= $length) {
            if (self::isRecord($raw, $o) || self::isEmptyRecord($raw, $o)) {
                if (self::isRecord($raw, $o)) {
                    $punches[] = [
                        'biometric_id' => rtrim(substr($raw, $o + 2, 24), "\0"),
                        'timestamp' => self::decodeTime(unpack('V', substr($raw, $o + 27, 4))[1]),
                        'state' => ord($raw[$o + 31]),
                    ];
                }
                $o += $width;
                continue;
            }

            // Out of step: find where two records in a row read true again.
            $o++;
            $skipped++;
            while ($o + 2 * $width <= $length && ! (self::isRecord($raw, $o) && self::isRecord($raw, $o + $width))) {
                $o++;
                $skipped++;
            }
            if ($o + 2 * $width > $length) {
                break;
            }
        }

        // Mostly unreadable is a different format, or a broken download -
        // not something to import from.
        if (! $punches || $skipped > $length / 4) {
            return null;
        }

        return $punches;
    }

    /** A badge of letters and digits, a date somebody could punch, a key 0-5. */
    private static function isRecord(string $raw, int $o): bool
    {
        $badge = substr($raw, $o + 2, 24);
        if (! preg_match('/^[0-9A-Za-z]{1,24}\0*$/', $badge)) {
            return false;
        }
        if (ord($raw[$o + 31]) > 5) {
            return false;
        }
        $year = (int) substr(self::decodeTime(unpack('V', substr($raw, $o + 27, 4))[1]), 0, 4);

        return $year >= 2015 && $year <= (int) date('Y') + 1;
    }

    /** A slot the device keeps empty: no badge and its epoch for a date. */
    private static function isEmptyRecord(string $raw, int $o): bool
    {
        return trim(substr($raw, $o + 2, 24), "\0") === ''
            && str_starts_with(self::decodeTime(unpack('V', substr($raw, $o + 27, 4))[1]), '2000-');
    }

    /** Do the first few records decode to a date anyone could have punched? */
    private static function looksRight(string $raw, int $header, int $width, int $timeAt): bool
    {
        $checked = 0;
        $good = 0;

        for ($o = $header; $o + $width <= strlen($raw) && $checked < 20; $o += $width) {
            $year = (int) substr(self::decodeTime(unpack('V', substr($raw, $o + $timeAt, 4))[1]), 0, 4);
            $checked++;

            if ($year >= 2015 && $year <= (int) date('Y') + 1) {
                $good++;
            }
        }

        return $checked > 0 && $good >= $checked * 0.8;
    }

    /**
     * The device's packed time, in integer arithmetic.
     *
     * The library's version divides with floats and relies on PHP truncating
     * them back, which it now warns about - and float division is exactly the
     * place a punch could land a second or a day off without anyone noticing.
     */
    private static function decodeTime(int $t): string
    {
        $second = $t % 60; $t = intdiv($t, 60);
        $minute = $t % 60; $t = intdiv($t, 60);
        $hour = $t % 24; $t = intdiv($t, 24);
        $day = $t % 31 + 1; $t = intdiv($t, 31);
        $month = $t % 12 + 1; $t = intdiv($t, 12);
        $year = $t + 2000;

        return sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second);
    }
}
