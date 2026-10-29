<?php
// admin/fix_user.php - Fix a single user's application
require_once dirname(__DIR__) . '/includes/functions.php';
requireAdmin();

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$user_id) {
    header('Location: check_applications.php');
    exit();
}

// Get user details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    die("User not found.");
}

// Get active semester
$stmt = $pdo->query("SELECT * FROM semesters WHERE status = 'active' ORDER BY id DESC LIMIT 1");
$active_semester = $stmt->fetch();

if (!$active_semester) {
    die("No active semester found.");
}

$success = '';
$error = '';

// Fix user
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $course_code = sanitize($_POST['course_code']);
    $grade = sanitize($_POST['grade']);
    
    if ($user['role'] === 'mentor') {
        // Check if application exists
        $stmt = $pdo->prepare("SELECT id FROM mentor_applications WHERE mentor_id = ?");
        $stmt->execute([$user_id]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            $error = "Mentor application already exists for this user.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO mentor_applications 
                                   (mentor_id, semester_id, course_code, grade, status, created_at) 
                                   VALUES (?, ?, ?, ?, 'pending', NOW())");
            $stmt->execute([$user_id, $active_semester['id'], $course_code, $grade]);
            $success = "Mentor application created successfully!";
            logActivity($_SESSION['user_id'], "Fixed mentor application for user #$user_id");
        }
    } elseif ($user['role'] === 'mentee') {
        $stmt = $pdo->prepare("SELECT id FROM mentee_enrollments WHERE mentee_id = ?");
        $stmt->execute([$user_id]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            $error = "Mentee enrollment already exists for this user.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO mentee_enrollments 
                                   (mentee_id, semester_id, course_code, status, created_at) 
                                   VALUES (?, ?, ?, 'pending', NOW())");
            $stmt->execute([$user_id, $active_semester['id'], $course_code]);
            $success = "Mentee enrollment created successfully!";
            logActivity($_SESSION['user_id'], "Fixed mentee enrollment for user #$user_id");
        }
    }
}

// Get courses
$stmt = $pdo->query("SELECT * FROM courses ORDER BY course_code");
$courses = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix User - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/admin_nav.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-md-3">
                <?php include '../includes/admin_sidebar.php'; ?>
            </div>
            <div class="col-md-9">
                <h3><i class="bi bi-tools"></i> Fix User Application</h3>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <div class="card shadow">
                    <div class="card-body">
                        <h5>User Details</h5>
                        <table class="table">
                            <tr><td><strong>ID:</strong></td><td>#<?php echo $user['id']; ?></td></tr>
                            <tr><td><strong>Name:</strong></td><td><?php echo htmlspecialchars($user['name']); ?></td></tr>
                            <tr><td><strong>Email:</strong></td><td><?php echo htmlspecialchars($user['email']); ?></td></tr>
                            <tr><td><strong>Phone:</strong></td><td><?php echo htmlspecialchars($user['phone']); ?></td></tr>
                            <tr><td><strong>Role:</strong></td><td><?php echo ucfirst($user['role']); ?></td></tr>
                            <tr><td><strong>Status:</strong></td><td><?php echo ucfirst($user['status']); ?></td></tr>
                        </table>
                        
                        <hr>
                        
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="course_code" class="form-label">Course Code</label>
                                <select class="form-select" id="course_code" name="course_code" required>
                                    <option value="">Select Course</option>
                                    <?php foreach ($courses as $course): ?>
                                        <option value="<?php echo htmlspecialchars($course['course_code']); ?>">
                                            <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <?php if ($user['role'] === 'mentor'): ?>
                                <div class="mb-3">
                                    <label for="grade" class="form-label">Grade</label>
                                    <select class="form-select" id="grade" name="grade" required>
                                        <option value="">Select Grade</option>
                                        <option value="A">A</option>
                                        <option value="B+">B+</option>
                                        <option value="B">B</option>
                                        <option value="C+">C+</option>
                                        <option value="C">C</option>
                                    </select>
                                </div>
                            <?php endif; ?>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle"></i> Create Application
                            </button>
                            <a href="check_applications.php" class="btn btn-secondary">Back</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>