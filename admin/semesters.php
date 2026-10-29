<?php
// admin/semesters.php
require_once '../includes/functions.php';
requireAdmin();

$user = getCurrentUser();
$error = '';
$success = '';

// Create or update semester
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $semester_name = sanitize($_POST['semester_name']);
    $start_date = sanitize($_POST['start_date']);
    $end_date = sanitize($_POST['end_date']);
    $registration_open = sanitize($_POST['registration_open']);
    $registration_close = sanitize($_POST['registration_close']);
    $status = sanitize($_POST['status']);
    
    if ($id > 0) {
        // Update
        $stmt = $pdo->prepare("UPDATE semesters 
                               SET semester_name = ?, start_date = ?, end_date = ?, 
                                   registration_open = ?, registration_close = ?, status = ?
                               WHERE id = ?");
        $stmt->execute([$semester_name, $start_date, $end_date, $registration_open, $registration_close, $status, $id]);
        $success = 'Semester updated successfully!';
    } else {
        // Create
        $stmt = $pdo->prepare("INSERT INTO semesters 
                               (semester_name, start_date, end_date, registration_open, registration_close, status)
                               VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$semester_name, $start_date, $end_date, $registration_open, $registration_close, $status]);
        $success = 'Semester created successfully!';
    }
    
    logActivity($user['id'], 'Updated semester: ' . $semester_name);
}

// Delete semester
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // Check if semester has sessions
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM sessions WHERE semester_id = ?");
    $stmt->execute([$id]);
    if ($stmt->fetchColumn() > 0) {
        $error = 'Cannot delete semester with existing sessions.';
    } else {
        $stmt = $pdo->prepare("DELETE FROM semesters WHERE id = ?");
        $stmt->execute([$id]);
        $success = 'Semester deleted successfully.';
    }
}

// Get all semesters
$stmt = $pdo->query("SELECT * FROM semesters ORDER BY start_date DESC");
$semesters = $stmt->fetchAll();

// Get semester to edit
$edit_semester = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM semesters WHERE id = ?");
    $stmt->execute([$id]);
    $edit_semester = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Semesters - Admin</title>
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
                <h3><i class="bi bi-calendar-range"></i> Semesters</h3>
                <p class="text-muted">Manage academic semesters for mentorship programs.</p>
                
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
                                <h5 class="mb-0"><?php echo $edit_semester ? 'Edit Semester' : 'Create New Semester'; ?></h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <?php if ($edit_semester): ?>
                                        <input type="hidden" name="id" value="<?php echo $edit_semester['id']; ?>">
                                    <?php endif; ?>
                                    
                                    <div class="mb-3">
                                        <label for="semester_name" class="form-label">Semester Name *</label>
                                        <input type="text" class="form-control" id="semester_name" name="semester_name" 
                                               value="<?php echo $edit_semester ? htmlspecialchars($edit_semester['semester_name']) : ''; ?>" 
                                               placeholder="e.g. 2025-1" required>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="start_date" class="form-label">Start Date *</label>
                                            <input type="date" class="form-control" id="start_date" name="start_date" 
                                                   value="<?php echo $edit_semester ? $edit_semester['start_date'] : ''; ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="end_date" class="form-label">End Date *</label>
                                            <input type="date" class="form-control" id="end_date" name="end_date" 
                                                   value="<?php echo $edit_semester ? $edit_semester['end_date'] : ''; ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="registration_open" class="form-label">Registration Open *</label>
                                            <input type="date" class="form-control" id="registration_open" name="registration_open" 
                                                   value="<?php echo $edit_semester ? $edit_semester['registration_open'] : ''; ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="registration_close" class="form-label">Registration Close *</label>
                                            <input type="date" class="form-control" id="registration_close" name="registration_close" 
                                                   value="<?php echo $edit_semester ? $edit_semester['registration_close'] : ''; ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="status" class="form-label">Status *</label>
                                        <select class="form-select" id="status" name="status" required>
                                            <option value="upcoming" <?php echo ($edit_semester && $edit_semester['status'] === 'upcoming') ? 'selected' : ''; ?>>Upcoming</option>
                                            <option value="active" <?php echo ($edit_semester && $edit_semester['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                                            <option value="closed" <?php echo ($edit_semester && $edit_semester['status'] === 'closed') ? 'selected' : ''; ?>>Closed</option>
                                            <option value="archived" <?php echo ($edit_semester && $edit_semester['status'] === 'archived') ? 'selected' : ''; ?>>Archived</option>
                                        </select>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary w-100">
                                        <?php echo $edit_semester ? 'Update Semester' : 'Create Semester'; ?>
                                    </button>
                                    <?php if ($edit_semester): ?>
                                        <a href="semesters.php" class="btn btn-secondary w-100 mt-2">Cancel Edit</a>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-7">
                        <div class="card shadow">
                            <div class="card-header">
                                <h5 class="mb-0">All Semesters</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($semesters): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Start</th>
                                                    <th>End</th>
                                                    <th>Registration</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($semesters as $sem): ?>
                                                <tr>
                                                    <td><strong><?php echo htmlspecialchars($sem['semester_name']); ?></strong></td>
                                                    <td><?php echo date('d M Y', strtotime($sem['start_date'])); ?></td>
                                                    <td><?php echo date('d M Y', strtotime($sem['end_date'])); ?></td>
                                                    <td>
                                                        <small>
                                                            <?php echo date('d M', strtotime($sem['registration_open'])); ?> - 
                                                            <?php echo date('d M Y', strtotime($sem['registration_close'])); ?>
                                                        </small>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?php 
                                                            echo $sem['status'] === 'active' ? 'success' : 
                                                                ($sem['status'] === 'upcoming' ? 'info' : 
                                                                ($sem['status'] === 'closed' ? 'warning' : 'secondary')); 
                                                        ?>">
                                                            <?php echo getSemesterStatus($sem['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <a href="semesters.php?edit=<?php echo $sem['id']; ?>" 
                                                           class="btn btn-sm btn-primary">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                        <a href="semesters.php?delete=<?php echo $sem['id']; ?>" 
                                                           class="btn btn-sm btn-danger"
                                                           onclick="return confirm('Delete this semester?')">
                                                            <i class="bi bi-trash"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">No semesters created yet.</p>
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