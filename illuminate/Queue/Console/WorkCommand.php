<?php

namespace Illuminate\Queue\Console;

use Carbon\CarbonInterval;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobReleasedAfterException;
use Illuminate\Queue\Worker;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Carbon;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Terminal;

use function Termwind\terminal;

#[AsCommand(name: 'queue:work')]
class WorkCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'queue:work
        {connection? : The name of the queue connection to work}
        {--name=default : The name of the worker}
        {--queue= : The names of the queues to work}
        {--daemon : Run the worker in daemon mode (Deprecated)}
        {--once : Only process the next job on the queue}
        {--stop-when-empty : Stop when the queue is empty}
        {--delay=0 : The number of seconds to delay failed jobs (Deprecated)}
        {--backoff=0 : The number of seconds to wait before retrying a job that encountered an uncaught exception}
        {--max-jobs=0 : The number of jobs to process before stopping}
        {--max-time=0 : The maximum number of seconds the worker should run}
        {--force : Force the worker to run even in maintenance mode}
        {--memory=128 : The memory limit in megabytes}
        {--sleep=3 : Number of seconds to sleep when no job is available}
        {--rest=0 : Number of seconds to rest between jobs}
        {--timeout=60 : The number of seconds a child process can run}
        {--tries=1 : Number of times to attempt a job before logging it failed}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start processing jobs on the queue as a daemon';

    /**
     * The queue worker instance.
     *
     * @var \Illuminate\Queue\Worker
     */
    protected $worker;

    /**
     * The cache store implementation.
     *
     * @var \Illuminate\Contracts\Cache\Repository
     */
    protected $cache;

    /**
     * Holds the start time of the last processed job, if any.
     *
     * @var float|null
     */
    protected $latestStartedAt;

    /**
     * Create a new queue work command.
     *
     * @param \Illuminate\Queue\Worker $worker
     * @param \Illuminate\Contracts\Cache\Repository $cache
     * @return void
     */
    public function __construct(Worker $worker, Cache $cache)
    {
        parent::__construct();

        $this->cache = $cache;
        $this->worker = $worker;
    }

    /**
     * Execute the console command.
     *
     * @return int|null
     */
    public function handle()
    {
        if ($this->downForMaintenance() && $this->option('once')) {
            $this->worker->sleep($this->option('sleep'));

            return null;
        }

        // We'll listen to the processed and failed events so we can write information
        // to the console as jobs are processed, which will let the developer watch
        // which jobs are coming through a queue and be informed on its progress.
        $this->listenForEvents();

        $connection = $this->argument('connection')
            ?: $this->laravel['config']['queue.default'];

        // We need to get the right queue for the connection which is set in the queue
        // configuration file for the application. We will pull it based on the set
        // connection being run for the queue operation currently being executed.
        $queue = $this->getQueue($connection);

        if (Terminal::hasSttyAvailable()) {
            $this->components->info(
                sprintf('Processing jobs from the [%s] %s.', $queue, str('queue')->plural(explode(',', $queue)))
            );
        }

        return $this->runWorker(
            $connection,
            $queue
        );
    }

    /**
     * Run the worker instance.
     *
     * @param string $connection
     * @param string $queue
     * @return int|null
     */
    protected function runWorker($connection, $queue)
    {
        $workerOptions = $this->gatherWorkerOptions();
        \ini_set('memory_limit', $workerOptions->memory . 'M');

        return $this->worker
            ->setName($workerOptions->name)
            ->setCache($this->cache)
            ->{$this->option('once') ? 'runNextJob' : 'daemon'}($connection, $queue, $workerOptions);
    }

    /**
     * Gather all of the queue worker options as a single object.
     *
     * @return \Illuminate\Queue\WorkerOptions
     */
    protected function gatherWorkerOptions()
    {
        return new WorkerOptions(
            $this->option('name'),
            max($this->option('backoff'), $this->option('delay')),
            $this->option('memory'),
            $this->option('timeout'),
            $this->option('sleep'),
            $this->option('tries'),
            $this->option('force'),
            $this->option('stop-when-empty'),
            $this->option('max-jobs'),
            $this->option('max-time'),
            $this->option('rest')
        );
    }

    /**
     * Listen for the queue events in order to update the console output.
     *
     * @return void
     */
    protected function listenForEvents()
    {
        $this->laravel['events']->listen(JobProcessing::class, function ($event) {
            $this->writeOutput($event->job, 'starting');
        });

        $this->laravel['events']->listen(JobProcessed::class, function ($event) {
            $this->writeOutput($event->job, 'success');
        });

        $this->laravel['events']->listen(JobReleasedAfterException::class, function ($event) {
            $this->writeOutput($event->job, 'released_after_exception');
        });

        $this->laravel['events']->listen(JobFailed::class, function ($event) {
            $this->writeOutput($event->job, 'failed');

            $this->logFailedJob($event);
        });
    }

    /**
     * Write the status output for the queue worker.
     *
     * @param \Illuminate\Contracts\Queue\Job $job
     * @param string $status
     * @return void
     */
    protected function writeOutput(Job $job, $status)
    {
        $this->output->write(
            sprintf(
                '  <fg=gray>%s</> %s%s',
                $this->now()->format('Y-m-d H:i:s'),
                $job->resolveName(),
                $this->output->isVerbose()
                    ? sprintf(' <fg=gray>%s</>', $job->getJobId())
                    : ''
            )
        );

        if ($status == 'starting') {
            $this->latestStartedAt = microtime(true);

            $dots = max(
                terminal()->width() - mb_strlen($job->resolveName()) - (
                $this->output->isVerbose() ? (mb_strlen($job->getJobId()) + 1) : 0
                ) - 33,
                0
            );

            $this->output->write(' ' . str_repeat('<fg=gray>.</>', $dots));

            $this->output->writeln(' <fg=yellow;options=bold>RUNNING</>');

            return;
        }

        $runTime = $this->formatRunTime($this->latestStartedAt);

        $dots = max(
            terminal()->width() - mb_strlen($job->resolveName()) - (
            $this->output->isVerbose() ? (mb_strlen($job->getJobId()) + 1) : 0
            ) - mb_strlen($runTime) - 31,
            0
        );

        $this->output->write(' ' . str_repeat('<fg=gray>.</>', $dots));
        $this->output->write(" <fg=gray>$runTime</>");

        $this->output->writeln(
            match ($status) {
                'success' => ' <fg=green;options=bold>DONE</>',
                'released_after_exception' => ' <fg=yellow;options=bold>FAIL</>',
                default => ' <fg=red;options=bold>FAIL</>',
            }
        );
    }

    /**
     * Get the current date / time.
     *
     * @return \Illuminate\Support\Carbon
     */
    protected function now()
    {
        $queueTimezone = $this->laravel['config']->get('queue.output_timezone');

        if (
            $queueTimezone &&
            $queueTimezone !== $this->laravel['config']->get('app.timezone')
        ) {
            return Carbon::now()->setTimezone($queueTimezone);
        }

        return Carbon::now();
    }

    /**
     * Given a start time, format the total run time for human readability.
     *
     * @param float $startTime
     * @return string
     */
    protected function formatRunTime($startTime)
    {
        $runTime = (microtime(true) - $startTime) * 1000;

        return $runTime > 1000
            ? CarbonInterval::milliseconds($runTime)->cascade()->forHumans(short: true)
            : number_format($runTime, 2) . 'ms';
    }

    /**
     * Store a failed job event.
     *
     * @param \Illuminate\Queue\Events\JobFailed $event
     * @return void
     */
    protected function logFailedJob(JobFailed $event)
    {
        $this->laravel['queue.failer']->log(
            $event->connectionName,
            $event->job->getQueue(),
            $event->job->getRawBody(),
            $event->exception
        );
    }

    /**
     * Get the queue name for the worker.
     *
     * @param string $connection
     * @return string
     */
    protected function getQueue($connection)
    {
        return $this->option('queue') ?: $this->laravel['config']->get(
            "queue.connections.{$connection}.queue",
            'default'
        );
    }

    /**
     * Determine if the worker should run in maintenance mode.
     *
     * @return bool
     */
    protected function downForMaintenance()
    {
        return $this->option('force') ? false : $this->laravel->isDownForMaintenance();
    }
}
