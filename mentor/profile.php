<?php
// mentor/profile.php
require_once '../includes/functions.php';
requireMentor();

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

// Get mentor statistics
$stmt = $pdo->prepare("SELECT COUNT(*) as total_sessions FROM sessions WHERE mentor_id = ? AND status = 'completed'");
$stmt->execute([$user['id']]);
$total_sessions = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT SUM(mentor_payout) as total_earnings FROM sessions WHERE mentor_id = ? AND payment_status = 'released_to_mentor'");
$stmt->execute([$user['id']]);
$total_earnings = $stmt->fetchColumn() ?? 0;

$has_subscription = hasActiveSubscription($user['id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Mentor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/mentor_nav.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-md-3 col-lg-2 mb-3">
                <?php include '../includes/mentor_sidebar.php'; ?>
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
                                <div class="profile-avatar mx-auto mb-3 bg-primary text-white d-flex align-items-center justify-content-center" 
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
                                        <small class="text-muted">Sessions</small>
                                    </div>
                                    <div class="col-4">
                                        <h5><?php echo formatCurrency($total_earnings); ?></h5>
                                        <small class="text-muted">Earnings</small>
                                    </div>
                                    <div class="col-4">
                                        <h5><?php echo $has_subscription ? 'Visible' : 'Hidden'; ?></h5>
                                        <small class="text-muted">Search status</small>
                                    </div>
                                </div>
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