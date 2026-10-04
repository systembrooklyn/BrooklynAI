<?php

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Application\DTOs\RequestPasswordResetInput;
use App\Modules\Identity\Application\Services\PasswordResetService;

final class RequestPasswordResetAction
{
    public function __construct(
        private readonly PasswordResetService $service,
    ) {}

    public function execute(RequestPasswordResetInput $input): void
    {
        $this->service->requestReset($input->email);
    }
}
