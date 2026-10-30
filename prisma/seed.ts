import { PrismaClient, type Rol } from "@prisma/client";

const prisma = new PrismaClient();

const IDS = {
  empresa: "emp_demo_repositoria",
  sucursalNorte: "suc_norte",
  sucursalSur: "suc_sur",
  users: {
    master: "usr_master",
    jefeNorte: "usr_jefe_norte",
    jefeSur: "usr_jefe_sur",
    contador: "usr_contador",
    abogado: "usr_abogado",
    prevencionista: "usr_prevencionista",
  },
  membresias: {
    master: "mem_master",
    jefeNorte: "mem_jefe_norte",
    jefeSur: "mem_jefe_sur",
    contador: "mem_contador",
    abogado: "mem_abogado",
    prevencionista: "mem_prevencionista",
  },
  depts: {
    adminNorte: "dep_admin_norte",
    rrhhNorte: "dep_rrhh_norte",
    prevNorte: "dep_prev_norte",
    adminSur: "dep_admin_sur",
    rrhhSur: "dep_rrhh_sur",
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

  await prisma.expedienteAutorizacion.deleteMany();
  await prisma.expedienteStub.deleteMany();
  await prisma.departamento.deleteMany();
  await prisma.membresia.deleteMany();
  await prisma.sucursal.deleteMany();
  await prisma.usuario.deleteMany();
  await prisma.empresa.deleteMany();

  const empresa = await prisma.empresa.create({
    data: {
      id: IDS.empresa,
      nombre: "Empresa Demo",
      slug: "empresa-demo",
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
    ],
  });

  const users: Array<{
    id: string;
    email: string;
    nombre: string;
    memId: string;
    rol: Rol;
    sucursalId: string | null;
  }> = [
    {
      id: IDS.users.master,
      email: "admin@repositoria.local",
      nombre: "Administrador Demo",
      memId: IDS.membresias.master,
      rol: "ADMINISTRADOR_MASTER",
      sucursalId: null,
    },
    {
      id: IDS.users.jefeNorte,
      email: "jefe.norte@repositoria.local",
      nombre: "Jefe Norte",
      memId: IDS.membresias.jefeNorte,
      rol: "JEFE_SUCURSAL",
      sucursalId: norte.id,
    },
    {
      id: IDS.users.jefeSur,
      email: "jefe.sur@repositoria.local",
      nombre: "Jefe Sur",
      memId: IDS.membresias.jefeSur,
      rol: "JEFE_SUCURSAL",
      sucursalId: sur.id,
    },
    {
      id: IDS.users.contador,
      email: "contador@repositoria.local",
      nombre: "Contador Externo",
      memId: IDS.membresias.contador,
      rol: "CONTADOR",
      sucursalId: null,
    },
    {
      id: IDS.users.abogado,
      email: "abogado@repositoria.local",
      nombre: "Abogado Externo",
      memId: IDS.membresias.abogado,
      rol: "ABOGADO",
      sucursalId: null,
    },
    {
      id: IDS.users.prevencionista,
      email: "prevencionista@repositoria.local",
      nombre: "Prevencionista Externo",
      memId: IDS.membresias.prevencionista,
      rol: "PREVENCIONISTA",
      sucursalId: null,
    },
  ];

  for (const u of users) {
    await prisma.usuario.create({
      data: {
        id: u.id,
        email: u.email,
        nombre: u.nombre,
        passwordHash: "demo",
      },
    });
    await prisma.membresia.create({
      data: {
        id: u.memId,
        empresaId: empresa.id,
        usuarioId: u.id,
        rol: u.rol,
        sucursalId: u.sucursalId,
      },
    });
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
    ],
  });

  console.log("Seed OK");
  console.log(`  Empresa: ${empresa.nombre} (${empresa.id})`);
  console.log(`  Sucursales: ${norte.nombre}, ${sur.nombre}`);
  console.log("  Demo users:");
  for (const u of users) {
    console.log(`    ${u.email} → ${u.rol}`);
  }
}

main()
  .catch((err) => {
    console.error(err);
    process.exit(1);
  })
  .finally(async () => {
    await prisma.$disconnect();
  });
