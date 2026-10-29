<?php
// mentee/session_details.php
require_once '../includes/functions.php';
requireMentee();

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
           u.name as mentor_name, u.email as mentor_email, u.phone as mentor_phone,
           u.programme as mentor_programme, u.year_of_study as mentor_year
    FROM sessions s
    JOIN users u ON s.mentor_id = u.id
    WHERE s.id = ? AND s.mentee_id = ?
");
$stmt->execute([$session_id, $user['id']]);
$session = $stmt->fetch();

if (!$session) {
    $error = 'Session not found or unauthorized.';
}

// Get feedback if exists
$stmt = $pdo->prepare("SELECT * FROM feedback WHERE session_id = ?");
$stmt->execute([$session_id]);
$feedback = $stmt->fetch();

// Handle session actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['confirm_completion'])) {
        $stmt = $pdo->prepare("UPDATE sessions SET status = 'completed' WHERE id = ? AND mentee_id = ?");
        $stmt->execute([$session_id, $user['id']]);
        $success = 'Session marked as completed!';
        logActivity($user['id'], 'Confirmed completion of session #' . $session_id);
    } elseif (isset($_POST['cancel'])) {
        $stmt = $pdo->prepare("UPDATE sessions SET status = 'cancelled' WHERE id = ? AND mentee_id = ?");
        $stmt->execute([$session_id, $user['id']]);
        $success = 'Session cancelled.';
        logActivity($user['id'], 'Cancelled session #' . $session_id);
    } elseif (isset($_POST['submit_feedback'])) {
        $rating = (int)$_POST['rating'];
        $comments = sanitize($_POST['comments']);
        
        $stmt = $pdo->prepare("INSERT INTO feedback (session_id, mentee_id, mentor_id, rating, comments) 
                               VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$session_id, $user['id'], $session['mentor_id'], $rating, $comments]);
        
        $success = 'Feedback submitted successfully!';
        logActivity($user['id'], 'Submitted feedback for session #' . $session_id);
        
        // Refresh feedback
        $stmt = $pdo->prepare("SELECT * FROM feedback WHERE session_id = ?");
        $stmt->execute([$session_id]);
        $feedback = $stmt->fetch();
    }
    
    // Refresh session data
    $stmt = $pdo->prepare("
        SELECT s.*, u.name as mentor_name, u.email as mentor_email, u.phone as mentor_phone
        FROM sessions s
        JOIN users u ON s.mentor_id = u.id
        WHERE s.id = ? AND s.mentee_id = ?
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
    <title>Session Details - Mentee</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/mobile.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/mentee_nav.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-md-3 col-lg-2 mb-3">
                <?php include '../includes/mentee_sidebar.php'; ?>
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
                                            <td><strong>Mentor:</strong></td>
                                            <td>
                                                <?php echo htmlspecialchars($session['mentor_name']); ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($session['mentor_email']); ?></small>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($session['mentor_phone']); ?></small>
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
                                                <small class="text-muted">Includes 5% service fee</small>
                                            </td>
                                        </tr>
                                        <?php if ($session['meeting_link']): ?>
                                            <tr>
                                                <td><strong>Meeting Link:</strong></td>
                                                <td><a href="<?php echo htmlspecialchars($session['meeting_link']); ?>" target="_blank" class="btn btn-sm btn-primary">
                                                    <i class="bi bi-video"></i> Join Session
                                                </a></td>
                                            </tr>
                                        <?php endif; ?>
                                    </table>
                                </div>
                            </div>
                            
                            <!-- Feedback Section -->
                            <?php if ($session['status'] === 'completed'): ?>
                                <div class="card shadow mt-3">
                                    <div class="card-header">
                                        <h5 class="mb-0"><i class="bi bi-star"></i> Feedback</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if ($feedback): ?>
                                            <div class="mb-2">
                                                <strong>Your Rating:</strong>
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <span class="text-<?php echo $i <= $feedback['rating'] ? 'warning' : 'secondary'; ?>">
                                                        <i class="bi bi-star-fill"></i>
                                                    </span>
                                                <?php endfor; ?>
                                            </div>
                                            <?php if ($feedback['comments']): ?>
                                                <p><strong>Your Comments:</strong></p>
                                                <p class="border p-3 bg-light rounded"><?php echo nl2br(htmlspecialchars($feedback['comments'])); ?></p>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <form method="POST" action="">
                                                <div class="mb-3">
                                                    <label class="form-label">Rating *</label>
                                                    <div class="rating-stars">
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <div class="form-check form-check-inline">
                                                                <input class="form-check-input" type="radio" name="rating" value="<?php echo $i; ?>" id="star<?php echo $i; ?>" required>
                                                                <label class="form-check-label" for="star<?php echo $i; ?>"><?php echo $i; ?>★</label>
                                                            </div>
                                                        <?php endfor; ?>
                                                    </div>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="comments" class="form-label">Comments</label>
                                                    <textarea class="form-control" id="comments" name="comments" rows="3" 
                                                              placeholder="Share your experience..."></textarea>
                                                </div>
                                                <button type="submit" name="submit_feedback" class="btn btn-primary">
                                                    <i class="bi bi-send"></i> Submit Feedback
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="card shadow">
                                <div class="card-header">
                                    <h5 class="mb-0">Actions</h5>
                                </div>
                                <div class="card-body">
                                    <?php if ($session['status'] === 'pending' && $session['payment_status'] === 'pending'): ?>
                                        <a href="payment.php?session_id=<?php echo $session['id']; ?>" 
                                           class="btn btn-success w-100 mb-2">
                                            <i class="bi bi-credit-card"></i> Pay Now
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if ($session['status'] === 'confirmed' || $session['status'] === 'pending'): ?>
                                        <form method="POST" action="" class="d-grid gap-2">
                                            <button type="submit" name="cancel" class="btn btn-danger" 
                                                    onclick="return confirm('Cancel this session?')">
                                                <i class="bi bi-x-circle"></i> Cancel Session
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <?php if ($session['status'] === 'completed'): ?>
                                        <div class="alert alert-success">
                                            <i class="bi bi-check-circle"></i> Session completed
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($session['status'] === 'cancelled'): ?>
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