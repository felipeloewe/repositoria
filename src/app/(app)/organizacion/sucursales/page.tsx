import { redirect } from "next/navigation";
import {
  createSucursal,
  deleteSucursal,
  updateSucursal,
} from "@/app/actions/organizacion";
import {
  canBrowseOrganization,
  canManageOrganization,
  filterVisibleSucursalIds,
} from "@/lib/acl";
import { getSession } from "@/lib/session";
import { prisma } from "@/lib/prisma";
import {
  btnDanger,
  btnPrimary,
  btnSecondary,
  EmptyState,
  Field,
  inputClass,
  PageHeader,
  Panel,
} from "@/components/ui";

export const instant = false;

export const metadata = {
  title: "Sucursales",
};

export default async function SucursalesPage() {
  const session = (await getSession())!;
  if (!canBrowseOrganization(session)) {
    redirect("/expedientes");
  }

  const canWrite = canManageOrganization(session);
  const departamentos = await prisma.departamento.findMany({
    where: { empresaId: session.empresaId },
    orderBy: { nombre: "asc" },
  });

  const all = await prisma.sucursal.findMany({
    where: { empresaId: session.empresaId },
    include: { departamento: true },
    orderBy: [{ departamento: { nombre: "asc" } }, { nombre: "asc" }],
  });
  const visibleIds = filterVisibleSucursalIds(
    session,
    all.map((s) => s.id),
  );
  const sucursales = all.filter((s) => visibleIds.includes(s.id));

  return (
    <div className="space-y-6">
      <PageHeader
        title="Sucursales"
        description={
          canWrite
            ? "Sucursales bajo un departamento de la empresa."
            : "Solo puedes ver tu sucursal asignada (sin acceso cruzado)."
        }
      />

      {canWrite ? (
        <Panel className="p-5">
          <h2 className="font-medium text-[var(--ink)]">Nueva sucursal</h2>
          {departamentos.length === 0 ? (
            <p className="mt-2 text-sm text-[var(--muted)]">
              Crea un departamento antes de añadir sucursales.
            </p>
          ) : (
            <form
              action={createSucursal}
              className="mt-3 grid gap-3 sm:grid-cols-[1fr_1fr_120px_auto]"
            >
              <Field label="Departamento">
                <select className={inputClass} name="departamentoId" required>
                  {departamentos.map((d) => (
                    <option key={d.id} value={d.id}>
                      {d.nombre}
                    </option>
                  ))}
                </select>
              </Field>
              <Field label="Nombre">
                <input className={inputClass} name="nombre" required />
              </Field>
              <Field label="Código">
                <input className={inputClass} name="codigo" placeholder="NTE" />
              </Field>
              <div className="flex items-end">
                <button type="submit" className={btnPrimary}>
                  Crear
                </button>
              </div>
            </form>
          )}
        </Panel>
      ) : null}

      {sucursales.length === 0 ? (
        <EmptyState title="Sin sucursales visibles" />
      ) : (
        <ul className="space-y-3">
          {sucursales.map((s) => (
            <li key={s.id}>
              <Panel className="p-4">
                {canWrite ? (
                  <form
                    action={updateSucursal}
                    className="grid gap-3 sm:grid-cols-[1fr_1fr_100px_110px_auto]"
                  >
                    <input type="hidden" name="id" value={s.id} />
                    <Field label="Departamento">
                      <select
                        className={inputClass}
                        name="departamentoId"
                        defaultValue={s.departamentoId}
                        required
                      >
                        {departamentos.map((d) => (
                          <option key={d.id} value={d.id}>
                            {d.nombre}
                          </option>
                        ))}
                      </select>
                    </Field>
                    <Field label="Nombre">
                      <input
                        className={inputClass}
                        name="nombre"
                        defaultValue={s.nombre}
                        required
                      />
                    </Field>
                    <Field label="Código">
                      <input
                        className={inputClass}
                        name="codigo"
                        defaultValue={s.codigo ?? ""}
                      />
                    </Field>
                    <Field label="Estado">
                      <select
                        className={inputClass}
                        name="activa"
                        defaultValue={s.activa ? "true" : "false"}
                      >
                        <option value="true">Activa</option>
                        <option value="false">Inactiva</option>
                      </select>
                    </Field>
                    <div className="flex items-end gap-2">
                      <button type="submit" className={btnSecondary}>
                        Guardar
                      </button>
                    </div>
                  </form>
                ) : (
                  <div>
                    <p className="text-xs text-[var(--muted)]">
                      {s.departamento.nombre}
                    </p>
                    <p className="font-medium text-[var(--ink)]">{s.nombre}</p>
                    <p className="text-sm text-[var(--muted)]">
                      {s.codigo ?? "Sin código"}
                    </p>
                  </div>
                )}
                {canWrite ? (
                  <div className="mt-3 flex items-center justify-between gap-3 text-sm text-[var(--muted)]">
                    <span>{s.departamento.nombre}</span>
                    <form action={deleteSucursal}>
                      <input type="hidden" name="id" value={s.id} />
                      <button type="submit" className={btnDanger}>
                        Eliminar
                      </button>
                    </form>
                  </div>
                ) : null}
              </Panel>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
