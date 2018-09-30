<?php
declare(strict_types = 1);

namespace Innmind\Infrastructure\Neo4j;

use function Innmind\HttpTransport\bootstrap as transport;
use function Innmind\EventBus\bootstrap as eventBus;
use function Innmind\InstallationMonitor\bootstrap as monitor;
use Innmind\CLI\Commands;
use Innmind\Server\Control\ServerFactory;
use Innmind\Immutable\{
    Map,
    SetInterface,
    Set,
};

function bootstrap(): Commands
{
    $clients = monitor()['client'];
    $client = $clients['silence'](
        $clients['socket']()
    );

    $transport = transport();
    $eventBus = eventBus()['bus'](
        (new Map('string', SetInterface::class))
            ->put(
                Event\PasswordWasChanged::class,
                Set::of('callable', new Listener\InstallationMonitor($client))
            )
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
