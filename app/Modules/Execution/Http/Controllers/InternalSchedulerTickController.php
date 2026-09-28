<?php

namespace App\Modules\Execution\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Execution\Application\Services\SchedulerTickService;
use Illuminate\Http\JsonResponse;

final class InternalSchedulerTickController extends Controller
{
    public function __invoke(SchedulerTickService $service): JsonResponse
    {
        return response()->json($service->tick()->toArray());
    }
}
