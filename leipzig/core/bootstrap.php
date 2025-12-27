<?php
declare(strict_types=1);

/**
 * GastroRadar Core Bootstrap
 * - setzt Pfade
 * - lädt Auth
 * - stellt APP_ID bereit
 */

if (!defined('APP_ID') || !APP_ID) {
    http_response_code(500);
    echo "APP_ID fehlt.";
    exit;
}

// absolute Root-Pfade (Server-Dateisystem)
define('GR_ROOT', dirname(__DIR__));                 // /.../gastroradar.info
define('GR_CORE', GR_ROOT . '/core');
define('GR_CONFIG', GR_ROOT . '/config');
define('GR_DATA', GR_ROOT . '/data');
define('GR_PARTIALS', GR_ROOT . '/partials');

// Web-Base: immer von Domain-Root aus arbeiten (empfohlen)
define('GR_WEB_ROOT', '/');                          // Domain-Root
define('GR_ASSETS', GR_WEB_ROOT . 'assets/');        // z.B. /assets/style.css
define('GR_IMG', GR_WEB_ROOT . 'img/');              // z.B. /img/RBLogo.png

// Auth prüfen (setzt ggf. Zugriff / schreibt devices)
require_once GR_CORE . '/auth.php';