<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('travel_providers', function (Blueprint $table) {
            $table->text('integration_profiles')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('travel_providers', fn (Blueprint $table) => $table->dropColumn('integration_profiles'));
    }
};
