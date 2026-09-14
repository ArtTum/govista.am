<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('travel_providers', function (Blueprint $table) {
            $table->json('settings')->nullable();
        });
        Schema::create('package_offers', function (Blueprint $table) {
            $table->id();
            $table->string('provider_code', 30);
            $table->string('supplier_reference', 120);
            $table->json('title');
            $table->json('description')->nullable();
            $table->string('destination_code', 30);
            $table->string('origin', 3)->default('EVN');
            $table->string('hotel_name', 190)->nullable();
            $table->unsignedTinyInteger('stars')->nullable();
            $table->string('room_type', 190)->nullable();
            $table->string('meal_plan', 10)->default('RO');
            $table->date('departure_date')->nullable();
            $table->unsignedTinyInteger('nights')->default(7);
            $table->unsignedTinyInteger('adults')->default(2);
            $table->json('children')->nullable();
            $table->json('inclusions')->nullable();
            $table->json('exclusions')->nullable();
            $table->json('terms')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->decimal('cost', 12, 2)->nullable();
            $table->string('currency', 3)->default('AMD');
            $table->string('price_basis', 20)->default('party_total');
            $table->string('image', 500)->nullable();
            $table->string('status', 20)->default('draft');
            $table->string('availability', 20)->default('on_request');
            $table->timestamp('valid_until')->nullable();
            $table->timestamp('price_checked_at')->nullable();
            $table->text('internal_notes')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['provider_code', 'supplier_reference']);
            $table->index(['status', 'destination_code', 'departure_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_offers');
        Schema::table('travel_providers', fn (Blueprint $table) => $table->dropColumn('settings'));
    }
};
