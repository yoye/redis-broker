redis-broker
============

PHP Redis message broker

### Installation

The recommended way to install redis-broker is through [Composer](http://getcomposer.org/)

```
composer require "yoye/redis-broker" "dev-master"
```

### Usage

```php
<?php

// broker.php

require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\EventDispatcher\EventDispatcher;
use Yoye\Broker\Adapter\PhpRedisAdapter;
use Yoye\Broker\Broker;
use Yoye\Broker\Event\BrokerEvents;
use Yoye\Broker\Event\MessageEvent;

$client = new Redis();
$client->connect('127.0.0.1', 6379, 0);
$adapter = new PhpRedisAdapter($client);
$dispatcher = new EventDispatcher();
$dispatcher->addListener(BrokerEvents::MESSAGE_RECEIVED, function(MessageEvent $event) {
        $channel = $event->getChannel();
        $message = $event->getMessage();

        var_dump($channel, $message);

        // The event must be marked has done 
        // otherwise the listener will be called indefinitely
        $event->setDone();
});

$broker = new Broker($adapter, ['foo.channel', 'bar.channel'], $dispatcher);
$broker->run();
```

Now type in your console

```
php broker.php
```

On another console type `redis-cli LPUSH foo.channel 'This is a message'` or ``redis-cli LPUSH bar.channel 'This is a message'``, on your first console you should see:

```
string(11) "foo.channel"
string(17) "This is a message"
```

You can also set a repetition limit's, if this limit is reached, a new event will be launched.

```php
$client = new Redis();
$client->connect('127.0.0.1', 6379, 0);
$adapter = new PhpRedisAdapter($client);
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

$broker = new Broker($adapter, ['foo.channel', 'bar.channel'], $dispatcher);
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
