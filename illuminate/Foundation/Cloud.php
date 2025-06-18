<?php

namespace Illuminate\Foundation;

use Illuminate\Database\Migrations\Migrator;
use Illuminate\Foundation\Bootstrap\HandleExceptions;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\SocketHandler;
use PDO;

class Cloud
{
    /**
     * Handle a bootstrapper that is bootstrapping.
     */
    public static function bootstrapperBootstrapping(Application $app, string $bootstrapper): void
    {
        //
    }

    /**
     * Handle a bootstrapper that has bootstrapped.
     */
    public static function bootstrapperBootstrapped(Application $app, string $bootstrapper): void
    {
        (match ($bootstrapper) {
            LoadConfiguration::class => function () use ($app) {
                static::ensureMigrationsUseUnpooledConnection($app);
            },
            HandleExceptions::class => function () use ($app) {
            },
            default => fn() => true,
        })();
    }

    /**
     * Ensure that migrations use the unpooled Postgres connection if applicable.
     */
    public static function ensureMigrationsUseUnpooledConnection(Application $app): void
    {
        if (!is_array($app['config']->get('database.connections.pgsql-unpooled'))) {
            return;
        }

        Migrator::resolveConnectionsUsing(function ($resolver, $connection) use ($app) {
            $connection = $connection ?? $app['config']->get('database.default');

            return $resolver->connection(
                $connection === 'pgsql' ? 'pgsql-unpooled' : $connection
            );
        });
    }
}
