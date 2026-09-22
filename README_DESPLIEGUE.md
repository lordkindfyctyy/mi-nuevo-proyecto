# Despliegue en Hostinger

Esta guía asume que ya tienes:

- Una cuenta de Hostinger con un plan que incluya **PHP 8+ y MySQL** (Premium/Business/Cloud; para el flujo con Git y SSH necesitas que el plan incluya acceso SSH).
- El código de este proyecto en un repositorio de **GitHub**.

`config/config.php` detecta solo si está corriendo en local o en producción (por el dominio de la petición), así que en Hostinger no hay que tocar ese archivo: solo hay que darle las credenciales de la base de datos (paso 4).

---

## 1. Crear la base de datos MySQL

1. En hPanel, ve a **Bases de datos → Bases de datos MySQL**.
2. Crea una nueva base de datos y un usuario (Hostinger les agrega automáticamente el prefijo de tu cuenta, ej. `u123456789_minuevoproyecto` / `u123456789_admin`).
3. Asocia el usuario a la base de datos con todos los privilegios.
4. Anota estos 4 datos, los vas a necesitar en el paso 4:
   - Host (normalmente `localhost`)
   - Nombre de la base de datos
   - Usuario
   - Contraseña

## 2. Importar el esquema (`database/schema.sql`)

El archivo `database/schema.sql` empieza con `CREATE DATABASE IF NOT EXISTS mi_nuevo_proyecto` y `USE mi_nuevo_proyecto;`. En Hostinger la base ya existe con otro nombre (el del paso 1) y tu usuario normalmente no tiene permiso para crear bases nuevas, así que:

1. Abre `database/schema.sql` en tu editor y **borra (o comenta con `--`) esas dos primeras líneas útiles** (`CREATE DATABASE...` y `USE mi_nuevo_proyecto;`). El resto del archivo (los `CREATE TABLE IF NOT EXISTS ...`) no necesita cambios.
2. Importa el archivo ya editado:

   **Opción A — phpMyAdmin (más simple):**
   - hPanel → **Bases de datos → phpMyAdmin** → entra a tu base de datos.
   - Pestaña **Importar** → selecciona el `schema.sql` editado → Ejecutar.

   **Opción B — SSH (si tu plan lo incluye):**
   ```bash
   mysql -h localhost -u u123456789_admin -p u123456789_minuevoproyecto < database/schema.sql
   ```

3. Verifica que se crearon las tablas: `tenants`, `users`, `products`, `sales`, `sale_items`, `suppliers`, `purchases`, `purchase_items`.

> **Datos de prueba:** `database/seed.php` crea un negocio, usuarios y productos de ejemplo. Es opcional y pensado para desarrollo — si lo corres en producción (`php database/seed.php` por SSH), vas a tener ese negocio de prueba visible; bórralo desde la app o por SQL cuando ya no lo necesites.

## 3. Configurar el dominio para que apunte a `public/`

Esta app espera que el **document root sea la carpeta `public/`** del proyecto, no la raíz del repositorio (así están armados los `require_once __DIR__ . '/../...'` y el `.htaccess` con `DirectoryIndex index.php`). Si el document root queda en la raíz del repo en vez de `public/`, vas a exponer `config/`, `src/`, `database/` directamente por HTTP.

Dependiendo de tu plan, elige una de estas dos formas:

**Opción A — Cambiar el document root desde hPanel (si tu plan lo permite):**
- hPanel → **Dominios** → tu dominio → busca la opción para cambiar la carpeta raíz del sitio (a veces bajo "Avanzado" o al gestionar el dominio) → apúntala a la carpeta `public/` dentro de donde vayas a clonar el repositorio (ej. `/home/u123456789/repositorios/mi-nuevo-proyecto/public`).

**Opción B — Symlink por SSH (funciona en cualquier plan con SSH):**
1. Clona el repo fuera de `public_html`, por ejemplo:
   ```bash
   cd ~
   git clone https://github.com/tu-usuario/mi-nuevo-proyecto.git repositorios/mi-nuevo-proyecto
   ```
2. Respalda el `public_html` actual si tiene algo, y reemplázalo por un symlink a la carpeta `public/` del repo:
   ```bash
   rm -rf ~/domains/tudominio.com/public_html
   ln -s ~/repositorios/mi-nuevo-proyecto/public ~/domains/tudominio.com/public_html
   ```
   (la ruta exacta de `domains/tudominio.com` puede variar; confírmala en hPanel → Administrador de archivos).

## 4. Configurar las credenciales de la base de datos

