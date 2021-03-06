<?php

namespace Yoye\Broker;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Yoye\Broker\Adapter\AdapterInterface;
use Yoye\Broker\Event\BrokerEvents;
use Yoye\Broker\Event\MessageEvent;

class Broker
{
    /**
     * @var AdapterInterface
     */
    protected $client;

    /**
     * @var array
     */
    protected $channels;

    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     * @var integer
     */
    protected $nestingLimit;

    /**
     * Broker constructor.
     *
     * @param AdapterInterface $client
     * @param $channels
     * @param EventDispatcherInterface|null $eventDispatcher
     */
    public function __construct(AdapterInterface $client, $channels, EventDispatcherInterface $eventDispatcher = null)
    {
        if ($eventDispatcher === null) {
            $eventDispatcher = new EventDispatcher();
        }

        $this->client          = $client;
        $this->channels        = (array) $channels;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Add a new channel to listen.
     *
     * @param string $channel
     */
    public function addChannel($channel)
    {
        if (!in_array($channel, $this->channels)) {
            $this->channels[] = $channel;
        }
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
     * Get Event Dispatcher.
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
     * in a temporary queue to prevent crash and create a restoration action.
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
                if ($this->nestingLimit !== null && $this->nestingLimit === $this->client->incr($message->getUuid())) {
                    $this->eventDispatcher->dispatch(BrokerEvents::NESTING_LIMIT, $event);
                    $this->client->del($message->getUuid());
                } else {
                    $this->client->lpush($channel, (string) $message);
                }
            }

            $this->removeTemporary($message, $channel);
        }
    }

    /**
     * Add message to the queue broker.
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
     * Push the message to the queue.
     *
     * @param \Yoye\Broker\Message $message
     * @param string               $channel
     */
    protected function push(Message $message, $channel)
    {
        $this->client->lpush($channel, (string) $message);
    }

    /**
     * Listen on channel.
     *
     * @return array
     */
    protected function listen()
    {
        list($channel, $json) = $this->client->brpop($this->channels, 0);

        $this->client->lpush($this->getTemporaryChannel($channel), $json);

        $data = json_decode($json, true);

        if (is_array($data) && array_key_exists('uuid', $data) && array_key_exists('data', $data)) {
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
     * This will replace a message in the temporary list by a new one with an uuid.
     *
     * @param string $data
     * @param string $channel
     *
     * @return \Yoye\Broker\Message
     */
    protected function buildMessage($data, $channel)
    {
        $this->removeTemporary($data, $channel);
        $message = new Message($data);
        $this->client->lpush($this->getTemporaryChannel($channel), (string) $message);

        return $message;
    }

    /**
     * Remove a message from the temporary channel.
     *
     * @param string $message
     * @param string $channel
     */
    protected function removeTemporary($message, $channel)
    {
        $this->client->lrem($this->getTemporaryChannel($channel), 0, (string) $message);
    }

    /**
     * Flush temporary queue.
     */
    protected function flushTemporary()
    {
        foreach ($this->channels as $channel) {
            do {
                $message = $this->client->rpoplpush($this->getTemporaryChannel($channel), $channel);
            } while ($message !== false);
        }
    }

    /**
     * @param string $channel
     *
     * @return string
     */
    protected function getTemporaryChannel($channel)
    {
        return sprintf('%s.temporary', $channel);
    }
}
