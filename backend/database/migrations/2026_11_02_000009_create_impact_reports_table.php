<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('impact_reports', function (Blueprint $table): void
        {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignUuid('diff_id')->constrained('contract_diffs')->cascadeOnDelete();
            $table->string('status')->default('queued');
            $table->string('strategy')->default('topological');
            $table->string('graph_version')->nullable();
            $table->jsonb('summary')->default('{}');
            $table->text('error')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->index(['workspace_id', 'created_at']);
            $table->index(['diff_id']);
        });

        Schema::create('impact_report_items', function (Blueprint $table): void
        {
            $table->uuid('id')->primary();
            $table->foreignUuid('report_id')->constrained('impact_reports')->cascadeOnDelete();
            $table->foreignUuid('service_id')->constrained('services')->cascadeOnDelete();
            $table->string('owner_team');
            $table->string('relation');
            $table->integer('depth')->default(0);
            $table->jsonb('change_ids')->default('[]');
            $table->text('reason');

            $table->unique(['report_id', 'service_id']);
        });

        Schema::create('impact_report_waves', function (Blueprint $table): void
        {
            $table->uuid('id')->primary();
            $table->foreignUuid('report_id')->constrained('impact_reports')->cascadeOnDelete();
            $table->integer('wave_index');
            $table->foreignUuid('service_id')->constrained('services')->cascadeOnDelete();

            $table->unique(['report_id', 'service_id']);
            $table->index(['report_id', 'wave_index']);
        });

        Schema::create('impact_report_dependencies', function (Blueprint $table): void
        {
            $table->uuid('id')->primary();
            $table->foreignUuid('report_id')->constrained('impact_reports')->cascadeOnDelete();
            $table->foreignUuid('service_id')->constrained('services')->cascadeOnDelete();
            $table->foreignUuid('depends_on_service_id')->constrained('services')->cascadeOnDelete();
            $table->boolean('in_cycle')->default(false);

            $table->unique(['report_id', 'service_id', 'depends_on_service_id']);
        });

        Schema::create('impact_report_cycles', function (Blueprint $table): void
        {
            $table->uuid('id')->primary();
            $table->foreignUuid('report_id')->constrained('impact_reports')->cascadeOnDelete();
            $table->jsonb('service_ids');
            $table->uuid('recommended_break_service_id')->nullable();
            $table->text('reason');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impact_report_cycles');
        Schema::dropIfExists('impact_report_dependencies');
        Schema::dropIfExists('impact_report_waves');
        Schema::dropIfExists('impact_report_items');
        Schema::dropIfExists('impact_reports');
    }
};
