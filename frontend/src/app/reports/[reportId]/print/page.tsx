import AppShell from "@/components/features/shell/AppShell";

export default function PrintReportPage() {
  return (
    <div className="min-h-screen bg-paper p-8 text-paper-ink print:bg-white">
      <div className="mx-auto max-w-[800px] space-y-6">
        <header className="border-b border-paper-border pb-4">
          <div className="text-micro font-medium text-paper-muted">BlastSpec — Impact Report</div>
          <h1 className="mt-1 text-2xl font-semibold">Impact report</h1>
          <p className="mt-1 text-sm text-paper-muted">
            billing 1.0.0 → 2.0.0 · 2 Nov 2026, 14:03 UTC
          </p>
        </header>

        <section>
          <h2 className="text-h3 font-semibold">Summary</h2>
          <div className="mt-2 grid grid-cols-4 gap-4 text-sm">
            <div>
              <div className="text-paper-muted">Affected services</div>
              <div className="text-lg font-semibold">5</div>
            </div>
            <div>
              <div className="text-paper-muted">Critical changes</div>
              <div className="text-lg font-semibold">3</div>
            </div>
            <div>
              <div className="text-paper-muted">Migration waves</div>
              <div className="text-lg font-semibold">3</div>
            </div>
            <div>
              <div className="text-paper-muted">Cycles</div>
              <div className="text-lg font-semibold">0</div>
            </div>
          </div>
        </section>

        <section className="print:break-inside-avoid">
          <h2 className="text-h3 font-semibold">Migration waves</h2>
          <div className="mt-2 space-y-3">
            {[
              { wave: 0, services: "billing (Payments)" },
              { wave: 1, services: "checkout (Storefront), notifications (Growth), legacy (Payments)" },
              { wave: 2, services: "analytics (Data)" },
            ].map((w) => (
              <div key={w.wave} className="rounded-panel border border-paper-border p-3 print:break-inside-avoid">
                <div className="text-micro font-medium text-paper-muted">WAVE {w.wave}</div>
                <div className="mt-1 text-sm">{w.services}</div>
              </div>
            ))}
          </div>
        </section>

        <footer className="border-t border-paper-border pt-4 text-xs text-paper-muted">
          <div>Graph version: sha256:abc123…</div>
          <div>Engine version: 1.0.0</div>
          <div>Page 1 of 1</div>
        </footer>
      </div>

      <style>{`
        @media print {
          body { background: white; }
          .print\\:break-inside-avoid { break-inside: avoid; }
        }
      `}</style>
    </div>
  );
}
