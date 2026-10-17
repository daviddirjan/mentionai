<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('normalized_name')->index();
            $table->string('domain')->index(); // normalized host, no www., lowercase
            $table->json('brand_variations')->nullable(); // list<string>
            $table->timestamps();
        });

        Schema::create('analyses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique(); // public, non-guessable identifier
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            // Snapshot of the submitted input, kept even if the company row changes.
            $table->string('company_name');
            $table->string('website_url');
            $table->string('domain')->index();
            $table->string('market', 2)->index();   // ISO 3166-1 alpha-2, uppercase
            $table->string('language', 5)->index(); // analysis language, e.g. "ro"
            $table->string('industry')->nullable();
            $table->string('interface_locale', 5)->default('en');

            $table->string('status')->index(); // App\Enums\AnalysisStatus
            $table->boolean('is_demo')->default(false)->index();
            // Hash of domain + market + language + query configuration, used for cache reuse and dedupe.
            $table->string('config_hash', 64)->index();

            $table->json('website_inspection')->nullable(); // App\Data\WebsiteInspection::toArray()
            $table->json('query_config')->nullable();       // snapshot of query count + template ids used

            $table->decimal('visibility_score', 5, 2)->nullable(); // 0..100
            $table->unsignedSmallInteger('successful_tests')->default(0);
            $table->unsignedSmallInteger('failed_tests')->default(0);
            $table->unsignedSmallInteger('recommendation_count')->default(0);
            $table->unsignedSmallInteger('mention_count')->default(0);
            $table->unsignedSmallInteger('citation_count')->default(0);
            $table->json('provider_scores')->nullable(); // {slug: {recommended, successful, total, percentage}}
            $table->decimal('estimated_cost', 12, 6)->default(0); // USD

            $table->string('ip_hash', 64)->nullable()->index();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['config_hash', 'created_at']);
            $table->index('created_at');
        });

        Schema::create('analysis_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prompt_template_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('position');
            $table->text('question');      // final text sent to providers
            $table->text('template_snapshot')->nullable(); // template text at generation time
            $table->timestamps();

            $table->unique(['analysis_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analysis_questions');
        Schema::dropIfExists('analyses');
        Schema::dropIfExists('companies');
    }
};
