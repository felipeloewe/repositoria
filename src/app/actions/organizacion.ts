"use server";

import { revalidatePath } from "next/cache";
import { prisma } from "@/lib/prisma";
import {
  AclError,
  requireMaster,
  requireOrgManager,
  requireSucursalAccess,
  requireSucursalManage,
  roleNeedsSucursal,
} from "@/lib/acl";
import { isRole } from "@/lib/roles";
import { requireSession } from "@/lib/session";
import type { Rol } from "@prisma/client";

function slugify(value: string): string {
  return value
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-|-$/g, "")
    .slice(0, 48);
}

function revalidateOrg() {
  revalidatePath("/organizacion");
  revalidatePath("/organizacion/empresa");
  revalidatePath("/organizacion/sucursales");
  revalidatePath("/organizacion/departamentos");
  revalidatePath("/organizacion/miembros");
  revalidatePath("/dashboard");
  revalidatePath("/expedientes");
  revalidatePath("/login");
}

export async function updateEmpresa(formData: FormData) {
  const session = await requireSession();
  requireOrgManager(session);

  const nombre = String(formData.get("nombre") ?? "").trim();
  const slugRaw = String(formData.get("slug") ?? "").trim();
  const rut = String(formData.get("rut") ?? "").trim() || null;
  if (!nombre) throw new AclError("Nombre requerido");

  const slug = slugify(slugRaw || nombre);
  await prisma.empresa.update({
    where: { id: session.empresaId },
    data: { nombre, slug, rut },
  });
  revalidateOrg();
}

export async function createSucursal(formData: FormData) {
  const session = await requireSession();
  requireOrgManager(session);

  const nombre = String(formData.get("nombre") ?? "").trim();
  const codigo = String(formData.get("codigo") ?? "").trim() || null;
  if (!nombre) throw new Error("Nombre requerido");

  await prisma.sucursal.create({
    data: {
      empresaId: session.empresaId,
      nombre,
      codigo,
    },
  });
  revalidateOrg();
}

export async function updateSucursal(formData: FormData) {
  const session = await requireSession();
  const id = String(formData.get("id") ?? "");
  const nombre = String(formData.get("nombre") ?? "").trim();
  const codigo = String(formData.get("codigo") ?? "").trim() || null;
  const activa = String(formData.get("activa") ?? "true") === "true";

  const existing = await prisma.sucursal.findUnique({ where: { id } });
  if (!existing || existing.empresaId !== session.empresaId) {
    throw new AclError("Sucursal no encontrada");
  }
  requireSucursalManage(session, id);
  // Jefe can rename? Assignment says Master manages tenant; Jefe only own branch access.
  // Limit write of sucursal metadata to Master; Jefe manages departamentos only.
  requireOrgManager(session);

  if (!nombre) throw new Error("Nombre requerido");

  await prisma.sucursal.update({
    where: { id },
    data: { nombre, codigo, activa },
  });
  revalidateOrg();
}

export async function deleteSucursal(formData: FormData) {
  const session = await requireSession();
  requireOrgManager(session);
  const id = String(formData.get("id") ?? "");
  const existing = await prisma.sucursal.findUnique({ where: { id } });
  if (!existing || existing.empresaId !== session.empresaId) {
    throw new AclError("Sucursal no encontrada");
  }
  await prisma.sucursal.delete({ where: { id } });
  revalidateOrg();
}

export async function createDepartamento(formData: FormData) {
  const session = await requireSession();
  const sucursalId = String(formData.get("sucursalId") ?? "");
  const nombre = String(formData.get("nombre") ?? "").trim();
  if (!nombre || !sucursalId) throw new Error("Datos incompletos");

  const sucursal = await prisma.sucursal.findUnique({ where: { id: sucursalId } });
  if (!sucursal || sucursal.empresaId !== session.empresaId) {
    throw new AclError("Sucursal no encontrada");
  }
  requireSucursalManage(session, sucursalId);

  await prisma.departamento.create({
    data: {
      empresaId: session.empresaId,
      sucursalId,
      nombre,
    },
  });
  revalidateOrg();
}

export async function updateDepartamento(formData: FormData) {
  const session = await requireSession();
  const id = String(formData.get("id") ?? "");
  const nombre = String(formData.get("nombre") ?? "").trim();
  if (!nombre) throw new Error("Nombre requerido");

  const existing = await prisma.departamento.findUnique({ where: { id } });
  if (!existing || existing.empresaId !== session.empresaId) {
    throw new AclError("Departamento no encontrado");
  }
  requireSucursalManage(session, existing.sucursalId);

  await prisma.departamento.update({
    where: { id },
    data: { nombre },
  });
  revalidateOrg();
}

