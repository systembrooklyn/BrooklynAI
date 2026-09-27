<?php

namespace App\Modules\Identity\Application\DTOs;

final class LoginInput
{
    public function __construct(
        public readonly string $email,
        public readonly string $password,
    ) {}
}
