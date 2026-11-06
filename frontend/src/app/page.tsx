import AppShell from "@/components/features/shell/AppShell";

export default function OverviewPage() {
  return (
    <AppShell breadcrumb="Overview">
      <div className="space-y-8">
        <div>
          <h1 className="text-h1 font-semibold tracking-tight">Overview</h1>
          <p className="mt-1 text-sm text-text-muted">Demo workspace · 3 services</p>
        </div>

        <section className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
          {[
            { label: "Specs", value: "3" },
            { label: "Services", value: "3" },
            { label: "Open impact reports", value: "1" },
            { label: "Critical changes (30d)", value: "2" },
          ].map((stat) => (
            <div
              key={stat.label}
              className="rounded-panel border border-border bg-surface px-5 py-4"
            >
              <div className="text-xs text-text-muted">{stat.label}</div>
              <div className="mt-1 text-2xl font-semibold tabular-nums">{stat.value}</div>
            </div>
          ))}
        </section>

        <section className="grid grid-cols-1 gap-6 lg:grid-cols-2">
          <div className="rounded-panel border border-border bg-surface">
            <div className="flex h-12 items-center border-b border-border px-5">
              <h2 className="text-h3 font-semibold">Recent reports</h2>
            </div>
            <div className="p-5 text-sm text-text-muted">
              No impact reports yet. Run a diff and select breaking changes.
            </div>
          </div>

          <div className="rounded-panel border border-border bg-surface">
            <div className="flex h-12 items-center border-b border-border px-5">
              <h2 className="text-h3 font-semibold">Workspace health</h2>
            </div>
            <ul className="space-y-2 p-5 text-sm">
              <li className="flex items-center justify-between">
                <span className="text-text-muted">Graph version</span>
                <span className="font-mono text-xs text-text-faint">—</span>
              </li>
              <li className="flex items-center justify-between">
                <span className="text-text-muted">Last sync</span>
                <span className="text-text-faint">never</span>
              </li>
              <li className="flex items-center justify-between">
                <span className="text-text-muted">Worker status</span>
                <span className="text-warning">Degraded</span>
              </li>
              <li className="flex items-center justify-between">
                <span className="text-text-muted">DLQ count</span>
                <span className="tabular-nums">0</span>
              </li>
            </ul>
          </div>
        </section>
      </div>
    </AppShell>
  );
}
