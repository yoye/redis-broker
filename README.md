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
```

Now type in your console

```
php broker.php
```

On another console type

```
redis-cli LPUSH foo.channel 'This is a message'
```

Return on your first console, you should see

```
string(11) "foo.channel"
string(17) "This is a message"
```