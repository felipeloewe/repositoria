export const ROLES = [
  "ADMINISTRADOR_MASTER",
  "JEFE_SUCURSAL",
  "CONTADOR",
  "ABOGADO",
  "PREVENCIONISTA",
] as const;

export type Role = (typeof ROLES)[number];

export const ROLE_LABELS: Record<Role, string> = {
  ADMINISTRADOR_MASTER: "Administrador Master",
  JEFE_SUCURSAL: "Jefe de Sucursal",
  CONTADOR: "Contador",
  ABOGADO: "Abogado",
  PREVENCIONISTA: "Prevencionista",
};

export function roleLabel(rol: string): string {
  return ROLE_LABELS[rol as Role] ?? rol;
}
