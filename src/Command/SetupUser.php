<?php
declare(strict_types = 1);

namespace Innmind\Infrastructure\Neo4j\Command;

use Innmind\Infrastructure\Neo4j\Event\PasswordWasChanged;
use Innmind\CLI\{
    Command,
    Command\Arguments,
    Command\Options,
    Environment,
};
use Innmind\Server\Control\{
    Server,
    Server\Command as ServerCommand,
};
use Innmind\HttpTransport\Transport;
use Innmind\Http\{
    Message\Request\Request,
    Message\Method,
    ProtocolVersion,
    Headers,
    Header\ContentType,
    Header\Authorization,
};
use Innmind\Url\Url;
use Innmind\Stream\Readable\Stream;
use Innmind\EventBus\EventBus;
use Innmind\OperatingSystem\CurrentProcess;
use Innmind\TimeContinuum\Earth\Period\Second;
use Innmind\Immutable\Str;

final class SetupUser implements Command
{
    private CurrentProcess $process;
    private Server $server;
    private Transport $fulfill;
    private EventBus $dispatch;

    public function __construct(
        CurrentProcess $process,
        Server $server,
        Transport $fulfill,
        EventBus $dispatch
    ) {
        $this->process = $process;
        $this->server = $server;
        $this->fulfill = $fulfill;
        $this->dispatch = $dispatch;
    }

    public function __invoke(Environment $env, Arguments $arguments, Options $options): void
    {
        $this->waitServerToBeStarted();
        $statusCode = ($this->fulfill)
            (
                new Request(
                    Url::of('http://localhost:7474/user/neo4j/password'),
                    Method::post(),
                    new ProtocolVersion(2, 0),
                    Headers::of(
                        ContentType::of('application', 'json'),
                        Authorization::of(
                            'Basic',
                            base64_encode('neo4j:neo4j'),
                        ),
                    ),
                    Stream::ofContent(json_encode([
                        'password' => $password = \sha1(\random_bytes(32)),
                    ]))
                )
            )
            ->statusCode();

        if ($statusCode->value() !== 200) {
            $env->exit(1);

            return;
        }

        ($this->dispatch)(new PasswordWasChanged('neo4j', $password));
    }

    public function toString(): string
    {
        return <<<USAGE
setup-user

This will change the password for the user 'neo4j'
USAGE;
    }

    private function waitServerToBeStarted(): void
    {
        do {
            $this->process->halt(new Second(1));

            $process = $this
                ->server
                ->processes()
                ->execute(
                    ServerCommand::foreground('service')
                        ->withArgument('neo4j')
                        ->withArgument('status')
                );
            $process->wait();
            $started = $process
                ->output()
                ->reduce(
                    false,
                    static function(bool $started, Str $line): bool {
                        return $started || $line->contains('Remote interface available');
                    }
                );
        } while (!$started);
    }
}
