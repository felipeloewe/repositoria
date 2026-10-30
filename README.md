# Repositoria

Repositorio documental **multi-usuario y multi-empresa** (tenant = empresa). Stack: **Next.js App Router** + **MySQL** (Prisma). Local-first.

## Fases en este repo

- **Fase 1:** organización, membresías, ACL, shell.
- **Fase 2:** repositorio de documentos por empresa (ligados a departamentos; alcance por sucursal).
- **Fase 3:** integración con [imap-mail](https://github.com/felipeloewe/imap-mail) — casilla por usuario, hilos, adjuntos + certificado PDF → repositorio.
- **Fase 4:** fiscalización (autorización Master antes de exportar/enviar) + canales seguros para externos.

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

## Correo (Fase 3)

Servicio paralelo **imap-mail** (puerto típico **3001**). Repositoria no almacena IMAP; llama APIs con el `usuarioId` de la membresía activa.

| UI | Función |
| --- | --- |
| `/correo` | Lista hilos de la casilla del usuario |
| `/correo/configuracion` | Vincular casilla IMAP (`POST /api/accounts`) |
| `/correo/[threadId]` | Detalle; **guardar adjunto** y **certificado PDF** como `Documento` (departamento/sección + ACL) |
| `POST /api/webhooks/imap-mail` | Stub inbound desde imap-mail |

### Cómo correr ambos

```bash
# Terminal A — imap-mail (https://github.com/felipeloewe/imap-mail)
cd imap-mail && cp .env.example .env
# DATABASE_URL distinta (p. ej. imap_mail), PORT=3001
npm install && npx prisma migrate deploy && npm run seed:demo
npm run dev -- -p 3001

# Terminal B — Repositoria
cd repositoria && cp .env.example .env
# IMAP_MAIL_BASE_URL=http://localhost:3001
# En imap-mail: REPOSITORIA_WEBHOOK_URL=http://localhost:3000/api/webhooks/imap-mail
npm run db:seed && npm run dev
```

Sin `IMAP_MAIL_BASE_URL` (o si el servicio no responde), Correo usa **fixtures mock** alineadas al seed de imap-mail para poder probar UI y “guardar en repositorio” offline.

APIs usadas: `/api/health`, `/api/accounts`, `/api/threads`, `/api/threads/:id`, `/api/threads/:id/certificate`, `/api/attachments/:id/export`.

## Tipografía y shell

Plus Jakarta Sans. Shell vivo: azul fuerte, cards redondeadas, banner en degradé (ref. UI vibrant — sin clonar Copec). Sidebar + top bar.

## Setup local

```bash
git clone https://github.com/felipeloewe/repositoria.git
cd repositoria
npm install
cp .env.example .env
# DATABASE_URL=...  # opcional: STORAGE_ROOT=...  IMAP_MAIL_BASE_URL=...
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

## Flujos (Fase 4)

| UI | Función |
| --- | --- |
| `/flujos` | Resumen fiscalización + canales |
| `/flujos/fiscalizacion` | Solicitar envío liquidaciones/contratos; Master aprueba/rechaza; solo entonces “enviar/exportar”; auditoría |
| `/flujos/canales` | Paquetes documentales para Contador / Abogado / Prevencionista |

## Fuera de alcance aún

Áreas/etapas en depto (Fase 5), IA sobre índice de correo, auth de producción / object storage real.
