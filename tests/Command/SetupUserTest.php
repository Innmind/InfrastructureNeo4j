<?php
declare(strict_types = 1);

namespace Tests\Innmind\Infrastructure\Neo4j\Command;

use Innmind\Infrastructure\Neo4j\{
    Command\SetupUser,
    Event\PasswordWasChanged,
};
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
    Server\Process\Output\Output,
    Server\Process\Output\Type,
};
use Innmind\HttpTransport\Transport;
use Innmind\Http\{
    Message\Response,
    Message\StatusCode,
};
use Innmind\EventBus\EventBus;
use Innmind\OperatingSystem\CurrentProcess;
use Innmind\TimeContinuum\Earth\Period\Second;
use Innmind\Immutable\{
    Map,
    Sequence,
    Str,
};
use PHPUnit\Framework\TestCase;

class SetupUserTest extends TestCase
{
    public function testInterface()
    {
        $this->assertInstanceOf(
            Command::class,
            new SetupUser(
                $this->createMock(CurrentProcess::class),
                $this->createMock(Server::class),
                $this->createMock(Transport::class),
                $this->createMock(EventBus::class)
            )
        );
    }

    public function testInvokation()
    {
        $setup = new SetupUser(
            $process = $this->createMock(CurrentProcess::class),
            $server = $this->createMock(Server::class),
            $transport = $this->createMock(Transport::class),
            $bus = $this->createMock(EventBus::class)
        );
        $password = null;
        $server
            ->expects($this->exactly(2))
            ->method('processes')
            ->willReturn($processes = $this->createMock(Processes::class));
        $processes
            ->expects($this->exactly(2))
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return $command->toString() === "service 'neo4j' 'status'";
            }))
            ->will($this->onConsecutiveCalls(
                $firstProcess = $this->createMock(Process::class),
                $secondProcess = $this->createMock(Process::class)
            ));
        $firstProcess
            ->expects($this->once())
            ->method('wait');
        $firstProcess
            ->expects($this->once())
            ->method('output')
            ->willReturn(new Output(
                $lines = Sequence::of(
                    'array',
                    [Str::of('Apr 21 11:43:10 vps42 neo4j[12225]: 2018-04-21 09:43:10.740+0000 INFO  Starting...'), Type::output()],
                    [Str::of("Apr 21 11:43:12 vps42 neo4j[12225]: 2018-04-21 09:43:12.573+0000 INFO  Bolt enabled on 0.0.0.0:76\n87."), Type::output()],
                    [Str::of('Apr 21 11:43:18 vps42 neo4j[12225]: 2018-04-21 09:43:18.987+0000 INFO  Started.'), Type::output()],
                )
            ));
        $secondProcess
            ->expects($this->once())
            ->method('wait');
        $secondProcess
            ->expects($this->once())
            ->method('output')
            ->willReturn(new Output(
                $lines->add([
                    Str::of('Apr 21 11:43:21 vps42 neo4j[12225]: 2018-04-21 09:43:21.023+0000 INFO  Remote interface available
 at http://0.0.0.0:7474/'),
                    Type::output()
                ])
            ));
        $transport
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->callback(static function($request) use (&$password): bool {
                $body = json_decode($request->body()->toString(), true);
                $password = $body['password'];

                return $request->url()->toString() === 'http://localhost:7474/user/neo4j/password' &&
                    $request->method()->toString() === 'POST' &&
                    $request->protocolVersion()->toString() === '2.0' &&
                    $request->headers()->get('authorization')->toString() === 'Authorization: "Basic" bmVvNGo6bmVvNGo=' &&
                    $request->headers()->get('content-type')->toString() === 'Content-Type: application/json' &&
                    strlen($body['password']) === 40;
            }))
            ->willReturn($response = $this->createMock(Response::class));
        $response
            ->expects($this->once())
            ->method('statusCode')
            ->willReturn(new StatusCode(200));
        $bus
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->callback(static function(PasswordWasChanged $event) use (&$password): bool {
                return $event->user() === 'neo4j' && $event->password() === $password;
            }));
        $process
            ->expects($this->exactly(2))
            ->method('halt')
            ->with(new Second(1));

        $this->assertNull($setup(
            $this->createMock(Environment::class),
            new Arguments,
            new Options
        ));
    }

    public function testFailsWhenCallToChangePasswordFailed()
    {
        $setup = new SetupUser(
            $this->createMock(CurrentProcess::class),
            $server = $this->createMock(Server::class),
            $transport = $this->createMock(Transport::class),
            $bus = $this->createMock(EventBus::class)
        );
        $server
            ->expects($this->once())
            ->method('processes')
            ->willReturn($processes = $this->createMock(Processes::class));
        $processes
            ->expects($this->once())
            ->method('execute')
            ->with($this->callback(static function($command): bool {
                return $command->toString() === "service 'neo4j' 'status'";
            }))
            ->willReturn($process = $this->createMock(Process::class));
        $process
            ->expects($this->once())
            ->method('wait');
        $process
            ->expects($this->once())
            ->method('output')
            ->willReturn(new Output(
                Sequence::of(
                    'array',
                    [Str::of('Remote interface available'), Type::output()]
                )
            ));
        $transport
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->callback(static function($request): bool {
                $body = json_decode($request->body()->toString(), true);

                return $request->url()->toString() === 'http://localhost:7474/user/neo4j/password' &&
                    $request->method()->toString() === 'POST' &&
                    $request->protocolVersion()->toString() === '2.0' &&
                    $request->headers()->get('authorization')->toString() === 'Authorization: "Basic" bmVvNGo6bmVvNGo=' &&
                    $request->headers()->get('content-type')->toString() === 'Content-Type: application/json' &&
                    strlen($body['password']) === 40;
            }))
            ->willReturn($response = $this->createMock(Response::class));
        $response
            ->expects($this->once())
            ->method('statusCode')
            ->willReturn(new StatusCode(400));
        $env = $this->createMock(Environment::class);
        $env
            ->expects($this->once())
            ->method('exit')
            ->with(1);
        $bus
            ->expects($this->never())
            ->method('__invoke');

        $this->assertNull($setup(
            $env,
            new Arguments,
            new Options
        ));
    }

    public function testUsage()
    {
        $expected = <<<USAGE
setup-user

This will change the password for the user 'neo4j'
USAGE;

        $this->assertSame($expected, (new SetupUser(
            $this->createMock(CurrentProcess::class),
            $this->createMock(Server::class),
            $this->createMock(Transport::class),
            $this->createMock(EventBus::class)
        ))->toString());
    }
}
