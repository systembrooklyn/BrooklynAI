<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GmailService;
use App\Services\GoogleDocsService;
use Illuminate\Http\Request;

class GoogleDocsController extends Controller
{
    public function create(Request $request)
    {
        $request->validate(['title' => 'nullable|string|max:255']);
        $title = $request->input('title', 'Untitled Document');

        $service = new GoogleDocsService($request->user());
        $doc = $service->createDocument($title);

        return response()->json($doc);
    }

    public function show(Request $request, string $documentId)
    {
        $service = new GoogleDocsService($request->user());
        $doc = $service->getDocument($documentId);

        return response()->json($doc);
    }

    public function append(Request $request, string $documentId)
    {
        $request->validate(['text' => 'required|string']);

        $service = new GoogleDocsService($request->user());
        $result = $service->appendText($documentId, $request->text);

        return response()->json($result);
    }

    public function index(Request $request)
    {
        $service = new GoogleDocsService($request->user());
        $documents = $service->listAllDocuments();

        return response()->json([
            'message' => 'Google Docs retrieved successfully.',
            'data' => $documents,
        ]);
    }
    public function update(Request $request, string $documentId)
    {
        $request->validate(['content' => 'required|string']);

        $service = new GoogleDocsService($request->user());
        $result = $service->updateDocument($documentId, $request->content);

        return response()->json([
            'message' => 'DOC Updated Successfully',
            'data' => $result
        ]);
    }

    public function delete(Request $request, string $documentId)
    {
        $service = new GoogleDocsService($request->user());
        $result = $service->deleteDocument($documentId);

        return response()->json([
            'message' => 'DOC deleted Successfully',
            'data' => $result
        ]);
    }

    public function downloadPdf(Request $request, string $documentId)
    {
        $service = new GoogleDocsService($request->user());
        $pdfBinary = $service->exportDocAsPdf($documentId, true);

        return response($pdfBinary)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="document.pdf"')
            ->header('Content-Length', strlen($pdfBinary));
    }

    public function generateFromTemplate(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'service' => 'required|string',
            'sign' => 'required|string',
            'title' => 'nullable|string',
        ]);

        $service = new GoogleDocsService($request->user());
        $doc = $service->createPersonalizedDoc(
            [
                'name' => $request->name,
                'service' => $request->service,
                'sign' => $request->sign,
            ],
            $request->title ?? 'Personalized Letter'
        );

        return response()->json($doc);
    }
    public function generateAndEmailPdf(Request $request)
    {
        $request->validate([
            'to' => 'required|array',
            'to.*' => 'email',
            'subject' => 'required|string',
            'body' => 'nullable|string', // optional body
            'data' => 'required|array', // e.g., ['name' => 'Ahmed', 'service' => 'Premium']
            'filename' => 'nullable|string|max:255',
        ]);

        $user = $request->user();
        $toEmails = $request->input('to');
        $subject = $request->input('subject');
        $body = $request->input('body', '');
        $filename = $request->input('filename', 'document.pdf');

        try {
            // 1. Generate personalized doc
            $docsService = new GoogleDocsService($user);
            $doc = $docsService->createPersonalizedDoc($request->data, 'Temp Doc for Email');

            // 2. Get PDF binary
            $pdfBinary = $docsService->getDocAsPdfBinary($doc['id']);

            // 3. Send email with PDF attachment
            $gmailService = new GmailService ($user);
            $gmailService->sendEmailWithAttachment(
                toEmails: $toEmails,
                subject: $subject,
                htmlBody: $body ?: '<p>Please find the attached document.</p>',
                pdfBinary: $pdfBinary,
                filename: $filename
            );

            // Optional: Delete the temporary doc (if you don't need to keep it)
            // $docsService->deleteDocument($doc['id']);

            return response()->json([
                'message' => 'Document generated and emailed successfully!',
                'sent_to' => $toEmails,
                'doc_id' => $doc['id'], // keep if you want to track it
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Generate & Email PDF Error', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to generate and send document',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
