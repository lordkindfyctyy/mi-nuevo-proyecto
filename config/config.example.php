<?php

// Copia este archivo como config.php y ajusta los valores para tu entorno.

// Configuración general del sistema
define('BASE_URL', 'http://localhost:8000');
define('APP_NAME', 'Mi Nuevo Proyecto');

// Configuración de base de datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'mi_nuevo_proyecto');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
