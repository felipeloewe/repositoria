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
        <h1 className="font-[family-name:var(--font-display)] text-3xl font-semibold tracking-tight text-[var(--ink)]">
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
      className={`rounded-[var(--radius-card)] border border-[var(--border)] bg-[var(--surface-elevated)] shadow-[var(--shadow-card)] ${className}`}
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
  "w-full rounded-[var(--radius-control)] border border-[var(--border)] bg-white px-3.5 py-2.5 text-sm outline-none ring-[var(--accent)] transition-shadow focus:ring-2";

export const btnPrimary =
  "inline-flex items-center justify-center rounded-[var(--radius-control)] bg-[var(--accent)] px-4 py-2.5 text-sm font-semibold text-white shadow-[0_8px_18px_rgba(26,92,255,0.28)] transition hover:bg-[var(--accent-strong)] disabled:opacity-50";

export const btnSecondary =
  "inline-flex items-center justify-center rounded-[var(--radius-control)] border border-[var(--border)] bg-white px-4 py-2.5 text-sm font-medium text-[var(--ink)] transition-colors hover:border-[var(--accent)] hover:text-[var(--accent)]";

export const btnDanger =
  "inline-flex items-center justify-center rounded-[var(--radius-control)] border border-rose-200 bg-white px-4 py-2.5 text-sm font-medium text-rose-700 transition-colors hover:bg-rose-50";

export function Flash({ children }: { children: ReactNode }) {
  return (
    <p className="rounded-[var(--radius-control)] border border-[var(--border)] bg-[var(--accent-soft)] px-3.5 py-2.5 text-sm text-[var(--ink)]">
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
    <div className="rounded-[var(--radius-card)] border border-dashed border-[var(--border)] bg-[var(--surface-elevated)] p-10 text-center shadow-[var(--shadow-card)]">
      <p className="font-semibold text-[var(--ink)]">{title}</p>
      {detail ? (
        <p className="mt-1 text-sm text-[var(--muted)]">{detail}</p>
      ) : null}
    </div>
  );
}
