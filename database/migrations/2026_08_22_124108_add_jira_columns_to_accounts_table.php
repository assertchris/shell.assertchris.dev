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
            $table->boolean('jira_used')->default(false)->after('github_pat');
            $table->text('jira_base_url')->nullable()->after('jira_used');
            $table->text('jira_email')->nullable()->after('jira_base_url');
            $table->text('jira_api_token')->nullable()->after('jira_email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn(['jira_used', 'jira_base_url', 'jira_email', 'jira_api_token']);
        });
    }
};
