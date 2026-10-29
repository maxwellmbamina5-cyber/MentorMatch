<?php
// admin/feedback.php
require_once dirname(__DIR__) . '/includes/functions.php';
requireAdmin();

$user = getCurrentUser();
$error = '';
$success = '';

// Handle feedback deletion
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM feedback WHERE id = ?");
    $stmt->execute([$id]);
    $success = 'Feedback deleted successfully!';
    logActivity($user['id'], 'Deleted feedback #' . $id);
}

// Get filter parameters
$filter_rating = isset($_GET['rating']) ? (int)$_GET['rating'] : 0;
$filter_mentor = isset($_GET['mentor']) ? (int)$_GET['mentor'] : 0;
$filter_date = isset($_GET['date']) ? $_GET['date'] : '';

// Build query
$query = "
    SELECT f.*, 
           m.name as mentor_name, m.email as mentor_email,
           u.name as mentee_name, u.email as mentee_email,
           s.course_code, s.session_date
    FROM feedback f
    JOIN users m ON f.mentor_id = m.id
    JOIN users u ON f.mentee_id = u.id
    JOIN sessions s ON f.session_id = s.id
    WHERE 1=1
";

$params = [];

if ($filter_rating > 0) {
    $query .= " AND f.rating = ?";
    $params[] = $filter_rating;
}

if ($filter_mentor > 0) {
    $query .= " AND f.mentor_id = ?";
    $params[] = $filter_mentor;
}

if (!empty($filter_date)) {
    $query .= " AND DATE(f.submitted_at) = ?";
    $params[] = $filter_date;
}

$query .= " ORDER BY f.submitted_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$feedback_list = $stmt->fetchAll();

// Get statistics
$stmt = $pdo->query("SELECT COUNT(*) as total FROM feedback");
$total_feedback = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT AVG(rating) as avg FROM feedback");
$avg_rating = $stmt->fetchColumn() ?? 0;

