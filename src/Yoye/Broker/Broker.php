<?php

namespace Yoye\Broker;

use Predis\Client;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class Broker
{
    /**
     * Event name for new message received 
     */

    const MESSAGE_RECEIVED = 'redis-broker.message.received';

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

    function __construct(Client $predisClient, $channel, EventDispatcherInterface $eventDispatcher)
    {
        $this->predisClient     = $predisClient;
        $this->channel          = $channel;
        $this->temporaryChannel = $channel . '.temporary';
        $this->eventDispatcher  = $eventDispatcher;
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
            $event   = new MessageEvent($this->channel, $message);

            $this->eventDispatcher->dispatch(self::MESSAGE_RECEIVED, $event);

            if (!$event->isDone()) {
                $this->queue($message);
            }

            $this->removeTemporary($message);
        }
    }

    /**
     * Add message to the queue broker
     * 
     * @param string $message
     */
    public function queue($message)
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
        return $this->predisClient->brpoplpush($this->channel, $this->temporaryChannel, 0);
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