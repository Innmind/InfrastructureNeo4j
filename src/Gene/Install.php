<?php
declare(strict_types = 1);

namespace Innmind\Infrastructure\Neo4j\Gene;

use Innmind\Genome\{
    Gene,
    History,
    Exception\PreConditionFailed,
    Exception\ExpressionFailed,
};
use Innmind\OperatingSystem\OperatingSystem;
use Innmind\Server\Control\{
    Server,
    Server\Command,
    Server\Script,
    Exception\ScriptFailed,
};

final class Install implements Gene
{
    public function name(): string
    {
        return 'Neo4j install';
    }

    public function express(
        OperatingSystem $local,
        Server $target,
        History $history
    ): History {
        try {
            $preCondition = new Script(
                Command::foreground('which')->withArgument('apt'),
            );
            $preCondition($target);
        } catch (ScriptFailed $e) {
            throw new PreConditionFailed('apt is missing');
        }

        try {
            $install = new Script(
                Command::foreground('wget')
                    ->withArgument('install')
                    ->withShortOption('O', '-')
                    ->withArgument('https://debian.neo4j.org/neotechnology.gpg.key')
                    ->pipe(
                        Command::foreground('apt-key')
                            ->withArgument('add')
                            ->withArgument('-'),
                    ),
                Command::foreground('echo')
                    ->withArgument('deb https://debian.neo4j.org/repo stable/')
                    ->pipe(
                        Command::foreground('tee')
                            ->withArgument('/etc/apt/sources.list.d/neo4j.list'),
                    ),
                Command::foreground('apt')->withArgument('update'),
                Command::foreground('apt')
                    ->withArgument('install')
                    ->withShortOption('y')
                    ->withArgument('neo4j'),
                Command::foreground('sed')
                    ->withShortOption('i.bak')
                    ->withArgument('s/#dbms.connectors.default_listen_address=0.0.0.0/dbms.connectors.default_listen_address=0.0.0.0/g')
                    ->withArgument('/etc/neo4j/neo4j.conf'),
                Command::foreground('sed')
                    ->withShortOption('i.bak')
                    ->withArgument('s/#dbms.connectors.default_advertised_address=localhost/dbms.connectors.default_advertised_address=0.0.0.0/g')
                    ->withArgument('/etc/neo4j/neo4j.conf'),
                Command::foreground('service')
                    ->withArgument('neo4j')
                    ->withArgument('restart'),
            );
            $install($target);
        } catch (ScriptFailed $e) {
            throw new ExpressionFailed($this->name());
        }

        return $history;
    }
}
