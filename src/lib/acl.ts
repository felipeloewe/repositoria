import type { Rol } from "@prisma/client";
import { isExternalRole, isMasterRole } from "@/lib/roles";
import type { Session } from "@/lib/session";

export class AclError extends Error {
  constructor(message = "No autorizado") {
    super(message);
    this.name = "AclError";
  }
}

export function assertSameTenant(session: Session, empresaId: string) {
  if (session.empresaId !== empresaId) {
    throw new AclError("Tenant distinto");
  }
}

export function canManageOrganization(session: Session): boolean {
  return isMasterRole(session.rol);
}

export function canManageMemberships(session: Session): boolean {
  return isMasterRole(session.rol);
}

export function canManageSucursal(
  session: Session,
  sucursalId: string,
): boolean {
  if (isMasterRole(session.rol)) return true;
  if (session.rol === "JEFE_SUCURSAL") {
    return session.sucursalId === sucursalId;
  }
  return false;
}

export function canViewSucursal(session: Session, sucursalId: string): boolean {
  if (isMasterRole(session.rol)) return true;
  if (session.rol === "JEFE_SUCURSAL") {
    return session.sucursalId === sucursalId;
  }
  // Externals do not browse org structure by branch.
  return false;
}

export function filterVisibleSucursalIds(
  session: Session,
  sucursalIds: string[],
): string[] {
  if (isMasterRole(session.rol)) return sucursalIds;
  if (session.rol === "JEFE_SUCURSAL" && session.sucursalId) {
    return sucursalIds.filter((id) => id === session.sucursalId);
  }
  return [];
}

export function canBrowseOrganization(session: Session): boolean {
  return isMasterRole(session.rol) || session.rol === "JEFE_SUCURSAL";
}

export function canViewExpediente(
  session: Session,
  expediente: {
    empresaId: string;
    sucursalId: string | null;
    autorizadoUsuarioIds: string[];
  },
): boolean {
  if (session.empresaId !== expediente.empresaId) return false;
  if (isMasterRole(session.rol)) return true;
  if (session.rol === "JEFE_SUCURSAL") {
    return (
      !!session.sucursalId && expediente.sucursalId === session.sucursalId
    );
  }
  if (isExternalRole(session.rol)) {
    return expediente.autorizadoUsuarioIds.includes(session.userId);
  }
  return false;
}

export function requireMaster(session: Session) {
  if (!isMasterRole(session.rol)) {
    throw new AclError("Solo Administrador Master");
  }
}

export function requireOrgManager(session: Session) {
  if (!canManageOrganization(session)) {
    throw new AclError("Sin permiso de organización");
  }
}

export function requireSucursalAccess(session: Session, sucursalId: string) {
  if (!canViewSucursal(session, sucursalId)) {
    throw new AclError("Sin acceso a esta sucursal");
  }
}

export function requireSucursalManage(session: Session, sucursalId: string) {
  if (!canManageSucursal(session, sucursalId)) {
    throw new AclError("Sin permiso para administrar esta sucursal");
  }
}

export function roleNeedsSucursal(rol: Rol | string): boolean {
  return rol === "JEFE_SUCURSAL";
}
