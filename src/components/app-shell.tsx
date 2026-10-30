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

  const correo: NavGroup = {
    title: "Correo",
    items: [
      { href: "/correo", label: "Hilos" },
      { href: "/correo/configuracion", label: "Vincular casilla" },
    ],
  };

  const flujos: NavGroup = {
    title: "Flujos",
    items: isMasterRole(session.rol) || session.rol === "JEFE_SUCURSAL"
      ? [
          { href: "/flujos", label: "Resumen" },
          { href: "/flujos/fiscalizacion", label: "Fiscalización" },
          { href: "/flujos/canales", label: "Canales seguros" },
        ]
      : [
          { href: "/flujos", label: "Resumen" },
          { href: "/flujos/canales", label: "Canales seguros" },
        ],
  };

  return [
    inicio,
    ...(organizacion ? [organizacion] : []),
    documentos,
    correo,
    flujos,
  ];
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
    <div className="flex min-h-full flex-1">
      <aside className="sticky top-0 hidden h-screen w-60 shrink-0 flex-col border-r border-[var(--border)] bg-[var(--surface-elevated)]/95 shadow-[4px_0_24px_rgba(18,54,181,0.04)] backdrop-blur md:flex">
        <div className="flex h-16 items-center px-4">
          <Link
            href="/dashboard"
            className="rounded-2xl bg-[linear-gradient(135deg,var(--banner),var(--banner-end))] px-3.5 py-2 font-[family-name:var(--font-display)] text-base font-semibold tracking-tight text-white shadow-[0_8px_18px_rgba(26,92,255,0.28)]"
          >
            Repositoria
          </Link>
        </div>
        <SidebarNav groups={groups} />
        <div className="px-4 py-4 text-[10px] font-medium uppercase tracking-[0.14em] text-[var(--muted)]">
          Fase 4 · flujos
        </div>
      </aside>

      <div className="flex min-w-0 flex-1 flex-col">
        <header className="sticky top-0 z-10 border-b border-[var(--border)] bg-[var(--surface-elevated)]/90 backdrop-blur">
          <div className="flex h-16 items-center gap-4 px-4 sm:px-6">
            <Link
              href="/dashboard"
              className="rounded-xl bg-[var(--accent)] px-2.5 py-1 font-[family-name:var(--font-display)] text-sm font-semibold text-white md:hidden"
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
                className="w-full rounded-full border border-[var(--border)] bg-[var(--surface)] px-4 py-2 text-sm text-[var(--muted)] outline-none"
                title="Búsqueda disponible en una fase posterior"
              />
            </div>

            <div className="ml-auto flex min-w-0 items-center gap-3">
              <div className="hidden min-w-0 text-right lg:block">
                <p className="truncate text-sm font-semibold text-[var(--ink)]">
                  {session.nombre}
                </p>
                <p className="truncate text-xs text-[var(--muted)]">
                  {roleLabel(session.rol)} · {session.empresaNombre}
                  {session.sucursalNombre ? ` · ${session.sucursalNombre}` : ""}
                </p>
              </div>
              <div
                className="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-[linear-gradient(135deg,var(--banner),var(--accent-glow))] text-sm font-semibold text-white shadow-[0_6px_14px_rgba(26,92,255,0.3)]"
                aria-hidden
              >
                {initials(session.nombre)}
              </div>
              {multiEmpresa ? (
                <form action={changeEmpresa}>
                  <button
                    type="submit"
                    className="rounded-full border border-[var(--border)] bg-white px-3 py-1.5 text-xs font-medium text-[var(--muted)] transition-colors hover:border-[var(--accent)] hover:text-[var(--accent)]"
                  >
                    Empresa
                  </button>
                </form>
              ) : null}
              <form action={logout}>
                <button
                  type="submit"
                  className="rounded-full border border-[var(--border)] bg-white px-3 py-1.5 text-xs font-medium text-[var(--muted)] transition-colors hover:border-[var(--accent)] hover:text-[var(--accent)]"
                >
                  Salir
                </button>
              </form>
            </div>
          </div>

          <div className="flex gap-1.5 overflow-x-auto px-3 py-2 md:hidden">
            {groups.flatMap((g) => g.items).map((item) => (
              <Link
                key={item.href}
                href={item.href}
                className="shrink-0 rounded-full border border-[var(--border)] bg-white px-3 py-1 text-xs font-medium text-[var(--muted)]"
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
