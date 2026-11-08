<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'hospital_booking');

$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Helper: Redirect based on role
function redirectByRole($role) {
    if ($role === 'admin')   header("Location: ../admin/dashboard.php");
    elseif ($role === 'doctor') header("Location: ../doctor/dashboard.php");
    else                        header("Location: ../patient/dashboard.php");
    exit();
}

// Helper: Check if logged in
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../auth/login.php");
        exit();
    }
}

// Helper: Sanitize input
function clean($conn, $data) {
    return mysqli_real_escape_string($conn, htmlspecialchars(trim($data)));
}
?>
