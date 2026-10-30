import { PrismaClient, type Rol } from "@prisma/client";
import { createHash } from "crypto";
import { mkdirSync, writeFileSync, rmSync } from "fs";
import path from "path";

const prisma = new PrismaClient();

function storageRoot(): string {
  return (
    process.env.STORAGE_ROOT?.trim() ||
    path.join(process.cwd(), "storage", "documents")
  );
}

function writeSeedFile(
  empresaId: string,
  documentoId: string,
  filename: string,
  contents: string,
): { storageKey: string; tamanioBytes: number } {
  const storageKey = path.join(empresaId, documentoId, filename);
  const full = path.join(storageRoot(), storageKey);
  mkdirSync(path.dirname(full), { recursive: true });
  writeFileSync(full, contents, "utf8");
  return { storageKey, tamanioBytes: Buffer.byteLength(contents, "utf8") };
}

function hashPassword(password: string): string {
  return createHash("sha256")
    .update(`repositoria:v1:${password}`)
    .digest("hex");
}

const DEMO_PASSWORD = "demo123";

const IDS = {
  empresa: "emp_demo_repositoria",
  empresaBeta: "emp_demo_beta",
  depts: {
    admin: "dep_admin",
    rrhh: "dep_rrhh",
    prev: "dep_prev",
    adminBeta: "dep_admin_beta",
  },
  sucursalNorte: "suc_norte",
  sucursalSur: "suc_sur",
  sucursalBeta: "suc_beta",
  users: {
    master: "usr_master",
    jefeNorte: "usr_jefe_norte",
    jefeSur: "usr_jefe_sur",
    contador: "usr_contador",
    abogado: "usr_abogado",
    prevencionista: "usr_prevencionista",
    multi: "usr_multi",
  },
  membresias: {
    master: "mem_master",
    jefeNorte: "mem_jefe_norte",
    jefeSur: "mem_jefe_sur",
    contador: "mem_contador",
    abogado: "mem_abogado",
    prevencionista: "mem_prevencionista",
    multiDemo: "mem_multi_demo",
    multiBeta: "mem_multi_beta",
  },
  expedientes: {
    finiquitoNorte: "exp_finiquito_norte",
    liquidacionSur: "exp_liquidacion_sur",
    contratoCorp: "exp_contrato_corp",
    reclamoNorte: "exp_reclamo_norte",
  },
} as const;

