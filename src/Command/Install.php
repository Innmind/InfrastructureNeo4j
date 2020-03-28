<?php
declare(strict_types = 1);

namespace Innmind\Infrastructure\Neo4j\Command;

use Innmind\CLI\{
    Command,
    Command\Arguments,
    Command\Options,
    Environment,
};
use Innmind\Server\Control\{
    Server,
    Server\Command as ServerCommand,
    Server\Process\ExitCode,
};
use Innmind\Immutable\{
    Stream,
    Str,
};

final class Install implements Command
{
    private Server $server;
    private Stream $actions;

    public function __construct(Server $server)
    {
        $this->server = $server;
        $this->actions = Stream::of(
            'string',
            'wget -O - https://debian.neo4j.org/neotechnology.gpg.key | apt-key add -',
            'echo \'deb https://debian.neo4j.org/repo stable/\' | tee /etc/apt/sources.list.d/neo4j.list',
            'apt-get update',
            'apt-get install neo4j -y',
            'sed \'-i.bak\' \'s/#dbms.connectors.default_listen_address=0.0.0.0/dbms.connectors.default_listen_address=0.0.0.0/g\' \'/etc/neo4j/neo4j.conf\'',
            'sed \'-i.bak\' \'s/#dbms.connectors.default_advertised_address=localhost/dbms.connectors.default_advertised_address=0.0.0.0/g\' \'/etc/neo4j/neo4j.conf\'',
            'service neo4j restart'
        );
    }

    public function __invoke(Environment $env, Arguments $arguments, Options $options): void
    {
        $processes = $this->server->processes();
        $output = $env->output();
        $exitCode = $this->actions->reduce(
            new ExitCode(0),
            static function(ExitCode $exitCode, string $action) use ($processes, $output): ExitCode {
                if (!$exitCode->isSuccessful()) {
                    return $exitCode;
                }

                $output->write(Str::of($action)->append("\n"));

                return $processes
                    ->execute(ServerCommand::foreground($action))
                    ->wait()
                    ->exitCode();
            }
        );
        $env->exit($exitCode->toInt());
    }

    public function __toString(): string
    {
        return <<<USAGE
install

This will install the neo4j server on the machine
USAGE;
    }
}
