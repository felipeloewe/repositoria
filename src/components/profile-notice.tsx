"use client";

import { useEffect } from "react";
import { roleLabel } from "@/lib/roles";
import type { ProfileSwitchNotice } from "@/lib/session";

export function ProfileNotice({ notice }: { notice: ProfileSwitchNotice }) {
  useEffect(() => {
    void fetch("/api/clear-profile-notice", { method: "POST" });
  }, []);

  return (
    <div
      role="status"
      className="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950"
    >
      <p className="font-medium">Perfil de sesión restablecido</p>
      <p className="mt-1">
        Cambiaste de <strong>{notice.fromEmpresaNombre}</strong> (
        {roleLabel(notice.fromRol)}) a{" "}
        <strong>{notice.toEmpresaNombre}</strong> (
        {roleLabel(notice.toRol)}). El alcance activo es el de la membresía de
        esta empresa; el rol anterior no se conserva.
      </p>
    </div>
  );
}
