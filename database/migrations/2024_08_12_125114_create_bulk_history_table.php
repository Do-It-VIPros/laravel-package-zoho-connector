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
        Schema::create(config('zohoconnector.bulks_table_name'), function (Blueprint $table) {
            $table->id();
            $table->string('bulk_id');
            $table->string('report');
            $table->string('criterias');
            $table->string('step');
            $table->string('call_back_url');
            $table->timestamp('last_launch');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('zohoconnector.bulks_table_name'));
    }
};
