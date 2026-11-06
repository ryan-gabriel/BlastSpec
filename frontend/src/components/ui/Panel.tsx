import * as React from "react";
import { cn } from "@/lib/cn";

export function Panel({
  title,
  actions,
  children,
  className,
}: {
  title?: string;
  actions?: React.ReactNode;
  children: React.ReactNode;
  className?: string;
}) {
  return (
    <div className={cn("rounded-panel border border-border bg-surface", className)}>
      {title || actions ? (
        <div className="flex h-12 items-center justify-between border-b border-border px-5">
          {title ? <h3 className="text-h3 font-semibold">{title}</h3> : <span />}
          {actions ? <div className="flex items-center gap-2">{actions}</div> : null}
        </div>
      ) : null}
      <div className="p-5">{children}</div>
    </div>
  );
}
