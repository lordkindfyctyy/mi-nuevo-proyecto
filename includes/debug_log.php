<?php

/**
 * Log de diagnóstico temporal, guardado en storage/logs/app.log —fuera de
 * public/, así que nunca es accesible por navegador— para poder ver
 * errores puntuales desde el Administrador de archivos de Hostinger
 * cuando el panel no ofrece un visor de log de errores de PHP.
 */
function app_debug_log(string $message): void
{
    $dir = __DIR__ . '/../storage/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    @file_put_contents($dir . '/app.log', $line, FILE_APPEND | LOCK_EX);
}
