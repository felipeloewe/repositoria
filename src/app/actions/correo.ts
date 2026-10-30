"use server";

import { revalidatePath } from "next/cache";
import {
  AclError,
  canUploadDocumento,
  requireDocumentoUpload,
} from "@/lib/acl";
import {
  createMailAccount,
  downloadMailAttachment,
  downloadThreadCertificate,
  generateThreadCertificate,
  getMailThread,
} from "@/lib/imap-mail";
import { prisma } from "@/lib/prisma";
import { requireSession } from "@/lib/session";
import { saveDocumentFile } from "@/lib/storage";

export type CorreoActionState = {
  error?: string;
  ok?: boolean;
  documentoId?: string;
  mode?: string;
};

function revalidateCorreo(threadId?: string) {
  revalidatePath("/correo");
  revalidatePath("/correo/configuracion");
  revalidatePath("/documentos");
  if (threadId) revalidatePath(`/correo/${threadId}`);
}

async function resolveSeccionForUpload(
  sessionEmpresaId: string,
  seccionId: string,
) {
  const seccion = await prisma.documentoSeccion.findUnique({
    where: { id: seccionId },
  });
  if (!seccion || seccion.empresaId !== sessionEmpresaId) {
    throw new AclError("Sección no válida");
  }
  return seccion;
}

async function createDocumentoFromBuffer(opts: {
  empresaId: string;
  userId: string;
  departamentoId: string;
  seccionId: string;
  sucursalId: string | null;
  titulo: string;
  nombreArchivo: string;
  mimeType: string;
  data: Buffer;
}) {
  const doc = await prisma.documento.create({
    data: {
      empresaId: opts.empresaId,
      departamentoId: opts.departamentoId,
      seccionId: opts.seccionId,
      sucursalId: opts.sucursalId,
      titulo: opts.titulo,
      nombreArchivo: opts.nombreArchivo,
      mimeType: opts.mimeType,
      tamanioBytes: opts.data.length,
      storageKey: "pending",
      subidoPorId: opts.userId,
    },
  });

  try {
    const saved = await saveDocumentFile({
      empresaId: opts.empresaId,
      documentoId: doc.id,
      nombreArchivo: opts.nombreArchivo,
      data: opts.data,
    });
    await prisma.documento.update({
      where: { id: doc.id },
      data: {
        storageKey: saved.storageKey,
        tamanioBytes: saved.tamanioBytes,
      },
    });
  } catch (err) {
    await prisma.documento.delete({ where: { id: doc.id } }).catch(() => undefined);
    throw err;
  }

  return doc;
}

export async function linkMailboxAction(
  _prev: CorreoActionState,
  formData: FormData,
): Promise<CorreoActionState> {
  const session = await requireSession();

  const email = String(formData.get("email") ?? "").trim().toLowerCase();
  const displayName = String(formData.get("displayName") ?? "").trim() || undefined;
  const providerRaw = String(formData.get("provider") ?? "IMAP");
  const provider = providerRaw === "GMAIL" ? "GMAIL" : "IMAP";
  const imapHost = String(formData.get("imapHost") ?? "").trim() || undefined;
  const imapPortRaw = String(formData.get("imapPort") ?? "").trim();
  const imapPort = imapPortRaw ? Number(imapPortRaw) : undefined;
  const imapUser = String(formData.get("imapUser") ?? "").trim() || undefined;
  const imapPassword =
    String(formData.get("imapPassword") ?? "").trim() || undefined;

  if (!email) return { error: "Correo requerido." };
  if (provider === "IMAP" && !imapHost) {
    return { error: "Host IMAP requerido (o usa modo mock sin servicio)." };
  }

  try {
    const result = await createMailAccount({
      usuarioId: session.userId,
      email,
      displayName,
      provider,
      imapHost,
      imapPort,
      imapUser: imapUser || email,
      imapPassword,
    });
    revalidateCorreo();
    return { ok: true, mode: result.mode };
  } catch (e) {
    return {
      error: e instanceof Error ? e.message : "No se pudo vincular la casilla.",
    };
  }
}

