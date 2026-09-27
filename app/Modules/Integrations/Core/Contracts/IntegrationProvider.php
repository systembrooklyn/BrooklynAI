<?php

namespace App\Modules\Integrations\Core\Contracts;

use App\Modules\Integrations\Core\Entities\IntegrationDefinition;

interface IntegrationProvider
{
    public function definition(): IntegrationDefinition;
}
