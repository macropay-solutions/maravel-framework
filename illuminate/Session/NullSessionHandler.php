<?php

namespace Illuminate\Session;

use SessionHandlerInterface;

class NullSessionHandler implements SessionHandlerInterface
{
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
        return '';
    }

    /**
     * {@inheritdoc}
     *
     * @return bool
     */
    public function write($id, $data): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @return bool
     */
    public function destroy($id): bool
    {
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
}
