# Repositoria

Repositorio documental **multi-usuario y multi-empresa** (tenant = empresa). Stack: **Next.js App Router** + **MySQL** (Prisma). Local-first.

**Fase 1 — Organización y perfiles:** empresa → sucursales → departamentos, membresías con roles, ACL, y stubs de expedientes para autorizaciones externas.

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

Ajusta `DATABASE_URL` en `.env`, crea la base, luego:

```bash
npx prisma migrate deploy
npx prisma generate
npm run db:seed
npm run dev
```

Abre [http://localhost:3000/login](http://localhost:3000/login).

## Entrada (perfilado real)

1. El usuario inicia sesión con **correo + contraseña**.
2. La sesión carga su **Usuario** y la(s) **Membresia**(s) asociadas.
3. Si pertenece a **una** empresa, entra directo con el rol de esa membresía.
4. Si pertenece a **varias** empresas, ve un **selector de empresa** (solo tenants donde es miembro). El rol no se “elige”: sale del perfil en esa empresa.
5. No hay switcher de personas/roles como flujo principal.

Opcional solo-dev: `DEV_AUTH_SWITCHER=1` habilita `/dev/switcher` para impersonación local. No es el producto.

## Usuarios seed

Contraseña de todos: **`demo123`**

| Correo | Perfil |
| --- | --- |
| `admin@repositoria.local` | Master en Empresa Demo |
| `jefe.norte@repositoria.local` | Jefe Sucursal Norte |
| `jefe.sur@repositoria.local` | Jefe Sucursal Sur |
| `contador@repositoria.local` | Contador (expedientes autorizados) |
| `abogado@repositoria.local` | Abogado |
| `prevencionista@repositoria.local` | Prevencionista |
| `multi@repositoria.local` | Contador en Demo **y** Master en Empresa Beta (muestra el picker de empresa) |

## Scripts

| Script | Descripción |
| --- | --- |
| `npm run dev` | Desarrollo |
| `npm run build` / `start` | Producción |
| `npm run lint` | ESLint |
| `npm run db:migrate` / `db:deploy` | Migraciones |
| `npm run db:seed` | Datos demo |
| `npm run db:studio` | Prisma Studio |

## ACL (Fase 1)

- **Administrador Master:** CRUD organización + todos los expedientes stub del tenant.
- **Jefe de Sucursal:** solo su sucursal (sin acceso cruzado).
- **Contador / Abogado / Prevencionista:** solo expedientes con autorización explícita.

Enforcement: `src/lib/acl.ts` + server actions.

## Fuera de alcance aún

Documentos (Fase 2), correo (Fase 3), flujos (Fase 4), auth de producción (OAuth/IdP).

## Licencia

Privado — uso interno del proyecto.
