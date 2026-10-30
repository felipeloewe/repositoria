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

export const metadata = {
  title: "Sucursales",
};

export default async function SucursalesPage() {
  const session = (await getSession())!;
  if (!canBrowseOrganization(session)) {
    redirect("/expedientes");
  }

  const canWrite = canManageOrganization(session);
  const all = await prisma.sucursal.findMany({
    where: { empresaId: session.empresaId },
    include: { _count: { select: { departamentos: true } } },
    orderBy: { nombre: "asc" },
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
            ? "Alta y edición de sucursales del tenant."
            : "Solo puedes ver tu sucursal asignada (sin acceso cruzado)."
        }
      />

      {canWrite ? (
        <Panel className="p-5">
          <h2 className="font-medium text-[var(--ink)]">Nueva sucursal</h2>
          <form
            action={createSucursal}
            className="mt-3 grid gap-3 sm:grid-cols-[1fr_160px_auto]"
          >
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
                    className="grid gap-3 sm:grid-cols-[1fr_120px_120px_auto]"
                  >
                    <input type="hidden" name="id" value={s.id} />
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
                    <p className="font-medium text-[var(--ink)]">{s.nombre}</p>
                    <p className="text-sm text-[var(--muted)]">
                      {s.codigo ?? "Sin código"} ·{" "}
                      {s._count.departamentos} departamentos
                    </p>
                  </div>
                )}
                <div className="mt-3 flex items-center justify-between gap-3 text-sm text-[var(--muted)]">
                  <span>{s._count.departamentos} departamentos</span>
                  {canWrite ? (
                    <form action={deleteSucursal}>
                      <input type="hidden" name="id" value={s.id} />
                      <button type="submit" className={btnDanger}>
                        Eliminar
                      </button>
                    </form>
                  ) : null}
                </div>
              </Panel>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
