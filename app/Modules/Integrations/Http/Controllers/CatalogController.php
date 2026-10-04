<?php

namespace App\Modules\Integrations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Integrations\Application\Services\CatalogProjector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function __invoke(Request $request, CatalogProjector $projector): JsonResponse
    {
        return response()->json([
            'message' => __('integrations::messages.catalog_retrieved'),
            'data' => $projector->project(),
        ]);
    }
}