$stmt = $pdo->query("
    SELECT rating, COUNT(*) as count 
    FROM feedback 
    GROUP BY rating 
    ORDER BY rating DESC
");
$rating_distribution = $stmt->fetchAll();

// Get mentors for filter
$stmt = $pdo->query("
    SELECT DISTINCT u.id, u.name 
    FROM users u
    JOIN feedback f ON u.id = f.mentor_id
    WHERE u.role = 'mentor'
    ORDER BY u.name
");
$mentors = $stmt->fetchAll();

// Get recent feedback (last 5)
$stmt = $pdo->query("
    SELECT f.*, m.name as mentor_name, u.name as mentee_name
    FROM feedback f
    JOIN users m ON f.mentor_id = m.id
    JOIN users u ON f.mentee_id = u.id
    ORDER BY f.submitted_at DESC
    LIMIT 5
");
$recent_feedback = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .rating-stars {
            color: #ffc107;
            font-size: 1.1rem;
        }
        .feedback-card {
            transition: all 0.3s ease;
        }
        .feedback-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .feedback-card .card-header {
            background: transparent;
            border-bottom: none;
            padding-bottom: 0;
        }
        .feedback-card .card-body {
            padding-top: 0.5rem;
        }
        .feedback-card .card-footer {
            background: transparent;
            border-top: 1px solid #e9ecef;
            font-size: 0.85rem;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
        }
        .stat-label {
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            opacity: 0.7;
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
                        <h3><i class="bi bi-star"></i> Feedback Management</h3>
                        <p class="text-muted">View and manage all mentor feedback and ratings.</p>
                    </div>
                    <div>
                        <a href="reports.php?type=feedback" class="btn btn-outline-primary">
                            <i class="bi bi-file-earmark-text"></i> Export Report
                        </a>
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
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <div class="stat-label">Total Feedback</div>
                                <div class="stat-number"><?php echo $total_feedback; ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <div class="stat-label">Average Rating</div>
                                <div class="stat-number"><?php echo number_format($avg_rating, 1); ?> ★</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-dark">
                            <div class="card-body">
                                <div class="stat-label">5 Star Ratings</div>
                                <div class="stat-number">
                                    <?php 
                                    $five_star = array_filter($rating_distribution, function($r) { return $r['rating'] == 5; });
                                    echo reset($five_star)['count'] ?? 0;
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <div class="stat-label">Mentors with Feedback</div>
                                <div class="stat-number">
                                    <?php 
                                    $stmt = $pdo->query("SELECT COUNT(DISTINCT mentor_id) FROM feedback");
                                    echo $stmt->fetchColumn();
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="card shadow mb-4">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Rating</label>
                                <select class="form-select" name="rating" onchange="this.form.submit()">
                                    <option value="0">All Ratings</option>
                                    <option value="5" <?php echo $filter_rating == 5 ? 'selected' : ''; ?>>5 ★</option>
                                    <option value="4" <?php echo $filter_rating == 4 ? 'selected' : ''; ?>>4 ★</option>
                                    <option value="3" <?php echo $filter_rating == 3 ? 'selected' : ''; ?>>3 ★</option>
                                    <option value="2" <?php echo $filter_rating == 2 ? 'selected' : ''; ?>>2 ★</option>
                                    <option value="1" <?php echo $filter_rating == 1 ? 'selected' : ''; ?>>1 ★</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Mentor</label>
                                <select class="form-select" name="mentor" onchange="this.form.submit()">
                                    <option value="0">All Mentors</option>
                                    <?php foreach ($mentors as $mentor): ?>
                                        <option value="<?php echo $mentor['id']; ?>" <?php echo $filter_mentor == $mentor['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($mentor['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Date</label>
                                <input type="date" class="form-control" name="date" value="<?php echo $filter_date; ?>" onchange="this.form.submit()">
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <a href="feedback.php" class="btn btn-secondary w-100">Clear Filters</a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Rating Distribution -->
                <div class="row g-3 mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="bi bi-bar-chart"></i> Rating Distribution</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php for ($i = 5; $i >= 1; $i--): 
                                        $count = 0;
                                        foreach ($rating_distribution as $r) {
                                            if ($r['rating'] == $i) {
                                                $count = $r['count'];
                                                break;
                                            }
                                        }
                                        $percentage = $total_feedback > 0 ? ($count / $total_feedback) * 100 : 0;
                                    ?>
                                        <div class="col-md-2">
                                            <div class="text-center">
                                                <div class="h4"><?php echo $i; ?> ★</div>
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar bg-<?php echo $i >= 4 ? 'success' : ($i == 3 ? 'warning' : 'danger'); ?>" 
                                                         style="width: <?php echo $percentage; ?>%">
                                                        <?php echo $count; ?>
                                                    </div>
                                                </div>
                                                <small class="text-muted"><?php echo number_format($percentage, 1); ?>%</small>
                                            </div>
                                        </div>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Feedback -->
                <?php if ($recent_feedback): ?>
                    <div class="card shadow mb-4">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-clock-history"></i> Recent Feedback</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php foreach ($recent_feedback as $item): ?>
                                    <div class="col-md-6 col-lg-4 mb-3">
                                        <div class="card feedback-card h-100">
                                            <div class="card-header">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <strong><?php echo htmlspecialchars($item['mentee_name']); ?></strong>
                                                    <small class="text-muted"><?php echo date('d M Y', strtotime($item['submitted_at'])); ?></small>
                                                </div>
                                                <div class="rating-stars mt-1">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="bi bi-star-fill <?php echo $i <= $item['rating'] ? 'text-warning' : 'text-muted opacity-25'; ?>"></i>
                                                    <?php endfor; ?>
                                                </div>
                                            </div>
                                            <div class="card-body">
                                                <div class="mb-2">
                                                    <span class="badge bg-primary"><?php echo htmlspecialchars($item['course_code']); ?></span>
                                                    <span class="badge bg-secondary">To: <?php echo htmlspecialchars($item['mentor_name']); ?></span>
                                                </div>
                                                <?php if ($item['comments']): ?>
                                                    <p class="card-text small"><?php echo nl2br(htmlspecialchars(substr($item['comments'], 0, 150))); ?><?php echo strlen($item['comments']) > 150 ? '...' : ''; ?></p>
                                                <?php else: ?>
                                                    <p class="card-text text-muted fst-italic small">No comments provided</p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="card-footer d-flex justify-content-between align-items-center">
                                                <small class="text-muted">
                                                    <i class="bi bi-calendar3"></i> <?php echo date('h:i A', strtotime($item['submitted_at'])); ?>
                                                </small>
                                                <a href="feedback.php?delete=<?php echo $item['id']; ?>" 
                                                   class="btn btn-sm btn-outline-danger"
                                                   onclick="return confirm('Delete this feedback?')">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- All Feedback Table -->
                <div class="card shadow">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="bi bi-list-ul"></i> All Feedback</h6>
                        <span class="badge bg-secondary"><?php echo count($feedback_list); ?> entries</span>
                    </div>
                    <div class="card-body">
                        <?php if ($feedback_list): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Mentor</th>
                                            <th>Mentee</th>
                                            <th>Course</th>
                                            <th>Rating</th>
                                            <th>Comments</th>
                                            <th>Session Date</th>
                                            <th>Submitted</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($feedback_list as $item): ?>
                                        <tr>
                                            <td><?php echo $item['id']; ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($item['mentor_name']); ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($item['mentor_email']); ?></small>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($item['mentee_name']); ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($item['mentee_email']); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($item['course_code']); ?></td>
                                            <td>
                                                <div class="rating-stars">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="bi bi-star-fill <?php echo $i <= $item['rating'] ? 'text-warning' : 'text-muted opacity-25'; ?>" style="font-size: 0.9rem;"></i>
                                                    <?php endfor; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($item['comments']): ?>
                                                    <span data-bs-toggle="tooltip" title="<?php echo htmlspecialchars($item['comments']); ?>">
                                                        <?php echo htmlspecialchars(substr($item['comments'], 0, 30)); ?><?php echo strlen($item['comments']) > 30 ? '...' : ''; ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('d M Y', strtotime($item['session_date'])); ?></td>
                                            <td><?php echo date('d M Y', strtotime($item['submitted_at'])); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-info" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#feedbackModal"
                                                        data-id="<?php echo $item['id']; ?>"
                                                        data-mentor="<?php echo htmlspecialchars($item['mentor_name']); ?>"
                                                        data-mentee="<?php echo htmlspecialchars($item['mentee_name']); ?>"
                                                        data-course="<?php echo htmlspecialchars($item['course_code']); ?>"
                                                        data-rating="<?php echo $item['rating']; ?>"
                                                        data-comments="<?php echo htmlspecialchars($item['comments'] ?? ''); ?>"
                                                        data-date="<?php echo date('d M Y h:i A', strtotime($item['submitted_at'])); ?>"
                                                        data-session="<?php echo date('d M Y h:i A', strtotime($item['session_date'])); ?>">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <a href="feedback.php?delete=<?php echo $item['id']; ?>" 
                                                   class="btn btn-sm btn-danger"
                                                   onclick="return confirm('Delete this feedback?')">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="bi bi-star display-1 text-muted"></i>
                                <p class="text-muted mt-3">No feedback found.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Feedback Details Modal -->
    <div class="modal fade" id="feedbackModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Feedback Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="text-muted small">Mentor</label>
                        <p class="fw-bold" id="modalMentor">-</p>
                    </div>
                    <div class="mb-3">
                        <label class="text-muted small">Mentee</label>
                        <p class="fw-bold" id="modalMentee">-</p>
                    </div>
                    <div class="mb-3">
                        <label class="text-muted small">Course</label>
                        <p class="fw-bold" id="modalCourse">-</p>
                    </div>
                    <div class="mb-3">
                        <label class="text-muted small">Session Date</label>
                        <p class="fw-bold" id="modalSessionDate">-</p>
                    </div>
                    <div class="mb-3">
                        <label class="text-muted small">Rating</label>
                        <div id="modalRating" class="rating-stars fs-4"></div>
                    </div>
                    <div class="mb-3">
                        <label class="text-muted small">Comments</label>
                        <p class="border p-3 bg-light rounded" id="modalComments">-</p>
                    </div>
                    <div class="mb-0">
                        <label class="text-muted small">Submitted</label>
                        <p class="fw-bold" id="modalDate">-</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Feedback modal
            const feedbackModal = document.getElementById('feedbackModal');
            feedbackModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                
                document.getElementById('modalMentor').textContent = button.dataset.mentor;
                document.getElementById('modalMentee').textContent = button.dataset.mentee;
                document.getElementById('modalCourse').textContent = button.dataset.course;
                document.getElementById('modalSessionDate').textContent = button.dataset.session;
                document.getElementById('modalDate').textContent = button.dataset.date;
                
                // Rating stars
                const rating = parseInt(button.dataset.rating);
                const ratingContainer = document.getElementById('modalRating');
                ratingContainer.innerHTML = '';
                for (let i = 1; i <= 5; i++) {
                    const star = document.createElement('i');
                    star.className = `bi bi-star-fill ${i <= rating ? 'text-warning' : 'text-muted opacity-25'}`;
                    ratingContainer.appendChild(star);
                }
                
                // Comments
                const comments = button.dataset.comments || 'No comments provided';
                document.getElementById('modalComments').textContent = comments;
            });
        });
    </script>
</body>
</html>