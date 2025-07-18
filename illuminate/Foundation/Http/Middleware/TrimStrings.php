<?php

namespace Illuminate\Foundation\Http\Middleware;

use Closure;

class TrimStrings extends TransformsRequest
{
    /**
     * All of the registered skip callbacks.
     *
     * @var array
     */
    protected static $skipCallbacks = [];

    /**
     * The attributes that should not be trimmed.
     *
     * @var array<int, string>
     */
    protected $except = [
        //
    ];

    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        foreach (static::$skipCallbacks as $callback) {
            if ($callback($request)) {
                return $next($request);
            }
        }

        return parent::handle($request, $next);
    }

    /**
     * Transform the given value.
     *
     * @param string $key
     * @param mixed $value
     * @return mixed
     */
    protected function transform($key, $value)
    {
        if (in_array($key, $this->except, true) || !is_string($value)) {
            return $value;
        }

        return preg_replace('~^[\s\x{FEFF}\x{200B}]+|[\s\x{FEFF}\x{200B}]+$~u', '', $value) ?? trim($value);
    }

    /**
     * Register a callback that instructs the middleware to be skipped.
     *
     * @param \Closure $callback
     * @return void
     */
    public static function skipWhen(Closure $callback)
    {
        static::$skipCallbacks[] = $callback;
    }

    /**
     * Flush the middleware's global state.
     *
     * @return void
     */
    public static function flushState()
    {
        static::$skipCallbacks = [];
    }
}
