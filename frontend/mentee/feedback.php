<?php
// admin/feedback.php
require_once dirname(__DIR__, 2) . '/backend/includes/functions.php';
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

// Handle bulk delete
if (isset($_GET['bulk_delete']) && isset($_GET['ids'])) {
    $ids = explode(',', $_GET['ids']);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("DELETE FROM feedback WHERE id IN ($placeholders)");
    $stmt->execute($ids);
    $success = count($ids) . ' feedback entries deleted successfully!';
    logActivity($user['id'], 'Bulk deleted ' . count($ids) . ' feedback entries');
}

// Get filter parameters
$filter_rating = isset($_GET['rating']) ? (int)$_GET['rating'] : 0;
$filter_mentor = isset($_GET['mentor']) ? (int)$_GET['mentor'] : 0;
$filter_date = isset($_GET['date']) ? $_GET['date'] : '';
$filter_course = isset($_GET['course']) ? $_GET['course'] : '';

// Build query
$query = "
    SELECT f.*, 
           m.name as mentor_name, m.email as mentor_email,
           u.name as mentee_name, u.email as mentee_email,
           s.course_code, s.session_date,
           (SELECT COUNT(*) FROM feedback WHERE mentor_id = m.id) as total_feedback,
           (SELECT AVG(rating) FROM feedback WHERE mentor_id = m.id) as mentor_avg_rating
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

if (!empty($filter_course)) {
    $query .= " AND s.course_code = ?";
    $params[] = $filter_course;
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

// Rating distribution
$rating_distribution = [];
for ($i = 5; $i >= 1; $i--) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM feedback WHERE rating = ?");
    $stmt->execute([$i]);
    $rating_distribution[$i] = (int)$stmt->fetchColumn();
}

