<?php

namespace App\Modules\Identity\Application\DTOs;

final class ResetPasswordInput
{
    public function __construct(
        public readonly string $email,
        public readonly string $code,
        public readonly string $password,
    ) {}
}
