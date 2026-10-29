<?php
// mentor/session_details.php
require_once '../includes/functions.php';
requireMentor();

$user = getCurrentUser();
$error = '';
$success = '';

if (!isset($_GET['id'])) {
    header('Location: sessions.php');
    exit();
}

$session_id = (int)$_GET['id'];

// Get session details
$stmt = $pdo->prepare("
    SELECT s.*, 
           u.name as mentee_name, u.email as mentee_email, u.phone as mentee_phone,
           u.programme as mentee_programme, u.year_of_study as mentee_year
    FROM sessions s
    JOIN users u ON s.mentee_id = u.id
    WHERE s.id = ? AND s.mentor_id = ?
");
$stmt->execute([$session_id, $user['id']]);
$session = $stmt->fetch();

if (!$session) {
    $error = 'Session not found or unauthorized.';
}

// Handle session actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['confirm'])) {
        $stmt = $pdo->prepare("UPDATE sessions SET status = 'confirmed' WHERE id = ? AND mentor_id = ?");
        $stmt->execute([$session_id, $user['id']]);
        $success = 'Session confirmed successfully!';
        logActivity($user['id'], 'Confirmed session #' . $session_id);
    } elseif (isset($_POST['complete'])) {
        $stmt = $pdo->prepare("UPDATE sessions SET status = 'completed' WHERE id = ? AND mentor_id = ?");
        $stmt->execute([$session_id, $user['id']]);
        $success = 'Session marked as completed!';
        logActivity($user['id'], 'Completed session #' . $session_id);
    } elseif (isset($_POST['cancel'])) {
        $stmt = $pdo->prepare("UPDATE sessions SET status = 'cancelled' WHERE id = ? AND mentor_id = ?");
        $stmt->execute([$session_id, $user['id']]);
        $success = 'Session cancelled.';
        logActivity($user['id'], 'Cancelled session #' . $session_id);
    }
    
    // Refresh session data
    $stmt = $pdo->prepare("
        SELECT s.*, u.name as mentee_name, u.email as mentee_email, u.phone as mentee_phone
        FROM sessions s
        JOIN users u ON s.mentee_id = u.id
        WHERE s.id = ? AND s.mentor_id = ?
    ");
    $stmt->execute([$session_id, $user['id']]);
    $session = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Session Details - Mentor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/mobile.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/mentor_nav.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-md-3 col-lg-2 mb-3">
                <?php include '../includes/mentor_sidebar.php'; ?>
            </div>
            
            <div class="col-md-9 col-lg-10">
                <h3><i class="bi bi-calendar-check"></i> Session Details</h3>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <?php if ($session): ?>
                    <div class="row">
                        <div class="col-md-8">
                            <div class="card shadow">
                                <div class="card-header">
                                    <h5 class="mb-0">Session #<?php echo $session['id']; ?></h5>
                                </div>
                                <div class="card-body">
                                    <table class="table table-borderless">
                                        <tr>
                                            <td><strong>Course:</strong></td>
                                            <td><?php echo htmlspecialchars($session['course_code']); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Mentee:</strong></td>
                                            <td>
                                                <?php echo htmlspecialchars($session['mentee_name']); ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($session['mentee_email']); ?></small>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($session['mentee_phone']); ?></small>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>Date & Time:</strong></td>
                                            <td><?php echo date('l, F d, Y h:i A', strtotime($session['session_date'])); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Duration:</strong></td>
                                            <td><?php echo $session['duration_minutes']; ?> minutes</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Status:</strong></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $session['status'] === 'completed' ? 'success' : 
                                                        ($session['status'] === 'confirmed' ? 'primary' : 
                                                        ($session['status'] === 'pending' ? 'warning' : 'danger')); 
                                                ?>">
                                                    <?php echo getSessionStatus($session['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>Payment Status:</strong></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $session['payment_status'] === 'released_to_mentor' ? 'success' : 
                                                        ($session['payment_status'] === 'paid_to_admin' ? 'info' : 'secondary'); 
                                                ?>">
                                                    <?php echo getPaymentStatus($session['payment_status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>Amount:</strong></td>
                                            <td>
                                                Total: <?php echo formatCurrency($session['total_amount']); ?><br>
                                                <small class="text-muted">Fee: <?php echo formatCurrency($session['admin_fee']); ?> | Payout: <?php echo formatCurrency($session['mentor_payout']); ?></small>
                                            </td>
                                        </tr>
                                        <?php if ($session['meeting_link']): ?>
                                            <tr>
                                                <td><strong>Meeting Link:</strong></td>
                                                <td><a href="<?php echo htmlspecialchars($session['meeting_link']); ?>" target="_blank">Join Session</a></td>
                                            </tr>
                                        <?php endif; ?>
                                    </table>
                                </div>
                            </div>
                            
                        </div>
                        
                        <div class="col-md-4">
                            <div class="card shadow">
                                <div class="card-header">
                                    <h5 class="mb-0">Actions</h5>
                                </div>
                                <div class="card-body">
                                    <?php if ($session['status'] === 'pending'): ?>
                                        <form method="POST" action="" class="d-grid gap-2">
                                            <button type="submit" name="confirm" class="btn btn-success">
                                                <i class="bi bi-check-circle"></i> Confirm Session
                                            </button>
                                            <button type="submit" name="cancel" class="btn btn-danger" 
                                                    onclick="return confirm('Cancel this session?')">
                                                <i class="bi bi-x-circle"></i> Cancel
                                            </button>
                                        </form>
                                    <?php elseif ($session['status'] === 'confirmed'): ?>
                                        <form method="POST" action="" class="d-grid gap-2">
                                            <button type="submit" name="complete" class="btn btn-success">
                                                <i class="bi bi-check-circle"></i> Mark as Completed
                                            </button>
                                            <button type="submit" name="cancel" class="btn btn-danger"
                                                    onclick="return confirm('Cancel this session?')">
                                                <i class="bi bi-x-circle"></i> Cancel
                                            </button>
                                        </form>
                                    <?php elseif ($session['status'] === 'completed'): ?>
                                        <div class="alert alert-success">
                                            <i class="bi bi-check-circle"></i> Session completed
                                        </div>
                                        <?php if ($session['payment_status'] !== 'released_to_mentor'): ?>
                                            <div class="alert alert-info">
                                                <i class="bi bi-clock"></i> Payment pending admin release
                                            </div>
                                        <?php else: ?>
                                            <div class="alert alert-success">
                                                <i class="bi bi-cash"></i> Payment released
                                            </div>
                                        <?php endif; ?>
                                    <?php elseif ($session['status'] === 'cancelled'): ?>
                                        <div class="alert alert-danger">
                                            <i class="bi bi-x-circle"></i> Session cancelled
                                        </div>
                                    <?php endif; ?>
                                    
                                    <a href="sessions.php" class="btn btn-secondary w-100 mt-2">
                                        <i class="bi bi-arrow-left"></i> Back to Sessions
                                    </a>
                                </div>
                            </div>
                            
                            <div class="card shadow mt-3">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="bi bi-info-circle"></i> Session Info</h5>
                                </div>
                                <div class="card-body">
                                    <ul class="list-unstyled">
                                        <li><strong>Created:</strong> <?php echo date('d M Y', strtotime($session['created_at'])); ?></li>
                                        <li><strong>Semester:</strong> <?php echo $session['semester_id']; ?></li>
                                        <li><strong>Transaction:</strong> <code><?php echo $session['transaction_id'] ?? 'N/A'; ?></code></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>