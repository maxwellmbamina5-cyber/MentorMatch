<?php
// includes/email.php
// Using PHP's built-in mail function - No PHPMailer or Composer required

// Get base URL for links
$base_url = (isset($_SERVER['HTTPS']) ? "https://" : "http://") . $_SERVER['HTTP_HOST'] . '/mentormatch/';

// Send email using PHP's mail function
function sendEmailNotification($to, $subject, $message, $html = false) {
    // Headers
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: MentorMatch <noreply@mentormatch.com>\r\n";
    $headers .= "Reply-To: noreply@mentormatch.com\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    // Build message
    $email_message = $html ? $message : nl2br($message);
    
    // Send email
    return mail($to, $subject, $email_message, $headers);
}

// ============================================
// EMAIL TEMPLATES
// ============================================

// Welcome Email
function sendWelcomeEmail($user_name, $user_email, $role) {
    global $base_url;
    
    $subject = "Welcome to MentorMatch!";
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #0d6efd; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
            .content { padding: 20px; background: #f8f9fa; border: 1px solid #dee2e6; border-top: none; border-radius: 0 0 5px 5px; }
            .btn { display: inline-block; padding: 10px 20px; background: #0d6efd; color: white; text-decoration: none; border-radius: 5px; }
            .footer { text-align: center; font-size: 12px; color: #6c757d; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>Welcome to MentorMatch!</h2>
            </div>
            <div class='content'>
                <h3>Hello " . htmlspecialchars($user_name) . ",</h3>
                <p>Thank you for joining MentorMatch as a <strong>" . ucfirst($role) . "</strong>!</p>
                <p>Your registration has been received and is <strong>pending approval</strong> by our administrator.</p>
                <p>You will receive another email once your account has been approved.</p>
                <br>
                <p>In the meantime, you can:</p>
                <ul>
                    <li>Learn more about how MentorMatch works</li>
                    <li>Prepare for your mentorship journey</li>
                </ul>
                <br>
                <a href='" . $base_url . "' class='btn'>Visit MentorMatch</a>
                <br><br>
                <p>Regards,<br><strong>MentorMatch Team</strong></p>
            </div>
            <div class='footer'>
                &copy; " . date('Y') . " MentorMatch. All rights reserved.
            </div>
        </div>
    </body>
    </html>
    ";
    return sendEmailNotification($user_email, $subject, $message, true);
}

// Approval Email
function sendApprovalEmail($user_name, $user_email, $role) {
    global $base_url;
    
    $subject = "Your MentorMatch Application is Approved!";
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #28a745; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
            .content { padding: 20px; background: #f8f9fa; border: 1px solid #dee2e6; border-top: none; border-radius: 0 0 5px 5px; }
            .btn { display: inline-block; padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 5px; }
            .footer { text-align: center; font-size: 12px; color: #6c757d; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>✅ Application Approved!</h2>
            </div>
            <div class='content'>
                <h3>Congratulations, " . htmlspecialchars($user_name) . "!</h3>
                <p>Your application to become a <strong>" . ucfirst($role) . "</strong> has been approved!</p>
                <p>You can now login to your account and start using the platform.</p>
                <br>
                <a href='" . $base_url . "login.php' class='btn'>Login Now</a>
                <br><br>
                <p>Regards,<br><strong>MentorMatch Team</strong></p>
            </div>
            <div class='footer'>
                &copy; " . date('Y') . " MentorMatch. All rights reserved.
            </div>
        </div>
    </body>
    </html>
    ";
    return sendEmailNotification($user_email, $subject, $message, true);
}

// Rejection Email
function sendRejectionEmail($user_name, $user_email, $reason = '') {
    $subject = "Your MentorMatch Application Status";
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #dc3545; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
            .content { padding: 20px; background: #f8f9fa; border: 1px solid #dee2e6; border-top: none; border-radius: 0 0 5px 5px; }
            .footer { text-align: center; font-size: 12px; color: #6c757d; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>Application Status Update</h2>
            </div>
            <div class='content'>
                <h3>Hello " . htmlspecialchars($user_name) . ",</h3>
                <p>We regret to inform you that your application to join MentorMatch has been <strong>rejected</strong>.</p>
                " . ($reason ? "<p><strong>Reason:</strong> " . htmlspecialchars($reason) . "</p>" : "") . "
                <p>If you have any questions, please contact the administrator.</p>
                <br>
                <p>Regards,<br><strong>MentorMatch Team</strong></p>
            </div>
            <div class='footer'>
                &copy; " . date('Y') . " MentorMatch. All rights reserved.
            </div>
        </div>
    </body>
    </html>
    ";
    return sendEmailNotification($user_email, $subject, $message, true);
}

// Session Confirmation Email
function sendSessionConfirmationEmail($user_name, $user_email, $session_details, $role) {
    global $base_url;
    
    $subject = "Session Confirmed - MentorMatch";
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #0d6efd; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
            .content { padding: 20px; background: #f8f9fa; border: 1px solid #dee2e6; border-top: none; border-radius: 0 0 5px 5px; }
            .btn { display: inline-block; padding: 10px 20px; background: #0d6efd; color: white; text-decoration: none; border-radius: 5px; }
            .footer { text-align: center; font-size: 12px; color: #6c757d; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>📅 Session Confirmed!</h2>
            </div>
            <div class='content'>
                <h3>Hello " . htmlspecialchars($user_name) . ",</h3>
                <p>Your session has been confirmed!</p>
                <table border='1' cellpadding='5' style='width:100%; border-collapse:collapse;'>
                    <tr><td><strong>Course:</strong></td><td>" . htmlspecialchars($session_details['course_code'] ?? 'N/A') . "</td></tr>
                    <tr><td><strong>Date:</strong></td><td>" . htmlspecialchars($session_details['session_date'] ?? 'N/A') . "</td></tr>
                    <tr><td><strong>Time:</strong></td><td>" . htmlspecialchars($session_details['session_time'] ?? 'N/A') . "</td></tr>
                    " . ($role === 'mentor' ? "<tr><td><strong>Mentee:</strong></td><td>" . htmlspecialchars($session_details['mentee_name'] ?? 'N/A') . "</td></tr>" : "") . "
                    " . ($role === 'mentee' ? "<tr><td><strong>Mentor:</strong></td><td>" . htmlspecialchars($session_details['mentor_name'] ?? 'N/A') . "</td></tr>" : "") . "
                    <tr><td><strong>Duration:</strong></td><td>" . htmlspecialchars($session_details['duration'] ?? 60) . " minutes</td></tr>
                </table>
                <br>
                <a href='" . $base_url . $role . "/session_details.php?id=" . htmlspecialchars($session_details['session_id'] ?? '') . "' class='btn'>View Session Details</a>
                <br><br>
                <p>Regards,<br><strong>MentorMatch Team</strong></p>
            </div>
            <div class='footer'>
                &copy; " . date('Y') . " MentorMatch. All rights reserved.
            </div>
        </div>
    </body>
    </html>
    ";
    return sendEmailNotification($user_email, $subject, $message, true);
}

// Payment Receipt Email
function sendPaymentReceiptEmail($user_name, $user_email, $payment_details) {
    $subject = "Payment Receipt - MentorMatch";
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #28a745; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
            .content { padding: 20px; background: #f8f9fa; border: 1px solid #dee2e6; border-top: none; border-radius: 0 0 5px 5px; }
            .footer { text-align: center; font-size: 12px; color: #6c757d; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>💰 Payment Receipt</h2>
            </div>
            <div class='content'>
                <h3>Dear " . htmlspecialchars($user_name) . ",</h3>
                <p>Your payment has been received successfully.</p>
                <table border='1' cellpadding='5' style='width:100%; border-collapse:collapse;'>
                    <tr><td><strong>Transaction ID:</strong></td><td>" . htmlspecialchars($payment_details['transaction_id'] ?? 'N/A') . "</td></tr>
                    <tr><td><strong>Amount:</strong></td><td>" . htmlspecialchars($payment_details['amount'] ?? '0.00') . "</td></tr>
                    <tr><td><strong>Course:</strong></td><td>" . htmlspecialchars($payment_details['course_code'] ?? 'N/A') . "</td></tr>
                    <tr><td><strong>Date:</strong></td><td>" . htmlspecialchars($payment_details['payment_date'] ?? date('Y-m-d H:i:s')) . "</td></tr>
                </table>
                <br>
                <p>Regards,<br><strong>MentorMatch Team</strong></p>
            </div>
            <div class='footer'>
                &copy; " . date('Y') . " MentorMatch. All rights reserved.
            </div>
        </div>
    </body>
    </html>
    ";
    return sendEmailNotification($user_email, $subject, $message, true);
}

// Certificate Email
function sendCertificateEmail($user_name, $user_email, $certificate_id) {
    global $base_url;
    
    $subject = "Certificate Issued - MentorMatch";
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #ffc107; color: #212529; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
            .content { padding: 20px; background: #f8f9fa; border: 1px solid #dee2e6; border-top: none; border-radius: 0 0 5px 5px; }
            .btn { display: inline-block; padding: 10px 20px; background: #ffc107; color: #212529; text-decoration: none; border-radius: 5px; font-weight: bold; }
            .footer { text-align: center; font-size: 12px; color: #6c757d; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>🏆 Certificate Issued!</h2>
            </div>
            <div class='content'>
                <h3>Congratulations, " . htmlspecialchars($user_name) . "!</h3>
                <p>You have been awarded a Certificate of Achievement for your mentorship contributions.</p>
                <br>
                <a href='" . $base_url . "mentor/download_certificate.php?id=" . htmlspecialchars($certificate_id) . "' class='btn'>Download Your Certificate</a>
                <br><br>
                <p>Regards,<br><strong>MentorMatch Team</strong></p>
            </div>
            <div class='footer'>
                &copy; " . date('Y') . " MentorMatch. All rights reserved.
            </div>
        </div>
    </body>
    </html>
    ";
    return sendEmailNotification($user_email, $subject, $message, true);
}

// Password Reset Email (Optional)
function sendPasswordResetEmail($user_name, $user_email, $reset_link) {
    $subject = "Password Reset - MentorMatch";
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #0d6efd; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
            .content { padding: 20px; background: #f8f9fa; border: 1px solid #dee2e6; border-top: none; border-radius: 0 0 5px 5px; }
            .btn { display: inline-block; padding: 10px 20px; background: #0d6efd; color: white; text-decoration: none; border-radius: 5px; }
            .footer { text-align: center; font-size: 12px; color: #6c757d; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>🔑 Password Reset</h2>
            </div>
            <div class='content'>
                <h3>Hello " . htmlspecialchars($user_name) . ",</h3>
                <p>We received a request to reset your password for your MentorMatch account.</p>
                <p>Click the button below to reset your password:</p>
                <br>
                <a href='" . htmlspecialchars($reset_link) . "' class='btn'>Reset Password</a>
                <br><br>
                <p>If you did not request this, please ignore this email.</p>
                <p>Regards,<br><strong>MentorMatch Team</strong></p>
            </div>
            <div class='footer'>
                &copy; " . date('Y') . " MentorMatch. All rights reserved.
            </div>
        </div>
    </body>
    </html>
    ";
    return sendEmailNotification($user_email, $subject, $message, true);
}
?>