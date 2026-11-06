<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_diffs', function (Blueprint $table): void
        {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignUuid('spec_id')->constrained('specs')->cascadeOnDelete();
            $table->foreignUuid('from_version_id')->constrained('spec_versions')->cascadeOnDelete();
            $table->foreignUuid('to_version_id')->constrained('spec_versions')->cascadeOnDelete();
            $table->string('status')->default('queued');
            $table->string('engine_version')->default('1.0.0');
            $table->jsonb('summary')->default(json_encode(['critical' => 0, 'warning' => 0, 'info' => 0, 'total' => 0, 'breaking' => false]));
            $table->text('error')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->unique(['spec_id', 'from_version_id', 'to_version_id']);
            $table->index(['workspace_id', 'created_at']);
        });

        Schema::create('contract_changes', function (Blueprint $table): void
        {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignUuid('diff_id')->constrained('contract_diffs')->cascadeOnDelete();
            $table->char('fingerprint', 64);
            $table->string('br_rule_id');
            $table->string('kind');
            $table->string('severity');
            $table->boolean('breaking')->default(false);
            $table->string('method')->nullable();
            $table->string('path')->nullable();
            $table->text('pointer')->nullable();
            $table->jsonb('before')->nullable();
            $table->jsonb('after')->nullable();
            $table->text('description');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['diff_id', 'fingerprint']);
            $table->index(['diff_id', 'severity']);
            $table->index(['diff_id', 'breaking']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_changes');
        Schema::dropIfExists('contract_diffs');
    }
};
