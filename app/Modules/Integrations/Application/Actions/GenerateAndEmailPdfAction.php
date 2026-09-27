<?php

namespace App\Modules\Integrations\Application\Actions;

use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Integrations\Application\DTOs\GenerateAndEmailPdfInput;
use App\Modules\Integrations\Infrastructure\Google\Docs\GoogleDocsClient;
use App\Modules\Integrations\Infrastructure\Google\Gmail\GmailEmailSender;

final class GenerateAndEmailPdfAction
{
    public function __construct(
        private readonly GoogleCredentialsResolver $credentials,
        private readonly GoogleDocsClient $docs,
        private readonly GmailEmailSender $gmail,
    ) {}

    public function execute(GenerateAndEmailPdfInput $input): array
    {
        $resolved = $this->credentials->resolve($input->userId, $input->connectionId);

        $doc = $this->docs->createPersonalizedDoc($resolved, $input->data, 'Temp Doc for Email');

        $pdfBinary = $this->docs->getDocAsPdfBinary($resolved, $doc['id']);

        $this->gmail->sendWithAttachment(
            credentials: $resolved,
            fromEmail: $input->userEmail,
            toEmails: $input->toEmails,
            subject: $input->subject,
            htmlBody: $input->body ?: '<p>Please find the attached document.</p>',
            pdfBinary: $pdfBinary,
            filename: $input->filename ?? 'document.pdf',
        );

        return [
            'message' => 'Document generated and emailed successfully!',
            'sent_to' => $input->toEmails,
            'doc_id' => $doc['id'],
        ];
    }
}
