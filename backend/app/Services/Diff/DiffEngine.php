<?php

namespace BlastSpec\Services\Diff;

final class DiffEngine
{
    private array $changes = [];
    private string $workspaceId = '';
    private string $diffId = '';

    /** @return array<int, array<string, mixed>> */
    public function diff(string $workspaceId, string $diffId, array $fromNormalized, array $toNormalized): array
    {
        $this->workspaceId = $workspaceId;
        $this->diffId = $diffId;
        $this->changes = [];

        $fromEndpoints = $this->indexEndpoints($fromNormalized);
        $toEndpoints = $this->indexEndpoints($toNormalized);

        // BR-001: Removed operations
        foreach ($fromEndpoints as $key => $fromEp) {
            if (! isset($toEndpoints[$key])) {
                $this->addChange('BR-001', 'operation_removed', 'critical', $fromEp['method'], $fromEp['path'], '/paths', $fromEp, null, "Removed {$fromEp['method']} {$fromEp['path']} operation.");
            }
        }

        // BR-002: Added operations
        foreach ($toEndpoints as $key => $toEp) {
            if (! isset($fromEndpoints[$key])) {
                $this->addChange('BR-002', 'operation_added', 'info', $toEp['method'], $toEp['path'], '/paths', null, $toEp, "Added {$toEp['method']} {$toEp['path']} operation.");
            }
        }

        // Compare common operations
        foreach ($fromEndpoints as $key => $fromEp) {
            if (! isset($toEndpoints[$key])) {
                continue;
            }
            $toEp = $toEndpoints[$key];

            // BR-003: operationId changed
            if (($fromEp['operation_id'] ?? null) !== ($toEp['operation_id'] ?? null)
                && ($fromEp['operation_id'] !== null || $toEp['operation_id'] !== null)) {
                $this->addChange('BR-003', 'operation_id_changed', 'info', $fromEp['method'], $fromEp['path'],
                    '/paths/' . $this->escapePointer($fromEp['path']) . '/' . strtolower($fromEp['method']) . '/operationId',
                    $fromEp['operation_id'], $toEp['operation_id'],
                    "Changed operationId for {$fromEp['method']} {$fromEp['path']}.");
            }

            // BR-004: deprecated
            if (! ($fromEp['deprecated'] ?? false) && ($toEp['deprecated'] ?? false)) {
                $this->addChange('BR-004', 'operation_deprecated', 'info', $fromEp['method'], $fromEp['path'],
                    '/paths/' . $this->escapePointer($fromEp['path']) . '/' . strtolower($fromEp['method']) . '/deprecated',
                    false, true,
                    "Deprecated {$fromEp['method']} {$fromEp['path']} operation.");
            }

            // Parameters (BR-005 to BR-012)
            $this->diffParameters($fromEp, $toEp);

            // Request body (BR-013 to BR-024, BR-043, BR-044)
            $this->diffRequestBody($fromEp, $toEp);

            // Responses (BR-025 to BR-034, BR-045)
            $this->diffResponses($fromEp, $toEp);

            // Cross-cutting (BR-035 to BR-042, BR-043, BR-044)
            $this->diffCrossCutting($fromEp, $toEp, $fromNormalized, $toNormalized);
        }

        // Deduplicate by fingerprint
        $this->deduplicate();

        return $this->changes;
    }

    /** @return array<string, array<string, mixed>> */
    private function indexEndpoints(array $normalized): array
    {
        $result = [];
        foreach ($normalized['endpoints'] ?? [] as $ep) {
            $result[$ep['key']] = $ep;
        }

        return $result;
    }

