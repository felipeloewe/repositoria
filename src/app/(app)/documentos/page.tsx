import Link from "next/link";
import { deleteDocumento } from "@/app/actions/documentos";
import { DocumentoUploadForm } from "@/components/documento-upload-form";
import {
  btnDanger,
  btnSecondary,
  EmptyState,
  PageHeader,
  Panel,
} from "@/components/ui";
import {
  canDeleteDocumento,
  canUploadDocumento,
  canViewDocumento,
} from "@/lib/acl";
import { prisma } from "@/lib/prisma";
import { isMasterRole } from "@/lib/roles";
import { getSession } from "@/lib/session";

export const instant = false;

export const metadata = {
  title: "Documentos",
};

function formatBytes(n: number): string {
  if (n < 1024) return `${n} B`;
  if (n < 1024 * 1024) return `${(n / 1024).toFixed(1)} KB`;
  return `${(n / (1024 * 1024)).toFixed(1)} MB`;
}

export default async function DocumentosPage({
  searchParams,
}: {
  searchParams: Promise<{
    departamento?: string;
    sucursal?: string;
    seccion?: string;
  }>;
}) {
  const session = (await getSession())!;
  const filters = await searchParams;

  const [secciones, departamentos, sucursales, documentos] = await Promise.all([
    prisma.documentoSeccion.findMany({
      where: { empresaId: session.empresaId },
      include: { departamento: true, sucursal: true },
      orderBy: [
        { departamento: { nombre: "asc" } },
        { sucursalId: "asc" },
        { nombre: "asc" },
      ],
    }),
    prisma.departamento.findMany({
      where: { empresaId: session.empresaId },
      orderBy: { nombre: "asc" },
    }),
    prisma.sucursal.findMany({
      where: { empresaId: session.empresaId, activa: true },
      include: { departamento: true },
      orderBy: { nombre: "asc" },
    }),
    prisma.documento.findMany({
      where: { empresaId: session.empresaId },
      include: {
        departamento: true,
        seccion: true,
        sucursal: true,
        subidoPor: true,
        autorizaciones: true,
      },
      orderBy: { createdAt: "desc" },
    }),
  ]);

  let visible = documentos.filter((d) =>
    canViewDocumento(session, {
      empresaId: d.empresaId,
      departamentoId: d.departamentoId,
      sucursalId: d.sucursalId,
      autorizadoUsuarioIds: d.autorizaciones.map((a) => a.usuarioId),
    }),
  );

  if (filters.departamento) {
    visible = visible.filter((d) => d.departamentoId === filters.departamento);
  }
  if (filters.sucursal === "depto") {
    visible = visible.filter((d) => d.sucursalId === null);
  } else if (filters.sucursal) {
    visible = visible.filter((d) => d.sucursalId === filters.sucursal);
  }
  if (filters.seccion) {
    visible = visible.filter((d) => d.seccion.codigo === filters.seccion);
  }

  const uploadSecciones = secciones
    .filter((s) =>
      canUploadDocumento(session, {
        sucursalId: s.sucursalId,
        departamentoId: s.departamentoId,
      }),
    )
    .map((s) => ({
      id: s.id,
      label: s.sucursal
        ? `${s.departamento.nombre} · ${s.sucursal.nombre} · ${s.nombre}`
        : `${s.departamento.nombre} · Nivel depto · ${s.nombre}`,
    }));

  const seccionCodigos = [...new Set(secciones.map((s) => s.codigo))];

  return (
    <div className="mx-auto max-w-6xl space-y-6">
      <PageHeader
        title="Documentos"
        description="Repositorio por empresa ligado a departamentos; alcance opcional por sucursal y ACL por rol."
      />

      <Panel className="rounded-2xl p-4 shadow-sm">
        <form className="flex flex-wrap items-end gap-3" method="get">
          <label className="space-y-1 text-sm">
            <span className="text-[var(--muted)]">Departamento</span>
            <select
              name="departamento"
              defaultValue={filters.departamento ?? ""}
              className="block rounded border border-[var(--border)] bg-white px-3 py-2 text-sm"
            >
              <option value="">Todos</option>
              {departamentos.map((d) => (
                <option key={d.id} value={d.id}>
                  {d.nombre}
                </option>
              ))}
            </select>
          </label>
          <label className="space-y-1 text-sm">
            <span className="text-[var(--muted)]">Ámbito</span>
            <select
              name="sucursal"
              defaultValue={filters.sucursal ?? ""}
              className="block rounded border border-[var(--border)] bg-white px-3 py-2 text-sm"
            >
              <option value="">Todos visibles</option>
              <option value="depto">Nivel departamento</option>
              {sucursales.map((s) => (
                <option key={s.id} value={s.id}>
                  {s.departamento.nombre} · {s.nombre}
                </option>
              ))}
            </select>
          </label>
          <label className="space-y-1 text-sm">
            <span className="text-[var(--muted)]">Sección</span>
            <select
              name="seccion"
              defaultValue={filters.seccion ?? ""}
              className="block rounded border border-[var(--border)] bg-white px-3 py-2 text-sm"
            >
              <option value="">Todas</option>
              {seccionCodigos.map((c) => (
                <option key={c} value={c}>
                  {c}
                </option>
              ))}
            </select>
          </label>
          <button type="submit" className={btnSecondary}>
            Filtrar
          </button>
          {(filters.departamento || filters.sucursal || filters.seccion) && (
            <Link href="/documentos" className="text-sm text-[var(--accent)]">
              Limpiar
            </Link>
          )}
        </form>
      </Panel>

      {uploadSecciones.length > 0 ? (
        <Panel className="rounded-2xl p-5 shadow-sm">
          <h2 className="font-medium text-[var(--ink)]">Subir</h2>
          <div className="mt-3 max-w-lg">
            <DocumentoUploadForm secciones={uploadSecciones} />
          </div>
        </Panel>
      ) : (
        <Panel className="rounded-2xl p-4 text-sm text-[var(--muted)] shadow-sm">
          Tu perfil no puede subir documentos
          {isMasterRole(session.rol) ? "" : " (solo lectura / autorizados)"}.
        </Panel>
      )}

      {visible.length === 0 ? (
        <EmptyState
          title="Sin documentos visibles"
          detail="No hay archivos en tu alcance con los filtros actuales."
        />
      ) : (
        <ul className="divide-y divide-[var(--border)] overflow-hidden rounded-2xl border border-[var(--border)] bg-[var(--surface-elevated)] shadow-sm">
          {visible.map((d) => {
            const canDelete = canDeleteDocumento(session, {
              empresaId: d.empresaId,
              departamentoId: d.departamentoId,
              sucursalId: d.sucursalId,
              autorizadoUsuarioIds: d.autorizaciones.map((a) => a.usuarioId),
            });
            return (
              <li
                key={d.id}
                className="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
              >
                <div className="min-w-0">
                  <p className="font-medium text-[var(--ink)]">{d.titulo}</p>
                  <p className="mt-0.5 text-sm text-[var(--muted)]">
                    {d.departamento.nombre}
                    {d.sucursal
                      ? ` · ${d.sucursal.nombre}`
                      : " · Nivel depto"}{" "}
                    · {d.seccion.nombre} · {d.nombreArchivo} ·{" "}
                    {formatBytes(d.tamanioBytes)} · {d.subidoPor.nombre}
                  </p>
                </div>
                <div className="flex shrink-0 gap-2">
                  <a
                    href={`/api/documentos/${d.id}/download`}
                    className={btnSecondary}
                  >
                    Descargar
                  </a>
                  {canDelete ? (
                    <form action={deleteDocumento}>
                      <input type="hidden" name="id" value={d.id} />
                      <button type="submit" className={btnDanger}>
                        Eliminar
                      </button>
                    </form>
                  ) : null}
                </div>
              </li>
            );
          })}
        </ul>
      )}
    </div>
  );
}
