<?php
// admin/settings.php
require_once dirname(__DIR__, 2) . '/backend/includes/functions.php';
requireAdmin();

$user = getCurrentUser();
$error = '';
$success = '';
$password_changed = false;
$mobile_money_number = getSystemSetting('mobile_money_number', MOMO_PHONE);

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = 'Your action could not be verified. Reload the page and try again.';
    } elseif (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'Please fill in all password fields';
    } elseif (strlen($new_password) < 6) {
        $error = 'New password must be at least 6 characters';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New passwords do not match';
    } else {
        // Verify current password
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$user['id']]);
        $db_password = $stmt->fetchColumn();
        
        if (!password_verify($current_password, $db_password)) {
            $error = 'Current password is incorrect';
        } else {
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $user['id']]);
            
            logActivity($user['id'], 'Changed password');
            $success = 'Password changed successfully! Please login again.';
            $password_changed = true;
            
            // Logout user after password change
            session_destroy();
            header('Refresh: 3; URL=../login.php');
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_mobile_money_number'])) {
    $new_mobile_money_number = trim($_POST['mobile_money_number'] ?? '');
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = 'Your action could not be verified. Reload the page and try again.';
    } elseif ($new_mobile_money_number !== '' && !preg_match('/^\+?[0-9][0-9\s-]{5,19}$/', $new_mobile_money_number)) {
        $error = 'Enter a valid mobile-money phone number, or leave it blank to hide payment instructions.';
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO system_settings (setting_key, setting_value)
                VALUES ('mobile_money_number', ?)
                ON CONFLICT (setting_key) DO UPDATE
                SET setting_value = EXCLUDED.setting_value
            ");
            $stmt->execute([$new_mobile_money_number]);
            $mobile_money_number = $new_mobile_money_number;
            $success = 'Mobile-money payment number saved.';
            logActivity($user['id'], 'Updated mobile-money subscription number');
        } catch (PDOException $exception) {
            error_log('Mobile-money setting update failed: ' . $exception->getMessage());
            $error = 'The mobile-money number could not be saved. Please try again.';
        }
    }
}

// Get system statistics for display
$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role != 'admin'");
$total_users = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) as total FROM sessions");
$total_sessions = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) as total FROM payments WHERE payment_status = 'captured'");
$total_payments = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT SUM(total_amount) as total FROM sessions WHERE payment_status = 'paid_to_admin'");
$total_revenue = $stmt->fetchColumn() ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .settings-card {
            transition: all 0.3s ease;
        }
        .settings-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .password-requirements {
            font-size: 0.85rem;
            color: #6c757d;
        }
        .password-requirements li {
            margin-bottom: 3px;
        }
        .password-requirements .valid {
            color: #28a745;
        }
        .password-requirements .invalid {
            color: #dc3545;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
        }
        .stat-label {
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6c757d;
        }
        .password-strength {
            height: 4px;
            border-radius: 2px;
            margin-top: 5px;
            transition: all 0.3s ease;
        }
        .password-strength.weak {
            background: #dc3545;
            width: 25%;
        }
        .password-strength.medium {
            background: #ffc107;
            width: 50%;
        }
        .password-strength.strong {
            background: #28a745;
            width: 75%;
        }
        .password-strength.very-strong {
            background: #198754;
            width: 100%;
        }
        .logout-timer {
            font-weight: bold;
            color: #dc3545;
        }
        .security-tips {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
        }
        .security-tips i {
            color: var(--mm-primary);
        }
        .icon-circle {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(23, 107, 103, 0.1);
            color: var(--mm-primary);
            font-size: 1.5rem;
        }
        @media (max-width: 768px) {
            .stat-number { font-size: 1.5rem; }
        }
    </style>
