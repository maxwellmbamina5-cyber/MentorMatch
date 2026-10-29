<?php
// admin/profile.php
require_once '../includes/functions.php';
requireAdmin();

$user = getCurrentUser();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $phone = sanitize($_POST['phone']);
    $password = $_POST['password'] ?? '';
    
    // Update profile
    if (!empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ?, password = ? WHERE id = ?");
        $stmt->execute([$name, $phone, $hashed_password, $user['id']]);
    } else {
        $stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ? WHERE id = ?");
        $stmt->execute([$name, $phone, $user['id']]);
    }
    
    $success = 'Profile updated successfully!';
    logActivity($user['id'], 'Updated admin profile');
    
    // Refresh user data
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user['id']]);
    $user = $stmt->fetch();
    $_SESSION['user_name'] = $user['name'];
}

// Get statistics for admin
$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role != 'admin'");
$total_users = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) as total FROM sessions");
$total_sessions = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT SUM(total_amount) as total FROM sessions WHERE payment_status = 'paid_to_admin'");
$total_revenue = $stmt->fetchColumn() ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/admin_nav.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-md-3 col-lg-2 mb-3">
                <?php include '../includes/admin_sidebar.php'; ?>
            </div>
            
            <div class="col-md-9 col-lg-10">
                <h3><i class="bi bi-person"></i> Admin Profile</h3>
                
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
                                <div class="profile-avatar mx-auto mb-3 bg-danger text-white d-flex align-items-center justify-content-center" 
                                     style="width: 120px; height: 120px; border-radius: 50%; font-size: 48px;">
                                    <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                </div>
                                <h4><?php echo htmlspecialchars($user['name']); ?></h4>
                                <p class="text-muted">System Administrator</p>
                                <div class="alert alert-light border mb-3">
                                    <small class="text-muted d-block">Your MentorMatch user reference</small>
                                    <strong class="fs-5"><?php echo htmlspecialchars(getUserReference($user['id']), ENT_QUOTES, 'UTF-8'); ?></strong>
                                </div>
                                <hr>
                                <div class="row">
                                    <div class="col-4">
                                        <h5><?php echo $total_users; ?></h5>
                                        <small class="text-muted">Users</small>
                                    </div>
                                    <div class="col-4">
                                        <h5><?php echo $total_sessions; ?></h5>
                                        <small class="text-muted">Sessions</small>
                                    </div>
                                    <div class="col-4">
                                        <h5><?php echo formatCurrency($total_revenue); ?></h5>
                                        <small class="text-muted">Revenue</small>
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
                                        <label for="phone" class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" 
                                               value="<?php echo htmlspecialchars($user['phone']); ?>">
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