"use client";

import { useState } from "react";
import Link from "next/link";
import AppShell from "@/components/features/shell/AppShell";
import { Panel } from "@/components/ui/Panel";
import { Button } from "@/components/ui/Button";
import { Input } from "@/components/ui/Input";

type Step = 1 | 2 | 3;

export default function UploadSpecPage() {
  const [step, setStep] = useState<Step>(1);
  const [serviceName, setServiceName] = useState("");
  const [ownerTeam, setOwnerTeam] = useState("");
  const [version, setVersion] = useState("1.0.0");
  const [fileName, setFileName] = useState("");

  return (
    <AppShell breadcrumb="Specs / Upload">
      <div className="mx-auto max-w-[720px] space-y-6">
        <div>
          <h1 className="text-h1 font-semibold tracking-tight">Upload spec</h1>
          <p className="mt-1 text-sm text-text-muted">
            Step {step} of 3 — {step === 1 ? "Service" : step === 2 ? "Document" : "Review"}
          </p>
        </div>

        <div className="flex gap-2">
          {[1, 2, 3].map((s) => (
            <div
              key={s}
              className={`h-1 flex-1 rounded-full ${s <= step ? "bg-accent" : "bg-border"}`}
            />
          ))}
        </div>

        {step === 1 && (
          <Panel title="Service">
            <div className="space-y-4">
              <div className="space-y-1.5">
                <label className="block text-xs text-text-muted">Service name</label>
                <Input
                  value={serviceName}
                  onChange={(e) => setServiceName(e.target.value)}
                  placeholder="billing"
                />
              </div>
              <div className="space-y-1.5">
                <label className="block text-xs text-text-muted">Owner team</label>
                <Input
                  value={ownerTeam}
                  onChange={(e) => setOwnerTeam(e.target.value)}
                  placeholder="Payments"
                />
                <p className="text-xs text-text-faint">
                  Owner team is required. Impact reports use it to tell you who to notify.
                </p>
              </div>
              <div className="flex justify-end">
                <Button onClick={() => setStep(2)} disabled={!serviceName || !ownerTeam}>
                  Continue
                </Button>
              </div>
            </div>
          </Panel>
        )}

        {step === 2 && (
          <Panel title="Document">
            <div className="space-y-4">
              <div className="rounded-panel border border-dashed border-border-strong bg-ink-900 p-8 text-center">
                <p className="text-sm text-text-muted">
                  Drop an OpenAPI file or browse. JSON or YAML, up to 5 MB.
                </p>
                <input
                  type="file"
                  accept=".json,.yaml,.yml"
                  className="mt-4 block w-full text-sm text-text-muted file:mr-4 file:rounded-control file:border-0 file:bg-accent file:px-4 file:py-2 file:text-sm file:font-medium file:text-accent-ink"
                  onChange={(e) => setFileName(e.target.files?.[0]?.name ?? "")}
                />
                {fileName && (
                  <p className="mt-2 text-xs text-text-faint">Selected: {fileName}</p>
                )}
              </div>
              <div className="space-y-1.5">
                <label className="block text-xs text-text-muted">Version label</label>
                <Input value={version} onChange={(e) => setVersion(e.target.value)} />
              </div>
              <div className="flex justify-between">
                <Button variant="secondary" onClick={() => setStep(1)}>
                  Back
                </Button>
                <Button onClick={() => setStep(3)} disabled={!fileName}>
                  Continue
                </Button>
              </div>
            </div>
          </Panel>
        )}

        {step === 3 && (
          <Panel title="Review">
            <div className="space-y-4">
              <dl className="space-y-2 text-sm">
                <div className="flex justify-between">
                  <dt className="text-text-muted">Service</dt>
                  <dd>{serviceName}</dd>
                </div>
                <div className="flex justify-between">
                  <dt className="text-text-muted">Owner team</dt>
                  <dd>{ownerTeam}</dd>
                </div>
                <div className="flex justify-between">
                  <dt className="text-text-muted">Version</dt>
                  <dd className="font-mono">{version}</dd>
                </div>
                <div className="flex justify-between">
                  <dt className="text-text-muted">File</dt>
                  <dd className="font-mono text-xs">{fileName}</dd>
                </div>
              </dl>
              <div className="flex justify-between">
                <Button variant="secondary" onClick={() => setStep(2)}>
                  Back
                </Button>
                <Button>Upload and build graph</Button>
              </div>
            </div>
          </Panel>
        )}

        <div className="text-xs text-text-faint">
          <Link href="/specs" className="text-info hover:underline">
            Back to specs
          </Link>
        </div>
      </div>
    </AppShell>
  );
}
