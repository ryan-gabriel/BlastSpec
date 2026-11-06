"use client";

import { useState } from "react";
import Link from "next/link";
import AppShell from "@/components/features/shell/AppShell";
import { Panel } from "@/components/ui/Panel";
import { Button } from "@/components/ui/Button";
import { SeverityChip } from "@/components/ui/SeverityChip";

type VersionRow = {
  id: string;
  version: string;
  openapi_version: string | null;
  endpoint_count: number;
  warning_count: number;
  parse_status: string;
  created_at: string;
};

export default function SpecOverviewPage() {
  const [tab, setTab] = useState<"overview" | "graph" | "versions" | "diffs">("overview");
  const [selectedVersions, setSelectedVersions] = useState<string[]>([]);

  const versions: VersionRow[] = [];

  const toggleVersion = (id: string) => {
    setSelectedVersions((prev) =>
      prev.includes(id) ? prev.filter((v) => v !== id) : [...prev, id].slice(-2),
    );
  };

  return (
    <AppShell breadcrumb="Specs / billing">
      <div className="space-y-6">
        <div className="flex items-start justify-between">
          <div>
            <h1 className="text-h1 font-semibold tracking-tight">Billing API</h1>
            <div className="mt-2 flex items-center gap-2 text-sm text-text-muted">
              <span className="rounded-control bg-accent-soft px-2 py-0.5 text-xs font-medium text-accent">
                billing
              </span>
              <span>Payments</span>
              <span className="font-mono text-xs text-text-faint">v2.0.0</span>
            </div>
          </div>
          <div className="flex gap-2">
            <Link href="/specs/spec-1/graph">
              <Button variant="secondary">Open graph</Button>
            </Link>
            <Link href="/specs/spec-1/diff?from=v1&to=v2">
              <Button>Run diff</Button>
            </Link>
            <Link href="/specs/upload">
              <Button variant="secondary">Upload version</Button>
            </Link>
          </div>
        </div>

        <div className="flex gap-1 border-b border-border">
          {(["overview", "graph", "versions", "diffs"] as const).map((t) => (
            <button
              key={t}
              onClick={() => setTab(t)}
              className={`h-9 px-4 text-sm capitalize transition-colors ${
                tab === t
                  ? "border-b-2 border-accent text-text"
                  : "text-text-muted hover:text-text"
              }`}
            >
              {t}
            </button>
          ))}
        </div>

        {tab === "overview" && (
          <div className="grid grid-cols-1 gap-6 lg:grid-cols-[2fr_1fr]">
            <Panel title="Endpoints">
              <p className="text-sm text-text-muted">
                No endpoints yet. Upload a spec to see them here.
              </p>
            </Panel>
            <Panel title="Consumers">
              <p className="text-sm text-text-muted">
                No consumers found. Add <code className="font-mono text-xs">x-consumers</code> to the
                spec or declare a binding.
              </p>
            </Panel>
          </div>
        )}

        {tab === "versions" && (
          <Panel
            title="Version history"
            actions={
              selectedVersions.length === 2 ? (
                <Link
                  href={`/specs/spec-1/diff?from=${selectedVersions[0]}&to=${selectedVersions[1]}`}
                >
                  <Button size="sm">Compare selected</Button>
                </Link>
              ) : null
            }
          >
            {versions.length === 0 ? (
              <p className="text-sm text-text-muted">
                Only one version. Upload a new version to compare.
              </p>
            ) : (
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-border text-left text-micro text-text-faint">
                    <th className="pb-3 pr-4 font-medium">Version</th>
                    <th className="pb-3 pr-4 font-medium">OpenAPI</th>
                    <th className="pb-3 pr-4 font-medium">Endpoints</th>
                    <th className="pb-3 pr-4 font-medium">Warnings</th>
                    <th className="pb-3 pr-4 font-medium">Created</th>
                    <th className="pb-3 pr-4 font-medium">Select</th>
                  </tr>
                </thead>
                <tbody>
                  {versions.map((v) => (
                    <tr key={v.id} className="border-b border-border">
                      <td className="py-3 pr-4 font-mono">{v.version}</td>
                      <td className="py-3 pr-4 text-text-muted">{v.openapi_version}</td>
                      <td className="py-3 pr-4 tabular-nums">{v.endpoint_count}</td>
                      <td className="py-3 pr-4 tabular-nums">{v.warning_count}</td>
                      <td className="py-3 pr-4 text-text-faint text-xs">{v.created_at}</td>
                      <td className="py-3 pr-4">
                        <input
                          type="checkbox"
                          checked={selectedVersions.includes(v.id)}
                          onChange={() => toggleVersion(v.id)}
                          className="h-4 w-4 rounded-control border-border bg-ink-900 accent-accent"
                        />
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </Panel>
        )}

        {tab === "graph" && (
          <Panel title="Graph">
            <p className="text-sm text-text-muted">
              Graph explorer loads here. Open the dedicated graph view for full interaction.
            </p>
            <div className="mt-3">
              <Link href="/specs/spec-1/graph">
                <Button variant="secondary" size="sm">
                  Open graph explorer
                </Button>
              </Link>
            </div>
          </Panel>
        )}

        {tab === "diffs" && (
          <Panel title="Diffs">
            <p className="text-sm text-text-muted">No diffs yet. Compare two versions to start.</p>
          </Panel>
        )}
      </div>
    </AppShell>
  );
}
