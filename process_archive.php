<?php
include 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$company_id = $_SESSION['current_company_id'] ?? 1;

function logDebug($message) {
    error_log(date('[Y-m-d H:i:s] ') . $message . PHP_EOL, 3, 'debug.log');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->begin_transaction();

        // Handle permanent deletion of archived income records
        if (isset($_POST['action']) && $_POST['action'] === 'delete_income' && isset($_POST['ids']) && is_array($_POST['ids'])) {
            $deleted_count = 0;
            foreach ($_POST['ids'] as $id) {
                $id = (int)$id;
                logDebug("Permanently deleting archived income: id=$id");

                // Delete from archive_income
                $stmt = $conn->prepare("DELETE FROM archive_income WHERE id = ? AND company_id = ?");
                $stmt->bind_param("ii", $id, $company_id);
                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        $deleted_count++;
                    } else {
                        logDebug("No archived income record found for id=$id");
                    }
                } else {
                    throw new Exception("Error deleting archived income id=$id: " . $conn->error);
                }
            }

            $_SESSION['success'] = "$deleted_count archived income record(s) permanently deleted successfully.";
        }

        // Handle permanent deletion of archived expense records
        if (isset($_POST['action']) && $_POST['action'] === 'delete_expenses' && isset($_POST['ids']) && is_array($_POST['ids'])) {
            $deleted_count = 0;
            foreach ($_POST['ids'] as $id) {
                $id = (int)$id;
                logDebug("Permanently deleting archived expense: id=$id");

                // Delete from archive_expenses
                $stmt = $conn->prepare("DELETE FROM archive_expenses WHERE id = ? AND company_id = ?");
                $stmt->bind_param("ii", $id, $company_id);
                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        $deleted_count++;
                    } else {
                        logDebug("No archived expense record found for id=$id");
                    }
                } else {
                    throw new Exception("Error deleting archived expense id=$id: " . $conn->error);
                }
            }

            $_SESSION['success'] = "$deleted_count archived expense record(s) permanently deleted successfully.";
        }

        $conn->commit();
        logDebug("Transaction committed successfully");
        header("Location: archive.php?tab=" . ($_POST['action'] === 'delete_income' ? 'income' : 'expenses') . "&company_id=$company_id");
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        logDebug("Error occurred: " . $e->getMessage());
        $_SESSION['error'] = "Error: " . $e->getMessage();
        header("Location: archive.php?tab=" . ($_POST['action'] === 'delete_income' ? 'income' : 'expenses') . "&company_id=$company_id");
        exit;
    }
}

logDebug("Invalid request method or action");
header("Location: archive.php?tab=income&company_id=$company_id");
exit;
?>