import { redirect } from "next/navigation";
import {
  createMiembro,
  deleteMiembro,
  updateMiembro,
} from "@/app/actions/organizacion";
import { canManageMemberships } from "@/lib/acl";
import { ROLES, roleLabel } from "@/lib/roles";
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
  title: "Miembros",
};

export default async function MiembrosPage() {
  const session = (await getSession())!;
  if (!canManageMemberships(session)) {
    redirect("/organizacion");
  }

  const [membresias, sucursales] = await Promise.all([
    prisma.membresia.findMany({
      where: { empresaId: session.empresaId },
      include: { usuario: true, sucursal: true },
      orderBy: [{ rol: "asc" }, { createdAt: "asc" }],
    }),
    prisma.sucursal.findMany({
      where: { empresaId: session.empresaId, activa: true },
      orderBy: { nombre: "asc" },
    }),
  ]);

  return (
    <div className="space-y-6">
      <PageHeader
        title="Miembros y roles"
        description="Asigna Administrador Master, Jefe de Sucursal o roles externos acotados."
      />

      <Panel className="p-5">
        <h2 className="font-medium text-[var(--ink)]">Nueva membresía</h2>
        <form action={createMiembro} className="mt-3 grid gap-3 md:grid-cols-2">
          <Field label="Nombre">
            <input className={inputClass} name="nombre" required />
          </Field>
          <Field label="Correo">
            <input className={inputClass} name="email" type="email" required />
          </Field>
          <Field label="Rol">
            <select className={inputClass} name="rol" required defaultValue="JEFE_SUCURSAL">
              {ROLES.map((rol) => (
                <option key={rol} value={rol}>
                  {roleLabel(rol)}
                </option>
              ))}
            </select>
          </Field>
          <Field label="Sucursal (requerida para Jefe)">
            <select className={inputClass} name="sucursalId" defaultValue="">
              <option value="">— Sin sucursal —</option>
              {sucursales.map((s) => (
                <option key={s.id} value={s.id}>
                  {s.nombre}
                </option>
              ))}
            </select>
          </Field>
          <div className="md:col-span-2">
            <button type="submit" className={btnPrimary}>
              Asignar
            </button>
          </div>
        </form>
      </Panel>

      {membresias.length === 0 ? (
        <EmptyState title="Sin membresías" />
      ) : (
        <ul className="space-y-3">
          {membresias.map((m) => (
            <li key={m.id}>
              <Panel className="p-4">
                <div className="mb-3">
                  <p className="font-medium text-[var(--ink)]">
                    {m.usuario.nombre}
                  </p>
                  <p className="text-sm text-[var(--muted)]">{m.usuario.email}</p>
                </div>
                <form
                  action={updateMiembro}
                  className="grid gap-3 md:grid-cols-[1fr_1fr_140px_auto]"
                >
                  <input type="hidden" name="id" value={m.id} />
                  <Field label="Rol">
                    <select
                      className={inputClass}
                      name="rol"
                      defaultValue={m.rol}
                    >
                      {ROLES.map((rol) => (
                        <option key={rol} value={rol}>
                          {roleLabel(rol)}
                        </option>
                      ))}
                    </select>
                  </Field>
                  <Field label="Sucursal">
                    <select
                      className={inputClass}
                      name="sucursalId"
                      defaultValue={m.sucursalId ?? ""}
                    >
                      <option value="">— Sin sucursal —</option>
                      {sucursales.map((s) => (
                        <option key={s.id} value={s.id}>
                          {s.nombre}
                        </option>
                      ))}
                    </select>
                  </Field>
                  <Field label="Estado">
                    <select
                      className={inputClass}
                      name="activa"
                      defaultValue={m.activa ? "true" : "false"}
                    >
                      <option value="true">Activa</option>
                      <option value="false">Inactiva</option>
                    </select>
                  </Field>
                  <div className="flex items-end gap-2">
                    <button type="submit" className={btnSecondary}>
                      Guardar
                    </button>
                    {m.id !== session.membresiaId ? (
                      <button
                        formAction={deleteMiembro}
                        type="submit"
                        className={btnDanger}
                      >
                        Quitar
                      </button>
                    ) : null}
                  </div>
                </form>
              </Panel>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
