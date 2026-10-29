<?php
// mentee/courses.php - Manage Courses I Need Help With
require_once dirname(__DIR__, 2) . '/backend/includes/functions.php';
requireMentee();

$user = getCurrentUser();
$has_subscription = hasActiveSubscription($user['id']);
$error = '';
$success = '';

// Get active semester
$stmt = $pdo->query("SELECT * FROM semesters WHERE status = 'active' ORDER BY id DESC LIMIT 1");
$active_semester = $stmt->fetch();

// Get all courses for dropdown
$stmt = $pdo->query("SELECT * FROM courses ORDER BY course_code ASC");
$all_courses = $stmt->fetchAll();

// Get mentee's enrolled courses - FIXED: removed reference to 'mentors' table
$stmt = $pdo->prepare("
    SELECT me.*, 
           (SELECT COUNT(*) FROM sessions WHERE mentee_id = ? AND course_code = me.course_code AND status = 'completed') as sessions_completed,
           (SELECT CASE WHEN ? = 1 THEN COUNT(DISTINCT u.id) ELSE 0 END
            FROM users u
            JOIN mentor_rates r ON u.id = r.mentor_id
            JOIN mentor_applications ma ON u.id = ma.mentor_id
            WHERE u.role = 'mentor' 
              AND u.status = 'active' 
              AND ma.status = 'approved' 
              AND r.course_code = me.course_code
              AND EXISTS (
                  SELECT 1 FROM subscriptions sub
                  WHERE sub.user_id = u.id AND sub.status = 'active' AND sub.expires_at > NOW()
              )
              AND ma.semester_id = ?) as available_mentors
    FROM mentee_enrollments me
    WHERE me.mentee_id = ? AND me.status != 'rejected'
    ORDER BY me.created_at DESC
");
$stmt->execute([$user['id'], $has_subscription ? 1 : 0, $active_semester['id'] ?? 0, $user['id']]);
$enrolled_courses = $stmt->fetchAll();

// Add new course
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_course'])) {
    $course_code = sanitize($_POST['course_code']);
    $course_custom = sanitize($_POST['course_custom'] ?? '');
    
    // Handle custom course
    if (!empty($course_custom)) {
        $course_code = $course_custom;
        // Check if course exists in database, if not add it
        $stmt = $pdo->prepare("SELECT id FROM courses WHERE course_code = ?");
        $stmt->execute([$course_code]);
        if (!$stmt->fetch()) {
            $stmt = $pdo->prepare("INSERT INTO courses (course_code, course_name) VALUES (?, ?)");
            $stmt->execute([$course_code, $course_custom . ' (Custom)']);
        }
    }
    
    if (empty($course_code)) {
        $error = 'Please select or enter a course.';
    } else {
        // Check if already enrolled
        $stmt = $pdo->prepare("SELECT id FROM mentee_enrollments WHERE mentee_id = ? AND course_code = ? AND semester_id = ?");
        $stmt->execute([$user['id'], $course_code, $active_semester['id']]);
        
        if ($stmt->fetch()) {
            $error = 'You are already enrolled in this course.';
        } else {
            // Get course name
            $course_name = '';
            foreach ($all_courses as $c) {
                if ($c['course_code'] === $course_code) {
                    $course_name = $c['course_name'] ?? '';
                    break;
                }
            }
            if (empty($course_name)) {
                $course_name = $course_custom . ' (Custom)';
            }
            
            $stmt = $pdo->prepare("INSERT INTO mentee_enrollments 
                                   (mentee_id, semester_id, course_code, course_name, status, created_at) 
                                   VALUES (?, ?, ?, ?, 'pending', NOW())");
            $stmt->execute([$user['id'], $active_semester['id'], $course_code, $course_name]);
            
            logActivity($user['id'], 'Added course: ' . $course_code . ' to mentee enrollment');
            $success = 'Course added successfully! Waiting for admin approval.';
        }
    }
}

// Remove course (delete or deactivate)
if (isset($_GET['remove'])) {
    $id = (int)$_GET['remove'];
    
    $stmt = $pdo->prepare("DELETE FROM mentee_enrollments WHERE id = ? AND mentee_id = ?");
    $stmt->execute([$id, $user['id']]);
    
    logActivity($user['id'], 'Removed course enrollment #' . $id);
    $success = 'Course removed successfully.';
}

// Get AI recommendations based on enrolled courses
$ai_recommendations = [];
if ($has_subscription && !empty($enrolled_courses)) {
    $course_codes = array_column($enrolled_courses, 'course_code');
    $placeholders = implode(',', array_fill(0, count($course_codes), '?'));
    
    $stmt = $pdo->prepare("
        SELECT DISTINCT u.id, u.name, u.email, u.programme,
               r.course_code, r.rate_per_hour,
               (SELECT AVG(rating) FROM feedback f WHERE f.mentor_id = u.id) as avg_rating,
               (SELECT COUNT(*) FROM feedback f WHERE f.mentor_id = u.id) as total_reviews,
               (SELECT COUNT(*) FROM sessions s WHERE s.mentor_id = u.id AND s.status = 'completed') as total_sessions
        FROM users u
        JOIN mentor_rates r ON u.id = r.mentor_id
        JOIN mentor_applications ma ON u.id = ma.mentor_id
        WHERE u.role = 'mentor' 
          AND u.status = 'active'
          AND ma.status = 'approved'
          AND ma.semester_id = ?
          AND EXISTS (
              SELECT 1 FROM subscriptions sub
              WHERE sub.user_id = u.id AND sub.status = 'active' AND sub.expires_at > NOW()
          )
          AND r.course_code IN ($placeholders)
        ORDER BY avg_rating DESC, total_sessions DESC
        LIMIT 6
    ");
    $params = array_merge([$active_semester['id']], $course_codes);
    $stmt->execute($params);
    $ai_recommendations = $stmt->fetchAll();
}

// Get enrolled courses count by status
$stmt = $pdo->prepare("
    SELECT status, COUNT(*) as count 
    FROM mentee_enrollments 
    WHERE mentee_id = ? 
    GROUP BY status
");
$stmt->execute([$user['id']]);
$status_counts = $stmt->fetchAll();
$counts = ['pending' => 0, 'active' => 0, 'rejected' => 0];
foreach ($status_counts as $sc) {
    $counts[$sc['status']] = $sc['count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Courses - Mentee</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/mobile.css" rel="stylesheet">
    <style>
        .course-card {
            transition: all 0.3s ease;
            border-radius: 16px;
            border: none;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        }
        .course-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        .course-card .card-header {
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            padding: 12px 16px;
        }
        .course-code-badge {
            font-size: 1.2rem;
            font-weight: 700;
            color: #0d6efd;
        }
        .course-status {
            font-size: 0.75rem;
            padding: 4px 12px;
            border-radius: 20px;
        }
        .course-status.pending { background: #fff3cd; color: #856404; }
        .course-status.active { background: #d4edda; color: #155724; }
        .course-status.rejected { background: #f8d7da; color: #721c24; }
        .mentor-match-card {
            transition: all 0.3s ease;
            border-radius: 12px;
            border: 1px solid #e9ecef;
        }
        .mentor-match-card:hover {
            border-color: #0d6efd;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
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
        .rating-stars {
            color: #ffc107;
            font-size: 0.8rem;
        }
        .search-course-box {
            position: relative;
        }
        .search-course-box .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            max-height: 200px;
            overflow-y: auto;
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            z-index: 1000;
            display: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .search-course-box .search-results.show {
            display: block;
        }
        .search-result-item {
            padding: 10px 14px;
            cursor: pointer;
            transition: background 0.2s ease;
            border-bottom: 1px solid #f8f9fa;
        }
        .search-result-item:hover {
            background: #e7f1ff;
        }
        .search-result-item .badge {
            font-size: 0.6rem;
            padding: 2px 8px;
        }
        .selected-course-display {
            background: #e7f1ff;
            padding: 8px 15px;
            border-radius: 8px;
            display: inline-block;
            margin-top: 5px;
        }
        .selected-course-display .remove-course {
            cursor: pointer;
            color: #dc3545;
            margin-left: 10px;
        }
        .empty-state {
            text-align: center;
            padding: 40px 20px;
        }
        .empty-state .icon {
            font-size: 4rem;
            color: #dee2e6;
        }
        .ai-chip {
            background: #e7f1ff;
            color: #0d6efd;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-block;
        }
        .quick-add-btn {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .quick-add-btn:hover {
            background: #0d6efd;
            color: white;
            border-color: #0d6efd;
        }
        @media (max-width: 768px) {
            .course-card .card-body { padding: 14px; }
            .mentor-avatar-sm { width: 32px; height: 32px; font-size: 0.8rem; }
            .course-code-badge { font-size: 1rem; }
        }
    </style>
</head>
<body>
    <?php include dirname(__DIR__, 2) . '/frontend/includes/mentee_nav.php'; ?>
    
    <div class="container-fluid mt-3 mt-md-4">
        <div class="row">
            <div class="col-md-3 col-lg-2 mb-3">
                <?php include dirname(__DIR__, 2) . '/frontend/includes/mentee_sidebar.php'; ?>
            </div>
            
            <div class="col-md-9 col-lg-10">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
                    <div>
                        <h4><i class="bi bi-book"></i> My Courses</h4>
                        <p class="text-muted">Manage the courses you need help with</p>
                    </div>
                    <div>
                        <span class="ai-chip"><i class="bi bi-cpu"></i> AI Powered</span>
                    </div>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="bi bi-check-circle"></i> <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Course Stats -->
                <div class="row g-3 mb-4">
                    <div class="col-4 col-md-3">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h5 class="mb-0"><?php echo count($enrolled_courses); ?></h5>
                                <small class="text-muted">Total</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-4 col-md-3">
                        <div class="card bg-warning">
                            <div class="card-body text-center">
                                <h5 class="mb-0"><?php echo $counts['pending']; ?></h5>
                                <small class="text-dark">Pending</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-4 col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <h5 class="mb-0"><?php echo $counts['active']; ?></h5>
                                <small>Active</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-4 col-md-3 d-md-none">
                        <div class="card bg-danger text-white">
                            <div class="card-body text-center">
                                <h5 class="mb-0"><?php echo $counts['rejected']; ?></h5>
                                <small>Rejected</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row g-3">
                    <!-- Add Course Column -->
                    <div class="col-lg-4 order-lg-2">
                        <div class="card course-card">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="bi bi-plus-circle"></i> Add New Course</h6>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <div class="mb-3">
                                        <label for="course_search" class="form-label">Search or Enter Course</label>
                                        <div class="search-course-box">
                                            <input type="text" class="form-control" id="course_search" 
                                                   placeholder="Type to search courses..." 
                                                   onkeyup="searchCourses(this.value)" onfocus="searchCourses(this.value)"
                                                   autocomplete="off">
                                            <input type="hidden" name="course_code" id="course_code" value="">
                                            <input type="hidden" name="course_custom" id="course_custom" value="">
                                            <div class="search-results" id="courseResults"></div>
                                        </div>
                                        <div id="selectedCourseDisplay" class="selected-course-display" style="display: none;">
                                            <i class="bi bi-check-circle text-success"></i>
                                            <span id="selectedCourseText"></span>
                                            <span class="remove-course" onclick="clearCourseSelection()" title="Remove course">
                                                <i class="bi bi-x-circle"></i>
                                            </span>
                                        </div>
                                        <small class="text-muted">Search for a course or type a custom course code</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Quick Add</label>
                                        <div class="d-flex flex-wrap gap-1">
                                            <span class="quick-add-btn" onclick="quickAdd('CSC101')">CSC101</span>
                                            <span class="quick-add-btn" onclick="quickAdd('MAT101')">MAT101</span>
                                            <span class="quick-add-btn" onclick="quickAdd('PHY101')">PHY101</span>
                                            <span class="quick-add-btn" onclick="quickAdd('ENG101')">ENG101</span>
                                            <span class="quick-add-btn" onclick="quickAdd('BIO101')">BIO101</span>
                                            <span class="quick-add-btn" onclick="quickAdd('ACC101')">ACC101</span>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" name="add_course" class="btn btn-primary w-100">
                                        <i class="bi bi-plus-circle"></i> Add Course
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <!-- AI Recommendations -->
                        <?php if (!empty($ai_recommendations)): ?>
                            <div class="card course-card mt-3">
                                <div class="card-header">
                                    <h6 class="mb-0"><i class="bi bi-robot"></i> AI Recommended Mentors</h6>
                                </div>
                                <div class="card-body">
                                    <?php foreach (array_slice($ai_recommendations, 0, 4) as $mentor): 
                                        $bg_classes = ['bg-1', 'bg-2', 'bg-3', 'bg-4', 'bg-5'];
                                        $avatar_bg = $bg_classes[($mentor['id'] - 1) % count($bg_classes)];
                                    ?>
                                        <div class="mentor-match-card p-2 mb-2">
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="mentor-avatar-sm <?php echo $avatar_bg; ?>">
                                                    <?php echo strtoupper(substr($mentor['name'], 0, 1)); ?>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="fw-bold small"><?php echo htmlspecialchars($mentor['name']); ?></div>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <span class="badge bg-primary" style="font-size: 0.6rem;">
                                                            <?php echo htmlspecialchars($mentor['course_code']); ?>
                                                        </span>
                                                        <?php if ($mentor['avg_rating']): ?>
                                                            <div class="rating-stars">
                                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                    <i class="bi bi-star-fill <?php echo $i <= round($mentor['avg_rating']) ? 'text-warning' : 'text-muted opacity-25'; ?>"></i>
                                                                <?php endfor; ?>
                                                            </div>
                                                            <small class="text-muted" style="font-size: 0.6rem;"><?php echo number_format($mentor['avg_rating'], 1); ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <a href="search.php?search=<?php echo urlencode($mentor['course_code']); ?>&search_type=course" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-search"></i>
                                                </a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    
                                    <a href="search.php" class="btn btn-sm btn-outline-secondary w-100 mt-2">
                                        <i class="bi bi-search"></i> Find More Mentors
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Courses List Column -->
                    <div class="col-lg-8 order-lg-1">
                        <?php if ($enrolled_courses): ?>
                            <div class="row g-3">
                                <?php foreach ($enrolled_courses as $course): ?>
                                    <div class="col-md-6">
                                        <div class="card course-card">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <div class="course-code-badge"><?php echo htmlspecialchars($course['course_code']); ?></div>
                                                        <?php if ($course['course_name']): ?>
                                                            <div class="text-muted small"><?php echo htmlspecialchars($course['course_name']); ?></div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <span class="course-status <?php echo $course['status']; ?>">
                                                        <?php echo ucfirst($course['status']); ?>
                                                    </span>
                                                </div>
                                                
                                                <div class="mt-2">
                                                    <div class="row g-1 text-center">
                                                        <div class="col-6">
                                                            <div class="bg-light rounded p-1">
                                                                <div class="small fw-bold"><?php echo $course['sessions_completed'] ?? 0; ?></div>
                                                                <div class="text-muted" style="font-size: 0.6rem;">Sessions</div>
                                                            </div>
                                                        </div>
                                                        <div class="col-6">
                                                            <div class="bg-light rounded p-1">
                                                                <div class="small fw-bold"><?php echo $course['available_mentors'] ?? 0; ?></div>
                                                                <div class="text-muted" style="font-size: 0.6rem;">Mentors</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="mt-2 d-flex gap-2">
                                                    <?php if ($course['status'] === 'active'): ?>
                                                        <a href="search.php?search=<?php echo urlencode($course['course_code']); ?>&search_type=course" 
                                                           class="btn btn-sm btn-primary flex-grow-1">
                                                            <i class="bi bi-search"></i> Find Mentors
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted small flex-grow-1">
                                                            <?php if ($course['status'] === 'pending'): ?>
                                                                <i class="bi bi-clock"></i> Awaiting admin approval
                                                            <?php elseif ($course['status'] === 'rejected'): ?>
                                                                <i class="bi bi-x-circle"></i> Rejected by admin
                                                            <?php endif; ?>
                                                        </span>
                                                    <?php endif; ?>
                                                    <a href="courses.php?remove=<?php echo $course['id']; ?>" 
                                                       class="btn btn-sm btn-outline-danger"
                                                       onclick="return confirm('Remove this course from your list?')">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="bi bi-book icon"></i>
                                <h5 class="mt-3">No Courses Added Yet</h5>
                                <p class="text-muted">Add courses you need help with to find the right mentors.</p>
                                <p class="text-muted small">Courses you add will be reviewed by admin and then activated.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // ============================================
        // COURSE SEARCH
        // ============================================
        const allCourses = <?php echo json_encode($all_courses); ?>;
        
        function searchCourses(query) {
            const resultsDiv = document.getElementById('courseResults');
            const hiddenInput = document.getElementById('course_code');
            const customInput = document.getElementById('course_custom');
            
            if (query.length === 0) {
                resultsDiv.classList.remove('show');
                return;
            }
            
            const matches = allCourses.filter(c => 
                c.course_code.toLowerCase().includes(query.toLowerCase()) ||
                c.course_name.toLowerCase().includes(query.toLowerCase())
            );
            
            if (matches.length > 0) {
                resultsDiv.innerHTML = matches.map(c => 
                    `<div class="search-result-item" onclick="selectCourse('${c.course_code}', '${c.course_name}')">
                        <strong>${c.course_code}</strong> - ${c.course_name}
                        <span class="badge bg-secondary float-end">Course</span>
                    </div>`
                ).join('');
                resultsDiv.classList.add('show');
            } else {
                resultsDiv.innerHTML = `
                    <div class="search-result-item" onclick="selectCourse('${query.toUpperCase()}', '${query} (Custom)')">
                        <i class="bi bi-plus-circle"></i> Add "${query.toUpperCase()}" as custom course
                        <span class="badge bg-warning text-dark float-end">Custom</span>
                    </div>
                `;
                resultsDiv.classList.add('show');
            }
        }
        
        function selectCourse(code, name) {
            const displayText = code + ' - ' + name;
            document.getElementById('course_search').value = code;
            document.getElementById('course_code').value = code;
            document.getElementById('course_custom').value = code;
            document.getElementById('courseResults').classList.remove('show');
            document.getElementById('courseResults').innerHTML = '';
            
            document.getElementById('selectedCourseText').textContent = displayText;
            document.getElementById('selectedCourseDisplay').style.display = 'inline-block';
        }
        
        function clearCourseSelection() {
            document.getElementById('course_search').value = '';
            document.getElementById('course_code').value = '';
            document.getElementById('course_custom').value = '';
            document.getElementById('selectedCourseDisplay').style.display = 'none';
            document.getElementById('course_search').focus();
        }
        
        function quickAdd(courseCode) {
            document.getElementById('course_search').value = courseCode;
            document.getElementById('course_code').value = courseCode;
            document.getElementById('course_custom').value = courseCode;
            
            // Find course name
            const match = allCourses.find(c => c.course_code === courseCode);
            const displayText = match ? courseCode + ' - ' + match.course_name : courseCode + ' (Custom)';
            document.getElementById('selectedCourseText').textContent = displayText;
            document.getElementById('selectedCourseDisplay').style.display = 'inline-block';
            document.getElementById('courseResults').classList.remove('show');
        }
        
        // Close results when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.search-course-box')) {
                document.getElementById('courseResults').classList.remove('show');
            }
        });
        
        // ============================================
        // KEYBOARD SHORTCUTS
        // ============================================
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.getElementById('courseResults').classList.remove('show');
            }
        });
    </script>
</body>
</html>