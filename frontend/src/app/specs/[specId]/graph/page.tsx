"use client";

import { useState } from "react";
import Link from "next/link";
import AppShell from "@/components/features/shell/AppShell";
import { Panel } from "@/components/ui/Panel";
import { Button } from "@/components/ui/Button";

export default function GraphExplorerPage() {
  const [showEndpoints, setShowEndpoints] = useState(false);
  const [showSchemas, setShowSchemas] = useState(false);
  const [listView, setListView] = useState(false);

  return (
    <AppShell breadcrumb="Specs / billing / Graph">
      <div className="flex h-[calc(100vh-7rem)] flex-col gap-4">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-h1 font-semibold tracking-tight">Graph explorer</h1>
            <p className="mt-1 text-sm text-text-muted">
              Service dependencies and consumer relationships.
            </p>
          </div>
          <div className="flex gap-2">
            <Button
              variant={showEndpoints ? "primary" : "secondary"}
              size="sm"
              onClick={() => setShowEndpoints(!showEndpoints)}
            >
              {showEndpoints ? "Hide" : "Show"} endpoints
            </Button>
            <Button
              variant={showSchemas ? "primary" : "secondary"}
              size="sm"
              onClick={() => setShowSchemas(!showSchemas)}
            >
              {showSchemas ? "Hide" : "Show"} schemas
            </Button>
            <Button
              variant={listView ? "primary" : "secondary"}
              size="sm"
              onClick={() => setListView(!listView)}
            >
              {listView ? "Canvas" : "List view"}
            </Button>
          </div>
        </div>

        <div className="flex flex-1 gap-4 overflow-hidden">
          <div className="relative flex-1 overflow-hidden rounded-panel border border-border bg-ink-950">
            {/* Graph canvas placeholder */}
            <div className="absolute inset-0 flex items-center justify-center">
              <div className="text-center">
                <div className="text-sm text-text-muted">Graph canvas</div>
                <p className="mt-1 text-xs text-text-faint">
                  React Flow + ELK layered layout renders here.
                </p>
              </div>
            </div>

            {/* Top-left controls */}
            <div className="absolute left-3 top-3 flex flex-col gap-1">
              <button className="flex h-8 w-8 items-center justify-center rounded-control border border-border bg-surface text-text-muted hover:text-text" aria-label="Zoom in">
                +
              </button>
              <button className="flex h-8 w-8 items-center justify-center rounded-control border border-border bg-surface text-text-muted hover:text-text" aria-label="Zoom out">
                −
              </button>
              <button className="flex h-8 w-8 items-center justify-center rounded-control border border-border bg-surface text-text-muted hover:text-text" aria-label="Fit view">
                ⊡
              </button>
            </div>

            {/* Top-right filters */}
            <div className="absolute right-3 top-3 flex gap-2">
              <input
                type="search"
                placeholder="Search nodes"
                className="h-8 w-48 rounded-control border border-border bg-surface px-3 text-sm text-text placeholder:text-text-faint focus:border-accent focus:outline-none"
              />
            </div>

            {/* Bottom-left legend */}
            <div className="absolute bottom-3 left-3 rounded-panel border border-border bg-surface px-3 py-2 text-xs">
              <div className="flex items-center gap-2">
                <span className="h-0.5 w-4 bg-text-muted" />
                <span className="text-text-muted">Declared</span>
              </div>
              <div className="mt-1 flex items-center gap-2">
                <span className="h-0.5 w-4 border-t border-dashed border-text-muted" />
                <span className="text-text-muted">Inferred</span>
              </div>
              <div className="mt-1 flex items-center gap-2">
                <span className="h-2 w-2 rounded-full bg-critical" />
                <span className="text-text-muted">Critical</span>
              </div>
            </div>
          </div>

          {/* Right drawer */}
          <div className="w-[320px] shrink-0 rounded-panel border border-border bg-surface p-5">
            <h3 className="text-h3 font-semibold">Node details</h3>
            <p className="mt-2 text-sm text-text-muted">
              Select a node to see its details, owner, endpoints, and consumers.
            </p>
          </div>
        </div>

        {listView && (
          <Panel title="Graph list (accessible)">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-border text-left text-micro text-text-faint">
                  <th className="pb-3 pr-4 font-medium">Service</th>
                  <th className="pb-3 pr-4 font-medium">Owner</th>
                  <th className="pb-3 pr-4 font-medium">Relation</th>
                  <th className="pb-3 pr-4 font-medium">Depth</th>
                  <th className="pb-3 pr-4 font-medium">Critical</th>
                  <th className="pb-3 pr-4 font-medium">Endpoints</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td colSpan={6} className="py-8 text-center text-text-muted">
                    No nodes to display. Build the graph first.
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
