import Link from "next/link";
import { redirect } from "next/navigation";
import { createFiscalizacionSolicitud } from "@/app/actions/flujos";
import {
  btnPrimary,
  EmptyState,
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

export const metadata = { title: "Fiscalización" };

const ESTADO_LABEL: Record<string, string> = {
  BORRADOR: "Borrador",
  PENDIENTE: "Pendiente",
  APROBADA: "Aprobada",
  RECHAZADA: "Rechazada",
  ENVIADA: "Enviada",
};

export default async function FiscalizacionListPage() {
  const session = (await getSession())!;
  if (!canRequestFiscalizacion(session) && !canReviewFiscalizacion(session)) {
    redirect("/flujos/canales");
  }

  const [solicitudes, documentos, departamentos, sucursales] =
    await Promise.all([
      prisma.fiscalizacionSolicitud.findMany({
        where: { empresaId: session.empresaId },
        include: {
          solicitadoPor: true,
          _count: { select: { documentos: true } },
        },
        orderBy: { createdAt: "desc" },
      }),
      prisma.documento.findMany({
        where: {
          empresaId: session.empresaId,
          ...(session.rol === "JEFE_SUCURSAL" && session.sucursalId
            ? {
                OR: [
                  { sucursalId: session.sucursalId },
                  {
                    sucursalId: null,
                    departamentoId: session.departamentoId ?? undefined,
                  },
                ],
              }
            : {}),
        },
        include: { departamento: true, seccion: true },
        orderBy: { createdAt: "desc" },
        take: 40,
      }),
      prisma.departamento.findMany({
        where: { empresaId: session.empresaId },
        orderBy: { nombre: "asc" },
      }),
      prisma.sucursal.findMany({
        where: {
          empresaId: session.empresaId,
          activa: true,
          ...(session.rol === "JEFE_SUCURSAL" && session.sucursalId
            ? { id: session.sucursalId }
            : {}),
        },
        orderBy: { nombre: "asc" },
      }),
    ]);

  const visible = solicitudes.filter((s) => canViewFiscalizacion(session, s));

  return (
    <div className="mx-auto max-w-5xl space-y-6">
      <PageHeader
        title="Fiscalización"
        description="Antes de exportar o enviar liquidaciones/contratos, Master debe autorizar. Queda registro en auditoría."
        actions={
          <Link
            href="/flujos"
            className="rounded-[var(--radius-control)] border border-[var(--border)] bg-white px-3 py-2 text-sm"
          >
            Volver
          </Link>
        }
      />

      {canRequestFiscalizacion(session) ? (
        <Panel className="p-5">
          <h2 className="font-semibold text-[var(--ink)]">Nueva solicitud</h2>
          <form action={createFiscalizacionSolicitud} className="mt-4 space-y-3">
            <Field label="Título">
              <input
                className={inputClass}
                name="titulo"
                required
                placeholder="Envío liquidaciones marzo — Norte"
              />
            </Field>
            <div className="grid gap-3 sm:grid-cols-2">
              <Field label="Tipo">
                <select className={inputClass} name="tipo" required>
                  <option value="LIQUIDACION">Liquidación</option>
                  <option value="CONTRATO">Contrato</option>
                </select>
              </Field>
              {session.rol !== "JEFE_SUCURSAL" ? (
                <Field label="Sucursal (opc.)">
                  <select className={inputClass} name="sucursalId" defaultValue="">
                    <option value="">—</option>
                    {sucursales.map((s) => (
                      <option key={s.id} value={s.id}>
                        {s.nombre}
                      </option>
                    ))}
                  </select>
                </Field>
              ) : (
                <input type="hidden" name="sucursalId" value={session.sucursalId ?? ""} />
              )}
            </div>
            <Field label="Departamento (opc.)">
              <select className={inputClass} name="departamentoId" defaultValue="">
                <option value="">—</option>
                {departamentos.map((d) => (
                  <option key={d.id} value={d.id}>
                    {d.nombre}
                  </option>
                ))}
              </select>
            </Field>
            <Field label="Motivo">
              <textarea
                className={inputClass}
                name="motivo"
                rows={2}
                placeholder="Fiscalización DT / envío a contador externo…"
              />
            </Field>
            <fieldset>
              <legend className="mb-2 text-sm font-medium text-[var(--ink)]">
                Documentos a incluir
              </legend>
              {documentos.length === 0 ? (
                <p className="text-sm text-[var(--muted)]">
                  No hay documentos visibles para adjuntar.
                </p>
              ) : (
                <ul className="max-h-48 space-y-1 overflow-y-auto rounded-[var(--radius-control)] border border-[var(--border)] p-3">
                  {documentos.map((d) => (
                    <li key={d.id}>
                      <label className="flex cursor-pointer items-start gap-2 text-sm">
                        <input
                          type="checkbox"
                          name="documentoId"
                          value={d.id}
                          className="mt-1"
                        />
                        <span>
                          <span className="text-[var(--ink)]">{d.titulo}</span>
                          <span className="block text-xs text-[var(--muted)]">
                            {d.departamento.nombre} · {d.seccion.nombre}
                          </span>
                        </span>
                      </label>
                    </li>
                  ))}
                </ul>
              )}
            </fieldset>
            <div className="flex flex-wrap gap-2">
              <button type="submit" className={btnPrimary} name="submitNow" value="0">
                Guardar borrador
              </button>
              <button
                type="submit"
                className={btnPrimary}
                name="submitNow"
                value="1"
              >
                Enviar a autorización Master
              </button>
            </div>
          </form>
        </Panel>
      ) : null}

      {visible.length === 0 ? (
        <EmptyState title="Sin solicitudes" detail="Crea una para liquidaciones o contratos." />
      ) : (
        <ul className="divide-y divide-[var(--border)] overflow-hidden rounded-[var(--radius-card)] border border-[var(--border)] bg-[var(--surface-elevated)] shadow-[var(--shadow-card)]">
          {visible.map((s) => (
            <li key={s.id}>
              <Link
                href={`/flujos/fiscalizacion/${s.id}`}
                className="flex flex-col gap-1 px-5 py-4 transition hover:bg-[var(--surface)] sm:flex-row sm:items-center sm:justify-between"
              >
                <div>
                  <p className="font-medium text-[var(--ink)]">{s.titulo}</p>
                  <p className="text-sm text-[var(--muted)]">
                    {s.tipo} · {s._count.documentos} docs ·{" "}
                    {s.solicitadoPor.nombre}
                  </p>
                </div>
                <span
                  className={`shrink-0 rounded-full px-3 py-1 text-xs font-semibold ${
                    s.estado === "PENDIENTE"
                      ? "bg-amber-100 text-amber-800"
                      : s.estado === "APROBADA" || s.estado === "ENVIADA"
                        ? "bg-emerald-100 text-emerald-800"
                        : s.estado === "RECHAZADA"
                          ? "bg-rose-100 text-rose-800"
                          : "bg-[var(--surface-muted)] text-[var(--muted)]"
                  }`}
                >
                  {ESTADO_LABEL[s.estado] ?? s.estado}
                </span>
              </Link>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
