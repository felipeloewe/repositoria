import { redirect } from "next/navigation";
import {
  createDepartamento,
  deleteDepartamento,
  updateDepartamento,
} from "@/app/actions/organizacion";
import {
  canBrowseOrganization,
  canManageSucursal,
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

export const metadata = {
  title: "Departamentos",
};

export default async function DepartamentosPage() {
  const session = (await getSession())!;
  if (!canBrowseOrganization(session)) {
    redirect("/expedientes");
  }

  const sucursales = await prisma.sucursal.findMany({
    where: { empresaId: session.empresaId, activa: true },
    orderBy: { nombre: "asc" },
  });
  const visibleSucursalIds = filterVisibleSucursalIds(
    session,
    sucursales.map((s) => s.id),
  );
  const visibleSucursales = sucursales.filter((s) =>
    visibleSucursalIds.includes(s.id),
  );

  const departamentos = await prisma.departamento.findMany({
    where: {
      empresaId: session.empresaId,
      sucursalId: { in: visibleSucursalIds },
    },
    include: { sucursal: true },
    orderBy: [{ sucursal: { nombre: "asc" } }, { nombre: "asc" }],
  });

  const canCreateAnywhere = visibleSucursales.some((s) =>
    canManageSucursal(session, s.id),
  );

  return (
    <div className="space-y-6">
      <PageHeader
        title="Departamentos"
        description="Áreas bajo cada sucursal. Jefe de Sucursal solo administra la suya."
      />

      {canCreateAnywhere ? (
        <Panel className="p-5">
          <h2 className="font-medium text-[var(--ink)]">Nuevo departamento</h2>
          <form
            action={createDepartamento}
            className="mt-3 grid gap-3 sm:grid-cols-[200px_1fr_auto]"
          >
            <Field label="Sucursal">
              <select className={inputClass} name="sucursalId" required>
                {visibleSucursales
                  .filter((s) => canManageSucursal(session, s.id))
                  .map((s) => (
                    <option key={s.id} value={s.id}>
                      {s.nombre}
                    </option>
                  ))}
              </select>
            </Field>
            <Field label="Nombre">
              <input
                className={inputClass}
                name="nombre"
                placeholder="Administración"
                required
              />
            </Field>
            <div className="flex items-end">
              <button type="submit" className={btnPrimary}>
                Crear
              </button>
            </div>
          </form>
        </Panel>
      ) : null}

      {departamentos.length === 0 ? (
        <EmptyState title="Sin departamentos visibles" />
      ) : (
        <ul className="space-y-3">
          {departamentos.map((d) => {
            const canEdit = canManageSucursal(session, d.sucursalId);
            return (
              <li key={d.id}>
                <Panel className="p-4">
                  {canEdit ? (
                    <form
                      action={updateDepartamento}
                      className="flex flex-col gap-3 sm:flex-row sm:items-end"
                    >
                      <input type="hidden" name="id" value={d.id} />
                      <div className="flex-1">
                        <p className="mb-1 text-xs text-[var(--muted)]">
                          {d.sucursal.nombre}
                        </p>
                        <input
                          className={inputClass}
                          name="nombre"
                          defaultValue={d.nombre}
                          required
                        />
                      </div>
                      <button type="submit" className={btnSecondary}>
                        Guardar
                      </button>
                      <button
                        formAction={deleteDepartamento}
                        type="submit"
                        className={btnDanger}
                      >
                        Eliminar
                      </button>
                    </form>
                  ) : (
                    <div>
                      <p className="text-xs text-[var(--muted)]">
                        {d.sucursal.nombre}
                      </p>
                      <p className="font-medium text-[var(--ink)]">{d.nombre}</p>
                    </div>
                  )}
                </Panel>
              </li>
            );
          })}
        </ul>
      )}
    </div>
  );
}
