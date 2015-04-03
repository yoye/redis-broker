<?php

namespace Yoye\Broker;

class MessageTest extends \PHPUnit_Framework_TestCase
{
    public function testGetUuid()
    {
        $message = new Message('foo');
        $this->assertNotNull($message->getUuid());
        $this->assertRegExp('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/', $message->getUuid());

        $wrongMessage = new Message('foo', 'bar');
        $this->assertEquals($wrongMessage->getUuid(), 'bar');
    }

    public function testGetData()
    {
        $message = new Message('foo');
        $this->assertEquals('foo', $message->getData());
    }

    public function testToString()
    {
        $message = new Message('foo');
        $this->assertEquals(json_encode(array('uuid' => $message->getUuid(), 'data' => $message->getData())), (string) $message);
    }
}
