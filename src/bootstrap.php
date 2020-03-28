<?php
declare(strict_types = 1);

namespace Innmind\Infrastructure\Neo4j;

use function Innmind\EventBus\bootstrap as eventBus;
use function Innmind\InstallationMonitor\bootstrap as monitor;
use Innmind\CLI\Commands;
use Innmind\OperatingSystem\OperatingSystem;
use Innmind\Immutable\{
    Map,
    Set,
};

function bootstrap(OperatingSystem $os): Commands
{
    $clients = monitor($os)['client'];
    $client = $clients['silence'](
        $clients['ipc']()
    );

    /**
     * @psalm-suppress InvalidScalarArgument
     * @psalm-suppress InvalidArgument
     */
    $eventBus = eventBus()['bus'](
        Map::of('string', 'callable')
            (
                Event\PasswordWasChanged::class,
                new Listener\InstallationMonitor($client)
            )
    );

    return new Commands(
        new Command\Install($os->control()),
        new Command\SetupUser(
            $os->process(),
            $os->control(),
            $os->remote()->http(),
            $eventBus
        )
    );
}
