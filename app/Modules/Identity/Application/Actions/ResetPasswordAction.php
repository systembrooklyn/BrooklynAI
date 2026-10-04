<?php

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Application\DTOs\ResetPasswordInput;
use App\Modules\Identity\Application\Services\PasswordResetService;
use App\Modules\Identity\Core\Exceptions\InvalidPasswordResetCodeException;

final class ResetPasswordAction
{
    public function __construct(
        private readonly PasswordResetService $service,
    ) {}

    /**
     * @throws InvalidPasswordResetCodeException
     */
    public function execute(ResetPasswordInput $input): void
    {
        $this->service->resetPassword($input->email, $input->code, $input->password);
    }
}
