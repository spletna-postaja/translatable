<?php

use Laraplus\Data\TranslatableConfig;
use Illuminate\Database\Capsule\Manager as Capsule;

abstract class TestCase extends PHPUnit\Framework\TestCase
{
    public function setUp(): void
    {
        $capsule = new Capsule;

        $capsule->addConnection([
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => ''
        ]);

        $capsule->setAsGlobal();

        $capsule->bootEloquent();

        TranslatableConfig::currentLocaleGetter(function () {
            return 'en';
        });

        TranslatableConfig::fallbackLocaleGetter(function () {
            return 'en';
        });

        require_once __DIR__ . '/stubs/Post.php';
    }
}