    private function diffParameters(array $fromEp, array $toEp): void
    {
        $fromParams = $this->indexBy($fromEp['parameters'] ?? [], fn ($p) => ($p['in'] ?? '') . '|' . ($p['name'] ?? ''));
        $toParams = $this->indexBy($toEp['parameters'] ?? [], fn ($p) => ($p['in'] ?? '') . '|' . ($p['name'] ?? ''));

        // BR-005/BR-006: New parameters
        foreach ($toParams as $key => $toParam) {
            if (! isset($fromParams[$key])) {
                if ($toParam['required'] ?? false) {
                    $this->addChange('BR-005', 'required_parameter_added', 'critical',
                        $fromEp['method'], $fromEp['path'],
                        '/paths/' . $this->escapePointer($fromEp['path']) . '/' . strtolower($fromEp['method']) . '/parameters',
                        null, $toParam,
                        "Added required parameter \"{$toParam['name']}\" to {$fromEp['method']} {$fromEp['path']}.");
                } else {
                    $this->addChange('BR-006', 'optional_parameter_added', 'info',
                        $fromEp['method'], $fromEp['path'],
                        '/paths/' . $this->escapePointer($fromEp['path']) . '/' . strtolower($fromEp['method']) . '/parameters',
                        null, $toParam,
                        "Added optional parameter \"{$toParam['name']}\" to {$fromEp['method']} {$fromEp['path']}.");
                }
            }
        }

        // BR-007: Removed parameters
        foreach ($fromParams as $key => $fromParam) {
            if (! isset($toParams[$key])) {
                $this->addChange('BR-007', 'parameter_removed', 'warning',
                    $fromEp['method'], $fromEp['path'],
                    '/paths/' . $this->escapePointer($fromEp['path']) . '/' . strtolower($fromEp['method']) . '/parameters',
                    $fromParam, null,
                    "Removed parameter \"{$fromParam['name']}\" from {$fromEp['method']} {$fromEp['path']}.");
            }
        }

        // Compare common parameters
        foreach ($fromParams as $key => $fromParam) {
            if (! isset($toParams[$key])) {
                continue;
            }
            $toParam = $toParams[$key];

            // BR-008: became required
            if (! ($fromParam['required'] ?? false) && ($toParam['required'] ?? false)) {
                $this->addChange('BR-008', 'parameter_became_required', 'critical',
                    $fromEp['method'], $fromEp['path'],
                    '/paths/' . $this->escapePointer($fromEp['path']) . '/' . strtolower($fromEp['method']) . '/parameters',
                    $fromParam, $toParam,
                    "Parameter \"{$fromParam['name']}\" became required in {$fromEp['method']} {$fromEp['path']}.");
            }

            // BR-009: became optional
            if (($fromParam['required'] ?? false) && ! ($toParam['required'] ?? false)) {
                $this->addChange('BR-009', 'parameter_became_optional', 'info',
                    $fromEp['method'], $fromEp['path'],
                    '/paths/' . $this->escapePointer($fromEp['path']) . '/' . strtolower($fromEp['method']) . '/parameters',
                    $fromParam, $toParam,
                    "Parameter \"{$fromParam['name']}\" became optional in {$fromEp['method']} {$fromEp['path']}.");
            }

            // BR-010: type changed
            $fromType = $fromParam['schema']['type'] ?? null;
            $toType = $toParam['schema']['type'] ?? null;
            if ($fromType !== null && $toType !== null && $fromType !== $toType) {
                $this->addChange('BR-010', 'parameter_type_changed', 'critical',
                    $fromEp['method'], $fromEp['path'],
                    '/paths/' . $this->escapePointer($fromEp['path']) . '/' . strtolower($fromEp['method']) . '/parameters',
                    $fromParam, $toParam,
                    "Changed type of parameter \"{$fromParam['name']}\" from {$fromType} to {$toType} in {$fromEp['method']} {$fromEp['path']}.");
            }

            // BR-011: enum narrowed
            $fromEnum = $fromParam['schema']['enum'] ?? null;
            $toEnum = $toParam['schema']['enum'] ?? null;
            if (is_array($fromEnum) && is_array($toEnum)) {
                $removed = array_diff($fromEnum, $toEnum);
                if (count($removed) > 0 && count($toEnum) < count($fromEnum)) {
                    $this->addChange('BR-011', 'parameter_enum_narrowed', 'critical',
                        $fromEp['method'], $fromEp['path'],
                        '/paths/' . $this->escapePointer($fromEp['path']) . '/' . strtolower($fromEp['method']) . '/parameters',
                        $fromParam, $toParam,
                        "Narrowed enum of parameter \"{$fromParam['name']}\" in {$fromEp['method']} {$fromEp['path']}.");
                }
            }

            // BR-012: constraint tightened
            $constraints = ['minimum', 'maximum', 'minLength', 'maxLength', 'pattern', 'minItems', 'maxItems'];
            foreach ($constraints as $constraint) {
                $fromVal = $fromParam['schema'][$constraint] ?? null;
                $toVal = $toParam['schema'][$constraint] ?? null;
                if ($fromVal !== null && $toVal !== null && $fromVal !== $toVal) {
                    // Simplified: any change to a constraint is a warning
                    $this->addChange('BR-012', 'parameter_constraint_tightened', 'warning',
                        $fromEp['method'], $fromEp['path'],
                        '/paths/' . $this->escapePointer($fromEp['path']) . '/' . strtolower($fromEp['method']) . '/parameters',
                        $fromParam, $toParam,
                        "Tightened constraint \"{$constraint}\" on parameter \"{$fromParam['name']}\" in {$fromEp['method']} {$fromEp['path']}.");
                    break; // One change per parameter for constraints
                }
            }
        }
    }

    /** @param array<int, mixed> $items */
    private function indexBy(array $items, callable $keyFn): array
    {
        $result = [];
        foreach ($items as $item) {
            $result[$keyFn($item)] = $item;
        }

        return $result;
    }

