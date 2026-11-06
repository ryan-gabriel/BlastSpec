import * as React from "react";
import { cn } from "@/lib/cn";

type Severity = "critical" | "warning" | "info";

const styles: Record<Severity, string> = {
  critical: "bg-critical-bg text-critical",
  warning: "bg-warning-bg text-warning",
  info: "bg-info-bg text-info",
};

export function SeverityChip({ severity, className }: { severity: Severity; className?: string }) {
  const label = severity.charAt(0).toUpperCase() + severity.slice(1);
  return (
    <span
      className={cn(
        "inline-flex h-5 items-center gap-1.5 rounded-control px-2 text-micro font-medium",
        styles[severity],
        className,
      )}
    >
      <span aria-hidden="true" className="h-1.5 w-1.5 rounded-full bg-current" />
      {label}
    </span>
  );
}