export async function deleteDepartamento(formData: FormData) {
  const session = await requireSession();
  const id = String(formData.get("id") ?? "");
  const existing = await prisma.departamento.findUnique({ where: { id } });
  if (!existing || existing.empresaId !== session.empresaId) {
    throw new AclError("Departamento no encontrado");
  }
  requireSucursalManage(session, existing.sucursalId);
  await prisma.departamento.delete({ where: { id } });
  revalidateOrg();
}

export async function createMiembro(formData: FormData) {
  const session = await requireSession();
  requireMaster(session);

  const email = String(formData.get("email") ?? "").trim().toLowerCase();
  const nombre = String(formData.get("nombre") ?? "").trim();
  const rolRaw = String(formData.get("rol") ?? "");
  const sucursalIdRaw = String(formData.get("sucursalId") ?? "").trim();
  const sucursalId = sucursalIdRaw || null;

  if (!email || !nombre || !isRole(rolRaw)) {
    throw new Error("Datos de miembro incompletos");
  }
  const rol = rolRaw as Rol;

  if (roleNeedsSucursal(rol) && !sucursalId) {
    throw new Error("Jefe de Sucursal requiere sucursal");
  }
  if (sucursalId) {
    requireSucursalAccess(session, sucursalId);
    const sucursal = await prisma.sucursal.findFirst({
      where: { id: sucursalId, empresaId: session.empresaId },
    });
    if (!sucursal) throw new AclError("Sucursal inválida");
  }

  const usuario = await prisma.usuario.upsert({
    where: { email },
    create: { email, nombre },
    update: { nombre, activo: true },
  });

  // One Membresia per (usuarioId, empresaId) — upsert role/scope for that pair.
  await prisma.membresia.upsert({
    where: {
      usuarioId_empresaId: {
        usuarioId: usuario.id,
        empresaId: session.empresaId,
      },
    },
    create: {
      empresaId: session.empresaId,
      usuarioId: usuario.id,
      rol,
      sucursalId: roleNeedsSucursal(rol) || sucursalId ? sucursalId : null,
    },
    update: {
      rol,
      sucursalId: roleNeedsSucursal(rol) || sucursalId ? sucursalId : null,
      activa: true,
    },
  });
  revalidateOrg();
}

export async function updateMiembro(formData: FormData) {
  const session = await requireSession();
  requireMaster(session);

  const id = String(formData.get("id") ?? "");
  const rolRaw = String(formData.get("rol") ?? "");
  const sucursalIdRaw = String(formData.get("sucursalId") ?? "").trim();
  const sucursalId = sucursalIdRaw || null;
  const activa = String(formData.get("activa") ?? "true") === "true";

  if (!isRole(rolRaw)) throw new Error("Rol inválido");
  const rol = rolRaw as Rol;

  const existing = await prisma.membresia.findUnique({ where: { id } });
  if (!existing || existing.empresaId !== session.empresaId) {
    throw new AclError("Membresía no encontrada");
  }
  if (roleNeedsSucursal(rol) && !sucursalId) {
    throw new Error("Jefe de Sucursal requiere sucursal");
  }

  await prisma.membresia.update({
    where: { id },
    data: {
      rol,
      sucursalId,
      activa,
    },
  });
  revalidateOrg();
}

export async function deleteMiembro(formData: FormData) {
  const session = await requireSession();
  requireMaster(session);
  const id = String(formData.get("id") ?? "");
  const existing = await prisma.membresia.findUnique({ where: { id } });
  if (!existing || existing.empresaId !== session.empresaId) {
    throw new AclError("Membresía no encontrada");
  }
  if (existing.id === session.membresiaId) {
    throw new Error("No puedes eliminar tu propia membresía activa");
  }
  await prisma.membresia.delete({ where: { id } });
  revalidateOrg();
}

export async function authorizeExpediente(formData: FormData) {
  const session = await requireSession();
  requireMaster(session);
  const expedienteId = String(formData.get("expedienteId") ?? "");
  const usuarioId = String(formData.get("usuarioId") ?? "");

  const expediente = await prisma.expedienteStub.findUnique({
    where: { id: expedienteId },
  });
  if (!expediente || expediente.empresaId !== session.empresaId) {
    throw new AclError("Expediente no encontrado");
  }

  await prisma.expedienteAutorizacion.upsert({
    where: {
      expedienteId_usuarioId: { expedienteId, usuarioId },
    },
    create: {
      empresaId: session.empresaId,
      expedienteId,
      usuarioId,
    },
    update: {},
  });
  revalidateOrg();
}
