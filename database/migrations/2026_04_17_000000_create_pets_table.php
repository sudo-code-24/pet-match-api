<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('name');
            $table->enum('species', ['dog', 'cat']);
            $table->enum('gender', ['male', 'female']);
            $table->string('breed')->nullable();
            $table->integer('age')->nullable();
            $table->text('health_notes')->nullable();
            $table->text('adoption_details')->nullable();
            $table->enum('purpose', ['adoption', 'mate', 'companion'])->default('companion');
            $table->string('image_url')->nullable();
            $table->json('image_urls')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index('user_id');
            $table->index(['user_id', 'active']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pets');
    }
};
