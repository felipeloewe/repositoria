import Link from "next/link";
import { redirect } from "next/navigation";
import { canBrowseOrganization } from "@/lib/acl";
import { isMasterRole } from "@/lib/roles";
import { getSession } from "@/lib/session";
import { prisma } from "@/lib/prisma";
import { PageHeader, Panel } from "@/components/ui";

export const metadata = {
  title: "Organización",
};

export default async function OrganizacionPage() {
  const session = (await getSession())!;
  if (!canBrowseOrganization(session)) {
    redirect("/expedientes");
  }

  const [empresa, sucursales, departamentos, miembros] = await Promise.all([
    prisma.empresa.findUniqueOrThrow({ where: { id: session.empresaId } }),
    prisma.sucursal.count({ where: { empresaId: session.empresaId } }),
    prisma.departamento.count({
      where: {
        empresaId: session.empresaId,
        ...(session.rol === "JEFE_SUCURSAL" && session.sucursalId
          ? { sucursalId: session.sucursalId }
          : {}),
      },
    }),
    isMasterRole(session.rol)
      ? prisma.membresia.count({
          where: { empresaId: session.empresaId, activa: true },
        })
      : Promise.resolve(null),
  ]);

  const links = [
    {
      href: "/organizacion/empresa",
      title: "Empresa",
      detail: `${empresa.nombre} · ${empresa.slug}`,
      masterOnly: true,
    },
    {
      href: "/organizacion/sucursales",
      title: "Sucursales",
      detail: `${sucursales} registradas`,
      masterOnly: false,
    },
    {
      href: "/organizacion/departamentos",
      title: "Departamentos",
      detail: `${departamentos} visibles`,
      masterOnly: false,
    },
    {
      href: "/organizacion/miembros",
      title: "Miembros y roles",
      detail:
        miembros === null
          ? "Solo Master"
          : `${miembros} membresías activas`,
      masterOnly: true,
    },
  ].filter((l) => !l.masterOnly || isMasterRole(session.rol));

  return (
    <div className="space-y-6">
      <PageHeader
        title="Organización"
        description="Empresa → sucursales → departamentos, con membresías y ACL por rol."
      />
      <ul className="grid gap-3 sm:grid-cols-2">
        {links.map((link) => (
          <li key={link.href}>
            <Link href={link.href}>
              <Panel className="h-full p-5 transition-colors hover:border-[var(--accent)]">
                <p className="font-medium text-[var(--ink)]">{link.title}</p>
                <p className="mt-1 text-sm text-[var(--muted)]">{link.detail}</p>
              </Panel>
            </Link>
          </li>
        ))}
      </ul>
    </div>
  );
}
