<?php
session_start();

$data = json_decode(file_get_contents('php://input'), true);

$customer = $data['customer'] ?? null;
$password = $data['password'] ?? null;

$configFile = __DIR__ . '/../config/customers.json';
$customers = json_decode(file_get_contents($configFile), true);

if (
    !$customer ||
    empty($customers[$customer]) ||
    $customers[$customer]['password'] !== $password
) {
    http_response_code(403);
    exit;
}

$_SESSION['gr_logged_in'] = $customer;