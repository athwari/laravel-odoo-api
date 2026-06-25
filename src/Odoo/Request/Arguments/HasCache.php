<?php

namespace Athwari\LaravelOdooApi\Odoo\Request\Arguments;

use DateInterval;
use DateTimeInterface;

trait HasCache
{
    /**
     * The number of seconds or Time to Live for the cache.
     *
     * @var \DateTimeInterface|\DateInterval|int|null
     */
    protected $cacheTtl;

    /**
     * The explicit cache key.
     */
    protected ?string $cacheKey = null;

    /**
     * Indicate that the query results should be cached.
     *
     * @return $this
     */
    public function cache(DateTimeInterface|DateInterval|int $ttl, ?string $key = null): static
    {
        $this->cacheTtl = $ttl;
        $this->cacheKey = $key;

        return $this;
    }

    /**
     * Generate a unique cache key for the current query builder parameters.
     *
     * @param  string  $operation  e.g., 'get', 'count'
     */
    protected function generateCacheKey(string $operation): string
    {
        if ($this->cacheKey !== null) {
            // If the user explicitly provided a key, we append the operation so that
            // a custom cached 'count' doesn't collide with a custom cached 'get' using the same user-key.
            return "odoo_cache:{$this->cacheKey}:{$operation}";
        }

        $components = [
            'connection' => $this->endpoint->getConfig()->getHost().'|'.$this->endpoint->getConfig()->getDatabase(),
            'model' => $this->model,
            'domain' => $this->domain->toArray(),
            'fields' => $this->fields,
            'offset' => $this->offset,
            'limit' => $this->limit,
            'order' => $this->getOrderString(),
            'group_by' => $this->groupBy ?? [],
            'options' => $this->options->toArray(),
            'operation' => $operation,
        ];

        return 'odoo_cache:'.md5(json_encode($components));
    }
}
