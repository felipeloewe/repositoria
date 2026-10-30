import Link from "next/link";
import { notFound } from "next/navigation";
import { prisma } from "@/lib/prisma";
import { roleLabel } from "@/lib/roles";
import { btnPrimary, Panel } from "@/components/ui";

export const instant = false;

export const metadata = {
  title: "Dev switcher",
};

/** Local debug only — not the product entry. Requires DEV_AUTH_SWITCHER=1. */
export default async function DevSwitcherPage() {
  if (process.env.DEV_AUTH_SWITCHER !== "1") {
    notFound();
  }

  const membresias = await prisma.membresia.findMany({
    where: { activa: true },
    include: { usuario: true, empresa: true, sucursal: true },
    orderBy: [{ empresa: { nombre: "asc" } }, { rol: "asc" }],
  });

  return (
    <div className="mx-auto max-w-xl px-4 py-12">
      <p className="text-sm text-amber-800">
        Solo desarrollo (`DEV_AUTH_SWITCHER=1`). El flujo de producto es
        correo/contraseña en{" "}
        <Link href="/login" className="underline">
          /login
        </Link>
        .
      </p>
      <h1 className="mt-4 font-[family-name:var(--font-display)] text-2xl">
        Impersonación (dev)
      </h1>
      <ul className="mt-6 space-y-2">
        {membresias.map((m) => (
          <li key={m.id}>
            <Panel className="p-3">
              <Link
                href={`/api/demo-switch?membresiaId=${encodeURIComponent(m.id)}`}
                className="flex items-center justify-between gap-3"
              >
                <span className="text-sm">
                  <span className="block font-medium">{m.usuario.email}</span>
                  <span className="text-[var(--muted)]">
                    {m.empresa.nombre} · {roleLabel(m.rol)}
                  </span>
                </span>
                <span className={btnPrimary}>Usar</span>
              </Link>
            </Panel>
          </li>
        ))}
      </ul>
    </div>
  );
}
