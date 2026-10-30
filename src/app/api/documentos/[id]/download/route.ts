import { NextRequest, NextResponse } from "next/server";
import { canViewDocumento } from "@/lib/acl";
import { prisma } from "@/lib/prisma";
import { getSession } from "@/lib/session";
import { readDocumentFile } from "@/lib/storage";

export const instant = false;

type Params = { params: Promise<{ id: string }> };

export async function GET(_request: NextRequest, { params }: Params) {
  const session = await getSession();
  if (!session) {
    return NextResponse.json({ error: "No autenticado" }, { status: 401 });
  }

  const { id } = await params;
  const doc = await prisma.documento.findUnique({
    where: { id },
    include: { autorizaciones: true },
  });

  if (!doc || doc.empresaId !== session.empresaId) {
    return NextResponse.json({ error: "No encontrado" }, { status: 404 });
  }

  const allowed = canViewDocumento(session, {
    empresaId: doc.empresaId,
    departamentoId: doc.departamentoId,
    sucursalId: doc.sucursalId,
    autorizadoUsuarioIds: doc.autorizaciones.map((a) => a.usuarioId),
  });
  if (!allowed) {
    return NextResponse.json({ error: "Sin acceso" }, { status: 403 });
  }

  try {
    const data = await readDocumentFile(doc.storageKey);
    return new NextResponse(new Uint8Array(data), {
      status: 200,
      headers: {
        "Content-Type": doc.mimeType,
        "Content-Length": String(data.length),
        "Content-Disposition": `attachment; filename="${encodeURIComponent(doc.nombreArchivo)}"`,
        "Cache-Control": "private, no-store",
      },
    });
  } catch {
    return NextResponse.json(
      { error: "Archivo no disponible en storage" },
      { status: 404 },
    );
  }
}
