<?php

namespace BlastSpec\Actions\Specs;

use BlastSpec\Models\Service;
use BlastSpec\Models\Spec;
use BlastSpec\Models\SpecVersion;
use BlastSpec\Models\User;
use BlastSpec\Services\Spec\SpecParser;
use BlastSpec\Services\Spec\Canonicalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class UploadSpec
{
    public function __construct(
        private readonly SpecParser $parser,
        private readonly Canonicalizer $canonicalizer,
    ) {}

    public function execute(
        string $workspaceId,
        string $serviceId,
        string $name,
        string $versionLabel,
        string $rawContent,
        ?User $user = null,
        string $extension = 'json',
    ): array
    {
        $service = Service::query()
            ->where('workspace_id', $workspaceId)
            ->where('id', $serviceId)
            ->first();

        if ($service === null) {
            throw new \RuntimeException('Service not found in workspace.');
        }

        $contentHash = SpecVersion::hashContent($rawContent);

        $existingVersion = SpecVersion::query()
            ->where('spec_id', function ($q) use ($serviceId): void
            {
                $q->select('id')->from('specs')->where('service_id', $serviceId);
            })
            ->where('content_hash', $contentHash)
            ->first();

        if ($existingVersion !== null) {
            throw new \RuntimeException('Duplicate content: this document already exists as a version.');
        }

        $parsed = $this->parser->parse($rawContent, $extension);

        if ($parsed['errors'] !== []) {
            $messages = array_map(fn ($e) => is_array($e) ? ($e['message'] ?? '') : (string) $e, $parsed['errors']);

            throw new \RuntimeException(implode(' ', $messages));
        }

        $normalized = $this->canonicalizer->canonicalize($parsed['normalized']);

        return DB::transaction(function () use (
            $workspaceId,
            $service,
            $name,
            $versionLabel,
            $rawContent,
            $contentHash,
            $parsed,
            $normalized,
            $user,
        ): array
        {
            $specName = $name !== '' ? $name : ($normalized['info']['title'] ?? $service->name . ' API');
            $slug = Spec::slugify($specName);

            $existingSpec = Spec::query()
                ->where('service_id', $service->id)
                ->where('slug', $slug)
                ->first();

            if ($existingSpec === null) {
                $spec = Spec::query()->create([
                    'id' => (string) Str::uuid7(),
                    'workspace_id' => $workspaceId,
                    'service_id' => $service->id,
                    'name' => $specName,
                    'slug' => $slug,
                ]);
            } else {
                $spec = $existingSpec;
            }

            $duplicateLabel = SpecVersion::query()
                ->where('spec_id', $spec->id)
                ->where('version', $versionLabel)
                ->first();

            if ($duplicateLabel !== null) {
                throw new \RuntimeException('Duplicate version label.');
            }

            $specVersion = SpecVersion::query()->create([
                'id' => (string) Str::uuid7(),
                'workspace_id' => $workspaceId,
                'spec_id' => $spec->id,
                'version' => $versionLabel,
                'raw_content' => $rawContent,
                'content_hash' => $contentHash,
                'openapi_version' => $parsed['openapi_version'],
                'normalized' => $normalized,
                'parse_status' => 'parsed',
                'warnings' => $parsed['warnings'],
                'endpoint_count' => $parsed['endpoint_count'],
                'warning_count' => count($parsed['warnings']),
                'created_by' => $user?->id,
            ]);

            $spec->update(['current_version_id' => $specVersion->id]);

            return [
                'spec' => $spec->fresh(['service', 'currentVersion']),
                'version' => $specVersion,
            ];
        });
    }
}
