"use client";

import { useState } from "react";
import AppShell from "@/components/features/shell/AppShell";
import { Panel } from "@/components/ui/Panel";
import { Button } from "@/components/ui/Button";
import { SeverityChip } from "@/components/ui/SeverityChip";

export default function WebhooksPage() {
  const [tab, setTab] = useState<"events" | "dlq">("events");

  return (
    <AppShell breadcrumb="Settings / Webhooks">
      <div className="space-y-6">
        <div>
          <h1 className="text-h1 font-semibold tracking-tight">Webhooks</h1>
          <p className="mt-1 text-sm text-text-muted">
            Verify CI integration and recover failed jobs.
          </p>
        </div>

        <div className="flex gap-1 border-b border-border">
          {(["events", "dlq"] as const).map((t) => (
            <button
              key={t}
              onClick={() => setTab(t)}
              className={`h-9 px-4 text-sm capitalize transition-colors ${
                tab === t
                  ? "border-b-2 border-accent text-text"
                  : "text-text-muted hover:text-text"
              }`}
            >
              {t === "events" ? "Events" : "Dead letter queue"}
            </button>
          ))}
        </div>

        {tab === "events" && (
          <Panel title="Webhook events">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-border text-left text-micro text-text-faint">
                  <th className="pb-3 pr-4 font-medium">Event</th>
                  <th className="pb-3 pr-4 font-medium">Type</th>
                  <th className="pb-3 pr-4 font-medium">Idempotency key</th>
                  <th className="pb-3 pr-4 font-medium">Signature</th>
                  <th className="pb-3 pr-4 font-medium">Status</th>
                  <th className="pb-3 pr-4 font-medium">Received</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td colSpan={6} className="py-8 text-center text-text-muted">
                    No webhook events yet. POST to /api/v1/webhooks/ci from your pipeline.
                  </td>
                </tr>
              </tbody>
            </table>
          </Panel>
        )}

        {tab === "dlq" && (
          <Panel title="Dead letter queue">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-border text-left text-micro text-text-faint">
                  <th className="pb-3 pr-4 font-medium">Job type</th>
                  <th className="pb-3 pr-4 font-medium">Error</th>
                  <th className="pb-3 pr-4 font-medium">Retries</th>
                  <th className="pb-3 pr-4 font-medium">Last attempt</th>
                  <th className="pb-3 pr-4 font-medium">Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td colSpan={5} className="py-8 text-center text-text-muted">
                    No failed jobs.
                  </td>
                </tr>
              </tbody>
            </table>
          </Panel>
        )}
      </div>
    </AppShell>
  );
}
