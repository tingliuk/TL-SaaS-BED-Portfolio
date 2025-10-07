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
        Schema::table('votes', function (Blueprint $table) {
            // Drop existing foreign key constraint
            $table->dropForeign(['joke_id']);
            
            // Add new foreign key with cascade delete
            $table->foreign('joke_id')
                  ->references('id')
                  ->on('jokes')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('votes', function (Blueprint $table) {
            // Drop cascade foreign key
            $table->dropForeign(['joke_id']);
            
            // Restore original foreign key without cascade
            $table->foreign('joke_id')
                  ->references('id')
                  ->on('jokes');
        });
    }
};