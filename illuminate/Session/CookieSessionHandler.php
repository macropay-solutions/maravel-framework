<?php

namespace Illuminate\Session;

use Illuminate\Contracts\Cookie\QueueingFactory as CookieJar;
use Illuminate\Support\InteractsWithTime;
use SessionHandlerInterface;
use Symfony\Component\HttpFoundation\Request;

class CookieSessionHandler implements SessionHandlerInterface
{
    use InteractsWithTime;

    /**
     * The cookie jar instance.
     *
     * @var \Illuminate\Contracts\Cookie\Factory
     */
    protected $cookie;

    /**
     * The request instance.
     *
     * @var \Symfony\Component\HttpFoundation\Request
     */
    protected $request;

    /**
     * The number of minutes the session should be valid.
     *
     * @var int
     */
    protected $minutes;

    /**
     * Indicates whether the session should be expired when the browser closes.
     *
     * @var bool
     */
    protected $expireOnClose;

    /**
     * Create a new cookie driven handler instance.
     *
     * @param \Illuminate\Contracts\Cookie\QueueingFactory $cookie
     * @param int $minutes
     * @param bool $expireOnClose
     * @return void
     */
    public function __construct(CookieJar $cookie, $minutes, $expireOnClose = false)
    {
        $this->cookie = $cookie;
        $this->minutes = $minutes;
        $this->expireOnClose = $expireOnClose;
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
     * @return string|false
     */
    public function read($id): string|false
    {
        $value = $this->request->cookies->get($id) ?: '';

        if (
            !is_null($decoded = json_decode($value, true)) && is_array($decoded) &&
            isset($decoded['expires']) && $this->currentTime() <= $decoded['expires']
        ) {
            return $decoded['data'];
        }

        return '';
    }

    /**
     * {@inheritdoc}
     *
     * @return bool
     */
    public function write($id, $data): bool
    {
        $this->cookie->queue($id, json_encode([
            'data' => $data,
            'expires' => $this->availableAt($this->minutes * 60),
        ]), $this->expireOnClose ? 0 : $this->minutes);

        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @return bool
     */
    public function destroy($id): bool
    {
        $this->cookie->queue($this->cookie->forget($id));

        return true;
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
     * Set the request instance.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return void
     */
    public function setRequest(Request $request)
    {
        $this->request = $request;
    }
}
