<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->timestamp('failed_at')->nullable()->after('processed_at');
            $table->text('failure_reason')->nullable()->after('failed_at');
            $table->text('translation_en')->nullable()->change();
            $table->text('translation_es')->nullable()->change();
            $table->text('summary')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['failed_at', 'failure_reason']);
            $table->text('translation_en')->nullable(false)->change();
            $table->text('translation_es')->nullable(false)->change();
            $table->text('summary')->nullable(false)->change();
        });
    }
};
