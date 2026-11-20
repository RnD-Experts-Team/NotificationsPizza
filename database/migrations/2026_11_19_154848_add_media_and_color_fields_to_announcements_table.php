<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            // A disk-relative path, not a full URL — see AnnouncementMediaService.
            // Surviving an APP_URL/host change is the point: the real URL is
            // computed on read, never stored.
            $table->string('media_path')->nullable()->after('body');
            $table->string('media_type', 20)->nullable()->after('media_path');
            // Video only, reported by the browser at upload time.
            $table->unsignedInteger('media_width')->nullable()->after('media_type');
            $table->unsignedInteger('media_height')->nullable()->after('media_width');
            $table->unsignedInteger('media_duration_seconds')->nullable()->after('media_height');
            $table->string('color_start', 7)->nullable()->after('media_duration_seconds');
            $table->string('color_end', 7)->nullable()->after('color_start');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn([
                'media_path', 'media_type', 'media_width', 'media_height',
                'media_duration_seconds', 'color_start', 'color_end',
            ]);
        });
    }
};
