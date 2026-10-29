<?php
// mentee/profile.php
require_once dirname(__DIR__, 2) . '/backend/includes/functions.php';
requireMentee();

$user = getCurrentUser();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $phone = sanitize($_POST['phone']);
    $programme = sanitize($_POST['programme']);
    $year_of_study = sanitize($_POST['year_of_study']);
    $password = $_POST['password'] ?? '';
    
    // Update profile
    if (!empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ?, programme = ?, year_of_study = ?, password = ? WHERE id = ?");
        $stmt->execute([$name, $phone, $programme, $year_of_study, $hashed_password, $user['id']]);
    } else {
        $stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ?, programme = ?, year_of_study = ? WHERE id = ?");
        $stmt->execute([$name, $phone, $programme, $year_of_study, $user['id']]);
    }
    
    $success = 'Profile updated successfully!';
    logActivity($user['id'], 'Updated profile');
    
    // Refresh user data
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user['id']]);
    $user = $stmt->fetch();
    $_SESSION['user_name'] = $user['name'];
}

// Get mentee statistics
$stmt = $pdo->prepare("SELECT COUNT(*) as total_sessions FROM sessions WHERE mentee_id = ?");
$stmt->execute([$user['id']]);
$total_sessions = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) as completed_sessions FROM sessions WHERE mentee_id = ? AND status = 'completed'");
$stmt->execute([$user['id']]);
$completed_sessions = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT SUM(total_amount) as total_spent FROM sessions WHERE mentee_id = ? AND payment_status = 'paid_to_admin'");
$stmt->execute([$user['id']]);
$total_spent = $stmt->fetchColumn() ?? 0;

// Get enrolled courses
$stmt = $pdo->prepare("
    SELECT me.*, u.name as reviewer_name
    FROM mentee_enrollments me
    LEFT JOIN users u ON me.reviewed_by = u.id
    WHERE me.mentee_id = ?
    ORDER BY me.created_at DESC
");
$stmt->execute([$user['id']]);
$enrollments = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Mentee</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include dirname(__DIR__, 2) . '/frontend/includes/mentee_nav.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-md-3 col-lg-2 mb-3">
                <?php include dirname(__DIR__, 2) . '/frontend/includes/mentee_sidebar.php'; ?>
            </div>
            
            <div class="col-md-9 col-lg-10">
                <h3><i class="bi bi-person"></i> My Profile</h3>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="card shadow text-center">
                            <div class="card-body">
                                <div class="profile-avatar mx-auto mb-3 bg-info text-white d-flex align-items-center justify-content-center" 
                                     style="width: 120px; height: 120px; border-radius: 50%; font-size: 48px;">
                                    <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                </div>
                                <h4><?php echo htmlspecialchars($user['name']); ?></h4>
                                <p class="text-muted"><?php echo ucfirst($user['role']); ?></p>
                                <div class="alert alert-light border mb-3">
                                    <small class="text-muted d-block">Your MentorMatch user reference</small>
                                    <strong class="fs-5"><?php echo htmlspecialchars(getUserReference($user['id']), ENT_QUOTES, 'UTF-8'); ?></strong>
                                    <small class="d-block text-muted">Use this reference when paying your subscription.</small>
                                </div>
                                <hr>
                                <div class="row">
                                    <div class="col-4">
                                        <h5><?php echo $total_sessions; ?></h5>
                                        <small class="text-muted">Total</small>
                                    </div>
                                    <div class="col-4">
                                        <h5><?php echo $completed_sessions; ?></h5>
                                        <small class="text-muted">Completed</small>
                                    </div>
                                    <div class="col-4">
                                        <h5><?php echo formatCurrency($total_spent); ?></h5>
                                        <small class="text-muted">Spent</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card shadow mt-3">
                            <div class="card-header">
                                <h5 class="mb-0">Enrolled Courses</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($enrollments): ?>
                                    <ul class="list-group list-group-flush">
                                        <?php foreach ($enrollments as $enrollment): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <?php echo htmlspecialchars($enrollment['course_code']); ?>
                                                <span class="badge bg-<?php 
                                                    echo $enrollment['status'] === 'active' ? 'success' : 
                                                        ($enrollment['status'] === 'pending' ? 'warning' : 'danger'); 
                                                ?>">
                                                    <?php echo ucfirst($enrollment['status']); ?>
                                                </span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <p class="text-muted">No courses enrolled.</p>
                                    <a href="search.php" class="btn btn-sm btn-primary">Find a Mentor</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-8">
                        <div class="card shadow">
                            <div class="card-header">
                                <h5 class="mb-0">Edit Profile</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Full Name *</label>
                                        <input type="text" class="form-control" id="name" name="name" 
                                               value="<?php echo htmlspecialchars($user['name']); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email (Read Only)</label>
                                        <input type="email" class="form-control" id="email" 
                                               value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                                    </div>
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">Phone Number *</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" 
                                               value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="programme" class="form-label">Programme</label>
                                        <input type="text" class="form-control" id="programme" name="programme" 
                                               value="<?php echo htmlspecialchars($user['programme']); ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="year_of_study" class="form-label">Year of Study</label>
                                        <select class="form-select" id="year_of_study" name="year_of_study">
                                            <option value="">Select Year</option>
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <option value="<?php echo $i; ?>" <?php echo $user['year_of_study'] == $i ? 'selected' : ''; ?>>
                                                    Year <?php echo $i; ?>
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <hr>
                                    <div class="mb-3">
                                        <label for="password" class="form-label">New Password (Leave blank to keep current)</label>
                                        <input type="password" class="form-control" id="password" name="password" 
                                               placeholder="Enter new password">
                                    </div>
                                    <button type="submit" class="btn btn-primary w-100">Update Profile</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>