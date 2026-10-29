<?php
// admin/check_applications.php
require_once dirname(__DIR__) . '/includes/functions.php';
requireAdmin();

echo "<h1>Application Debugger</h1>";

// Check pending users
$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE status = 'pending'");
$pending_users = $stmt->fetchColumn();
echo "<p>Pending Users in users table: <strong>$pending_users</strong></p>";

// Check pending mentors
$stmt = $pdo->query("SELECT COUNT(*) as total FROM mentor_applications WHERE status = 'pending'");
$pending_mentors = $stmt->fetchColumn();
echo "<p>Pending Mentor Applications: <strong>$pending_mentors</strong></p>";

// Check pending mentees
$stmt = $pdo->query("SELECT COUNT(*) as total FROM mentee_enrollments WHERE status = 'pending'");
$pending_mentees = $stmt->fetchColumn();
echo "<p>Pending Mentee Enrollments: <strong>$pending_mentees</strong></p>";

// Check active semester
$stmt = $pdo->query("SELECT * FROM semesters WHERE status = 'active'");
$active = $stmt->fetch();
echo "<p>Active Semester: <strong>" . ($active ? htmlspecialchars($active['semester_name']) : 'NONE') . "</strong></p>";

// List all pending users with their applications
echo "<h2>Pending Users Details</h2>";
$stmt = $pdo->query("
    SELECT u.id, u.name, u.email, u.role, u.status,
           (SELECT COUNT(*) FROM mentor_applications WHERE mentor_id = u.id) as mentor_apps,
           (SELECT COUNT(*) FROM mentee_enrollments WHERE mentee_id = u.id) as mentee_apps
    FROM users u
    WHERE u.status = 'pending'
");
$users = $stmt->fetchAll();

if ($users) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Mentor Apps</th><th>Mentee Apps</th><th>Action</th></tr>";
    foreach ($users as $u) {
        echo "<tr>";
        echo "<td>{$u['id']}</td>";
        echo "<td>{$u['name']}</td>";
        echo "<td>{$u['email']}</td>";
        echo "<td>{$u['role']}</td>";
        echo "<td>{$u['mentor_apps']}</td>";
        echo "<td>{$u['mentee_apps']}</td>";
        echo "<td><a href='fix_user.php?id={$u['id']}' class='btn btn-sm btn-primary'>Fix</a></td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No pending users found.</p>";
}

// Fix button
echo "<hr>";
echo "<a href='fix_applications.php' class='btn btn-success'>Fix All Applications</a>";
echo " <a href='applications.php' class='btn btn-primary'>Go to Applications</a>";