---
status: approved
owner: Engineering
last_verified: 2026-11-02
source_of_truth_for:
  - documentation map
  - maintenance rules
related:
  - AGENTS.md
  - PRD.md
  - ARCHITECTURE.md
---

# Documentation Map

This directory holds supporting and supporting-in-depth documents. Normative engineering docs live at the repository root.

---

## 1. Map by purpose

| Need | Read |
|---|---|
| Starting any work | [`../AGENTS.md`](../AGENTS.md) |
| Product behavior | [`../PRD.md`](../PRD.md) |
| System design | [`../ARCHITECTURE.md`](../ARCHITECTURE.md) |
| Schema | [`../DATA_MODEL.md`](../DATA_MODEL.md) |
| HTTP | [`../API_CONTRACT.md`](../API_CONTRACT.md) and [`../contracts/openapi.yaml`](../contracts/openapi.yaml) |
| Diffing rules | [`../BREAKING_CHANGES.md`](../BREAKING_CHANGES.md) |
| Algorithms | [`../GRAPH_ALGORITHMS.md`](../GRAPH_ALGORITHMS.md) |
| Visual system | [`../DESIGN.md`](../DESIGN.md) |
| Screens | [`../docs/SCREENS.md`](../docs/SCREENS.md) |
| Coding standards | [`../RULES.md`](../RULES.md) |
| Tests | [`../TESTING.md`](../TESTING.md) |
| Security | [`../SECURITY.md`](../SECURITY.md) |
| Deploy / ops | [`../DEPLOYMENT.md`](../DEPLOYMENT.md), [`../ENVIRONMENT.md`](../ENVIRONMENT.md) |
| Delivery | [`../ROADMAP.md`](../ROADMAP.md), [`../TASKS.md`](../TASKS.md) |
| Vocabulary | [`../GLOSSARY.md`](../GLOSSARY.md) |
| Decisions | [`docs/adr/`](adr/README.md) |
| Overview | [`../README.md`](../README.md) |

---

## 2. Read order quick reference

| You are… | Read |
|---|---|
| Writing an endpoint | OpenAPI → API contract → data model → rules |
| Changing behavior | `PRD.md` → `TASKS.md` → `TESTING.md` |
| Touching the database | `DATA_MODEL.md` → `ARCHITECTURE.md` → `RULES.md` |
| Adding/removing a breaking rule | `BREAKING_CHANGES.md` → `TESTING.md` → `DATA_MODEL.md` |
| Doing graph work | `GRAPH_ALGORITHMS.md` → `DATA_MODEL.md` → `ARCHITECTURE.md` |
| Designing UI | `DESIGN.md` → `docs/SCREENS.md` → `RULES.md` §6 |
| Handling async/reliability | `ARCHITECTURE.md` → `API_CONTRACT.md` → `SECURITY.md` |
| Deploying/configuring | `DEPLOYMENT.md` → `ENVIRONMENT.md` → `SECURITY.md` |

---

## 3. Ownership and maintenance

| Doc | Owner | Last verified |
|---|---|---|
| `AGENTS.md` | Engineering | 2026-11-02 |
| `PRD.md` | Product | 2026-11-02 |
| `ARCHITECTURE.md` | Architecture | 2026-11-02 |
| `DATA_MODEL.md` | Data | 2026-11-02 |
| `API_CONTRACT.md` | API | 2026-11-02 |
| `BREAKING_CHANGES.md` | Diff engine | 2026-11-02 |
| `GRAPH_ALGORITHMS.md` | Graph | 2026-11-02 |
| `DESIGN.md` | Design | 2026-11-02 |
| `docs/SCREENS.md` | Design | 2026-11-02 |
| `RULES.md` | Engineering | 2026-11-02 |
| `TESTING.md` | QA | 2026-11-02 |
| `SECURITY.md` | Security | 2026-11-02 |
| `DEPLOYMENT.md` | Platform | 2026-11-02 |
| `ENVIRONMENT.md` | Platform | 2026-11-02 |
| `ROADMAP.md` | Product | 2026-11-02 |
| `TASKS.md` | Engineering | 2026-11-02 |
| `GLOSSARY.md` | Product | 2026-11-02 |

Every doc records `status`, `owner`, and `last_verified` in its front matter.

---

## 4. ADR process

- Write an ADR in `docs/adr/000N-slug.md`.
- Number sequentially; never reuse a number.
- Lifecycle: `Proposed` → `Accepted` → `Superseded by ADR-XXXX`.
- Update `docs/adr/README.md` as the index.
- Update the owning engineering doc when a decision makes it stale.

---

## 5. Identifier conventions

| Prefix | Meaning | Owning doc |
|---|---|---|
| `FR-` | Functional requirement | `PRD.md` |
| `NFR-` | Non-functional requirement | `PRD.md` |
| `AC-` | Acceptance criterion | `PRD.md` |
| `BR-` | Breaking-change rule | `BREAKING_CHANGES.md` |
| `ERR-` | Error code | `API_CONTRACT.md` |
| `GRAPH-` | Graph algorithm/skill | `GRAPH_ALGORITHMS.md` |
| `TASK-` | Backlog ticket | `TASKS.md` |
| `ADR-` | Architecture decision | `docs/adr/` |

When a requirement is implemented, the task references it; when the task changes behavior, the doc is the source of truth.

---

## 6. Maintenance rules

1. Docs and behavior change in the same PR.
2. Contract changes: update OpenAPI first, then code, then generated types.
3. Schema changes: update `DATA_MODEL.md` before the migration.
4. Algorithm changes: update `GRAPH_ALGORITHMS.md` and add tests.
5. Severity changes: update `BREAKING_CHANGES.md`, fixtures, and `engine_version`.
6. UI changes: update `DESIGN.md` tokens or `docs/SCREENS.md` screens.
7. Env changes: update `ENVIRONMENT.md`, `.env.example`, and code.
8. Ops changes: update `DEPLOYMENT.md`.

---

## 7. Verification checklist for docs

- [ ] `markdownlint` passes.
- [ ] Mermaid blocks render.
- [ ] Relative links resolve.
- [ ] No new doc assumes a standalone Kuzu server or a published Kuzu port (ADR-0002 documents the correction).
- [ ] No contradictory severity, contract, or auth claims.
- [ ] Every requirement in `TASKS.md` links to accepted docs.
- [ ] Every error code in the catalog has an owner doc and a code.

---

## 8. Quick navigation

```text
docs/
├── README.md
├── SCREENS.md
└── adr/
    ├── README.md
    └── 0001-*.md … 0008-*.md
```

Support files that belong to the docs set (not the product):

- `docs/assets/` — screenshots, diagrams, report exports.
- `docs/reports/` — demo runs and acceptance snapshots.
