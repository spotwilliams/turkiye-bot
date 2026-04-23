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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('telegram_chat_id');
            $table->bigInteger('telegram_message_id')->nullable();
            $table->text('original_text');
            $table->text('translation_en');
            $table->text('translation_es');
            $table->text('summary');
            $table->json('raw_processor_response')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index('telegram_chat_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
