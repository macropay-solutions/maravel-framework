<?php

namespace Illuminate\Cache;

use Illuminate\Contracts\Cache\Store;

abstract class TaggableStore implements Store
{
    /**
     * Begin executing a new tags operation.
     *
     * @param array|mixed $names
     * @return \Illuminate\Cache\TaggedCache
     */
    public function tags($names)
    {
//        return new TaggedCache($this, new TagSet($this, is_array($names) ? $names : func_get_args()));
        return \app(TaggedCache::class, [$this, new TagSet($this, is_array($names) ? $names : func_get_args())]);
    }
}
