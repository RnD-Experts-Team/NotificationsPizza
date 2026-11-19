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
            $table->string('media_url')->nullable()->after('body');
            $table->string('media_type', 20)->nullable()->after('media_url');
            $table->string('color_start', 7)->nullable()->after('media_type');
            $table->string('color_end', 7)->nullable()->after('color_start');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn(['media_url', 'media_type', 'color_start', 'color_end']);
        });
    }
};
