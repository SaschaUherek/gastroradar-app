document.getElementById('gr-login-btn').addEventListener('click', async () => {
  const pw = document.getElementById('gr-password').value;

  const res = await fetch('/public/login.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      customer: window.GR_CUSTOMER,
      password: pw
    })
  });

  if (res.ok) {
    location.reload();
  } else {
    alert('Falsches Passwort');
  }
});