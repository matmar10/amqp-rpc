<?php

require_once __DIR__ . '/../vendor/autoload.php';

use AmqpRpc\Server;
use Symfony\Component\Yaml\Parser;

$parser = new Parser();
$config = $parser->parse(file_get_contents(__DIR__.'/config.yml'));

class Dummy
{
    public function foo($arg1)
    {
        return ">>> arg1 was $arg1 <<<";
    }
}

$serviceInstance = new Dummy();

$server = new Server(
    $config['hostname'],
    $config['port'],
    $config['username'],
    $config['password'],
    $config['vhost'],
    $serviceInstance
);

while($server->channelHasCallbacks()) {
    $server->channelWait();
}

