import Link from "next/link";
import { notFound } from "next/navigation";
import {
  reviewFiscalizacion,
  sendFiscalizacion,
  submitFiscalizacion,
} from "@/app/actions/flujos";
import {
  btnDanger,
  btnPrimary,
  btnSecondary,
  Field,
  inputClass,
  PageHeader,
  Panel,
} from "@/components/ui";
import {
  canRequestFiscalizacion,
  canReviewFiscalizacion,
  canViewFiscalizacion,
} from "@/lib/acl";
import { prisma } from "@/lib/prisma";
import { getSession } from "@/lib/session";

export const instant = false;

type Params = { params: Promise<{ id: string }> };

export default async function FiscalizacionDetailPage({ params }: Params) {
  const session = (await getSession())!;
  const { id } = await params;

  const solicitud = await prisma.fiscalizacionSolicitud.findUnique({
    where: { id },
    include: {
      solicitadoPor: true,
      revisadoPor: true,
      departamento: true,
      sucursal: true,
      documentos: {
        include: {
          documento: { include: { seccion: true, departamento: true } },
        },
      },
      auditoria: {
        include: { actor: true },
        orderBy: { createdAt: "desc" },
      },
    },
  });

  if (!solicitud || !canViewFiscalizacion(session, solicitud)) notFound();

  const canSubmit =
    canRequestFiscalizacion(session) &&
    (solicitud.estado === "BORRADOR" || solicitud.estado === "RECHAZADA");
  const canReview =
    canReviewFiscalizacion(session) && solicitud.estado === "PENDIENTE";
  const canSend =
    canReviewFiscalizacion(session) && solicitud.estado === "APROBADA";

  return (
    <div className="mx-auto max-w-3xl space-y-6">
      <PageHeader
        title={solicitud.titulo}
        description={`${solicitud.tipo} · ${solicitud.estado}${solicitud.sucursal ? ` · ${solicitud.sucursal.nombre}` : ""}`}
        actions={
          <Link href="/flujos/fiscalizacion" className={btnSecondary}>
            Listado
          </Link>
        }
      />

      <Panel className="space-y-3 p-5">
        <p className="text-sm text-[var(--muted)]">
          Solicitado por {solicitud.solicitadoPor.nombre}
          {solicitud.departamento ? ` · ${solicitud.departamento.nombre}` : ""}
        </p>
        {solicitud.motivo ? (
          <p className="text-sm text-[var(--ink)]">{solicitud.motivo}</p>
        ) : null}
        {solicitud.comentarioRevision ? (
          <p className="rounded-[var(--radius-control)] bg-[var(--accent-soft)] px-3 py-2 text-sm">
            Revisión: {solicitud.comentarioRevision}
            {solicitud.revisadoPor
              ? ` — ${solicitud.revisadoPor.nombre}`
              : ""}
          </p>
        ) : null}

        <h3 className="pt-2 font-semibold text-[var(--ink)]">Documentos</h3>
        <ul className="divide-y divide-[var(--border)]">
          {solicitud.documentos.map((row) => (
            <li key={row.id} className="py-2 text-sm">
              <span className="font-medium text-[var(--ink)]">
                {row.documento.titulo}
              </span>
              <span className="block text-xs text-[var(--muted)]">
                {row.documento.departamento.nombre} · {row.documento.seccion.nombre}
              </span>
            </li>
          ))}
        </ul>

        <div className="flex flex-wrap gap-2 border-t border-[var(--border)] pt-4">
          {canSubmit ? (
            <form action={submitFiscalizacion}>
              <input type="hidden" name="id" value={solicitud.id} />
              <button type="submit" className={btnPrimary}>
                Enviar a autorización Master
              </button>
            </form>
          ) : null}
          {canSend ? (
            <form action={sendFiscalizacion}>
              <input type="hidden" name="id" value={solicitud.id} />
              <button type="submit" className={btnPrimary}>
                Registrar exportación / envío
              </button>
            </form>
          ) : null}
        </div>

        {canReview ? (
          <form action={reviewFiscalizacion} className="space-y-3 border-t border-[var(--border)] pt-4">
            <input type="hidden" name="id" value={solicitud.id} />
            <Field label="Comentario de revisión">
              <textarea className={inputClass} name="comentario" rows={2} />
            </Field>
            <div className="flex flex-wrap gap-2">
              <button
                type="submit"
                name="decision"
                value="APPROVE"
                className={btnPrimary}
              >
                Aprobar
              </button>
              <button
                type="submit"
                name="decision"
                value="DENY"
                className={btnDanger}
              >
                Rechazar
              </button>
            </div>
          </form>
        ) : null}

        {solicitud.estado === "PENDIENTE" && !canReview ? (
          <p className="text-sm text-amber-800">
            Esperando autorización del Administrador Master antes de cualquier
            exportación o envío.
          </p>
        ) : null}
      </Panel>

      <Panel className="p-5">
        <h2 className="font-semibold text-[var(--ink)]">Auditoría</h2>
        <ul className="mt-3 space-y-2">
          {solicitud.auditoria.map((a) => (
            <li key={a.id} className="text-sm">
              <span className="font-medium text-[var(--ink)]">{a.accion}</span>
              <span className="text-[var(--muted)]">
                {" "}
                · {a.actor.nombre} ·{" "}
                {a.createdAt.toLocaleString("es-CL", {
                  dateStyle: "short",
                  timeStyle: "short",
                })}
              </span>
              {a.detalle ? (
                <span className="block text-xs text-[var(--muted)]">
                  {a.detalle}
                </span>
              ) : null}
            </li>
          ))}
        </ul>
      </Panel>
    </div>
  );
}
