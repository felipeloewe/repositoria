import Link from "next/link";

export const metadata = {
  title: "Ingresar",
};

export default function LoginPage() {
  return (
    <div className="flex flex-1 items-center justify-center bg-[radial-gradient(ellipse_at_top,_var(--accent-soft),_transparent_55%),linear-gradient(180deg,var(--surface)_0%,#e4ebe7_100%)] px-4 py-16">
      <div className="w-full max-w-md">
        <p className="font-[family-name:var(--font-display)] text-3xl tracking-tight text-[var(--ink)]">
          Repositoria
        </p>
        <p className="mt-2 text-[var(--muted)]">
          Repositorio documental multi-empresa. Auth real llegará en una
          iteración posterior; este ingreso es un stub de desarrollo.
        </p>

        <div className="mt-8 space-y-4 rounded-lg border border-[var(--border)] bg-[var(--surface-elevated)] p-6 shadow-sm">
          <label className="block space-y-1.5">
            <span className="text-sm font-medium text-[var(--ink)]">Correo</span>
            <input
              type="email"
              name="email"
              defaultValue="admin@repositoria.local"
              readOnly
              className="w-full rounded border border-[var(--border)] bg-[var(--surface-muted)] px-3 py-2 text-sm text-[var(--muted)]"
              autoComplete="username"
            />
          </label>
          <label className="block space-y-1.5">
            <span className="text-sm font-medium text-[var(--ink)]">
              Contraseña
            </span>
            <input
              type="password"
              name="password"
              defaultValue="demo"
              readOnly
              className="w-full rounded border border-[var(--border)] bg-[var(--surface-muted)] px-3 py-2 text-sm text-[var(--muted)]"
              autoComplete="current-password"
            />
          </label>
          <Link
            href="/dashboard"
            className="flex w-full items-center justify-center rounded bg-[var(--accent)] px-4 py-2.5 text-sm font-medium text-white transition-opacity hover:opacity-90"
          >
            Continuar al dashboard
          </Link>
        </div>

        <p className="mt-4 text-center text-xs text-[var(--muted)]">
          Stub · no valida credenciales · sesión demo Administrador Master
        </p>
      </div>
    </div>
  );
}
