<?php
// mentor/certificate.php
require_once dirname(__DIR__) . '/includes/functions.php';
requireMentor();

$user = getCurrentUser();
$error = '';
$success = '';

// Get active semester
$stmt = $pdo->query("SELECT * FROM semesters WHERE status = 'active' ORDER BY id DESC LIMIT 1");
$active_semester = $stmt->fetch();

if (!$active_semester) {
    $error = 'No active semester found.';
}

// Get all certificates for this mentor
$stmt = $pdo->prepare("
    SELECT c.*, s.semester_name
    FROM certificates c
    JOIN semesters s ON c.semester_id = s.id
    WHERE c.mentor_id = ?
    ORDER BY c.issue_date DESC
");
$stmt->execute([$user['id']]);
$certificates = $stmt->fetchAll();

// Get mentor performance stats
$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT s.id) as total_sessions,
        SUM(s.duration_minutes) / 60.0 as total_hours
    FROM users u
    LEFT JOIN sessions s ON u.id = s.mentor_id AND s.status = 'completed'
    WHERE u.id = ?
    GROUP BY u.id
");
$stmt->execute([$user['id']]);
$performance = $stmt->fetch();

// Check if eligible for certificate (if not already has one for active semester)
$eligible = false;
$has_certificate = false;
if ($active_semester) {
    // Check if already has certificate for active semester
    $stmt = $pdo->prepare("SELECT id FROM certificates WHERE mentor_id = ? AND semester_id = ?");
    $stmt->execute([$user['id'], $active_semester['id']]);
    if ($stmt->fetch()) {
        $has_certificate = true;
    } else {
        // Check eligibility
        $total_hours = getMentorHours($user['id'], $active_semester['id']);
        $eligible = $total_hours >= MIN_MENTOR_HOURS;
    }
}