async function main() {
  console.log("Seeding Repositoria demo data…");

  await prisma.fiscalizacionAuditoria.deleteMany();
  await prisma.fiscalizacionDocumento.deleteMany();
  await prisma.fiscalizacionSolicitud.deleteMany();
  await prisma.canalPaqueteAuditoria.deleteMany();
  await prisma.canalPaqueteDocumento.deleteMany();
  await prisma.canalPaquete.deleteMany();
  await prisma.documentoAutorizacion.deleteMany();
  await prisma.documento.deleteMany();
  await prisma.documentoSeccion.deleteMany();
  await prisma.expedienteAutorizacion.deleteMany();
  await prisma.expedienteStub.deleteMany();
  await prisma.actividad.deleteMany();
  await prisma.temaInteres.deleteMany();
  await prisma.membresia.deleteMany();
  await prisma.sucursal.deleteMany();
  await prisma.departamento.deleteMany();
  await prisma.usuario.deleteMany();
  await prisma.empresa.deleteMany();

  try {
    rmSync(storageRoot(), { recursive: true, force: true });
  } catch {
    /* empty */
  }

  const passwordHash = hashPassword(DEMO_PASSWORD);

  const empresa = await prisma.empresa.create({
    data: {
      id: IDS.empresa,
      nombre: "Empresa Demo",
      slug: "empresa-demo",
      rut: "76.111.111-1",
      activa: true,
    },
  });

  const empresaBeta = await prisma.empresa.create({
    data: {
      id: IDS.empresaBeta,
      nombre: "Empresa Beta",
      slug: "empresa-beta",
      rut: "76.222.222-2",
      activa: true,
    },
  });

  // Empresa → Departamentos → Sucursales
  await prisma.departamento.createMany({
    data: [
      {
        id: IDS.depts.admin,
        empresaId: empresa.id,
        nombre: "Administración",
      },
      {
        id: IDS.depts.rrhh,
        empresaId: empresa.id,
        nombre: "RR.HH.",
      },
      {
        id: IDS.depts.prev,
        empresaId: empresa.id,
        nombre: "Prevención",
      },
      {
        id: IDS.depts.adminBeta,
        empresaId: empresaBeta.id,
        nombre: "Administración",
      },
    ],
  });

  await prisma.actividad.createMany({
    data: [
      {
        id: "act_admin_contabilidad",
        empresaId: empresa.id,
        departamentoId: IDS.depts.admin,
        nombre: "Contabilidad",
      },
      {
        id: "act_admin_tesoreria",
        empresaId: empresa.id,
        departamentoId: IDS.depts.admin,
        nombre: "Tesorería",
      },
      {
        id: "act_rrhh_seleccion",
        empresaId: empresa.id,
        departamentoId: IDS.depts.rrhh,
        nombre: "Selección",
      },
      {
        id: "act_prev_fiscalizacion",
        empresaId: empresa.id,
        departamentoId: IDS.depts.prev,
        nombre: "Fiscalización",
      },
    ],
  });

  await prisma.temaInteres.createMany({
    data: [
      {
        id: "tema_admin_contratos",
        empresaId: empresa.id,
        departamentoId: IDS.depts.admin,
        nombre: "Contratos marco",
      },
      {
        id: "tema_admin_politicas",
        empresaId: empresa.id,
        departamentoId: IDS.depts.admin,
        nombre: "Políticas",
      },
      {
        id: "tema_rrhh_finiquitos",
        empresaId: empresa.id,
        departamentoId: IDS.depts.rrhh,
        nombre: "Finiquitos",
      },
      {
        id: "tema_rrhh_liquidaciones",
        empresaId: empresa.id,
        departamentoId: IDS.depts.rrhh,
        nombre: "Liquidaciones",
      },
      {
        id: "tema_prev_reclamos",
        empresaId: empresa.id,
        departamentoId: IDS.depts.prev,
        nombre: "Reclamos",
      },
    ],
  });

  const norte = await prisma.sucursal.create({
    data: {
      id: IDS.sucursalNorte,
      empresaId: empresa.id,
      departamentoId: IDS.depts.admin,
      nombre: "Sucursal Norte",
      codigo: "NTE",
    },
  });

  const sur = await prisma.sucursal.create({
    data: {
      id: IDS.sucursalSur,
      empresaId: empresa.id,
      departamentoId: IDS.depts.admin,
      nombre: "Sucursal Sur",
      codigo: "SUR",
    },
  });

  const betaSuc = await prisma.sucursal.create({
    data: {
      id: IDS.sucursalBeta,
      empresaId: empresaBeta.id,
      departamentoId: IDS.depts.adminBeta,
      nombre: "Casa Matriz",
      codigo: "BETA",
    },
  });

  const users: Array<{
    id: string;
    email: string;
    nombre: string;
  }> = [
    {
      id: IDS.users.master,
      email: "admin@repositoria.local",
      nombre: "Administrador Demo",
    },
    {
      id: IDS.users.jefeNorte,
      email: "jefe.norte@repositoria.local",
      nombre: "Jefe Norte",
    },
    {
      id: IDS.users.jefeSur,
      email: "jefe.sur@repositoria.local",
      nombre: "Jefe Sur",
    },
    {
      id: IDS.users.contador,
      email: "contador@repositoria.local",
      nombre: "Contador Externo",
    },
    {
      id: IDS.users.abogado,
      email: "abogado@repositoria.local",
      nombre: "Abogado Externo",
    },
    {
      id: IDS.users.prevencionista,
      email: "prevencionista@repositoria.local",
      nombre: "Prevencionista Externo",
    },
    {
      id: IDS.users.multi,
      email: "multi@repositoria.local",
      nombre: "Usuario Multi-empresa",
    },
  ];

  let rutSeq = 1;
  for (const u of users) {
    await prisma.usuario.create({
      data: {
        id: u.id,
        email: u.email,
        nombre: u.nombre,
        rut: `11.111.11${rutSeq}-${rutSeq}`,
        passwordHash,
      },
    });
    rutSeq += 1;
  }

  const memberships: Array<{
    id: string;
    empresaId: string;
    usuarioId: string;
    rol: Rol;
    sucursalId: string | null;
  }> = [
    {
      id: IDS.membresias.master,
      empresaId: empresa.id,
      usuarioId: IDS.users.master,
      rol: "ADMINISTRADOR_MASTER",
      sucursalId: null,
    },
    {
      id: IDS.membresias.jefeNorte,
      empresaId: empresa.id,
      usuarioId: IDS.users.jefeNorte,
      rol: "JEFE_SUCURSAL",
      sucursalId: norte.id,
    },
    {
      id: IDS.membresias.jefeSur,
      empresaId: empresa.id,
      usuarioId: IDS.users.jefeSur,
      rol: "JEFE_SUCURSAL",
      sucursalId: sur.id,
    },
    {
      id: IDS.membresias.contador,
      empresaId: empresa.id,
      usuarioId: IDS.users.contador,
      rol: "CONTADOR",
      sucursalId: null,
    },
    {
      id: IDS.membresias.abogado,
      empresaId: empresa.id,
      usuarioId: IDS.users.abogado,
      rol: "ABOGADO",
      sucursalId: null,
    },
    {
      id: IDS.membresias.prevencionista,
      empresaId: empresa.id,
      usuarioId: IDS.users.prevencionista,
      rol: "PREVENCIONISTA",
      sucursalId: null,
    },
    {
      id: IDS.membresias.multiDemo,
      empresaId: empresa.id,
      usuarioId: IDS.users.multi,
      rol: "CONTADOR",
      sucursalId: null,
    },
    {
      id: IDS.membresias.multiBeta,
      empresaId: empresaBeta.id,
      usuarioId: IDS.users.multi,
      rol: "ADMINISTRADOR_MASTER",
      sucursalId: null,
    },
  ];

  for (const m of memberships) {
    await prisma.membresia.create({ data: m });
  }

  const finiquito = await prisma.expedienteStub.create({
    data: {
      id: IDS.expedientes.finiquitoNorte,
      empresaId: empresa.id,
      sucursalId: norte.id,
      titulo: "Finiquito — Sucursal Norte",
      tipo: "FINIQUITO",
    },
  });
  const liquidacion = await prisma.expedienteStub.create({
    data: {
      id: IDS.expedientes.liquidacionSur,
      empresaId: empresa.id,
      sucursalId: sur.id,
      titulo: "Liquidaciones Q3 — Sucursal Sur",
      tipo: "LIQUIDACION",
    },
  });
  await prisma.expedienteStub.create({
    data: {
      id: IDS.expedientes.contratoCorp,
      empresaId: empresa.id,
      sucursalId: null,
      titulo: "Contrato marco corporativo",
      tipo: "CONTRATO",
    },
  });
  const reclamo = await prisma.expedienteStub.create({
    data: {
      id: IDS.expedientes.reclamoNorte,
      empresaId: empresa.id,
      sucursalId: norte.id,
      titulo: "Reclamo laboral — Norte",
      tipo: "RECLAMO",
    },
  });

  await prisma.expedienteAutorizacion.createMany({
    data: [
      {
        empresaId: empresa.id,
        expedienteId: finiquito.id,
        usuarioId: IDS.users.contador,
      },
      {
        empresaId: empresa.id,
        expedienteId: liquidacion.id,
        usuarioId: IDS.users.contador,
      },
      {
        empresaId: empresa.id,
        expedienteId: reclamo.id,
        usuarioId: IDS.users.abogado,
      },
      {
        empresaId: empresa.id,
        expedienteId: finiquito.id,
        usuarioId: IDS.users.prevencionista,
      },
      {
        empresaId: empresa.id,
        expedienteId: finiquito.id,
        usuarioId: IDS.users.multi,
      },
    ],
  });

  // Documentos ligados a Departamentos (+ sucursal opcional)
  const secAdminContratos = await prisma.documentoSeccion.create({
    data: {
      id: "sec_admin_contratos",
      empresaId: empresa.id,
      departamentoId: IDS.depts.admin,
      sucursalId: null,
      nombre: "Contratos",
      codigo: "CONTRATOS",
    },
  });
  const secAdminNorteLiq = await prisma.documentoSeccion.create({
    data: {
      id: "sec_admin_norte_liquidaciones",
      empresaId: empresa.id,
      departamentoId: IDS.depts.admin,
      sucursalId: norte.id,
      nombre: "Liquidaciones",
      codigo: "LIQUIDACIONES",
    },
  });
  const secAdminSurContratos = await prisma.documentoSeccion.create({
    data: {
      id: "sec_admin_sur_contratos",
      empresaId: empresa.id,
      departamentoId: IDS.depts.admin,
      sucursalId: sur.id,
      nombre: "Contratos",
      codigo: "CONTRATOS",
    },
  });
  await prisma.documentoSeccion.create({
    data: {
      id: "sec_admin_norte_general",
      empresaId: empresa.id,
      departamentoId: IDS.depts.admin,
      sucursalId: norte.id,
      nombre: "General",
      codigo: "GENERAL",
    },
  });
  await prisma.documentoSeccion.create({
    data: {
      id: "sec_rrhh_liquidaciones",
      empresaId: empresa.id,
      departamentoId: IDS.depts.rrhh,
      sucursalId: null,
      nombre: "Liquidaciones",
      codigo: "LIQUIDACIONES",
    },
  });

  const docs = [
    {
      id: "doc_politica_corp",
      departamentoId: IDS.depts.admin,
      seccionId: secAdminContratos.id,
      sucursalId: null as string | null,
      titulo: "Política de contratos — Administración",
      filename: "politica-contratos.txt",
      body: "Documento a nivel departamento Administración — visible a Master y Jefes de sucursales de Admin.\n",
      authorize: [] as string[],
    },
    {
      id: "doc_liq_norte",
      departamentoId: IDS.depts.admin,
      seccionId: secAdminNorteLiq.id,
      sucursalId: norte.id,
      titulo: "Liquidaciones Norte — marzo",
      filename: "liquidaciones-norte-marzo.txt",
      body: "Liquidaciones Sucursal Norte (depto Administración) — ejemplo seed.\n",
      authorize: [IDS.users.contador, IDS.users.multi],
    },
    {
      id: "doc_contrato_sur",
      departamentoId: IDS.depts.admin,
      seccionId: secAdminSurContratos.id,
      sucursalId: sur.id,
      titulo: "Contrato proveedor Sur",
      filename: "contrato-sur.txt",
      body: "Contrato Sucursal Sur (depto Administración) — ejemplo seed.\n",
      authorize: [IDS.users.abogado],
    },
  ];

  for (const d of docs) {
    const file = writeSeedFile(empresa.id, d.id, d.filename, d.body);
    await prisma.documento.create({
      data: {
        id: d.id,
        empresaId: empresa.id,
        departamentoId: d.departamentoId,
        seccionId: d.seccionId,
        sucursalId: d.sucursalId,
        titulo: d.titulo,
        nombreArchivo: d.filename,
        mimeType: "text/plain",
        tamanioBytes: file.tamanioBytes,
        storageKey: file.storageKey,
        subidoPorId: IDS.users.master,
      },
    });
    for (const uid of d.authorize) {
      await prisma.documentoAutorizacion.create({
        data: {
          empresaId: empresa.id,
          documentoId: d.id,
          usuarioId: uid,
        },
      });
    }
  }

  // --- Fase 4: fiscalización + canal seguro ---
  const fisPendiente = await prisma.fiscalizacionSolicitud.create({
    data: {
      id: "fis_liq_norte_pendiente",
      empresaId: empresa.id,
      departamentoId: IDS.depts.admin,
      sucursalId: norte.id,
      tipo: "LIQUIDACION",
      titulo: "Envío liquidaciones Norte — marzo (fiscalización)",
      motivo: "Requiere OK Master antes de remitir a fiscalización / contador.",
      estado: "PENDIENTE",
      solicitadoPorId: IDS.users.jefeNorte,
      documentos: {
        create: [{ documentoId: "doc_liq_norte" }],
      },
    },
  });
  await prisma.fiscalizacionAuditoria.createMany({
    data: [
      {
        empresaId: empresa.id,
        solicitudId: fisPendiente.id,
        actorId: IDS.users.jefeNorte,
        accion: "CREATED",
        detalle: "Solicitud demo pendiente",
      },
      {
        empresaId: empresa.id,
        solicitudId: fisPendiente.id,
        actorId: IDS.users.jefeNorte,
        accion: "SUBMITTED",
      },
    ],
  });

  const fisAprobada = await prisma.fiscalizacionSolicitud.create({
    data: {
      id: "fis_contrato_sur_aprobada",
      empresaId: empresa.id,
      departamentoId: IDS.depts.admin,
      sucursalId: sur.id,
      tipo: "CONTRATO",
      titulo: "Exportar contrato proveedor Sur",
      motivo: "Envío a equipo legal externo.",
      estado: "APROBADA",
      solicitadoPorId: IDS.users.master,
      revisadoPorId: IDS.users.master,
      revisadoAt: new Date("2026-03-20T12:00:00Z"),
      comentarioRevision: "Autorizado para exportación.",
      documentos: {
        create: [{ documentoId: "doc_contrato_sur" }],
      },
    },
  });
  await prisma.fiscalizacionAuditoria.createMany({
    data: [
      {
        empresaId: empresa.id,
        solicitudId: fisAprobada.id,
        actorId: IDS.users.master,
        accion: "CREATED",
      },
      {
        empresaId: empresa.id,
        solicitudId: fisAprobada.id,
        actorId: IDS.users.master,
        accion: "SUBMITTED",
      },
      {
        empresaId: empresa.id,
        solicitudId: fisAprobada.id,
        actorId: IDS.users.master,
        accion: "APPROVED",
        detalle: "Autorizado para exportación.",
      },
    ],
  });

  const paqueteContador = await prisma.canalPaquete.create({
    data: {
      id: "canal_pkg_contador_liq",
      empresaId: empresa.id,
      titulo: "Paquete liquidaciones — Contador",
      descripcion: "Canal seguro: liquidaciones Norte autorizadas para revisión.",
      destinatarioId: IDS.users.contador,
      creadoPorId: IDS.users.master,
      estado: "ACTIVO",
      documentos: {
        create: [{ documentoId: "doc_liq_norte" }],
      },
    },
  });
  await prisma.canalPaqueteAuditoria.create({
    data: {
      empresaId: empresa.id,
      paqueteId: paqueteContador.id,
      actorId: IDS.users.master,
      accion: "CREATED",
      detalle: "Paquete demo para contador externo",
    },
  });

  const paqueteAbogado = await prisma.canalPaquete.create({
    data: {
      id: "canal_pkg_abogado_contrato",
      empresaId: empresa.id,
      titulo: "Paquete contratos — Abogado",
      descripcion: "Contrato Sur para revisión legal.",
      destinatarioId: IDS.users.abogado,
      creadoPorId: IDS.users.master,
      estado: "ACTIVO",
      documentos: {
        create: [{ documentoId: "doc_contrato_sur" }],
      },
    },
  });
  await prisma.canalPaqueteAuditoria.create({
    data: {
      empresaId: empresa.id,
      paqueteId: paqueteAbogado.id,
      actorId: IDS.users.master,
      accion: "CREATED",
    },
  });

  console.log("Seed OK");
  console.log(`  Empresas: ${empresa.nombre}, ${empresaBeta.nombre}`);
  console.log(
    `  Jerarquía: Empresa → Departamentos → Sucursales (${norte.nombre}, ${sur.nombre}, ${betaSuc.nombre})`,
  );
  console.log(`  Contraseña demo: ${DEMO_PASSWORD}`);
  console.log(`  Documentos seed: ${docs.length} en ${storageRoot()}`);
  console.log(
    `  Fase 4: fiscalización pendiente ${fisPendiente.id}; canal contador ${paqueteContador.id}`,
  );
  console.log("  Usuarios:");
  for (const u of users) {
    console.log(`    ${u.email}`);
  }
  console.log(
    "  multi@repositoria.local pertenece a Empresa Demo (Contador) y Empresa Beta (Master).",
  );
}

main()
  .catch((err) => {
    console.error(err);
    process.exit(1);
  })
  .finally(async () => {
    await prisma.$disconnect();
  });
