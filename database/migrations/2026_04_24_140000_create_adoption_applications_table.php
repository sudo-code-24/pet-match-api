<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adoption_applications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('pet_id');
            $table->uuid('applicant_user_id');
            $table->text('message');
            $table->string('status')->default('submitted');
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->foreign('pet_id')->references('id')->on('pets')->cascadeOnDelete();
            $table->foreign('applicant_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['pet_id', 'applicant_user_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adoption_applications');
    }
};
