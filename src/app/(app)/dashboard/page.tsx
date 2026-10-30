import { getStubSession } from "@/lib/auth-stub";
import { roleLabel } from "@/lib/roles";

export const metadata = {
  title: "Dashboard",
};

export default function DashboardPage() {
  const session = getStubSession()!;

  return (
    <div className="space-y-6">
      <div>
        <h1 className="font-[family-name:var(--font-display)] text-3xl tracking-tight text-[var(--ink)]">
          Dashboard
        </h1>
        <p className="mt-1 text-[var(--muted)]">
          Vista inicial de {session.empresaNombre}. Documentos, correo y flujos
          se agregarán en fases posteriores.
        </p>
      </div>

      <dl className="grid gap-4 sm:grid-cols-3">
        <div className="rounded-lg border border-[var(--border)] bg-[var(--surface-elevated)] p-4">
          <dt className="text-xs uppercase tracking-wide text-[var(--muted)]">
            Empresa (tenant)
          </dt>
          <dd className="mt-1 font-medium text-[var(--ink)]">
            {session.empresaNombre}
          </dd>
        </div>
        <div className="rounded-lg border border-[var(--border)] bg-[var(--surface-elevated)] p-4">
          <dt className="text-xs uppercase tracking-wide text-[var(--muted)]">
            Rol
          </dt>
          <dd className="mt-1 font-medium text-[var(--ink)]">
            {roleLabel(session.rol)}
          </dd>
        </div>
        <div className="rounded-lg border border-[var(--border)] bg-[var(--surface-elevated)] p-4">
          <dt className="text-xs uppercase tracking-wide text-[var(--muted)]">
            Usuario
          </dt>
          <dd className="mt-1 font-medium text-[var(--ink)]">{session.email}</dd>
        </div>
      </dl>

      <section className="rounded-lg border border-dashed border-[var(--border)] bg-[var(--surface-elevated)] p-8 text-center">
        <p className="font-medium text-[var(--ink)]">Sin contenido aún</p>
        <p className="mt-1 text-sm text-[var(--muted)]">
          Fase 1: modelo de organización y perfiles. Documentos (Fase 2), correo
          (Fase 3) y flujos (Fase 4) llegan después.
        </p>
      </section>
    </div>
  );
}
