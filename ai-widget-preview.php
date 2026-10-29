<?php
require_once __DIR__ . '/includes/functions.php';

if (!isLoggedIn()) {
    header('Location: ' . BASE_URL . 'login.php');
    exit();
}

$role = $_SESSION['user_role'] ?? '';
$dashboardByRole = [
    'mentor' => 'mentor/dashboard.php',
    'mentee' => 'mentee/dashboard.php',
    'admin' => 'admin/dashboard.php',
];

if (!isset($dashboardByRole[$role])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit();
}

header('Location: ' . BASE_URL . $dashboardByRole[$role]);
exit();
