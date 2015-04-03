<?php

namespace Yoye\Broker;

use M6Web\Component\RedisMock\RedisMockFactory;

class BrokerTest extends \PHPUnit_Framework_TestCase
{
    private $redis;
    private $broker;

    public function setUp()
    {
        $factory      = new RedisMockFactory();
        $this->redis  = $factory->getAdapter('Yoye\Broker\Adapter\PredisAdapter', true);
        $this->broker = new Broker($this->redis, 'foo.bar');
    }

    public function tearDown()
    {
        unset($this->broker);
        unset($this->redis);
    }

    public function testQueue()
    {
        $this->broker->queue('FooBar', 'foo.bar');
        $message = $this->redis->rpop('foo.bar');
        $this->assertInstanceOf('Yoye\\Broker\\Message', $message);
        $this->assertEquals('FooBar', $message->getData());
    }

    public function testRemoveTemporary()
    {
        $method = new \ReflectionMethod($this->broker, 'removeTemporary');
        $method->setAccessible(true);

        $this->redis->lpush('foo.bar.temporary', 'Foo');
        $this->assertEquals('Foo', $this->redis->rpop('foo.bar.temporary'));

        $method->invoke($this->broker, 'Foo', 'foo.bar');

        $this->assertNull($this->redis->rpop('foo.bar.temporary'));
    }
}
