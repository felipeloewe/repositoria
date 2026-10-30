import { redirect } from "next/navigation";
import { selectEmpresa, changeEmpresa } from "@/app/actions/auth";
import { btnPrimary, btnSecondary, Panel } from "@/components/ui";
import { roleLabel } from "@/lib/roles";
import {
  getAuthenticatedUserId,
  getSession,
  listUserMembresias,
  tenantOptionsFromMembresias,
} from "@/lib/session";

export const instant = false;

export const metadata = {
  title: "Confirmar cambio de empresa",
};

export default async function ConfirmarCambioEmpresaPage({
  searchParams,
}: {
  searchParams: Promise<{ empresaId?: string }>;
}) {
  const userId = await getAuthenticatedUserId();
  if (!userId) redirect("/login");

  const current = await getSession();
  if (!current) redirect("/seleccionar-empresa");

  const { empresaId } = await searchParams;
  if (!empresaId) redirect("/seleccionar-empresa");

  const tenants = tenantOptionsFromMembresias(
    await listUserMembresias(userId),
  );
  const target = tenants.find((t) => t.empresaId === empresaId);
  if (!target) redirect("/seleccionar-empresa");

  if (target.empresaId === current.empresaId) {
    redirect("/dashboard");
  }

  const roleDiffers = target.rol !== current.rol;

  return (
    <div className="flex flex-1 items-center justify-center bg-[radial-gradient(ellipse_at_top,_var(--accent-soft),_transparent_55%),linear-gradient(180deg,var(--surface)_0%,#e4ebe7_100%)] px-4 py-16">
      <div className="w-full max-w-lg">
        <p className="font-[family-name:var(--font-display)] text-3xl tracking-tight text-[var(--ink)]">
          Repositoria
        </p>

        <Panel className="mt-6 space-y-4 p-6">
          <h1 className="text-lg font-medium text-[var(--ink)]">
            {roleDiffers
              ? "Cambio de empresa con otro perfil"
              : "Cambiar de empresa"}
          </h1>

          {roleDiffers ? (
            <div className="rounded border border-amber-200 bg-amber-50 px-3 py-3 text-sm text-amber-950">
              <p className="font-medium">Tu rol activo se va a restablecer</p>
              <p className="mt-1">
                En <strong>{current.empresaNombre}</strong> eres{" "}
                <strong>{roleLabel(current.rol)}</strong>. Al entrar a{" "}
                <strong>{target.empresaNombre}</strong> tu sesión usará el
                perfil de esa membresía:{" "}
                <strong>{roleLabel(target.rol)}</strong>
                {target.sucursalNombre
                  ? ` · ${target.sucursalNombre}`
                  : ""}
                . No se conserva el rol anterior.
              </p>
            </div>
          ) : (
            <p className="text-sm text-[var(--muted)]">
              Entrarás a <strong>{target.empresaNombre}</strong> con el mismo
              tipo de rol ({roleLabel(target.rol)}). El perfil activo se
              restablece a la membresía de esa empresa.
            </p>
          )}

          <dl className="grid gap-3 text-sm sm:grid-cols-2">
            <div className="rounded border border-[var(--border)] p-3">
              <dt className="text-xs uppercase tracking-wide text-[var(--muted)]">
                Actual
              </dt>
              <dd className="mt-1 font-medium text-[var(--ink)]">
                {current.empresaNombre}
              </dd>
              <dd className="text-[var(--muted)]">{roleLabel(current.rol)}</dd>
            </div>
            <div className="rounded border border-[var(--accent)] bg-[var(--accent-soft)] p-3">
              <dt className="text-xs uppercase tracking-wide text-[var(--muted)]">
                Nuevo
              </dt>
              <dd className="mt-1 font-medium text-[var(--ink)]">
                {target.empresaNombre}
              </dd>
              <dd className="text-[var(--muted)]">{roleLabel(target.rol)}</dd>
            </div>
          </dl>

          <div className="flex flex-wrap gap-2 pt-1">
            <form action={selectEmpresa}>
              <input type="hidden" name="empresaId" value={target.empresaId} />
              <input type="hidden" name="confirmed" value="1" />
              <button type="submit" className={btnPrimary}>
                Confirmar y restablecer perfil
              </button>
            </form>
            <form action={changeEmpresa}>
              <button type="submit" className={btnSecondary}>
                Volver
              </button>
            </form>
          </div>
        </Panel>
      </div>
    </div>
  );
}
