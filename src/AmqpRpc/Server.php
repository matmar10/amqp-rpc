<?php

namespace AmqpRpc;

use Exception;
use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;
use ReflectionMethod;
use Symfony\Component\Yaml\Parser;

class Server
{
    private $connection;
    private $channel;
    protected $reflectionClass;
    protected $serviceInstance;

    public function __construct(
        $host,
        $port,
        $username,
        $password,
        $virtualHost,
        $serviceInstance
    ) {
        $this->serviceInstance = $serviceInstance;
        $this->connection = new AMQPConnection(
            $host,
            $port,
            $username,
            $password,
            $virtualHost
        );
        $this->channel = $this->connection->channel();
        $this->channel->queue_declare('rpc_queue', false, false, false, false);
        $this->channel->basic_qos(null, 1, null);
        $this->channel->basic_consume('rpc_queue', '', false, false, false, false, array(
            $this,
            'onRequest',
        ));
    }

    public function onRequest($request)
    {
        $body = json_decode($request->body, true);

        try {

            $reflectionMethod = new ReflectionMethod($this->serviceInstance, $body['methodName']);
            $numRequiredParams = $reflectionMethod->getNumberOfRequiredParameters();
            if($numRequiredParams) {
                $invalidNumArgsMessage = "Invalid number of arguments supplied: method '{$body['methodName']}' requires $numRequiredParams arguments (%s supplied).";
                if(!is_array($body['arguments'])) {
                    throw new Exception(sprintf($invalidNumArgsMessage, 0));
                }
                if(count($body['arguments']) < $numRequiredParams) {
                    throw new Exception(sprintf($invalidNumArgsMessage, count($body['arguments'])));
                }
            }

            $methodResult = call_user_func_array(array($this->serviceInstance, $body['methodName']), $body['arguments']);
        } catch(Exception $e) {
            $methodResult = json_encode(array(
                'exception' => array(
                    'code' => $e->getCode(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'message' => $e->getMessage(),
                    'previous' => $e->getPrevious(),
                    'trace' => $e->getTraceAsString(),
                ),
            ));
        }

        $message = new AMQPMessage($methodResult, array(
            'correlation_id' => $request->get('correlation_id'),
        ));
        $request->delivery_info['channel']->basic_publish($message, '', $request->get('reply_to'));
        $request->delivery_info['channel']->basic_ack($request->delivery_info['delivery_tag']);
    }

    public function channelHasCallbacks()
    {
        return $this->channel->callbacks;
    }

    public function channelWait()
    {
        $this->channel->wait();
    }

    public function __destruct()
    {
        $this->channel->close();
        $this->connection->close();
    }
}
