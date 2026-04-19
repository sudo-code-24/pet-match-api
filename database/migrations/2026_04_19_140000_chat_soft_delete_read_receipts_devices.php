<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->boolean('deleted_by_user_one')->default(false);
            $table->boolean('deleted_by_user_two')->default(false);
            $table->timestamp('deleted_at')->nullable();
        });

        Schema::table('messages', function (Blueprint $table): void {
            $table->timestamp('read_at')->nullable();
            $table->index(['conversation_id', 'read_at']);
        });

        Schema::create('user_devices', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->text('device_token');
            $table->string('platform', 32)->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['user_id', 'device_token']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_devices');

        Schema::table('messages', function (Blueprint $table): void {
            $table->dropIndex(['conversation_id', 'read_at']);
            $table->dropColumn('read_at');
        });

        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropColumn(['deleted_by_user_one', 'deleted_by_user_two', 'deleted_at']);
        });
    }
};
