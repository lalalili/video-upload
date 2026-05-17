<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $videoTable = config('video-upload.tables.videos', 'videos');
        $sessionTable = config('video-upload.tables.sessions', 'video_upload_sessions');

        // Guards ensure this is idempotent on hosts that already have these tables.
        if (! Schema::hasTable($videoTable)) {
            Schema::create($videoTable, function (Blueprint $table): void {
                $table->id();
                $table->string('provider')->index();
                $table->string('provider_video_id')->nullable()->index();
                $table->string('upload_strategy')->nullable();
                $table->string('staging_disk')->nullable();
                $table->string('staging_path')->nullable();
                $table->string('provider_status')->nullable()->index();
                $table->string('transcode_status')->nullable()->index();
                $table->json('metadata')->nullable();
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('link')->nullable();
                $table->string('player_embed_url')->nullable();
                $table->unsignedInteger('duration')->nullable();
                $table->string('thumbnail_url')->nullable();
                $table->unsignedBigInteger('size')->nullable();
                $table->string('mime_type')->nullable();
                $table->foreignId('folder_id')->nullable()->index();
                $table->foreignId('course_id')->nullable()->index();
                $table->foreignId('course_chapter_id')->nullable()->index();
                $table->foreignId('company_id')->nullable()->index();
                $table->foreignId('created_by')->nullable()->index();
                $table->foreignId('updated_by')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable($sessionTable)) {
            Schema::create($sessionTable, function (Blueprint $table): void {
                $table->id();
                $table->ulid('ulid')->unique();
                $table->foreignId('video_id')->index();
                $table->foreignId('folder_id')->nullable()->index();
                $table->foreignId('company_id')->nullable()->index();
                $table->foreignId('created_by')->nullable()->index();

                // Generic target: host sets target_type/target_id to associate
                // the session with any model (e.g. a course unit, a serial episode).
                // The course_id/course_chapter_id columns are kept for backward
                // compatibility and will be removed in a future migration.
                $table->nullableMorphs('target');
                $table->foreignId('course_id')->nullable()->index();
                $table->foreignId('course_chapter_id')->nullable()->index();

                $table->string('source')->nullable()->default('upload_center')->index();
                $table->string('provider')->index();
                $table->string('strategy')->index();
                $table->string('status')->default('created')->index();
                $table->string('original_file_name');
                $table->unsignedBigInteger('file_size')->default(0);
                $table->string('mime_type')->nullable();
                $table->string('title')->nullable();
                $table->text('description')->nullable();

                // Staging: used when the file is temporarily held on a staging disk
                // before being imported to the video provider.
                $table->string('staging_disk')->nullable();
                $table->string('staging_path')->nullable();
                $table->string('multipart_upload_id')->nullable();

                $table->string('provider_video_id')->nullable()->index();
                $table->text('upload_endpoint')->nullable();
                $table->json('upload_headers')->nullable();
                $table->unsignedBigInteger('bytes_uploaded')->default(0);
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->text('error_message')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'status']);
                $table->index(['created_by', 'status']);
                $table->index(['provider', 'provider_video_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists(config('video-upload.tables.sessions', 'video_upload_sessions'));
        Schema::dropIfExists(config('video-upload.tables.videos', 'videos'));
    }
};
