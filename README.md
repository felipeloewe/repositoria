# Repositoria

Repositorio documental **multi-usuario y multi-empresa** (tenant = empresa). Stack: **Next.js App Router** + **MySQL** (Prisma). Local-first.

Este repo cubre **Fase 1 — Organización y perfiles**: estructura empresa → sucursales → departamentos, membresías con roles, ACL por rol/sucursal, y stubs de expedientes para demostrar autorizaciones externas.

## Requisitos

- Node.js 20+ (recomendado 22)
- MySQL 8+ / MariaDB 10.11+
- npm

## Setup local

```bash
git clone https://github.com/felipeloewe/repositoria.git
cd repositoria
npm install
cp .env.example .env
```

Ajusta `DATABASE_URL` en `.env`:

```env
DATABASE_URL="mysql://repositoria:repositoria@localhost:3306/repositoria"
```

Crea la base (ejemplo MySQL/MariaDB):

```sql
CREATE DATABASE repositoria CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'repositoria'@'localhost' IDENTIFIED BY 'repositoria';
GRANT ALL PRIVILEGES ON repositoria.* TO 'repositoria'@'localhost';
FLUSH PRIVILEGES;
```

Migrar, generar cliente y cargar demo:

```bash
npx prisma migrate deploy
npx prisma generate
npm run db:seed
npm run dev
```

Abre [http://localhost:3000](http://localhost:3000). En **/login** elige un usuario demo (stub de auth con cookie de membresía).

## Usuarios demo (seed)

| Correo | Rol | Alcance |
| --- | --- | --- |
| `admin@repositoria.local` | Administrador Master | Toda la empresa |
| `jefe.norte@repositoria.local` | Jefe de Sucursal | Solo Sucursal Norte |
| `jefe.sur@repositoria.local` | Jefe de Sucursal | Solo Sucursal Sur |
| `contador@repositoria.local` | Contador | Expedientes autorizados |
| `abogado@repositoria.local` | Abogado | Expedientes autorizados |
| `prevencionista@repositoria.local` | Prevencionista | Expedientes autorizados |

Seed: 1 empresa, 2 sucursales, departamentos de ejemplo, membresías y 4 expedientes stub con autorizaciones.

## Scripts

| Script | Descripción |
| --- | --- |
| `npm run dev` | Next.js desarrollo |
| `npm run build` / `npm run start` | Producción |
| `npm run lint` | ESLint |
| `npm run db:generate` | `prisma generate` |
| `npm run db:migrate` | `prisma migrate dev` |
| `npm run db:deploy` | `prisma migrate deploy` |
| `npm run db:seed` | Datos demo |
| `npm run db:studio` | Prisma Studio |

## ACL (Fase 1)

- **Administrador Master:** CRUD de empresa, sucursales, departamentos y membresías; ve todos los expedientes stub del tenant.
- **Jefe de Sucursal:** ve/administra departamentos de **su** sucursal; sin acceso cruzado a otras; ve expedientes de su sucursal.
- **Contador / Abogado / Prevencionista:** no navegan la organización; solo expedientes con `ExpedienteAutorizacion` a su usuario.

La enforcement vive en `src/lib/acl.ts` y en server actions (`src/app/actions/*`).

## Modelo de datos

| Modelo | Notas |
| --- | --- |
| **Empresa** | Tenant |
| **Sucursal** / **Departamento** | `empresaId` (+ `sucursalId` en depto) |
| **Usuario** / **Membresia** | Rol + sucursal opcional |
| **ExpedienteStub** / **ExpedienteAutorizacion** | Solo para demo ACL (no es Fase 2 documental) |

Schema: [`prisma/schema.prisma`](prisma/schema.prisma).

## Fuera de alcance aún

Gestión documental completa (Fase 2), correo (Fase 3), flujos con terceros (Fase 4), auth real.

## Licencia

Privado — uso interno del proyecto.
