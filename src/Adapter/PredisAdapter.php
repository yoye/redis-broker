<?php

namespace Yoye\Broker\Adapter;

use Predis\Client;

class PredisAdapter implements AdapterInterface
{
    /**
     * @var Client
     */
    private $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function rpop($channel)
    {
        return $this->client->rpop($channel);
    }

    public function brpop($channel, $timeout)
    {
        return $this->client->brpop($channel, $timeout);
    }

    public function del($key)
    {
        return $this->client->del($key);
    }

    public function incr($key)
    {
        return $this->client->incr($key);
    }

    public function lpush($key, $value)
    {
        return $this->client->lpush($key, $value);
    }

    public function lrem($key, $count, $value)
    {
        return $this->client->lrem($key, $count, $value);
    }

    public function rpoplpush($source, $destination)
    {
        $value = $this->client->rpoplpush($source, $destination);

        return $value === null ? false : $value;
    }
}
