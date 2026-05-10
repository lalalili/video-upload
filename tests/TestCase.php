<?php

namespace Lalalili\VideoUpload\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Lalalili\CourseCore\CourseCoreServiceProvider;
use Lalalili\VideoUpload\VideoUploadServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            CourseCoreServiceProvider::class,
            VideoUploadServiceProvider::class,
        ];
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        Schema::create('course_units', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('video_id')->nullable();
            $table->string('provider')->nullable();
            $table->string('provider_video_id')->nullable();
            $table->string('video_url')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->unsignedInteger('duration')->nullable();
            $table->string('provider_status')->nullable();
            $table->string('transcode_status')->nullable();
            $table->timestamps();
        });
        DB::statement('PRAGMA foreign_keys = ON');
    }

    protected function getEnvironmentSetUp($app): void
    {
        config()->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
        config()->set('course-core.default_video_platform', 'cloudflare_stream');
        config()->set('course-core.providers.cloudflare_stream.account_id', 'account-123');
        config()->set('course-core.providers.cloudflare_stream.api_token', 'token-123');
    }
}
