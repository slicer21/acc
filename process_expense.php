<?php
include 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$company_id = $_SESSION['current_company_id'] ?? 1;

function logDebug($message) {
    error_log(date('[Y-m-d H:i:s] ') . $message . PHP_EOL, 3, 'debug.log');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $conn->begin_transaction();

        if (isset($_POST['add_expense'])) {
            $date = $_POST['date'] ?? date('Y-m-d');
            $receipt_no = trim($_POST['receipt_no'] ?? '');
            $vendor_name = trim($_POST['vendor_name'] ?? '');
            $supplier = trim($_POST['supplier'] ?? '');
            $supplier_tin = trim($_POST['supplier_tin'] ?? '');
            $explanation = trim($_POST['explanation'] ?? '');
            $category = trim($_POST['category'] ?? 'General and Administrative Expenses');
            $sub_category = trim($_POST['sub_category'] ?? 'Other');
            $amount = (float)($_POST['amount'] ?? 0);
            $payment_method = $_POST['payment_method'] ?? 'Cash';
            $notes = trim($_POST['notes'] ?? '');

            if (empty($vendor_name) || $amount <= 0 || empty($category) || empty($sub_category)) {
                throw new Exception("Vendor Name, Amount, Category, and Sub Category are required.");
            }

            logDebug("Adding expense: vendor=$vendor_name, amount=$amount, category=$category, receipt_no=$receipt_no");

            $stmt = $conn->prepare("INSERT INTO expenses 
                                  (date, receipt_no, vendor_name, supplier, supplier_tin, explanation, category, 
                                  sub_category, amount, payment_method, notes, company_id) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssssdssi", 
                $date, $receipt_no, $vendor_name, $supplier, $supplier_tin, $explanation, $category,
                $sub_category, $amount, $payment_method, $notes, $company_id);

            if (!$stmt->execute()) {
                throw new Exception("Error adding expense: " . $conn->error);
            }

            setupDefaultAccounts($company_id);

            $expense_account = '5300';
            if (stripos($category, 'General') !== false) {
                $expense_account = '5000';
            } elseif (stripos($category, 'Facility') !== false) {
                $expense_account = '5200';
            }

            $entries = [
                [
                    'account_code' => $expense_account,
                    'amount' => $amount,
                    'entry_type' => 'debit'
                ],
                [
                    'account_code' => '1000',
                    'amount' => $amount,
                    'entry_type' => 'credit'
                ]
            ];

            recordTransaction(
                $date,
                "Expense: $category - $sub_category to $vendor_name",
                $entries,
                $receipt_no,
                $company_id
            );

            $_SESSION['expense_message'] = [
                'success' => true,
                'message' => 'Expense added successfully.'
            ];
        } elseif (isset($_POST['edit_expense'])) {
            $id = (int)($_POST['id'] ?? 0);
            $date = $_POST['date'] ?? date('Y-m-d');
            $receipt_no = trim($_POST['receipt_no'] ?? '');
            $vendor_name = trim($_POST['vendor_name'] ?? '');
            $supplier = trim($_POST['supplier'] ?? '');
            $supplier_tin = trim($_POST['supplier_tin'] ?? '');
            $explanation = trim($_POST['explanation'] ?? '');
            $category = trim($_POST['category'] ?? 'General and Administrative Expenses');
            $sub_category = trim($_POST['sub_category'] ?? 'Other');
            $amount = (float)($_POST['amount'] ?? 0);
            $payment_method = $_POST['payment_method'] ?? 'Cash';
            $notes = trim($_POST['notes'] ?? '');

            if ($id <= 0 || empty($vendor_name) || $amount <= 0 || empty($category) || empty($sub_category)) {
                throw new Exception("Invalid data provided for editing expense.");
            }

            logDebug("Editing expense: id=$id, vendor=$vendor_name, amount=$amount");

            $stmt = $conn->prepare("UPDATE expenses 
                                  SET date = ?, receipt_no = ?, vendor_name = ?, supplier = ?, supplier_tin = ?, 
                                  explanation = ?, category = ?, sub_category = ?, amount = ?, 
                                  payment_method = ?, notes = ? 
                                  WHERE id = ? AND company_id = ?");
            $stmt->bind_param("ssssssssdssii", 
                $date, $receipt_no, $vendor_name, $supplier, $supplier_tin, $explanation, $category,
                $sub_category, $amount, $payment_method, $notes, $id, $company_id);

            if (!$stmt->execute()) {
                throw new Exception("Error updating expense: " . $conn->error);
            }

            setupDefaultAccounts($company_id);

            $expense_account = '5300';
            if (stripos($category, 'General') !== false) {
                $expense_account = '5000';
            } elseif (stripos($category, 'Facility') !== false) {
                $expense_account = '5200';
            }

            $entries = [
                [
                    'account_code' => $expense_account,
                    'amount' => $amount,
                    'entry_type' => 'debit'
                ],
                [
                    'account_code' => '1000',
                    'amount' => $amount,
                    'entry_type' => 'credit'
                ]
            ];

            recordTransaction(
                $date,
                "Expense: $category - $sub_category to $vendor_name",
                $entries,
                $receipt_no,
                $company_id
            );

            $_SESSION['expense_message'] = [
                'success' => true,
                'message' => 'Expense updated successfully.'
            ];
        } elseif (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['ids']) && is_array($_POST['ids'])) {
            $archived_count = 0;
            foreach ($_POST['ids'] as $id) {
                $id = (int)$id;
                logDebug("Archiving expense: id=$id");

                // Fetch expense record to archive
                $stmt = $conn->prepare("SELECT date, receipt_no, vendor_name, supplier, supplier_tin, explanation, 
                                              category, sub_category, amount, payment_method, notes 
                                       FROM expenses WHERE id = ? AND company_id = ?");
                $stmt->bind_param("ii", $id, $company_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($row = $result->fetch_assoc()) {
                    // Insert into archive_expenses
                    $stmt = $conn->prepare("INSERT INTO archive_expenses 
                                           (original_id, date, receipt_no, vendor_name, supplier, supplier_tin, 
                                           explanation, category, sub_category, amount, payment_method, notes, company_id) 
                                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("issssssssdssi", 
                        $id, 
                        $row['date'], 
                        $row['receipt_no'], 
                        $row['vendor_name'], 
                        $row['supplier'], 
                        $row['supplier_tin'], 
                        $row['explanation'], 
                        $row['category'], 
                        $row['sub_category'], 
                        $row['amount'], 
                        $row['payment_method'], 
                        $row['notes'], 
                        $company_id
                    );
                    
                    if (!$stmt->execute()) {
                        throw new Exception("Error archiving expense id=$id: " . $conn->error);
                    }
                    
                    // Find associated transaction
                    $stmt = $conn->prepare("SELECT t.id as transaction_id, j.account_code, j.amount, j.entry_type 
                                           FROM transactions t 
                                           JOIN journal_entries j ON t.id = j.transaction_id 
                                           WHERE t.reference_no = ? AND t.company_id = ?");
                    $stmt->bind_param("si", $row['receipt_no'], $company_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    $transaction_id = null;
                    $accounts_to_update = [];
                    while ($trans_row = $result->fetch_assoc()) {
                        $transaction_id = $trans_row['transaction_id'];
                        $accounts_to_update[] = [
                            'account_code' => $trans_row['account_code'],
                            'amount' => $trans_row['amount'],
                            'entry_type' => $trans_row['entry_type']
                        ];
                    }
                    
                    // If no transaction found, log and proceed
                    if (!$transaction_id) {
                        logDebug("No transaction found for expense id=$id");
                    } else {
                        // Delete journal entries
                        $stmt = $conn->prepare("DELETE FROM journal_entries WHERE transaction_id = ? AND company_id = ?");
                        $stmt->bind_param("ii", $transaction_id, $company_id);
                        if (!$stmt->execute()) {
                            throw new Exception("Error deleting journal entries for transaction_id=$transaction_id: " . $conn->error);
                        }
                        
                        // Delete transaction
                        $stmt = $conn->prepare("DELETE FROM transactions WHERE id = ? AND company_id = ?");
                        $stmt->bind_param("ii", $transaction_id, $company_id);
                        if (!$stmt->execute()) {
                            throw new Exception("Error deleting transaction id=$transaction_id: " . $conn->error);
                        }
                        
                        // Reverse account balances
                        foreach ($accounts_to_update as $account) {
                            $operator = $account['entry_type'] == 'debit' ? '-' : '+';
                            $query = "UPDATE accounts 
                                     SET balance = balance $operator {$account['amount']} 
                                     WHERE account_code = ? AND company_id = ?";
                            $stmt = $conn->prepare($query);
                            $stmt->bind_param("si", $account['account_code'], $company_id);
                            if (!$stmt->execute()) {
                                throw new Exception("Error updating account balance for account_code={$account['account_code']}: " . $conn->error);
                            }
                        }
                    }
                    
                    // Delete expense record
                    $stmt = $conn->prepare("DELETE FROM expenses WHERE id = ? AND company_id = ?");
                    $stmt->bind_param("ii", $id, $company_id);
                    if (!$stmt->execute()) {
                        throw new Exception("Error deleting expense id=$id: " . $conn->error);
                    }
                    
                    $archived_count++;
                } else {
                    logDebug("Expense record id=$id not found");
                }
            }
            
            // Reset all account balances to zero
            $query = "UPDATE accounts SET balance = 0 WHERE company_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $company_id);
            if (!$stmt->execute()) {
                throw new Exception("Error resetting account balances: " . $conn->error);
            }
            
            $_SESSION['expense_message'] = [
                'success' => true,
                'message' => "$archived_count expense(s) archived successfully."
            ];
        }

        $conn->commit();
        logDebug("Transaction committed successfully");
        header("Location: expenses.php");
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        logDebug("Error occurred: " . $e->getMessage());
        $_SESSION['expense_message'] = [
            'success' => false,
            'message' => "Error: " . $e->getMessage()
        ];
        header("Location: expenses.php");
        exit;
    }
}

logDebug("Invalid request method or action");
header("Location: expenses.php");
exit;
?>