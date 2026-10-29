<?php
require_once 'includes/functions.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] === 'suspended') {
                $error = 'Your account has been suspended. Please contact admin.';
            } elseif ($user['status'] === 'rejected') {
                $error = 'Your registration was rejected.';
            } else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_name'] = $user['name'];

                logActivity($user['id'], 'User logged in');

                if ($user['role'] === 'admin') {
                    header('Location: admin/dashboard.php');
                } elseif ($user['role'] === 'mentor') {
                    header('Location: mentor/dashboard.php');
                } else {
                    header('Location: mentee/dashboard.php');
                }
                exit();
            }
        } else {
            $error = 'Invalid email or password';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#123c43">
    <title>Sign in | MentorMatch</title>
    <link rel="icon" type="image/svg+xml" href="assets/images/mentormatch-mark.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link href="assets/css/mobile.css" rel="stylesheet">
</head>
<body class="auth-page">
    <a class="skip-link" href="#login-form">Skip to sign in</a>
    <main class="container">
        <div class="auth-shell row g-0 mx-auto">
            <div class="col-md-5 d-none d-md-flex">
                <section class="auth-story" aria-label="Learn together">
                    <a class="auth-brand text-white mb-auto" href="index.php"><img class="brand-mark-image" src="assets/images/mentormatch-mark.svg" alt=""> MentorMatch</a>
                    <div>
                        <h2>Make your next learning goal a little closer.</h2>
                        <p>Find a fellow student who can help you move forward, one session at a time.</p>
                    </div>
                </section>
            </div>
            <div class="col-md-7">
                <section class="auth-form-panel" id="login-form">
                    <a class="auth-brand mb-4 d-inline-flex d-md-none" href="index.php"><img class="brand-mark-image" src="assets/images/mentormatch-mark.svg" alt=""> MentorMatch</a>
                    <span class="eyebrow mb-2">Welcome back</span>
                    <h1>Sign in to your account</h1>
                    <p class="text-muted mb-4">Pick up where your learning journey left off.</p>

                    <?php if ($error): ?>
                        <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email address</label>
                            <input type="email" class="form-control" id="email" name="email" autocomplete="email" value="<?php echo htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>
                        <div class="mb-4">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" autocomplete="current-password" required>
                        </div>
                        <button type="submit" class="btn btn-primary btn-lg w-100">Sign in <i class="bi bi-arrow-right ms-2" aria-hidden="true"></i></button>
                    </form>

                    <p class="text-center text-muted mt-4 mb-0">New to MentorMatch? <a href="register.php" class="fw-semibold">Create an account</a></p>
                    <p class="text-center mt-3 mb-0"><a href="index.php" class="small text-muted text-decoration-none"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Back to home</a></p>
                </section>
            </div>
        </div>
    </main>
</body>
</html>
