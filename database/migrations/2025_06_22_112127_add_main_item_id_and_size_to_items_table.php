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
        Schema::table('items', function (Blueprint $table) {
            $table->foreignId('main_item_id')->nullable()->constrained('main_items')->onDelete('set null');
            $table->enum('size', ['small', 'medium', 'large', 'family'])->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropForeign(['main_item_id']);
            $table->dropColumn('main_item_id');
            $table->dropColumn('size');
        });
    }
};
