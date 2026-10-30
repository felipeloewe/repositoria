import Link from "next/link";
import { changeEmpresa, logout } from "@/app/actions/auth";
import { isMasterRole, roleLabel } from "@/lib/roles";
import type { Session } from "@/lib/session";

type AppShellProps = {
  session: Session;
  multiEmpresa?: boolean;
  children: React.ReactNode;
};

export function AppShell({
  session,
  multiEmpresa = false,
  children,
}: AppShellProps) {
  const showOrg =
    isMasterRole(session.rol) || session.rol === "JEFE_SUCURSAL";

  const nav = [
    { href: "/dashboard", label: "Dashboard" },
    ...(showOrg
      ? [
          { href: "/organizacion", label: "Organización" },
          { href: "/organizacion/sucursales", label: "Sucursales" },
          { href: "/organizacion/departamentos", label: "Departamentos" },
          ...(isMasterRole(session.rol)
            ? [{ href: "/organizacion/miembros", label: "Miembros" }]
            : []),
        ]
      : []),
    { href: "/expedientes", label: "Expedientes" },
  ];

  return (
    <div className="flex min-h-full flex-1 flex-col bg-[var(--surface)]">
      <header className="border-b border-[var(--border)] bg-[var(--surface-elevated)]">
        <div className="mx-auto flex h-14 max-w-6xl items-center justify-between gap-6 px-4 sm:px-6">
          <div className="flex min-w-0 items-center gap-6">
            <Link
              href="/dashboard"
              className="shrink-0 font-[family-name:var(--font-display)] text-lg tracking-tight text-[var(--ink)]"
            >
              Repositoria
            </Link>
            <nav className="hidden items-center gap-1 md:flex">
              {nav.map((item) => (
                <Link
                  key={item.href}
                  href={item.href}
                  className="rounded px-2.5 py-1.5 text-sm text-[var(--muted)] transition-colors hover:bg-[var(--surface-muted)] hover:text-[var(--ink)]"
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
                {session.sucursalNombre ? ` · ${session.sucursalNombre}` : ""}
              </p>
            </div>
            {multiEmpresa ? (
              <form action={changeEmpresa}>
                <button
                  type="submit"
                  className="shrink-0 rounded border border-[var(--border)] px-3 py-1.5 text-[var(--muted)] transition-colors hover:border-[var(--ink)] hover:text-[var(--ink)]"
                >
                  Empresa
                </button>
              </form>
            ) : null}
            <form action={logout}>
              <button
                type="submit"
                className="shrink-0 rounded border border-[var(--border)] px-3 py-1.5 text-[var(--muted)] transition-colors hover:border-[var(--ink)] hover:text-[var(--ink)]"
              >
                Salir
              </button>
            </form>
          </div>
        </div>
      </header>
      <main className="mx-auto w-full max-w-6xl flex-1 px-4 py-8 sm:px-6">
        {children}
      </main>
    </div>
  );
}
