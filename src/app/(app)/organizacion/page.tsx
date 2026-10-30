export const metadata = {
  title: "Organización",
};

const ENTITIES = [
  {
    name: "Empresa",
    detail: "Tenant raíz. Aísla usuarios, sucursales y documentos.",
  },
  {
    name: "Sucursal",
    detail: "Unidad dentro de la empresa. Alcance de Jefe de Sucursal.",
  },
  {
    name: "Departamento",
    detail: "Área bajo una sucursal (Administración, RR.HH., Prevención…).",
  },
  {
    name: "Usuario + Membresía",
    detail:
      "Identidad global con rol por empresa: Administrador Master, Jefe Sucursal, Contador, Abogado, Prevencionista.",
  },
] as const;

export default function OrganizacionPage() {
  return (
    <div className="space-y-6">
      <div>
        <h1 className="font-[family-name:var(--font-display)] text-3xl tracking-tight text-[var(--ink)]">
          Organización
        </h1>
        <p className="mt-1 text-[var(--muted)]">
          Fundamentos del modelo de datos (Prisma / MySQL). La UI de alta y
          edición llega en el siguiente incremento.
        </p>
      </div>

      <ul className="divide-y divide-[var(--border)] rounded-lg border border-[var(--border)] bg-[var(--surface-elevated)]">
        {ENTITIES.map((entity) => (
          <li key={entity.name} className="px-5 py-4">
            <p className="font-medium text-[var(--ink)]">{entity.name}</p>
            <p className="mt-0.5 text-sm text-[var(--muted)]">{entity.detail}</p>
          </li>
        ))}
      </ul>
    </div>
  );
}