</head>
<body>
    <?php include dirname(__DIR__, 2) . '/frontend/includes/admin_nav.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-md-3 col-lg-2 mb-3">
                <?php include dirname(__DIR__, 2) . '/frontend/includes/admin_sidebar.php'; ?>
            </div>
            
            <div class="col-md-9 col-lg-10">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h3><i class="bi bi-gear"></i> Settings</h3>
                        <p class="text-muted">Manage your account settings and security.</p>
                    </div>
                    <div>
                        <span class="badge bg-secondary">
                            <i class="bi bi-person"></i> <?php echo htmlspecialchars($user['name']); ?>
                        </span>
                    </div>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i> <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php if ($password_changed): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> 
                        You will be redirected to the login page in <span class="logout-timer" id="timer">3</span> seconds.
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
                
                <div class="row">
                    <!-- Password Change -->
                    <div class="col-md-7">
                        <div class="card shadow settings-card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-lock"></i> Change Password</h5>
                            </div>
                            <div class="card-body">
                                <?php if (!$password_changed): ?>
                                    <form method="POST" action="" id="passwordForm">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                        <div class="mb-3">
                                            <label for="current_password" class="form-label">Current Password <span class="text-danger">*</span></label>
                                            <div class="password-toggle">
                                                <input type="password" class="form-control" id="current_password" name="current_password" 
                                                       placeholder="Enter your current password" required>
                                                <button type="button" class="toggle-btn" onclick="togglePassword('current_password')">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                            </div>

                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="new_password" class="form-label">New Password <span class="text-danger">*</span></label>
                                            <div class="password-toggle">
                                                <input type="password" class="form-control" id="new_password" name="new_password" 
                                                       placeholder="Enter new password (min 6 characters)" required minlength="6" onkeyup="checkPasswordStrength(this.value)">
                                                <button type="button" class="toggle-btn" onclick="togglePassword('new_password')">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                            </div>
                                            <div class="password-strength" id="strengthBar"></div>
                                            <small class="text-muted" id="strengthText">Enter a password to check strength</small>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="confirm_password" class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                                            <div class="password-toggle">
                                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                                       placeholder="Re-enter new password" required>
                                                <button type="button" class="toggle-btn" onclick="togglePassword('confirm_password')">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                            </div>
                                            <div id="confirmMatch" class="small mt-1"></div>
                                        </div>
                                        
                                        <div class="password-requirements mb-3">
                                            <strong>Password Requirements:</strong>
                                            <ul class="list-unstyled mb-0">
                                                <li id="req-length"><i class="bi bi-<?php echo isset($_POST['new_password']) && strlen($_POST['new_password']) >= 6 ? 'check-circle text-success' : 'circle'; ?>"></i> At least 6 characters</li>
                                            </ul>
                                        </div>
                                        
                                        <div class="security-tips mb-3">
                                            <strong><i class="bi bi-shield-check"></i> Security Tips:</strong>
                                            <ul class="mb-0">
                                                <li>Use a strong password with a mix of letters, numbers, and symbols</li>
                                                <li>Do not share your password with anyone</li>
                                                <li>Change your password regularly</li>
                                            </ul>
                                        </div>
                                        
                                        <button type="submit" name="change_password" class="btn btn-primary w-100">
                                            <i class="bi bi-key"></i> Change Password
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Account Info & Stats -->
                    <div class="col-md-5">
                        <!-- Account Info -->
                        <div class="card shadow settings-card mb-3">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-person-circle"></i> Account Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="icon-circle me-3">
                                        <i class="bi bi-person-fill"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-0"><?php echo htmlspecialchars($user['name']); ?></h6>
                                        <small class="text-muted"><?php echo htmlspecialchars($user['email']); ?></small>
                                    </div>
                                </div>

                                <hr>
                                <div class="row">
                                    <div class="col-6">
                                        <small class="text-muted">Role</small>
                                        <p class="fw-bold"><?php echo ucfirst($user['role']); ?></p>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">Phone</small>
                                        <p class="fw-bold"><?php echo htmlspecialchars($user['phone']); ?></p>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-6">
                                        <small class="text-muted">Member Since</small>
                                        <p class="fw-bold"><?php echo date('d M Y', strtotime($user['created_at'])); ?></p>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">Status</small>
                                        <p class="fw-bold">
                                            <span class="badge bg-success">Active</span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card shadow settings-card mb-3">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-phone"></i> Subscription payment number</h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted">Users will see this number on the subscription page when paying through mobile money. Confirm payments manually using the user's reference.</p>
                                <form method="POST" action="">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                    <div class="mb-3">
                                        <label for="mobile_money_number" class="form-label">Mobile-money number</label>
                                        <input type="tel" class="form-control" id="mobile_money_number" name="mobile_money_number"
                                               value="<?php echo htmlspecialchars($mobile_money_number, ENT_QUOTES, 'UTF-8'); ?>"
                                               placeholder="+260 97 123 4567" autocomplete="tel">
                                    </div>
                                    <button type="submit" name="save_mobile_money_number" value="1" class="btn btn-primary w-100">
                                        <i class="bi bi-save me-1" aria-hidden="true"></i> Save payment number
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Quick Stats -->
                        <div class="card shadow settings-card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-speedometer2"></i> System Statistics</h5>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-4">
                                        <div class="stat-number"><?php echo $total_users; ?></div>
                                        <div class="stat-label">Users</div>
                                    </div>
                                    <div class="col-4">
                                        <div class="stat-number"><?php echo $total_sessions; ?></div>
                                        <div class="stat-label">Sessions</div>
                                    </div>
                                    <div class="col-4">
                                        <div class="stat-number"><?php echo $total_payments; ?></div>
                                        <div class="stat-label">Payments</div>
                                    </div>
                                </div>
                                <hr>
                                <div class="text-center">
                                    <div class="stat-number text-success"><?php echo formatCurrency($total_revenue); ?></div>
                                    <div class="stat-label">Total Revenue</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Toggle password visibility
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = field.parentElement.querySelector('.toggle-btn i');
            if (field.type === 'password') {
                field.type = 'text';
                icon.className = 'bi bi-eye-slash';
            } else {
                field.type = 'password';
                icon.className = 'bi bi-eye';
            }
        }
        
        // Check password strength
        function checkPasswordStrength(password) {
            const bar = document.getElementById('strengthBar');
            const text = document.getElementById('strengthText');
            const reqLength = document.getElementById('req-length');
            
            // Check length
            if (password.length >= 6) {
                reqLength.innerHTML = '<i class="bi bi-check-circle text-success"></i> At least 6 characters';
            } else {
                reqLength.innerHTML = '<i class="bi bi-circle"></i> At least 6 characters';
            }
            
            if (password.length === 0) {
                bar.className = 'password-strength';
                text.textContent = 'Enter a password to check strength';
                return;
            }
            
            // Calculate strength
            let strength = 0;
            if (password.length >= 6) strength++;
            if (password.length >= 10) strength++;
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
            if (/\d/.test(password)) strength++;
            if (/[^a-zA-Z0-9]/.test(password)) strength++;
            
            // Set strength bar
            if (strength <= 1) {
                bar.className = 'password-strength weak';
                text.textContent = 'Weak - Try adding more characters, numbers, and symbols';
                text.className = 'text-danger';
            } else if (strength <= 2) {
                bar.className = 'password-strength medium';
                text.textContent = 'Medium - Add more variety for better security';
                text.className = 'text-warning';
            } else if (strength <= 3) {
                bar.className = 'password-strength strong';
                text.textContent = 'Strong - Good password!';
                text.className = 'text-success';
            } else {
                bar.className = 'password-strength very-strong';
                text.textContent = 'Very Strong - Excellent password!';
                text.className = 'text-success';
            }
        }
        
        // Confirm password match
        document.addEventListener('DOMContentLoaded', function() {
            const newPassword = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');
            const confirmMatch = document.getElementById('confirmMatch');
            
            confirmPassword.addEventListener('keyup', function() {
                if (this.value.length === 0) {
                    confirmMatch.innerHTML = '';
                    return;
                }
                if (this.value === newPassword.value) {
                    confirmMatch.innerHTML = '<i class="bi bi-check-circle text-success"></i> Passwords match!';
                    confirmMatch.className = 'text-success small mt-1';
                } else {
                    confirmMatch.innerHTML = '<i class="bi bi-x-circle text-danger"></i> Passwords do not match';
                    confirmMatch.className = 'text-danger small mt-1';
                }
            });
            
            newPassword.addEventListener('keyup', function() {
                if (confirmPassword.value.length > 0) {
                    if (this.value === confirmPassword.value) {
                        confirmMatch.innerHTML = '<i class="bi bi-check-circle text-success"></i> Passwords match!';
                        confirmMatch.className = 'text-success small mt-1';
                    } else {
                        confirmMatch.innerHTML = '<i class="bi bi-x-circle text-danger"></i> Passwords do not match';
                        confirmMatch.className = 'text-danger small mt-1';
                    }
                }
            });
        });
        
        // Logout timer
        <?php if ($success): ?>
        let seconds = 3;
        const timer = document.getElementById('timer');
        const countdown = setInterval(function() {
            seconds--;
            timer.textContent = seconds;
            if (seconds <= 0) {
                clearInterval(countdown);
                window.location.href = '../login.php';
            }
        }, 1000);
        <?php endif; ?>
    </script>
</body>
</html>