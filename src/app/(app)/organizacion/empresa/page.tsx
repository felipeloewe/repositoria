import { redirect } from "next/navigation";
import { updateEmpresa } from "@/app/actions/organizacion";
import { canManageOrganization } from "@/lib/acl";
import { getSession } from "@/lib/session";
import { prisma } from "@/lib/prisma";
import {
  btnPrimary,
  Field,
  inputClass,
  PageHeader,
  Panel,
} from "@/components/ui";

export const metadata = {
  title: "Empresa",
};

export default async function EmpresaPage() {
  const session = (await getSession())!;
  if (!canManageOrganization(session)) {
    redirect("/organizacion");
  }

  const empresa = await prisma.empresa.findUniqueOrThrow({
    where: { id: session.empresaId },
  });

  return (
    <div className="space-y-6">
      <PageHeader
        title="Empresa"
        description="Identidad por ID estable. Nombre, slug y RUT son atributos complementarios."
      />
      <Panel className="p-5">
        <p className="mb-4 text-xs text-[var(--muted)]">
          ID: <code className="rounded bg-[var(--surface-muted)] px-1">{empresa.id}</code>
        </p>
        <form action={updateEmpresa} className="max-w-lg space-y-4">
          <Field label="Nombre">
            <input
              className={inputClass}
              name="nombre"
              defaultValue={empresa.nombre}
              required
            />
          </Field>
          <Field label="Slug">
            <input
              className={inputClass}
              name="slug"
              defaultValue={empresa.slug}
              required
            />
          </Field>
          <Field label="RUT (opcional)">
            <input
              className={inputClass}
              name="rut"
              defaultValue={empresa.rut ?? ""}
              placeholder="76.111.111-1"
            />
          </Field>
          <button type="submit" className={btnPrimary}>
            Guardar
          </button>
        </form>
      </Panel>
    </div>
  );
}
