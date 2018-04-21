<?php
declare(strict_types = 1);

namespace Tests\Innmind\Infrastructure\Neo4j\Command;

use Innmind\Infrastructure\Neo4j\Command\Install;
use Innmind\CLI\{
    Command,
    Command\Arguments,
    Command\Options,
    Environment,
};
use Innmind\Server\Control\{
    Server,
    Server\Processes,
    Server\Process,
    Server\Process\ExitCode,
};
use PHPUnit\Framework\TestCase;

class InstallTest extends TestCase
{
    public function testInterface()
    {
        $this->assertInstanceOf(
            Command::class,
            new Install($this->createMock(Server::class))
        );
    }

    public function testInvokation()
    {
        $install = new Install(
            $server = $this->createMock(Server::class)
        );
        $server
            ->expects($this->once())
            ->method('processes')
            ->willReturn($processes = $this->createMock(Processes::class));
        $processes
            ->expects($this->exactly(6))
            ->method('execute')
            ->withConsecutive(
                ['wget -O - https://debian.neo4j.org/neotechnology.gpg.key | apt-key add -'],
                ['echo \'deb https://debian.neo4j.org/repo stable/\' | tee /etc/apt/sources.list.d/neo4j.list'],
                ['apt-get update'],
                ['apt-get install neo4j curl -y'],
                ['sed \'-i.bak\' \'s/#dbms.connectors.default_listen_address=localhost/dbms.connectors.default_listen_address=0.0.0.0/g\' \'/etc/neo4j/neo4j.conf\''],
                ['sed \'-i.bak\' \'s/#dbms.connectors.default_advertised_address=localhost/dbms.connectors.default_advertised_address=0.0.0.0/g\' \'/etc/neo4j/neo4j.conf\'']
            )
            ->willReturn($process = $this->createMock(Process::class));
        $process
            ->expects($this->exactly(6))
            ->method('wait')
            ->will($this->returnSelf());
        $process
            ->expects($this->exactly(6))
            ->method('exitCode')
            ->willReturn(new ExitCode(0));
        $env = $this->createMock(Environment::class);
        $env
            ->expects($this->once())
            ->method('exit')
            ->with(0);

        $this->assertNull($install(
            $env,
            new Arguments,
            new Options
        ));
    }

    public function testExitWithErrorCodeWhenOneActionFailed()
    {
        $install = new Install(
            $server = $this->createMock(Server::class)
        );
        $server
            ->expects($this->once())
            ->method('processes')
            ->willReturn($processes = $this->createMock(Processes::class));
        $processes
            ->expects($this->exactly(2))
            ->method('execute')
            ->withConsecutive(
                ['wget -O - https://debian.neo4j.org/neotechnology.gpg.key | apt-key add -'],
                ['echo \'deb https://debian.neo4j.org/repo stable/\' | tee /etc/apt/sources.list.d/neo4j.list']
            )
            ->willReturn($process = $this->createMock(Process::class));
        $process
            ->expects($this->exactly(2))
            ->method('wait')
            ->will($this->returnSelf());
        $process
            ->expects($this->exactly(2))
            ->method('exitCode')
            ->will($this->onConsecutiveCalls(
                new ExitCode(0),
                new ExitCode(1)
            ));
        $env = $this->createMock(Environment::class);
        $env
            ->expects($this->once())
            ->method('exit')
            ->with(1);

        $this->assertNull($install(
            $env,
            new Arguments,
            new Options
        ));
    }

    public function testUsage()
    {
        $expected = <<<USAGE
install

This will install the neo4j server on the machine
USAGE;

        $this->assertSame($expected, (string) new Install($this->createMock(Server::class)));
    }
}
