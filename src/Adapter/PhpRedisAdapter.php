<?php

namespace Yoye\Broker\Adapter;

class PhpRedisAdapter implements AdapterInterface
{

    /**
     * @var \Redis
     */
    private $client;

    public function __construct(\Redis $client)
    {
        $this->client = $client;
    }

    public function brpop($channel, $timeout)
    {
        return $this->client->brPop($channel, $timeout);
    }

    public function del($key)
    {
        return $this->client->delete($key);
    }

    public function incr($key)
    {
        return $this->client->incr($key);
    }

    public function lpush($key, $value)
    {
        return $this->client->lPush($key, $value);
    }

    public function lrem($key, $count, $value)
    {
        return $this->client->lRem($key, $value, $count);
    }

    public function rpoplpush($source, $destination)
    {
        return $this->client->rpoplpush($source, $destination);
    }

}
