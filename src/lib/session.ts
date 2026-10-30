import { cookies } from "next/headers";
import type { Rol } from "@prisma/client";
import { prisma } from "@/lib/prisma";

export const USER_COOKIE = "repositoria_user_id";
export const SESSION_COOKIE = "repositoria_membresia_id";

export type Session = {
  membresiaId: string;
  userId: string;
  email: string;
  nombre: string;
  empresaId: string;
  empresaNombre: string;
  empresaSlug: string;
  rol: Rol;
  sucursalId: string | null;
  sucursalNombre: string | null;
};

const ROLE_RANK: Record<Rol, number> = {
  ADMINISTRADOR_MASTER: 100,
  JEFE_SUCURSAL: 80,
  CONTADOR: 40,
  ABOGADO: 40,
  PREVENCIONISTA: 40,
};

export async function getAuthenticatedUserId(): Promise<string | null> {
  if (process.env.AUTH_STUB_DISABLED === "1") return null;
  const jar = await cookies();
  return jar.get(USER_COOKIE)?.value ?? null;
}

export async function getSession(): Promise<Session | null> {
  if (process.env.AUTH_STUB_DISABLED === "1") {
    return null;
  }

  const jar = await cookies();
  const userId = jar.get(USER_COOKIE)?.value;
  const membresiaId = jar.get(SESSION_COOKIE)?.value;

  if (!userId) return null;

  if (membresiaId) {
    const session = await loadSessionByMembresiaId(membresiaId);
    if (session && session.userId === userId) return session;
  }

  return null;
}

export async function requireSession(): Promise<Session> {
  const session = await getSession();
  if (!session) {
    throw new Error("No autenticado");
  }
  return session;
}

export async function loadSessionByMembresiaId(
  membresiaId: string,
): Promise<Session | null> {
  const membresia = await prisma.membresia.findUnique({
    where: { id: membresiaId },
    include: {
      usuario: true,
      empresa: true,
      sucursal: true,
    },
  });

  if (
    !membresia ||
    !membresia.activa ||
    !membresia.usuario.activo ||
    !membresia.empresa.activa
  ) {
    return null;
  }

  return {
    membresiaId: membresia.id,
    userId: membresia.usuarioId,
    email: membresia.usuario.email,
    nombre: membresia.usuario.nombre,
    empresaId: membresia.empresaId,
    empresaNombre: membresia.empresa.nombre,
    empresaSlug: membresia.empresa.slug,
    rol: membresia.rol,
    sucursalId: membresia.sucursalId,
    sucursalNombre: membresia.sucursal?.nombre ?? null,
  };
}

/** Active memberships for a user, grouped later by empresa for tenant picker. */
export async function listUserMembresias(userId: string) {
  return prisma.membresia.findMany({
    where: {
      usuarioId: userId,
      activa: true,
      empresa: { activa: true },
      usuario: { activo: true },
    },
    include: {
      empresa: true,
      sucursal: true,
    },
    orderBy: [{ empresa: { nombre: "asc" } }, { rol: "asc" }],
  });
}

export type TenantOption = {
  empresaId: string;
  empresaNombre: string;
  empresaSlug: string;
  /** Membership chosen for this tenant (highest-privilege perfil, not a costume picker). */
  membresiaId: string;
  rol: Rol;
  sucursalNombre: string | null;
};

/**
 * One option per empresa the user belongs to.
 * If they have several roles in the same empresa, pick the highest-privilege
 * membership automatically — never offer a role costume switcher.
 */
export function tenantOptionsFromMembresias(
  membresias: Awaited<ReturnType<typeof listUserMembresias>>,
): TenantOption[] {
  const byEmpresa = new Map<string, typeof membresias>();

  for (const m of membresias) {
    const list = byEmpresa.get(m.empresaId) ?? [];
    list.push(m);
    byEmpresa.set(m.empresaId, list);
  }

  const options: TenantOption[] = [];
  for (const [, list] of byEmpresa) {
    const best = [...list].sort(
      (a, b) => (ROLE_RANK[b.rol] ?? 0) - (ROLE_RANK[a.rol] ?? 0),
    )[0];
    options.push({
      empresaId: best.empresaId,
      empresaNombre: best.empresa.nombre,
      empresaSlug: best.empresa.slug,
      membresiaId: best.id,
      rol: best.rol,
      sucursalNombre: best.sucursal?.nombre ?? null,
    });
  }

  return options.sort((a, b) =>
    a.empresaNombre.localeCompare(b.empresaNombre, "es"),
  );
}

export async function countUserEmpresas(userId: string): Promise<number> {
  const memberships = await listUserMembresias(userId);
  return tenantOptionsFromMembresias(memberships).length;
}
