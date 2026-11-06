<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dlq_jobs', function (Blueprint $table): void
        {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->string('job_type');
            $table->jsonb('payload');
            $table->text('error');
            $table->integer('retry_count')->default(3);
            $table->string('status')->default('pending');
            $table->timestamp('last_attempt_at');
            $table->timestamp('replayed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['workspace_id', 'status']);
            $table->index(['job_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dlq_jobs');
    }
};
