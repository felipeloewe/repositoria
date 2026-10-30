import { cookies } from "next/headers";
import type { Rol } from "@prisma/client";
import { prisma } from "@/lib/prisma";

/** Authenticated user identity cookie — value is Usuario.id (cuid). */
export const USER_COOKIE = "repositoria_user_id";
/** Active tenant profile — value is Membresia.id (cuid). */
export const SESSION_COOKIE = "repositoria_membresia_id";
/** One-shot banner after switching empresa with a different role. */
export const PROFILE_NOTICE_COOKIE = "repositoria_profile_notice";

export type Session = {
  membresiaId: string;
  userId: string;
  email: string;
  nombre: string;
  rut: string | null;
  empresaId: string;
  empresaNombre: string;
  empresaSlug: string;
  empresaRut: string | null;
  rol: Rol;
  sucursalId: string | null;
  sucursalNombre: string | null;
  /** Departamento that owns the assigned sucursal (Jefe); null otherwise. */
  departamentoId: string | null;
  departamentoNombre: string | null;
};

export type ProfileSwitchNotice = {
  fromEmpresaNombre: string;
  fromRol: Rol;
  toEmpresaNombre: string;
  toRol: Rol;
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
    // ACL/tenancy: membership must belong to this user id.
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
      sucursal: { include: { departamento: true } },
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
    rut: membresia.usuario.rut,
    empresaId: membresia.empresaId,
    empresaNombre: membresia.empresa.nombre,
    empresaSlug: membresia.empresa.slug,
    empresaRut: membresia.empresa.rut,
    rol: membresia.rol,
    sucursalId: membresia.sucursalId,
    sucursalNombre: membresia.sucursal?.nombre ?? null,
    departamentoId: membresia.sucursal?.departamentoId ?? null,
    departamentoNombre: membresia.sucursal?.departamento?.nombre ?? null,
  };
}

/** Active memberships for a user (at most one per empresa). */
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
    orderBy: [{ empresa: { nombre: "asc" } }],
  });
}

export type TenantOption = {
  empresaId: string;
  empresaNombre: string;
  empresaSlug: string;
  empresaRut: string | null;
  membresiaId: string;
  rol: Rol;
  sucursalNombre: string | null;
};

/** One option per empresa membership — role comes from that Membresia row. */
export function tenantOptionsFromMembresias(
  membresias: Awaited<ReturnType<typeof listUserMembresias>>,
): TenantOption[] {
  return membresias
    .map((m) => ({
      empresaId: m.empresaId,
      empresaNombre: m.empresa.nombre,
      empresaSlug: m.empresa.slug,
      empresaRut: m.empresa.rut,
      membresiaId: m.id,
      rol: m.rol,
      sucursalNombre: m.sucursal?.nombre ?? null,
    }))
    .sort((a, b) => a.empresaNombre.localeCompare(b.empresaNombre, "es"));
}

export async function countUserEmpresas(userId: string): Promise<number> {
  return (await listUserMembresias(userId)).length;
}

/** Read-only — cookie deletion must happen in a Server Action. */
export async function peekProfileNotice(): Promise<ProfileSwitchNotice | null> {
  const jar = await cookies();
  const raw = jar.get(PROFILE_NOTICE_COOKIE)?.value;
  if (!raw) return null;
  try {
    return JSON.parse(decodeURIComponent(raw)) as ProfileSwitchNotice;
  } catch {
    return null;
  }
}

export function encodeProfileNotice(notice: ProfileSwitchNotice): string {
  return encodeURIComponent(JSON.stringify(notice));
}
