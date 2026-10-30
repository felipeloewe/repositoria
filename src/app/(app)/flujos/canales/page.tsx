import Link from "next/link";
import { redirect } from "next/navigation";
import { createCanalPaquete } from "@/app/actions/flujos";
import {
  btnPrimary,
  EmptyState,
  Field,
  inputClass,
  PageHeader,
  Panel,
} from "@/components/ui";
import { canBrowseFlujos, canManageCanalPaquetes } from "@/lib/acl";
import { isExternalRole, roleLabel } from "@/lib/roles";
import { prisma } from "@/lib/prisma";
import { getSession } from "@/lib/session";

export const instant = false;

export const metadata = { title: "Canales seguros" };

export default async function CanalesPage() {
  const session = (await getSession())!;
  if (!canBrowseFlujos(session)) redirect("/dashboard");

  const isMaster = canManageCanalPaquetes(session);
  const isExt = isExternalRole(session.rol);

  const [paquetes, externos, documentos] = await Promise.all([
    prisma.canalPaquete.findMany({
      where: {
        empresaId: session.empresaId,
        ...(isExt ? { destinatarioId: session.userId, estado: "ACTIVO" } : {}),
      },
      include: {
        destinatario: true,
        _count: { select: { documentos: true } },
      },
      orderBy: { createdAt: "desc" },
    }),
    isMaster
      ? prisma.membresia.findMany({
          where: {
            empresaId: session.empresaId,
            activa: true,
            rol: { in: ["CONTADOR", "ABOGADO", "PREVENCIONISTA"] },
          },
          include: { usuario: true },
          orderBy: { rol: "asc" },
        })
      : Promise.resolve([]),
    isMaster
      ? prisma.documento.findMany({
          where: { empresaId: session.empresaId },
          include: { departamento: true, seccion: true },
          orderBy: { createdAt: "desc" },
          take: 40,
        })
      : Promise.resolve([]),
  ]);

  return (
    <div className="mx-auto max-w-5xl space-y-6">
      <PageHeader
        title="Canales seguros"
        description={
          isExt
            ? "Paquetes documentales que la empresa compartió contigo."
            : "Comparte bundles autorizados con contador, abogado o prevencionista."
        }
        actions={
          <Link
            href="/flujos"
            className="rounded-[var(--radius-control)] border border-[var(--border)] bg-white px-3 py-2 text-sm"
          >
            Volver
          </Link>
        }
      />

      {isMaster ? (
        <Panel className="p-5">
          <h2 className="font-semibold text-[var(--ink)]">Nuevo paquete</h2>
          <form action={createCanalPaquete} className="mt-4 space-y-3">
            <Field label="Título">
              <input
                className={inputClass}
                name="titulo"
                required
                placeholder="Liquidaciones Q1 — Contador"
              />
            </Field>
            <Field label="Destinatario externo">
              <select className={inputClass} name="destinatarioId" required>
                <option value="" disabled>
                  Elegir…
                </option>
                {externos.map((m) => (
                  <option key={m.id} value={m.usuarioId}>
                    {m.usuario.nombre} · {roleLabel(m.rol)}
                  </option>
                ))}
              </select>
            </Field>
            <Field label="Descripción">
              <textarea className={inputClass} name="descripcion" rows={2} />
            </Field>
            <fieldset>
              <legend className="mb-2 text-sm font-medium text-[var(--ink)]">
                Documentos del paquete
              </legend>
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
            </fieldset>
            <button type="submit" className={btnPrimary}>
              Crear paquete seguro
            </button>
          </form>
        </Panel>
      ) : null}

      {paquetes.length === 0 ? (
        <EmptyState
          title="Sin paquetes"
          detail={
            isExt
              ? "Cuando Master comparta documentos, aparecerán aquí."
              : "Crea un paquete para un externo."
          }
        />
      ) : (
        <ul className="divide-y divide-[var(--border)] overflow-hidden rounded-[var(--radius-card)] border border-[var(--border)] bg-[var(--surface-elevated)] shadow-[var(--shadow-card)]">
          {paquetes.map((p) => (
            <li key={p.id}>
              <Link
                href={`/flujos/canales/${p.id}`}
                className="flex flex-col gap-1 px-5 py-4 transition hover:bg-[var(--surface)] sm:flex-row sm:items-center sm:justify-between"
              >
                <div>
                  <p className="font-medium text-[var(--ink)]">{p.titulo}</p>
                  <p className="text-sm text-[var(--muted)]">
                    {p.destinatario.nombre} · {p._count.documentos} docs
                  </p>
                </div>
                <span
                  className={`shrink-0 rounded-full px-3 py-1 text-xs font-semibold ${
                    p.estado === "ACTIVO"
                      ? "bg-emerald-100 text-emerald-800"
                      : "bg-rose-100 text-rose-800"
                  }`}
                >
                  {p.estado}
                </span>
              </Link>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
