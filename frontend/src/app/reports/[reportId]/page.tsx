"use client";

import { useState } from "react";
import Link from "next/link";
import AppShell from "@/components/features/shell/AppShell";
import { Panel } from "@/components/ui/Panel";
import { Button } from "@/components/ui/Button";
import { SeverityChip } from "@/components/ui/SeverityChip";

export default function ImpactReportPage() {
  const [groupByWave, setGroupByWave] = useState(true);

  return (
    <AppShell breadcrumb="Reports / Impact report">
      <div className="space-y-6">
        <div className="flex items-start justify-between">
          <div>
            <h1 className="text-h1 font-semibold tracking-tight">Impact report</h1>
            <p className="mt-1 text-sm text-text-muted">
              billing 1.0.0 → 2.0.0 · Generated 2 Nov 2026, 14:03 UTC
            </p>
          </div>
          <div className="flex gap-2">
            <Button variant="secondary">Print</Button>
            <Button>Approve report</Button>
          </div>
        </div>

        {/* Summary band */}
        <section className="grid grid-cols-2 gap-3 sm:grid-cols-4">
          {[
            { label: "Affected services", value: "5" },
            { label: "Critical changes", value: "3" },
            { label: "Migration waves", value: "3" },
            { label: "Cycles", value: "0" },
          ].map((s) => (
            <div key={s.label} className="rounded-panel border border-border bg-surface px-5 py-4">
              <div className="text-xs text-text-muted">{s.label}</div>
              <div className="mt-1 text-2xl font-semibold tabular-nums">{s.value}</div>
            </div>
          ))}
        </section>

        {/* What changed */}
        <Panel title="What changed">
          <ul className="space-y-3">
            {[
              { rule: "BR-016", sev: "critical" as const, desc: "POST /users now requires the `email` request field." },
              { rule: "BR-001", sev: "critical" as const, desc: "Removed GET /users operation." },
              { rule: "BR-028", sev: "critical" as const, desc: "Removed field `email` from response 200." },
            ].map((c, i) => (
              <li key={i} className="flex items-start gap-3">
                <SeverityChip severity={c.sev} />
                <div>
                  <span className="font-mono text-xs text-text-faint">{c.rule}</span>
                  <p className="text-sm text-text">{c.desc}</p>
                </div>
              </li>
            ))}
          </ul>
        </Panel>

        {/* Who is affected */}
        <Panel
          title="Who is affected"
          actions={
            <button
              onClick={() => setGroupByWave(!groupByWave)}
              className="text-xs text-text-muted hover:text-text"
            >
              {groupByWave ? "Ungroup" : "Group by wave"}
            </button>
          }
        >
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-border text-left text-micro text-text-faint">
                <th className="pb-3 pr-4 font-medium">Service</th>
                <th className="pb-3 pr-4 font-medium">Owner team</th>
                <th className="pb-3 pr-4 font-medium">Relation</th>
                <th className="pb-3 pr-4 font-medium">Depth</th>
                <th className="pb-3 pr-4 font-medium">Reason</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td colSpan={5} className="py-8 text-center text-text-muted">
                  No affected services. Run an impact analysis first.
                </td>
              </tr>
            </tbody>
          </table>
        </Panel>

        {/* Migration plan */}
        <Panel title="Migration plan">
          <div className="space-y-4">
            {[
              { wave: 0, services: ["billing (Payments)"] },
              { wave: 1, services: ["checkout (Storefront)", "notifications (Growth)", "legacy (Payments)"] },
              { wave: 2, services: ["analytics (Data)"] },
            ].map((w) => (
              <div key={w.wave} className="rounded-panel border border-border bg-ink-900 p-4">
                <div className="mb-2 text-micro font-medium text-text-faint">WAVE {w.wave}</div>
                <div className="flex flex-wrap gap-2">
                  {w.services.map((s) => (
                    <span
                      key={s}
                      className="rounded-control border border-border bg-surface px-3 py-1.5 text-sm"
                    >
                      {s}
                    </span>
                  ))}
                </div>
              </div>
            ))}
          </div>
        </Panel>

        {/* Coverage note */}
        <div className="rounded-panel border border-border bg-surface p-4 text-sm text-text-muted">
          2 bindings were inferred. Confirm with the owning teams.
        </div>
      </div>
    </AppShell>
  );
}
