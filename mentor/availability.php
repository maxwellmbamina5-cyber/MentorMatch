<?php
// mentor/availability.php
require_once '../includes/functions.php';
requireMentor();

$user = getCurrentUser();
$error = '';
$success = '';

// Handle adding availability
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
    $day_of_week = filter_var($_POST['day_of_week'] ?? null, FILTER_VALIDATE_INT);
    $start_time = sanitize($_POST['start_time'] ?? '');
    $end_time = sanitize($_POST['end_time'] ?? '');
    
    // Validate
    if ($day_of_week === false || $day_of_week < 0 || $day_of_week > 6
        || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $start_time)
        || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $end_time)) {
        $error = 'Choose a valid day and time range.';
    } elseif ($start_time >= $end_time) {
        $error = 'End time must be later than start time.';
    } else {
        // Check for duplicate
        $stmt = $pdo->prepare("SELECT id FROM mentor_availability 
                               WHERE mentor_id = ? AND day_of_week = ? AND start_time = ? AND end_time = ?");
        $stmt->execute([$user['id'], $day_of_week, $start_time, $end_time]);
        
        if ($stmt->fetch()) {
            $error = 'This availability slot already exists.';
        } else {
            $stmt = $pdo->prepare("INSERT INTO mentor_availability 
                                   (mentor_id, day_of_week, start_time, end_time, is_recurring)
                                   VALUES (?, ?, ?, ?, 1)");
            $stmt->execute([$user['id'], $day_of_week, $start_time, $end_time]);
            $success = 'Availability slot added successfully!';
            logActivity($user['id'], 'Added availability slot');
        }
    }
}

// Handle deleting availability
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM mentor_availability WHERE id = ? AND mentor_id = ?");
    $stmt->execute([$id, $user['id']]);
    $success = 'Availability slot deleted.';
    logActivity($user['id'], 'Deleted availability slot #' . $id);
}

// Get mentor's availability
$stmt = $pdo->prepare("SELECT * FROM mentor_availability WHERE mentor_id = ? ORDER BY day_of_week, start_time");
$stmt->execute([$user['id']]);
$availability = $stmt->fetchAll();

// Group by day
$days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$grouped = [];
foreach ($availability as $slot) {
    $grouped[$slot['day_of_week']][] = $slot;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Availability - Mentor</title>
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
                <h3><i class="bi bi-clock"></i> My Availability</h3>
                <p class="text-muted">Set weekly time ranges that mentees can use when requesting sessions.</p>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-5">
                        <div class="card shadow">
                            <div class="card-header">
                                <h5 class="mb-0">Add Availability Slot</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <div class="mb-3">
                                        <label for="day_of_week" class="form-label">Day *</label>
                                        <select class="form-select" id="day_of_week" name="day_of_week" required>
                                            <?php foreach ($days as $key => $day): ?>
                                                <option value="<?php echo $key; ?>"><?php echo $day; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="start_time" class="form-label">Start Time *</label>
                                            <input type="time" class="form-control" id="start_time" name="start_time" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="end_time" class="form-label">End Time *</label>
                                            <input type="time" class="form-control" id="end_time" name="end_time" required>
                                        </div>
                                    </div>
                                    <p class="small text-muted">Each time range repeats weekly. You can remove a range whenever your schedule changes.</p>
                                    <button type="submit" name="add" class="btn btn-primary w-100">
                                        <i class="bi bi-plus-circle"></i> Add Slot
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-7">
                        <div class="card shadow">
                            <div class="card-header">
                                <h5 class="mb-0">Your Availability</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($availability): ?>
                                    <div class="row">
                                        <?php foreach ($grouped as $day => $slots): ?>
                                            <div class="col-md-6 mb-3">
                                                <div class="card bg-light">
                                                    <div class="card-header">
                                                        <strong><?php echo $days[$day]; ?></strong>
                                                    </div>
                                                    <ul class="list-group list-group-flush">
                                                        <?php foreach ($slots as $slot): ?>
                                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                                <?php echo date('h:i A', strtotime($slot['start_time'])); ?> - 
                                                                <?php echo date('h:i A', strtotime($slot['end_time'])); ?>
                                                                <span class="badge bg-info">Weekly</span>
                                                                <a href="availability.php?delete=<?php echo $slot['id']; ?>" 
                                                                   class="btn btn-sm btn-danger"
                                                                   onclick="return confirm('Delete this slot?')">
                                                                    <i class="bi bi-trash"></i>
                                                                </a>
                                                            </li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">No availability set. Add your availability above.</p>
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