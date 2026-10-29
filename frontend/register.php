<?php
// register.php
require_once __DIR__ . '/../backend/includes/functions.php';

$error = '';
$success = '';
$registration_data = null;
$selected_role = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['role'] ?? '')
    : ($_GET['role'] ?? '');
$selected_role = in_array($selected_role, ['mentor', 'mentee'], true) ? $selected_role : '';

// Get active semester
$stmt = $pdo->query("SELECT * FROM semesters WHERE status = 'active' ORDER BY id DESC LIMIT 1");
$active_semester = $stmt->fetch();

// If no active semester, create one automatically
if (!$active_semester) {
    $semester_id = $pdo->query("INSERT INTO semesters (semester_name, start_date, end_date, registration_open, registration_close, status)
                VALUES ('2025-1', CURRENT_DATE, CURRENT_DATE + INTERVAL '6 months', CURRENT_DATE, CURRENT_DATE + INTERVAL '1 month', 'active')
                RETURNING id")->fetchColumn();
    
    $stmt = $pdo->query("SELECT * FROM semesters WHERE id = $semester_id");
    $active_semester = $stmt->fetch();
}

// Get all courses for dropdown
$stmt = $pdo->query("SELECT * FROM courses ORDER BY course_code ASC");
$courses = $stmt->fetchAll();

// If no courses exist, provide default list and insert them
if (empty($courses)) {
    $default_courses = [
        ['course_code' => 'CSC101', 'course_name' => 'Introduction to Computer Science'],
        ['course_code' => 'CSC201', 'course_name' => 'Data Structures and Algorithms'],
        ['course_code' => 'CSC301', 'course_name' => 'Database Systems'],
        ['course_code' => 'CSC401', 'course_name' => 'Software Engineering'],
        ['course_code' => 'MAT101', 'course_name' => 'Calculus I'],
        ['course_code' => 'MAT201', 'course_name' => 'Linear Algebra'],
        ['course_code' => 'MAT301', 'course_name' => 'Statistics'],
        ['course_code' => 'PHY101', 'course_name' => 'Introduction to Physics'],
        ['course_code' => 'PHY201', 'course_name' => 'Physics II'],
        ['course_code' => 'ENG101', 'course_name' => 'English Composition'],
        ['course_code' => 'ENG201', 'course_name' => 'Technical Writing'],
        ['course_code' => 'BIO101', 'course_name' => 'Introduction to Biology'],
        ['course_code' => 'CHEM101', 'course_name' => 'General Chemistry'],
        ['course_code' => 'CHEM201', 'course_name' => 'Organic Chemistry'],
        ['course_code' => 'ECON101', 'course_name' => 'Principles of Economics'],
        ['course_code' => 'PSY101', 'course_name' => 'Introduction to Psychology'],
        ['course_code' => 'SOC101', 'course_name' => 'Introduction to Sociology'],
        ['course_code' => 'ACC101', 'course_name' => 'Principles of Accounting'],
        ['course_code' => 'FIN101', 'course_name' => 'Introduction to Finance'],
        ['course_code' => 'MKT101', 'course_name' => 'Principles of Marketing']
    ];
    $courses = $default_courses;
    
    // Insert default courses into database
    foreach ($courses as $course) {
        $stmt = $pdo->prepare("INSERT INTO courses (course_code, course_name) VALUES (?, ?) ON CONFLICT (course_code) DO NOTHING");
        $stmt->execute([$course['course_code'], $course['course_name']]);
    }
}

// Get programmes list
$programmes = [
    'BSc Computer Science',
    'BSc Information Technology',
    'BSc Software Engineering',
    'BSc Business Administration',
    'BSc Economics',
    'BSc Mathematics',
    'BSc Physics',
    'BSc Chemistry',
    'BSc Biology',
    'BSc Psychology',
    'BA English',
    'BA Sociology',
    'BSc Engineering',
    'BSc Accounting',
    'BSc Finance',
    'BSc Marketing',
    'BSc Nursing',
    'BSc Public Health',
    'LLB Law',
    'BSc Architecture',
    'BSc Environmental Science',
    'BA Political Science',
    'BA History',
    'BSc Agriculture',
    'BSc Food Science'
];

// Grades list for mentors
$grades = ['A', 'B+', 'B', 'C+', 'C'];

// Handle registration
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = sanitize($_POST['role']);
    $programme = sanitize($_POST['programme'] ?? '');
    $programme_custom = sanitize($_POST['programme_custom'] ?? '');
    $year_of_study = sanitize($_POST['year_of_study'] ?? '');
    $course_code = sanitize($_POST['course_code'] ?? '');
    $course_custom = sanitize($_POST['course_custom'] ?? '');
    $grade = sanitize($_POST['grade'] ?? '');
    $agree_terms = isset($_POST['agree_terms']) ? 1 : 0;
    
    // Handle custom programme
    if (!empty($programme_custom)) {
        $programme = $programme_custom;
    }
    
    // Handle custom course
    if (empty($course_code) && !empty($course_custom)) {
        $course_code = $course_custom;
    }
    
    if (empty($course_code) && !empty($_POST['course_search_value'])) {
        $course_code = sanitize($_POST['course_search_value']);
    }
    
    if (empty($course_code) && !empty($_POST['course_search_mentee_value'])) {
        $course_code = sanitize($_POST['course_search_mentee_value']);
    }
    
    // Validation
    if (empty($name) || empty($email) || empty($phone) || empty($password) || empty($role)) {
        $error = 'Please fill in all required fields';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } elseif ($role === 'mentor' && empty($course_code)) {
        $error = 'Please select or enter a course you excel in';
    } elseif ($role === 'mentor' && empty($grade)) {
        $error = 'Please select your grade for the course';
    } elseif (!$agree_terms) {
        $error = 'Please agree to the Terms and Conditions';
    } else {
        // Check if email exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Email already registered. Please use a different email or login.';
        } else {
            // Check if phone exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ?");
            $stmt->execute([$phone]);
            if ($stmt->fetch()) {
                $error = 'Phone number already registered. Please use a different number.';
            } else {
                // Check if active semester exists
                if (!$active_semester) {
                    $error = 'No active semester found. Please contact the administrator.';
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Insert user with pending status
                    $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, role, programme, year_of_study, status)
                                           VALUES (?, ?, ?, ?, ?, ?, ?, 'pending') RETURNING id");
                    $stmt->execute([$name, $email, $phone, $hashed_password, $role, $programme, $year_of_study]);
                    $user_id = $stmt->fetchColumn();
                    
                    // Get course name
                    $course_name = '';
                    if (!empty($course_code)) {
                        foreach ($courses as $c) {
                            if ($c['course_code'] === $course_code) {
                                $course_name = $c['course_name'] ?? '';
                                break;
                            }
                        }
                        if (empty($course_name)) {
                            $course_name = $course_custom . ' (Custom)';
                        }
                    }
                    
                    // Create application based on role
                    if ($role === 'mentor') {
                        if (empty($course_code)) {
                            $error = 'Mentors must select or enter a course they excel in.';
                            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);
                        } else {
                            $stmt = $pdo->prepare("INSERT INTO mentor_applications 
                                                   (mentor_id, semester_id, course_code, course_name, grade, status, created_at) 
                                                   VALUES (?, ?, ?, ?, ?, 'pending', NOW())");
                            $stmt->execute([$user_id, $active_semester['id'], $course_code, $course_name, $grade]);
                        }
                    } elseif ($role === 'mentee') {
                        if (!empty($course_code)) {
                            $stmt = $pdo->prepare("INSERT INTO mentee_enrollments 
                                                   (mentee_id, semester_id, course_code, course_name, status, created_at) 
                                                   VALUES (?, ?, ?, ?, 'pending', NOW())");
                            $stmt->execute([$user_id, $active_semester['id'], $course_code, $course_name]);
                        } else {
                            $stmt = $pdo->prepare("INSERT INTO mentee_enrollments 
                                                   (mentee_id, semester_id, course_code, course_name, status, created_at) 
                                                   VALUES (?, ?, 'PENDING', 'No course selected yet', 'pending', NOW())");
                            $stmt->execute([$user_id, $active_semester['id']]);
                        }
                    }
                    
                    logActivity($user_id, 'User registered as ' . $role);
                    
                    // Send welcome email
                    try {
                        require_once __DIR__ . '/../backend/includes/email.php';
                        sendWelcomeEmail($name, $email, $role);
                    } catch (Exception $e) {
                        error_log("Welcome email failed: " . $e->getMessage());
                    }
                    
                    // Store registration data for success page
                    $registration_data = [
                        'name' => $name,
                        'email' => $email,
                        'role' => $role,
                        'user_reference' => getUserReference($user_id),
                        'subscription_price' => getSubscriptionPrice($role),
                        'mobile_money_number' => getSystemSetting('mobile_money_number', MOMO_PHONE),
                        'course' => $course_code,
                        'grade' => $grade,
                        'programme' => $programme
                    ];
                    
                    $success = true;
                    
                    // Clear form data
                    $_POST = array();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - MentorMatch</title>
    <link rel="icon" type="image/svg+xml" href="assets/images/mentormatch-mark.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link href="assets/css/mobile.css" rel="stylesheet">
    <style>
        .registration-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        .registration-header {
            background: linear-gradient(135deg, #176b67, #10534f);
            color: white;
            padding: 25px 30px;
            border-radius: 15px 15px 0 0;
        }
        .registration-header h2 {
            margin-bottom: 5px;
        }
        .registration-header p {
            opacity: 0.8;
            margin-bottom: 0;
        }
        /* ============================================
           SUCCESS DASHBOARD STYLES
           ============================================ */
        .success-dashboard {
            padding: 20px 0;
        }
        .success-icon {
            font-size: 5rem;
            color: #28a745;
            animation: pulse 2s ease-in-out infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        .status-badge {
            padding: 10px 30px;
            border-radius: 50px;
            font-size: 1.1rem;
            font-weight: 600;
            display: inline-block;
        }
        .status-badge.pending {
            background: #fff3cd;
            color: #856404;
            border: 2px solid #ffc107;
        }
        .status-badge .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #ffc107;
            border-top-color: #856404;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 10px;
            vertical-align: middle;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .info-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            border-left: 4px solid #0d6efd;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }
        .info-card:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        .info-card .label {
            font-size: 0.8rem;
            text-transform: uppercase;
            color: #6c757d;
            letter-spacing: 0.5px;
        }
        .info-card .value {
            font-size: 1.1rem;
            font-weight: 600;
            color: #212529;
        }
        .steps-timeline {
            position: relative;
            padding-left: 30px;
        }
        .steps-timeline::before {
            content: '';
            position: absolute;
            left: 8px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #dee2e6;
        }
        .step-item {
            position: relative;
            padding-bottom: 25px;
            padding-left: 15px;
        }
        .step-item:last-child {
            padding-bottom: 0;
        }
        .step-item .step-number {
            position: absolute;
            left: -22px;
            top: 0;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.85rem;
            color: white;
        }
        .step-item .step-number.done {
            background: #28a745;
        }
        .step-item .step-number.current {
            background: #ffc107;
            color: #212529;
            animation: pulse 1.5s ease-in-out infinite;
        }
        .step-item .step-number.pending {
            background: #dee2e6;
            color: #6c757d;
        }
        .step-item .step-title {
            font-weight: 600;
        }
        .step-item .step-desc {
            font-size: 0.9rem;
            color: #6c757d;
        }
        .action-buttons .btn {
            padding: 12px 30px;
            border-radius: 50px;
            font-weight: 600;
        }
        .form-step {
            display: none;
        }
        .form-step.active {
            display: block;
            animation: fadeIn 0.5s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .step-indicators {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 25px;
        }
        .step-indicator {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: #6c757d;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .step-indicator.active {
            background: #176b67;
            color: white;
        }
        .step-indicator.completed {
            background: #28a745;
            color: white;
        }
        .step-line {
            flex: 1;
            height: 3px;
            background: #e9ecef;
            align-self: center;
            max-width: 60px;
        }
        .step-line.completed {
            background: #28a745;
        }
        .role-selector {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }
        .role-option {
            flex: 1;
            padding: 20px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
        }
        .role-option:hover {
            border-color: #176b67;
            background: #f8f9fa;
        }
        .role-option.selected {
            border-color: #176b67;
            background: #e8f2ed;
            box-shadow: 0 0 0 3px rgba(23, 107, 103, 0.18);
        }
        .role-option i {
            font-size: 2.5rem;
            display: block;
            margin-bottom: 10px;
        }
        .role-option .role-title {
            font-weight: 700;
        }
        .role-option .role-desc {
            font-size: 0.85rem;
            color: #6c757d;
        }
        .role-option input[type="radio"] {
            display: none;
        }
        .form-control:focus, .form-select:focus {
            border-color: #176b67;
            box-shadow: 0 0 0 0.2rem rgba(23, 107, 103, 0.18);
        }
        .password-toggle {
            position: relative;
        }
        .password-toggle .toggle-btn {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
        }
        .terms-text {
            font-size: 0.85rem;
            color: #6c757d;
        }
        .terms-text a {
            color: #0d6efd;
        }
        .badge-required {
            color: #dc3545;
            font-size: 0.7rem;
        }
        .optional-text {
            color: #6c757d;
            font-size: 0.8rem;
            font-weight: normal;
        }
        .programme-search-box, .course-search-box {
            position: relative;
        }
        .programme-search-box .search-results, .course-search-box .search-results {
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
        }
        .programme-search-box .search-results.show, .course-search-box .search-results.show {
            display: block;
        }
        .search-result-item {
            padding: 8px 12px;
            cursor: pointer;
            transition: background 0.2s ease;
        }
        .search-result-item:hover {
            background: #e7f1ff;
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
        @media (max-width: 768px) {
            .role-selector {
                flex-direction: column;
            }
            .registration-header {
                padding: 20px;
            }
            .success-dashboard .row > div {
                margin-bottom: 15px;
            }
            .action-buttons .btn {
                width: 100%;
                margin-bottom: 10px;
            }
        }
    </style>
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <a class="auth-brand" href="index.php"><img class="brand-mark-image" src="assets/images/mentormatch-mark.svg" alt=""> MentorMatch</a>
            <a href="login.php" class="btn btn-outline-secondary">Sign in</a>
        </div>
        <div class="row justify-content-center">
            <div class="col-lg-8 col-md-10">
                <div class="card registration-card">
                    <!-- Header -->
                    <div class="registration-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h2><i class="bi bi-person-plus"></i> <?php echo $success ? 'Registration Complete!' : 'Join MentorMatch'; ?></h2>
                                <p><?php echo $success ? 'Your account has been created successfully.' : 'Create your account and start your mentorship journey'; ?></p>
                            </div>
                            <div>
                                <span class="badge bg-light text-dark">
                                    <i class="bi bi-mortarboard"></i> 
                                    <?php echo $active_semester ? htmlspecialchars($active_semester['semester_name']) : 'No Active Semester'; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-body p-4 p-lg-5">
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success && $registration_data): ?>
                            <!-- ==========================================
                            SUCCESS DASHBOARD - WAITING FOR APPROVAL
                            ========================================== -->
                            <div class="success-dashboard">
                                <!-- Success Icon -->
                                <div class="text-center mb-4">
                                    <div class="success-icon"><i class="bi bi-check-circle-fill"></i></div>
                                    <h3 class="mt-3 text-success">Registration Successful!</h3>
                                    <p class="text-muted">Your account has been created and is pending admin approval.</p>
                                </div>
                                
                                <!-- Status Badge -->
                                <div class="text-center mb-4">
                                    <div class="status-badge pending">
                                        <span class="spinner"></span>
                                        Pending Approval
                                    </div>
                                    <p class="text-muted mt-2">
                                        <i class="bi bi-clock"></i> 
                                        Estimated waiting time: 1-2 business days
                                    </p>
                                </div>
                                
                                <!-- Registration Details -->
                                <div class="row g-3 mb-4">
                                    <div class="col-md-6">
                                        <div class="info-card">
                                            <div class="label"><i class="bi bi-person"></i> Full Name</div>
                                            <div class="value"><?php echo htmlspecialchars($registration_data['name']); ?></div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="info-card">
                                            <div class="label"><i class="bi bi-envelope"></i> Email</div>
                                            <div class="value"><?php echo htmlspecialchars($registration_data['email']); ?></div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="info-card">
                                            <div class="label"><i class="bi bi-person-badge"></i> Role</div>
                                            <div class="value">
                                                <span class="badge <?php echo $registration_data['role'] === 'mentor' ? 'bg-primary' : 'bg-success'; ?>">
                                                    <?php echo ucfirst($registration_data['role']); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="info-card">
                                            <div class="label"><i class="bi bi-book"></i> Course</div>
                                            <div class="value"><?php echo htmlspecialchars($registration_data['course'] ?? 'Not specified (will add later)'); ?></div>
                                        </div>
                                    </div>
                                    <?php if ($registration_data['grade']): ?>
                                    <div class="col-md-6">
                                        <div class="info-card">
                                            <div class="label"><i class="bi bi-award"></i> Grade</div>
                                            <div class="value">
                                                <span class="badge bg-success"><?php echo htmlspecialchars($registration_data['grade']); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($registration_data['programme']): ?>
                                    <div class="col-md-6">
                                        <div class="info-card">
                                            <div class="label"><i class="bi bi-mortarboard"></i> Programme</div>
                                            <div class="value"><?php echo htmlspecialchars($registration_data['programme']); ?></div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Approval Steps Timeline -->
                                <div class="card bg-light mb-4">
                                    <div class="card-body">
                                        <h6 class="mb-3"><i class="bi bi-clock-history"></i> Application Progress</h6>
                                        <div class="steps-timeline">
                                            <div class="step-item">
                                                <div class="step-number done">✓</div>
                                                <div class="step-title">Registration Submitted</div>
                                                <div class="step-desc">Your application has been received</div>
                                            </div>
                                            <div class="step-item">
                                                <div class="step-number current">2</div>
                                                <div class="step-title">Awaiting Admin Review</div>
                                                <div class="step-desc">An administrator is reviewing your application</div>
                                            </div>
                                            <div class="step-item">
                                                <div class="step-number pending">3</div>
                                                <div class="step-title">Account Activation</div>
                                                <div class="step-desc">You'll receive an email once approved</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- What to Expect -->
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i>
                                    <strong>What happens next?</strong>
                                    <ul class="mb-0 mt-2">
                                        <li>An administrator will review your application within 1-2 business days</li>
                                        <li>You will receive an email notification once your account is approved</li>
                                        <li>You can pay your subscription now; request payment confirmation after your account is approved</li>
                                        <?php if ($registration_data['role'] === 'mentor'): ?>
                                            <li>As a mentor, you'll be able to set your rates and availability</li>
                                        <?php else: ?>
                                            <li>As a mentee, you'll be able to search for mentors and book sessions</li>
                                        <?php endif; ?>
                                    </ul>
                                </div>

                                <div class="card border-primary mb-4">
                                    <div class="card-body">
                                        <h5 class="card-title"><i class="bi bi-phone me-1" aria-hidden="true"></i> Subscription payment instructions</h5>
                                        <p>Monthly price: <strong>ZMW <?php echo number_format($registration_data['subscription_price'], 2); ?></strong></p>
                                        <?php if ($registration_data['mobile_money_number'] !== ''): ?>
                                            <p class="mb-2">Send your payment to: <strong class="fs-5"><?php echo htmlspecialchars($registration_data['mobile_money_number'], ENT_QUOTES, 'UTF-8'); ?></strong></p>
                                        <?php else: ?>
                                            <p class="mb-2">The administrator has not added a mobile-money number yet. Contact the administrator for payment instructions.</p>
                                        <?php endif; ?>
                                        <p class="mb-0">Use this user reference as your payment reference: <strong><?php echo htmlspecialchars($registration_data['user_reference'], ENT_QUOTES, 'UTF-8'); ?></strong>. Keep it for your records. After your account is approved, sign in, open Subscriptions, and request payment confirmation.</p>
                                    </div>
                                </div>
                                
                                <!-- Action Buttons -->
                                <div class="action-buttons text-center">
                                    <a href="login.php" class="btn btn-primary">
                                        <i class="bi bi-box-arrow-in-right"></i> Go to Login
                                    </a>
                                    <a href="index.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-house"></i> Home
                                    </a>
                                    <button class="btn btn-outline-info" onclick="window.print()">
                                        <i class="bi bi-printer"></i> Print Confirmation
                                    </button>
                                </div>
                            </div>
                            
                        <?php else: ?>
                            <?php if (!$active_semester): ?>
                                <div class="alert alert-danger">
                                    <i class="bi bi-exclamation-triangle"></i> 
                                    <strong>No active semester found!</strong> 
                                    Registrations are currently closed. Please contact the administrator.
                                </div>
                            <?php endif; ?>
                            
                            <!-- ==========================================
                            REGISTRATION FORM
                            ========================================== -->
                            <form method="POST" action="" id="registrationForm" novalidate>
                                <!-- Step 1: Account Information -->
                                <div class="form-step active" id="step1">
                                    <h5 class="mb-3"><i class="bi bi-person"></i> Account Information</h5>
                                    <hr>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="name" class="form-label">Full Name <span class="badge-required">*</span></label>
                                            <input type="text" class="form-control" id="name" name="name" 
                                                   placeholder="Enter your full name" 
                                                   value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="email" class="form-label">Email Address <span class="badge-required">*</span></label>
                                            <input type="email" class="form-control" id="email" name="email" 
                                                   placeholder="Enter your email" 
                                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">Phone Number <span class="badge-required">*</span></label>
                                        <input type="tel" class="form-control" id="phone" name="phone" 
                                               placeholder="+2609XXXXXXXX" 
                                               value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" required>
                                        <small class="text-muted">Enter your mobile money registered number</small>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="password" class="form-label">Password <span class="badge-required">*</span></label>
                                            <div class="password-toggle">
                                                <input type="password" class="form-control" id="password" name="password" 
                                                       placeholder="Min 6 characters" required minlength="6">
                                                <button type="button" class="toggle-btn" onclick="togglePassword('password')">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="confirm_password" class="form-label">Confirm Password <span class="badge-required">*</span></label>
                                            <div class="password-toggle">
                                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                                       placeholder="Re-enter password" required>
                                                <button type="button" class="toggle-btn" onclick="togglePassword('confirm_password')">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="programme" class="form-label">Programme</label>
                                            <div class="programme-search-box">
                                                <input type="text" class="form-control" id="programme_search" 
                                                       placeholder="Search or type programme name..." 
                                                       onkeyup="searchProgramme(this.value)" onfocus="searchProgramme(this.value)"
                                                       value="<?php echo htmlspecialchars($_POST['programme'] ?? ''); ?>">
                                                <input type="hidden" name="programme" id="programme" value="<?php echo htmlspecialchars($_POST['programme'] ?? ''); ?>">
                                                <input type="hidden" name="programme_custom" id="programme_custom" value="">
                                                <div class="search-results" id="programmeResults"></div>
                                            </div>
                                            <small class="text-muted">Type to search existing programmes or enter a custom one</small>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="year_of_study" class="form-label">Year of Study</label>
                                            <select class="form-select" id="year_of_study" name="year_of_study">
                                                <option value="">Select Year</option>
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <option value="<?php echo $i; ?>" 
                                                            <?php echo (isset($_POST['year_of_study']) && $_POST['year_of_study'] == $i) ? 'selected' : ''; ?>>
                                                        Year <?php echo $i; ?>
                                                    </option>
                                                <?php endfor; ?>
                                                <option value="Postgraduate">Postgraduate</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-end mt-3">
                                        <button type="button" class="btn btn-primary" onclick="goToStep(2)">
                                            Next <i class="bi bi-arrow-right"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Step 2: Role Selection -->
                                <div class="form-step" id="step2">
                                    <h5 class="mb-3"><i class="bi bi-person-badge"></i> Choose Your Role</h5>
                                    <hr>
                                    <p class="text-muted">Select how you want to participate in MentorMatch</p>
                                    
                                    <div class="role-selector">
                                        <label class="role-option <?php echo $selected_role === 'mentor' ? 'selected' : ''; ?>">
                                            <input type="radio" name="role" value="mentor" 
                                                   <?php echo $selected_role === 'mentor' ? 'checked' : ''; ?> 
                                                   onchange="toggleRoleFields()">
                                            <i class="bi bi-person-badge text-primary"></i>
                                            <div class="role-title">Mentor</div>
                                            <div class="role-desc">Help others succeed by sharing your knowledge</div>
                                            <span class="badge bg-primary mt-2">I want to help</span>
                                        </label>
                                        
                                        <label class="role-option <?php echo $selected_role === 'mentee' ? 'selected' : ''; ?>">
                                            <input type="radio" name="role" value="mentee" 
                                                   <?php echo $selected_role === 'mentee' ? 'checked' : ''; ?> 
                                                   onchange="toggleRoleFields()">
                                            <i class="bi bi-person text-success"></i>
                                            <div class="role-title">Mentee</div>
                                            <div class="role-desc">Get academic support from experienced peers</div>
                                            <span class="badge bg-success mt-2">I need help</span>
                                        </label>
                                    </div>
                                    
                                    <!-- Mentor-specific fields -->
                                    <div id="mentorFields" style="display: <?php echo $selected_role === 'mentor' ? 'block' : 'none'; ?>;">
                                        <div class="alert alert-info">
                                            <i class="bi bi-info-circle"></i> 
                                            As a mentor, you'll help students who are struggling with courses you've excelled in.
                                            You can set your own rates for sessions.
                                        </div>
                                        <div class="row">
                                            <div class="col-md-8 mb-3">
                                                <label for="course_search" class="form-label">Course You Excel In <span class="badge-required">*</span></label>
                                                <div class="course-search-box">
                                                    <input type="text" class="form-control" id="course_search" 
                                                           name="course_search_value"
                                                           placeholder="Search or type course code..." 
                                                           onkeyup="searchCourse(this.value)" onfocus="searchCourse(this.value)"
                                                           value="<?php echo htmlspecialchars($_POST['course_search_value'] ?? ''); ?>">
                                                    <input type="hidden" name="course_code" id="course_code" value="<?php echo htmlspecialchars($_POST['course_code'] ?? ''); ?>">
                                                    <input type="hidden" name="course_custom" id="course_custom" value="<?php echo htmlspecialchars($_POST['course_custom'] ?? ''); ?>">
                                                    <div class="search-results" id="courseResults"></div>
                                                </div>
                                                <div id="selectedCourseDisplay" class="selected-course-display" style="display: <?php echo (!empty($_POST['course_code']) || !empty($_POST['course_search_value'])) ? 'inline-block' : 'none'; ?>;">
                                                    <i class="bi bi-check-circle text-success"></i>
                                                    <span id="selectedCourseText"><?php echo htmlspecialchars($_POST['course_search_value'] ?? $_POST['course_code'] ?? ''); ?></span>
                                                    <span class="remove-course" onclick="clearCourseSelection()" title="Remove course">
                                                        <i class="bi bi-x-circle"></i>
                                                    </span>
                                                </div>
                                                <small class="text-muted">Type to search existing courses or enter a custom course code</small>
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label for="grade" class="form-label">Grade Achieved <span class="badge-required">*</span></label>
                                                <select class="form-select" id="grade" name="grade">
                                                    <option value="">Select Grade</option>
                                                    <?php foreach ($grades as $g): ?>
                                                        <option value="<?php echo $g; ?>" 
                                                                <?php echo (isset($_POST['grade']) && $_POST['grade'] === $g) ? 'selected' : ''; ?>>
                                                            <?php echo $g; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Mentee-specific fields -->
                                    <div id="menteeFields" style="display: <?php echo $selected_role === 'mentee' ? 'block' : 'none'; ?>;">
                                        <div class="alert alert-info">
                                            <i class="bi bi-info-circle"></i> 
                                            As a mentee, you'll be matched with mentors who have excelled in your courses.
                                            You pay per session and can choose mentors based on ratings and rates.
                                            <br><strong>Note:</strong> You can skip selecting a course now and add it later after your account is approved.
                                        </div>
                                        <div class="mb-3">
                                            <label for="course_search_mentee" class="form-label">Course You Need Help With <span class="optional-text">(Optional)</span></label>
                                            <div class="course-search-box">
                                                <input type="text" class="form-control" id="course_search_mentee" 
                                                       name="course_search_mentee_value"
                                                       placeholder="Search or type course code... (optional)" 
                                                       onkeyup="searchCourseMentee(this.value)" onfocus="searchCourseMentee(this.value)"
                                                       value="<?php echo htmlspecialchars($_POST['course_search_mentee_value'] ?? ''); ?>">
                                                <input type="hidden" name="course_code" id="course_code_mentee" value="<?php echo htmlspecialchars($_POST['course_code'] ?? ''); ?>">
                                                <input type="hidden" name="course_custom" id="course_custom_mentee" value="<?php echo htmlspecialchars($_POST['course_custom'] ?? ''); ?>">
                                                <div class="search-results" id="courseResultsMentee"></div>
                                            </div>
                                            <div id="selectedCourseMenteeDisplay" class="selected-course-display" style="display: <?php echo (!empty($_POST['course_code']) || !empty($_POST['course_search_mentee_value'])) ? 'inline-block' : 'none'; ?>;">
                                                <i class="bi bi-check-circle text-success"></i>
                                                <span id="selectedCourseMenteeText"><?php echo htmlspecialchars($_POST['course_search_mentee_value'] ?? $_POST['course_code'] ?? ''); ?></span>
                                                <span class="remove-course" onclick="clearCourseMenteeSelection()" title="Remove course">
                                                    <i class="bi bi-x-circle"></i>
                                                </span>
                                            </div>
                                            <small class="text-muted">Type to search existing courses or enter a custom course code</small>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between mt-3">
                                        <button type="button" class="btn btn-secondary" onclick="goToStep(1)">
                                            <i class="bi bi-arrow-left"></i> Back
                                        </button>
                                        <button type="button" class="btn btn-primary" onclick="goToStep(3)">
                                            Next <i class="bi bi-arrow-right"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Step 3: Terms & Submit -->
                                <div class="form-step" id="step3">
                                    <h5 class="mb-3"><i class="bi bi-check-circle"></i> Review & Submit</h5>
                                    <hr>
                                    
                                    <div class="alert alert-info">
                                        <i class="bi bi-info-circle"></i> 
                                        Please review your information before submitting. You won't be able to edit after submission.
                                    </div>
                                    
                                    <div class="card bg-light mb-3">
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <small class="text-muted">Name</small>
                                                    <p class="fw-bold" id="reviewName">-</p>
                                                </div>
                                                <div class="col-md-6">
                                                    <small class="text-muted">Email</small>
                                                    <p class="fw-bold" id="reviewEmail">-</p>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <small class="text-muted">Phone</small>
                                                    <p class="fw-bold" id="reviewPhone">-</p>
                                                </div>
                                                <div class="col-md-6">
                                                    <small class="text-muted">Role</small>
                                                    <p class="fw-bold" id="reviewRole">-</p>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <small class="text-muted">Programme</small>
                                                    <p class="fw-bold" id="reviewProgramme">-</p>
                                                </div>
                                                <div class="col-md-6">
                                                    <small class="text-muted">Year of Study</small>
                                                    <p class="fw-bold" id="reviewYear">-</p>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-12">
                                                    <small class="text-muted">Course</small>
                                                    <p class="fw-bold" id="reviewCourse">-</p>
                                                </div>
                                            </div>
                                            <div id="reviewGradeContainer">
                                                <div class="row">
                                                    <div class="col-md-12">
                                                        <small class="text-muted">Grade</small>
                                                        <p class="fw-bold" id="reviewGrade">-</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="agree_terms" name="agree_terms" required>
                                            <label class="form-check-label terms-text" for="agree_terms">
                                                I agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms and Conditions</a> 
                                                and confirm that the information provided is accurate.
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="alert alert-warning">
                                        <i class="bi bi-clock"></i> 
                                        <strong>Note:</strong> Your registration will be reviewed by an administrator. 
                                        This process may take 1-2 business days. You will receive an email notification once approved.
                                    </div>
                                    
                                    <div class="d-flex justify-content-between mt-3">
                                        <button type="button" class="btn btn-secondary" onclick="goToStep(2)">
                                            <i class="bi bi-arrow-left"></i> Back
                                        </button>
                                        <button type="submit" class="btn btn-success">
                                            <i class="bi bi-check-circle"></i> Submit Registration
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Step Indicators -->
                                <div class="step-indicators mt-4">
                                    <div class="step-indicator active" id="indicator1">1</div>
                                    <div class="step-line" id="line1"></div>
                                    <div class="step-indicator" id="indicator2">2</div>
                                    <div class="step-line" id="line2"></div>
                                    <div class="step-indicator" id="indicator3">3</div>
                                </div>
                            </form>
                        <?php endif; ?>
                        
                        <div class="text-center mt-3">
                            <?php if ($success): ?>
                                <small class="text-muted">You will receive an email once your account is approved.</small>
                            <?php else: ?>
                                <p>Already have an account? <a href="login.php">Login here</a></p>
                                <small class="text-muted">By registering, you agree to our terms of service.</small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Terms Modal -->
    <div class="modal fade" id="termsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Terms and Conditions</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6>1. Acceptance of Terms</h6>
                    <p>By registering for MentorMatch, you agree to abide by these terms and conditions.</p>
                    
                    <h6>2. User Responsibilities</h6>
                    <ul>
                        <li>Provide accurate and truthful information</li>
                        <li>Respect all users and maintain professional conduct</li>
                        <li>Attend scheduled sessions punctually</li>
                        <li>Provide constructive feedback</li>
                    </ul>
                    
                    <h6>3. Mentor Responsibilities</h6>
                    <ul>
                        <li>Prepare adequately for mentoring sessions</li>
                        <li>Provide helpful and accurate academic support</li>
                        <li>Maintain confidentiality of mentee information</li>
                        <li>Track session hours accurately</li>
                    </ul>
                    
                    <h6>4. Mentee Responsibilities</h6>
                    <ul>
                        <li>Come prepared to sessions</li>
                        <li>Respect mentor's time and expertise</li>
                        <li>Provide honest feedback</li>
                        <li>Complete payments as required</li>
                    </ul>
                    
                    <h6>5. Payment Terms</h6>
                    <ul>
                        <li>Mentees pay for sessions via Mobile Money</li>
                        <li>A 5% service fee applies to all payments</li>
                        <li>Mentors receive 95% of the session fee</li>
                        <li>No refunds for completed sessions</li>
                    </ul>
                    
                    <h6>6. Code of Conduct</h6>
                    <ul>
                        <li>No harassment or discrimination</li>
                        <li>No sharing of inappropriate content</li>
                        <li>No academic dishonesty</li>
                        <li>Respect intellectual property</li>
                    </ul>
                    
                    <h6>7. Termination</h6>
                    <p>MentorMatch reserves the right to suspend or terminate accounts that violate these terms.</p>
                    
                    <h6>8. Privacy Policy</h6>
                    <p>Your personal information will be kept confidential and used only for platform operations.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // ============================================
        // PROGRAMME SEARCH FUNCTION
        // ============================================
        const programmes = <?php echo json_encode($programmes); ?>;
        
        function searchProgramme(query) {
            const resultsDiv = document.getElementById('programmeResults');
            
            if (query.length === 0) {
                resultsDiv.classList.remove('show');
                return;
            }
            
            const matches = programmes.filter(p => 
                p.toLowerCase().includes(query.toLowerCase())
            );
            
            if (matches.length > 0) {
                resultsDiv.innerHTML = matches.map(p => 
                    `<div class="search-result-item" onclick="selectProgramme('${p}')">${p}</div>`
                ).join('');
                resultsDiv.classList.add('show');
            } else {
                resultsDiv.innerHTML = `
                    <div class="search-result-item text-muted" onclick="selectProgramme('${query}')">
                        <i class="bi bi-plus-circle"></i> Add "${query}" as custom programme
                    </div>
                `;
                resultsDiv.classList.add('show');
            }
        }
        
        function selectProgramme(value) {
            document.getElementById('programme_search').value = value;
            document.getElementById('programme').value = value;
            document.getElementById('programme_custom').value = value;
            document.getElementById('programmeResults').classList.remove('show');
            document.getElementById('programmeResults').innerHTML = '';
        }
        
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.programme-search-box')) {
                document.getElementById('programmeResults').classList.remove('show');
            }
        });
        
        // ============================================
        // COURSE SEARCH FUNCTION (Mentor)
        // ============================================
        const courses = <?php echo json_encode($courses); ?>;
        
        function searchCourse(query) {
            const resultsDiv = document.getElementById('courseResults');
            
            if (query.length === 0) {
                resultsDiv.classList.remove('show');
                return;
            }
            
            const matches = courses.filter(c => 
                c.course_code.toLowerCase().includes(query.toLowerCase()) ||
                c.course_name.toLowerCase().includes(query.toLowerCase())
            );
            
            if (matches.length > 0) {
                resultsDiv.innerHTML = matches.map(c => 
                    `<div class="search-result-item" onclick="selectCourse('${c.course_code}', '${c.course_name}')">
                        <strong>${c.course_code}</strong> - ${c.course_name}
                    </div>`
                ).join('');
                resultsDiv.classList.add('show');
            } else {
                resultsDiv.innerHTML = `
                    <div class="search-result-item text-muted" onclick="selectCourse('${query.toUpperCase()}', '${query} (Custom)')">
                        <i class="bi bi-plus-circle"></i> Add "${query.toUpperCase()}" as custom course
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
        
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.course-search-box')) {
                document.getElementById('courseResults').classList.remove('show');
            }
        });
        
        // ============================================
        // COURSE SEARCH FUNCTION (Mentee)
        // ============================================
        function searchCourseMentee(query) {
            const resultsDiv = document.getElementById('courseResultsMentee');
            
            if (query.length === 0) {
                resultsDiv.classList.remove('show');
                return;
            }
            
            const matches = courses.filter(c => 
                c.course_code.toLowerCase().includes(query.toLowerCase()) ||
                c.course_name.toLowerCase().includes(query.toLowerCase())
            );
            
            if (matches.length > 0) {
                resultsDiv.innerHTML = matches.map(c => 
                    `<div class="search-result-item" onclick="selectCourseMentee('${c.course_code}', '${c.course_name}')">
                        <strong>${c.course_code}</strong> - ${c.course_name}
                    </div>`
                ).join('');
                resultsDiv.classList.add('show');
            } else {
                resultsDiv.innerHTML = `
                    <div class="search-result-item text-muted" onclick="selectCourseMentee('${query.toUpperCase()}', '${query} (Custom)')">
                        <i class="bi bi-plus-circle"></i> Add "${query.toUpperCase()}" as custom course
                    </div>
                `;
                resultsDiv.classList.add('show');
            }
        }
        
        function selectCourseMentee(code, name) {
            const displayText = code + ' - ' + name;
            document.getElementById('course_search_mentee').value = code;
            document.getElementById('course_code_mentee').value = code;
            document.getElementById('course_custom_mentee').value = code;
            document.getElementById('courseResultsMentee').classList.remove('show');
            document.getElementById('courseResultsMentee').innerHTML = '';
            
            document.getElementById('selectedCourseMenteeText').textContent = displayText;
            document.getElementById('selectedCourseMenteeDisplay').style.display = 'inline-block';
        }
        
        function clearCourseMenteeSelection() {
            document.getElementById('course_search_mentee').value = '';
            document.getElementById('course_code_mentee').value = '';
            document.getElementById('course_custom_mentee').value = '';
            document.getElementById('selectedCourseMenteeDisplay').style.display = 'none';
            document.getElementById('course_search_mentee').focus();
        }
        
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.course-search-box')) {
                document.getElementById('courseResultsMentee').classList.remove('show');
            }
        });
        
        // ============================================
        // TOGGLE PASSWORD VISIBILITY
        // ============================================
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = field.parentElement.querySelector('.toggle-btn i');
            if (field.type === 'password') {
                field.type = 'text';
                icon.className = 'bi bi-eye-slash';
            } else {
                field.type = 'password';
                icon.className = 'bi bi-eye';
            }
        }
        
        // ============================================
        // TOGGLE ROLE FIELDS
        // ============================================
        function toggleRoleFields() {
            const role = document.querySelector('input[name="role"]:checked');
            const mentorFields = document.getElementById('mentorFields');
            const menteeFields = document.getElementById('menteeFields');
            
            if (role) {
                if (role.value === 'mentor') {
                    mentorFields.style.display = 'block';
                    menteeFields.style.display = 'none';
                } else if (role.value === 'mentee') {
                    mentorFields.style.display = 'none';
                    menteeFields.style.display = 'block';
                }
            } else {
                mentorFields.style.display = 'none';
                menteeFields.style.display = 'none';
            }
            
            document.querySelectorAll('.role-option').forEach(el => {
                el.classList.remove('selected');
                const radio = el.querySelector('input[type="radio"]');
                if (radio && radio.checked) {
                    el.classList.add('selected');
                }
            });
        }
        
        // ============================================
        // STEP NAVIGATION
        // ============================================
        let currentStep = 1;
        
        function goToStep(step) {
            if (step > currentStep) {
                if (!validateStep(currentStep)) {
                    return;
                }
            }
            
            document.querySelectorAll('.form-step').forEach(el => {
                el.classList.remove('active');
            });
            
            document.getElementById('step' + step).classList.add('active');
            
            for (let i = 1; i <= 3; i++) {
                const indicator = document.getElementById('indicator' + i);
                indicator.classList.remove('active', 'completed');
                if (i === step) {
                    indicator.classList.add('active');
                } else if (i < step) {
                    indicator.classList.add('completed');
                }
            }
            
            for (let i = 1; i <= 2; i++) {
                const line = document.getElementById('line' + i);
                line.classList.toggle('completed', i < step);
            }
            
            if (step === 3) {
                updateReview();
            }
            
            currentStep = step;
            document.querySelector('.card-body').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        
        // ============================================
        // VALIDATE STEP
        // ============================================
        function validateStep(step) {
            if (step === 1) {
                const name = document.getElementById('name').value.trim();
                const email = document.getElementById('email').value.trim();
                const phone = document.getElementById('phone').value.trim();
                const password = document.getElementById('password').value;
                const confirm = document.getElementById('confirm_password').value;
                
                if (!name) {
                    alert('Please enter your full name.');
                    document.getElementById('name').focus();
                    return false;
                }
                if (!email) {
                    alert('Please enter your email address.');
                    document.getElementById('email').focus();
                    return false;
                }
                if (!phone) {
                    alert('Please enter your phone number.');
                    document.getElementById('phone').focus();
                    return false;
                }
                if (password.length < 6) {
                    alert('Password must be at least 6 characters.');
                    document.getElementById('password').focus();
                    return false;
                }
                if (password !== confirm) {
                    alert('Passwords do not match.');
                    document.getElementById('confirm_password').focus();
                    return false;
                }
                return true;
            }
            
            if (step === 2) {
                const role = document.querySelector('input[name="role"]:checked');
                if (!role) {
                    alert('Please select a role (Mentor or Mentee).');
                    return false;
                }
                
                if (role.value === 'mentor') {
                    const courseSearch = document.getElementById('course_search').value.trim();
                    const courseCode = document.getElementById('course_code').value.trim();
                    
                    if (!courseSearch && !courseCode) {
                        alert('Please select or enter a course you excel in.');
                        document.getElementById('course_search').focus();
                        return false;
                    }
                    
                    if (courseSearch && !courseCode) {
                        document.getElementById('course_code').value = courseSearch;
                        document.getElementById('course_custom').value = courseSearch;
                    }
                    
                    const grade = document.getElementById('grade').value;
                    if (!grade) {
                        alert('Please select your grade for the course.');
                        document.getElementById('grade').focus();
                        return false;
                    }
                }
                return true;
            }
            
            return true;
        }
        
        // ============================================
        // UPDATE REVIEW
        // ============================================
        function updateReview() {
            document.getElementById('reviewName').textContent = document.getElementById('name').value || '-';
            document.getElementById('reviewEmail').textContent = document.getElementById('email').value || '-';
            document.getElementById('reviewPhone').textContent = document.getElementById('phone').value || '-';
            
            const role = document.querySelector('input[name="role"]:checked');
            document.getElementById('reviewRole').textContent = role ? role.value.charAt(0).toUpperCase() + role.value.slice(1) : '-';
            
            const programme = document.getElementById('programme').value || document.getElementById('programme_search').value;
            document.getElementById('reviewProgramme').textContent = programme || 'Not specified';
            document.getElementById('reviewYear').textContent = document.getElementById('year_of_study').value || 'Not specified';
            
            let courseDisplay = 'Not selected (will add later)';
            if (role && role.value === 'mentor') {
                courseDisplay = document.getElementById('course_search').value || document.getElementById('course_code').value || 'Not selected';
            } else if (role && role.value === 'mentee') {
                courseDisplay = document.getElementById('course_search_mentee').value || document.getElementById('course_code_mentee').value || 'Not selected (will add later)';
            }
            document.getElementById('reviewCourse').textContent = courseDisplay;
            
            const grade = document.getElementById('grade').value;
            document.getElementById('reviewGrade').textContent = grade || '-';
            
            const gradeContainer = document.getElementById('reviewGradeContainer');
            if (role && role.value === 'mentor') {
                gradeContainer.style.display = 'block';
            } else {
                gradeContainer.style.display = 'none';
            }
        }
        
        // ============================================
        // INITIALIZE
        // ============================================
        document.addEventListener('DOMContentLoaded', function() {
            toggleRoleFields();
        });
    </script>
</body>
</html>