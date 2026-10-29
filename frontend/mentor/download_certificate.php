<?php
// mentor/download_certificate.php
require_once dirname(__DIR__, 2) . '/backend/includes/functions.php';
requireMentor();

// Include FPDF
require_once dirname(__DIR__, 2) . '/vendor/fpdf.php';

$user = getCurrentUser();

if (!isset($_GET['id'])) {
    header('Location: certificate.php');
    exit();
}

$certificate_id = (int)$_GET['id'];

// Get certificate details
$stmt = $pdo->prepare("
    SELECT c.*, u.name as mentor_name, u.email as mentor_email,
           s.semester_name, s.start_date, s.end_date
    FROM certificates c
    JOIN users u ON c.mentor_id = u.id
    JOIN semesters s ON c.semester_id = s.id
    WHERE c.id = ? AND c.mentor_id = ?
");
$stmt->execute([$certificate_id, $user['id']]);
$cert = $stmt->fetch();

if (!$cert) {
    die('Certificate not found or unauthorized.');
}

// Create PDF
class PDF extends FPDF
{
    function Header()
    {
        // No header for certificate
    }
    
    function Footer()
    {
        $this->SetY(-25);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(128, 128, 128);
        $this->Cell(0, 10, 'Certificate ID: ' . $this->cert_hash, 0, 0, 'L');
        $this->Cell(0, 10, 'Page ' . $this->PageNo(), 0, 0, 'R');
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 7);
        $this->Cell(0, 10, 'This certificate is issued by MentorMatch. Verify at: ' . BASE_URL . 'verify.php', 0, 0, 'C');
    }
}

$pdf = new PDF('L', 'mm', 'A4'); // Landscape
$pdf->cert_hash = substr($cert['certificate_hash'], 0, 12);
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 30);

// ============================================
// CERTIFICATE DESIGN
// ============================================

// Outer border
$pdf->SetDrawColor(0, 51, 102);
$pdf->SetLineWidth(2);
$pdf->Rect(15, 15, 267, 180);

// Inner border
$pdf->SetDrawColor(200, 200, 200);
$pdf->SetLineWidth(0.5);
$pdf->Rect(20, 20, 257, 170);

// Decorative lines
$pdf->SetDrawColor(0, 51, 102);
$pdf->SetLineWidth(1);
$pdf->Line(25, 55, 272, 55);
$pdf->Line(25, 165, 272, 165);

// ============================================
// CERTIFICATE CONTENT
// ============================================

// Title
$pdf->SetY(38);
$pdf->SetFont('Arial', 'B', 26);
$pdf->SetTextColor(0, 51, 102);
$pdf->Cell(0, 20, 'CERTIFICATE OF ACHIEVEMENT', 0, 1, 'C');

// Subtitle
$pdf->SetFont('Arial', 'I', 12);
$pdf->SetTextColor(128, 128, 128);
$pdf->Cell(0, 10, 'Presented to', 0, 1, 'C');

// Mentor Name
$pdf->SetY(75);
$pdf->SetFont('Arial', 'B', 28);
$pdf->SetTextColor(0, 51, 102);
$pdf->Cell(0, 15, strtoupper($cert['mentor_name']), 0, 1, 'C');

// Description
$pdf->SetY(105);
$pdf->SetFont('Arial', '', 12);
$pdf->SetTextColor(0, 0, 0);
$pdf->MultiCell(0, 8, "In recognition of outstanding dedication and contribution as a Peer Mentor during the {$cert['semester_name']} semester. Successfully completed {$cert['total_sessions']} mentoring sessions totaling " . number_format($cert['total_hours'], 1) . " hours of academic support.", 0, 'C');

// Stats in boxes
$pdf->SetY(145);
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetTextColor(0, 0, 0);

// Box 1: Hours
$pdf->SetFillColor(240, 245, 255);
$pdf->SetDrawColor(0, 51, 102);
$pdf->SetLineWidth(0.5);
$pdf->Rect(75, 145, 55, 25, 'DF');
$pdf->SetXY(80, 150);
$pdf->Cell(45, 10, number_format($cert['total_hours'], 1) . ' hrs', 0, 0, 'C');

// Box 2: Sessions
$pdf->SetFillColor(240, 245, 255);
$pdf->Rect(155, 145, 55, 25, 'DF');
$pdf->SetXY(160, 150);
$pdf->Cell(45, 10, $cert['total_sessions'] . ' sessions', 0, 0, 'C');

// Semester
$pdf->SetY(180);
$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(128, 128, 128);
$pdf->Cell(0, 10, 'Semester: ' . date('d M Y', strtotime($cert['start_date'])) . ' - ' . date('d M Y', strtotime($cert['end_date'])), 0, 1, 'C');

// Issue date
$pdf->SetY(190);
$pdf->SetFont('Arial', 'I', 10);
$pdf->Cell(0, 10, 'Issued on: ' . date('d F Y', strtotime($cert['issue_date'])), 0, 1, 'C');

// Signature lines
$pdf->SetY(200);
$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(0, 0, 0);

// Left signature
$pdf->Cell(90, 10, '_________________________', 0, 0, 'C');
$pdf->Cell(10, 10, '', 0, 0, 'C');
// Right signature
$pdf->Cell(90, 10, '_________________________', 0, 0, 'C');

$pdf->SetY(208);
$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(128, 128, 128);
$pdf->Cell(90, 10, 'Program Coordinator', 0, 0, 'C');
$pdf->Cell(10, 10, '', 0, 0, 'C');
$pdf->Cell(90, 10, 'Head of Department', 0, 0, 'C');

// ============================================
// OUTPUT
// ============================================

// Check if print mode
if (isset($_GET['print'])) {
    // Output for print - inline display
    $pdf->Output('I', 'Certificate_' . $cert['mentor_name'] . '.pdf');
} else {
    // Download
    $pdf->Output('D', 'Certificate_' . $cert['mentor_name'] . '.pdf');
}
exit();
?>