<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Zugriff</title>
<link rel="stylesheet" href="/assets/style.css">
</head>
<body class="login-lock">

<div class="login-overlay">
  <div class="login-box">
    <h1>GastroRadar</h1>
    <p>Bitte Passwort eingeben</p>

    <input type="password" id="gr-password" placeholder="Passwort">
    <button id="gr-login-btn">Zugriff öffnen</button>

    <p class="login-hint">
      Passwort vergessen? <br>
      Schreib Sascha kurz bei WhatsApp.
    </p>
  </div>
</div>

<script>
  window.GR_CUSTOMER = "<?= htmlspecialchars($customer) ?>";
</script>
<script src="/assets/login.js"></script>

</body>
</html>