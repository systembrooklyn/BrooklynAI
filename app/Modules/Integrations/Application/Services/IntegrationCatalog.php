<?php

namespace App\Modules\Integrations\Application\Services;

use App\Modules\Integrations\Core\Contracts\IntegrationProvider;
use App\Modules\Integrations\Core\Entities\IntegrationDefinition;
use App\Modules\Integrations\Core\Exceptions\DuplicateIntegrationKey;
use App\Modules\Integrations\Core\Exceptions\IntegrationNotFound;

final class IntegrationCatalog
{
    /** @var array<string, IntegrationDefinition> */
    private array $definitions = [];

    /**
     * @param  iterable<IntegrationProvider>  $providers
     */
    public function __construct(iterable $providers)
    {
        foreach ($providers as $provider) {
            $definition = $provider->definition();
            $key = $definition->key->value;

            if (isset($this->definitions[$key])) {
                throw DuplicateIntegrationKey::forKey($key);
            }

            $this->definitions[$key] = $definition;
        }
    }

    /**
     * @return IntegrationDefinition[]
     */
    public function all(): array
    {
        return array_values($this->definitions);
    }

    public function find(string $key): ?IntegrationDefinition
    {
        return $this->definitions[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return isset($this->definitions[$key]);
    }

    public function require(string $key): IntegrationDefinition
    {
        $definition = $this->find($key);

        if ($definition === null) {
            throw IntegrationNotFound::forKey($key);
        }

        return $definition;
    }
}
