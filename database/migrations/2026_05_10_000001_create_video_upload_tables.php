<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create(config('video-upload.tables.videos', 'videos'), function (Blueprint $table): void {
            $table->id();
            $table->string('provider')->index();
            $table->string('provider_video_id')->nullable()->index();
            $table->string('upload_strategy')->nullable();
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

        Schema::create(config('video-upload.tables.sessions', 'video_upload_sessions'), function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('video_id')->index();
            $table->foreignId('folder_id')->nullable()->index();
            $table->foreignId('course_id')->nullable()->index();
            $table->foreignId('course_chapter_id')->nullable()->index();
            $table->foreignId('company_id')->nullable()->index();
            $table->foreignId('created_by')->nullable()->index();
            $table->string('source')->nullable()->index();
            $table->string('provider')->index();
            $table->string('strategy')->index();
            $table->string('status')->index();
            $table->string('original_file_name');
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('mime_type')->nullable();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
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
            $table->index(['provider', 'provider_video_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('video-upload.tables.sessions', 'video_upload_sessions'));
        Schema::dropIfExists(config('video-upload.tables.videos', 'videos'));
    }
};
