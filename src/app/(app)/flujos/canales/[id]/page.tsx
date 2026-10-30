import Link from "next/link";
import { notFound } from "next/navigation";
import { revokeCanalPaquete } from "@/app/actions/flujos";
import {
  btnDanger,
  btnSecondary,
  PageHeader,
  Panel,
} from "@/components/ui";
import { canManageCanalPaquetes, canViewCanalPaquete } from "@/lib/acl";
import { prisma } from "@/lib/prisma";
import { getSession } from "@/lib/session";

export const instant = false;

type Params = { params: Promise<{ id: string }> };

export default async function CanalPaqueteDetailPage({ params }: Params) {
  const session = (await getSession())!;
  const { id } = await params;

  const paquete = await prisma.canalPaquete.findUnique({
    where: { id },
    include: {
      destinatario: true,
      creadoPor: true,
      documentos: {
        include: {
          documento: { include: { seccion: true, departamento: true } },
        },
      },
      auditoria: {
        include: { actor: true },
        orderBy: { createdAt: "desc" },
        take: 20,
      },
    },
  });

  if (!paquete || !canViewCanalPaquete(session, paquete)) notFound();

  return (
    <div className="mx-auto max-w-3xl space-y-6">
      <PageHeader
        title={paquete.titulo}
        description={`Para ${paquete.destinatario.nombre} · ${paquete.estado}`}
        actions={
          <Link href="/flujos/canales" className={btnSecondary}>
            Listado
          </Link>
        }
      />

      <Panel className="space-y-3 p-5">
        {paquete.descripcion ? (
          <p className="text-sm text-[var(--ink)]">{paquete.descripcion}</p>
        ) : null}
        <p className="text-xs text-[var(--muted)]">
          Creado por {paquete.creadoPor.nombre}
        </p>

        <h3 className="font-semibold text-[var(--ink)]">Documentos</h3>
        <ul className="divide-y divide-[var(--border)]">
          {paquete.documentos.map((row) => (
            <li
              key={row.id}
              className="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between"
            >
              <div>
                <p className="font-medium text-[var(--ink)]">
                  {row.documento.titulo}
                </p>
                <p className="text-xs text-[var(--muted)]">
                  {row.documento.departamento.nombre} ·{" "}
                  {row.documento.seccion.nombre} · {row.documento.nombreArchivo}
                </p>
              </div>
              {paquete.estado === "ACTIVO" ? (
                <a
                  href={`/api/documentos/${row.documento.id}/download`}
                  className={btnSecondary}
                >
                  Descargar
                </a>
              ) : null}
            </li>
          ))}
        </ul>

        {canManageCanalPaquetes(session) && paquete.estado === "ACTIVO" ? (
          <form action={revokeCanalPaquete} className="border-t border-[var(--border)] pt-4">
            <input type="hidden" name="id" value={paquete.id} />
            <button type="submit" className={btnDanger}>
              Revocar acceso
            </button>
          </form>
        ) : null}
      </Panel>

      {canManageCanalPaquetes(session) ? (
        <Panel className="p-5">
          <h2 className="font-semibold text-[var(--ink)]">Auditoría</h2>
          <ul className="mt-3 space-y-2">
            {paquete.auditoria.map((a) => (
              <li key={a.id} className="text-sm">
                <span className="font-medium">{a.accion}</span>
                <span className="text-[var(--muted)]">
                  {" "}
                  · {a.actor.nombre} ·{" "}
                  {a.createdAt.toLocaleString("es-CL", {
                    dateStyle: "short",
                    timeStyle: "short",
                  })}
                </span>
              </li>
            ))}
          </ul>
        </Panel>
      ) : null}
    </div>
  );
}
