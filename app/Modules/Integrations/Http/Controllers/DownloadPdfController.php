<?php

namespace App\Modules\Integrations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Connections\Core\Exceptions\ConnectionNotFoundException;
use App\Modules\Integrations\Application\Actions\DownloadPdfAction;
use App\Modules\Integrations\Application\DTOs\DownloadPdfInput;
use App\Modules\Integrations\Http\Requests\DownloadPdfRequest;
use Symfony\Component\HttpFoundation\Response;

class DownloadPdfController extends Controller
{
    public function __invoke(DownloadPdfRequest $request, string $documentId, DownloadPdfAction $action): Response
    {
        try {
            $pdfBinary = $action->execute(new DownloadPdfInput(
                userId: (int) $request->user()->id,
                documentId: $documentId,
                connectionId: $request->filled('connection_id') ? (int) $request->input('connection_id') : null,
            ));
        } catch (ConnectionNotFoundException) {
            return response()->json(['message' => 'Connection not found'], 404);
        }

        return response($pdfBinary)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="document.pdf"')
            ->header('Content-Length', strlen($pdfBinary));
    }
}
