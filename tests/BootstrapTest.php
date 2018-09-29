<?php
declare(strict_types = 1);

namespace Tests\Innmind\Infrastructure\Neo4j;

use function Innmind\Infrastructure\Neo4j\bootstrap;
use Innmind\CLI\Commands;
use PHPUnit\Framework\TestCase;

class BootstrapTest extends TestCase
{
    public function testBootstrap()
    {
        $this->assertInstanceOf(Commands::class, bootstrap());
    }
}
