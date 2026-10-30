import type { ReactNode } from "react";

export function PageHeader({
  title,
  description,
  actions,
}: {
  title: string;
  description?: string;
  actions?: ReactNode;
}) {
  return (
    <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <h1 className="font-[family-name:var(--font-display)] text-3xl tracking-tight text-[var(--ink)]">
          {title}
        </h1>
        {description ? (
          <p className="mt-1 max-w-2xl text-[var(--muted)]">{description}</p>
        ) : null}
      </div>
      {actions ? <div className="flex flex-wrap gap-2">{actions}</div> : null}
    </div>
  );
}

export function Panel({
  children,
  className = "",
}: {
  children: ReactNode;
  className?: string;
}) {
  return (
    <div
      className={`rounded-lg border border-[var(--border)] bg-[var(--surface-elevated)] ${className}`}
    >
      {children}
    </div>
  );
}

export function Field({
  label,
  name,
  children,
}: {
  label: string;
  name?: string;
  children: ReactNode;
}) {
  return (
    <label className="block space-y-1.5">
      <span className="text-sm font-medium text-[var(--ink)]">{label}</span>
      {children}
      {name ? <span className="sr-only">{name}</span> : null}
    </label>
  );
}

export const inputClass =
  "w-full rounded border border-[var(--border)] bg-white px-3 py-2 text-sm outline-none ring-[var(--accent)] focus:ring-2";

export const btnPrimary =
  "inline-flex items-center justify-center rounded bg-[var(--accent)] px-3 py-2 text-sm font-medium text-white transition-opacity hover:opacity-90 disabled:opacity-50";

export const btnSecondary =
  "inline-flex items-center justify-center rounded border border-[var(--border)] px-3 py-2 text-sm text-[var(--ink)] transition-colors hover:bg-[var(--surface-muted)]";

export const btnDanger =
  "inline-flex items-center justify-center rounded border border-red-200 px-3 py-2 text-sm text-red-700 transition-colors hover:bg-red-50";

export function Flash({ children }: { children: ReactNode }) {
  return (
    <p className="rounded border border-[var(--border)] bg-[var(--accent-soft)] px-3 py-2 text-sm text-[var(--ink)]">
      {children}
    </p>
  );
}

export function EmptyState({
  title,
  detail,
}: {
  title: string;
  detail?: string;
}) {
  return (
    <div className="rounded-lg border border-dashed border-[var(--border)] bg-[var(--surface-elevated)] p-8 text-center">
      <p className="font-medium text-[var(--ink)]">{title}</p>
      {detail ? (
        <p className="mt-1 text-sm text-[var(--muted)]">{detail}</p>
      ) : null}
    </div>
  );
}
