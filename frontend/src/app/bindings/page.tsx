"use client";

import AppShell from "@/components/features/shell/AppShell";
import { Panel } from "@/components/ui/Panel";
import { Button } from "@/components/ui/Button";

export default function BindingsPage() {
  return (
    <AppShell breadcrumb="Bindings">
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-h1 font-semibold tracking-tight">Consumer bindings</h1>
            <p className="mt-1 text-sm text-text-muted">
              Correct gaps in the dependency graph by declaring who consumes what.
            </p>
          </div>
          <Button>Add binding</Button>
        </div>

        <Panel title="Bindings">
          <p className="text-sm text-text-muted">
            No bindings yet. Declare one or add <code className="font-mono text-xs">x-consumers</code>{" "}
            to a spec.
          </p>
        </Panel>
      </div>
    </AppShell>
  );
}
