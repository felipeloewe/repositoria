# Repositoria

Repositorio documental **multi-usuario y multi-empresa** (tenant = empresa).

**Stack (mainline):** [CodeIgniter 4](https://codeigniter.com/) + **PHP 8.2+ / 8.5** + **MySQL** + Apache (`public/` + `.htaccess`).

El prototipo Next.js (rama `cursor/scaffold-repositoria-e946` / PR #1) queda como **referencia de producto** (roles, fases, modelo); no es el runtime de esta rama.

## Alcance de este PR

- Scaffold CI4 Apache-friendly
- **Fase 1:** auth por membresía real (sin role switcher) + CRUD de organización (Empresa → Departamento → Sucursal; tags Actividades / Temas de Interés)
- Migraciones + seed demo
- Stubs de esquema ACL documental (Fase 2 tables)

**Follow-ups:** Fase 2 (docs + ACL UI), Fase 3 (imap-mail), Fase 4 (fiscalización / canales).

## Jerarquía e identidad

- **Empresa → Departamentos → Sucursales**
- Actividades y Temas de Interés = tags **independientes** del departamento
- Usuario / Empresa por **ID estable**; email/RUT son atributos
- Membresía `(usuario_id, empresa_id)` → rol (+ `sucursal_id` si aplica)
- Multi-empresa: selector de empresa; si el rol cambia, se avisa y se restablece el perfil

## Roles

| Rol | Alcance (Fase 1) |
| --- | --- |
| Administrador Master | Org CRUD + visibilidad total |
| Jefe de Sucursal | Perfil acotado a su sucursal |
| Contador / Abogado / Prevencionista | Externos acotados (docs ACL en Fase 2) |

## Setup local

Requisitos: PHP 8.2+ (intl, mbstring, mysqlnd, curl), Composer, MySQL 8.

```bash
git clone https://github.com/felipeloewe/repositoria.git
cd repositoria
git checkout cursor/ci4-repositoria-4c01   # o la rama del PR
composer install
cp .env.example .env
# editar database.* y app.baseURL
php spark key:generate
php spark migrate
php spark db:seed DemoSeeder
php spark serve
```

→ http://localhost:8080/login · contraseña seed **`demo123`**

| Correo | Perfil |
| --- | --- |
| `admin@repositoria.local` | Master · Empresa Demo |
| `jefe.norte@repositoria.local` | Jefe Norte |
| `jefe.sur@repositoria.local` | Jefe Sur |
| `contador@repositoria.local` | Contador |
| `abogado@repositoria.local` | Abogado |
| `prevencionista@repositoria.local` | Prevencionista |
| `multi@repositoria.local` | Contador Demo + Master Beta |

## Deploy cPanel / Apache (AlmaLinux, WHM)

**Document root = `public/`** (no la raíz del repo).

1. **MySQL** (cPanel → MySQL® Databases): crear BD + usuario; ALL PRIVILEGES.
2. **Código:** Git Version Control o ZIP en p. ej. `/home/CUENTA/repositoria`.
3. **PHP:** MultiPHP Manager → **8.2+** (ideal 8.3/8.5). Extensiones: `mysqli`, `intl`, `mbstring`, `curl`, `json`.
4. **Document root:** en el dominio/subdominio, apuntar a `/home/CUENTA/repositoria/public`.
5. **Composer** (SSH o Terminal cPanel):

```bash
cd ~/repositoria
composer install --no-dev --optimize-autoloader
cp .env.example .env
# Editar .env: app.baseURL=https://repositoria.tudominio.cl/
# database.default.* con credenciales cPanel
php spark key:generate
php spark migrate
php spark db:seed DemoSeeder   # opcional
```

6. Permisos: `writable/` y (Fase 2) `storage/` deben ser escribibles por el usuario de Apache/PHP (`chmod -R ug+rwX writable storage`).
7. `.htaccess` en `public/` ya reescribe a `index.php`. Si el host usa `AllowOverride None`, habilítalo o replica las reglas en el VirtualHost.
8. Probar `https://repositoria.tudominio.cl/login`.

### Estructura relevante

```text
repositoria/
  app/                 # Controllers, Models, Views, Migrations, Seeds
  public/              # ← document root Apache
    index.php
    .htaccess
    assets/css/app.css
  writable/            # cache, logs, session
  storage/documents/   # Fase 2 (reservado)
  .env.example
  spark
```

## UI

Plus Jakarta Sans / Questrial. Shell vivo azul + cards redondeadas (ref. vibrant; no clonar Copec). Sidebar + top bar + banner.

## Scripts útiles

```bash
php spark migrate
php spark migrate:rollback
php spark db:seed DemoSeeder
php spark serve --host 0.0.0.0 --port 8080
```
