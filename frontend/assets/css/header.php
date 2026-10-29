<?php
// includes/header.php - Mobile-optimized header
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <meta name="theme-color" content="#0d6efd">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="MentorMatch">
    <meta name="mobile-web-app-capable" content="yes">
    
    <title><?php echo $page_title ?? 'MentorMatch'; ?></title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Custom Styles -->
    <link href="<?php echo BASE_URL; ?>assets/css/style.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>assets/css/mobile.css" rel="stylesheet">
    
    <!-- Touch Icons -->
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo BASE_URL; ?>assets/icons/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo BASE_URL; ?>assets/icons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo BASE_URL; ?>assets/icons/favicon-16x16.png">
    
    <style>
        /* Additional inline styles for performance */
        .btn, a.btn, button, input[type="submit"], input[type="button"] {
            min-height: 44px;
            min-width: 44px;
        }
    </style>
</head>
<body>