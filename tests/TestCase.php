<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function withToken($token, $type = 'Bearer')
    {
        if (isset($this->app)) {
            $this->app->make('auth')->forgetGuards();
        }

        return parent::withToken($token, $type);
    }
}
