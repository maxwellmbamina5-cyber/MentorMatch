<?php
// mentee/book.php
require_once dirname(__DIR__, 2) . '/backend/includes/functions.php';
requireMentee();
requireActiveSubscription();

$user = getCurrentUser();
$error = '';
$success = '';
$mentor = null;
$course_code = '';
$mentor_courses = [];
$duration_options = [30, 60, 90, 120];

// Get active semester
$stmt = $pdo->query("SELECT * FROM semesters WHERE status = 'active' ORDER BY id DESC LIMIT 1");
$active_semester = $stmt->fetch();

if (!$active_semester) {
    $error = 'No active semester found. Please contact admin.';
}

// Get mentor details
if (isset($_GET['mentor_id'])) {
    $mentor_id = (int)$_GET['mentor_id'];
    $course_code = sanitize($_GET['course'] ?? '');
    
    // Get mentor details with full info
    $stmt = $pdo->prepare("
        SELECT u.*, 
               r.rate_per_hour,
               r.course_code as mentor_course,
               (SELECT AVG(rating) FROM feedback WHERE mentor_id = u.id) as avg_rating,
               (SELECT COUNT(*) FROM feedback WHERE mentor_id = u.id) as total_reviews,
               (SELECT COUNT(*) FROM sessions WHERE mentor_id = u.id AND status = 'completed') as total_sessions,
               (SELECT COUNT(*) FROM sessions WHERE mentor_id = u.id AND status IN ('pending', 'confirmed')) as pending_sessions,
               ma.grade
        FROM users u
        JOIN mentor_rates r ON u.id = r.mentor_id AND r.course_code = ?
        JOIN mentor_applications ma ON u.id = ma.mentor_id AND ma.course_code = r.course_code
        WHERE u.id = ? AND u.role = 'mentor' AND u.status = 'active'
          AND ma.status = 'approved' AND ma.semester_id = ?
          AND EXISTS (
              SELECT 1 FROM subscriptions sub
              WHERE sub.user_id = u.id AND sub.status = 'active' AND sub.expires_at > NOW()
          )
    ");
    $stmt->execute([$course_code, $mentor_id, $active_semester['id'] ?? 0]);
    $mentor = $stmt->fetch();
    
    if (!$mentor) {
        $error = 'Mentor not found or not available.';
    } else {
        // Get all courses this mentor offers
        $stmt = $pdo->prepare("
            SELECT r.course_code, r.rate_per_hour, ma.grade
            FROM mentor_rates r
            JOIN mentor_applications ma ON ma.mentor_id = r.mentor_id AND ma.course_code = r.course_code
            WHERE r.mentor_id = ? AND ma.status = 'approved' AND ma.semester_id = ?
            ORDER BY r.course_code
        ");
        $stmt->execute([$mentor_id, $active_semester['id'] ?? 0]);
        $mentor_courses = $stmt->fetchAll();
    }
}

// Process booking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book'])) {
    if (!hasActiveSubscription($user['id'])) {
        header('Location: ../subscription.php?required=1');
        exit();
    }

    $mentor_id = filter_var($_POST['mentor_id'] ?? null, FILTER_VALIDATE_INT);
    $course_code = is_string($_POST['course_code'] ?? null) ? sanitize($_POST['course_code']) : '';
    $session_date = is_string($_POST['session_date'] ?? null) ? trim($_POST['session_date']) : '';
    $session_time = is_string($_POST['session_time'] ?? null) ? trim($_POST['session_time']) : '';
    $duration_minutes = filter_var($_POST['duration_minutes'] ?? null, FILTER_VALIDATE_INT);
    $date_value = DateTime::createFromFormat('!Y-m-d', $session_date);
    $time_value = DateTime::createFromFormat('!H:i', $session_time);
    $valid_date = $date_value && $date_value->format('Y-m-d') === $session_date;
    $valid_time = $time_value && $time_value->format('H:i') === $session_time;
    $valid_booking_window = $valid_date
        && $session_date >= date('Y-m-d')
        && $session_date <= date('Y-m-d', strtotime('+14 days'));
    $valid_duration = in_array($duration_minutes, $duration_options, true);
    $datetime = $valid_booking_window && $valid_time
        ? $session_date . ' ' . $session_time . ':00'
        : '';
    
    $mentor_eligible = $pdo->prepare("
        SELECT COUNT(*)
        FROM users u
        JOIN mentor_rates r ON r.mentor_id = u.id AND r.course_code = ?
        JOIN mentor_applications ma ON ma.mentor_id = u.id AND ma.course_code = r.course_code
        WHERE u.id = ? AND u.role = 'mentor' AND u.status = 'active'
          AND ma.status = 'approved' AND ma.semester_id = ?
          AND EXISTS (
              SELECT 1 FROM subscriptions sub
              WHERE sub.user_id = u.id AND sub.status = 'active' AND sub.expires_at > NOW()
          )
    ");
    $mentor_eligible->execute([$course_code, $mentor_id ?: 0, $active_semester['id'] ?? 0]);

    if (!$valid_booking_window || !$valid_time || !$valid_duration || !$mentor_id) {
        $error = 'Choose a valid date within the next 14 days, start time, and session duration.';
    } elseif (!$active_semester || !$mentor_eligible->fetchColumn()) {
        $error = 'This mentor is not currently available for booking.';
    } elseif ($datetime < date('Y-m-d H:i:s')) {
        $error = 'Session date/time cannot be in the past.';
    } elseif (!isMentorAvailable($mentor_id, $datetime, $duration_minutes)) {
        $error = 'Mentor is not available at that time. Please choose another time.';
    } else {
        // Get mentor rate
        $rate = getMentorRate($mentor_id, $course_code);
        if ($rate <= 0) {
            $error = 'Mentor has not set a rate for this course.';
        } else {
            // Calculate payment
            $payment = calculatePayment($rate, $duration_minutes);
            
            // Create session
            $stmt = $pdo->prepare("INSERT INTO sessions 
                                   (mentor_id, mentee_id, semester_id, course_code, session_date, 
                                    duration_minutes, mentor_rate, total_amount, admin_fee, mentor_payout, 
                                    status, payment_status)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending')
                                   RETURNING id");
            $success = $stmt->execute([
                $mentor_id,
                $user['id'],
                $active_semester['id'],
                $course_code,
                $datetime,
                $duration_minutes,
                $rate,
                $payment['total'],
                $payment['admin_fee'],
                $payment['mentor_payout']
            ]);
            
            if ($success) {
                $session_id = $stmt->fetchColumn();
                logActivity($user['id'], 'Booked session #' . $session_id . ' with mentor #' . $mentor_id);
                
                // Redirect to payment
                header("Location: payment.php?session_id=$session_id");
                exit();
            } else {
                $error = 'Booking failed. Please try again.';
            }
        }
    }
}

$mentor_availability = [];
$booked_sessions = [];
$availability_by_date = [];
if ($mentor) {
    $stmt = $pdo->prepare("
        SELECT day_of_week, start_time, end_time
        FROM mentor_availability
        WHERE mentor_id = ? AND is_recurring = 1
        ORDER BY day_of_week, start_time
    ");
    $stmt->execute([$mentor['id']]);
    $weekly_slots = $stmt->fetchAll();

    foreach ($weekly_slots as $slot) {
        $mentor_availability[$slot['day_of_week']][] = [
            'start' => substr($slot['start_time'], 0, 5),
            'end' => substr($slot['end_time'], 0, 5),
        ];
    }

    $stmt = $pdo->prepare("
        SELECT session_date, duration_minutes
        FROM sessions
        WHERE mentor_id = ?
          AND status IN ('pending', 'confirmed')
          AND session_date < CURRENT_DATE + INTERVAL '15 days'
          AND session_date + duration_minutes * INTERVAL '1 minute' > NOW()
        ORDER BY session_date
    ");
    $stmt->execute([$mentor['id']]);
    foreach ($stmt->fetchAll() as $session) {
        $booked_sessions[] = [
            'start' => date('Y-m-d H:i', strtotime($session['session_date'])),
            'duration' => (int)$session['duration_minutes'],
        ];
    }
}

$available_dates = [];
for ($i = 0; $i <= 14; $i++) {
    $date = date('Y-m-d', strtotime("+$i days"));
    $available_dates[] = $date;
    $day_of_week = (int)date('w', strtotime($date));
    $availability_by_date[$date] = $mentor_availability[$day_of_week] ?? [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Session - MentorMatch</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/mobile.css" rel="stylesheet">
    <style>
        .booking-container {
            max-width: 900px;
            margin: 0 auto;
        }
        .mentor-profile-card {
            border-radius: 16px;
            border: none;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            overflow: hidden;
        }
        .mentor-profile-card .card-header {
            background: linear-gradient(135deg, #0d6efd, #0a58ca);
            color: white;
            padding: 20px;
            border: none;
        }
        .mentor-avatar-lg {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 700;
            color: white;
            flex-shrink: 0;
            border: 3px solid rgba(255,255,255,0.3);
        }
        .mentor-avatar-lg.bg-1 { background: linear-gradient(135deg, #0d6efd, #0a58ca); }
        .mentor-avatar-lg.bg-2 { background: linear-gradient(135deg, #28a745, #1e7e34); }
        .mentor-avatar-lg.bg-3 { background: linear-gradient(135deg, #dc3545, #b02a37); }
        .mentor-avatar-lg.bg-4 { background: linear-gradient(135deg, #ffc107, #d39e00); }
        .mentor-avatar-lg.bg-5 { background: linear-gradient(135deg, #17a2b8, #0d6efd); }
        .mentor-avatar-lg.bg-6 { background: linear-gradient(135deg, #6f42c1, #4a2a8a); }
        .rating-stars {
            color: #ffc107;
        }
        .booking-card {
            border-radius: 16px;
            border: none;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        }
        .booking-card .card-header {
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            padding: 16px 20px;
        }
        .course-badge {
            display: inline-block;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.8rem;
            background: #e7f1ff;
            color: #0d6efd;
            margin: 3px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .course-badge:hover {
            background: #0d6efd;
            color: white;
        }
        .course-badge.active {
            background: #0d6efd;
            color: white;
        }
        .stat-badge {
            text-align: center;
            padding: 6px 10px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .stat-badge .number {
            font-size: 1rem;
            font-weight: 700;
        }
        .stat-badge .label {
            font-size: 0.65rem;
            color: #6c757d;
        }
        .time-slot {
            padding: 8px 12px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            font-size: 0.9rem;
            color: inherit;
            background: #fff;
            font-family: inherit;
        }
        .time-slot:hover {
            border-color: #0d6efd;
            background: #f8f9fa;
        }
        .time-slot.selected {
            border-color: #0d6efd;
            background: #e7f1ff;
        }
        .time-slot.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .date-selector {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            padding: 8px 0;
            -webkit-overflow-scrolling: touch;
        }
        .date-selector .date-item {
            flex: 0 0 auto;
            min-width: 70px;
            padding: 10px 8px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            color: inherit;
            background: #fff;
            font-family: inherit;
        }

        .date-selector .date-item:disabled {
            cursor: not-allowed;
        }
        .date-selector .date-item:hover {
            border-color: #0d6efd;
            background: #f8f9fa;
        }
        .date-selector .date-item.selected {
            border-color: #0d6efd;
            background: #e7f1ff;
        }
        .date-selector .date-item .day {
            font-size: 0.7rem;
            color: #6c757d;
            text-transform: uppercase;
        }
        .date-selector .date-item .date-num {
            font-size: 1.2rem;
            font-weight: 700;
        }
        .date-selector .date-item .month {
            font-size: 0.7rem;
            color: #6c757d;
        }
        .payment-summary {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 16px;
        }
        .payment-summary .row {
            margin-bottom: 6px;
        }
        .payment-summary .label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        .payment-summary .value {
            font-weight: 600;
            text-align: right;
        }
        .payment-summary .total {
            border-top: 2px solid #dee2e6;
            padding-top: 10px;
            margin-top: 6px;
        }
        .payment-summary .total .label {
            font-weight: 700;
            color: #212529;
        }
        .payment-summary .total .value {
            font-size: 1.2rem;
            color: #0d6efd;
        }
        @media (max-width: 768px) {
            .mentor-avatar-lg {
                width: 60px;
                height: 60px;
                font-size: 1.5rem;
            }
            .date-selector .date-item {
                min-width: 60px;
                padding: 8px 6px;
            }
            .date-selector .date-item .date-num {
                font-size: 1rem;
            }
            .time-slot {
                padding: 6px 10px;
                font-size: 0.8rem;
            }
            .booking-card .card-header {
                padding: 12px 16px;
                font-size: 0.9rem;
            }
            .payment-summary {
                padding: 12px;
            }
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
                <div class="booking-container">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h4><i class="bi bi-calendar-plus"></i> Book a Session</h4>
                            <p class="text-muted">Book as many sessions as you need. Choose a time inside your mentor's weekly availability.</p>
                        </div>
                        <a href="search.php" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-arrow-left"></i> Back to Search
                        </a>
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($mentor): ?>
                        <div class="row g-3">
                            <!-- Mentor Profile Column -->
                            <div class="col-lg-5">
                                <div class="card mentor-profile-card">
                                    <div class="card-header">
                                        <div class="d-flex align-items-center">
                                            <?php 
                                                $avatar_bg = 'bg-1';
                                                $bg_classes = ['bg-1', 'bg-2', 'bg-3', 'bg-4', 'bg-5', 'bg-6'];
                                                $avatar_bg = $bg_classes[($mentor['id'] - 1) % count($bg_classes)];
                                            ?>
                                            <div class="mentor-avatar-lg <?php echo $avatar_bg; ?> me-3">
                                                <?php echo strtoupper(substr($mentor['name'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <h5 class="mb-0 text-white"><?php echo htmlspecialchars($mentor['name']); ?></h5>
                                                <small class="text-white-50">
                                                    <i class="bi bi-book"></i> <?php echo htmlspecialchars($mentor['programme'] ?? 'Student'); ?>
                                                    <?php if ($mentor['year_of_study']): ?>
                                                        • Year <?php echo $mentor['year_of_study']; ?>
                                                    <?php endif; ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <!-- Rating -->
                                        <div class="d-flex align-items-center gap-2 mb-3">
                                            <?php if ($mentor['avg_rating']): ?>
                                                <div class="rating-stars">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="bi bi-star-fill <?php echo $i <= round($mentor['avg_rating']) ? 'text-warning' : 'text-muted opacity-25'; ?>"></i>
                                                    <?php endfor; ?>
                                                </div>
                                                <span class="fw-bold"><?php echo number_format($mentor['avg_rating'], 1); ?></span>
                                                <small class="text-muted">(<?php echo $mentor['total_reviews']; ?> reviews)</small>
                                            <?php else: ?>
                                                <span class="text-muted">No reviews yet</span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Stats -->
                                        <div class="row g-2 mb-3">
                                            <div class="col-4">
                                                <div class="stat-badge">
                                                    <div class="number"><?php echo $mentor['total_sessions']; ?></div>
                                                    <div class="label">Sessions</div>
                                                </div>
                                            </div>
                                            <div class="col-4">
                                                <div class="stat-badge">
                                                    <div class="number"><?php echo $mentor['total_reviews']; ?></div>
                                                    <div class="label">Reviews</div>
                                                </div>
                                            </div>
                                            <div class="col-4">
                                                <div class="stat-badge">
                                                    <div class="number text-primary"><?php echo formatCurrency($mentor['rate_per_hour']); ?></div>
                                                    <div class="label">/hour</div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Courses Offered -->
                                        <?php if (empty($mentor_availability)): ?>
                                            <div class="alert alert-info py-2 small">
                                                This mentor has not posted weekly availability yet. Please check back later.
                                            </div>
                                        <?php endif; ?>
                                        <div class="mb-3">
                                            <label class="text-muted small fw-bold">Courses Offered</label>
                                            <div class="d-flex flex-wrap">
                                                <?php foreach ($mentor_courses as $mc): ?>
                                                    <span class="course-badge <?php echo $mc['course_code'] === $course_code ? 'active' : ''; ?>" 
                                                          onclick="selectCourse('<?php echo $mc['course_code']; ?>')">
                                                        <?php echo htmlspecialchars($mc['course_code']); ?>
                                                        <?php if ($mc['grade']): ?>
                                                            <span class="badge bg-success text-white ms-1" style="font-size: 0.6rem;">
                                                                <?php echo $mc['grade']; ?>
                                                            </span>
                                                        <?php endif; ?>
                                                        <span class="badge bg-light text-dark ms-1" style="font-size: 0.6rem;">
                                                            <?php echo formatCurrency($mc['rate_per_hour']); ?>/hr
                                                        </span>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        
                                        <!-- Contact Info -->
                                        <div class="border-top pt-3">
                                            <div class="d-flex gap-3">
                                                <a href="mailto:<?php echo htmlspecialchars($mentor['email']); ?>" class="text-decoration-none text-muted small">
                                                    <i class="bi bi-envelope"></i> Email
                                                </a>
                                                <a href="tel:<?php echo htmlspecialchars($mentor['phone']); ?>" class="text-decoration-none text-muted small">
                                                    <i class="bi bi-phone"></i> Call
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Booking Form Column -->
                            <div class="col-lg-7">
                                <div class="card booking-card">
                                    <div class="card-header">
                                        <h6 class="mb-0"><i class="bi bi-calendar"></i> Schedule Your Session</h6>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST" action="" id="bookingForm">
                                            <input type="hidden" name="mentor_id" value="<?php echo $mentor['id']; ?>">
                                            <input type="hidden" name="course_code" id="selected_course" value="<?php echo htmlspecialchars($course_code); ?>">
                                            <input type="hidden" name="session_date" id="selected_date" value="">
                                            <input type="hidden" name="session_time" id="selected_time" value="">
                                            <input type="hidden" name="duration_minutes" id="selected_duration" value="60">
                                            
                                            <!-- Step 1: Select Course -->
                                            <div class="mb-3">
                                                <label class="form-label fw-bold">1. Select Course <span class="text-danger">*</span></label>
                                                <div class="d-flex flex-wrap gap-1">
                                                    <?php foreach ($mentor_courses as $mc): ?>
                                                        <span class="course-badge <?php echo $mc['course_code'] === $course_code ? 'active' : ''; ?>" 
                                                              onclick="selectCourse('<?php echo $mc['course_code']; ?>')">
                                                            <?php echo htmlspecialchars($mc['course_code']); ?>
                                                            <span class="badge bg-success text-white ms-1" style="font-size: 0.6rem;">
                                                                <?php echo $mc['grade']; ?>
                                                            </span>
                                                        </span>
                                                    <?php endforeach; ?>
                                                </div>
                                                <?php if (empty($course_code)): ?>
                                                    <small class="text-muted">Please select a course to continue</small>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <!-- Step 2: Select Date -->
                                            <div class="mb-3">
                                                <label class="form-label fw-bold">2. Select Date <span class="text-danger">*</span></label>
                                                <div class="date-selector" id="dateSelector">
                                                    <?php foreach ($available_dates as $date): 
                                                        $day_name = date('D', strtotime($date));
                                                        $day_num = date('d', strtotime($date));
                                                        $month = date('M', strtotime($date));
                                                        $date_slots = $availability_by_date[$date] ?? [];
                                                    ?>
                                                        <button type="button" class="date-item <?php echo $date_slots ? '' : 'opacity-50'; ?>"
                                                             <?php if ($date_slots): ?>onclick="selectDate('<?php echo $date; ?>')"<?php else: ?>disabled<?php endif; ?>
                                                             data-date="<?php echo $date; ?>">
                                                            <div class="day"><?php echo $day_name; ?></div>
                                                            <div class="date-num"><?php echo $day_num; ?></div>
                                                            <div class="month"><?php echo $month; ?></div>
                                                            <div class="small"><?php echo $date_slots ? count($date_slots) . ' range' . (count($date_slots) === 1 ? '' : 's') : 'No hours'; ?></div>
                                                        </button>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                            
                                            <!-- Step 3: Select Time -->
                                            <div class="mb-3">
                                                <label class="form-label fw-bold">3. Select Time <span class="text-danger">*</span></label>
                                                <div class="row g-2" id="timeSelector">
                                                    <div class="col-12 text-muted small" id="timeHelp">Select a date to see open start times.</div>
                                                </div>
                                                <div class="small text-primary mt-2" id="selectedTimeRange" aria-live="polite"></div>
                                            </div>
                                            
                                            <!-- Step 4: Duration -->
                                            <div class="mb-3">
                                                <label class="form-label fw-bold">4. Duration</label>
                                                <div class="d-flex gap-2">
                                                    <?php foreach ($duration_options as $dur): ?>
                                                        <button type="button" class="time-slot duration-slot <?php echo $dur === 60 ? 'selected' : ''; ?>"
                                                             data-duration="<?php echo $dur; ?>"
                                                             onclick="selectDuration(<?php echo $dur; ?>)"
                                                             style="min-width: 60px;">
                                                            <?php echo $dur; ?> min
                                                        </button>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                            
                                            <!-- Payment Summary -->
                                            <div class="payment-summary mb-3">
                                                <h6 class="mb-2"><i class="bi bi-calculator"></i> Payment Summary</h6>
                                                <div class="row">
                                                    <div class="col-8 label">Mentor Rate</div>
                                                    <div class="col-4 value" id="display_rate"><?php echo formatCurrency($mentor['rate_per_hour']); ?>/hr</div>
                                                </div>
                                                <div class="row">
                                                    <div class="col-8 label">Duration</div>
                                                    <div class="col-4 value" id="display_duration">60 min</div>
                                                </div>
                                                <div class="row">
                                                    <div class="col-8 label">Service Fee (5%)</div>
                                                    <div class="col-4 value text-muted" id="display_fee">K0.00</div>
                                                </div>
                                                <div class="row total">
                                                    <div class="col-8 label">Total Amount</div>
                                                    <div class="col-4 value" id="display_total"><?php echo formatCurrency($mentor['rate_per_hour']); ?></div>
                                                </div>
                                            </div>
                                            
                                            <button type="submit" name="book" class="btn btn-success w-100">
                                                <i class="bi bi-credit-card"></i> Proceed to Payment
                                            </button>
                                            <a href="search.php" class="btn btn-secondary w-100 mt-2">
                                                <i class="bi bi-arrow-left"></i> Cancel
                                            </a>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="bi bi-person-x display-1 text-muted"></i>
                            <h4 class="mt-3">Mentor Not Found</h4>
                            <p class="text-muted">The mentor you're looking for is not available.</p>
                            <a href="search.php" class="btn btn-primary">
                                <i class="bi bi-search"></i> Find Another Mentor
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        const availabilityByDate = <?php echo json_encode($availability_by_date, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
        const bookedSessions = <?php echo json_encode($booked_sessions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;

        // ============================================
        // SELECT COURSE
        // ============================================
        function selectCourse(course) {
            document.getElementById('selected_course').value = course;
            
            // Update UI
            document.querySelectorAll('.course-badge').forEach(function(badge) {
                badge.classList.remove('active');
                if (badge.textContent.trim().startsWith(course)) {
                    badge.classList.add('active');
                }
            });
            
            // Update rate display
            const rate = <?php echo json_encode($mentor_courses); ?>;
            const selected = rate.find(r => r.course_code === course);
            if (selected) {
                document.getElementById('display_rate').textContent = formatCurrency(selected.rate_per_hour) + '/hr';
                calculateTotal();
            }
        }
        
        // ============================================
        // SELECT DATE
        // ============================================
        function selectDate(date) {
            if (!availabilityByDate[date] || availabilityByDate[date].length === 0) {
                return;
            }
            document.getElementById('selected_date').value = date;
            document.getElementById('selected_time').value = '';
            
            // Update UI
            document.querySelectorAll('.date-item').forEach(function(item) {
                item.classList.remove('selected');
                if (item.dataset.date === date) {
                    item.classList.add('selected');
                }
            });
            renderTimeSlots(date);
        }
        
        // ============================================
        // SELECT TIME
        // ============================================
        function selectTime(time) {
            document.getElementById('selected_time').value = time;
            
            // Update UI
            document.querySelectorAll('#timeSelector .time-slot').forEach(function(slot) {
                slot.classList.remove('selected');
                if (slot.dataset.time === time) {
                    slot.classList.add('selected');
                }
            });
            const duration = parseInt(document.getElementById('selected_duration').value, 10) || 60;
            const [hours, minutes] = time.split(':').map(Number);
            const end = new Date(2000, 0, 1, hours, minutes + duration);
            const date = document.getElementById('selected_date').value;
            const startLabel = new Date(2000, 0, 1, hours, minutes).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
            const endLabel = end.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
            document.getElementById('selectedTimeRange').textContent = date
                ? 'Selected range: ' + startLabel + '–' + endLabel
                : '';
        }
        
        // ============================================
        // SELECT DURATION
        // ============================================
        function selectDuration(duration) {
            document.getElementById('selected_duration').value = duration;
            document.getElementById('display_duration').textContent = duration + ' min';
            
            document.querySelectorAll('.duration-slot').forEach(function(slot) {
                slot.classList.toggle('selected', parseInt(slot.dataset.duration, 10) === duration);
            });
            const selectedDate = document.getElementById('selected_date').value;
            if (selectedDate) {
                renderTimeSlots(selectedDate);
            }
            // Update payment summary
            calculateTotal();
        }

        function renderTimeSlots(date) {
            const container = document.getElementById('timeSelector');
            const duration = parseInt(document.getElementById('selected_duration').value, 10) || 60;
            const daySlots = availabilityByDate[date] || [];
            const startOptions = new Set();
            const now = new Date();

            daySlots.forEach(function (range) {
                const [startHour, startMinute] = range.start.split(':').map(Number);
                const [endHour, endMinute] = range.end.split(':').map(Number);
                const rangeStart = startHour * 60 + startMinute;
                const rangeEnd = endHour * 60 + endMinute;

                for (let minute = rangeStart; minute + duration <= rangeEnd; minute += 30) {
                    const time = String(Math.floor(minute / 60)).padStart(2, '0') + ':' + String(minute % 60).padStart(2, '0');
                    const start = new Date(date + 'T' + time + ':00');
                    const end = new Date(start.getTime() + duration * 60000);
                    if (start <= now) continue;

                    const overlapsBooked = bookedSessions.some(function (session) {
                        const bookedStart = new Date(session.start.replace(' ', 'T') + ':00');
                        const bookedEnd = new Date(bookedStart.getTime() + session.duration * 60000);
                        return start < bookedEnd && end > bookedStart;
                    });
                    if (!overlapsBooked) startOptions.add(time);
                }
            });

            container.replaceChildren();
            document.getElementById('selected_time').value = '';
            document.getElementById('selectedTimeRange').textContent = '';
            const times = Array.from(startOptions).sort();
            if (times.length === 0) {
                const empty = document.createElement('div');
                empty.className = 'col-12 text-muted small';
                empty.textContent = 'No open times fit this duration on that date.';
                container.appendChild(empty);
                return;
            }

            times.forEach(function (time) {
                const column = document.createElement('div');
                column.className = 'col-6 col-md-4';
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'time-slot w-100';
                button.dataset.time = time;
                const [hours, minutes] = time.split(':').map(Number);
                button.textContent = new Date(2000, 0, 1, hours, minutes).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
                button.addEventListener('click', function () {
                    selectTime(time);
                });
                column.appendChild(button);
                container.appendChild(column);
            });
        }
        
        // ============================================
        // CALCULATE TOTAL
        // ============================================
        function calculateTotal() {
            const duration = parseInt(document.getElementById('selected_duration').value) || 60;
            const rateText = document.getElementById('display_rate').textContent;
            const rate = parseFloat(rateText.replace(/[^0-9.]/g, '')) || 0;
            
            const total = (rate * duration) / 60;
            const fee = total * 0.05;
            
            document.getElementById('display_total').textContent = formatCurrency(total);
            document.getElementById('display_fee').textContent = formatCurrency(fee);
        }
        
        // ============================================
        // FORMAT CURRENCY
        // ============================================
        function formatCurrency(amount) {
            return 'K' + parseFloat(amount).toFixed(2);
        }
        
        // ============================================
        // AUTO SELECT FIRST AVAILABLE
        // ============================================
        document.addEventListener('DOMContentLoaded', function() {
            const firstDate = document.querySelector('.date-item:not(:disabled)');
            if (firstDate) {
                firstDate.click();
            }
            calculateTotal();
        });
    </script>
</body>
</html>