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
    Message\Method\Method,
    ProtocolVersion\ProtocolVersion,
    Headers\Headers,
    Header\ContentType,
    Header\ContentTypeValue,
    Header\Authorization,
    Header\AuthorizationValue,
};
use Innmind\Url\Url;
use Innmind\Filesystem\Stream\StringStream;
use Innmind\EventBus\EventBus;
use Innmind\Immutable\Str;

final class SetupUser implements Command
{
    private $server;
    private $fulfill;
    private $dispatch;

    public function __construct(
        Server $server,
        Transport $fulfill,
        EventBus $dispatch
    ) {
        $this->server = $server;
        $this->fulfill = $fulfill;
        $this->dispatch = $dispatch;
    }

    public function __invoke(Environment $env, Arguments $arguments, Options $options): void
    {
        $this->waitServerToBeStarted();
        $statusCode = ($this->fulfill)(
                new Request(
                    Url::fromString('http://localhost:7474/user/neo4j/password'),
                    Method::post(),
                    new ProtocolVersion(2, 0),
                    Headers::of(
                        new ContentType(
                            new ContentTypeValue('application', 'json')
                        ),
                        new Authorization(
                            new AuthorizationValue(
                                'Basic',
                                base64_encode('neo4j:neo4j')
                            )
                        )
                    ),
                    new StringStream(json_encode([
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

    public function __toString(): string
    {
        return <<<USAGE
setup-user

This will change the password for the user 'neo4j'
USAGE;
    }

    private function waitServerToBeStarted(): void
    {
        do {
            sleep(1);

            $started = $this
                ->server
                ->processes()
                ->execute(
                    ServerCommand::foreground('service')
                        ->withArgument('neo4j')
                        ->withArgument('status')
                )
                ->wait()
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
