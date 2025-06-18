<?php
require 'db.php';

$date = $_POST['date'];
$name = $_POST['name'];
$category = $_POST['category'] ?? 'Revenue'; // Default to Revenue for tithes
$sub_category = $_POST['sub_category'] ?? 'Tithes'; // Default to Tithes
$amount = $_POST['amount'] ?: 0;
$notes = $_POST['notes'] ?? '';

$stmt = $conn->prepare("INSERT INTO journal_entries (date, name, category, sub_category, amount, notes) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssssds", $date, $name, $category, $sub_category, $amount, $notes);
$stmt->execute();

header("Location: dashboard.php");
?>