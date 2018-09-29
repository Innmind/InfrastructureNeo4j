<?php
declare(strict_types = 1);

namespace Innmind\Infrastructure\Neo4j;

use function Innmind\HttpTransport\bootstrap as transport;
use function Innmind\EventBus\bootstrap as eventBus;
use Innmind\CLI\Commands;
use Innmind\Server\Control\ServerFactory;
use Innmind\Immutable\{
    Map,
    SetInterface,
};

function bootstrap(): Commands
{
    $transport = transport();
    $eventBus = eventBus()['bus'](
        new Map('string', SetInterface::class)
    );

    $server = ServerFactory::build();

    return new Commands(
        new Command\Install($server),
        new Command\SetupUser(
            $server,
            $transport['catch_guzzle_exceptions'](
                $transport['guzzle']()
            ),
            $eventBus
        )
    );
}
