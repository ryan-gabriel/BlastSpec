import * as React from "react";
import { cn } from "@/lib/cn";

export interface InputProps extends React.InputHTMLAttributes<HTMLInputElement> {
  error?: boolean;
}

export const Input = React.forwardRef<HTMLInputElement, InputProps>(
  ({ className, error, ...props }, ref) => (
    <input
      ref={ref}
      className={cn(
        "h-9 w-full rounded-control border bg-ink-900 px-3 text-sm text-text placeholder:text-text-faint focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/20",
        error ? "border-critical" : "border-border",
        className,
      )}
      {...props}
    />
  ),
);
Input.displayName = "Input";
