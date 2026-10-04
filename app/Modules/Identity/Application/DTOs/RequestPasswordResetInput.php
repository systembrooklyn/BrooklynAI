<?php

namespace App\Modules\Identity\Application\DTOs;

final class RequestPasswordResetInput
{
    public function __construct(
        public readonly string $email,
    ) {}
}
