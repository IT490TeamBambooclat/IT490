<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: index.html");
    exit;
}
$role = $_POST['role'] ?? '';
if ($role !== 'jobseeker' && $role !== 'employer') {
    header("Location: role_select.php");
    exit;
}
$_SESSION['role'] = $role;
// redirect to appropriate dashboard
if ($role === 'jobseeker') {
    header("Location: jobseeker.php");
} else {
    header("Location: employer.php");
}
exit;
?>
