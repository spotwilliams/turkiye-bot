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
        // Web-origin rows have no Telegram chat — make the chat columns nullable
        // without dropping them (Telegram-origin rows keep their values).
        Schema::table('messages', function (Blueprint $table) {
            $table->bigInteger('telegram_chat_id')->nullable()->change();
        });

        Schema::table('family_members', function (Blueprint $table) {
            $table->bigInteger('telegram_chat_id')->nullable()->change();
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->bigInteger('telegram_chat_id')->nullable()->change();
            // Delivery attribution only (not a scope key): web-created tasks
            // remember their author so reminders can be emailed to them.
            $table->foreignId('created_by')->nullable()->after('telegram_chat_id')
                ->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->bigInteger('telegram_chat_id')->nullable(false)->change();
        });

        Schema::table('family_members', function (Blueprint $table) {
            $table->bigInteger('telegram_chat_id')->nullable(false)->change();
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->bigInteger('telegram_chat_id')->nullable(false)->change();
        });
    }
};