    private function escapePointer(string $path): string
    {
        return str_replace(['~', '/'], ['~0', '~1'], $path);
    }

    private function addChange(
        string $ruleId,
        string $kind,
        string $severity,
        string $method,
        string $path,
        string $pointer,
        mixed $before,
        mixed $after,
        string $description,
    ): void
    {
        $fingerprint = hash('sha256', implode('|', [
            $ruleId,
            $method,
            $path,
            $pointer,
            json_encode($before, JSON_SORT_KEYS | JSON_UNESCAPED_SLASHES),
            json_encode($after, JSON_SORT_KEYS | JSON_UNESCAPED_SLASHES),
        ]));

        $this->changes[] = [
            'fingerprint' => $fingerprint,
            'rule_id' => $ruleId,
            'kind' => $kind,
            'severity' => $severity,
            'breaking' => $severity === 'critical',
            'method' => $method,
            'path' => $path,
            'pointer' => $pointer,
            'before' => $before,
            'after' => $after,
            'description' => $description,
        ];
    }

    private function diffRequestBody(array $fromEp, array $toEp): void
    {
        $fromBody = $fromEp['request_body'] ?? null;
        $toBody = $toEp['request_body'] ?? null;

        // BR-013: became required
        if (is_array($fromBody) && is_array($toBody)) {
            if (! ($fromBody['required'] ?? false) && ($toBody['required'] ?? false)) {
                $this->addChange('BR-013', 'request_body_became_required', 'critical',
                    $fromEp['method'], $fromEp['path'],
                    '/paths/' . $this->escapePointer($fromEp['path']) . '/' . strtolower($fromEp['method']) . '/requestBody/required',
                    $fromBody['required'] ?? false, true,
                    "Request body became required in {$fromEp['method']} {$fromEp['path']}.");
            }

            // BR-014: became optional
            if (($fromBody['required'] ?? false) && ! ($toBody['required'] ?? false)) {
                $this->addChange('BR-014', 'request_body_became_optional', 'info',
                    $fromEp['method'], $fromEp['path'],
                    '/paths/' . $this->escapePointer($fromEp['path']) . '/' . strtolower($fromEp['method']) . '/requestBody/required',
                    true, false,
                    "Request body became optional in {$fromEp['method']} {$fromEp['path']}.");
            }

            // Media types (BR-043, BR-044)
            $fromMedia = array_keys($fromBody['content'] ?? []);
            $toMedia = array_keys($toBody['content'] ?? []);

            foreach (array_diff($fromMedia, $toMedia) as $mt) {
                $this->addChange('BR-043', 'request_media_type_removed', 'critical',
                    $fromEp['method'], $fromEp['path'],
                    '/paths/' . $this->escapePointer($fromEp['path']) . '/' . strtolower($fromEp['method']) . '/requestBody/content',
                    $mt, null,
                    "Removed media type \"{$mt}\" from request body in {$fromEp['method']} {$fromEp['path']}.");
            }

            foreach (array_diff($toMedia, $fromMedia) as $mt) {
                $this->addChange('BR-044', 'request_media_type_added', 'info',
                    $fromEp['method'], $fromEp['path'],
                    '/paths/' . $this->escapePointer($fromEp['path']) . '/' . strtolower($fromEp['method']) . '/requestBody/content',
                    null, $mt,
                    "Added media type \"{$mt}\" to request body in {$fromEp['method']} {$fromEp['path']}.");
            }

            // Schema field diffing (use first common media type, default application/json)
            $commonMedia = array_intersect($fromMedia, $toMedia);
            $mediaType = ! empty($commonMedia) ? reset($commonMedia) : 'application/json';

            $fromSchema = $fromBody['content'][$mediaType]['schema'] ?? null;
            $toSchema = $toBody['content'][$mediaType]['schema'] ?? null;

            if (is_array($fromSchema) && is_array($toSchema)) {
                $this->diffSchemaFields($fromSchema, $toSchema, $fromEp, 'request');
            }
        } elseif ($fromBody === null && is_array($toBody)) {
            // Request body added
            $this->addChange('BR-017', 'request_body_added', 'info',
                $fromEp['method'], $fromEp['path'],
                '/paths/' . $this->escapePointer($fromEp['path']) . '/' . strtolower($fromEp['method']) . '/requestBody',
                null, $toBody,
                "Added request body to {$fromEp['method']} {$fromEp['path']}.");
        } elseif (is_array($fromBody) && $toBody === null) {
            // Request body removed
            $this->addChange('BR-015', 'request_body_removed', 'critical',
                $fromEp['method'], $fromEp['path'],
                '/paths/' . $this->escapePointer($fromEp['path']) . '/' . strtolower($fromEp['method']) . '/requestBody',
                $fromBody, null,
                "Removed request body from {$fromEp['method']} {$fromEp['path']}.");
        }
    }

