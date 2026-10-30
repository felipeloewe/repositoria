import { redirect } from "next/navigation";
import { selectEmpresa, logout } from "@/app/actions/auth";
import { btnPrimary, btnSecondary, Panel } from "@/components/ui";
import { roleLabel } from "@/lib/roles";
import {
  getAuthenticatedUserId,
  getSession,
  listUserMembresias,
  tenantOptionsFromMembresias,
} from "@/lib/session";
import { prisma } from "@/lib/prisma";

export const instant = false;

export const metadata = {
  title: "Elegir empresa",
};

export default async function SeleccionarEmpresaPage() {
  const userId = await getAuthenticatedUserId();
  if (!userId) {
    redirect("/login");
  }

  const usuario = await prisma.usuario.findUnique({ where: { id: userId } });
  if (!usuario) {
    redirect("/login");
  }

  const tenants = tenantOptionsFromMembresias(
    await listUserMembresias(userId),
  );

  if (tenants.length === 0) {
    redirect(
      "/login?error=" + encodeURIComponent("Sin membresías activas."),
    );
  }

  const session = await getSession();
  // If already scoped and only one empresa, stay in the app.
  if (session && tenants.length === 1) {
    redirect("/dashboard");
  }

  // Authenticated but no tenant cookie yet, and only one empresa: auto-enter.
  if (!session && tenants.length === 1) {
    // Render a one-click continue (cookie set must happen via action).
    return (
      <div className="flex flex-1 items-center justify-center px-4 py-16">
        <Panel className="w-full max-w-md p-6">
          <p className="font-medium text-[var(--ink)]">{tenants[0].empresaNombre}</p>
          <p className="mt-1 text-sm text-[var(--muted)]">
            {roleLabel(tenants[0].rol)}
          </p>
          <form action={selectEmpresa} className="mt-4">
            <input type="hidden" name="empresaId" value={tenants[0].empresaId} />
            <button type="submit" className={`${btnPrimary} w-full`}>
              Continuar
            </button>
          </form>
        </Panel>
      </div>
    );
  }

  return (
    <div className="flex flex-1 items-center justify-center bg-[radial-gradient(ellipse_at_top,_var(--accent-soft),_transparent_55%),linear-gradient(180deg,var(--surface)_0%,#e4ebe7_100%)] px-4 py-16">
      <div className="w-full max-w-lg">
        <p className="font-[family-name:var(--font-display)] text-3xl tracking-tight text-[var(--ink)]">
          Repositoria
        </p>
        <p className="mt-2 text-[var(--muted)]">
          Hola, {usuario.nombre}. Elige una <strong>empresa</strong> de tu
          perfil. El rol sale de la membresía de esa empresa; si cambia respecto
          a la sesión actual, te pediremos confirmar y restableceremos el
          perfil activo.
        </p>

        <ul className="mt-8 space-y-2">
          {tenants.map((t) => {
            const active = session?.empresaId === t.empresaId;
            return (
              <li key={t.empresaId}>
                <form action={selectEmpresa}>
                  <input type="hidden" name="empresaId" value={t.empresaId} />
                  <button
                    type="submit"
                    className={`flex w-full items-center justify-between gap-3 rounded-lg border px-4 py-3 text-left transition-colors ${
                      active
                        ? "border-[var(--accent)] bg-[var(--accent-soft)]"
                        : "border-[var(--border)] bg-[var(--surface-elevated)] hover:border-[var(--accent)]"
                    }`}
                  >
                    <span>
                      <span className="block font-medium text-[var(--ink)]">
                        {t.empresaNombre}
                      </span>
                      <span className="block text-sm text-[var(--muted)]">
                        {roleLabel(t.rol)}
                        {t.sucursalNombre ? ` · ${t.sucursalNombre}` : ""}
                      </span>
                    </span>
                    <span className={btnPrimary}>
                      {active ? "Continuar" : "Entrar"}
                    </span>
                  </button>
                </form>
              </li>
            );
          })}
        </ul>

        <form action={logout} className="mt-6 text-center">
          <button type="submit" className={btnSecondary}>
            Salir
          </button>
        </form>
      </div>
    </div>
  );
}
