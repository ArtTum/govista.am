<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->json('location')->nullable()->after('description');
            $table->json('gallery')->nullable()->after('image');
            $table->json('unit')->nullable()->after('currency');
            $table->decimal('rating', 3, 2)->default(4.90)->after('unit');
            $table->unsignedInteger('review_count')->default(0)->after('rating');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['location', 'gallery', 'unit', 'rating', 'review_count']);
        });
    }
};
