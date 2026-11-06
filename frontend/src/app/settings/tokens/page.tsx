"use client";

import { useState } from "react";
import AppShell from "@/components/features/shell/AppShell";
import { Panel } from "@/components/ui/Panel";
import { Button } from "@/components/ui/Button";
import { Input } from "@/components/ui/Input";

export default function TokensPage() {
  const [showCreate, setShowCreate] = useState(false);
  const [tokenName, setTokenName] = useState("");
  const [scopes, setScopes] = useState<string[]>(["specs:write"]);
  const [revealedToken, setRevealedToken] = useState<string | null>(null);

  const allScopes = ["specs:write", "webhooks:write", "reports:read"];

  const toggleScope = (scope: string) => {
    setScopes((prev) =>
      prev.includes(scope) ? prev.filter((s) => s !== scope) : [...prev, scope],
    );
  };

  return (
    <AppShell breadcrumb="Settings / Tokens">
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-h1 font-semibold tracking-tight">CI tokens</h1>
            <p className="mt-1 text-sm text-text-muted">
              Create and revoke pipeline credentials.
            </p>
          </div>
          <Button onClick={() => setShowCreate(true)}>Create token</Button>
        </div>

        {showCreate && (
          <Panel title="Create token">
            <div className="space-y-4">
              <div className="space-y-1.5">
                <label className="block text-xs text-text-muted">Token name</label>
                <Input
                  value={tokenName}
                  onChange={(e) => setTokenName(e.target.value)}
                  placeholder="ci-pipeline"
                />
              </div>
              <div className="space-y-1.5">
                <label className="block text-xs text-text-muted">Scopes</label>
                <div className="space-y-2">
                  {allScopes.map((scope) => (
                    <label key={scope} className="flex items-center gap-2 text-sm">
                      <input
                        type="checkbox"
                        checked={scopes.includes(scope)}
                        onChange={() => toggleScope(scope)}
                        className="h-4 w-4 rounded-control border-border bg-ink-900 accent-accent"
                      />
                      <span className="font-mono text-xs">{scope}</span>
                    </label>
                  ))}
                </div>
              </div>
              <div className="flex justify-end gap-2">
                <Button variant="secondary" onClick={() => setShowCreate(false)}>
                  Cancel
                </Button>
                <Button
                  onClick={() => {
                    setRevealedToken("bs_9f2c1a4b_2f7c9e1d5a6b4c8e0f3a1d2c9b8e7f6a");
                    setShowCreate(false);
                  }}
                  disabled={!tokenName || scopes.length === 0}
                >
                  Create token
                </Button>
              </div>
            </div>
          </Panel>
        )}

        {revealedToken && (
          <div className="rounded-panel border border-warning bg-warning-bg p-5">
            <div className="mb-2 text-sm font-medium text-warning">
              This token will not be shown again.
            </div>
            <div className="flex items-center gap-2">
              <code className="flex-1 rounded-control border border-border bg-ink-900 px-3 py-2 font-mono text-xs text-text">
                {revealedToken}
              </code>
              <Button
                size="sm"
                variant="secondary"
                onClick={() => navigator.clipboard.writeText(revealedToken)}
              >
                Copy
              </Button>
            </div>
            <div className="mt-3">
              <Button size="sm" onClick={() => setRevealedToken(null)}>
                I stored it
              </Button>
            </div>
          </div>
        )}

        <Panel title="Tokens">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-border text-left text-micro text-text-faint">
                <th className="pb-3 pr-4 font-medium">Name</th>
                <th className="pb-3 pr-4 font-medium">Prefix</th>
                <th className="pb-3 pr-4 font-medium">Scopes</th>
                <th className="pb-3 pr-4 font-medium">Last used</th>
                <th className="pb-3 pr-4 font-medium">Status</th>
                <th className="pb-3 pr-4 font-medium">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td colSpan={6} className="py-8 text-center text-text-muted">
                  No CI tokens. Create one to publish specs from your pipeline.
                </td>
              </tr>
            </tbody>
          </table>
        </Panel>
      </div>
    </AppShell>
  );
}
