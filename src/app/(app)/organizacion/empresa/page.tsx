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
        description="Datos del tenant. Solo Administrador Master puede editar."
      />
      <Panel className="p-5">
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
          <button type="submit" className={btnPrimary}>
            Guardar
          </button>
        </form>
      </Panel>
    </div>
  );
}
