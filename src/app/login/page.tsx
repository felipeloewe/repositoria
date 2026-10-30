import Link from "next/link";
import { roleLabel } from "@/lib/roles";
import { getSession, listDemoMembresias } from "@/lib/session";
import { btnPrimary, Panel } from "@/components/ui";

export const instant = false;

export const metadata = {
  title: "Ingresar",
};

export default async function LoginPage() {
  const [membresias, current] = await Promise.all([
    listDemoMembresias(),
    getSession(),
  ]);

  return (
    <div className="flex flex-1 items-center justify-center bg-[radial-gradient(ellipse_at_top,_var(--accent-soft),_transparent_55%),linear-gradient(180deg,var(--surface)_0%,#e4ebe7_100%)] px-4 py-16">
      <div className="w-full max-w-xl">
        <p className="font-[family-name:var(--font-display)] text-3xl tracking-tight text-[var(--ink)]">
          Repositoria
        </p>
        <p className="mt-2 text-[var(--muted)]">
          Stub de auth: elige un usuario demo para probar ACL por rol y
          sucursal. Auth real llega después.
        </p>

        {membresias.length === 0 ? (
          <Panel className="mt-8 p-6">
            <p className="font-medium text-[var(--ink)]">Sin datos seed</p>
            <p className="mt-1 text-sm text-[var(--muted)]">
              Ejecuta{" "}
              <code className="rounded bg-[var(--surface-muted)] px-1">
                npm run db:seed
              </code>{" "}
              tras migrar MySQL.
            </p>
          </Panel>
        ) : (
          <ul className="mt-8 space-y-2">
            {membresias.map((m) => {
              const active = current?.membresiaId === m.id;
              const href = `/api/demo-switch?membresiaId=${encodeURIComponent(m.id)}&next=/dashboard`;
              return (
                <li key={m.id}>
                  <Link
                    href={href}
                    className={`flex w-full items-center justify-between gap-3 rounded-lg border px-4 py-3 text-left transition-colors ${
                      active
                        ? "border-[var(--accent)] bg-[var(--accent-soft)]"
                        : "border-[var(--border)] bg-[var(--surface-elevated)] hover:border-[var(--accent)]"
                    }`}
                  >
                    <span>
                      <span className="block font-medium text-[var(--ink)]">
                        {m.usuario.nombre}
                      </span>
                      <span className="block text-sm text-[var(--muted)]">
                        {m.usuario.email} · {roleLabel(m.rol)}
                        {m.sucursal ? ` · ${m.sucursal.nombre}` : ""}
                      </span>
                    </span>
                    <span className={btnPrimary}>
                      {active ? "Continuar" : "Entrar"}
                    </span>
                  </Link>
                </li>
              );
            })}
          </ul>
        )}
      </div>
    </div>
  );
}
