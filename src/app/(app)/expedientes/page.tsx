import { prisma } from "@/lib/prisma";
import { canViewExpediente } from "@/lib/acl";
import { isExternalRole, isMasterRole, roleLabel } from "@/lib/roles";
import { getSession } from "@/lib/session";
import { EmptyState, PageHeader, Panel } from "@/components/ui";

export const metadata = {
  title: "Expedientes",
};

export default async function ExpedientesPage() {
  const session = (await getSession())!;

  const expedientes = await prisma.expedienteStub.findMany({
    where: { empresaId: session.empresaId },
    include: {
      sucursal: true,
      autorizaciones: { include: { usuario: true } },
    },
    orderBy: { titulo: "asc" },
  });

  const visible = expedientes.filter((e) =>
    canViewExpediente(session, {
      empresaId: e.empresaId,
      sucursalId: e.sucursalId,
      autorizadoUsuarioIds: e.autorizaciones.map((a) => a.usuarioId),
    }),
  );

  return (
    <div className="space-y-6">
      <PageHeader
        title="Expedientes"
        description="Stubs para demostrar ACL (no es almacenamiento documental de Fase 2). Master ve todo; Jefe solo su sucursal; externos solo autorizados."
      />

      <Panel className="px-4 py-3 text-sm text-[var(--muted)]">
        Sesión actual: <strong className="text-[var(--ink)]">{roleLabel(session.rol)}</strong>
        {session.sucursalNombre ? ` · ${session.sucursalNombre}` : ""} ·{" "}
        {visible.length} de {expedientes.length} visibles
      </Panel>

      {visible.length === 0 ? (
        <EmptyState
          title="Sin expedientes autorizados"
          detail={
            isExternalRole(session.rol)
              ? "Este rol externo no tiene autorizaciones asignadas."
              : "No hay stubs en tu alcance."
          }
        />
      ) : (
        <ul className="divide-y divide-[var(--border)] rounded-lg border border-[var(--border)] bg-[var(--surface-elevated)]">
          {visible.map((e) => (
            <li key={e.id} className="px-5 py-4">
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <p className="font-medium text-[var(--ink)]">{e.titulo}</p>
                  <p className="mt-0.5 text-sm text-[var(--muted)]">
                    {e.tipo}
                    {e.sucursal
                      ? ` · ${e.sucursal.nombre}`
                      : " · Corporativo"}
                  </p>
                </div>
                {isMasterRole(session.rol) ? (
                  <div className="text-xs text-[var(--muted)]">
                    Autorizados:{" "}
                    {e.autorizaciones.length === 0
                      ? "ninguno"
                      : e.autorizaciones
                          .map((a) => a.usuario.nombre)
                          .join(", ")}
                  </div>
                ) : null}
              </div>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
