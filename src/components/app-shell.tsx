import Link from "next/link";
import { changeEmpresa, logout } from "@/app/actions/auth";
import { SidebarNav, type NavGroup } from "@/components/sidebar-nav";
import { isMasterRole, roleLabel } from "@/lib/roles";
import type { Session } from "@/lib/session";

type AppShellProps = {
  session: Session;
  multiEmpresa?: boolean;
  children: React.ReactNode;
};

function buildNav(session: Session): NavGroup[] {
  const showOrg =
    isMasterRole(session.rol) || session.rol === "JEFE_SUCURSAL";

  const inicio: NavGroup = {
    title: "Inicio",
    items: [{ href: "/dashboard", label: "Dashboard" }],
  };

  const organizacion: NavGroup | null = showOrg
    ? {
        title: "Organización",
        items: [
          { href: "/organizacion", label: "Resumen" },
          { href: "/organizacion/departamentos", label: "Departamentos" },
          { href: "/organizacion/sucursales", label: "Sucursales" },
          ...(isMasterRole(session.rol)
            ? [
                { href: "/organizacion/empresa", label: "Empresa" },
                { href: "/organizacion/miembros", label: "Miembros" },
              ]
            : []),
        ],
      }
    : null;

  const documentos: NavGroup = {
    title: "Documentos",
    items: [
      { href: "/documentos", label: "Repositorio" },
      { href: "/expedientes", label: "Expedientes" },
    ],
  };

  return [inicio, ...(organizacion ? [organizacion] : []), documentos];
}

function initials(nombre: string): string {
  const parts = nombre.trim().split(/\s+/).filter(Boolean);
  if (parts.length === 0) return "?";
  if (parts.length === 1) return parts[0].slice(0, 1).toUpperCase();
  return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
}

export function AppShell({
  session,
  multiEmpresa = false,
  children,
}: AppShellProps) {
  const groups = buildNav(session);

  return (
    <div className="flex min-h-full flex-1 bg-[var(--surface)]">
      <aside className="sticky top-0 hidden h-screen w-56 shrink-0 flex-col border-r border-[var(--border)] bg-[var(--surface-elevated)] md:flex">
        <div className="flex h-14 items-center border-b border-[var(--border)] px-4">
          <Link
            href="/dashboard"
            className="font-[family-name:var(--font-questia)] text-lg tracking-tight text-[var(--ink)]"
          >
            Repositoria
          </Link>
        </div>
        <SidebarNav groups={groups} />
        <div className="border-t border-[var(--border)] px-4 py-3 text-[10px] text-[var(--muted)]">
          Fase 2 · documentos
        </div>
      </aside>

      <div className="flex min-w-0 flex-1 flex-col">
        <header className="sticky top-0 z-10 border-b border-[var(--border)] bg-[var(--surface-elevated)]/95 backdrop-blur">
          <div className="flex h-14 items-center gap-4 px-4 sm:px-6">
            <Link
              href="/dashboard"
              className="font-[family-name:var(--font-questia)] text-base text-[var(--ink)] md:hidden"
            >
              Repositoria
            </Link>

            <div className="mx-auto hidden w-full max-w-md flex-1 sm:block">
              <label className="sr-only" htmlFor="shell-search">
                Buscar
              </label>
              <input
                id="shell-search"
                type="search"
                placeholder="Buscar…"
                disabled
                className="w-full rounded-full border border-[var(--border)] bg-[var(--surface)] px-4 py-1.5 text-sm text-[var(--muted)] outline-none"
                title="Búsqueda disponible en una fase posterior"
              />
            </div>

            <div className="ml-auto flex min-w-0 items-center gap-3">
              <div className="hidden min-w-0 text-right lg:block">
                <p className="truncate text-sm font-medium text-[var(--ink)]">
                  {session.nombre}
                </p>
                <p className="truncate text-xs text-[var(--muted)]">
                  {roleLabel(session.rol)} · {session.empresaNombre}
                  {session.sucursalNombre ? ` · ${session.sucursalNombre}` : ""}
                </p>
              </div>
              <div
                className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[var(--accent)] text-sm font-medium text-white"
                aria-hidden
              >
                {initials(session.nombre)}
              </div>
              {multiEmpresa ? (
                <form action={changeEmpresa}>
                  <button
                    type="submit"
                    className="rounded-lg border border-[var(--border)] px-2.5 py-1.5 text-xs text-[var(--muted)] transition-colors hover:border-[var(--ink)] hover:text-[var(--ink)]"
                  >
                    Empresa
                  </button>
                </form>
              ) : null}
              <form action={logout}>
                <button
                  type="submit"
                  className="rounded-lg border border-[var(--border)] px-2.5 py-1.5 text-xs text-[var(--muted)] transition-colors hover:border-[var(--ink)] hover:text-[var(--ink)]"
                >
                  Salir
                </button>
              </form>
            </div>
          </div>

          {/* Mobile nav */}
          <div className="flex gap-1 overflow-x-auto border-t border-[var(--border)] px-3 py-2 md:hidden">
            {groups.flatMap((g) => g.items).map((item) => (
              <Link
                key={item.href}
                href={item.href}
                className="shrink-0 rounded-full border border-[var(--border)] px-3 py-1 text-xs text-[var(--muted)]"
              >
                {item.label}
              </Link>
            ))}
          </div>
        </header>

        <main className="flex-1 px-4 py-6 sm:px-6 lg:px-8">{children}</main>
      </div>
    </div>
  );
}
