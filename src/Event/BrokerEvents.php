<?php

namespace Yoye\Broker\Event;

class BrokerEvents
{
    /**
     * Event name for new message received.
     */
    const MESSAGE_RECEIVED = 'redis-broker.message.received';

    /**
     * A message has been repeated too many time.
     */
    const NESTING_LIMIT = 'redis-broker.message.nesting-limit';
}
