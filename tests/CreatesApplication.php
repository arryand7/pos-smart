<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;

trait CreatesApplication
{
    /**
     * Creates the application.
     */
    public function createApplication()
    {
        $connection = getenv('SMART_TEST_DB_CONNECTION') ?: 'sqlite';
        $database = getenv('SMART_TEST_DB_DATABASE') ?: ':memory:';
        putenv("DB_CONNECTION={$connection}");
        putenv("DB_DATABASE={$database}");
        $_ENV['DB_CONNECTION'] = $connection;
        $_ENV['DB_DATABASE'] = $database;
        $_SERVER['DB_CONNECTION'] = $connection;
        $_SERVER['DB_DATABASE'] = $database;

        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        $app['config']->set('database.default', $connection);
        if ($connection === 'sqlite') {
            $app['config']->set('database.connections.sqlite.database', $database);
        }

        return $app;
    }
}
