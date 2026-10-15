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
            $table->boolean('laravel_cloud_used')->default(false)->after('jira_api_token');
            $table->text('laravel_cloud_api_token')->nullable()->after('laravel_cloud_used');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn(['laravel_cloud_used', 'laravel_cloud_api_token']);
        });
    }
};
