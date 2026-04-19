<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table): void {
            $table->dropIndex(['conversation_id', 'created_at']);
            $table->index(
                ['conversation_id', 'created_at', 'id'],
                'messages_conversation_created_id_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table): void {
            $table->dropIndex('messages_conversation_created_id_index');
            $table->index(['conversation_id', 'created_at']);
        });
    }
};
