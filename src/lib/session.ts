import { cookies } from "next/headers";
import type { Rol } from "@prisma/client";
import { prisma } from "@/lib/prisma";

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

export async function getSession(): Promise<Session | null> {
  if (process.env.AUTH_STUB_DISABLED === "1") {
    return null;
  }

  const jar = await cookies();
  const membresiaId = jar.get(SESSION_COOKIE)?.value;

  if (membresiaId) {
    const session = await loadSessionByMembresiaId(membresiaId);
    if (session) return session;
  }

  // Fallback: first Master membership so the shell stays browsable before seed login.
  const fallback = await prisma.membresia.findFirst({
    where: { activa: true, rol: "ADMINISTRADOR_MASTER" },
    orderBy: { createdAt: "asc" },
    select: { id: true },
  });

  if (!fallback) return null;
  return loadSessionByMembresiaId(fallback.id);
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

  if (!membresia || !membresia.activa || !membresia.usuario.activo) {
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

export async function listDemoMembresias() {
  return prisma.membresia.findMany({
    where: { activa: true },
    include: {
      usuario: true,
      empresa: true,
      sucursal: true,
    },
    orderBy: [{ rol: "asc" }, { createdAt: "asc" }],
  });
}
