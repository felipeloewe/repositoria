"use server";

import { revalidatePath } from "next/cache";
import {
  AclError,
  canDeleteDocumento,
  requireDocumentoUpload,
  requireMaster,
} from "@/lib/acl";
import { prisma } from "@/lib/prisma";
import { requireSession } from "@/lib/session";
import { deleteDocumentFile, saveDocumentFile } from "@/lib/storage";

const MAX_BYTES = 15 * 1024 * 1024;

function revalidateDocs() {
  revalidatePath("/documentos");
  revalidatePath("/dashboard");
}

export type UploadState = { error?: string; ok?: boolean };

export async function uploadDocumento(
  _prev: UploadState,
  formData: FormData,
): Promise<UploadState> {
  const session = await requireSession();

  const seccionId = String(formData.get("seccionId") ?? "");
  const titulo = String(formData.get("titulo") ?? "").trim();
  const file = formData.get("file");

  if (!seccionId || !titulo) {
    return { error: "Título y sección son obligatorios." };
  }
  if (!(file instanceof File) || file.size === 0) {
    return { error: "Selecciona un archivo." };
  }
  if (file.size > MAX_BYTES) {
    return { error: "El archivo supera el límite de 15 MB." };
  }

  const seccion = await prisma.documentoSeccion.findUnique({
    where: { id: seccionId },
  });
  if (!seccion || seccion.empresaId !== session.empresaId) {
    return { error: "Sección no válida." };
  }

  try {
    requireDocumentoUpload(session, {
      sucursalId: seccion.sucursalId,
      departamentoId: seccion.departamentoId,
    });
  } catch (e) {
    return {
      error: e instanceof AclError ? e.message : "Sin permiso de carga.",
    };
  }

  const buffer = Buffer.from(await file.arrayBuffer());

  const doc = await prisma.documento.create({
    data: {
      empresaId: session.empresaId,
      departamentoId: seccion.departamentoId,
      seccionId: seccion.id,
      sucursalId: seccion.sucursalId,
      titulo,
      nombreArchivo: file.name,
      mimeType: file.type || "application/octet-stream",
      tamanioBytes: buffer.length,
      storageKey: "pending",
      subidoPorId: session.userId,
    },
  });

  try {
    const { storageKey, tamanioBytes } = await saveDocumentFile({
      empresaId: session.empresaId,
      documentoId: doc.id,
      nombreArchivo: file.name,
      data: buffer,
    });
    await prisma.documento.update({
      where: { id: doc.id },
      data: { storageKey, tamanioBytes },
    });
  } catch (err) {
    await prisma.documento.delete({ where: { id: doc.id } }).catch(() => undefined);
    console.error(err);
    return { error: "No se pudo guardar el archivo." };
  }

  revalidateDocs();
  return { ok: true };
}

export async function deleteDocumento(formData: FormData) {
  const session = await requireSession();
  const id = String(formData.get("id") ?? "");
  const doc = await prisma.documento.findUnique({
    where: { id },
    include: { autorizaciones: true },
  });
  if (!doc || doc.empresaId !== session.empresaId) {
    throw new AclError("Documento no encontrado");
  }

  const acl = {
    empresaId: doc.empresaId,
    departamentoId: doc.departamentoId,
    sucursalId: doc.sucursalId,
    autorizadoUsuarioIds: doc.autorizaciones.map((a) => a.usuarioId),
    subidoPorId: doc.subidoPorId,
  };

  if (!canDeleteDocumento(session, acl)) {
    throw new AclError("Sin permiso para eliminar");
  }

  await prisma.documento.delete({ where: { id } });
  await deleteDocumentFile(doc.storageKey).catch(() => undefined);
  revalidateDocs();
}

export async function authorizeDocumento(formData: FormData) {
  const session = await requireSession();
  requireMaster(session);
  const documentoId = String(formData.get("documentoId") ?? "");
  const usuarioId = String(formData.get("usuarioId") ?? "");

  const doc = await prisma.documento.findUnique({ where: { id: documentoId } });
  if (!doc || doc.empresaId !== session.empresaId) {
    throw new AclError("Documento no encontrado");
  }

  await prisma.documentoAutorizacion.upsert({
    where: {
      documentoId_usuarioId: { documentoId, usuarioId },
    },
    create: {
      empresaId: session.empresaId,
      documentoId,
      usuarioId,
    },
    update: {},
  });
  revalidateDocs();
}
