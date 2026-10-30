import Link from "next/link";
import { prisma } from "@/lib/prisma";
import {
  canBrowseOrganization,
  filterVisibleSucursalIds,
} from "@/lib/acl";
import { isExternalRole, isMasterRole, roleLabel } from "@/lib/roles";
import { peekProfileNotice, getSession } from "@/lib/session";
import { ProfileNotice } from "@/components/profile-notice";
import { EmptyState, Panel } from "@/components/ui";

export const metadata = {
  title: "Dashboard",
};

function greetingLabel(date: Date): string {
  const h = date.getHours();
  if (h < 12) return "Buenos días";
  if (h < 20) return "Buenas tardes";
  return "Buenas noches";
}

function formatDateEs(date: Date): string {
  return date.toLocaleDateString("es-CL", {
    weekday: "long",
    day: "numeric",
    month: "long",
  });
}

export default async function DashboardPage() {
  const session = (await getSession())!;
  const profileNotice = await peekProfileNotice();
  const now = new Date();

  const [sucursales, miembros, expedientes] = await Promise.all([
    prisma.sucursal.findMany({
      where: { empresaId: session.empresaId, activa: true },
      select: { id: true, nombre: true },
      orderBy: { nombre: "asc" },
    }),
    prisma.membresia.count({
      where: { empresaId: session.empresaId, activa: true },
    }),
    prisma.expedienteStub.findMany({
      where: { empresaId: session.empresaId },
      include: { autorizaciones: true, sucursal: true },
      orderBy: { titulo: "asc" },
    }),
  ]);

  const visibleSucursalIds = filterVisibleSucursalIds(
    session,
    sucursales.map((s) => s.id),
  );
  const visibleSucursales = sucursales.filter((s) =>
    visibleSucursalIds.includes(s.id),
  );

  const visibleExpedientes = expedientes.filter((e) => {
    if (isMasterRole(session.rol)) return true;
    if (session.rol === "JEFE_SUCURSAL") {
      return e.sucursalId === session.sucursalId;
    }
    if (isExternalRole(session.rol)) {
      return e.autorizaciones.some((a) => a.usuarioId === session.userId);
    }
    return false;
  });

  const firstName = session.nombre.split(/\s+/)[0] ?? session.nombre;

  return (
    <div className="mx-auto max-w-6xl space-y-6">
      {profileNotice ? <ProfileNotice notice={profileNotice} /> : null}

      <section className="overflow-hidden rounded-2xl bg-[linear-gradient(135deg,#14352c_0%,#0f6e56_55%,#1a5c4a_100%)] px-6 py-7 text-white shadow-sm sm:px-8">
        <p className="text-xs uppercase tracking-[0.14em] text-white/70">
          {greetingLabel(now)} · {formatDateEs(now)}
        </p>
        <div className="mt-3 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <h1 className="font-[family-name:var(--font-questia)] text-3xl tracking-tight sm:text-4xl">
              Hola, {firstName}
            </h1>
            <p className="mt-2 max-w-xl text-sm text-white/80">
              {session.empresaNombre}
              {session.empresaRut ? ` · RUT ${session.empresaRut}` : ""} ·{" "}
              {roleLabel(session.rol)}
              {session.sucursalNombre ? ` · ${session.sucursalNombre}` : ""}
            </p>
          </div>
          <div className="flex flex-wrap gap-2">
            {canBrowseOrganization(session) ? (
              <Link
                href="/organizacion"
                className="rounded-lg bg-white/15 px-3 py-2 text-sm backdrop-blur transition-colors hover:bg-white/25"
              >
                Organización
              </Link>
            ) : null}
            <Link
              href="/expedientes"
              className="rounded-lg bg-white px-3 py-2 text-sm font-medium text-[var(--accent)]"
            >
              Expedientes
            </Link>
          </div>
        </div>
        <div className="mt-5 flex flex-wrap gap-2">
          <span className="rounded-full bg-black/20 px-3 py-1 text-xs text-white/90">
            {roleLabel(session.rol)}
          </span>
          {canBrowseOrganization(session) ? (
            <span className="rounded-full bg-black/20 px-3 py-1 text-xs text-white/90">
              {visibleSucursales.length} sucursales visibles
            </span>
          ) : null}
          <span className="rounded-full bg-black/20 px-3 py-1 text-xs text-white/90">
            {visibleExpedientes.length} expedientes visibles
          </span>
          {isMasterRole(session.rol) ? (
            <span className="rounded-full bg-black/20 px-3 py-1 text-xs text-white/90">
              {miembros} miembros
            </span>
          ) : null}
        </div>
      </section>

      <dl className="grid gap-4 sm:grid-cols-3">
        <Panel className="rounded-2xl p-5 shadow-sm">
          <dt className="text-xs uppercase tracking-wide text-[var(--muted)]">
            Rol activo
          </dt>
          <dd className="mt-2 text-2xl text-[var(--ink)]">
            {roleLabel(session.rol)}
          </dd>
        </Panel>
        <Panel className="rounded-2xl p-5 shadow-sm">
          <dt className="text-xs uppercase tracking-wide text-[var(--muted)]">
            Sucursales
          </dt>
          <dd className="mt-2 text-2xl text-[var(--ink)]">
            {canBrowseOrganization(session) ? visibleSucursales.length : "—"}
          </dd>
        </Panel>
        <Panel className="rounded-2xl p-5 shadow-sm">
          <dt className="text-xs uppercase tracking-wide text-[var(--muted)]">
            Expedientes
          </dt>
          <dd className="mt-2 text-2xl text-[var(--ink)]">
            {visibleExpedientes.length}
          </dd>
        </Panel>
      </dl>

      <div className="grid gap-4 lg:grid-cols-2">
        {canBrowseOrganization(session) ? (
          <Panel className="rounded-2xl p-5 shadow-sm">
            <div className="flex items-center justify-between gap-3">
              <h2 className="text-[var(--ink)]">Sucursales</h2>
              <Link
                href="/organizacion/sucursales"
                className="text-sm text-[var(--accent)] hover:underline"
              >
                Ver todas
              </Link>
            </div>
            {visibleSucursales.length === 0 ? (
              <p className="mt-3 text-sm text-[var(--muted)]">Sin sucursales.</p>
            ) : (
              <ul className="mt-3 divide-y divide-[var(--border)]">
                {visibleSucursales.map((s) => (
                  <li key={s.id} className="py-2.5 text-sm text-[var(--ink)]">
                    {s.nombre}
                  </li>
                ))}
              </ul>
            )}
          </Panel>
        ) : (
          <EmptyState
            title="Vista externa acotada"
            detail="Tu membresía solo autoriza expedientes concretos. El alcance sale del perfil de esta empresa."
          />
        )}

        <Panel className="rounded-2xl p-5 shadow-sm">
          <div className="flex items-center justify-between gap-3">
            <h2 className="text-[var(--ink)]">Expedientes (stub ACL)</h2>
            <Link
              href="/expedientes"
              className="text-sm text-[var(--accent)] hover:underline"
            >
              Ver todos
            </Link>
          </div>
          {visibleExpedientes.length === 0 ? (
            <p className="mt-3 text-sm text-[var(--muted)]">
              Ningún expediente en tu alcance.
            </p>
          ) : (
            <ul className="mt-3 divide-y divide-[var(--border)]">
              {visibleExpedientes.slice(0, 5).map((e) => (
                <li
                  key={e.id}
                  className="flex justify-between gap-3 py-2.5 text-sm"
                >
                  <span className="text-[var(--ink)]">{e.titulo}</span>
                  <span className="shrink-0 text-[var(--muted)]">
                    {e.tipo}
                    {e.sucursal ? ` · ${e.sucursal.nombre}` : ""}
                  </span>
                </li>
              ))}
            </ul>
          )}
        </Panel>
      </div>
    </div>
  );
}