    private function diffSchemaFields(array $fromSchema, array $toSchema, array $ep, string $context): void
    {
        $fromProps = $fromSchema['properties'] ?? [];
        $toProps = $toSchema['properties'] ?? [];
        $fromRequired = $fromSchema['required'] ?? [];
        $toRequired = $toSchema['required'] ?? [];

        // BR-016/BR-017: New fields
        foreach (array_diff(array_keys($toProps), array_keys($fromProps)) as $field) {
            if (in_array($field, $toRequired, true)) {
                $this->addChange('BR-016', 'request_required_field_added', 'critical',
                    $ep['method'], $ep['path'],
                    '/paths/' . $this->escapePointer($ep['path']) . '/' . strtolower($ep['method']) . '/requestBody',
                    null, $toProps[$field],
                    "Added required field \"{$field}\" to request body in {$ep['method']} {$ep['path']}.");
            } else {
                $this->addChange('BR-017', 'request_optional_field_added', 'info',
                    $ep['method'], $ep['path'],
                    '/paths/' . $this->escapePointer($ep['path']) . '/' . strtolower($ep['method']) . '/requestBody',
                    null, $toProps[$field],
                    "Added optional field \"{$field}\" to request body in {$ep['method']} {$ep['path']}.");
            }
        }

        // BR-015: Removed fields
        foreach (array_diff(array_keys($fromProps), array_keys($toProps)) as $field) {
            $this->addChange('BR-015', 'request_field_removed', 'warning',
                $ep['method'], $ep['path'],
                '/paths/' . $this->escapePointer($ep['path']) . '/' . strtolower($ep['method']) . '/requestBody',
                $fromProps[$field], null,
                "Removed field \"{$field}\" from request body in {$ep['method']} {$ep['path']}.");
        }

        // Compare common fields
        foreach (array_intersect(array_keys($fromProps), array_keys($toProps)) as $field) {
            $fromField = $fromProps[$field];
            $toField = $toProps[$field];

            // BR-018: type changed
            $fromType = $fromField['type'] ?? null;
            $toType = $toField['type'] ?? null;
            if ($fromType !== null && $toType !== null && $fromType !== $toType) {
                $this->addChange('BR-018', 'request_field_type_changed', 'critical',
                    $ep['method'], $ep['path'],
                    '/paths/' . $this->escapePointer($ep['path']) . '/' . strtolower($ep['method']) . '/requestBody',
                    $fromField, $toField,
                    "Changed type of field \"{$field}\" from {$fromType} to {$toType} in {$ep['method']} {$ep['path']}.");
            }

            // BR-019: format changed
            $fromFormat = $fromField['format'] ?? null;
            $toFormat = $toField['format'] ?? null;
            if ($fromType === $toType && $fromFormat !== null && $toFormat !== null && $fromFormat !== $toFormat) {
                $this->addChange('BR-019', 'request_field_format_changed', 'warning',
                    $ep['method'], $ep['path'],
                    '/paths/' . $this->escapePointer($ep['path']) . '/' . strtolower($ep['method']) . '/requestBody',
                    $fromField, $toField,
                    "Changed format of field \"{$field}\" in {$ep['method']} {$ep['path']}.");
            }

            // BR-020: enum narrowed
            $fromEnum = $fromField['enum'] ?? null;
            $toEnum = $toField['enum'] ?? null;
            if (is_array($fromEnum) && is_array($toEnum)) {
                $removed = array_diff($fromEnum, $toEnum);
                if (count($removed) > 0 && count($toEnum) < count($fromEnum)) {
                    $this->addChange('BR-020', 'request_enum_narrowed', 'critical',
                        $ep['method'], $ep['path'],
                        '/paths/' . $this->escapePointer($ep['path']) . '/' . strtolower($ep['method']) . '/requestBody',
                        $fromField, $toField,
                        "Narrowed enum of field \"{$field}\" in {$ep['method']} {$ep['path']}.");
                }
            }

            // BR-021: enum widened
            if (is_array($fromEnum) && is_array($toEnum)) {
                $added = array_diff($toEnum, $fromEnum);
                if (count($added) > 0 && count($toEnum) > count($fromEnum)) {
                    $this->addChange('BR-021', 'request_enum_widened', 'info',
                        $ep['method'], $ep['path'],
                        '/paths/' . $this->escapePointer($ep['path']) . '/' . strtolower($ep['method']) . '/requestBody',
                        $fromField, $toField,
                        "Widened enum of field \"{$field}\" in {$ep['method']} {$ep['path']}.");
                }
            }

            // BR-022: became non-nullable
            $fromNullable = $fromField['nullable'] ?? false;
            $toNullable = $toField['nullable'] ?? false;
            if ($fromNullable && ! $toNullable) {
                $this->addChange('BR-022', 'request_field_became_non_nullable', 'critical',
                    $ep['method'], $ep['path'],
                    '/paths/' . $this->escapePointer($ep['path']) . '/' . strtolower($ep['method']) . '/requestBody',
                    $fromField, $toField,
                    "Field \"{$field}\" became non-nullable in {$ep['method']} {$ep['path']}.");
            }

            // BR-023: constraint tightened (simplified)
            $constraints = ['minimum', 'maximum', 'minLength', 'maxLength', 'pattern', 'minItems', 'maxItems'];
            foreach ($constraints as $constraint) {
                $fromVal = $fromField[$constraint] ?? null;
                $toVal = $toField[$constraint] ?? null;
                if ($fromVal !== null && $toVal !== null && $fromVal !== $toVal) {
                    $this->addChange('BR-023', 'request_constraint_tightened', 'warning',
                        $ep['method'], $ep['path'],
                        '/paths/' . $this->escapePointer($ep['path']) . '/' . strtolower($ep['method']) . '/requestBody',
                        $fromField, $toField,
                        "Tightened constraint on field \"{$field}\" in {$ep['method']} {$ep['path']}.");
                    break;
                }
            }

            // BR-024: additionalProperties false
            $fromAP = $fromField['additionalProperties'] ?? null;
            $toAP = $toField['additionalProperties'] ?? null;
            if ($fromAP !== false && $toAP === false) {
                $this->addChange('BR-024', 'additional_properties_false', 'warning',
                    $ep['method'], $ep['path'],
                    '/paths/' . $this->escapePointer($ep['path']) . '/' . strtolower($ep['method']) . '/requestBody',
                    $fromField, $toField,
                    "Set additionalProperties to false on field \"{$field}\" in {$ep['method']} {$ep['path']}.");
            }
        }

        // Required list changes
        $addedRequired = array_diff($toRequired, $fromRequired);
        foreach ($addedRequired as $field) {
            if (! isset($fromProps[$field]) && isset($toProps[$field])) {
                // Already handled as BR-016
            }
        }
    }

