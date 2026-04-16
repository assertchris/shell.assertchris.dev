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
            $table->boolean('slack_used')->default(false)->after('google_calendar_token_expires_at');
            $table->text('slack_access_token')->nullable()->after('slack_used');
            $table->string('slack_team_id')->nullable()->after('slack_access_token');
            $table->string('slack_team_name')->nullable()->after('slack_team_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn(['slack_used', 'slack_access_token', 'slack_team_id', 'slack_team_name']);
        });
    }
};
