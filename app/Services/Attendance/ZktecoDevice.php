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

        // The library waits 60 seconds for a reply. A device that is going to
        // answer answers in milliseconds - it is on the same switch - so the
        // only thing a minute buys is HR staring at a frozen screen before
        // being told it did not work. Five seconds is generous.
        socket_set_option($this->_zkclient, SOL_SOCKET, SO_RCVTIMEO,
            ['sec' => max(1, $timeoutSeconds), 'usec' => 0]);
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

        return pack('C4', $b[1] ^ $tick, $b[2] ^ $tick, $b[3], $b[4] ^ $tick);
    }
}
