import Link from "next/link";
import { redirect } from "next/navigation";
import { LoginForm } from "@/components/login-form";
import { Panel } from "@/components/ui";
import { getAuthenticatedUserId, getSession } from "@/lib/session";
import { prisma } from "@/lib/prisma";

export const instant = false;

export const metadata = {
  title: "Ingresar",
};

export default async function LoginPage({
  searchParams,
}: {
  searchParams: Promise<{ error?: string }>;
}) {
  const session = await getSession();
  if (session) {
    redirect("/dashboard");
  }

  const userId = await getAuthenticatedUserId();
  if (userId) {
    redirect("/seleccionar-empresa");
  }

  const params = await searchParams;
  const userCount = await prisma.usuario.count();
  const showDevHint = process.env.NODE_ENV !== "production";

  return (
    <div className="flex flex-1 items-center justify-center bg-[radial-gradient(ellipse_at_top,_rgba(26,92,255,0.18),_transparent_55%),linear-gradient(180deg,#f4f6fb_0%,#e7eeff_100%)] px-4 py-16">
      <div className="w-full max-w-md">
        <p className="inline-flex rounded-2xl bg-[linear-gradient(135deg,var(--banner),var(--banner-end))] px-3.5 py-1.5 font-[family-name:var(--font-display)] text-2xl font-semibold tracking-tight text-white shadow-[0_10px_24px_rgba(26,92,255,0.28)]">
          Repositoria
        </p>
        <p className="mt-2 text-[var(--muted)]">
          Ingresa con tu usuario. El alcance (empresa, rol y sucursal) sale de
          tu membresía — no se elige un perfil de disfraz.
        </p>

        {userCount === 0 ? (
          <Panel className="mt-8 p-6">
            <p className="font-medium text-[var(--ink)]">Sin usuarios</p>
            <p className="mt-1 text-sm text-[var(--muted)]">
              Ejecuta{" "}
              <code className="rounded bg-[var(--surface-muted)] px-1">
                npm run db:seed
              </code>{" "}
              para cargar usuarios de prueba.
            </p>
          </Panel>
        ) : (
          <Panel className="mt-8 p-6">
            <LoginForm initialError={params.error} />
          </Panel>
        )}

        {showDevHint ? (
          <p className="mt-4 text-center text-xs text-[var(--muted)]">
            Seed: correo de prueba + contraseña{" "}
            <code className="rounded bg-[var(--surface-muted)] px-1">demo123</code>
            . Ver README.
            {process.env.DEV_AUTH_SWITCHER === "1" ? (
              <>
                {" "}
                ·{" "}
                <Link href="/dev/switcher" className="underline">
                  switcher solo-dev
                </Link>
              </>
            ) : null}
          </p>
        ) : null}
      </div>
    </div>
  );
}
