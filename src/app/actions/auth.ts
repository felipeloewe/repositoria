"use server";

import { cookies } from "next/headers";
import { redirect } from "next/navigation";
import { SESSION_COOKIE, loadSessionByMembresiaId } from "@/lib/session";

export async function switchDemoUser(formData: FormData) {
  const membresiaId = String(formData.get("membresiaId") ?? "");
  if (!membresiaId) {
    throw new Error("membresiaId requerido");
  }

  const session = await loadSessionByMembresiaId(membresiaId);
  if (!session) {
    throw new Error("Membresía no válida");
  }

  const jar = await cookies();
  jar.set(SESSION_COOKIE, membresiaId, {
    httpOnly: true,
    sameSite: "lax",
    path: "/",
  });

  redirect("/dashboard");
}

export async function logoutDemoUser() {
  const jar = await cookies();
  jar.delete(SESSION_COOKIE);
  redirect("/login");
}
