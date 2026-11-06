import Link from "next/link";

const navItems = [
  { label: "Overview", href: "/" },
  { label: "Specs", href: "/specs" },
  { label: "Reports", href: "/reports" },
  { label: "Bindings", href: "/bindings" },
  { label: "Settings", href: "/settings/tokens" },
];

export default function AppShell({
  children,
  breadcrumb,
}: {
  children: React.ReactNode;
  breadcrumb?: string;
}) {
  return (
    <div className="flex h-screen bg-ink-950 text-text">
      <aside className="flex w-[232px] shrink-0 flex-col border-r border-border bg-ink-900">
        <div className="flex h-14 items-center border-b border-border px-5">
          <span className="text-base font-semibold tracking-tight">BlastSpec</span>
          <span className="ml-2 rounded-control bg-accent-soft px-1.5 py-0.5 text-[10px] font-medium text-accent">
            demo
          </span>
        </div>
        <nav className="flex-1 overflow-y-auto px-3 py-4">
          <ul className="space-y-1">
            {navItems.map((item) => (
              <li key={item.href}>
                <Link
                  href={item.href}
                  className="flex h-9 items-center rounded-control px-3 text-sm text-text-muted transition-colors hover:bg-surface-hover hover:text-text"
                >
                  {item.label}
                </Link>
              </li>
            ))}
          </ul>
        </nav>
        <div className="border-t border-border px-5 py-3 text-xs text-text-faint">
          Demo User · demo@blastspec.dev
        </div>
      </aside>

      <div className="flex min-w-0 flex-1 flex-col">
        <header className="flex h-14 shrink-0 items-center border-b border-border bg-ink-900 px-6">
          <nav aria-label="Breadcrumb" className="text-sm text-text-muted">
            {breadcrumb ?? "Overview"}
          </nav>
          <div className="ml-auto flex items-center gap-3">
            <span className="rounded-control bg-accent-soft px-2 py-1 text-xs font-medium text-accent">
              Demo workspace
            </span>
          </div>
        </header>
        <main className="min-w-0 flex-1 overflow-y-auto px-8 py-8">{children}</main>
      </div>
    </div>
  );
}
