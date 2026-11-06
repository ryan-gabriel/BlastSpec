import Link from "next/link";

export default function LoginPage() {
  return (
    <main className="grid min-h-screen grid-cols-1 lg:grid-cols-[1.1fr_0.9fr] bg-ink-950">
      <section className="hidden lg:flex flex-col justify-between bg-ink-900 border-r border-border px-16 py-12">
        <div>
          <div className="text-2xl font-semibold tracking-tight text-text">BlastSpec</div>
          <p className="mt-3 max-w-md text-sm text-text-muted">
            See what breaks before you ship. Upload OpenAPI specs, build the service graph, and
            generate a migration-ready impact report.
          </p>
        </div>
        <div className="relative h-64 w-full overflow-hidden rounded-panel border border-border bg-ink-950">
          <svg
            className="absolute inset-0 h-full w-full opacity-60"
            viewBox="0 0 400 200"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
          >
            <g stroke="var(--color-border-strong)" strokeWidth="1">
              <path d="M40 60 H160 M40 140 H160 M240 60 H360 M240 140 H360" />
              <path d="M160 60 L240 60" stroke="var(--color-accent)" />
              <path d="M160 140 L240 140" stroke="var(--color-critical)" strokeDasharray="4 3" />
              <path d="M160 60 L240 140" stroke="var(--color-warning)" strokeDasharray="4 3" />
            </g>
            <g>
              <rect x="32" y="48" width="136" height="24" rx="4" fill="var(--color-surface-raised)" stroke="var(--color-border-strong)" />
              <rect x="32" y="128" width="136" height="24" rx="4" fill="var(--color-surface-raised)" stroke="var(--color-border-strong)" />
              <rect x="232" y="48" width="136" height="24" rx="4" fill="var(--color-surface-raised)" stroke="var(--color-border-strong)" />
              <rect x="232" y="128" width="136" height="24" rx="4" fill="var(--color-surface-raised)" stroke="var(--color-border-strong)" />
            </g>
          </svg>
        </div>
        <div className="text-xs text-text-faint">Demo credentials are pre-filled.</div>
      </section>

      <section className="flex flex-col items-center justify-center px-6 py-12">
        <div className="w-full max-w-[360px]">
          <h1 className="text-h1 font-semibold tracking-tight text-text">Sign in to BlastSpec</h1>
          <p className="mt-2 text-sm text-text-muted">
            Sign in with your demo workspace account.
          </p>

          <form className="mt-8 space-y-4" action="/api/v1/auth/login" method="post">
            <div className="space-y-1.5">
              <label htmlFor="email" className="block text-xs text-text-muted">
                Email
              </label>
              <input
                id="email"
                name="email"
                type="email"
                autoComplete="email"
                defaultValue="demo@blastspec.dev"
                required
                className="h-9 w-full rounded-control border border-border bg-ink-900 px-3 text-sm text-text placeholder:text-text-faint focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/20"
              />
            </div>
            <div className="space-y-1.5">
              <label htmlFor="password" className="block text-xs text-text-muted">
                Password
              </label>
              <input
                id="password"
                name="password"
                type="password"
                autoComplete="current-password"
                defaultValue="demo-password"
                required
                minLength={8}
                className="h-9 w-full rounded-control border border-border bg-ink-900 px-3 text-sm text-text placeholder:text-text-faint focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/20"
              />
            </div>
            <button
              type="submit"
              className="mt-2 h-9 w-full rounded-control bg-accent px-4 text-sm font-medium text-accent-ink hover:brightness-95 focus:outline-none focus:ring-2 focus:ring-accent focus:ring-offset-2 focus:ring-offset-ink-950"
            >
              Sign in
            </button>
          </form>

          <p className="mt-6 text-xs text-text-faint">
            New to BlastSpec?{" "}
            <Link href="/" className="text-info hover:underline">
              Learn more
            </Link>
          </p>
        </div>
      </section>
    </main>
  );
}