// Get mentors for filter
$stmt = $pdo->query("
    SELECT DISTINCT u.id, u.name,
           (SELECT AVG(rating) FROM feedback WHERE mentor_id = u.id) as avg_rating,
           (SELECT COUNT(*) FROM feedback WHERE mentor_id = u.id) as total_feedback
    FROM users u
    JOIN feedback f ON u.id = f.mentor_id
    WHERE u.role = 'mentor'
    ORDER BY avg_rating DESC
");
$mentors = $stmt->fetchAll();

// Get courses for filter
$stmt = $pdo->query("
    SELECT DISTINCT course_code 
    FROM sessions 
    WHERE course_code IS NOT NULL
    ORDER BY course_code
");
$courses = $stmt->fetchAll();

// Get recent feedback (last 5)
$stmt = $pdo->query("
    SELECT f.*, m.name as mentor_name, u.name as mentee_name, s.course_code
    FROM feedback f
    JOIN users m ON f.mentor_id = m.id
    JOIN users u ON f.mentee_id = u.id
    JOIN sessions s ON f.session_id = s.id
    ORDER BY f.submitted_at DESC
    LIMIT 5
");
$recent_feedback = $stmt->fetchAll();

// Get top mentors by rating
$stmt = $pdo->query("
    SELECT u.id, u.name, u.email,
           AVG(f.rating) as avg_rating,
           COUNT(f.id) as total_feedback,
           (SELECT COUNT(*) FROM sessions WHERE mentor_id = u.id AND status = 'completed') as total_sessions
    FROM users u
    JOIN feedback f ON u.id = f.mentor_id
    WHERE u.role = 'mentor'
    GROUP BY u.id
    HAVING COUNT(f.id) >= 3
    ORDER BY avg_rating DESC
    LIMIT 5
");
$top_mentors = $stmt->fetchAll();

// Get feedback trend (last 6 months)
$trend_data = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $month_label = date('M Y', strtotime("-$i months"));
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM feedback WHERE TO_CHAR(submitted_at, 'YYYY-MM') = ?");
    $stmt->execute([$month]);
    $trend_data[$month_label] = (int)$stmt->fetchColumn();
}

// Get positive vs negative ratio
$stmt = $pdo->query("SELECT COUNT(*) as count FROM feedback WHERE rating >= 4");
$positive = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) as count FROM feedback WHERE rating <= 2");
$negative = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) as count FROM feedback WHERE rating = 3");
$neutral = $stmt->fetchColumn();

$total_ratings = $positive + $neutral + $negative;
$positive_percent = $total_ratings > 0 ? ($positive / $total_ratings) * 100 : 0;
$negative_percent = $total_ratings > 0 ? ($negative / $total_ratings) * 100 : 0;
$neutral_percent = $total_ratings > 0 ? ($neutral / $total_ratings) * 100 : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback Management - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/mobile.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .stat-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            cursor: default;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }
        .feedback-card {
            transition: all 0.3s ease;
            border-radius: 12px;
            border: 1px solid #e9ecef;
        }
        .feedback-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .feedback-card .rating-stars {
            color: #ffc107;
            font-size: 1.1rem;
        }
        .feedback-card .quote-icon {
            font-size: 1.5rem;
            color: #6c757d;
            opacity: 0.3;
        }
        .top-mentor-card {
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }
        .top-mentor-card.rank-1 {
            border-left-color: #ffd700;
            background: #fff8e1;
        }
        .top-mentor-card.rank-2 {
            border-left-color: #c0c0c0;
            background: #f8f9fa;
        }
        .top-mentor-card.rank-3 {
            border-left-color: #cd7f32;
            background: #fff3e0;
        }
        .top-mentor-card .rank-badge {
            font-size: 1.2rem;
            font-weight: 700;
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
        .chart-container {
            height: 200px;
        }
        .filter-section {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 16px;
        }
        @media (max-width: 768px) {
            .stat-card .stat-icon { font-size: 1.5rem; }
            .feedback-card { margin-bottom: 10px; }
            .chart-container { height: 150px; }
            .top-mentor-card { margin-bottom: 8px; }
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
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
                    <div>
                        <h3><i class="bi bi-star"></i> Feedback Management</h3>
                        <p class="text-muted">View and manage all mentor feedback and ratings.</p>
                    </div>
                    <div>
                        <span class="badge bg-primary fs-6">
                            <i class="bi bi-star-fill"></i> <?php echo number_format($avg_rating, 1); ?> ★ Avg Rating
                        </span>
                        <span class="badge bg-secondary fs-6 ms-2">
                            <i class="bi bi-chat"></i> <?php echo $total_feedback; ?> Reviews
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
                    <div class="col-md-3 col-6">
                        <div class="card stat-card bg-primary text-white">
                            <div class="card-body">
                                <h6 class="card-title">Total Reviews</h6>
                                <h2 class="mb-0"><?php echo $total_feedback; ?></h2>
                                <small>All time</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="card stat-card bg-success text-white">
                            <div class="card-body">
                                <h6 class="card-title">Positive (4-5★)</h6>
                                <h2 class="mb-0"><?php echo $positive; ?></h2>
                                <small><?php echo round($positive_percent); ?>% of reviews</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="card stat-card bg-warning text-dark">
                            <div class="card-body">
                                <h6 class="card-title">Neutral (3★)</h6>
                                <h2 class="mb-0"><?php echo $neutral; ?></h2>
                                <small><?php echo round($neutral_percent); ?>% of reviews</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="card stat-card bg-danger text-white">
                            <div class="card-body">
                                <h6 class="card-title">Negative (1-2★)</h6>
                                <h2 class="mb-0"><?php echo $negative; ?></h2>
                                <small><?php echo round($negative_percent); ?>% of reviews</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Top Mentors -->
                <?php if ($top_mentors): ?>
                    <div class="card shadow mb-4">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-trophy"></i> Top Rated Mentors</h6>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <?php foreach ($top_mentors as $index => $mentor): 
                                    $bg_classes = ['bg-1', 'bg-2', 'bg-3', 'bg-4', 'bg-5'];
                                    $avatar_bg = $bg_classes[($mentor['id'] - 1) % count($bg_classes)];
                                    $rank_class = $index === 0 ? 'rank-1' : ($index === 1 ? 'rank-2' : 'rank-3');
                                    $rank_icon = $index === 0 ? '🥇' : ($index === 1 ? '🥈' : '🥉');
                                ?>
                                    <div class="col-md-6 col-lg-4">
                                        <div class="card top-mentor-card <?php echo $rank_class; ?>">
                                            <div class="card-body">
                                                <div class="d-flex align-items-start gap-3">
                                                    <div class="mentor-avatar-sm <?php echo $avatar_bg; ?>">
                                                        <?php echo strtoupper(substr($mentor['name'], 0, 1)); ?>
                                                    </div>
                                                    <div class="flex-grow-1">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <h6 class="mb-0"><?php echo htmlspecialchars($mentor['name']); ?></h6>
                                                            <span class="rank-badge"><?php echo $rank_icon; ?></span>
                                                        </div>
                                                        <small class="text-muted"><?php echo htmlspecialchars($mentor['email']); ?></small>
                                                        <div class="d-flex align-items-center gap-2 mt-1">
                                                            <span class="badge bg-warning text-dark">
                                                                <i class="bi bi-star-fill"></i> <?php echo number_format($mentor['avg_rating'], 1); ?>
                                                            </span>
                                                            <span class="badge bg-primary"><?php echo $mentor['total_feedback']; ?> reviews</span>
                                                            <span class="badge bg-secondary"><?php echo $mentor['total_sessions']; ?> sessions</span>
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
                <?php endif; ?>
                
                <!-- Charts -->
                <div class="row g-3 mb-4">
                    <div class="col-md-7">
                        <div class="card shadow">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="bi bi-graph-up"></i> Feedback Trend</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="trendChart" class="chart-container"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="card shadow">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="bi bi-pie-chart"></i> Rating Distribution</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="distributionChart" class="chart-container"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="card shadow mb-4">
                    <div class="card-body filter-section">
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
                                    <?php foreach ($mentors as $m): ?>
                                        <option value="<?php echo $m['id']; ?>" <?php echo $filter_mentor == $m['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($m['name']); ?> (<?php echo $m['total_feedback']; ?> reviews)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Course</label>
                                <select class="form-select" name="course" onchange="this.form.submit()">
                                    <option value="">All Courses</option>
                                    <?php foreach ($courses as $c): ?>
                                        <option value="<?php echo htmlspecialchars($c['course_code']); ?>" <?php echo $filter_course == $c['course_code'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($c['course_code']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Date</label>
                                <input type="date" class="form-control" name="date" value="<?php echo $filter_date; ?>" onchange="this.form.submit()">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <a href="feedback.php" class="btn btn-secondary w-100">Clear</a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Recent Feedback -->
                <?php if ($recent_feedback): ?>
                    <div class="card shadow mb-4">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-clock-history"></i> Recent Feedback</h6>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <?php foreach ($recent_feedback as $item): ?>
                                    <div class="col-md-6 col-lg-4">
                                        <div class="card feedback-card">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($item['mentee_name']); ?></strong>
                                                        <small class="text-muted">→ <?php echo htmlspecialchars($item['mentor_name']); ?></small>
                                                    </div>
                                                    <small class="text-muted"><?php echo date('d M', strtotime($item['submitted_at'])); ?></small>
                                                </div>
                                                <div class="rating-stars mt-1">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="bi bi-star-fill <?php echo $i <= $item['rating'] ? 'text-warning' : 'text-muted opacity-25'; ?>"></i>
                                                    <?php endfor; ?>
                                                </div>
                                                <div class="mt-1">
                                                    <span class="badge bg-primary"><?php echo htmlspecialchars($item['course_code']); ?></span>
                                                </div>
                                                <?php if ($item['comments']): ?>
                                                    <p class="card-text small mt-2 mb-0">
                                                        <i class="bi bi-quote quote-icon"></i>
                                                        <?php echo htmlspecialchars(substr($item['comments'], 0, 100)); ?>
                                                        <?php echo strlen($item['comments']) > 100 ? '...' : ''; ?>
                                                    </p>
                                                <?php else: ?>
                                                    <p class="card-text text-muted fst-italic small mt-2">No comments provided</p>
                                                <?php endif; ?>
                                                <div class="mt-2">
                                                    <a href="feedback.php?delete=<?php echo $item['id']; ?>" 
                                                       class="btn btn-sm btn-outline-danger"
                                                       onclick="return confirm('Delete this feedback?')">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                    <button class="btn btn-sm btn-outline-info" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#feedbackModal"
                                                            data-mentor="<?php echo htmlspecialchars($item['mentor_name']); ?>"
                                                            data-mentee="<?php echo htmlspecialchars($item['mentee_name']); ?>"
                                                            data-course="<?php echo htmlspecialchars($item['course_code']); ?>"
                                                            data-rating="<?php echo $item['rating']; ?>"
                                                            data-comments="<?php echo htmlspecialchars($item['comments'] ?? ''); ?>"
                                                            data-date="<?php echo date('d M Y h:i A', strtotime($item['submitted_at'])); ?>">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                </div>
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
                    <div class="card-body p-0">
                        <?php if ($feedback_list): ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Mentor</th>
                                            <th>Mentee</th>
                                            <th>Course</th>
                                            <th>Rating</th>
                                            <th>Comments</th>
                                            <th>Date</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($feedback_list as $item): ?>
                                        <tr>
                                            <td><?php echo $item['id']; ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($item['mentor_name']); ?>
                                                <br><small class="text-muted">
                                                    Avg: <?php echo number_format($item['mentor_avg_rating'] ?? 0, 1); ?> ★
                                                    (<?php echo $item['total_feedback']; ?> reviews)
                                                </small>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($item['mentee_name']); ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($item['mentee_email']); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($item['course_code']); ?></td>
                                            <td>
                                                <div class="rating-stars">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="bi bi-star-fill <?php echo $i <= $item['rating'] ? 'text-warning' : 'text-muted opacity-25'; ?>" 
                                                           style="font-size: 0.9rem;"></i>
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
                                            <td>
                                                <?php echo date('d M Y', strtotime($item['submitted_at'])); ?>
                                                <br><small class="text-muted"><?php echo date('h:i A', strtotime($item['submitted_at'])); ?></small>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-info" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#feedbackModal"
                                                        data-mentor="<?php echo htmlspecialchars($item['mentor_name']); ?>"
                                                        data-mentee="<?php echo htmlspecialchars($item['mentee_name']); ?>"
                                                        data-course="<?php echo htmlspecialchars($item['course_code']); ?>"
                                                        data-rating="<?php echo $item['rating']; ?>"
                                                        data-comments="<?php echo htmlspecialchars($item['comments'] ?? ''); ?>"
                                                        data-date="<?php echo date('d M Y h:i A', strtotime($item['submitted_at'])); ?>">
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
                                <p class="text-muted small">Feedback will appear here once mentees start rating mentors.</p>
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
        // ============================================
        // FEEDBACK TREND CHART
        // ============================================
        const trendCtx = document.getElementById('trendChart').getContext('2d');
        const trendLabels = <?php echo json_encode(array_keys($trend_data)); ?>;
        const trendData = <?php echo json_encode(array_values($trend_data)); ?>;
        
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: trendLabels,
                datasets: [{
                    label: 'Feedback Received',
                    data: trendData,
                    backgroundColor: 'rgba(13, 110, 253, 0.2)',
                    borderColor: 'rgba(13, 110, 253, 1)',
                    borderWidth: 3,
                    pointBackgroundColor: 'rgba(13, 110, 253, 1)',
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
        
        // ============================================
        // RATING DISTRIBUTION CHART
        // ============================================
        const distCtx = document.getElementById('distributionChart').getContext('2d');
        const distLabels = ['5 ★', '4 ★', '3 ★', '2 ★', '1 ★'];
        const distData = <?php echo json_encode(array_values($rating_distribution)); ?>;
        const distColors = ['#28a745', '#20c997', '#ffc107', '#fd7e14', '#dc3545'];
        
        new Chart(distCtx, {
            type: 'doughnut',
            data: {
                labels: distLabels,
                datasets: [{
                    data: distData,
                    backgroundColor: distColors,
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            padding: 10
                        }
                    }
                }
            }
        });
        
        // ============================================
        // FEEDBACK MODAL
        // ============================================
        document.addEventListener('DOMContentLoaded', function() {
            const feedbackModal = document.getElementById('feedbackModal');
            feedbackModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                
                document.getElementById('modalMentor').textContent = button.dataset.mentor || 'N/A';
                document.getElementById('modalMentee').textContent = button.dataset.mentee || 'N/A';
                document.getElementById('modalCourse').textContent = button.dataset.course || 'N/A';
                document.getElementById('modalDate').textContent = button.dataset.date || 'N/A';
                
                // Rating stars
                const rating = parseInt(button.dataset.rating) || 0;
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