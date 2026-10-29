<?php
// admin/cache.php - Clear system cache
require_once '../includes/functions.php';
requireAdmin();

if (isset($_GET['clear'])) {
    // Clear sessions (don't clear PHP sessions)
    // Clear any cached files
    $cache_dir = '../cache';
    if (is_dir($cache_dir)) {
        $files = scandir($cache_dir);
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..') {
                unlink($cache_dir . '/' . $file);
            }
        }
    }
    
    logActivity($_SESSION['user_id'], 'Cleared system cache');
    $_SESSION['flash_message'] = 'Cache cleared successfully!';
}

header('Location: settings.php');
exit();
?>