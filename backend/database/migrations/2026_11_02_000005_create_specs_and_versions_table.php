<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('specs', function (Blueprint $table): void
        {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignUuid('service_id')->constrained('services')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->uuid('current_version_id')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();

            $table->unique(['service_id', 'slug']);
        });

        Schema::create('spec_versions', function (Blueprint $table): void
        {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignUuid('spec_id')->constrained('specs')->cascadeOnDelete();
            $table->string('version', 40);
            $table->text('raw_content');
            $table->char('content_hash', 64);
            $table->string('openapi_version')->nullable();
            $table->jsonb('normalized')->nullable();
            $table->string('parse_status')->default('parsed');
            $table->text('parse_error')->nullable();
            $table->jsonb('warnings')->default('[]');
            $table->integer('endpoint_count')->default(0);
            $table->integer('warning_count')->default(0);
            $table->string('graph_version')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['spec_id', 'version']);
            $table->unique(['spec_id', 'content_hash']);
            $table->index(['spec_id', 'created_at']);
        });

        Schema::table('specs', function (Blueprint $table): void
        {
            $table->foreign('current_version_id')
                ->references('id')
                ->on('spec_versions')
                ->nullOnDelete()
                ->deferrable();
        });
    }

    public function down(): void
    {
        Schema::table('specs', function (Blueprint $table): void
        {
            $table->dropForeign(['current_version_id']);
        });
        Schema::dropIfExists('spec_versions');
        Schema::dropIfExists('specs');
    }
};
