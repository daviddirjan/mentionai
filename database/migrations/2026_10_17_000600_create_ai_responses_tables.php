<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_id')->constrained()->cascadeOnDelete();
            $table->foreignId('analysis_question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_provider_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider');           // slug snapshot
            $table->string('model')->nullable();  // model snapshot
            $table->string('status')->index();    // App\Enums\ResponseStatus
            $table->boolean('is_demo')->default(false);

            $table->json('raw_response')->nullable();
            $table->longText('response_text')->nullable();
            $table->json('citations')->nullable(); // list<{url, title?}>

            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedSmallInteger('search_calls')->nullable();
            $table->decimal('estimated_cost', 12, 6)->default(0);

            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // One response per question per provider: retries update the row, never duplicate paid calls.
            $table->unique(['analysis_question_id', 'provider']);
            $table->index(['analysis_id', 'provider']);
        });

        Schema::create('brand_mentions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_response_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('classification')->index(); // App\Enums\MentionClassification
            $table->json('matched_terms')->nullable();  // list<string>
            $table->json('evidence')->nullable();       // list<{type, term, snippet}>
            $table->timestamps();
        });

        Schema::create('competitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('normalized_name');
            $table->string('domain')->nullable();
            $table->unsignedSmallInteger('mention_count')->default(0);
            $table->json('providers')->nullable(); // list<provider slug>
            $table->timestamps();

            $table->unique(['analysis_id', 'normalized_name']);
            $table->index('normalized_name');
        });

        Schema::create('api_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ai_response_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ai_provider_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider')->index();
            $table->string('model')->nullable();
            $table->string('operation'); // query | test_connection
            $table->string('status');    // succeeded | failed
            $table->boolean('is_demo')->default(false);
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedSmallInteger('search_calls')->nullable();
            $table->decimal('estimated_cost', 12, 6)->default(0); // USD estimate, not an invoice
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('error_code')->nullable();
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_usage_logs');
        Schema::dropIfExists('competitors');
        Schema::dropIfExists('brand_mentions');
        Schema::dropIfExists('ai_responses');
    }
};
