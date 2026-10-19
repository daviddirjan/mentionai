<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analyses', function (Blueprint $table) {
            $table->string('tier', 16)->default('check')->after('status')->index();
            // The free check an audit was requested from (audits only).
            $table->foreignId('parent_analysis_id')->nullable()->after('company_id')->constrained('analyses')->nullOnDelete();
            $table->json('site_audit')->nullable()->after('website_inspection');     // list<SiteCheck::toArray()>
            $table->json('recommendations')->nullable()->after('site_audit');         // list<Recommendation::toArray()>
            $table->json('narrative')->nullable()->after('recommendations');          // AuditNarrative::toArray()
            $table->string('report_path')->nullable()->after('narrative');            // local disk path, never public
            $table->timestamp('report_generated_at')->nullable()->after('report_path');
        });

        Schema::table('prompt_templates', function (Blueprint $table) {
            // Intent category (QuestionCategory value); audit-only templates are skipped by the free check.
            $table->string('category', 32)->nullable()->after('name');
            $table->boolean('is_audit_only')->default(false)->after('is_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('prompt_templates', function (Blueprint $table) {
            $table->dropColumn(['category', 'is_audit_only']);
        });

        Schema::table('analyses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_analysis_id');
            $table->dropColumn(['tier', 'site_audit', 'recommendations', 'narrative', 'report_path', 'report_generated_at']);
        });
    }
};
