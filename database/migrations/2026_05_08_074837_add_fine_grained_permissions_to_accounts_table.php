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
        Schema::table('accounts', function (Blueprint $table) {
            $table->boolean('google_calendar_read')->default(true)->after('google_calendar_used');
            $table->boolean('google_calendar_write')->default(true)->after('google_calendar_read');
            $table->boolean('mail_read')->default(true)->after('mail_used');
            $table->boolean('mail_write')->default(true)->after('mail_read');
            $table->boolean('slack_read')->default(true)->after('slack_used');
            $table->boolean('slack_write')->default(true)->after('slack_read');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn([
                'google_calendar_read',
                'google_calendar_write',
                'mail_read',
                'mail_write',
                'slack_read',
                'slack_write',
            ]);
        });
    }
};
