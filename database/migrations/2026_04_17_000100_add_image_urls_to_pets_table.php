<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pets')) {
            return;
        }

        Schema::table('pets', function (Blueprint $table): void {
            if (! Schema::hasColumn('pets', 'image_urls')) {
                $table->json('image_urls')->nullable()->after('image_url');
            }
        });

        DB::statement("
            UPDATE pets
            SET image_urls = JSON_ARRAY(image_url)
            WHERE image_url IS NOT NULL
              AND image_url <> ''
              AND (image_urls IS NULL OR JSON_LENGTH(image_urls) = 0)
        ");
    }

    public function down(): void
    {
        if (! Schema::hasTable('pets')) {
            return;
        }

        Schema::table('pets', function (Blueprint $table): void {
            if (Schema::hasColumn('pets', 'image_urls')) {
                $table->dropColumn('image_urls');
            }
        });
    }
};
