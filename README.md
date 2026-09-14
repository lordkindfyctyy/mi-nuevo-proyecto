# Mi Nuevo Proyecto

Sistema web multi-negocio (multi-tenant) para gestionar productos y registrar ventas, hecho con PHP + MySQL y Bootstrap 5.

## Funcionalidades

- Registro de negocios y login por sesión (cada negocio ve únicamente sus propios datos).
- Gestión de inventario (crear, editar, eliminar productos).
- Registro de ventas con cálculo de totales en vivo y descuento automático de stock.
- Historial de ventas con detalle por producto.

## Requisitos

- PHP 8.0 o superior (con la extensión `pdo_mysql` habilitada).
- MySQL 8+ o MariaDB 10.4+.
- Opcional: [XAMPP](https://www.apachefriends.org/) trae PHP y MySQL listos para usar en Windows.

## Instalación

1. **Clona el repositorio**

   ```bash
   git clone https://github.com/lordkindfyctyy/mi-nuevo-proyecto.git
   cd mi-nuevo-proyecto
   ```

2. **Crea tu archivo de configuración**

   ```bash
   cp config/config.example.php config/config.php
   ```

   Edita `config/config.php` y ajusta según tu entorno:

   ```php
   define('BASE_URL', 'http://localhost:8000'); // URL donde correrá el sitio
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'mi_nuevo_proyecto');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   ```

3. **Crea la base de datos**

   El script crea la base `mi_nuevo_proyecto` si no existe y todas las tablas (`tenants`, `users`, `products`, `sales`, `sale_items`).

   ```bash
   mysql -u root < database/schema.sql
   ```

4. **(Opcional) Carga datos de prueba**

   Crea un negocio de ejemplo ("Tienda Doña Rosa") con usuarios y productos:

   ```bash
   php database/seed.php
   ```

   Usuarios de prueba (contraseña `password123`):
   - `rosa@donarosa.test` (owner)
   - `carlos@donarosa.test` (employee)

5. **Levanta el servidor**

   ```bash
   php -S localhost:8000 -t public
   ```

6. Abre [http://localhost:8000](http://localhost:8000) en tu navegador. Puedes iniciar sesión con un usuario de prueba o crear tu propio negocio desde "Regístrate".

> **Windows con XAMPP:** si `php` o `mysql` no están en el PATH, usa las rutas completas:
>
> ```powershell
> & "C:\xampp\mysql\bin\mysql.exe" -u root -e "source database/schema.sql"
> & "C:\xampp\php\php.exe" database/seed.php
> & "C:\xampp\php\php.exe" -S localhost:8000 -t public
> ```

## Estructura

```
public/                 Document root (apunta aquí el servidor web)
  index.php, about.php, contact.php   Páginas públicas
  login.php, register.php, logout.php Autenticación
  productos.php          Gestión de inventario
  vender.php             Registrar ventas
  ventas.php             Historial de ventas
  process/                Scripts que procesan formularios (POST)
  assets/
    css/style.css
    js/main.js
    img/
includes/
  header.php, footer.php  Layout compartido (navbar, Bootstrap 5)
  tenant_context.php      Sesión activa: negocio y usuario logueados
config/
  config.php              Constantes del entorno (NO se sube a git)
  config.example.php      Plantilla de config.php
  database.php             Conexión PDO a MySQL
src/
  models/                 Tenant, User, Product, Sale (acceso a datos)
database/
  schema.sql              Esquema completo de la base de datos
  seed.php                 Datos de prueba
uploads/                  Archivos subidos por usuarios (ignorado en git)
```

## Notas

- `public/` es el único directorio que debe ser accesible desde el navegador; todo lo demás vive fuera del document root.
- Todas las consultas por id de producto/venta están filtradas por `tenant_id` (ver `findForTenant()` en los modelos) para que un negocio nunca pueda ver o modificar datos de otro.
- `config/config.php` está en `.gitignore` porque contiene credenciales de base de datos; usa `config/config.example.php` como plantilla.
