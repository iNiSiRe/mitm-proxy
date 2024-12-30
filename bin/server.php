<?php

$target = $_ENV['TARGET'] ?? null;
$port = $_ENV['PROXY_PORT'] ?? null;

echo sprintf('Target: %s', $target) . PHP_EOL;
echo sprintf('Listen port: %s', $port) . PHP_EOL;

if (!$target) {
    throw new RuntimeException('TARGET is null');
}

if (!$port) {
    throw new RuntimeException('PROXY_PORT is null');
}

echo "MITM v0.1.3: Started" . PHP_EOL;

$loader = require __DIR__ . '/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();

$socket = new React\Socket\Server('0.0.0.0:' . $port, $loop);

function onError(\Throwable $error) {
        echo 'Error: ' . get_class($error) . ' ' . $error->getMessage() . PHP_EOL;
}

$socket->on('error', 'onError');

$socket->on('connection', function (React\Socket\ConnectionInterface $outer) use ($loop, $target)
{
    echo sprintf('%d: Outer connection established', time()) . PHP_EOL;

    $outer->pause();

    $outer->on('error', 'onError');

    $connector = new React\Socket\Connector($loop);

    $connector->connect($target)->then(function (React\Socket\ConnectionInterface $inner) use ($loop, $outer) {
        echo sprintf('%d: Inner connection established', time()) . PHP_EOL;

        $inner->on('error', 'onError');

        $inner->on('data', function ($data) use ($inner, $outer) {
            echo sprintf('%d: %s <- %s', time(), $outer->getRemoteAddress(), $inner->getRemoteAddress()) . PHP_EOL;
            echo PHP_EOL . '----- START ------' . PHP_EOL;
            echo $data;
            echo PHP_EOL . '----- END ------' . PHP_EOL;
        });

        $outer->on('data', function ($data) use ($inner, $outer) {
            echo sprintf('%d: %s -> %s', time(), $outer->getRemoteAddress(), $inner->getRemoteAddress()) . PHP_EOL;
            echo PHP_EOL . '----- START ------' . PHP_EOL;
            echo $data;
            echo PHP_EOL . '----- END ------' . PHP_EOL;
        });

        $outer->on('close', function () use ($inner) {
            echo sprintf('%d: Connection closed by %s', time(), $inner->getRemoteAddress()) . PHP_EOL;
            $inner->close();
        });

        $inner->on('close', function () use ($outer) {
            echo sprintf('%d: Connection closed by %s', time(), $outer->getRemoteAddress()) . PHP_EOL;
            $outer->close();
        });

        $outer->pipe($inner);
        $inner->pipe($outer);

        $outer->resume();
    });

});

$loop->addSignal(SIGTERM, function (int $signal) use ($loop) {
    echo 'Signal SIGTERM received: exiting...' . PHP_EOL;
    $loop->stop();
});

$loop->addSignal(SIGINT, function (int $signal) use ($loop) {
    echo 'Signal SIGINT received: exiting...' . PHP_EOL;
    $loop->stop();
});

function signalHandler($signal) {
    echo 'Signal received: exiting...' . PHP_EOL;
    $loop->stop();
}

//pcntl_signal(SIGTERM, 'signalHandler');

$loop->run();
