<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table): void
        {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->string('idempotency_key');
            $table->string('event_type');
            $table->jsonb('payload');
            $table->string('signature_status')->default('valid');
            $table->string('status')->default('received');
            $table->uuid('spec_version_id')->nullable();
            $table->uuid('diff_id')->nullable();
            $table->uuid('report_id')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('received_at')->useCurrent();
            $table->timestamp('processed_at')->nullable();

            $table->unique(['workspace_id', 'idempotency_key']);
            $table->index(['workspace_id', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