export async function saveAttachmentToRepoAction(
  _prev: CorreoActionState,
  formData: FormData,
): Promise<CorreoActionState> {
  const session = await requireSession();

  const attachmentId = String(formData.get("attachmentId") ?? "");
  const threadId = String(formData.get("threadId") ?? "");
  const seccionId = String(formData.get("seccionId") ?? "");
  const tituloRaw = String(formData.get("titulo") ?? "").trim();

  if (!attachmentId || !seccionId) {
    return { error: "Adjunto y sección son obligatorios." };
  }

  try {
    const seccion = await resolveSeccionForUpload(session.empresaId, seccionId);
    requireDocumentoUpload(session, {
      sucursalId: seccion.sucursalId,
      departamentoId: seccion.departamentoId,
    });

    // Ownership: thread must belong to this usuario in mail service / mock
    if (threadId) {
      const { data: thread } = await getMailThread(threadId, session.userId);
      if (thread.account.usuarioId !== session.userId) {
        throw new AclError("Este hilo no pertenece a tu casilla");
      }
    }

    const file = await downloadMailAttachment(attachmentId);
    const titulo =
      tituloRaw || `Adjunto correo · ${file.filename}`;

    const doc = await createDocumentoFromBuffer({
      empresaId: session.empresaId,
      userId: session.userId,
      departamentoId: seccion.departamentoId,
      seccionId: seccion.id,
      sucursalId: seccion.sucursalId,
      titulo,
      nombreArchivo: file.filename,
      mimeType: file.mimeType,
      data: file.bytes,
    });

    revalidateCorreo(threadId || undefined);
    return { ok: true, documentoId: doc.id, mode: file.mode };
  } catch (e) {
    return {
      error:
        e instanceof AclError
          ? e.message
          : e instanceof Error
            ? e.message
            : "No se pudo guardar el adjunto.",
    };
  }
}

export async function saveCertificateToRepoAction(
  _prev: CorreoActionState,
  formData: FormData,
): Promise<CorreoActionState> {
  const session = await requireSession();

  const threadId = String(formData.get("threadId") ?? "");
  const seccionId = String(formData.get("seccionId") ?? "");
  const tituloRaw = String(formData.get("titulo") ?? "").trim();

  if (!threadId || !seccionId) {
    return { error: "Hilo y sección son obligatorios." };
  }

  try {
    const seccion = await resolveSeccionForUpload(session.empresaId, seccionId);
    requireDocumentoUpload(session, {
      sucursalId: seccion.sucursalId,
      departamentoId: seccion.departamentoId,
    });

    const { data: thread } = await getMailThread(threadId, session.userId);
    if (thread.account.usuarioId !== session.userId) {
      throw new AclError("Este hilo no pertenece a tu casilla");
    }

    // Ensure PDF exists on mail service (no-op push; we ingest ourselves)
    await generateThreadCertificate(threadId, {
      pushToRepositoria: false,
      suggestedDepartamentoId: seccion.departamentoId,
    });

    const file = await downloadThreadCertificate(threadId);
    const titulo =
      tituloRaw || `Certificado de hilo · ${thread.subject}`.slice(0, 180);

    const doc = await createDocumentoFromBuffer({
      empresaId: session.empresaId,
      userId: session.userId,
      departamentoId: seccion.departamentoId,
      seccionId: seccion.id,
      sucursalId: seccion.sucursalId,
      titulo,
      nombreArchivo: file.filename,
      mimeType: "application/pdf",
      data: file.bytes,
    });

    revalidateCorreo(threadId);
    return { ok: true, documentoId: doc.id, mode: file.mode };
  } catch (e) {
    return {
      error:
        e instanceof AclError
          ? e.message
          : e instanceof Error
            ? e.message
            : "No se pudo guardar el certificado.",
    };
  }
}

/** Sections the current membership may upload into (Master / Jefe). */
export async function listUploadableSecciones() {
  const session = await requireSession();
  const secciones = await prisma.documentoSeccion.findMany({
    where: { empresaId: session.empresaId },
    include: { departamento: true, sucursal: true },
    orderBy: [
      { departamento: { nombre: "asc" } },
      { sucursalId: "asc" },
      { nombre: "asc" },
    ],
  });

  return secciones
    .filter((s) =>
      canUploadDocumento(session, {
        sucursalId: s.sucursalId,
        departamentoId: s.departamentoId,
      }),
    )
    .map((s) => ({
      id: s.id,
      label: s.sucursal
        ? `${s.departamento.nombre} · ${s.sucursal.nombre} · ${s.nombre}`
        : `${s.departamento.nombre} · Nivel depto · ${s.nombre}`,
      departamentoId: s.departamentoId,
      sucursalId: s.sucursalId,
    }));
}
