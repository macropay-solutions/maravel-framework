<?php

namespace Illuminate\Session;

use Illuminate\Support\InteractsWithTime;
use SessionHandlerInterface;

class ArraySessionHandler implements SessionHandlerInterface
{
    use InteractsWithTime;

    /**
     * The array of stored values.
     *
     * @var array
     */
    protected $storage = [];

    /**
     * The number of minutes the session should be valid.
     *
     * @var int
     */
    protected $minutes;

    /**
     * Create a new array driven handler instance.
     *
     * @param int $minutes
     * @return void
     */
    public function __construct($minutes)
    {
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
     * @return string|false
     */
    public function read($id): string|false
    {
        if (!isset($this->storage[$id])) {
            return '';
        }

        $session = $this->storage[$id];

        $expiration = $this->calculateExpiration($this->minutes * 60);

        if (isset($session['time']) && $session['time'] >= $expiration) {
            return $session['data'];
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
        $this->storage[$id] = [
            'data' => $data,
            'time' => $this->currentTime(),
        ];

        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @return bool
     */
    public function destroy($id): bool
    {
        if (isset($this->storage[$id])) {
            unset($this->storage[$id]);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @return int
     */
    public function gc($max_lifetime): int
    {
        $expiration = $this->calculateExpiration($max_lifetime);

        $deletedSessions = 0;

        foreach ($this->storage as $sessionId => $session) {
            if ($session['time'] < $expiration) {
                unset($this->storage[$sessionId]);
                $deletedSessions++;
            }
        }

        return $deletedSessions;
    }

    /**
     * Get the expiration time of the session.
     *
     * @param int $seconds
     * @return int
     */
    protected function calculateExpiration($seconds)
    {
        return $this->currentTime() - $seconds;
    }
}
