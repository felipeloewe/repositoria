"use server";

import { cookies } from "next/headers";
import { redirect } from "next/navigation";
import { verifyPassword } from "@/lib/password";
import { prisma } from "@/lib/prisma";
import {
  PROFILE_NOTICE_COOKIE,
  SESSION_COOKIE,
  USER_COOKIE,
  encodeProfileNotice,
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

  await setAuthCookies(usuario.id, null);
  redirect("/seleccionar-empresa");
}

/**
 * Enter (or switch to) an empresa by id.
 * Access is validated via Membresia (usuarioId + empresaId).
 * If the active role differs from the target membership, redirect to a
 * confirmation screen that resets the session profile.
 */
export async function selectEmpresa(formData: FormData) {
  const jar = await cookies();
  const userId = jar.get(USER_COOKIE)?.value;
  if (!userId) {
    redirect("/login");
  }

  const empresaId = String(formData.get("empresaId") ?? "");
  const confirmed = String(formData.get("confirmed") ?? "") === "1";

  const membresias = await listUserMembresias(userId);
  const tenants = tenantOptionsFromMembresias(membresias);
  const chosen = tenants.find((t) => t.empresaId === empresaId);

  if (!chosen) {
    redirect("/seleccionar-empresa");
  }

  const nextSession = await loadSessionByMembresiaId(chosen.membresiaId);
  if (!nextSession || nextSession.userId !== userId) {
    redirect("/login");
  }

  const currentMembresiaId = jar.get(SESSION_COOKIE)?.value;
  const current = currentMembresiaId
    ? await loadSessionByMembresiaId(currentMembresiaId)
    : null;

  const roleChanges =
    !!current &&
    current.empresaId !== nextSession.empresaId &&
    current.rol !== nextSession.rol;

  if (roleChanges && !confirmed) {
    redirect(
      `/seleccionar-empresa/confirmar?empresaId=${encodeURIComponent(empresaId)}`,
    );
  }

  // Always reset active session profile to the membership for the chosen empresa.
  jar.set(SESSION_COOKIE, chosen.membresiaId, {
    httpOnly: true,
    sameSite: "lax",
    path: "/",
  });

  if (roleChanges && current) {
    jar.set(
      PROFILE_NOTICE_COOKIE,
      encodeProfileNotice({
        fromEmpresaNombre: current.empresaNombre,
        fromRol: current.rol,
        toEmpresaNombre: nextSession.empresaNombre,
        toRol: nextSession.rol,
      }),
      {
        httpOnly: true,
        sameSite: "lax",
        path: "/",
        maxAge: 120,
      },
    );
  }

  redirect("/dashboard");
}

/** Keep current session while browsing the empresa list (needed to compare roles). */
export async function changeEmpresa() {
  const jar = await cookies();
  if (!jar.get(USER_COOKIE)?.value) {
    redirect("/login");
  }
  redirect("/seleccionar-empresa");
}

export async function logout() {
  const jar = await cookies();
  jar.delete(USER_COOKIE);
  jar.delete(SESSION_COOKIE);
  jar.delete(PROFILE_NOTICE_COOKIE);
  redirect("/login");
}
