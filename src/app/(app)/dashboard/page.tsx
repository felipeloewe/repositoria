import Link from "next/link";
import { prisma } from "@/lib/prisma";
import {
  canBrowseOrganization,
  filterVisibleSucursalIds,
} from "@/lib/acl";
import { isExternalRole, isMasterRole, roleLabel } from "@/lib/roles";
import { getSession } from "@/lib/session";
import { EmptyState, PageHeader, Panel } from "@/components/ui";

export const metadata = {
  title: "Dashboard",
};

export default async function DashboardPage() {
  const session = (await getSession())!;

  const [sucursales, miembros, expedientes] = await Promise.all([
    prisma.sucursal.findMany({
      where: { empresaId: session.empresaId, activa: true },
      select: { id: true, nombre: true },
      orderBy: { nombre: "asc" },
    }),
    prisma.membresia.count({
      where: { empresaId: session.empresaId, activa: true },
    }),
    prisma.expedienteStub.findMany({
      where: { empresaId: session.empresaId },
      include: { autorizaciones: true, sucursal: true },
      orderBy: { titulo: "asc" },
    }),
  ]);

  const visibleSucursalIds = filterVisibleSucursalIds(
    session,
    sucursales.map((s) => s.id),
  );
  const visibleSucursales = sucursales.filter((s) =>
    visibleSucursalIds.includes(s.id),
  );

  const visibleExpedientes = expedientes.filter((e) => {
    if (isMasterRole(session.rol)) return true;
    if (session.rol === "JEFE_SUCURSAL") {
      return e.sucursalId === session.sucursalId;
    }
    if (isExternalRole(session.rol)) {
      return e.autorizaciones.some((a) => a.usuarioId === session.userId);
    }
    return false;
  });

  return (
    <div className="space-y-6">
      <PageHeader
        title="Dashboard"
        description={`Sesión de ${session.nombre} en ${session.empresaNombre}.`}
      />

      <dl className="grid gap-4 sm:grid-cols-3">
        <Panel className="p-4">
          <dt className="text-xs uppercase tracking-wide text-[var(--muted)]">
            Rol
          </dt>
          <dd className="mt-1 font-medium text-[var(--ink)]">
            {roleLabel(session.rol)}
          </dd>
        </Panel>
        <Panel className="p-4">
          <dt className="text-xs uppercase tracking-wide text-[var(--muted)]">
            Sucursales visibles
          </dt>
          <dd className="mt-1 font-medium text-[var(--ink)]">
            {canBrowseOrganization(session)
              ? visibleSucursales.length
              : "—"}
          </dd>
        </Panel>
        <Panel className="p-4">
          <dt className="text-xs uppercase tracking-wide text-[var(--muted)]">
            Expedientes visibles
          </dt>
          <dd className="mt-1 font-medium text-[var(--ink)]">
            {visibleExpedientes.length}
          </dd>
        </Panel>
      </dl>

      {canBrowseOrganization(session) ? (
        <Panel className="p-5">
          <div className="flex items-center justify-between gap-3">
            <h2 className="font-medium text-[var(--ink)]">Sucursales</h2>
            <Link
              href="/organizacion/sucursales"
              className="text-sm text-[var(--accent)] hover:underline"
            >
              Administrar
            </Link>
          </div>
          {visibleSucursales.length === 0 ? (
            <p className="mt-3 text-sm text-[var(--muted)]">Sin sucursales.</p>
          ) : (
            <ul className="mt-3 divide-y divide-[var(--border)]">
              {visibleSucursales.map((s) => (
                <li key={s.id} className="py-2 text-sm text-[var(--ink)]">
                  {s.nombre}
                </li>
              ))}
            </ul>
          )}
          {isMasterRole(session.rol) ? (
            <p className="mt-3 text-xs text-[var(--muted)]">
              {miembros} membresías activas en el tenant.
            </p>
          ) : null}
        </Panel>
      ) : (
        <EmptyState
          title="Vista externa acotada"
          detail="Como Contador, Abogado o Prevencionista solo ves expedientes autorizados. Cambia de usuario en /login para comparar ACL."
        />
      )}

      <Panel className="p-5">
        <div className="flex items-center justify-between gap-3">
          <h2 className="font-medium text-[var(--ink)]">
            Expedientes (stub ACL)
          </h2>
          <Link
            href="/expedientes"
            className="text-sm text-[var(--accent)] hover:underline"
          >
            Ver todos
          </Link>
        </div>
        {visibleExpedientes.length === 0 ? (
          <p className="mt-3 text-sm text-[var(--muted)]">
            Ningún expediente autorizado para este rol.
          </p>
        ) : (
          <ul className="mt-3 divide-y divide-[var(--border)]">
            {visibleExpedientes.slice(0, 5).map((e) => (
              <li key={e.id} className="flex justify-between gap-3 py-2 text-sm">
                <span className="text-[var(--ink)]">{e.titulo}</span>
                <span className="text-[var(--muted)]">
                  {e.tipo}
                  {e.sucursal ? ` · ${e.sucursal.nombre}` : " · Corporativo"}
                </span>
              </li>
            ))}
          </ul>
        )}
      </Panel>
    </div>
  );
}
