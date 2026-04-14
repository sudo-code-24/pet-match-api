<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shelters', function (Blueprint $table): void {
            $table->id();
            $table->uuid('user_id')->unique();
            $table->string('shelter_name');
            $table->text('description')->nullable();
            $table->string('address')->nullable();
            $table->string('location')->nullable();
            $table->string('phone')->nullable();
            $table->string('verification_status')->default('pending');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shelters');
    }
};
