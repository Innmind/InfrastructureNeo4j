<?php
declare(strict_types = 1);

namespace Innmind\Infrastructure\Neo4j\Event;

final class PasswordWasChanged
{
    private string $user;
    private string $password;

    public function __construct(string $user, string $password)
    {
        $this->user = $user;
        $this->password = $password;
    }

    public function user(): string
    {
        return $this->user;
    }

    public function password(): string
    {
        return $this->password;
    }
}
