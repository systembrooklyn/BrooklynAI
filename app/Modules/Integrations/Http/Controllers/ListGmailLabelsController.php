<?php

namespace App\Modules\Integrations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Connections\Core\Exceptions\ConnectionNotFoundException;
use App\Modules\Connections\Core\Exceptions\GoogleCredentialsUnavailableException;
use App\Modules\Integrations\Application\Actions\ListGmailLabelsAction;
use App\Modules\Integrations\Application\DTOs\ListGmailLabelsInput;
use App\Modules\Integrations\Core\Exceptions\GmailProviderException;
use App\Modules\Integrations\Http\Requests\ListGmailLabelsRequest;
use Illuminate\Http\JsonResponse;

class ListGmailLabelsController extends Controller
{
    public function __invoke(
        ListGmailLabelsRequest $request,
        ListGmailLabelsAction $action,
    ): JsonResponse {
        try {
            $labels = $action->execute(new ListGmailLabelsInput(
                userId: (int) $request->user()->id,
                connectionId: $request->filled('connection_id')
                    ? (int) $request->input('connection_id')
                    : null,
            ));

            return response()->json([
                'message' => __('integrations::messages.labels_retrieved'),
                'data' => $labels,
            ]);
        } catch (ConnectionNotFoundException) {
            return response()->json([
                'message' => __('integrations::messages.connection_not_found'),
            ], 404);
        } catch (GoogleCredentialsUnavailableException) {
            return response()->json([
                'message' => __('integrations::messages.google_credentials_unavailable'),
            ], 500);
        } catch (GmailProviderException $e) {
            return response()->json([
                'message' => __('integrations::messages.labels_retrieval_failed'),
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
