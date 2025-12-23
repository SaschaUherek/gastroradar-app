<?php
declare(strict_types=1);

/**
 * GastroRadar – "Geheimes Login" ohne Login
 * - Zugriff nur mit geheimem Link (?key=...)
 * - Gerätebindung über Cookie + UA-Hash
 * - Steuerung über config/access.json (keine DB)
 */

function gr_access_file(): string {
    return __DIR__ . '/../config/access.json';
}

function gr_load_access(): array {
    $file = gr_access_file();
    if (!file_exists($file)) return [];
    $raw = file_get_contents($file);
    $data = json_decode((string)$raw, true);
    return is_array($data) ? $data : [];
}

function gr_save_access(array $data): bool {
    $file = gr_access_file();
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) return false;

    // atomar + mit Lock speichern
    $tmp = $file . '.tmp';
    if (file_put_contents($tmp, $json, LOCK_EX) === false) return false;
    return rename($tmp, $file);
}

function gr_today(): string {
    return (new DateTime('now'))->format('Y-m-d');
}

function gr_param_key(): ?string {
    // wir akzeptieren ?key=... oder ?k=...
    $key = $_GET['key'] ?? ($_GET['k'] ?? null);
    if ($key === null) return null;
    $key = trim((string)$key);
    return $key !== '' ? $key : null;
}

function gr_get_session_key(): ?string {
    return $_COOKIE['gr_key'] ?? null;
}

function gr_store_session_key(string $key): void {
    setcookie('gr_key', $key, [
        'expires'  => time() + 30 * 24 * 3600, // 30 Tage
        'path'     => '/',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE['gr_key'] = $key;
}

function gr_ua_hash(): string {
    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    // kleiner zusätzlicher "Salt" aus Accept-Language (optional)
    $al = (string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
    return hash('sha256', $ua . '|' . $al);
}

function gr_get_device_id(): string {
    $cookieName = 'gr_device';
    if (!empty($_COOKIE[$cookieName])) {
        $id = preg_replace('/[^a-zA-Z0-9]/', '', (string)$_COOKIE[$cookieName]);
        if ($id !== '') return $id;
    }

    // neue Device-ID erzeugen
    $id = bin2hex(random_bytes(16));

    setcookie($cookieName, $id, [
        'expires'  => time() + 365 * 24 * 3600,
        'path'     => '/',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    $_COOKIE[$cookieName] = $id;
    return $id;
}

function gr_block(string $title, string $text): void {
    http_response_code(403);
    ?>
    <!doctype html>
    <html lang="de">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= htmlspecialchars($title) ?></title>
        <style>
            body{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:#0b0b0b;color:#fff;margin:0;display:flex;min-height:100vh;align-items:center;justify-content:center;padding:24px}
            .box{max-width:540px;background:#151515;border:1px solid rgba(255,255,255,.08);border-radius:16px;padding:20px}
            h1{font-size:18px;margin:0 0 10px}
            p{margin:0 0 12px;opacity:.92;line-height:1.45}
            .hint{font-size:13px;opacity:.7}
        </style>
    </head>
    <body>
 <!--       <div class="box">
            <h1><?= htmlspecialchars($title) ?></h1>
            <p><?= htmlspecialchars($text) ?></p>
            <p class="hint">Wenn du Zugriff brauchst: schreib Sascha kurz bei WhatsApp.</p>
        </div>
-->
        <?php
        if (!$accessAllowed) {
            include __DIR__ . '/../partials/access-denied.php';
            exit;
        }       ?>
    </body>
    </html>
    <?php
    exit;
}

/**
 * Pflicht-Gate: prüft Key, Laufzeit, Gerätebindung
 * $appId z.B. "sascha", später pro Kundenordner.
 */
function gr_require_access(string $appId): void {
    $access = gr_load_access();

    if (empty($access['apps']) || empty($access['apps'][$appId]) || !is_array($access['apps'][$appId])) {
        gr_block('Kein Zugriff', 'Diese Installation ist nicht freigeschaltet.');
    }

    $app = $access['apps'][$appId];

    if (empty($app['enabled'])) {
        gr_block('Zugriff deaktiviert', 'Dieser Zugang ist aktuell deaktiviert.');
    }

    $key = gr_param_key();
    if ($key) {
        gr_store_session_key($key);
    } else {
        $key = gr_get_session_key();
    }

    // Ablauf prüfen
    $today = gr_today();
    if (!empty($app['expires_at']) && $today > (string)$app['expires_at']) {
        gr_block('Zugang abgelaufen', 'Die Test-/Laufzeit ist abgelaufen.');
    }

    // Gerätebindung
    $deviceId = gr_get_device_id();
    $uaHash   = gr_ua_hash();

    if (empty($app['devices']) || !is_array($app['devices'])) {
        $app['devices'] = [];
    }

    // devices: array aus objects [{id, ua, first_seen, last_seen}]
    $knownIndex = null;
    foreach ($app['devices'] as $i => $d) {
        if (!is_array($d)) continue;
        if (($d['id'] ?? '') === $deviceId) {
            $knownIndex = $i;
            break;
        }
    }

    if ($knownIndex === null) {
        $max = (int)($app['max_devices'] ?? 1);
        if (count($app['devices']) >= $max) {
            gr_block('Gerät nicht freigeschaltet', 'Maximale Anzahl Geräte erreicht.');
        }

        $app['devices'][] = [
            'id'         => $deviceId,
            'ua'         => $uaHash,
            'first_seen' => (new DateTime())->format(DATE_ATOM),
            'last_seen'  => (new DateTime())->format(DATE_ATOM),
        ];

        $access['apps'][$appId] = $app;
        gr_save_access($access);
        return;
    }

    // bekanntes Gerät: UA muss passen (sonst als "anderes Gerät" behandeln)
    $known = $app['devices'][$knownIndex];
    $knownUa = (string)($known['ua'] ?? '');

    if ($knownUa !== '' && $knownUa !== $uaHash) {
        // hier bewusst hart blocken: Cookie wurde kopiert oder Browser stark verändert
        gr_block('Gerät nicht freigeschaltet', 'Dieses Gerät wurde nicht erkannt.');
    }

    // last_seen updaten
    $app['devices'][$knownIndex]['last_seen'] = (new DateTime())->format(DATE_ATOM);
    $access['apps'][$appId] = $app;
    gr_save_access($access);
}