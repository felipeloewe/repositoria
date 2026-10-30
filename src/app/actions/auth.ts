"use server";

import { cookies } from "next/headers";
import { redirect } from "next/navigation";
import { verifyPassword } from "@/lib/password";
import { prisma } from "@/lib/prisma";
import {
  SESSION_COOKIE,
  USER_COOKIE,
  listUserMembresias,
  loadSessionByMembresiaId,
  tenantOptionsFromMembresias,
} from "@/lib/session";

async function setAuthCookies(userId: string, membresiaId: string | null) {
  const jar = await cookies();
  jar.set(USER_COOKIE, userId, {
    httpOnly: true,
    sameSite: "lax",
    path: "/",
  });
  if (membresiaId) {
    jar.set(SESSION_COOKIE, membresiaId, {
      httpOnly: true,
      sameSite: "lax",
      path: "/",
    });
  } else {
    jar.delete(SESSION_COOKIE);
  }
}

export type LoginState = {
  error?: string;
};

export async function loginWithPassword(
  _prev: LoginState,
  formData: FormData,
): Promise<LoginState> {
  const email = String(formData.get("email") ?? "")
    .trim()
    .toLowerCase();
  const password = String(formData.get("password") ?? "");

  if (!email || !password) {
    return { error: "Correo y contraseña son obligatorios." };
  }

  const usuario = await prisma.usuario.findUnique({ where: { email } });
  if (!usuario || !usuario.activo) {
    return { error: "Credenciales incorrectas." };
  }
  if (!verifyPassword(password, usuario.passwordHash)) {
    return { error: "Credenciales incorrectas." };
  }

  const membresias = await listUserMembresias(usuario.id);
  const tenants = tenantOptionsFromMembresias(membresias);

  if (tenants.length === 0) {
    return {
      error: "Tu usuario no tiene membresías activas en ninguna empresa.",
    };
  }

  if (tenants.length === 1) {
    await setAuthCookies(usuario.id, tenants[0].membresiaId);
    redirect("/dashboard");
  }

  // Multiple empresas: authenticate user, then pick tenant (not role).
  await setAuthCookies(usuario.id, null);
  redirect("/seleccionar-empresa");
}

export async function selectEmpresa(formData: FormData) {
  const jar = await cookies();
  const userId = jar.get(USER_COOKIE)?.value;
  if (!userId) {
    redirect("/login");
  }

  const empresaId = String(formData.get("empresaId") ?? "");
  const membresias = await listUserMembresias(userId);
  const tenants = tenantOptionsFromMembresias(membresias);
  const chosen = tenants.find((t) => t.empresaId === empresaId);

  if (!chosen) {
    redirect("/seleccionar-empresa");
  }

  const session = await loadSessionByMembresiaId(chosen.membresiaId);
  if (!session || session.userId !== userId) {
    redirect("/login");
  }

  jar.set(SESSION_COOKIE, chosen.membresiaId, {
    httpOnly: true,
    sameSite: "lax",
    path: "/",
  });
  redirect("/dashboard");
}

/** Drop active tenant so the signed-in user can pick another empresa they belong to. */
export async function changeEmpresa() {
  const jar = await cookies();
  if (!jar.get(USER_COOKIE)?.value) {
    redirect("/login");
  }
  jar.delete(SESSION_COOKIE);
  redirect("/seleccionar-empresa");
}

export async function logout() {
  const jar = await cookies();
  jar.delete(USER_COOKIE);
  jar.delete(SESSION_COOKIE);
  redirect("/login");
}
