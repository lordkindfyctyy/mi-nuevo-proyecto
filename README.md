# Mi Nuevo Proyecto

Estructura inicial para un sistema web con PHP, HTML, CSS y JS.

## Estructura

```
public/              Document root (apunta aquí el servidor web)
  index.php
  about.php
  contact.php
  process/           Scripts que procesan formularios (POST)
  assets/
    css/style.css
    js/main.js
    img/
includes/            Header, footer y otros parciales reutilizables
config/
  config.php         Constantes generales (BASE_URL, APP_NAME, etc.)
  database.php       Conexión PDO a MySQL
src/
  controllers/       Lógica de controladores
  models/            Modelos / acceso a datos
database/            Scripts SQL (esquema, migraciones, seeds)
uploads/             Archivos subidos por usuarios (ignorado en git)
```

## Cómo ejecutar

1. Copia el proyecto a tu servidor local (XAMPP/WAMP/Laragon) o corre el servidor embebido de PHP:

   ```
   php -S localhost:8000 -t public
   ```

2. Ajusta `config/config.php` con la URL base y credenciales de base de datos.
3. Crea la base de datos y ejecuta los scripts que agregues en `database/`.

## Notas

- `public/` es el único directorio que debe ser accesible desde el navegador; todo lo demás vive fuera del document root.
- `includes/header.php` y `includes/footer.php` envuelven cada página pública.
