<?php

namespace Yoye\Broker;

use Predis\Client;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Yoye\Broker\Event\BrokerEvents;
use Yoye\Broker\Event\MessageEvent;

class Broker
{

    /**
     * @var Client
     */
    private $predisClient;

    /**
     * @var string
     */
    private $channel;

    /**
     * Used for temporary value
     * 
     * @var string
     */
    private $temporaryChannel;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var integer
     */
    private $nestingLimit;

    function __construct(Client $predisClient, $channel, EventDispatcherInterface $eventDispatcher = null)
    {
        if ($eventDispatcher === null) {
            $eventDispatcher = new EventDispatcher();
        }

        $this->predisClient     = $predisClient;
        $this->channel          = $channel;
        $this->temporaryChannel = $channel . '.temporary';
        $this->eventDispatcher  = $eventDispatcher;
    }

    /**
     * Set the nesting limit,
     * If this value is defined, a message will not be treated over this limit,
     * even if the events are not set as done.
     * 
     * @param integer $nestingLimit
     */
    public function setNestingLimit($nestingLimit)
    {
        $this->nestingLimit = $nestingLimit;
    }

    /**
     * Get Event Dispatcher
     * 
     * @return EventDispatcherInterface
     */
    public function getEventDispatcher()
    {
        return $this->eventDispatcher;
    }

    /**
     * Run broker
     * First flush temporary queue in case of crash
     * Listen on broker channel with a blocking action then push a message 
     * in a temporary queue to prevent crash and create a restoration action
     * 
     * When a message is received emit a MessageEvent
     * 
     * If job is not done, we requeue the message
     * 
     * Finally we remove message from temporary queue
     */
    public function run()
    {
        $this->flushTemporary();

        while (true) {
            $message = $this->listen();
            $event   = new MessageEvent($this->channel, $message->getData());

            $this->eventDispatcher->dispatch(BrokerEvents::MESSAGE_RECEIVED, $event);

            if (!$event->isDone()) {
                if ($this->nestingLimit !== null && $this->nestingLimit === $this->predisClient->incr($message->getUuid())) {
                    $this->eventDispatcher->dispatch(BrokerEvents::NESTING_LIMIT, $event);
                    $this->predisClient->del($message->getUuid());
                } else {
                    $this->predisClient->lpush($this->channel, $message);
                }
            }

            $this->removeTemporary($message);
        }
    }

    /**
     * Add message to the queue broker
     * 
     * @param string $data
     */
    public function queue($data)
    {
        $message = new Message($data);

        $this->push($message);
    }

    /**
     * Push the message to the queue
     * 
     * @param \Yoye\Broker\Message $message
     */
    protected function push(Message $message)
    {
        $this->predisClient->lpush($this->channel, $message);
    }

    /**
     * Listen on channel
     * 
     * @return mixed
     */
    protected function listen()
    {
        $json = $this->predisClient->brpoplpush($this->channel, $this->temporaryChannel, 0);
        $data = json_decode($json, true);

        if ($data === null) {
            return $this->createMessage($json);
        }

        if (array_key_exists('uuid', $data) && array_key_exists('data', $data)) {
            return new Message($data['data'], $data['uuid']);
        }

        return $this->createMessage($json);
    }

    /**
     * This will replace a message in the temporary list by a new one with an uuid
     * 
     * @param string $data
     * @return \Yoye\Broker\Message
     */
    protected function createMessage($data)
    {
        $this->removeTemporary($data);
        $message = new Message($data);
        $this->predisClient->lpush($this->temporaryChannel, $message);

        return $message;
    }

    /**
     * Remove a message from the temporary channel
     * 
     * @param string $message
     */
    protected function removeTemporary($message)
    {
        $this->predisClient->lrem($this->temporaryChannel, 0, $message);
    }

    /**
     * Flush temporary queue
     */
    protected function flushTemporary()
    {
        do {
            $message = $this->predisClient->rpoplpush($this->temporaryChannel, $this->channel);
        } while ($message !== null);
    }

}