`config/config.php` lee la configuración de producción, en este orden de prioridad:

1. `config/config.local.php` (si existe — no se sube a git).
2. Variables de entorno del hosting.
3. Si ninguna está definida, la app falla al conectar con un error claro (a propósito: nunca asume credenciales por defecto en producción).

**Opción A — Variables de entorno (recomendada si tu plan la ofrece):**
- hPanel → **Avanzado → Variables de entorno** (el nombre exacto puede variar según el panel) → agrega:
  - `DB_HOST` = `localhost`
  - `DB_NAME` = el nombre de tu base (paso 1)
  - `DB_USER` = tu usuario (paso 1)
  - `DB_PASS` = tu contraseña (paso 1)

**Opción B — Archivo `config/config.local.php` (si tu plan no tiene variables de entorno):**
1. Copia `config/config.local.php.example` como `config/config.local.php` (por Administrador de archivos o SSH), directamente en el servidor — **nunca lo subas a git**, ya está en `.gitignore`.
2. Completa los valores:
   ```php
   <?php
   return [
       'DB_HOST' => 'localhost',
       'DB_NAME' => 'u123456789_minuevoproyecto',
       'DB_USER' => 'u123456789_admin',
       'DB_PASS' => 'tu-contraseña',
   ];
   ```

No hace falta configurar `APP_BASE_URL`: se calcula solo a partir del dominio real con el que entran tus visitantes.

## 5. Conectar el repositorio de GitHub

**Opción A — Git integrado de Hostinger:**
- hPanel → **Avanzado → Git** → pega la URL de tu repositorio de GitHub, la rama a desplegar (ej. `master`) y la carpeta de destino en el servidor.
- Cada vez que uses el botón de "Deploy"/"Pull" en ese panel, Hostinger actualiza los archivos desde GitHub.
- Después de conectar, asegúrate de que la carpeta de destino sea la misma que usaste en el paso 3 (o ajusta el document root/symlink para que apunte a `<carpeta-de-destino>/public`).

**Opción B — Manual por SSH:**
```bash
cd ~/repositorios/mi-nuevo-proyecto
git pull origin master
```
Repite este `git pull` cada vez que quieras actualizar producción con los últimos cambios.

> En ambos casos, `config/config.local.php` (si lo usaste en el paso 4) vive fuera del control de git — un `git pull` nunca lo toca ni lo borra.

## 5.1. Configurar el envío de mails (recuperación de contraseña)

La app envía mails transaccionales (recuperación de contraseña) por SMTP autenticado. Sin esto configurado, cae a `mail()` nativo, que en Hostinger suele terminar bloqueado o en spam.

1. En hPanel → **Emails** → creá un buzón en tu dominio, por ejemplo `no-reply@tudominio.com`, con una contraseña.
2. Configurá estas variables de entorno (hPanel → Avanzado → Variables de entorno) o agregalas a `config/config.local.php` (ver plantilla en `config/config.local.php.example`):
   - `SMTP_HOST` = `smtp.hostinger.com`
   - `SMTP_PORT` = `587`
   - `SMTP_USER` = `no-reply@tudominio.com`
   - `SMTP_PASS` = la contraseña de ese buzón
   - `SMTP_ENCRYPTION` = `tls`
3. Si algún mail falla, el detalle específico de PHPMailer/SMTP queda en el log de errores de PHP (hPanel → Avanzado → Registro de errores de PHP), buscá líneas que empiecen con `[mailer:smtp]`.

## 6. Verificar el despliegue

1. Entra a `https://tudominio.com` — deberías ver la página de inicio.
2. Prueba iniciar sesión o registrar un negocio nuevo desde "Regístrate".
3. Si algo falla, revisa los errores de PHP: hPanel → **Avanzado → Registro de errores de PHP** (o `tail -f` sobre el log de errores por SSH). `config/config.php` deja `display_errors` apagado en producción a propósito, así que los errores no se muestran al visitante, solo quedan en el log.

## 7. Notas de seguridad

- No subas nunca `config/config.local.php` a git (ya está en `.gitignore`).
- Activa HTTPS (Hostinger ofrece SSL gratis vía hPanel → Seguridad) y fuerza redirección a `https://` para que las cookies de sesión viajen cifradas.
- Verifica que **no** se pueda acceder por navegador a rutas fuera de `public/` (ej. `https://tudominio.com/../config/config.php` o `https://tudominio.com/../database/schema.sql`). Si eso responde con contenido en vez de un 404, el document root no está apuntando a `public/` (revisa el paso 3).
