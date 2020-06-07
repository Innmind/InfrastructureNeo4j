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
use Innmind\TimeContinuum\Earth\Period\Second;
use Innmind\Immutable\Map;

final class SetupUser implements Gene
{
    public function name(): string
    {
        return 'Neo4j user setup';
    }

    public function express(
        OperatingSystem $local,
        Server $target,
        History $history
    ): History {
        try {
            $preCondition = new Script(
                Command::foreground('which')->withArgument('curl'),
            );
            $preCondition($target);
        } catch (ScriptFailed $e) {
            throw new PreConditionFailed('curl is missing');
        }

        do {
            $ready = false;
            $local->process()->halt(new Second(1));

            try {
                $wait = new Script(
                    Command::foreground('service')
                        ->withArgument('neo4j')
                        ->withArgument('status')
                        ->pipe(
                            Command::foreground('grep')
                                ->withArgument('Remote interface available'),
                        ),
                );
                $wait($target);
                $ready = true;
            } catch (ScriptFailed $e) {
                // not yet ready
            }
        } while (!$ready);

        try {
            $password = \sha1(\random_bytes(32));
            $changePassword = new Script(
                Command::foreground('curl')
                    ->withShortOption('X', 'POST')
                    ->withArgument('http://localhost:7474/user/neo4j/password')
                    ->withShortOption('H', 'Content-Type: application/json')
                    ->withShortOption('H', 'Authorization: Basic '.\base64_encode('neo4j:neo4j'))
                    ->withShortOption('d', "{\"password\":\"$password\"}"),
            );
            $changePassword($target);
        } catch (ScriptFailed $e) {
            throw new ExpressionFailed($this->name());
        }

        /** @var Map<string, mixed> */
        $payload = Map::of('string', 'mixed');

        return $history->add(
            'neo4j.password_changed',
            $payload
                ('user', 'neo4j')
                ('password', $password),
        );
    }
}
