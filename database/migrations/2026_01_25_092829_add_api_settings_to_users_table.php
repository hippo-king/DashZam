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
        Schema::table('users', function (Blueprint $table) {
            $table->string('api_base_url')->nullable();
            $table->string('api_test_endpoint')->nullable();
            $table->text('api_token')->nullable();
            $table->json('api_last_payload')->nullable();
            $table->timestamp('api_last_fetched_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'api_base_url',
                'api_test_endpoint',
                'api_token',
                'api_last_payload',
                'api_last_fetched_at',
            ]);
        });
    }
};
