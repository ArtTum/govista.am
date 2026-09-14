<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('travel_providers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->boolean('enabled')->default(false);
            $table->string('environment')->default('sandbox');
            $table->text('credentials')->nullable();
            $table->string('status')->default('not_configured');
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->string('last_error')->nullable();
            $table->timestamps();
        });
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('submission_key')->nullable()->unique();
            $table->string('submission_hash', 64)->nullable();
            $table->json('request_details')->nullable();
            $table->string('currency', 3)->default('AMD');
            $table->text('quote_terms')->nullable();
            $table->timestamp('quote_expires_at')->nullable();
            $table->json('components')->nullable();
            $table->string('confirmation_reference')->nullable();
            $table->unsignedInteger('version')->default(1);
        });
        Schema::create('booking_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status');
            $table->text('message')->nullable();
            $table->boolean('internal')->default(false);
            $table->json('snapshot')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_events');
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['submission_key', 'submission_hash', 'request_details', 'currency', 'quote_terms', 'quote_expires_at', 'components', 'confirmation_reference', 'version']);
        });
        Schema::dropIfExists('travel_providers');
    }
};
