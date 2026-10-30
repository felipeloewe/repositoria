# Repositoria

Repositorio documental **multi-usuario y multi-empresa** (tenant = empresa). Stack: **Next.js App Router** + **MySQL** (Prisma). Local-first.

## Fases en este repo

- **Fase 1:** organización, membresías, ACL, shell.
- **Fase 2:** repositorio de documentos por empresa (ligados a departamentos; alcance por sucursal).

## Jerarquía

**Empresa → Departamentos → Sucursales.**

En cada Departamento: mantenedores de **Actividades** y **Temas de Interés** (tags independientes entre sí).  
Fase 5 (más adelante): áreas/etapas dentro de Departamentos — no implementado aún.

## Identidad y membresía

- **Usuario** / **Empresa** por **ID estable** (`cuid`). Correo y RUT son atributos.
- **Membresia** = `(usuarioId, empresaId)` → `rol` (+ `sucursalId` si aplica).
- Login correo/contraseña; multi-empresa → selector de empresa (con confirmación si el rol cambia).

## Documentos (Fase 2)

- Secciones (`DocumentoSeccion`) y archivos (`Documento`) ligados a un **Departamento**; `sucursalId` null = nivel departamento.
- Archivos en disco local bajo `STORAGE_ROOT` (default `./storage/documents`).
- ACL: **Master** ve/sube todo; **Jefe** ve nivel-depto de su departamento + su sucursal (sube solo en su sucursal); **externos** solo documentos con `DocumentoAutorizacion`.
- UI: `/documentos` — listar, filtrar por departamento/ámbito/sección, subir, descargar.

## Tipografía y shell

Questrial (referencia Questia). Sidebar + top bar + banner. Colores verde/teal Repositoria.

## Setup local

```bash
git clone https://github.com/felipeloewe/repositoria.git
cd repositoria
npm install
cp .env.example .env
# DATABASE_URL=...  # opcional: STORAGE_ROOT=...
npx prisma migrate deploy
npx prisma generate
npm run db:seed
npm run dev
```

→ http://localhost:3000/login · contraseña seed **`demo123`**

| Correo | Perfil |
| --- | --- |
| `admin@repositoria.local` | Master · Empresa Demo |
| `jefe.norte@repositoria.local` | Jefe Norte |
| `jefe.sur@repositoria.local` | Jefe Sur |
| `contador@repositoria.local` | Contador (docs/expedientes autorizados) |
| `abogado@repositoria.local` | Abogado |
| `prevencionista@repositoria.local` | Prevencionista |
| `multi@repositoria.local` | Contador Demo + Master Beta |

## Scripts

`dev` · `build` · `lint` · `db:migrate` · `db:deploy` · `db:seed` · `db:studio`

## Fuera de alcance aún

Correo (Fase 3), flujos (Fase 4), áreas/etapas en depto (Fase 5), auth de producción / object storage real.
