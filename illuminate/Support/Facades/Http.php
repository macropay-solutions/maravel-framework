<?php

namespace Illuminate\Support\Facades;

use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\RequestInterface;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest as PR;
use Illuminate\Http\Client\Response as ResponseAlias;
use Psr\Http\Message\StreamInterface;

/**
 * @method static \Illuminate\Http\Client\Factory globalMiddleware(callable $middleware)
 * @method static \Illuminate\Http\Client\Factory globalRequestMiddleware(callable $middleware)
 * @method static \Illuminate\Http\Client\Factory globalResponseMiddleware(callable $middleware)
 * @method static \Illuminate\Http\Client\Factory globalOptions(array $options)
 * @method static PromiseInterface response(array|string|null $body = null, int $status = 200, array $headers = [])
 * @method static \Illuminate\Http\Client\ResponseSequence sequence(array $responses = [])
 * @method static \Illuminate\Http\Client\Factory allowStrayRequests()
 * @method static void recordRequestResponsePair(\Illuminate\Http\Client\Request $request, ResponseAlias $response)
 * @method static void assertSent(callable $callback)
 * @method static void assertSentInOrder(array $callbacks)
 * @method static void assertNotSent(callable $callback)
 * @method static void assertNothingSent()
 * @method static void assertSentCount(int $count)
 * @method static void assertSequencesAreEmpty()
 * @method static \Illuminate\Support\Collection recorded(callable $callback = null)
 * @method static \Illuminate\Contracts\Events\Dispatcher|null getDispatcher()
 * @method static array getGlobalMiddleware()
 * @method static void macro(string $name, object|callable $macro)
 * @method static void mixin(object $mixin, bool $replace = true)
 * @method static bool hasMacro(string $name)
 * @method static void flushMacros()
 * @method static mixed macroCall(string $method, array $parameters)
 * @method static PR baseUrl(string $url)
 * @method static PR withBody(StreamInterface|string $content, string $contentType = 'application/json')
 * @method static PR asJson()
 * @method static PR asForm()
 * @method static PR attach(string|array$name,string|resource$contents='',string|null$filename=null,array$headers=[])
 * @method static PR asMultipart()
 * @method static PR bodyFormat(string $format)
 * @method static PR withQueryParameters(array $parameters)
 * @method static PR contentType(string $contentType)
 * @method static PR acceptJson()
 * @method static PR accept(string $contentType)
 * @method static PR withHeaders(array $headers)
 * @method static PR withHeader(string $name, mixed $value)
 * @method static PR replaceHeaders(array $headers)
 * @method static PR withBasicAuth(string $username, string $password)
 * @method static PR withDigestAuth(string $username, string $password)
 * @method static PR withToken(string $token, string $type = 'Bearer')
 * @method static PR withUserAgent(string|bool $userAgent)
 * @method static PR withUrlParameters(array $parameters = [])
 * @method static PR withCookies(array $cookies, string $domain)
 * @method static PR maxRedirects(int $max)
 * @method static PR withoutRedirecting()
 * @method static PR withoutVerifying()
 * @method static PR sink(string|resource $to)
 * @method static PR timeout(int $seconds)
 * @method static PR connectTimeout(int $seconds)
 * @method static PR retry(array|int$times,\Closure|int$sleepMilliseconds=0,callable|null$when=null,bool$throw=true)
 * @method static PR withOptions(array $options)
 * @method static PR withMiddleware(callable $middleware)
 * @method static PR withRequestMiddleware(callable $middleware)
 * @method static PR withResponseMiddleware(callable $middleware)
 * @method static PR beforeSending(callable $callback)
 * @method static PR throw(callable|null $callback = null)
 * @method static PR throwIf(callable|bool $condition, callable|null $throwCallback = null)
 * @method static PR throwUnless(bool $condition)
 * @method static PR dump()
 * @method static PR dd()
 * @method static ResponseAlias get(string $url, array|string|null $query = null)
 * @method static ResponseAlias head(string $url, array|string|null $query = null)
 * @method static ResponseAlias post(string $url, array $data = [])
 * @method static ResponseAlias patch(string $url, array $data = [])
 * @method static ResponseAlias put(string $url, array $data = [])
 * @method static ResponseAlias delete(string $url, array $data = [])
 * @method static array pool(callable $callback)
 * @method static ResponseAlias send(string $method, string $url, array $options = [])
 * @method static \GuzzleHttp\Client buildClient()
 * @method static \GuzzleHttp\Client createClient(\GuzzleHttp\HandlerStack $handlerStack)
 * @method static \GuzzleHttp\HandlerStack buildHandlerStack()
 * @method static \GuzzleHttp\HandlerStack pushHandlers(\GuzzleHttp\HandlerStack $handlerStack)
 * @method static \Closure buildBeforeSendingHandler()
 * @method static \Closure buildRecorderHandler()
 * @method static \Closure buildStubHandler()
 * @method static RequestInterface runBeforeSendingCallbacks(RequestInterface $request, array $options)
 * @method static array mergeOptions(array ...$options)
 * @method static PR stub(callable $callback)
 * @method static PR async(bool $async = true)
 * @method static PromiseInterface|null getPromise()
 * @method static PR setClient(\GuzzleHttp\Client $client)
 * @method static PR setHandler(callable $handler)
 * @method static array getOptions()
 * @method static PR|mixed when(\Closure|mixed|null$value=null,callable|null$callback=null,callable|null$default=null)
 * @method static PR|mixed unless(\Closure|mixed|null$value=null,callable|null$callback=null,callable|null$default=null)
 *
 * @see \Illuminate\Http\Client\Factory
 */
class Http extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return Factory::class;
    }

    /**
     * Register a stub callable that will intercept requests and be able to return stub responses.
     *
     * @param \Closure|array $callback
     * @return \Illuminate\Http\Client\Factory
     */
    public static function fake($callback = null)
    {
        return tap(static::getFacadeRoot(), function ($fake) use ($callback) {
            static::swap($fake->fake($callback));
        });
    }

    /**
     * Register a response sequence for the given URL pattern.
     *
     * @param string $urlPattern
     * @return \Illuminate\Http\Client\ResponseSequence
     */
    public static function fakeSequence(string $urlPattern = '*')
    {
        $fake = tap(static::getFacadeRoot(), function ($fake) {
            static::swap($fake);
        });

        return $fake->fakeSequence($urlPattern);
    }

    /**
     * Indicate that an exception should be thrown if any request is not faked.
     *
     * @return \Illuminate\Http\Client\Factory
     */
    public static function preventStrayRequests()
    {
        return tap(static::getFacadeRoot(), function ($fake) {
            static::swap($fake->preventStrayRequests());
        });
    }

    /**
     * Stub the given URL using the given callback.
     *
     * @param string $url
     * @param ResponseAlias|PromiseInterface|callable $callback
     * @return \Illuminate\Http\Client\Factory
     */
    public static function stubUrl($url, $callback)
    {
        return tap(static::getFacadeRoot(), function ($fake) use ($url, $callback) {
            static::swap($fake->stubUrl($url, $callback));
        });
    }
}
