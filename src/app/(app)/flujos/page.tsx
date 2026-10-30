import Link from "next/link";
import { redirect } from "next/navigation";
import { EmptyState, PageHeader, Panel } from "@/components/ui";
import {
  canBrowseFlujos,
  canManageCanalPaquetes,
  canRequestFiscalizacion,
  canReviewFiscalizacion,
} from "@/lib/acl";
import { isExternalRole } from "@/lib/roles";
import { prisma } from "@/lib/prisma";
import { getSession } from "@/lib/session";

export const instant = false;

export const metadata = { title: "Flujos" };

export default async function FlujosPage() {
  const session = (await getSession())!;
  if (!canBrowseFlujos(session)) redirect("/dashboard");

  const [pendientes, misPaquetes, paquetesActivos] = await Promise.all([
    canReviewFiscalizacion(session) || canRequestFiscalizacion(session)
      ? prisma.fiscalizacionSolicitud.count({
          where: {
            empresaId: session.empresaId,
            estado: "PENDIENTE",
            ...(session.rol === "JEFE_SUCURSAL" && session.sucursalId
              ? { sucursalId: session.sucursalId }
              : {}),
          },
        })
      : Promise.resolve(0),
    isExternalRole(session.rol)
      ? prisma.canalPaquete.count({
          where: {
            empresaId: session.empresaId,
            destinatarioId: session.userId,
            estado: "ACTIVO",
          },
        })
      : Promise.resolve(0),
    canManageCanalPaquetes(session)
      ? prisma.canalPaquete.count({
          where: { empresaId: session.empresaId, estado: "ACTIVO" },
        })
      : Promise.resolve(0),
  ]);

  const cards = [
    !isExternalRole(session.rol)
      ? {
          href: "/flujos/fiscalizacion",
          title: "Fiscalización",
          detail:
            pendientes > 0
              ? `${pendientes} pendiente${pendientes === 1 ? "" : "s"} de autorización`
              : "Solicitar envío de liquidaciones/contratos (requiere OK Master)",
        }
      : null,
    {
      href: "/flujos/canales",
      title: "Canales seguros",
      detail: isExternalRole(session.rol)
        ? misPaquetes > 0
          ? `${misPaquetes} paquete${misPaquetes === 1 ? "" : "s"} compartido${misPaquetes === 1 ? "" : "s"} contigo`
          : "Sin paquetes activos"
        : canManageCanalPaquetes(session)
          ? `${paquetesActivos} paquete${paquetesActivos === 1 ? "" : "s"} activo${paquetesActivos === 1 ? "" : "s"} para externos`
          : "Paquetes documentales para contador / legal / prevención",
    },
  ].filter(Boolean) as Array<{ href: string; title: string; detail: string }>;

  return (
    <div className="mx-auto max-w-5xl space-y-6">
      <PageHeader
        title="Flujos"
        description="Fiscalización con autorización previa y canales seguros para terceros externos."
      />

      {cards.length === 0 ? (
        <EmptyState title="Sin flujos visibles" />
      ) : (
        <ul className="grid gap-4 sm:grid-cols-2">
          {cards.map((c) => (
            <li key={c.href}>
              <Link href={c.href}>
                <Panel className="h-full p-5 transition hover:border-[var(--accent)]">
                  <p className="font-semibold text-[var(--ink)]">{c.title}</p>
                  <p className="mt-1 text-sm text-[var(--muted)]">{c.detail}</p>
                </Panel>
              </Link>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
