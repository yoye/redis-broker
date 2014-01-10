<?php

require_once __DIR__ . '/vendor/autoload.php';

use Predis\Client;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Yoye\Broker\Broker;
use Yoye\Broker\MessageEvent;

$client     = new Client('tcp://localhost:6379');
$dispatcher = new EventDispatcher();
$dispatcher->addListener(Broker::MESSAGE_RECEIVED, function(MessageEvent $event) {
        $channel = $event->getChannel();
        $message = $event->getMessage();

        var_dump($channel, $message);
        
        $event->setDone();
    });

$broker = new Broker($client, 'foo.channel', $dispatcher);
$broker->run();