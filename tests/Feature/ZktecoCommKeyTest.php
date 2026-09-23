<?php

namespace Tests\Feature;

use App\Services\Attendance\ZktecoDevice;
use App\Services\Attendance\ZktecoPuller;
use ReflectionMethod;
use Tests\TestCase;

/**
 * The COM key handshake.
 *
 * A ZKTeco device can be given a communication key, and one that has it set
 * refuses every command until the client proves it knows it. rats/zkteco has
 * no notion of a COM key, and its checkValid() counts the refusal as success -
 * there is a TODO in the vendor source saying so. Against such a device the
 * pull reports a healthy connection and brings back nothing, which reads on
 * the attendance screen as nobody having clocked in.
 *
 * The encoding cannot be tested against hardware here, so these pin the
 * algorithm's properties instead: it depends on the key, it depends on the
 * session, and it is always the four bytes the protocol expects.
 */
class ZktecoCommKeyTest extends TestCase
{
    private function encode(int $key, int $session, int $ticks = 50): string
    {
        $m = new ReflectionMethod(ZktecoDevice::class, 'encodeKey');
        $m->setAccessible(true);

        return $m->invoke(null, $key, $session, $ticks);
    }

    public function test_the_encoded_key_is_four_bytes(): void
    {
        foreach ([0, 1, 1234, 999999, 0xFFFF] as $key) {
            $this->assertSame(4, strlen($this->encode($key, 1)),
                "a key of {$key} did not encode to four bytes");
        }
    }

    public function test_a_different_key_encodes_differently(): void
    {
        $this->assertNotSame($this->encode(1234, 7), $this->encode(5678, 7),
            'two different COM keys produced the same bytes, so the device could not tell them apart');
    }

    /**
     * The session id is mixed in so a captured packet cannot be replayed
     * against a later session.
     */
    public function test_the_same_key_encodes_differently_per_session(): void
    {
        $this->assertNotSame($this->encode(1234, 7), $this->encode(1234, 8),
            'the session was not mixed in, so one captured packet would work forever');
    }

    public function test_it_is_stable_for_the_same_key_and_session(): void
    {
        $this->assertSame($this->encode(1234, 7), $this->encode(1234, 7));
    }

    /** The puller reads the key from config rather than being told twice. */
    public function test_the_key_comes_from_configuration(): void
    {
        config(['attendance.zkteco.host' => '192.0.2.10']);
        config(['attendance.zkteco.port' => 4370]);
        config(['attendance.zkteco.comm_key' => '4321']);

        $puller = ZktecoPuller::fromConfig();

        $p = new \ReflectionProperty($puller, 'commKey');
        $p->setAccessible(true);

        $this->assertSame('4321', $p->getValue($puller));
    }

    public function test_no_host_is_still_refused_plainly(): void
    {
        config(['attendance.zkteco.host' => null]);

        $this->expectExceptionMessage('No scanner address configured');
        ZktecoPuller::fromConfig();
    }

    /**
     * The device is constructed with the key rather than the key being applied
     * afterwards, so there is no window where a command goes out unsigned.
     */
    public function test_the_device_takes_the_key_at_construction(): void
    {
        // The constructor opens a UDP socket, so there is nothing to build
        // without the extension. Skipped rather than quietly passing.
        if (! extension_loaded('sockets')) {
            $this->markTestSkipped('the PHP sockets extension is not enabled');
        }

        $device = new ZktecoDevice('192.0.2.10', 4370, '4321');

        $p = new \ReflectionProperty($device, 'commKey');
        $p->setAccessible(true);

        $this->assertSame('4321', $p->getValue($device));
        $this->assertSame('192.0.2.10', $device->_ip);
        $this->assertSame(4370, $device->_port);
    }
}
