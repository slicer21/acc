<?php
require 'db.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$company_id = (int)$_GET['id'];

// Verify company exists
$stmt = $conn->prepare("SELECT * FROM companies WHERE id = ?");
$stmt->bind_param("i", $company_id);
$stmt->execute();
$company = $stmt->get_result()->fetch_assoc();

if ($company) {
    $_SESSION['current_company_id'] = $company['id'];
    $_SESSION['current_company_name'] = $company['name'];
    $_SESSION['success'] = "Switched to " . $company['name'];
} else {
    $_SESSION['error'] = "Company not found";
}

header("Location: dashboard.php");
exit;
?>