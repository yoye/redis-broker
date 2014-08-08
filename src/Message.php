<?php

namespace Yoye\Broker;

use Rhumsaa\Uuid\Uuid;

class Message
{

    /**
     * @var string
     */
    private $uuid;

    /**
     * @var string
     */
    private $data;

    function __construct($data, $uuid = null)
    {
        $this->uuid = $uuid ? : (string) Uuid::uuid1();
        $this->data = $data;
    }

    public function getUuid()
    {
        return $this->uuid;
    }

    public function getData()
    {
        return $this->data;
    }

    public function __toString()
    {
        return json_encode(array(
            'uuid' => $this->uuid,
            'data' => $this->data,
        ));
    }

}
