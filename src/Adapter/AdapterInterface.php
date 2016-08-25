<?php

namespace Yoye\Broker\Adapter;

interface AdapterInterface
{
    public function incr($key);
    public function del($key);
    public function lpush($key, $value);
    public function rpop($channel);
    public function brpop($channel, $timeout);
    public function lrem($key, $count, $value);
    public function rpoplpush($source, $destination);
}