    private function diffResponses(array $fromEp, array $toEp): void
    {
        $fromResponses = $fromEp['responses'] ?? [];
        $toResponses = $toEp['responses'] ?? [];

        $fromStatuses = array_keys($fromResponses);
        $toStatuses = array_keys($toResponses);

        // Determine success status codes (2xx, 3xx)
        $isSuccess = fn ($s) => preg_match('/^[23]\d{2}$/', (string) $s);

        $fromSuccess = array_filter($fromStatuses, $isSuccess);
        $toSuccess = array_filter($toStatuses, $isSuccess);

        // BR-025: Success status code removed
        foreach (array_diff($fromSuccess, $toSuccess) as $status) {
            // Check if it's a simple removal (not a change)
            if (count($fromSuccess) === 1 && count($toSuccess) === 1 && array_values($fromSuccess)[0] === $status) {
                // This might be BR-027 (changed), handle below
            } else {
                $this->addChange('BR-025', 'success_status_removed', 'critical',
                    $fromEp['method'], $fromEp['path'],
                    '/paths/' . $this->escapePointer($fromEp['path']) . '/' . strtolower($fromEp['method']) . '/responses',
                    $status, null,
                    "Removed success status code {$status} from {$fromEp['method']} {$fromEp['path']}.");
            }
        }

        // BR-026: Success status code added
        foreach (array_diff($toSuccess, $fromSuccess) as $status) {
            $this->addChange('BR-026', 'success_status_added', 'warning',
                $fromEp['method'], $fromEp['path'],
                '/paths/' . $this->escapePointer($fromEp['path']) . '/' . strtolower($fromEp['method']) . '/responses',
                null, $status,
                "Added success status code {$status} to {$fromEp['method']} {$fromEp['path']}.");
        }

        // BR-027: Success status code changed (one removed, one added)
        if (count($fromSuccess) === 1 && count($toSuccess) === 1) {
            $fromStatus = array_values($fromSuccess)[0];
            $toStatus = array_values($toSuccess)[0];
            if ($fromStatus !== $toStatus) {
                $this->addChange('BR-027', 'success_status_changed', 'critical',
                    $fromEp['method'], $fromEp['path'],
                    '/paths/' . $this->escapePointer($fromEp['path']) . '/' . strtolower($fromEp['method']) . '/responses',
                    $fromStatus, $toStatus,
                    "Changed success status code from {$fromStatus} to {$toStatus} in {$fromEp['method']} {$fromEp['path']}.");
            }
        }

        // BR-028, BR-029, BR-030, BR-031: Response body field changes
        foreach (array_intersect($fromStatuses, $toStatuses) as $status) {
            $fromResp = $fromResponses[$status] ?? [];
            $toResp = $toResponses[$status] ?? [];

            $fromContent = $fromResp['content'] ?? [];
            $toContent = $toResp['content'] ?? [];

            // BR-032: Media type removed
            foreach (array_diff(array_keys($fromContent), array_keys($toContent)) as $mt) {
                $this->addChange('BR-032', 'response_media_type_removed', 'critical',
                    $fromEp['method'], $fromEp['path'],
                    '/paths/' . $this->escapePointer($fromEp['path']) . '/' . strtolower($fromEp['method']) . '/responses/' . $status . '/content',
                    $mt, null,
                    "Removed media type \"{$mt}\" from response {$status} in {$fromEp['method']} {$fromEp['path']}.");
            }

            // Compare schemas for common media types
            $commonMedia = array_intersect(array_keys($fromContent), array_keys($toContent));
            foreach ($commonMedia as $mt) {
                $fromSchema = $fromContent[$mt]['schema'] ?? null;
                $toSchema = $toContent[$mt]['schema'] ?? null;

                if (is_array($fromSchema) && is_array($toSchema)) {
                    $fromProps = $fromSchema['properties'] ?? [];
                    $toProps = $toSchema['properties'] ?? [];
                    $fromRequired = $fromSchema['required'] ?? [];
                    $toRequired = $toSchema['required'] ?? [];

                    // BR-028: Response field removed
                    foreach (array_diff(array_keys($fromProps), array_keys($toProps)) as $field) {
                        $this->addChange('BR-028', 'response_field_removed', 'critical',
                            $fromEp['method'], $fromEp['path'],
                            '/paths/' . $this->escapePointer($fromEp['path']) . '/' . strtolower($fromEp['method']) . '/responses/' . $status . '/content/' . $mt,
                            $fromProps[$field], null,
                            "Removed field \"{$field}\" from response {$status} in {$fromEp['method']} {$fromEp['path']}.");
                    }

                    // BR-045: Response field added
                    foreach (array_diff(array_keys($toProps), array_keys($fromProps)) as $field) {
                        $this->addChange('BR-045', 'response_field_added', 'info',
                            $fromEp['method'], $fromEp['path'],
                            '/paths/' . $this->escapePointer($fromEp['path']) . '/' . strtolower($fromEp['method']) . '/responses/' . $status . '/content/' . $mt,
                            null, $toProps[$field],
                            "Added field \"{$field}\" to response {$status} in {$fromEp['method']} {$fromEp['path']}.");
                    }

                    // BR-029: Response field type changed
                    foreach (array_intersect(array_keys($fromProps), array_keys($toProps)) as $field) {
                        $fromType = $fromProps[$field]['type'] ?? null;
                        $toType = $toProps[$field]['type'] ?? null;
                        if ($fromType !== null && $toType !== null && $fromType !== $toType) {
                            $this->addChange('BR-029', 'response_field_type_changed', 'critical',
                                $fromEp['method'], $fromEp['path'],
                                '/paths/' . $this->escapePointer($fromEp['path']) . '/' . strtolower($fromEp['method']) . '/responses/' . $status . '/content/' . $mt,
                                $fromProps[$field], $toProps[$field],
                                "Changed type of field \"{$field}\" in response {$status} in {$fromEp['method']} {$fromEp['path']}.");
                        }
                    }

                    // BR-030: Response field became optional
                    foreach (array_intersect(array_keys($fromProps), array_keys($toProps)) as $field) {
                        if (in_array($field, $fromRequired, true) && ! in_array($field, $toRequired, true)) {
                            $this->addChange('BR-030', 'response_field_became_optional', 'warning',
                                $fromEp['method'], $fromEp['path'],
                                '/paths/' . $this->escapePointer($fromEp['path']) . '/' . strtolower($fromEp['method']) . '/responses/' . $status . '/content/' . $mt,
                                $fromProps[$field], $toProps[$field],
                                "Field \"{$field}\" became optional in response {$status} in {$fromEp['method']} {$fromEp['path']}.");
                        }
                    }

                    // BR-031: Response enum changed
                    foreach (array_intersect(array_keys($fromProps), array_keys($toProps)) as $field) {
                        $fromEnum = $fromProps[$field]['enum'] ?? null;
                        $toEnum = $toProps[$field]['enum'] ?? null;
                        if (is_array($fromEnum) && is_array($toEnum) && $fromEnum !== $toEnum) {
                            $this->addChange('BR-031', 'response_enum_changed', 'warning',
                                $fromEp['method'], $fromEp['path'],
                                '/paths/' . $this->escapePointer($fromEp['path']) . '/' . strtolower($fromEp['method']) . '/responses/' . $status . '/content/' . $mt,
                                $fromProps[$field], $toProps[$field],
                                "Changed enum of field \"{$field}\" in response {$status} in {$fromEp['method']} {$fromEp['path']}.");
                        }
                    }
                }
            }

            // BR-033: Response header removed
            $fromHeaders = $fromResp['headers'] ?? [];
            $toHeaders = $toResp['headers'] ?? [];
            foreach (array_diff(array_keys($fromHeaders), array_keys($toHeaders)) as $header) {
                $this->addChange('BR-033', 'response_header_removed', 'warning',
                    $fromEp['method'], $fromEp['path'],
                    '/paths/' . $this->escapePointer($fromEp['path']) . '/' . strtolower($fromEp['method']) . '/responses/' . $status . '/headers',
                    $fromHeaders[$header], null,
                    "Removed header \"{$header}\" from response {$status} in {$fromEp['method']} {$fromEp['path']}.");
            }

            // BR-034: Required response header added
            foreach (array_diff(array_keys($toHeaders), array_keys($fromHeaders)) as $header) {
                if (($toHeaders[$header]['required'] ?? false)) {
                    $this->addChange('BR-034', 'required_response_header_added', 'warning',
                        $fromEp['method'], $fromEp['path'],
                        '/paths/' . $this->escapePointer($fromEp['path']) . '/' . strtolower($fromEp['method']) . '/responses/' . $status . '/headers',
                        null, $toHeaders[$header],
                        "Added required header \"{$header}\" to response {$status} in {$fromEp['method']} {$fromEp['path']}.");
                }
            }
        }
    }

