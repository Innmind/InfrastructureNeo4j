<?php
declare(strict_types = 1);

namespace Tests\Innmind\Infrastructure\Neo4j\Listener;

use Innmind\Infrastructure\Neo4j\{
    Listener\InstallationMonitor,
    Event\PasswordWasChanged,
};
use Innmind\InstallationMonitor\{
    Client,
    Event,
};
use Innmind\Immutable\Map;
use PHPUnit\Framework\TestCase;

class InstallationMonitorTest extends TestCase
{
    public function testInvokation()
    {
        $dispatch = new InstallationMonitor(
            $client = $this->createMock(Client::class)
        );
        $client
            ->expects($this->once())
            ->method('send')
            ->with(new Event(
                new Event\Name('neo4j.password_changed'),
                Map::of('string', 'scalar|array')
                    ('user', 'neo4j')
                    ('password', 'watev')
            ));

        $this->assertNull($dispatch(new PasswordWasChanged('neo4j', 'watev')));
    }
}
