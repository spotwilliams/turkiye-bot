<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->text('normalized_text')->nullable()->after('original_text');
            $table->string('normalized_text_hash', 64)->nullable()->after('normalized_text');

            $table->unique('normalized_text_hash', 'messages_hash_unique');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropUnique('messages_hash_unique');
            $table->dropColumn(['normalized_text', 'normalized_text_hash']);
        });
    }
};
