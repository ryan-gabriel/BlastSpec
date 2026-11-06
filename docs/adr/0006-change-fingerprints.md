# ADR-0006: Stable change fingerprints

- **Status:** Accepted
- **Date:** 2026-11-02
- **Deciders:** Architecture, Diff engine
- **Related:** `BREAKING_CHANGES.md`, `DATA_MODEL.md`

## Context

Changes are displayed, selected for impact analysis, and referenced by reports. If IDs change between runs, a report can point at a change that no longer exists, and re-running a diff creates duplicates. Array reordering and serialization differences must not affect identity.

## Decision

Every change has a deterministic `fingerprint`:

```text
fingerprint = sha256(
  br_rule_id + "|" +
  method + "|" +
  path + "|" +
  pointer + "|" +
  canonical_json(before) + "|" +
  canonical_json(after)
)
```

- `canonical_json` sorts object keys, removes insignificant whitespace, normalizes numbers, and sorts sets where order is not semantic (enums, required lists).
- `(diff_id, fingerprint)` is unique. Duplicate rules for the same location producing the same fingerprint are merged.
- Change IDs are UUIDv7 assigned at insert; the fingerprint is the stable identity across re-runs.
- Reports store `change_ids` for their generation and include fingerprints in the API for traceability.

## Consequences

### Positive

- Re-running a diff is idempotent.
- Reports remain meaningful after regeneration.
- Deduplication is deterministic.
- Test fixtures can assert exact fingerprints.

### Negative

- Any change to a rule's canonicalization invalidates fingerprints; this requires an `engine_version` bump.
- Fingerprints are opaque to users; the UI must show rule and location.

## Alternatives considered

- **Array index as ID:** breaks on reordering.
- **Random UUID per detection:** breaks re-runs and report references.
- **Raw diff string hash:** sensitive to formatting and ordering.
