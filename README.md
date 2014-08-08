redis-broker
============

Redis messsage broker writing in PHP

### Installation

The recommended way to install redis-broker is through [Composer](http://getcomposer.org/)

```
composer require "yoye/redis-broker": "dev-master"
composer update betacie/redis-broker
```

### Usage

```php
<?php

// broker.php

require_once __DIR__ . '/vendor/autoload.php';

use Predis\Client;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Yoye\Broker\Broker;
use Yoye\Broker\Event\BrokerEvents;
use Yoye\Broker\Event\MessageEvent;

$client     = new Client('tcp://localhost:6379');
$dispatcher = new EventDispatcher();
$dispatcher->addListener(BrokerEvents::MESSAGE_RECEIVED, function(MessageEvent $event) {
        $channel = $event->getChannel();
        $message = $event->getMessage();

        var_dump($channel, $message);
        
        // The event must be marked has done 
        // otherwise the listener will be called indefinitely
        $event->setDone();
    });

$broker = new Broker($client, 'foo.channel', $dispatcher);
$broker->run();
```

Now type in your console

```
php broker.php
```

On another console type `redis-cli LPUSH foo.channel 'This is a message'`, on your first console you should see:

```
string(11) "foo.channel"
string(17) "This is a message"
```

You can also set a limit of repetition, if this limit is reached, a new event will be launched.

```
$client     = new Client('tcp://localhost:6379');
$dispatcher = new EventDispatcher();
$dispatcher->addListener(BrokerEvents::MESSAGE_RECEIVED, function(MessageEvent $event) {
        var_dump($event->getMessage());

        if ($event->getMessage() === 'FooBar') {
            $event->setDone();
        }
    });
$dispatcher->addListener(BrokerEvents::NESTING_LIMIT, function(MessageEvent $event) {
        var_dump('Last call for: ' . $event->getMessage());
    });

$broker = new Broker($client, 'foo.channel', $dispatcher);
$broker->setNestingLimit(3);
$broker->run();
```

Now if you type `LPUSH foo.channel 'This is a message' 'FooBar'`, you should see:

```
string(17) "This is a message"
string(6) "FooBar"
string(17) "This is a message"
string(17) "This is a message"
string(32) "Last call for: This is a message"

```