    private function diffCrossCutting(array $fromEp, array $toEp, array $fromNormalized, array $toNormalized): void
    {
        // BR-035: Security requirement changed
        $fromSecurity = $fromEp['security'] ?? [];
        $toSecurity = $toEp['security'] ?? [];
        if (json_encode($fromSecurity, JSON_SORT_KEYS) !== json_encode($toSecurity, JSON_SORT_KEYS)) {
            // Determine if it's a critical change or warning
            $severity = 'critical';
            // Removing authentication entirely is a warning
            if (empty($toSecurity) && ! empty($fromSecurity)) {
                $severity = 'warning';
            }
            // Adding an optional security alternative is info
            if (count($toSecurity) > count($fromSecurity) && ! empty($fromSecurity)) {
                $severity = 'info';
            }
            $this->addChange('BR-035', 'security_requirement_changed', $severity,
                $fromEp['method'], $fromEp['path'],
                '/paths/' . $this->escapePointer($fromEp['path']) . '/' . strtolower($fromEp['method']) . '/security',
                $fromSecurity, $toSecurity,
                "Changed security requirements for {$fromEp['method']} {$fromEp['path']}.");
        }

        // BR-036: Path changed (handled at operation level - same operationId different path)
        // This is detected when operationId matches but path differs
        // (Simplified: handled by operation removal/addition)

        // BR-037: Referenced component removed
        // Check if any $ref in the operation points to a component not in toNormalized
        $toComponents = $toNormalized['components']['schemas'] ?? [];
        $this->checkRefs($fromEp, $toComponents, $fromEp['method'], $fromEp['path']);

        // BR-038: Union member removed
        $this->checkUnionMembers($fromEp, $toEp, $fromEp['method'], $fromEp['path']);

        // BR-040: Array item type changed
        $this->checkArrayItemTypes($fromEp, $toEp, $fromEp['method'], $fromEp['path']);

        // BR-041: Default value changed
        $this->checkDefaults($fromEp, $toEp, $fromEp['method'], $fromEp['path']);

        // BR-042: Metadata-only change
        $fromSummary = $fromEp['summary'] ?? null;
        $toSummary = $toEp['summary'] ?? null;
        $fromDesc = $fromEp['description'] ?? null;
        $toDesc = $toEp['description'] ?? null;
        $fromTags = $fromEp['tags'] ?? [];
        $toTags = $toEp['tags'] ?? [];

        if ($fromSummary !== $toSummary || $fromDesc !== $toDesc || $fromTags !== $toTags) {
            // Only emit if no structural changes were already detected for this endpoint
            $hasStructural = false;
            foreach ($this->changes as $c) {
                if ($c['method'] === $fromEp['method'] && $c['path'] === $fromEp['path'] && $c['severity'] !== 'info') {
                    $hasStructural = true;
                    break;
                }
            }
            if (! $hasStructural) {
                $this->addChange('BR-042', 'metadata_changed', 'info',
                    $fromEp['method'], $fromEp['path'],
                    '/paths/' . $this->escapePointer($fromEp['path']) . '/' . strtolower($fromEp['method']),
                    ['summary' => $fromSummary, 'description' => $fromDesc, 'tags' => $fromTags],
                    ['summary' => $toSummary, 'description' => $toDesc, 'tags' => $toTags],
                    "Metadata changed for {$fromEp['method']} {$fromEp['path']}.");
            }
        }
    }

