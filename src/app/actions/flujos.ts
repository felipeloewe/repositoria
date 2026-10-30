"use server";

import { revalidatePath } from "next/cache";
import type { FiscalizacionTipo } from "@prisma/client";
import {
  AclError,
  canManageCanalPaquetes,
  canRequestFiscalizacion,
  canReviewFiscalizacion,
  canViewCanalPaquete,
  canViewFiscalizacion,
  requireMaster,
} from "@/lib/acl";
import { isExternalRole } from "@/lib/roles";
import { prisma } from "@/lib/prisma";
import { requireSession, type Session } from "@/lib/session";

function revalidateFlujos(id?: string) {
  revalidatePath("/flujos");
  revalidatePath("/flujos/fiscalizacion");
  revalidatePath("/flujos/canales");
  revalidatePath("/dashboard");
  if (id) {
    revalidatePath(`/flujos/fiscalizacion/${id}`);
    revalidatePath(`/flujos/canales/${id}`);
  }
}

async function auditFiscalizacion(
  session: Session,
  solicitudId: string,
  accion: string,
  detalle?: string,
) {
  await prisma.fiscalizacionAuditoria.create({
    data: {
      empresaId: session.empresaId,
      solicitudId,
      actorId: session.userId,
      accion,
      detalle: detalle ?? null,
    },
  });
}

async function auditCanal(
  session: Session,
  paqueteId: string,
  accion: string,
  detalle?: string,
) {
  await prisma.canalPaqueteAuditoria.create({
    data: {
      empresaId: session.empresaId,
      paqueteId,
      actorId: session.userId,
      accion,
      detalle: detalle ?? null,
    },
  });
}

export async function createFiscalizacionSolicitud(formData: FormData) {
  const session = await requireSession();
  if (!canRequestFiscalizacion(session)) {
    throw new AclError("Sin permiso para solicitar fiscalización");
  }

  const titulo = String(formData.get("titulo") ?? "").trim();
  const tipoRaw = String(formData.get("tipo") ?? "");
  const motivo = String(formData.get("motivo") ?? "").trim() || null;
  const departamentoId =
    String(formData.get("departamentoId") ?? "").trim() || null;
  const sucursalId = String(formData.get("sucursalId") ?? "").trim() || null;
  const documentoIds = formData
    .getAll("documentoId")
    .map((v) => String(v))
    .filter(Boolean);

  if (!titulo) throw new Error("Título requerido");
  if (tipoRaw !== "LIQUIDACION" && tipoRaw !== "CONTRATO") {
    throw new Error("Tipo inválido");
  }
  const tipo = tipoRaw as FiscalizacionTipo;

  if (session.rol === "JEFE_SUCURSAL") {
    if (!session.sucursalId) throw new AclError("Jefe sin sucursal");
    if (sucursalId && sucursalId !== session.sucursalId) {
      throw new AclError("Solo puedes solicitar para tu sucursal");
    }
  }

  if (documentoIds.length === 0) {
    throw new Error("Selecciona al menos un documento");
  }

  const docs = await prisma.documento.findMany({
    where: { id: { in: documentoIds }, empresaId: session.empresaId },
  });
  if (docs.length !== documentoIds.length) {
    throw new AclError("Documento inválido");
  }

  const submitNow = String(formData.get("submitNow") ?? "") === "1";

  const solicitud = await prisma.fiscalizacionSolicitud.create({
    data: {
      empresaId: session.empresaId,
      departamentoId,
      sucursalId:
        session.rol === "JEFE_SUCURSAL" ? session.sucursalId : sucursalId,
      tipo,
      titulo,
      motivo,
      estado: submitNow ? "PENDIENTE" : "BORRADOR",
      solicitadoPorId: session.userId,
      documentos: {
        create: documentoIds.map((documentoId) => ({ documentoId })),
      },
    },
  });

  await auditFiscalizacion(
    session,
    solicitud.id,
    "CREATED",
    `${tipo} · ${titulo}`,
  );
  if (submitNow) {
    await auditFiscalizacion(session, solicitud.id, "SUBMITTED");
  }

  revalidateFlujos(solicitud.id);
}

export async function submitFiscalizacion(formData: FormData) {
  const session = await requireSession();
  const id = String(formData.get("id") ?? "");
  const solicitud = await prisma.fiscalizacionSolicitud.findUnique({
    where: { id },
  });
  if (!solicitud || !canViewFiscalizacion(session, solicitud)) {
    throw new AclError("Solicitud no encontrada");
  }
  if (!canRequestFiscalizacion(session)) {
    throw new AclError("Sin permiso");
  }
  if (solicitud.estado !== "BORRADOR" && solicitud.estado !== "RECHAZADA") {
    throw new Error("Solo borradores o rechazadas pueden enviarse a revisión");
  }

  await prisma.fiscalizacionSolicitud.update({
    where: { id },
    data: {
      estado: "PENDIENTE",
      revisadoPorId: null,
      revisadoAt: null,
      comentarioRevision: null,
    },
  });
  await auditFiscalizacion(session, id, "SUBMITTED");
  revalidateFlujos(id);
}

