<?php
declare(strict_types = 1);

namespace Innmind\Infrastructure\Neo4j\Listener;

use Innmind\Infrastructure\Neo4j\Event\PasswordWasChanged;
use Innmind\InstallationMonitor\{
    Client,
    Event,
};
use Innmind\Immutable\Map;

final class InstallationMonitor
{
    private Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function __invoke(PasswordWasChanged $event): void
    {
        $this->client->send(new Event(
            new Event\Name('neo4j.password_changed'),
            (new Map('string', 'variable'))
                ->put('user', $event->user())
                ->put('password', $event->password())
        ));
    }
}
