<?php
// admin/backup.php
require_once dirname(__DIR__, 2) . '/backend/includes/functions.php';
requireAdmin();

$user = getCurrentUser();
$error = '';
$success = '';
$backupDirectory = dirname(__DIR__, 2) . '/backend/backups';

if (!is_dir($backupDirectory) && !mkdir($backupDirectory, 0770, true) && !is_dir($backupDirectory)) {
    throw new RuntimeException('Unable to create the backup directory.');
}

if (isset($_GET['download'])) {
    if (!is_string($_GET['download'])) {
        http_response_code(404);
        exit('Backup not found.');
    }

    $file = basename($_GET['download']);
    $filePath = $backupDirectory . '/' . $file;

    if (pathinfo($file, PATHINFO_EXTENSION) !== 'sql' || !is_file($filePath)) {
        http_response_code(404);
        exit('Backup not found.');
    }

    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . $file . '"');
    readfile($filePath);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['backup'])) {
    // Create backup filename
    $filename = 'mentormatch_backup_' . date('Y-m-d_H-i-s') . '.sql';
    $backup_path = $backupDirectory . '/' . $filename;
    
    // Get database credentials
    require_once dirname(__DIR__, 2) . '/backend/config/database.php';
    
    // PG_DUMP_PATH can point to pg_dump when it is not on PATH.
    $pg_dump = getenv('PG_DUMP_PATH') ?: 'pg_dump';
    $previous_pg_password = getenv('PGPASSWORD');
    putenv('PGPASSWORD=' . $password);
    $command = escapeshellarg($pg_dump)
        . ' --host=' . escapeshellarg($host)
        . ' --port=' . escapeshellarg($port)
        . ' --user=' . escapeshellarg($username)
        . ' --no-owner --no-privileges --file=' . escapeshellarg($backup_path)
        . ' ' . escapeshellarg($dbname);

    try {
        exec($command, $output, $return_code);
    } finally {
        if ($previous_pg_password === false) {
            putenv('PGPASSWORD');
        } else {
            putenv('PGPASSWORD=' . $previous_pg_password);
        }
    }
    
    if ($return_code === 0) {
        logActivity($user['id'], 'Created database backup: ' . $filename);
        $success = 'Database backup created successfully!';
        
        // Download backup
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        readfile($backup_path);
        exit();
    } else {
        $error = 'Backup failed. Please try again.';
    }
}

// Handle delete backup
if (isset($_GET['delete'])) {
    if (!is_string($_GET['delete'])) {
        http_response_code(404);
        exit('Backup not found.');
    }

    $file = basename($_GET['delete']);
    $file_path = $backupDirectory . '/' . $file;
    if (file_exists($file_path)) {
        unlink($file_path);
        $success = 'Backup deleted successfully!';
        logActivity($user['id'], 'Deleted backup: ' . $file);
    }
}

// List existing backups
$backup_files = [];
if (is_dir($backupDirectory)) {
    $files = scandir($backupDirectory);
    if ($files === false) {
        throw new RuntimeException('Unable to read the backup directory.');
    }

    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
            $backup_files[] = [
                'name' => $file,
                'size' => filesize($backupDirectory . '/' . $file),
                'date' => filemtime($backupDirectory . '/' . $file)
            ];
        }
    }
    rsort($backup_files);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backup - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include dirname(__DIR__, 2) . '/frontend/includes/admin_nav.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-md-3 col-lg-2 mb-3">
                <?php include dirname(__DIR__, 2) . '/frontend/includes/admin_sidebar.php'; ?>
            </div>
            
            <div class="col-md-9 col-lg-10">
                <h3><i class="bi bi-database"></i> Database Backup</h3>
                <p class="text-muted">Create and manage database backups.</p>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="card shadow">
                            <div class="card-header">
                                <h5 class="mb-0">Create Backup</h5>
                            </div>
                            <div class="card-body text-center">
                                <div class="display-1 text-primary">
                                    <i class="bi bi-database-down"></i>
                                </div>
                                <p>Create a full database backup of the MentorMatch system.</p>
                                <form method="POST" action="">
                                    <button type="submit" name="backup" class="btn btn-primary w-100">
                                        <i class="bi bi-download"></i> Download Backup
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-8">
                        <div class="card shadow">
                            <div class="card-header">
                                <h5 class="mb-0">Backup History</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($backup_files): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>File Name</th>
                                                    <th>Size</th>
                                                    <th>Date</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($backup_files as $file): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($file['name']); ?></td>
                                                    <td><?php echo number_format($file['size'] / 1024, 2); ?> KB</td>
                                                    <td><?php echo date('d M Y h:i A', $file['date']); ?></td>
                                                    <td>
                                                        <a href="backup.php?download=<?php echo urlencode($file['name']); ?>" 
                                                           class="btn btn-sm btn-primary" download>
                                                            <i class="bi bi-download"></i>
                                                        </a>
                                                        <a href="backup.php?delete=<?php echo urlencode($file['name']); ?>" 
                                                           class="btn btn-sm btn-danger"
                                                           onclick="return confirm('Delete this backup?')">
                                                            <i class="bi bi-trash"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">No backups found.</p>
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