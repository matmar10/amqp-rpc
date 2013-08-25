<?php

namespace AmqpRpc;

use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;

class Client
{
    private $connection;
    private $channel;
    private $callbackQueue;
    private $response;
    private $correlationId;

    public function __construct(
        $host,
        $port,
        $username,
        $password,
        $virtualHost
    ) {
        $this->connection = new AMQPConnection(
            $host,
            $port,
            $username,
            $password,
            $virtualHost
        );
        $this->channel = $this->connection->channel();
        list($this->callbackQueue, ,) = $this->channel->queue_declare('', false, false, true, false);
        $this->channel->basic_consume($this->callbackQueue, '', false, false, false, false, array(
            $this,
            'onResponse'
        ));
    }

    public function onResponse($response)
    {
        if($response->get('correlation_id') === $this->correlationId) {
            $this->response = $response->body;
        }
    }

    public function callMethod($methodName, $arguments = array())
    {
        $this->response = null;
        $this->correlationId = uniqid();

        $messageBody = array(
            'methodName' => $methodName,
            'arguments' => $arguments,
        );

        $message = new AMQPMessage(json_encode($messageBody), array(
            'content_type' => 'application/json',
            'correlation_id' => $this->correlationId,
            'reply_to' => $this->callbackQueue,
        ));

        $this->channel->basic_publish($message, '', 'rpc_queue');
        while(!$this->response) {
            $this->channel->wait();
        }

        return $this->response;
    }
}

