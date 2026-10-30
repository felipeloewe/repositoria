# Repositoria

Repositorio documental **multi-usuario y multi-empresa** (tenant = empresa). Stack: **Next.js App Router** + **MySQL** (Prisma). Local-first.

**Fase 1 — Organización y perfiles:** empresa → sucursales → departamentos, membresías con roles, ACL, stubs de expedientes.

## Identidad y membresía

- **Usuario** y **Empresa** se identifican por **ID estable** (`cuid`). Correo y RUT son atributos complementarios; la ACL/tenancy valida por `usuarioId` / `empresaId`.
- **Membresia** = un registro por par `(usuarioId, empresaId)` → `rol` (+ `sucursalId` si aplica). El mismo usuario puede tener **distinto rol en cada empresa**.
- Login: correo/contraseña → sesión con la membresía de la empresa elegida.
- Multi-empresa: selector de empresas del perfil. Si al cambiar de empresa el **rol difiere**, se muestra confirmación, se **restablece el perfil activo** a esa membresía y se informa en el dashboard (no se conserva el rol anterior).

## Tipografía y shell

- UI en **Questrial** (Google Fonts) — webfont real correspondiente a la referencia de producto «Questia».
- Colores Repositoria (verde/teal); no se copia la paleta CRM de referencia.
- Shell: barra superior (marca, búsqueda opcional, usuario + rol/empresa) + **sidebar** con navegación Repositoria (Dashboard, Organización, Expedientes). La referencia CRM se usó solo como idea de layout.

## Setup local

```bash
git clone https://github.com/felipeloewe/repositoria.git
cd repositoria
npm install
cp .env.example .env
# ajustar DATABASE_URL
npx prisma migrate deploy
npx prisma generate
npm run db:seed
npm run dev
```

→ [http://localhost:3000/login](http://localhost:3000/login)

## Usuarios seed

Contraseña: **`demo123`**

| Correo | Membresía |
| --- | --- |
| `admin@repositoria.local` | Master · Empresa Demo |
| `jefe.norte@repositoria.local` | Jefe Sucursal Norte |
| `jefe.sur@repositoria.local` | Jefe Sucursal Sur |
| `contador@repositoria.local` | Contador (Demo) |
| `abogado@repositoria.local` | Abogado (Demo) |
| `prevencionista@repositoria.local` | Prevencionista (Demo) |
| `multi@repositoria.local` | Contador en Demo **y** Master en Empresa Beta (cambio de rol al cambiar empresa) |

## ACL (Fase 1)

- **Administrador Master:** organización completa del tenant + expedientes stub.
- **Jefe de Sucursal:** solo su sucursal.
- **Externos:** solo expedientes autorizados por `usuarioId`.

## Scripts

`npm run dev` · `build` · `lint` · `db:migrate` · `db:deploy` · `db:seed` · `db:studio`

Opcional solo-dev: `DEV_AUTH_SWITCHER=1` → `/dev/switcher`.

## Fuera de alcance

Documentos (Fase 2), correo (Fase 3), flujos (Fase 4), auth de producción.
