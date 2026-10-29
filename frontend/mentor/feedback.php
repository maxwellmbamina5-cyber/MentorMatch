<?php
require_once dirname(__DIR__, 2) . '/backend/includes/functions.php';
requireMentor();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Feedback privacy | MentorMatch</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/mobile.css" rel="stylesheet">
</head>
<body>
    <?php include dirname(__DIR__, 2) . '/frontend/includes/mentor_nav.php'; ?>
    <main class="container py-5" style="max-width: 720px;">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4 p-md-5 text-center">
                <i class="bi bi-shield-lock display-4 text-primary" aria-hidden="true"></i>
                <h1 class="h3 mt-3">Feedback is private</h1>
                <p class="text-muted mb-4">Ratings and written feedback are not displayed to mentors. You can still use the mentor coach on your dashboard for general tips on improving your sessions.</p>
                <a class="btn btn-primary" href="dashboard.php">Return to dashboard</a>
            </div>
        </div>
    </main>
</body>
</html>
