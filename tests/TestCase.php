<?php

namespace Board\PluginGitlab\Tests;

use Board\PluginGitlab\GitLabPluginServiceProvider;
use Board\PluginSdk\PluginRegistry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [GitLabPluginServiceProvider::class];
    }

    /**
     * Bind the host's PluginRegistry before the package provider boots so the
     * plugin registers itself exactly as it would inside a Board host. Also give
     * the app an encryption key and an in-memory database so the plugin's
     * instance settings (encrypted in the shared `settings` table) can be read.
     */
    protected function defineEnvironment($app): void
    {
        $app->singleton(PluginRegistry::class, fn (): PluginRegistry => new PluginRegistry);

        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('b', 32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('settings');
        Schema::create('settings', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }
}
