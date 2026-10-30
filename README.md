# Repositoria

Repositorio documental **multi-usuario y multi-empresa** (tenant = empresa). Stack: **Next.js App Router** + **MySQL** (Prisma). Arranque local-first; este scaffold cubre la app base y el modelo de datos de Fase 1 (organización y perfiles).

## Requisitos

- Node.js 20+ (recomendado 22)
- MySQL 8+
- npm

## Setup local

1. Clonar e instalar dependencias:

```bash
git clone https://github.com/felipeloewe/repositoria.git
cd repositoria
npm install
```

2. Crear la base de datos MySQL y copiar variables de entorno:

```bash
cp .env.example .env
```

Edita `.env` y ajusta `DATABASE_URL`, por ejemplo:

```env
DATABASE_URL="mysql://repositoria:repositoria@localhost:3306/repositoria"
```

Crea el schema en MySQL si aún no existe:

```sql
CREATE DATABASE repositoria CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'repositoria'@'localhost' IDENTIFIED BY 'repositoria';
GRANT ALL PRIVILEGES ON repositoria.* TO 'repositoria'@'localhost';
FLUSH PRIVILEGES;
```

3. Aplicar migraciones y generar el cliente Prisma:

```bash
npx prisma migrate deploy
# o, en desarrollo con tracking de migraciones:
# npx prisma migrate dev
npx prisma generate
```

4. Levantar el servidor de desarrollo:

```bash
npm run dev
```

Abre [http://localhost:3000](http://localhost:3000). El login es un **stub** (no valida credenciales) y entra al dashboard con sesión demo de Administrador Master.

## Scripts

| Script | Descripción |
| --- | --- |
| `npm run dev` | Next.js en modo desarrollo |
| `npm run build` | Build de producción |
| `npm run start` | Servidor de producción |
| `npm run lint` | ESLint |
| `npm run db:generate` | `prisma generate` |
| `npm run db:migrate` | `prisma migrate dev` |
| `npm run db:studio` | Prisma Studio |

## Modelo de datos (Fase 1)

| Modelo | Rol |
| --- | --- |
| **Empresa** | Tenant. Aislamiento entre organizaciones. |
| **Sucursal** | Unidad dentro de una empresa (`empresaId`). |
| **Departamento** | Área bajo una sucursal (`empresaId` + `sucursalId`). |
| **Usuario** | Identidad global (email único). |
| **Membresia** | Usuario ↔ Empresa con rol y sucursal opcional. |

Roles (`Rol`): `ADMINISTRADOR_MASTER`, `JEFE_SUCURSAL`, `CONTADOR`, `ABOGADO`, `PREVENCIONISTA`.

Todas las tablas con alcance de tenant llevan `empresaId`.

Schema: [`prisma/schema.prisma`](prisma/schema.prisma).

## Alcance de este scaffold

Incluye:

- App Next.js (App Router, TypeScript, ESLint, Tailwind)
- Prisma + MySQL y migración inicial
- Shell mínimo: login stub, layout tenant-aware, dashboard vacío, página de organización (placeholder)

Aún no incluye (fases posteriores): gestión documental, conector de correo, flujos con terceros, auth real.

## Licencia

Privado — uso interno del proyecto.