    private function checkRefs(array $ep, array $toComponents, string $method, string $path): void
    {
        // Recursively find $ref in the endpoint and check if target exists in toComponents
        $refs = $this->extractRefs($ep);
        foreach ($refs as $ref) {
            if (str_starts_with($ref, '#/components/schemas/')) {
                $schemaName = substr($ref, strlen('#/components/schemas/'));
                if (! isset($toComponents[$schemaName])) {
                    $this->addChange('BR-037', 'referenced_component_removed', 'critical',
                        $method, $path,
                        '/paths/' . $this->escapePointer($path) . '/' . strtolower($method),
                        $ref, null,
                        "Referenced component \"{$schemaName}\" removed in {$method} {$path}.");
                }
            }
        }
    }

    /** @return array<int, string> */
    private function extractRefs(mixed $value): array
    {
        $refs = [];
        if (is_array($value)) {
            if (isset($value['$ref']) && is_string($value['$ref'])) {
                $refs[] = $value['$ref'];
            }
            foreach ($value as $item) {
                $refs = array_merge($refs, $this->extractRefs($item));
            }
        }

        return array_unique($refs);
    }

    private function checkUnionMembers(array $fromEp, array $toEp, string $method, string $path): void
    {
        // Check oneOf/anyOf in request body and responses
        $fromBody = $fromEp['request_body']['content']['application/json']['schema'] ?? null;
        $toBody = $toEp['request_body']['content']['application/json']['schema'] ?? null;

        foreach (['oneOf', 'anyOf'] as $key) {
            if (isset($fromBody[$key]) && is_array($fromBody[$key]) && isset($toBody[$key]) && is_array($toBody[$key])) {
                $fromCount = count($fromBody[$key]);
                $toCount = count($toBody[$key]);
                if ($toCount < $fromCount) {
                    $this->addChange('BR-038', 'union_member_removed', 'critical',
                        $method, $path,
                        '/paths/' . $this->escapePointer($path) . '/' . strtolower($method) . '/requestBody',
                        $fromBody[$key], $toBody[$key],
                        "Removed a member from {$key} in {$method} {$path}.");
                }
            }
        }
    }

