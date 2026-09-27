<?php

namespace App\Modules\Execution\Http\Resources;

use App\Modules\Execution\Application\DTOs\ExecutionStepData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExecutionStepResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var ExecutionStepData $data */
        $data = $this->resource;

        return $data->toArray();
    }
}
