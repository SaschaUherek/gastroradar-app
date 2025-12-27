<?php
session_start();

function gr_require_access(string $customer) {
    $configFile = __DIR__ . '/../config/customers.json';

    if (!file_exists($configFile)) {
        return;
    }

    $customers = json_decode(file_get_contents($configFile), true);

    if (
        empty($customers[$customer]) ||
        empty($customers[$customer]['active'])
    ) {
        exit('App nicht aktiv.');
    }

    // schon eingeloggt?
    if (!empty($_SESSION['gr_logged_in']) && $_SESSION['gr_logged_in'] === $customer) {
        return;
    }

    // sonst Login anzeigen
    include __DIR__ . '/../partials/login-overlay.php';
    exit;
}