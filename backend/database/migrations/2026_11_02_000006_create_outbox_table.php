<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox', function (Blueprint $table): void
        {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->string('event_type');
            $table->string('aggregate_type');
            $table->uuid('aggregate_id');
            $table->jsonb('payload');
            $table->string('status')->default('pending');
            $table->integer('attempts')->default(0);
            $table->timestamp('available_at')->useCurrent();
            $table->timestamp('processed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['status', 'available_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox');
    }
};
