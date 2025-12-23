<?php
$accessFile = __DIR__ . '/../config/access.json';

if (!file_exists($accessFile)) {
    die('access.json nicht gefunden');
}

$data = json_decode(file_get_contents($accessFile), true);
if (!$data || empty($data['apps']['sascha'])) {
    die('App "sascha" nicht vorhanden');
}

$app = &$data['apps']['sascha'];
$message = null;

// Geräte zurücksetzen
if (isset($_POST['reset_devices'])) {
    $app['devices'] = [];
    file_put_contents(
        $accessFile,
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );
    $message = "Alle Geräte wurden zurückgesetzt.";
}

// speichern
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newMax = (int)($_POST['max_devices'] ?? 0);

    if ($newMax >= 1 && $newMax <= 10) {
        $app['max_devices'] = $newMax;
        file_put_contents(
            $accessFile,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
        $message = "max_devices wurde auf {$newMax} gesetzt.";
    } else {
        $message = "Bitte eine Zahl zwischen 1 und 10 eingeben.";
    }
}
?>

<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Demo · Geräte-Limit</title>
<style>
body {
  font-family: system-ui, sans-serif;
  background: #111;
  color: #fff;
  padding: 40px;
}
.box {
  max-width: 360px;
  margin: auto;
  background: #1c1c1c;
  border-radius: 12px;
  padding: 24px;
}
label {
  display: block;
  margin-bottom: 6px;
  font-weight: 600;
}
input {
  width: 100%;
  padding: 10px;
  border-radius: 8px;
  border: none;
  margin-bottom: 12px;
}
button {
  width: 100%;
  padding: 10px;
  border-radius: 8px;
  border: none;
  background: #ff6a00;
  color: #000;
  font-weight: 700;
  cursor: pointer;
}
.note {
  margin-top: 16px;
  font-size: 0.85rem;
  opacity: 0.7;
}
.msg {
  margin-bottom: 12px;
  color: #8aff8a;
}
</style>
</head>

<body>
<div class="box">
  <h1>Demo: Geräte-Limit</h1>

  <?php if ($message): ?>
    <div class="msg"><?= htmlspecialchars($message) ?></div>
  <?php endif; ?>

  <form method="post">
    <label>Maximale Geräte</label>
    <input
      type="number"
      name="max_devices"
      min="1"
      max="10"
      value="<?= (int)$app['max_devices'] ?>"
    >
    <button>Speichern</button>
  </form>

  <form method="post" style="margin-top:20px">
  <button
    type="submit"
    name="reset_devices"
    style="
      background:#444;
      color:#fff;
      border:1px solid #666;
      margin-top:8px;
    "
  >
    🔄 Geräte zurücksetzen
  </button>
</form>

  <div class="note">
    Nur für Demo-Zwecke.<br>
    Greift sofort bei nächstem Seitenaufruf.
  </div>
</div>
</body>
</html>