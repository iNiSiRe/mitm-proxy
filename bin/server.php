<?php

$loader = require __DIR__ . '/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();

if (!isset($_ENV['TARGET']))
{
    throw new RuntimeException('TARGET is null');
}

$port = $_ENV['PROXY_PORT'] ?? 9090;

$socket = new React\Socket\Server('0.0.0.0:' . $port, $loop);

$socket->on('connection', function (React\Socket\ConnectionInterface $outer) use ($loop)
{
    $outer->pause();

    $connector = new React\Socket\Connector($loop);
    $connector->connect($_ENV['TARGET'])->then(function (React\Socket\ConnectionInterface $inner) use ($loop, $outer)
    {
        $inner->on('data', function ($data)
        {
            echo PHP_EOL . '----- TARGET -> MITM ------' . PHP_EOL;
            echo $data;
        });

        $outer->on('data', function ($data)
        {
            echo PHP_EOL . '----- MITM -> TARGET ------' . PHP_EOL;
            echo $data;
        });

        $outer->on('close', function () use ($inner)
        {
            echo PHP_EOL . '----- CLIENT close connection ------' . PHP_EOL;
            $inner->close();
        });

        $outer->pipe($inner);
        $inner->pipe($outer);

        $outer->resume();
    });

});

$loop->run();