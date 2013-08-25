<?php

require_once __DIR__ . '/../vendor/autoload.php';

use AmqpRpc\Client;
use Symfony\Component\Yaml\Parser;

$parser = new Parser();
$config = $parser->parse(file_get_contents(__DIR__ . '/config.yml'));

$client = new Client(
    $config['hostname'],
    $config['port'],
    $config['username'],
    $config['password'],
    $config['vhost']
);
$response = $client->callMethod('foo', array(
    'ABCDEFG',
));
echo "Response: " . $response . "\n";

