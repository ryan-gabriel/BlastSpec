"use client";

import { useState } from "react";
import Link from "next/link";
import AppShell from "@/components/features/shell/AppShell";
import { Panel } from "@/components/ui/Panel";
import { Button } from "@/components/ui/Button";
import { SeverityChip } from "@/components/ui/SeverityChip";

type Change = {
  id: string;
  rule_id: string;
  kind: string;
  severity: "critical" | "warning" | "info";
  breaking: boolean;
  method: string | null;
  path: string | null;
  pointer: string | null;
  before: unknown;
  after: unknown;
  description: string;
};

export default function DiffReviewPage() {
  const [severityFilter, setSeverityFilter] = useState<string>("");
  const [breakingOnly, setBreakingOnly] = useState(false);
  const [selectedChange, setSelectedChange] = useState<Change | null>(null);

  const changes: Change[] = [];

  const filtered = changes.filter((c) => {
    if (severityFilter && c.severity !== severityFilter) return false;
    if (breakingOnly && !c.breaking) return false;
    return true;
  });

  const counts = {
    critical: changes.filter((c) => c.severity === "critical").length,
    warning: changes.filter((c) => c.severity === "warning").length,
    info: changes.filter((c) => c.severity === "info").length,
  };

  return (
    <AppShell breadcrumb="Specs / billing / Diff">
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-h1 font-semibold tracking-tight">Diff 1.0.0 → 2.0.0</h1>
            <p className="mt-1 text-sm text-text-muted">
              Review detected changes and generate an impact report.
            </p>
          </div>
          <Button disabled={changes.length === 0}>Generate impact report</Button>
        </div>

        <div className="flex gap-3">
          <div className="rounded-panel border border-border bg-surface px-4 py-2 text-center">
            <div className="text-xs text-text-muted">Critical</div>
            <div className="text-lg font-semibold text-critical">{counts.critical}</div>
          </div>
          <div className="rounded-panel border border-border bg-surface px-4 py-2 text-center">
            <div className="text-xs text-text-muted">Warning</div>
            <div className="text-lg font-semibold text-warning">{counts.warning}</div>
          </div>
          <div className="rounded-panel border border-border bg-surface px-4 py-2 text-center">
            <div className="text-xs text-text-muted">Info</div>
            <div className="text-lg font-semibold text-info">{counts.info}</div>
          </div>
        </div>

        <div className="flex flex-wrap gap-2">
          <select
            value={severityFilter}
            onChange={(e) => setSeverityFilter(e.target.value)}
            className="h-9 rounded-control border border-border bg-ink-900 px-3 text-sm text-text"
          >
            <option value="">All severities</option>
            <option value="critical">Critical</option>
            <option value="warning">Warning</option>
            <option value="info">Info</option>
          </select>
          <label className="flex h-9 items-center gap-2 text-sm text-text-muted">
            <input
              type="checkbox"
              checked={breakingOnly}
              onChange={(e) => setBreakingOnly(e.target.checked)}
              className="h-4 w-4 rounded-control border-border bg-ink-900 accent-accent"
            />
            Breaking only
          </label>
        </div>

        <div className="grid grid-cols-1 gap-6 lg:grid-cols-[2fr_1fr]">
          <Panel title="Changes">
            {filtered.length === 0 ? (
              <p className="text-sm text-text-muted">
                No contract changes between these versions.
              </p>
            ) : (
              <ul className="divide-y divide-border">
                {filtered.map((change) => (
                  <li
                    key={change.id}
                    onClick={() => setSelectedChange(change)}
                    className={`flex cursor-pointer items-start gap-3 py-3 transition-colors hover:bg-surface-hover ${
                      selectedChange?.id === change.id ? "bg-surface-hover" : ""
                    }`}
                  >
                    <SeverityChip severity={change.severity} />
                    <div className="min-w-0 flex-1">
                      <div className="flex items-center gap-2">
                        <span className="font-mono text-xs text-text-muted">
                          {change.method} {change.path}
                        </span>
                        <span className="text-micro text-text-faint">{change.rule_id}</span>
                      </div>
                      <p className="mt-0.5 text-sm text-text">{change.description}</p>
                    </div>
                  </li>
                ))}
              </ul>
            )}
          </Panel>

          <Panel title="Detail">
            {selectedChange ? (
              <div className="space-y-4">
                <div>
                  <div className="text-xs text-text-muted">Rule</div>
                  <div className="font-mono text-sm">{selectedChange.rule_id}</div>
                </div>
                <div>
                  <div className="text-xs text-text-muted">Location</div>
                  <div className="font-mono text-xs text-text-faint">
                    {selectedChange.pointer ?? "—"}
                  </div>
                </div>
                <div>
                  <div className="text-xs text-text-muted">Fingerprint</div>
                  <div className="font-mono text-xs text-text-faint">
                    {selectedChange.id.slice(0, 10)}…
                  </div>
                </div>
                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <div className="text-xs text-text-muted">Before</div>
                    <pre className="mt-1 overflow-auto rounded-control border border-border bg-ink-950 p-2 font-mono text-xs text-critical">
                      {JSON.stringify(selectedChange.before, null, 2) ?? "—"}
                    </pre>
                  </div>
                  <div>
                    <div className="text-xs text-text-muted">After</div>
                    <pre className="mt-1 overflow-auto rounded-control border border-border bg-ink-950 p-2 font-mono text-xs text-success">
                      {JSON.stringify(selectedChange.after, null, 2) ?? "—"}
                    </pre>
                  </div>
                </div>
              </div>
            ) : (
              <p className="text-sm text-text-muted">Select a change to see details.</p>
            )}
          </Panel>
        </div>
      </div>
    </AppShell>
  );
}
