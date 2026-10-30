import Link from "next/link";
import type { StubSession } from "@/lib/auth-stub";
import { roleLabel } from "@/lib/roles";

type AppShellProps = {
  session: StubSession;
  children: React.ReactNode;
};

const NAV = [
  { href: "/dashboard", label: "Dashboard" },
  { href: "/organizacion", label: "Organización" },
];

export function AppShell({ session, children }: AppShellProps) {
  return (
    <div className="flex min-h-full flex-1 flex-col bg-[var(--surface)]">
      <header className="border-b border-[var(--border)] bg-[var(--surface-elevated)]">
        <div className="mx-auto flex h-14 max-w-6xl items-center justify-between gap-6 px-4 sm:px-6">
          <div className="flex items-center gap-8">
            <Link
              href="/dashboard"
              className="font-[family-name:var(--font-display)] text-lg tracking-tight text-[var(--ink)]"
            >
              Repositoria
            </Link>
            <nav className="hidden items-center gap-1 sm:flex">
              {NAV.map((item) => (
                <Link
                  key={item.href}
                  href={item.href}
                  className="rounded px-3 py-1.5 text-sm text-[var(--muted)] transition-colors hover:bg-[var(--surface-muted)] hover:text-[var(--ink)]"
                >
                  {item.label}
                </Link>
              ))}
            </nav>
          </div>
          <div className="flex min-w-0 items-center gap-3 text-sm">
            <div className="hidden min-w-0 text-right sm:block">
              <p className="truncate font-medium text-[var(--ink)]">
                {session.empresaNombre}
              </p>
              <p className="truncate text-xs text-[var(--muted)]">
                {session.nombre} · {roleLabel(session.rol)}
              </p>
            </div>
            <Link
              href="/login"
              className="shrink-0 rounded border border-[var(--border)] px-3 py-1.5 text-[var(--muted)] transition-colors hover:border-[var(--ink)] hover:text-[var(--ink)]"
            >
              Salir
            </Link>
          </div>
        </div>
      </header>
      <main className="mx-auto w-full max-w-6xl flex-1 px-4 py-8 sm:px-6">
        {children}
      </main>
    </div>
  );
}
