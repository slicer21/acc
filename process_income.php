<?php
require 'db.php';

session_start();

function logDebug($message) {
    error_log(date('[Y-m-d H:i:s] ') . $message . PHP_EOL, 3, 'debug.log');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $company_id = $_SESSION['current_company_id'] ?? 1;
    
    try {
        $conn->begin_transaction();

        if (isset($_POST['add_income'])) {
            $date = $_POST['date'];
            $invoice_no = $_POST['invoice_no'] ?? null;
            $payor = $_POST['payor'] ?? null;
            $sub_category = $_POST['sub_category'];
            $amount = (float)$_POST['amount'];
            $payment_method = $_POST['payment_method'];
            $notes = $_POST['notes'] ?? '';
            
            // Use payor as donor_name for consistency
            $donor_name = $payor;
            
            $stmt = $conn->prepare("INSERT INTO income 
                                   (date, donor_name, invoice_no, payor, sub_category, amount, payment_method, notes, company_id) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssdssi", $date, $donor_name, $invoice_no, $payor, $sub_category, $amount, $payment_method, $notes, $company_id);
            
            if ($stmt->execute()) {
                $account_code = $sub_category == 'Tithes' ? '4000' : '4100';
                
                $entries = [
                    [
                        'account_code' => $account_code,
                        'amount' => $amount,
                        'entry_type' => 'credit'
                    ],
                    [
                        'account_code' => '1000',
                        'amount' => $amount,
                        'entry_type' => 'debit'
                    ]
                ];
                
                recordTransaction($date, "Income: $sub_category from $payor", $entries, $invoice_no, $company_id);
                
                $_SESSION['success'] = "Income record added successfully!";
            } else {
                throw new Exception("Error adding income record: " . $conn->error);
            }
        }
        
        if (isset($_POST['edit_income'])) {
            $id = (int)$_POST['id'];
            $date = $_POST['date'];
            $invoice_no = $_POST['invoice_no'] ?? null;
            $payor = $_POST['payor'] ?? null;
            $sub_category = $_POST['sub_category'];
            $amount = (float)$_POST['amount'];
            $payment_method = $_POST['payment_method'];
            $notes = $_POST['notes'] ?? '';
            
            // Use payor as donor_name for consistency
            $donor_name = $payor;
            
            $stmt = $conn->prepare("UPDATE income SET date=?, donor_name=?, invoice_no=?, payor=?, sub_category=?, amount=?, payment_method=?, notes=? 
                                   WHERE id=? AND company_id=?");
            $stmt->bind_param("sssssdssii", $date, $donor_name, $invoice_no, $payor, $sub_category, $amount, $payment_method, $notes, $id, $company_id);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Income record updated successfully!";
            } else {
                throw new Exception("Error updating income record: " . $conn->error);
            }
        }
        
        if (isset($_POST['ids']) && is_array($_POST['ids'])) {
            $archived_count = 0;
            foreach ($_POST['ids'] as $id) {
                $id = (int)$id;
                
                logDebug("Archiving income: id=$id");
                
                // Fetch income record to archive
                $stmt = $conn->prepare("SELECT date, invoice_no, donor_name as source, sub_category, amount, payment_method, notes 
                                       FROM income WHERE id = ? AND company_id = ?");
                $stmt->bind_param("ii", $id, $company_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($row = $result->fetch_assoc()) {
                    // Insert into archive_income
                    $stmt = $conn->prepare("INSERT INTO archive_income 
                                           (original_id, date, reference_no, source, explanation, category, sub_category, amount, payment_method, notes, company_id) 
                                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $explanation = "Archived income from " . $row['source'];
                    $category = 'Revenue';
                    $stmt->bind_param("issssssdssi", 
                        $id, 
                        $row['date'], 
                        $row['invoice_no'], 
                        $row['source'], 
                        $explanation, 
                        $category, 
                        $row['sub_category'], 
                        $row['amount'], 
                        $row['payment_method'], 
                        $row['notes'], 
                        $company_id
                    );
                    
                    if (!$stmt->execute()) {
                        throw new Exception("Error archiving income id=$id: " . $conn->error);
                    }
                    
                    // Find associated transaction
                    $stmt = $conn->prepare("SELECT t.id as transaction_id, j.account_code, j.amount, j.entry_type 
                                           FROM transactions t 
                                           JOIN journal_entries j ON t.id = j.transaction_id 
                                           WHERE t.reference_no = ? AND t.company_id = ?");
                    $stmt->bind_param("si", $row['invoice_no'], $company_id);
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
                        logDebug("No transaction found for income id=$id");
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
                    
                    // Delete income record
                    $stmt = $conn->prepare("DELETE FROM income WHERE id = ? AND company_id = ?");
                    $stmt->bind_param("ii", $id, $company_id);
                    if (!$stmt->execute()) {
                        throw new Exception("Error deleting income id=$id: " . $conn->error);
                    }
                    
                    $archived_count++;
                } else {
                    logDebug("Income record id=$id not found");
                }
            }
            
            // Reset all account balances to zero
            $query = "UPDATE accounts SET balance = 0 WHERE company_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $company_id);
            if (!$stmt->execute()) {
                throw new Exception("Error resetting account balances: " . $conn->error);
            }
            
            $_SESSION['success'] = "$archived_count income record(s) archived successfully!";
        }

        $conn->commit();
        logDebug("Transaction committed successfully");
        header("Location: income.php");
        exit;
        
    } catch (Exception $e) {
        $conn->rollback();
        logDebug("Error occurred: " . $e->getMessage());
        $_SESSION['error'] = "Error: " . $e->getMessage();
        header("Location: income.php");
        exit;
    }
}

logDebug("Invalid request method or action");
header("Location: income.php");
exit;
?>