export async function reviewFiscalizacion(formData: FormData) {
  const session = await requireSession();
  requireMaster(session);
  if (!canReviewFiscalizacion(session)) {
    throw new AclError("Solo Master aprueba o rechaza");
  }

  const id = String(formData.get("id") ?? "");
  const decision = String(formData.get("decision") ?? "");
  const comentario =
    String(formData.get("comentario") ?? "").trim() || null;

  const solicitud = await prisma.fiscalizacionSolicitud.findUnique({
    where: { id },
  });
  if (!solicitud || solicitud.empresaId !== session.empresaId) {
    throw new AclError("Solicitud no encontrada");
  }
  if (solicitud.estado !== "PENDIENTE") {
    throw new Error("La solicitud no está pendiente");
  }
  if (decision !== "APPROVE" && decision !== "DENY") {
    throw new Error("Decisión inválida");
  }

  const approved = decision === "APPROVE";
  await prisma.fiscalizacionSolicitud.update({
    where: { id },
    data: {
      estado: approved ? "APROBADA" : "RECHAZADA",
      revisadoPorId: session.userId,
      revisadoAt: new Date(),
      comentarioRevision: comentario,
    },
  });
  await auditFiscalizacion(
    session,
    id,
    approved ? "APPROVED" : "DENIED",
    comentario ?? undefined,
  );
  revalidateFlujos(id);
}

/** Mark as sent/exported — only after Master approval. */
export async function sendFiscalizacion(formData: FormData) {
  const session = await requireSession();
  requireMaster(session);

  const id = String(formData.get("id") ?? "");
  const solicitud = await prisma.fiscalizacionSolicitud.findUnique({
    where: { id },
  });
  if (!solicitud || solicitud.empresaId !== session.empresaId) {
    throw new AclError("Solicitud no encontrada");
  }
  if (solicitud.estado !== "APROBADA") {
    throw new AclError(
      "Se requiere autorización Master antes de exportar/enviar",
    );
  }

  await prisma.fiscalizacionSolicitud.update({
    where: { id },
    data: { estado: "ENVIADA", enviadoAt: new Date() },
  });
  await auditFiscalizacion(
    session,
    id,
    "SENT",
    "Exportación/envío autorizado registrado",
  );
  revalidateFlujos(id);
}

export async function createCanalPaquete(formData: FormData) {
  const session = await requireSession();
  if (!canManageCanalPaquetes(session)) {
    throw new AclError("Solo Master gestiona canales seguros");
  }

  const titulo = String(formData.get("titulo") ?? "").trim();
  const descripcion =
    String(formData.get("descripcion") ?? "").trim() || null;
  const destinatarioId = String(formData.get("destinatarioId") ?? "");
  const documentoIds = formData
    .getAll("documentoId")
    .map((v) => String(v))
    .filter(Boolean);

  if (!titulo || !destinatarioId) throw new Error("Datos incompletos");
  if (documentoIds.length === 0) {
    throw new Error("Selecciona al menos un documento");
  }

  const membresia = await prisma.membresia.findFirst({
    where: {
      empresaId: session.empresaId,
      usuarioId: destinatarioId,
      activa: true,
    },
  });
  if (!membresia || !isExternalRole(membresia.rol)) {
    throw new AclError("Destinatario debe ser externo de esta empresa");
  }

  const docs = await prisma.documento.findMany({
    where: { id: { in: documentoIds }, empresaId: session.empresaId },
  });
  if (docs.length !== documentoIds.length) {
    throw new AclError("Documento inválido");
  }

  const paquete = await prisma.canalPaquete.create({
    data: {
      empresaId: session.empresaId,
      titulo,
      descripcion,
      destinatarioId,
      creadoPorId: session.userId,
      estado: "ACTIVO",
      documentos: {
        create: documentoIds.map((documentoId) => ({ documentoId })),
      },
    },
  });

  // Mirror DocumentoAutorizacion so download ACL also works for package docs
  for (const documentoId of documentoIds) {
    await prisma.documentoAutorizacion.upsert({
      where: {
        documentoId_usuarioId: { documentoId, usuarioId: destinatarioId },
      },
      create: {
        empresaId: session.empresaId,
        documentoId,
        usuarioId: destinatarioId,
      },
      update: {},
    });
  }

  await auditCanal(session, paquete.id, "CREATED", titulo);
  revalidateFlujos(paquete.id);
}

export async function revokeCanalPaquete(formData: FormData) {
  const session = await requireSession();
  requireMaster(session);
  const id = String(formData.get("id") ?? "");
  const paquete = await prisma.canalPaquete.findUnique({ where: { id } });
  if (!paquete || paquete.empresaId !== session.empresaId) {
    throw new AclError("Paquete no encontrado");
  }
  await prisma.canalPaquete.update({
    where: { id },
    data: { estado: "REVOCADO" },
  });
  await auditCanal(session, id, "REVOKED");
  revalidateFlujos(id);
}

export async function markCanalPaqueteAccessed(paqueteId: string) {
  const session = await requireSession();
  const paquete = await prisma.canalPaquete.findUnique({
    where: { id: paqueteId },
  });
  if (!paquete || !canViewCanalPaquete(session, paquete)) {
    throw new AclError("Paquete no accesible");
  }
  if (isExternalRole(session.rol)) {
    await auditCanal(session, paqueteId, "ACCESSED");
  }
}
