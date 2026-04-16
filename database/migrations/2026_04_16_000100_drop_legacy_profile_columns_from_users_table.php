<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $columnsToDrop = [];
            if (Schema::hasColumn('users', 'profile')) {
                $columnsToDrop[] = 'profile';
            }
            if (Schema::hasColumn('users', 'shelter_profile')) {
                $columnsToDrop[] = 'shelter_profile';
            }

            if ($columnsToDrop !== []) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'profile')) {
                $table->json('profile')->nullable()->after('shelter_name');
            }

            if (! Schema::hasColumn('users', 'shelter_profile')) {
                $table->json('shelter_profile')->nullable()->after('profile');
            }
        });
    }
};
