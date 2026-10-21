<?php

namespace App\Modules\Execution\Core\Exceptions;

use RuntimeException;

final class TestEmailUnavailableException extends RuntimeException
{
    public const ERROR_NO_EMAILS = 'no_test_email_available';
    public const ERROR_NO_EMAILS_WITH_ATTACHMENTS = 'no_test_email_with_attachments_available';

    /**
     * @param  array<string, mixed>  $context
     */
    private function __construct(
        private readonly string $errorCode,
        private readonly array $context,
        string $message,
    ) {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }

    public static function noEmailsInInbox(): self
    {
        return new self(
            self::ERROR_NO_EMAILS,
            ['requires_attachment' => false],
            'No emails found in the connected Gmail account to use for testing. Please send an email first.',
        );
    }

    public static function noEmailsWithAttachments(): self
    {
        return new self(
            self::ERROR_NO_EMAILS_WITH_ATTACHMENTS,
            ['requires_attachment' => true],
            'No emails with attachments found in the connected Gmail account. Please send an email with an attachment to test this workflow.',
        );
    }
}
