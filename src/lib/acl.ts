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

/** Master manages all sucursales; Jefe only their assigned branch. */
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

/**
 * Hierarchy: Empresa → Departamentos → Sucursales.
 * Master: all depts. Jefe: the departamento that owns their sucursal.
 */
export function canViewDepartamento(
  session: Session,
  departamentoId: string,
): boolean {
  if (isMasterRole(session.rol)) return true;
  if (session.rol === "JEFE_SUCURSAL") {
    return session.departamentoId === departamentoId;
  }
  return false;
}

/** Master creates/edits/deletes departamentos. */
export function canManageDepartamento(session: Session): boolean {
  return isMasterRole(session.rol);
}

/**
 * Actividades / Temas de Interés tags on a Departamento.
 * Master: any. Jefe: tags of their departamento only.
 */
export function canManageDepartamentoTags(
  session: Session,
  departamentoId: string,
): boolean {
  if (isMasterRole(session.rol)) return true;
  if (session.rol === "JEFE_SUCURSAL") {
    return session.departamentoId === departamentoId;
  }
  return false;
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

export type DocumentoAclInput = {
  empresaId: string;
  departamentoId: string;
  sucursalId: string | null;
  autorizadoUsuarioIds: string[];
};

/**
 * Master: all tenant docs.
 * Jefe: own sucursal + departamento-level docs (sucursalId null) of their depto.
 * Externals: authorized only.
 */
export function canViewDocumento(
  session: Session,
  doc: DocumentoAclInput,
): boolean {
  if (session.empresaId !== doc.empresaId) return false;
  if (isMasterRole(session.rol)) return true;
  if (session.rol === "JEFE_SUCURSAL") {
    if (doc.sucursalId === null) {
      return session.departamentoId === doc.departamentoId;
    }
    return !!session.sucursalId && doc.sucursalId === session.sucursalId;
  }
  if (isExternalRole(session.rol)) {
    return doc.autorizadoUsuarioIds.includes(session.userId);
  }
  return false;
}

/** Master: any section. Jefe: only sections scoped to their sucursal (not depto-level). */
export function canUploadDocumento(
  session: Session,
  target: { sucursalId: string | null; departamentoId?: string },
): boolean {
  if (isMasterRole(session.rol)) return true;
  if (session.rol === "JEFE_SUCURSAL") {
    if (target.sucursalId === null) return false; // only Master uploads depto-level
    return session.sucursalId === target.sucursalId;
  }
  return false;
}

export function canDeleteDocumento(
  session: Session,
  doc: DocumentoAclInput & { subidoPorId?: string },
): boolean {
  if (!canViewDocumento(session, doc)) return false;
  if (isMasterRole(session.rol)) return true;
  if (session.rol === "JEFE_SUCURSAL") {
    return (
      !!session.sucursalId &&
      doc.sucursalId === session.sucursalId
    );
  }
  return false;
}

export function requireDocumentoView(
  session: Session,
  doc: DocumentoAclInput,
) {
  if (!canViewDocumento(session, doc)) {
    throw new AclError("Sin acceso a este documento");
  }
}

export function requireDocumentoUpload(
  session: Session,
  target: { sucursalId: string | null; departamentoId?: string },
) {
  if (!canUploadDocumento(session, target)) {
    throw new AclError("Sin permiso para subir en esta sección");
  }
}

/** Master or Jefe can open fiscalización / flujos. Externals use canales only. */
export function canBrowseFlujos(session: Session): boolean {
  return (
    isMasterRole(session.rol) ||
    session.rol === "JEFE_SUCURSAL" ||
    isExternalRole(session.rol)
  );
}

/** Create / submit fiscalización requests (Master or Jefe). */
export function canRequestFiscalizacion(session: Session): boolean {
  return isMasterRole(session.rol) || session.rol === "JEFE_SUCURSAL";
}

/** Only Master approves/denies and authorizes send/export. */
export function canReviewFiscalizacion(session: Session): boolean {
  return isMasterRole(session.rol);
}

export function canViewFiscalizacion(
  session: Session,
  solicitud: { empresaId: string; sucursalId: string | null; solicitadoPorId: string },
): boolean {
  if (session.empresaId !== solicitud.empresaId) return false;
  if (isMasterRole(session.rol)) return true;
  if (session.rol === "JEFE_SUCURSAL") {
    if (solicitud.solicitadoPorId === session.userId) return true;
    return (
      !!session.sucursalId &&
      !!solicitud.sucursalId &&
      solicitud.sucursalId === session.sucursalId
    );
  }
  return false;
}

/** Master creates/revokes secure packages for externals. */
export function canManageCanalPaquetes(session: Session): boolean {
  return isMasterRole(session.rol);
}

export function canViewCanalPaquete(
  session: Session,
  paquete: { empresaId: string; destinatarioId: string; estado: string },
): boolean {
  if (session.empresaId !== paquete.empresaId) return false;
  if (isMasterRole(session.rol)) return true;
  if (isExternalRole(session.rol) && paquete.destinatarioId === session.userId) {
    return paquete.estado === "ACTIVO";
  }
  return false;
}
