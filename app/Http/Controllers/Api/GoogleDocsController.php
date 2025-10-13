<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
}