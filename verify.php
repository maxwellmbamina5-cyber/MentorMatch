<?php
// verify.php - Public certificate verification
require_once 'includes/functions.php';

$error = '';
$certificate = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $verification_code = sanitize($_POST['verification_code']);
    
    if (empty($verification_code)) {
        $error = 'Please enter a verification code.';
    } else {
        $stmt = $pdo->prepare("
            SELECT c.*, u.name as mentor_name, u.email as mentor_email,
                   s.semester_name
            FROM certificates c
            JOIN users u ON c.mentor_id = u.id
            JOIN semesters s ON c.semester_id = s.id
            WHERE c.certificate_hash LIKE ? OR c.id = ?
        ");
        $stmt->execute(["%$verification_code%", (int)$verification_code]);
        $certificate = $stmt->fetch();
        
        if (!$certificate) {
            $error = 'Invalid verification code. Please check and try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Certificate - MentorMatch</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="text-center mb-4">
                    <h1>MentorMatch</h1>
                    <h4 class="text-muted">Certificate Verification</h4>
                </div>
                
                <div class="card shadow">
                    <div class="card-body p-5">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <?php if ($certificate): ?>
                            <div class="text-center">
                                <div class="display-1 text-success">
                                    <i class="bi bi-patch-check-fill"></i>
                                </div>
                                <h3 class="text-success">Certificate Verified!</h3>
                                <p class="text-muted">This certificate is valid and issued by MentorMatch.</p>
                                
                                <div class="card bg-light mt-4">
                                    <div class="card-body">
                                        <table class="table table-borderless">
                                            <tr>
                                                <td><strong>Certificate ID:</strong></td>
                                                <td><code><?php echo substr($certificate['certificate_hash'], 0, 12); ?></code></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Recipient:</strong></td>
                                                <td><?php echo htmlspecialchars($certificate['mentor_name']); ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Email:</strong></td>
                                                <td><?php echo htmlspecialchars($certificate['mentor_email']); ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Semester:</strong></td>
                                                <td><?php echo htmlspecialchars($certificate['semester_name']); ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Total Hours:</strong></td>
                                                <td><?php echo number_format($certificate['total_hours'], 1); ?> hours</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Total Sessions:</strong></td>
                                                <td><?php echo $certificate['total_sessions']; ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Issued Date:</strong></td>
                                                <td><?php echo date('d F Y', strtotime($certificate['issue_date'])); ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Status:</strong></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $certificate['status'] === 'issued' ? 'success' : 'warning'; ?>">
                                                        <?php echo ucfirst($certificate['status']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label for="verification_code" class="form-label">Enter Certificate Verification Code</label>
                                    <input type="text" class="form-control form-control-lg" id="verification_code" 
                                           name="verification_code" placeholder="e.g. 12345 or abc123" required>
                                    <small class="text-muted">Enter the certificate ID or verification code shown on the certificate.</small>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search"></i> Verify Certificate
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <div class="text-center mt-3">
                            <a href="index.php" class="text-decoration-none">Back to Home</a>
                        </div>
                    </div>
                </div>
                
                <div class="text-center mt-4 text-muted">
                    <small>&copy; <?php echo date('Y'); ?> MentorMatch. All rights reserved.</small>
                </div>
            </div>
        </div>
    </div>
</body>
</html>