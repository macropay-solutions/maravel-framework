<?php

namespace Illuminate\Session;

use Illuminate\Contracts\Cache\Repository as CacheContract;
use SessionHandlerInterface;

class CacheBasedSessionHandler implements SessionHandlerInterface
{
    /**
     * The cache repository instance.
     *
     * @var \Illuminate\Contracts\Cache\Repository
     */
    protected $cache;

    /**
     * The number of minutes to store the data in the cache.
     *
     * @var int
     */
    protected $minutes;

    /**
     * Create a new cache driven handler instance.
     *
     * @param  \Illuminate\Contracts\Cache\Repository  $cache
     * @param  int  $minutes
     * @return void
     */
    public function __construct(CacheContract $cache, $minutes)
    {
        $this->cache = $cache;
        $this->minutes = $minutes;
    }

    /**
     * {@inheritdoc}
     *
     * @return bool
     */
    public function open($path, $name): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @return bool
     */
    public function close(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    public function read($id): string
    {
        return $this->cache->get($id, '');
    }

    /**
     * {@inheritdoc}
     *
     * @return bool
     */
    public function write($id, $data): bool
    {
        return $this->cache->put($id, $data, $this->minutes * 60);
    }

    /**
     * {@inheritdoc}
     *
     * @return bool
     */
    public function destroy($id): bool
    {
        return $this->cache->forget($id);
    }

    /**
     * {@inheritdoc}
     *
     * @return int
     */
    public function gc($max_lifetime): int
    {
        return 0;
    }

    /**
     * Get the underlying cache repository.
     *
     * @return \Illuminate\Contracts\Cache\Repository
     */
    public function getCache()
    {
        return $this->cache;
    }
}
