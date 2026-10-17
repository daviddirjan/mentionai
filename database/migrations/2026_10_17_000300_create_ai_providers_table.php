<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_providers', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique(); // App\Enums\ProviderSlug
            $table->string('name');
            $table->string('display_name'); // consumer-facing name, e.g. "ChatGPT"
            $table->boolean('is_enabled')->default(true);
            $table->string('model');
            $table->unsignedSmallInteger('max_requests_per_analysis')->default(3);
            $table->unsignedSmallInteger('timeout_seconds')->default(60);
            $table->unsignedInteger('max_output_tokens')->default(1024);
            $table->unsignedSmallInteger('max_search_calls')->nullable();
            // Estimated prices in USD. Token prices are per 1M tokens, search price per 1K searches.
            $table->decimal('input_price_per_million', 12, 6)->default(0);
            $table->decimal('output_price_per_million', 12, 6)->default(0);
            $table->decimal('search_price_per_thousand', 12, 6)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('last_test_status')->nullable(); // ok | failed
            $table->text('last_test_message')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_providers');
    }
};
