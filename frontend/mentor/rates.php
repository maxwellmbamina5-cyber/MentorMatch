<?php
// mentor/rates.php
require_once dirname(__DIR__, 2) . '/backend/includes/functions.php';
requireMentor();

$user = getCurrentUser();
$error = '';
$success = '';

// Get mentor's existing rates
$stmt = $pdo->prepare("SELECT * FROM mentor_rates WHERE mentor_id = ?");
$stmt->execute([$user['id']]);
$rates = $stmt->fetchAll();

// Get all courses from mentor's approved applications
$stmt = $pdo->prepare("SELECT DISTINCT course_code, course_name 
                       FROM mentor_applications 
                       WHERE mentor_id = ? AND status = 'approved'");
$stmt->execute([$user['id']]);
$approved_courses = $stmt->fetchAll();

// Add new rate
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_rate'])) {
    $course_code = sanitize($_POST['course_code']);
    $rate_per_hour = (float)$_POST['rate_per_hour'];
    
    if ($rate_per_hour < 0) {
        $error = 'Rate cannot be negative';
    } else {
        // Check if rate already exists
        $stmt = $pdo->prepare("SELECT id FROM mentor_rates WHERE mentor_id = ? AND course_code = ?");
        $stmt->execute([$user['id'], $course_code]);
        
        if ($stmt->fetch()) {
            // Update existing rate
            $stmt = $pdo->prepare("UPDATE mentor_rates SET rate_per_hour = ?, updated_at = NOW() 
                                   WHERE mentor_id = ? AND course_code = ?");
            $stmt->execute([$rate_per_hour, $user['id'], $course_code]);
            $success = 'Rate updated successfully!';
        } else {
            // Insert new rate
            $stmt = $pdo->prepare("INSERT INTO mentor_rates (mentor_id, course_code, rate_per_hour) 
                                   VALUES (?, ?, ?)");
            $stmt->execute([$user['id'], $course_code, $rate_per_hour]);
            $success = 'Rate added successfully!';
        }
        
        logActivity($user['id'], 'Updated rate for ' . $course_code);
        
        // Refresh rates
        $stmt = $pdo->prepare("SELECT * FROM mentor_rates WHERE mentor_id = ?");
        $stmt->execute([$user['id']]);
        $rates = $stmt->fetchAll();
    }
}

// Delete rate
if (isset($_GET['delete'])) {
    $rate_id = (int)$_GET['delete'];
    
    $stmt = $pdo->prepare("DELETE FROM mentor_rates WHERE id = ? AND mentor_id = ?");
    $stmt->execute([$rate_id, $user['id']]);
    
    logActivity($user['id'], 'Deleted rate ID ' . $rate_id);
    header('Location: rates.php?deleted=1');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Rates - MentorMatch</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include dirname(__DIR__, 2) . '/frontend/includes/mentor_nav.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-md-3 col-lg-2 mb-3">
                <?php include dirname(__DIR__, 2) . '/frontend/includes/mentor_sidebar.php'; ?>
            </div>
            
            <div class="col-md-9 col-lg-10">
                <h3><i class="bi bi-currency-dollar"></i> My Rates</h3>
                <p class="text-muted">Set your hourly rate for each course you mentor.</p>
                
                <?php if (isset($_GET['deleted'])): ?>
                    <div class="alert alert-success">Rate deleted successfully.</div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="card shadow">
                            <div class="card-header">
                                <h5 class="mb-0">Add/Update Rate</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($approved_courses): ?>
                                    <form method="POST" action="">
                                        <div class="mb-3">
                                            <label for="course_code" class="form-label">Course *</label>
                                            <select class="form-select" id="course_code" name="course_code" required>
                                                <option value="">Select Course</option>
                                                <?php foreach ($approved_courses as $course): ?>
                                                    <option value="<?php echo htmlspecialchars($course['course_code']); ?>">
                                                        <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <small class="text-muted">Only courses you're approved for are shown.</small>
                                        </div>
                                        <div class="mb-3">
                                            <label for="rate_per_hour" class="form-label">Rate per Hour (K) *</label>
                                            <input type="number" class="form-control" id="rate_per_hour" name="rate_per_hour" 
                                                   step="0.01" min="0" required placeholder="e.g. 50.00">
                                        </div>
                                        <button type="submit" name="add_rate" class="btn btn-primary w-100">Save Rate</button>
                                    </form>
                                <?php else: ?>
                                    <div class="alert alert-info">
                                        You don't have any approved courses yet. 
                                        <a href="../admin/applications.php">Check your application status</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card shadow">
                            <div class="card-header">
                                <h5 class="mb-0">Your Current Rates</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($rates): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Course</th>
                                                    <th>Rate/Hour</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($rates as $rate): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($rate['course_code']); ?></td>
                                                    <td><?php echo formatCurrency($rate['rate_per_hour']); ?></td>
                                                    <td>
                                                        <a href="rates.php?delete=<?php echo $rate['id']; ?>" 
                                                           class="btn btn-sm btn-danger"
                                                           onclick="return confirm('Delete this rate?')">
                                                            <i class="bi bi-trash"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">No rates set yet.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>