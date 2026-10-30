import type { Rol } from "@prisma/client";

export const ROLES = [
  "ADMINISTRADOR_MASTER",
  "JEFE_SUCURSAL",
  "CONTADOR",
  "ABOGADO",
  "PREVENCIONISTA",
] as const satisfies readonly Rol[];

export type Role = (typeof ROLES)[number];

export const ROLE_LABELS: Record<Role, string> = {
  ADMINISTRADOR_MASTER: "Administrador Master",
  JEFE_SUCURSAL: "Jefe de Sucursal",
  CONTADOR: "Contador",
  ABOGADO: "Abogado",
  PREVENCIONISTA: "Prevencionista",
};

export const EXTERNAL_ROLES: readonly Role[] = [
  "CONTADOR",
  "ABOGADO",
  "PREVENCIONISTA",
];

export function roleLabel(rol: string): string {
  return ROLE_LABELS[rol as Role] ?? rol;
}

export function isRole(value: string): value is Role {
  return (ROLES as readonly string[]).includes(value);
}

export function isExternalRole(rol: string): boolean {
  return (EXTERNAL_ROLES as readonly string[]).includes(rol);
}

export function isMasterRole(rol: string): boolean {
  return rol === "ADMINISTRADOR_MASTER";
}
