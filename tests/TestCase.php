<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => getenv('SMART_TEST_DB_CONNECTION') ?: 'sqlite']);
    }
}
