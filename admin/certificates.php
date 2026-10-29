<?php
// admin/certificates.php
require_once dirname(__DIR__) . '/includes/functions.php';
requireAdmin();

$user = getCurrentUser();
$error = '';
$success = '';

// ============================================
// ACCREDIT CERTIFICATE TO MENTOR
// ============================================
if (isset($_GET['accredit']) && isset($_GET['mentor_id'])) {
    $mentor_id = (int)$_GET['mentor_id'];
    $semester_id = (int)$_GET['semester_id'] ?? 0;
    
    if ($semester_id === 0) {
        $stmt = $pdo->query("SELECT id FROM semesters WHERE status = 'active' ORDER BY id DESC LIMIT 1");
        $semester = $stmt->fetch();
        $semester_id = $semester['id'] ?? 0;
    }
    
    if ($semester_id > 0) {
        // Check if certificate already exists
        $stmt = $pdo->prepare("SELECT id FROM certificates WHERE mentor_id = ? AND semester_id = ?");
        $stmt->execute([$mentor_id, $semester_id]);
        if ($stmt->fetch()) {
            $error = 'Certificate already exists for this mentor and semester.';
        } else {
            // Get mentor performance data
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(DISTINCT s.id) as total_sessions,
                    SUM(s.duration_minutes) / 60.0 as total_hours,
                    AVG(f.rating) as avg_rating,
                    COUNT(DISTINCT f.id) as total_feedback
                FROM users u
                LEFT JOIN sessions s ON u.id = s.mentor_id AND s.status = 'completed'
                LEFT JOIN feedback f ON u.id = f.mentor_id
                WHERE u.id = ?
                GROUP BY u.id
            ");
            $stmt->execute([$mentor_id]);
            $performance = $stmt->fetch();
            
            if (!$performance || $performance['total_sessions'] == 0) {
                $error = 'Mentor has no completed sessions.';
            } elseif ($performance['avg_rating'] && $performance['avg_rating'] < 3.5) {
                $error = 'Mentor rating is below 3.5 stars. Minimum requirement is 3.5 stars.';
            } else {
                // Generate certificate
                $hash = generateCertificateHash($mentor_id, $semester_id);
                
                $stmt = $pdo->prepare("
                    INSERT INTO certificates 
                    (mentor_id, semester_id, total_hours, total_sessions, issue_date, certificate_hash, status)
                    VALUES (?, ?, ?, ?, CURRENT_DATE, ?, 'issued')
                    RETURNING id
                ");
                $stmt->execute([
                    $mentor_id,
                    $semester_id,
                    $performance['total_hours'] ?? 0,
                    $performance['total_sessions'] ?? 0,
                    $hash
                ]);
                
                $cert_id = $stmt->fetchColumn();
                
                // Send email notification
                try {
                    require_once '../includes/email.php';
                    $stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
                    $stmt->execute([$mentor_id]);
                    $mentor = $stmt->fetch();
                    
                    if ($mentor) {
                        sendCertificateEmail($mentor['name'], $mentor['email'], $cert_id);
                    }
                } catch (Exception $e) {
                    error_log("Certificate email failed: " . $e->getMessage());
                }
                
                logActivity($user['id'], 'Accredited certificate to mentor #' . $mentor_id);
                $success = 'Certificate accredited successfully! Mentor has been notified.';
            }
        }
    } else {
        $error = 'No active semester found.';
    }
}

// ============================================
// BULK ACCREDIT CERTIFICATES
// ============================================
if (isset($_GET['bulk_accredit'])) {
    $semester_id = (int)$_GET['semester_id'] ?? 0;
    
    if ($semester_id === 0) {
        $stmt = $pdo->query("SELECT id FROM semesters WHERE status = 'active' ORDER BY id DESC LIMIT 1");
        $semester = $stmt->fetch();
        $semester_id = $semester['id'] ?? 0;
    }
    
    if ($semester_id > 0) {
        // Get eligible mentors (completed sessions, good ratings)
        $stmt = $pdo->prepare("
            SELECT 
                u.id,
                u.name,
                u.email,
                COUNT(DISTINCT s.id) as total_sessions,
                SUM(s.duration_minutes) / 60.0 as total_hours,
                AVG(f.rating) as avg_rating,
                COUNT(DISTINCT f.id) as total_feedback
            FROM users u
            LEFT JOIN sessions s ON u.id = s.mentor_id AND s.status = 'completed'
            LEFT JOIN feedback f ON u.id = f.mentor_id
            WHERE u.role = 'mentor' 
              AND u.status = 'active'
              AND s.semester_id = ?
            GROUP BY u.id
            HAVING COUNT(DISTINCT s.id) >= 5
               AND (AVG(f.rating) >= 3.5 OR AVG(f.rating) IS NULL)
        ");
        $stmt->execute([$semester_id]);
        $eligible_mentors = $stmt->fetchAll();
        
        $accredited_count = 0;
        foreach ($eligible_mentors as $mentor) {
            // Check if certificate already exists
            $stmt = $pdo->prepare("SELECT id FROM certificates WHERE mentor_id = ? AND semester_id = ?");
            $stmt->execute([$mentor['id'], $semester_id]);
            if (!$stmt->fetch()) {
                $hash = generateCertificateHash($mentor['id'], $semester_id);
                
                $stmt = $pdo->prepare("
                    INSERT INTO certificates 
                    (mentor_id, semester_id, total_hours, total_sessions, issue_date, certificate_hash, status)
                    VALUES (?, ?, ?, ?, CURRENT_DATE, ?, 'issued')
                    RETURNING id
                ");
                $stmt->execute([
                    $mentor['id'],
                    $semester_id,
                    $mentor['total_hours'] ?? 0,
                    $mentor['total_sessions'] ?? 0,
                    $hash
                ]);
                
                $cert_id = $stmt->fetchColumn();
                
                // Send email notification
                try {
                    require_once '../includes/email.php';
                    sendCertificateEmail($mentor['name'], $mentor['email'], $cert_id);
                } catch (Exception $e) {
                    error_log("Certificate email failed: " . $e->getMessage());
                }
                
                $accredited_count++;
            }
        }
        
        logActivity($user['id'], 'Bulk accredited ' . $accredited_count . ' certificates');
        $success = 'Successfully accredited ' . $accredited_count . ' certificates!';
    } else {
        $error = 'No active semester found.';
    }
}

// ============================================
// DELETE CERTIFICATE
// ============================================
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM certificates WHERE id = ?");
    $stmt->execute([$id]);
    $success = 'Certificate deleted successfully.';
    logActivity($user['id'], 'Deleted certificate #' . $id);
}

// ============================================
// DOWNLOAD CERTIFICATE
// ============================================
if (isset($_GET['download'])) {
    $id = (int)$_GET['download'];
    // Redirect to mentor download
    header('Location: ../mentor/download_certificate.php?id=' . $id);
    exit();
}

// ============================================
// GET ALL CERTIFICATES
// ============================================
$stmt = $pdo->prepare("
    SELECT c.*, 
           u.name as mentor_name, 
           u.email as mentor_email,
           u.phone as mentor_phone,
           s.semester_name,
           (SELECT AVG(rating) FROM feedback WHERE mentor_id = u.id) as avg_rating,
           (SELECT COUNT(*) FROM feedback WHERE mentor_id = u.id) as total_reviews,
           (SELECT COUNT(*) FROM sessions WHERE mentor_id = u.id AND status = 'completed') as total_sessions
    FROM certificates c
    JOIN users u ON c.mentor_id = u.id
    JOIN semesters s ON c.semester_id = s.id
    ORDER BY c.issue_date DESC
");
$stmt->execute();
$certificates = $stmt->fetchAll();

// ============================================
// GET ELIGIBLE MENTORS FOR ACCREDITATION
// ============================================
$stmt = $pdo->query("SELECT id FROM semesters WHERE status = 'active' ORDER BY id DESC LIMIT 1");
$active_semester = $stmt->fetch();
$semester_id = $active_semester['id'] ?? 0;

$eligible_mentors = [];
if ($semester_id > 0) {
    $stmt = $pdo->prepare("
        SELECT 
            u.id,
            u.name,
            u.email,
            u.phone,
            u.programme,
            COUNT(DISTINCT s.id) as total_sessions,
            SUM(s.duration_minutes) / 60.0 as total_hours,
            AVG(f.rating) as avg_rating,
            COUNT(DISTINCT f.id) as total_reviews,
            (SELECT COUNT(*) FROM mentor_rates WHERE mentor_id = u.id) as total_courses,
            (SELECT COUNT(*) FROM certificates WHERE mentor_id = u.id AND semester_id = ?) as has_certificate
        FROM users u
        LEFT JOIN sessions s ON u.id = s.mentor_id AND s.status = 'completed' AND s.semester_id = ?
        LEFT JOIN feedback f ON u.id = f.mentor_id
        WHERE u.role = 'mentor' 
          AND u.status = 'active'
        GROUP BY u.id
        HAVING COUNT(DISTINCT s.id) >= 5 AND (AVG(f.rating) >= 3.5 OR AVG(f.rating) IS NULL)
        ORDER BY avg_rating DESC, total_sessions DESC
    ");
    $stmt->execute([$semester_id, $semester_id]);
    $eligible_mentors = $stmt->fetchAll();
}

// ============================================
// GET CERTIFICATE STATISTICS
// ============================================
$stmt = $pdo->query("SELECT COUNT(*) as total FROM certificates");
$total_certificates = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) as total FROM certificates WHERE status = 'issued'");
$issued_count = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) as total FROM certificates WHERE status = 'pending'");
$pending_count = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT AVG(total_hours) as avg FROM certificates");
$avg_hours = $stmt->fetchColumn() ?? 0;

$stmt = $pdo->query("SELECT COUNT(DISTINCT mentor_id) as total FROM certificates");
$unique_mentors = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificates - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/mobile.css" rel="stylesheet">
    <style>
        .stat-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            cursor: default;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }
        .certificate-card {
            transition: all 0.3s ease;
            border-radius: 12px;
            border: 1px solid #e9ecef;
        }
        .certificate-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            border-color: #ffc107;
        }
        .certificate-card .badge-issued {
            background: #28a745;
            color: white;
        }
        .certificate-card .badge-pending {
            background: #ffc107;
            color: #212529;
        }
        .eligible-mentor-card {
            transition: all 0.3s ease;
            border-left: 4px solid #28a745;
            cursor: pointer;
        }
        .eligible-mentor-card:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        .eligible-mentor-card .rating-stars {
            color: #ffc107;
            font-size: 0.85rem;
        }
        .mentor-avatar-sm {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            font-weight: 700;
            color: white;
            flex-shrink: 0;
        }
        .mentor-avatar-sm.bg-1 { background: linear-gradient(135deg, #0d6efd, #0a58ca); }
        .mentor-avatar-sm.bg-2 { background: linear-gradient(135deg, #28a745, #1e7e34); }
        .mentor-avatar-sm.bg-3 { background: linear-gradient(135deg, #dc3545, #b02a37); }
        .mentor-avatar-sm.bg-4 { background: linear-gradient(135deg, #ffc107, #d39e00); }
        .mentor-avatar-sm.bg-5 { background: linear-gradient(135deg, #17a2b8, #0d6efd); }
        .btn-accredit {
            background: #ffc107;
            color: #212529;
            border: none;
        }
        .btn-accredit:hover {
            background: #e0a800;
            color: #212529;
        }
        @media (max-width: 768px) {
            .stat-card .stat-icon { font-size: 1.5rem; }
            .eligible-mentor-card { margin-bottom: 10px; }
        }
    </style>
</head>
<body>
    <?php include '../includes/admin_nav.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-md-3 col-lg-2 mb-3">
                <?php include '../includes/admin_sidebar.php'; ?>
            </div>
            
            <div class="col-md-9 col-lg-10">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h3><i class="bi bi-award"></i> Certificates</h3>
                        <p class="text-muted">Accredit certificates to high-performing mentors.</p>
                    </div>
                    <div>
                        <?php if (count($eligible_mentors) > 0): ?>
                            <a href="certificates.php?bulk_accredit=1&semester_id=<?php echo $semester_id; ?>" 
                               class="btn btn-success"
                               onclick="return confirm('Accredit certificates to all eligible mentors?')">
                                <i class="bi bi-check-all"></i> Bulk Accredit (<?php echo count($eligible_mentors); ?>)
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <!-- Statistics Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-md-3 col-6">
                        <div class="card stat-card bg-primary text-white">
                            <div class="card-body">
                                <h6 class="card-title">Total Issued</h6>
                                <h2 class="mb-0"><?php echo $total_certificates; ?></h2>
                                <small>All certificates</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="card stat-card bg-success text-white">
                            <div class="card-body">
                                <h6 class="card-title">Active</h6>
                                <h2 class="mb-0"><?php echo $issued_count; ?></h2>
                                <small>Issued certificates</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="card stat-card bg-warning text-dark">
                            <div class="card-body">
                                <h6 class="card-title">Pending</h6>
                                <h2 class="mb-0"><?php echo $pending_count; ?></h2>
                                <small>Awaiting issuance</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="card stat-card bg-info text-white">
                            <div class="card-body">
                                <h6 class="card-title">Unique Mentors</h6>
                                <h2 class="mb-0"><?php echo $unique_mentors; ?></h2>
                                <small>With certificates</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Eligible Mentors for Accreditation -->
                <?php if (!empty($eligible_mentors)): ?>
                    <div class="card shadow mb-4">
                        <div class="card-header bg-success text-white">
                            <h6 class="mb-0"><i class="bi bi-star-fill"></i> Eligible Mentors for Accreditation</h6>
                            <small>Mentors with 5+ sessions and rating 3.5+ stars</small>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <?php foreach ($eligible_mentors as $mentor): 
                                    $bg_classes = ['bg-1', 'bg-2', 'bg-3', 'bg-4', 'bg-5'];
                                    $avatar_bg = $bg_classes[($mentor['id'] - 1) % count($bg_classes)];
                                ?>
                                    <div class="col-md-6 col-lg-4">
                                        <div class="card eligible-mentor-card">
                                            <div class="card-body">
                                                <div class="d-flex align-items-start gap-3">
                                                    <div class="mentor-avatar-sm <?php echo $avatar_bg; ?>">
                                                        <?php echo strtoupper(substr($mentor['name'], 0, 1)); ?>
                                                    </div>
                                                    <div class="flex-grow-1">
                                                        <h6 class="mb-0"><?php echo htmlspecialchars($mentor['name']); ?></h6>
                                                        <small class="text-muted"><?php echo htmlspecialchars($mentor['programme'] ?? 'Mentor'); ?></small>
                                                        <div class="d-flex align-items-center gap-2 mt-1 flex-wrap">
                                                            <span class="badge bg-primary"><?php echo $mentor['total_sessions']; ?> sessions</span>
                                                            <span class="badge bg-info"><?php echo round($mentor['total_hours'], 1); ?> hrs</span>
                                                            <?php if ($mentor['avg_rating']): ?>
                                                                <span class="badge bg-warning text-dark">
                                                                    <i class="bi bi-star-fill"></i> <?php echo number_format($mentor['avg_rating'], 1); ?>
                                                                </span>
                                                            <?php endif; ?>
                                                            <?php if ($mentor['has_certificate'] > 0): ?>
                                                                <span class="badge bg-success">✅ Certified</span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="mt-2">
                                                            <?php if ($mentor['has_certificate'] == 0): ?>
                                                                <a href="certificates.php?accredit=1&mentor_id=<?php echo $mentor['id']; ?>&semester_id=<?php echo $semester_id; ?>" 
                                                                   class="btn btn-sm btn-accredit"
                                                                   onclick="return confirm('Accredit certificate to <?php echo htmlspecialchars($mentor['name']); ?>?')">
                                                                    <i class="bi bi-award"></i> Accredit
                                                                </a>
                                                            <?php else: ?>
                                                                <span class="text-success"><i class="bi bi-check-circle"></i> Certificate issued</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> 
                        No mentors currently eligible for accreditation. Mentors need at least 5 completed sessions with a rating of 3.5+ stars.
                    </div>
                <?php endif; ?>
                
                <!-- All Certificates -->
                <div class="card shadow">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="bi bi-list-ul"></i> All Certificates</h6>
                        <span class="badge bg-secondary"><?php echo count($certificates); ?> entries</span>
                    </div>
                    <div class="card-body p-0">
                        <?php if ($certificates): ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Mentor</th>
                                            <th>Semester</th>
                                            <th>Hours</th>
                                            <th>Sessions</th>
                                            <th>Rating</th>
                                            <th>Issued</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($certificates as $cert): ?>
                                        <tr>
                                            <td><?php echo $cert['id']; ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($cert['mentor_name']); ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($cert['mentor_email']); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($cert['semester_name']); ?></td>
                                            <td><?php echo number_format($cert['total_hours'], 1); ?> hrs</td>
                                            <td><?php echo $cert['total_sessions']; ?></td>
                                            <td>
                                                <?php if ($cert['avg_rating']): ?>
                                                    <div class="d-flex align-items-center gap-1">
                                                        <span><?php echo number_format($cert['avg_rating'], 1); ?></span>
                                                        <span class="text-warning"><i class="bi bi-star-fill"></i></span>
                                                        <small class="text-muted">(<?php echo $cert['total_reviews']; ?> reviews)</small>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('d M Y', strtotime($cert['issue_date'])); ?></td>
                                            <td>
                                                <span class="badge <?php echo $cert['status'] === 'issued' ? 'badge-issued' : 'badge-pending'; ?>">
                                                    <?php echo ucfirst($cert['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="certificates.php?download=<?php echo $cert['id']; ?>" 
                                                       class="btn btn-sm btn-primary"
                                                       target="_blank"
                                                       title="Download PDF">
                                                        <i class="bi bi-download"></i>
                                                    </a>
                                                    <a href="certificates.php?delete=<?php echo $cert['id']; ?>" 
                                                       class="btn btn-sm btn-danger"
                                                       onclick="return confirm('Delete this certificate?')"
                                                       title="Delete">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="bi bi-award display-1 text-muted"></i>
                                <p class="text-muted mt-3">No certificates issued yet.</p>
                                <p class="text-muted small">Accredit certificates to eligible mentors above.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>