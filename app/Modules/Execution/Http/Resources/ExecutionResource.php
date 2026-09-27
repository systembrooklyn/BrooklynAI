<?php

namespace App\Modules\Execution\Http\Resources;

use App\Modules\Execution\Application\DTOs\ExecutionData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExecutionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var ExecutionData $data */
        $data = $this->resource;

        return $data->toArray();
    }
}
