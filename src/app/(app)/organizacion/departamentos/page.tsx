import { redirect } from "next/navigation";
import {
  createActividad,
  createDepartamento,
  createTemaInteres,
  deleteActividad,
  deleteDepartamento,
  deleteTemaInteres,
  updateDepartamento,
} from "@/app/actions/organizacion";
import {
  canBrowseOrganization,
  canManageDepartamento,
  canManageDepartamentoTags,
  canViewDepartamento,
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
  title: "Departamentos",
};

export default async function DepartamentosPage() {
  const session = (await getSession())!;
  if (!canBrowseOrganization(session)) {
    redirect("/expedientes");
  }

  const canWriteDept = canManageDepartamento(session);

  const all = await prisma.departamento.findMany({
    where: { empresaId: session.empresaId },
    include: {
      actividades: { orderBy: { nombre: "asc" } },
      temasInteres: { orderBy: { nombre: "asc" } },
      _count: { select: { sucursales: true } },
    },
    orderBy: { nombre: "asc" },
  });

  const departamentos = all.filter((d) => canViewDepartamento(session, d.id));

  return (
    <div className="space-y-6">
      <PageHeader
        title="Departamentos"
        description="Nivel bajo la empresa. Cada uno tiene sucursales y tags independientes: Actividades y Temas de Interés."
      />

      {canWriteDept ? (
        <Panel className="p-5">
          <h2 className="font-medium text-[var(--ink)]">Nuevo departamento</h2>
          <form
            action={createDepartamento}
            className="mt-3 grid gap-3 sm:grid-cols-[1fr_auto]"
          >
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
        <ul className="space-y-4">
          {departamentos.map((d) => {
            const canTags = canManageDepartamentoTags(session, d.id);
            return (
              <li key={d.id}>
                <Panel className="space-y-4 p-5">
                  {canWriteDept ? (
                    <form
                      action={updateDepartamento}
                      className="flex flex-col gap-3 sm:flex-row sm:items-end"
                    >
                      <input type="hidden" name="id" value={d.id} />
                      <div className="flex-1">
                        <p className="mb-1 text-xs text-[var(--muted)]">
                          {d._count.sucursales} sucursales
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
                      <p className="font-medium text-[var(--ink)]">{d.nombre}</p>
                      <p className="text-sm text-[var(--muted)]">
                        {d._count.sucursales} sucursales
                      </p>
                    </div>
                  )}

                  <div className="grid gap-4 border-t border-[var(--border)] pt-4 lg:grid-cols-2">
                    <div>
                      <h3 className="text-sm font-medium text-[var(--ink)]">
                        Actividades
                      </h3>
                      <p className="mt-0.5 text-xs text-[var(--muted)]">
                        Tags del departamento (independientes de temas).
                      </p>
                      <ul className="mt-2 space-y-1">
                        {d.actividades.map((a) => (
                          <li
                            key={a.id}
                            className="flex items-center justify-between gap-2 text-sm"
                          >
                            <span>{a.nombre}</span>
                            {canTags ? (
                              <form action={deleteActividad}>
                                <input type="hidden" name="id" value={a.id} />
                                <button
                                  type="submit"
                                  className="text-xs text-red-600 hover:underline"
                                >
                                  Quitar
                                </button>
                              </form>
                            ) : null}
                          </li>
                        ))}
                        {d.actividades.length === 0 ? (
                          <li className="text-sm text-[var(--muted)]">
                            Sin actividades
                          </li>
                        ) : null}
                      </ul>
                      {canTags ? (
                        <form
                          action={createActividad}
                          className="mt-2 flex gap-2"
                        >
                          <input
                            type="hidden"
                            name="departamentoId"
                            value={d.id}
                          />
                          <input
                            className={inputClass}
                            name="nombre"
                            placeholder="Nueva actividad"
                            required
                          />
                          <button type="submit" className={btnSecondary}>
                            Añadir
                          </button>
                        </form>
                      ) : null}
                    </div>

                    <div>
                      <h3 className="text-sm font-medium text-[var(--ink)]">
                        Temas de Interés
                      </h3>
                      <p className="mt-0.5 text-xs text-[var(--muted)]">
                        Tags del departamento (independientes de actividades).
                      </p>
                      <ul className="mt-2 space-y-1">
                        {d.temasInteres.map((t) => (
                          <li
                            key={t.id}
                            className="flex items-center justify-between gap-2 text-sm"
                          >
                            <span>{t.nombre}</span>
                            {canTags ? (
                              <form action={deleteTemaInteres}>
                                <input type="hidden" name="id" value={t.id} />
                                <button
                                  type="submit"
                                  className="text-xs text-red-600 hover:underline"
                                >
                                  Quitar
                                </button>
                              </form>
                            ) : null}
                          </li>
                        ))}
                        {d.temasInteres.length === 0 ? (
                          <li className="text-sm text-[var(--muted)]">
                            Sin temas
                          </li>
                        ) : null}
                      </ul>
                      {canTags ? (
                        <form
                          action={createTemaInteres}
                          className="mt-2 flex gap-2"
                        >
                          <input
                            type="hidden"
                            name="departamentoId"
                            value={d.id}
                          />
                          <input
                            className={inputClass}
                            name="nombre"
                            placeholder="Nuevo tema"
                            required
                          />
                          <button type="submit" className={btnSecondary}>
                            Añadir
                          </button>
                        </form>
                      ) : null}
                    </div>
                  </div>
                </Panel>
              </li>
            );
          })}
        </ul>
      )}
    </div>
  );
}
