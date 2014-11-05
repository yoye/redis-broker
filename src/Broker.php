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
     * @var array
     */
    private $channels;

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

    function __construct(Client $predisClient, $channels, EventDispatcherInterface $eventDispatcher = null)
    {
        if ($eventDispatcher === null) {
            $eventDispatcher = new EventDispatcher();
        }

        $this->predisClient    = $predisClient;
        $this->channels        = (array) $channels;
        $this->eventDispatcher = $eventDispatcher;
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
            list($channel, $message) = $this->listen();
            $event = new MessageEvent($channel, $message->getData());

            $this->eventDispatcher->dispatch(BrokerEvents::MESSAGE_RECEIVED, $event);

            if (!$event->isDone()) {
                if ($this->nestingLimit !== null && $this->nestingLimit === $this->predisClient->incr($message->getUuid())) {
                    $this->eventDispatcher->dispatch(BrokerEvents::NESTING_LIMIT, $event);
                    $this->predisClient->del($message->getUuid());
                } else {
                    $this->predisClient->lpush($channel, $message);
                }
            }

            $this->removeTemporary($message, $channel);
        }
    }

    /**
     * Add message to the queue broker
     * 
     * @param string $data
     * @param string $channel
     */
    public function queue($data, $channel)
    {
        $message = new Message($data);

        $this->push($message, $channel);
    }

    /**
     * Push the message to the queue
     * 
     * @param \Yoye\Broker\Message $message
     * @param string $channel
     */
    protected function push(Message $message, $channel)
    {
        $this->predisClient->lpush($channel, $message);
    }

    /**
     * Listen on channel
     * 
     * @return array
     */
    protected function listen()
    {
        list($channel, $json) = $this->predisClient->brpop($this->channels, 0);
        $this->predisClient->lpush($this->getTemporaryChannel($channel), $json);

        $data = json_decode($json, true);

        if ($data !== null && array_key_exists('uuid', $data) && array_key_exists('data', $data)) {
            $message = new Message($data['data'], $data['uuid']);
        } else {
            $message = $this->buildMessage($json, $channel);
        }

        return [
            $channel,
            $message,
        ];
    }

    /**
     * This will replace a message in the temporary list by a new one with an uuid
     * 
     * @param string $data
     * @param string $channel
     * @return \Yoye\Broker\Message
     */
    protected function buildMessage($data, $channel)
    {
        $this->removeTemporary($data, $channel);
        $message = new Message($data);
        $this->predisClient->lpush($this->getTemporaryChannel($channel), $message);

        return $message;
    }

    /**
     * Remove a message from the temporary channel
     * 
     * @param string $message
     * @param string $channel
     */
    protected function removeTemporary($message, $channel)
    {
        $this->predisClient->lrem($this->getTemporaryChannel($channel), 0, $message);
    }

    /**
     * Flush temporary queue
     */
    protected function flushTemporary()
    {
        foreach ($this->channels as $channel) {
            do {
                $message = $this->predisClient->rpoplpush($this->getTemporaryChannel($channel), $channel);
            } while ($message !== null);
        }
    }

    private function getTemporaryChannel($channel)
    {
        return sprintf('%s.temporary', $channel);
    }

}