// Get certificate counts
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM certificates WHERE mentor_id = ? AND status = 'issued'");
$stmt->execute([$user['id']]);
$total_certificates = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT SUM(total_hours) as total FROM certificates WHERE mentor_id = ? AND status = 'issued'");
$stmt->execute([$user['id']]);
$total_certified_hours = $stmt->fetchColumn() ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Certificates - Mentor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/mobile.css" rel="stylesheet">
    <style>
        .certificate-card {
            transition: all 0.3s ease;
            border-radius: 16px;
            border: 1px solid #e9ecef;
            overflow: hidden;
        }
        .certificate-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .certificate-card .cert-header {
            background: linear-gradient(135deg, #ffc107, #e0a800);
            padding: 20px;
            text-align: center;
            color: #212529;
        }
        .certificate-card .cert-header .icon {
            font-size: 3rem;
            margin-bottom: 5px;
        }
        .certificate-card .cert-body {
            padding: 20px;
        }
        .certificate-card .cert-body .cert-id {
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            background: #f8f9fa;
            padding: 4px 12px;
            border-radius: 4px;
        }
        .certificate-card .cert-body .badge-issued {
            background: #28a745;
            color: white;
        }
        .stat-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            cursor: default;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }
        .eligible-badge {
            background: #d4edda;
            color: #155724;
            padding: 10px 20px;
            border-radius: 50px;
            display: inline-block;
            font-weight: 600;
        }
        .eligible-badge .spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 3px solid #28a745;
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 10px;
            vertical-align: middle;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        @media print {
            .no-print {
                display: none !important;
            }
            .certificate-card {
                border: 2px solid #ffc107 !important;
                box-shadow: none !important;
            }
            .certificate-card .cert-header {
                background: #ffc107 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/mentor_nav.php'; ?>
    
    <div class="container-fluid mt-3 mt-md-4">
        <div class="row">
            <div class="col-md-3 col-lg-2 mb-3">
                <?php include '../includes/mentor_sidebar.php'; ?>
            </div>
            
            <div class="col-md-9 col-lg-10">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
                    <div>
                        <h4><i class="bi bi-award"></i> My Certificates</h4>
                        <p class="text-muted">Track your mentorship achievements and certificates.</p>
                    </div>
                    <div>
                        <span class="badge bg-primary fs-6">
                            <i class="bi bi-award"></i> <?php echo $total_certificates; ?> Certificates
                        </span>
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
                    <div class="col-md-4 col-6">
                        <div class="card stat-card bg-primary text-white">
                            <div class="card-body">
                                <h6 class="card-title">Total Certificates</h6>
                                <h2 class="mb-0"><?php echo $total_certificates; ?></h2>
                                <small>Issued to you</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 col-6">
                        <div class="card stat-card bg-success text-white">
                            <div class="card-body">
                                <h6 class="card-title">Total Hours</h6>
                                <h2 class="mb-0"><?php echo round($total_certified_hours, 1); ?></h2>
                                <small>Certified hours</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 col-6">
                        <div class="card stat-card bg-info text-white">
                            <div class="card-body">
                                <h6 class="card-title">Sessions</h6>
                                <h2 class="mb-0"><?php echo $performance['total_sessions'] ?? 0; ?></h2>
                                <small>Completed</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Eligibility Status -->
                <?php if ($active_semester): ?>
                    <div class="alert <?php echo $has_certificate ? 'alert-success' : ($eligible ? 'alert-success' : 'alert-info'); ?> no-print">
                        <div class="d-flex align-items-center justify-content-between flex-wrap">
                            <div>
                                <i class="bi <?php echo ($has_certificate || $eligible) ? 'bi-check-circle' : 'bi-clock'; ?>"></i>
                                <?php if ($has_certificate): ?>
                                    <strong>Certificate Issued!</strong> You have already received your certificate for this semester.
                                <?php elseif ($eligible): ?>
                                    <strong>Eligible!</strong> You have earned <?php echo MIN_MENTOR_HOURS; ?>+ hours this semester. 
                                    <a href="certificate.php?request=1" class="alert-link">Request your certificate now</a>
                                <?php else: ?>
                                    <strong>In Progress:</strong> Complete <?php echo MIN_MENTOR_HOURS - round($performance['total_hours'] ?? 0, 1); ?> more hours to earn a certificate.
                                <?php endif; ?>
                            </div>
                            <?php if (!$has_certificate && $eligible): ?>
                                <a href="certificate.php?request=1" class="btn btn-success btn-sm">
                                    <i class="bi bi-award"></i> Request Certificate
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Request Certificate (if eligible) -->
                <?php if (isset($_GET['request']) && $eligible && !$has_certificate): ?>
                    <div class="alert alert-warning no-print">
                        <i class="bi bi-info-circle"></i> 
                        <strong>Request Submitted!</strong> Your certificate request has been sent to the admin for review and accreditation.
                        <br><small>You will be notified once your certificate is issued.</small>
                    </div>
                <?php endif; ?>
                
                <!-- Certificate Cards -->
                <?php if ($certificates): ?>
                    <div class="row g-3">
                        <?php foreach ($certificates as $cert): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="certificate-card" id="cert-<?php echo $cert['id']; ?>">
                                    <div class="cert-header">
                                        <div class="icon"><i class="bi bi-award-fill"></i></div>
                                        <h5 class="mb-0">Certificate of Achievement</h5>
                                        <small><?php echo htmlspecialchars($cert['semester_name']); ?></small>
                                    </div>
                                    <div class="cert-body">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($user['name']); ?></h6>
                                                <small class="text-muted"><?php echo htmlspecialchars($user['email']); ?></small>
                                            </div>
                                            <span class="badge badge-issued">✓ Issued</span>
                                        </div>
                                        <hr>
                                        <div class="row text-center">
                                            <div class="col-6">
                                                <div class="fw-bold"><?php echo number_format($cert['total_hours'], 1); ?></div>
                                                <small class="text-muted">Hours</small>
                                            </div>
                                            <div class="col-6">
                                                <div class="fw-bold"><?php echo $cert['total_sessions']; ?></div>
                                                <small class="text-muted">Sessions</small>
                                            </div>
                                        </div>
                                        <div class="mt-2">
                                            <small class="text-muted cert-id">
                                                <i class="bi bi-hash"></i> <?php echo substr($cert['certificate_hash'], 0, 12); ?>
                                            </small>
                                            <br>
                                            <small class="text-muted">Issued: <?php echo date('d M Y', strtotime($cert['issue_date'])); ?></small>
                                        </div>
                                        <div class="d-flex gap-2 mt-3 no-print">
                                            <a href="download_certificate.php?id=<?php echo $cert['id']; ?>" 
                                               class="btn btn-sm btn-primary flex-grow-1"
                                               target="_blank">
                                                <i class="bi bi-download"></i> Download PDF
                                            </a>
                                            <button onclick="printCertificate(<?php echo $cert['id']; ?>)" 
                                                    class="btn btn-sm btn-secondary">
                                                <i class="bi bi-printer"></i> Print
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-award display-1 text-muted"></i>
                        <h5 class="mt-3">No Certificates Yet</h5>
                        <p class="text-muted">Complete <?php echo MIN_MENTOR_HOURS; ?>+ hours of mentoring to earn your first certificate.</p>
                        <div class="mt-3">
                            <a href="sessions.php" class="btn btn-primary">
                                <i class="bi bi-calendar-check"></i> View Sessions
                            </a>
                            <a href="dashboard.php" class="btn btn-outline-secondary">
                                <i class="bi bi-grid"></i> Dashboard
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Print Certificate Modal -->
    <div class="modal fade" id="printModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-printer"></i> Print Certificate</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center" id="printContent">
                    <!-- Dynamic content -->
                </div>
                <div class="modal-footer no-print">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="window.print()">
                        <i class="bi bi-printer"></i> Print
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // ============================================
        // PRINT CERTIFICATE
        // ============================================
        function printCertificate(certId) {
            // Find the certificate card
            const card = document.getElementById('cert-' + certId);
            if (!card) return;
            
            // Clone the card for printing
            const clone = card.cloneNode(true);
            
            // Remove print buttons from clone
            clone.querySelectorAll('.no-print').forEach(el => el.remove());
            
            // Open modal with clone
            const modal = document.getElementById('printModal');
            const content = document.getElementById('printContent');
            
            // Add print styles to clone
            const printStyles = `
                <style>
                    .certificate-card {
                        border: 2px solid #ffc107 !important;
                        box-shadow: none !important;
                        max-width: 800px;
                        margin: 0 auto;
                    }
                    .certificate-card .cert-header {
                        background: #ffc107 !important;
                        -webkit-print-color-adjust: exact !important;
                        print-color-adjust: exact !important;
                    }
                    .certificate-card .badge-issued {
                        background: #28a745 !important;
                        color: white !important;
                        -webkit-print-color-adjust: exact !important;
                        print-color-adjust: exact !important;
                    }
                    .certificate-card .text-warning {
                        color: #ffc107 !important;
                        -webkit-print-color-adjust: exact !important;
                        print-color-adjust: exact !important;
                    }
                    body {
                        background: white !important;
                    }
                    .modal-body {
                        padding: 20px !important;
                    }
                </style>
            `;
            
            content.innerHTML = printStyles + clone.outerHTML;
            
            // Show modal
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
            
            // Auto print after modal is shown
            modal.addEventListener('shown.bs.modal', function() {
                // Wait a moment for rendering
                setTimeout(function() {
                    window.print();
                }, 500);
            });
        }
        
        // ============================================
        // KEYBOARD SHORTCUT
        // ============================================
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'p') {
                // Check if viewing a certificate
                const certCards = document.querySelectorAll('.certificate-card');
                if (certCards.length === 1) {
                    e.preventDefault();
                    const id = certCards[0].id.replace('cert-', '');
                    printCertificate(id);
                }
            }
        });
    </script>
</body>
</html>