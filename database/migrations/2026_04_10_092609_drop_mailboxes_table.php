<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('mailboxes');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('mailboxes', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('host');
            $table->integer('port')->default(993);
            $table->string('encryption')->default('ssl');
            $table->string('username');
            $table->text('password');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
};
