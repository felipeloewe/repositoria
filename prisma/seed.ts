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
  depts: {
    adminNorte: "dep_admin_norte",
    rrhhNorte: "dep_rrhh_norte",
    prevNorte: "dep_prev_norte",
    adminSur: "dep_admin_sur",
    rrhhSur: "dep_rrhh_sur",
    adminBeta: "dep_admin_beta",
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

  await prisma.documentoAutorizacion.deleteMany();
  await prisma.documento.deleteMany();
  await prisma.documentoSeccion.deleteMany();
  await prisma.expedienteAutorizacion.deleteMany();
  await prisma.expedienteStub.deleteMany();
  await prisma.departamento.deleteMany();
  await prisma.membresia.deleteMany();
  await prisma.sucursal.deleteMany();
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

  const norte = await prisma.sucursal.create({
    data: {
      id: IDS.sucursalNorte,
      empresaId: empresa.id,
      nombre: "Sucursal Norte",
      codigo: "NTE",
    },
  });

  const sur = await prisma.sucursal.create({
    data: {
      id: IDS.sucursalSur,
      empresaId: empresa.id,
      nombre: "Sucursal Sur",
      codigo: "SUR",
    },
  });

  const betaSuc = await prisma.sucursal.create({
    data: {
      id: IDS.sucursalBeta,
      empresaId: empresaBeta.id,
      nombre: "Casa Matriz",
      codigo: "BETA",
    },
  });

  await prisma.departamento.createMany({
    data: [
      {
        id: IDS.depts.adminNorte,
        empresaId: empresa.id,
        sucursalId: norte.id,
        nombre: "Administración",
      },
      {
        id: IDS.depts.rrhhNorte,
        empresaId: empresa.id,
        sucursalId: norte.id,
        nombre: "RR.HH.",
      },
      {
        id: IDS.depts.prevNorte,
        empresaId: empresa.id,
        sucursalId: norte.id,
        nombre: "Prevención",
      },
      {
        id: IDS.depts.adminSur,
        empresaId: empresa.id,
        sucursalId: sur.id,
        nombre: "Administración",
      },
      {
        id: IDS.depts.rrhhSur,
        empresaId: empresa.id,
        sucursalId: sur.id,
        nombre: "RR.HH.",
      },
      {
        id: IDS.depts.adminBeta,
        empresaId: empresaBeta.id,
        sucursalId: betaSuc.id,
        nombre: "Administración",
      },
    ],
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
    // Multi-empresa: Contador in Demo + Master in Beta — picker is by empresa only.
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

  // --- Fase 2: secciones + documentos de ejemplo ---
  const secCorpContratos = await prisma.documentoSeccion.create({
    data: {
      id: "sec_corp_contratos",
      empresaId: empresa.id,
      sucursalId: null,
      nombre: "Contratos",
      codigo: "CONTRATOS",
    },
  });
  const secNorteLiq = await prisma.documentoSeccion.create({
    data: {
      id: "sec_norte_liquidaciones",
      empresaId: empresa.id,
      sucursalId: norte.id,
      nombre: "Liquidaciones",
      codigo: "LIQUIDACIONES",
    },
  });
  const secSurContratos = await prisma.documentoSeccion.create({
    data: {
      id: "sec_sur_contratos",
      empresaId: empresa.id,
      sucursalId: sur.id,
      nombre: "Contratos",
      codigo: "CONTRATOS",
    },
  });
  await prisma.documentoSeccion.create({
    data: {
      id: "sec_norte_general",
      empresaId: empresa.id,
      sucursalId: norte.id,
      nombre: "General",
      codigo: "GENERAL",
    },
  });

  const docs = [
    {
      id: "doc_politica_corp",
      seccionId: secCorpContratos.id,
      sucursalId: null as string | null,
      titulo: "Política de contratos corporativos",
      filename: "politica-contratos.txt",
      body: "Documento corporativo de ejemplo — visible a Master y Jefes.\n",
      authorize: [] as string[],
    },
    {
      id: "doc_liq_norte",
      seccionId: secNorteLiq.id,
      sucursalId: norte.id,
      titulo: "Liquidaciones Norte — marzo",
      filename: "liquidaciones-norte-marzo.txt",
      body: "Liquidaciones Sucursal Norte — ejemplo seed.\n",
      authorize: [IDS.users.contador, IDS.users.multi],
    },
    {
      id: "doc_contrato_sur",
      seccionId: secSurContratos.id,
      sucursalId: sur.id,
      titulo: "Contrato proveedor Sur",
      filename: "contrato-sur.txt",
      body: "Contrato Sucursal Sur — ejemplo seed.\n",
      authorize: [IDS.users.abogado],
    },
  ];

  for (const d of docs) {
    const file = writeSeedFile(empresa.id, d.id, d.filename, d.body);
    await prisma.documento.create({
      data: {
        id: d.id,
        empresaId: empresa.id,
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

  console.log("Seed OK");
  console.log(`  Empresas: ${empresa.nombre}, ${empresaBeta.nombre}`);
  console.log(`  Contraseña demo: ${DEMO_PASSWORD}`);
  console.log(`  Documentos seed: ${docs.length} en ${storageRoot()}`);
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