    private function checkArrayItemTypes(array $fromEp, array $toEp, string $method, string $path): void
    {
        // Check array items type changes in request body fields
        $fromBody = $fromEp['request_body']['content']['application/json']['schema'] ?? null;
        $toBody = $toEp['request_body']['content']['application/json']['schema'] ?? null;

        if (! is_array($fromBody) || ! is_array($toBody)) {
            return;
        }

        $fromProps = $fromBody['properties'] ?? [];
        $toProps = $toBody['properties'] ?? [];

        foreach (array_intersect(array_keys($fromProps), array_keys($toProps)) as $field) {
            $fromField = $fromProps[$field];
            $toField = $toProps[$field];

            if (($fromField['type'] ?? '') === 'array' && ($toField['type'] ?? '') === 'array') {
                $fromItemType = $fromField['items']['type'] ?? null;
                $toItemType = $toField['items']['type'] ?? null;
                if ($fromItemType !== null && $toItemType !== null && $fromItemType !== $toItemType) {
                    $this->addChange('BR-040', 'array_item_type_changed', 'critical',
                        $method, $path,
                        '/paths/' . $this->escapePointer($path) . '/' . strtolower($method) . '/requestBody',
                        $fromField, $toField,
                        "Changed array item type for field \"{$field}\" in {$method} {$path}.");
                }
            }
        }
    }

    private function checkDefaults(array $fromEp, array $toEp, string $method, string $path): void
    {
        $fromBody = $fromEp['request_body']['content']['application/json']['schema'] ?? null;
        $toBody = $toEp['request_body']['content']['application/json']['schema'] ?? null;

        if (! is_array($fromBody) || ! is_array($toBody)) {
            return;
        }

        $fromProps = $fromBody['properties'] ?? [];
        $toProps = $toBody['properties'] ?? [];

        foreach (array_intersect(array_keys($fromProps), array_keys($toProps)) as $field) {
            $fromDefault = $fromProps[$field]['default'] ?? null;
            $toDefault = $toProps[$field]['default'] ?? null;
            if (array_key_exists('default', $fromProps[$field]) && array_key_exists('default', $toProps[$field]) && $fromDefault !== $toDefault) {
                $this->addChange('BR-041', 'default_value_changed', 'warning',
                    $method, $path,
                    '/paths/' . $this->escapePointer($path) . '/' . strtolower($method) . '/requestBody',
                    $fromProps[$field], $toProps[$field],
                    "Changed default value for field \"{$field}\" in {$method} {$path}.");
            }
        }
    }

    private function deduplicate(): void
    {
        $seen = [];
        $unique = [];

        foreach ($this->changes as $change) {
            $key = $change['fingerprint'];
            if (! isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $change;
            }
        }

        $this->changes = $unique;
    }
}
