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
        Schema::create('zoho_connector_token', function (Blueprint $table) {
            $table->id();
            $table->string('token');
            $table->string('refresh_token');
            $table->timestamp('token_created_at');
            $table->timestamp('token_peremption_at');
            $table->integer('token_duration');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('zoho_connector_token');
    }
};
