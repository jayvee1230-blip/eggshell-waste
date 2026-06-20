<?php
// admin/ajax_approve_user.php — Super Admin AJAX Approve User Registration
require_once '../config.php';
require_once 'auth.php';

header('Content-Type: application/json');

// Session Role check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'super_admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

// CSRF Token validation
$headers = getallheaders();
$csrf_token = $_POST['csrf_token'] ?? $headers['X-CSRF-Token'] ?? '';
if (empty($csrf_token) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf_token)) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
    exit;
}

$user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$approved_role = trim($_POST['approved_role'] ?? '');

if ($user_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid user ID.']);
    exit;
}

// Whitelist mapping for role validation (prevents role injection)
$allowed_roles = [
    'criminology_student' => 'criminology_student',
    'faculty_researcher' => 'faculty_researcher',
    'alumni_police_partner' => 'alumni_police_partner',
    'super_admin' => 'super_admin'
];

if (empty($approved_role) || !isset($allowed_roles[$approved_role])) {
    echo json_encode(['success' => false, 'message' => 'Invalid role selected.']);
    exit;
}

// Use safe role from whitelist
$safe_role = $allowed_roles[$approved_role];

try {
    // Check if user exists and is pending
    $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ? AND status = 'pending' LIMIT 1");
    $stmt->execute([$user_id]);
    $email = $stmt->fetchColumn();

    if (!$email) {
        echo json_encode(['success' => false, 'message' => 'Pending user not found.']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE users SET status = 'active', role = ? WHERE id = ?");
    $stmt->execute([$safe_role, $user_id]);

    log_activity("Approve User", "Approved user registration for $email (assigned role: $safe_role)");

    echo json_encode([
        'success' => true,
        'message' => 'User account approved and role assigned successfully.',
        'data' => [
            'user_id' => $user_id,
            'role' => $safe_role,
            'email' => $email
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
exit;

