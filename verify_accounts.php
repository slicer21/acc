<?php
require 'db.php';

header('Content-Type: text/plain');

try {
    $conn->begin_transaction();
    
    echo "Starting account verification...\n";
    
    // 1. Ensure all companies have default accounts
    echo "Checking companies...\n";
    $companies = $conn->query("SELECT id, name FROM companies");
    $company_count = $companies->num_rows;
    echo "Found $company_count companies\n";
    
    while ($company = $companies->fetch_assoc()) {
        echo "Verifying accounts for {$company['name']} (ID: {$company['id']})... ";
        setupDefaultAccounts($company['id']);
        echo "Done\n";
    }
    
    // 2. Clean up near-zero balances
    echo "Cleaning up near-zero balances... ";
    $cleanup = $conn->query("UPDATE accounts SET balance = 0 WHERE ABS(balance) < 0.01");
    $affected = $conn->affected_rows;
    echo "Cleaned $affected accounts\n";
    
    // 3. Remove orphaned journal entries
    echo "Checking for orphaned journal entries... ";
    $orphan_journals = $conn->query("SELECT COUNT(*) as count FROM journal_entries je
                                   LEFT JOIN transactions t ON je.transaction_id = t.id
                                   WHERE t.id IS NULL")->fetch_assoc()['count'];
    echo "Found $orphan_journals orphaned entries\n";
    
    if ($orphan_journals > 0) {
        echo "Removing orphaned journal entries... ";
        $conn->query("DELETE je FROM journal_entries je
                     LEFT JOIN transactions t ON je.transaction_id = t.id
                     WHERE t.id IS NULL");
        echo "Done\n";
    }
    
    // 4. Remove orphaned transactions
    echo "Checking for orphaned transactions... ";
    $orphan_transactions = $conn->query("SELECT COUNT(*) as count FROM transactions t
                                       LEFT JOIN companies c ON t.company_id = c.id
                                       WHERE c.id IS NULL")->fetch_assoc()['count'];
    echo "Found $orphan_transactions orphaned transactions\n";
    
    if ($orphan_transactions > 0) {
        echo "Removing orphaned transactions... ";
        $conn->query("DELETE t FROM transactions t
                     LEFT JOIN companies c ON t.company_id = c.id
                     WHERE c.id IS NULL");
        echo "Done\n";
    }
    
    $conn->commit();
    echo "\nAccount verification completed successfully!\n";
    echo "Summary:\n";
    echo "- Verified/Created accounts for $company_count companies\n";
    echo "- Cleaned $affected near-zero balance accounts\n";
    echo "- Removed $orphan_journals orphaned journal entries\n";
    echo "- Removed $orphan_transactions orphaned transactions\n";
    
} catch (Exception $e) {
    $conn->rollback();
    echo "\nError during account verification: " . $e->getMessage() . "\n";
    echo "All changes have been rolled back\n";
}
?>