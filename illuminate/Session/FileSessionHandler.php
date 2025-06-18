<?php

namespace Illuminate\Session;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use SessionHandlerInterface;
use Symfony\Component\Finder\Finder;

class FileSessionHandler implements SessionHandlerInterface
{
    /**
     * The filesystem instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    /**
     * The path where sessions should be stored.
     *
     * @var string
     */
    protected $path;

    /**
     * The number of minutes the session should be valid.
     *
     * @var int
     */
    protected $minutes;

    /**
     * Create a new file driven handler instance.
     *
     * @param \Illuminate\Filesystem\Filesystem $files
     * @param string $path
     * @param int $minutes
     * @return void
     */
    public function __construct(Filesystem $files, $path, $minutes)
    {
        $this->path = $path;
        $this->files = $files;
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
        if (
            $this->files->isFile($path = $this->path . '/' . $id) &&
            $this->files->lastModified($path) >= Carbon::now()->subMinutes($this->minutes)->getTimestamp()
        ) {
            return $this->files->sharedGet($path);
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
        $this->files->put($this->path . '/' . $id, $data, true);

        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @return bool
     */
    public function destroy($id): bool
    {
        $this->files->delete($this->path . '/' . $id);

        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @return int
     */
    public function gc($max_lifetime): int
    {
        $files = Finder::create()
            ->in($this->path)
            ->files()
            ->ignoreDotFiles(true)
            ->date('<= now - ' . $max_lifetime . ' seconds');

        $deletedSessions = 0;

        foreach ($files as $file) {
            $this->files->delete($file->getRealPath());
            $deletedSessions++;
        }

        return $deletedSessions;
    }
}
