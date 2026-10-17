<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Leads come from the contact form on a results page.
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('analysis_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('company')->nullable();
            $table->string('email')->index();
            $table->string('phone')->nullable();
            $table->string('website')->nullable();
            $table->text('message')->nullable();
            $table->string('status')->default('new')->index(); // App\Enums\LeadStatus
            $table->text('notes')->nullable(); // internal, admin only
            $table->string('locale', 5)->default('en');
            $table->string('ip_hash', 64)->nullable();
            $table->timestamps();

            $table->index('created_at');
        });

        // Messages from the general contact section of the public site.
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->index();
            $table->string('company')->nullable();
            $table->string('subject')->nullable();
            $table->text('message');
            $table->string('status')->default('new')->index(); // App\Enums\ContactMessageStatus
            $table->timestamp('read_at')->nullable();
            $table->text('notes')->nullable();
            $table->string('locale', 5)->default('en');
            $table->string('ip_hash', 64)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
        Schema::dropIfExists('leads');
    }
};
