<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consumer_bindings', function (Blueprint $table): void
        {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignUuid('provider_service_id')->constrained('services')->cascadeOnDelete();
            $table->foreignUuid('consumer_service_id')->constrained('services')->cascadeOnDelete();
            $table->string('method');
            $table->string('path');
            $table->string('source');
            $table->decimal('confidence', 3, 2)->default(1.00);
            $table->jsonb('evidence')->default('{}');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['provider_service_id', 'consumer_service_id', 'method', 'path', 'source']);
            $table->index(['workspace_id', 'provider_service_id']);
            $table->index(['workspace_id', 'consumer_service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consumer_bindings');
    }
};
