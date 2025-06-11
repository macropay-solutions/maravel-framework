<?php

namespace Illuminate\Support\Facades;

use Closure;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Process\Factory;
use Illuminate\Process\FakeProcessResult;
use Illuminate\Process\InvokedProcess;
use Illuminate\Process\PendingProcess as PP;

/**
 * @method static PP command(array|string $command)
 * @method static PP path(string $path)
 * @method static PP timeout(int $timeout)
 * @method static PP idleTimeout(int $timeout)
 * @method static PP forever()
 * @method static PP env(array $environment)
 * @method static PP input(\Traversable|resource|string|int|float|bool|null $input)
 * @method static PP quietly()
 * @method static PP tty(bool $tty = true)
 * @method static PP options(array $options)
 * @method static ProcessResult run(array|string|null $command = null, callable|null $output = null)
 * @method static InvokedProcess start(array|string|null $command = null, callable|null $output = null)
 * @method static PP withFakeHandlers(array $fakeHandlers)
 * @method static PP|mixed when(\Closure|mixed|null$value=null,callable|null$callback=null,callable|null$default=null)
 * @method static PP|mixed unless(\Closure|mixed|null$value=null,callable|null$callback=null,callable|null$default=null)
 * @method static FakeProcessResult result(array|string $output = '', array|string $errorOutput = '', int $exitCode = 0)
 * @method static \Illuminate\Process\FakeProcessDescription describe()
 * @method static \Illuminate\Process\FakeProcessSequence sequence(array $processes = [])
 * @method static bool isRecording()
 * @method static \Illuminate\Process\Factory recordIfRecording(PP $process, ProcessResult $result)
 * @method static \Illuminate\Process\Factory record(PP $process, ProcessResult $result)
 * @method static \Illuminate\Process\Factory preventStrayProcesses(bool $prevent = true)
 * @method static bool preventingStrayProcesses()
 * @method static \Illuminate\Process\Factory assertRan(\Closure|string $callback)
 * @method static \Illuminate\Process\Factory assertRanTimes(\Closure|string $callback, int $times = 1)
 * @method static \Illuminate\Process\Factory assertNotRan(\Closure|string $callback)
 * @method static \Illuminate\Process\Factory assertDidntRun(\Closure|string $callback)
 * @method static \Illuminate\Process\Factory assertNothingRan()
 * @method static \Illuminate\Process\Pool pool(callable $callback)
 * @method static ProcessResult pipe(callable|array $callback, callable|null $output = null)
 * @method static \Illuminate\Process\ProcessPoolResults concurrently(callable $callback, callable|null $output = null)
 * @method static PP newPendingProcess()
 * @method static void macro(string $name, object|callable $macro)
 * @method static void mixin(object $mixin, bool $replace = true)
 * @method static bool hasMacro(string $name)
 * @method static void flushMacros()
 * @method static mixed macroCall(string $method, array $parameters)
 *
 * @see \Illuminate\Process\PP
 * @see \Illuminate\Process\Factory
 */
class Process extends Facade
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
     * Indicate that the process factory should fake processes.
     *
     * @param \Closure|array|null $callback
     * @return \Illuminate\Process\Factory
     */
    public static function fake(Closure|array|null $callback = null)
    {
        return tap(static::getFacadeRoot(), function ($fake) use ($callback) {
            static::swap($fake->fake($callback));
        });
    }
}
