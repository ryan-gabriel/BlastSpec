"use client";

import { useState } from "react";
import Link from "next/link";
import AppShell from "@/components/features/shell/AppShell";
import { Panel } from "@/components/ui/Panel";
import { Button } from "@/components/ui/Button";
import { Input } from "@/components/ui/Input";

type SpecRow = {
  id: string;
  name: string;
  slug: string;
  service_id: string;
  current_version_id: string | null;
  created_at: string;
  updated_at: string;
};

export default function SpecsPage() {
  const [query, setQuery] = useState("");
  const [serviceFilter, setServiceFilter] = useState("");

  return (
    <AppShell breadcrumb="Specs">
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-h1 font-semibold tracking-tight">Specs</h1>
            <p className="mt-1 text-sm text-text-muted">OpenAPI documents registered for your services.</p>
          </div>
          <Link href="/specs/upload">
            <Button>Upload spec</Button>
          </Link>
        </div>

        <div className="flex flex-col gap-3 sm:flex-row">
          <Input
            placeholder="Search by name, service, or path"
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            className="sm:max-w-sm"
          />
          <Input
            placeholder="Filter by service"
            value={serviceFilter}
            onChange={(e) => setServiceFilter(e.target.value)}
            className="sm:max-w-xs"
          />
        </div>

        <Panel title="Specs">
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-border text-left text-micro text-text-faint">
                  <th className="pb-3 pr-4 font-medium">Spec</th>
                  <th className="pb-3 pr-4 font-medium">Service</th>
                  <th className="pb-3 pr-4 font-medium">Version</th>
                  <th className="pb-3 pr-4 font-medium">Endpoints</th>
                  <th className="pb-3 pr-4 font-medium">Updated</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td colSpan={5} className="py-12 text-center text-text-muted">
                    No specs yet. Upload an OpenAPI document to build your first graph.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </Panel>
      </div>
    </AppShell>
  );
}
