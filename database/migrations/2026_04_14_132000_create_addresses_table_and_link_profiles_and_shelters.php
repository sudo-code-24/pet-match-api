<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table): void {
            $table->id();
            $table->text('street')->nullable();
            $table->text('landmark')->nullable();
            $table->text('barangay')->nullable();
            $table->text('city')->nullable();
            $table->text('province')->nullable();
            $table->text('zip_code')->nullable();
            $table->text('country')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('full_address')->nullable();
            $table->timestamps();
        });

        Schema::table('user_profiles', function (Blueprint $table): void {
            $table->foreignId('address_id')->nullable()->after('bio')->constrained('addresses')->nullOnDelete();
            $table->dropColumn('location');
        });

        Schema::table('shelters', function (Blueprint $table): void {
            $table->foreignId('address_id')->nullable()->after('address')->constrained('addresses')->nullOnDelete();
            $table->dropColumn('location');
        });
    }

    public function down(): void
    {
        Schema::table('shelters', function (Blueprint $table): void {
            $table->string('location')->nullable()->after('address_id');
            $table->dropConstrainedForeignId('address_id');
        });

        Schema::table('user_profiles', function (Blueprint $table): void {
            $table->string('location')->nullable()->after('address_id');
            $table->dropConstrainedForeignId('address_id');
        });

        Schema::dropIfExists('addresses');
    }
};
