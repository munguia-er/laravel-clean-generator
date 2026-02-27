<?php

namespace MunguiaEr\LaravelCleanGenerator\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use MunguiaEr\LaravelCleanGenerator\CleanGeneratorServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\File;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            CleanGeneratorServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app)
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
        
        $app['config']->set('clean-generator.base_namespace', 'App');
        $app['config']->set('clean-generator.base_path', __DIR__ . '/temp/app');
        // Pin architecture to 'clean' so all existing tests run against the legacy paths
        $app['config']->set('clean-generator.architecture', 'clean');
        // Pin default_stack to 'web' for deterministic tests
        $app['config']->set('clean-generator.default_stack', 'web');
    }

    protected function defineDatabaseMigrations()
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content')->nullable();
            $table->boolean('is_published')->default(false);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    protected function setupTempDirectory()
    {
        $tempApp = __DIR__ . '/temp/app';
        if (!file_exists($tempApp)) {
            mkdir($tempApp, 0777, true);
        }
        
        // Mock Providers and Routes directories
        @mkdir($tempApp . '/Providers', 0777, true);
        file_put_contents($tempApp . '/Providers/AppServiceProvider.php', "<?php\n\nnamespace App\Providers;\n\nclass AppServiceProvider { \npublic function register() {} \n}");
        
        @mkdir(__DIR__ . '/temp/routes', 0777, true);
        file_put_contents(__DIR__ . '/temp/routes/api.php', "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n");
        file_put_contents(__DIR__ . '/temp/routes/web.php', "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n");
        app()->setBasePath(__DIR__ . '/temp'); // Force base path for routes
    }

    protected function tearDownTempDirectory()
    {
        File::deleteDirectory(__DIR__ . '/temp');
    }
}
