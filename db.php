<?php
session_start();

$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'acc';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Authentication check
function require_auth() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }
}

// Check if company can be deleted
function canDeleteCompany($company_id) {
    global $conn;
    
    if ($company_id == 1) {
        return [
            'can_delete' => false,
            'reason' => 'Cannot delete the main church organization'
        ];
    }
    
    $tables = [
        'income' => 'income records',
        'expenses' => 'expense records', 
        'transactions' => 'transactions',
        'journal_entries' => 'journal entries'
    ];
    
    foreach ($tables as $table => $description) {
        $result = $conn->query("SELECT COUNT(*) as count FROM $table WHERE company_id = $company_id");
        if ($result->fetch_assoc()['count'] > 0) {
            return [
                'can_delete' => false,
                'reason' => "Company has existing $description"
            ];
        }
    }
    
    return ['can_delete' => true, 'reason' => ''];
}

// Setup default chart of accounts
function setupDefaultAccounts($company_id) {
    global $conn;
    
    $default_accounts = [
        // Assets
        ['1000', 'Cash', 'Asset'],
        ['1100', 'Accounts Receivable', 'Asset'],
        ['1200', 'Prepaid Expenses', 'Asset'],
        ['1300', 'Equipment', 'Asset'],
        ['1400', 'Accumulated Depreciation', 'Asset'],
        
        // Liabilities
        ['2000', 'Accounts Payable', 'Liability'],
        ['2100', 'Short-Term Loans', 'Liability'],
        ['2200', 'Long-Term Loans', 'Liability'],
        ['2300', 'Deferred Revenue', 'Liability'],
        ['2400', 'Output VAT', 'Liability'],
        ['2500', 'Input VAT', 'Liability'], // Added Input VAT
        
        // Equity
        ['3000', 'General Fund', 'Equity'],
        ['3100', 'Restricted Funds', 'Equity'],
        ['3200', 'Retained Earnings', 'Equity'],
        
        // Revenue
        ['4000', 'Tithes', 'Revenue'],
        ['4100', 'Offerings', 'Revenue'],
        ['4200', 'Donations', 'Revenue'],
        ['4300', 'Fundraising', 'Revenue'],
        
        // Expenses
        ['5000', 'Salaries', 'Expense'],
        ['5100', 'Facility Costs', 'Expense'],
        ['5200', 'Ministry Expenses', 'Expense'],
        ['5300', 'Outreach', 'Expense'],
        ['5400', 'Administrative', 'Expense']
    ];
    
    foreach ($default_accounts as $account) {
        $check = $conn->prepare("SELECT id FROM accounts 
                               WHERE account_code = ? AND company_id = ?");
        $check->bind_param("si", $account[0], $company_id);
        $check->execute();
        
        if ($check->get_result()->num_rows == 0) {
            $stmt = $conn->prepare("INSERT INTO accounts 
                                  (account_code, account_name, account_type, company_id) 
                                  VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sssi", $account[0], $account[1], $account[2], $company_id);
            $stmt->execute();
        }
    }
}

// Record a transaction with double-entry accounting
function recordTransaction($date, $description, $entries, $reference_no = null, $company_id = 1) {
    global $conn;
    
    $conn->begin_transaction();
    
    try {
        // Create transaction record
        $stmt = $conn->prepare("INSERT INTO transactions 
                              (transaction_date, description, reference_no, company_id) 
                              VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $date, $description, $reference_no, $company_id);
        $stmt->execute();
        $transaction_id = $conn->insert_id;
        
        // Record each journal entry
        foreach ($entries as $entry) {
            $stmt = $conn->prepare("INSERT INTO journal_entries 
                                  (transaction_id, account_code, amount, entry_type, company_id) 
                                  VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("isdsi", $transaction_id, $entry['account_code'], 
                             $entry['amount'], $entry['entry_type'], $company_id);
            $stmt->execute();
            
            // Update account balance
            $operator = $entry['entry_type'] == 'debit' ? '+' : '-';
            $conn->query("UPDATE accounts 
                         SET balance = balance $operator {$entry['amount']} 
                         WHERE account_code = '{$entry['account_code']}' 
                         AND company_id = $company_id");
        }
        
        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Transaction failed: " . $e->getMessage());
        return false;
    }
}

// Check company access
function hasCompanyAccess($company_id) {
    return true;
}

function getCompanyById($company_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM companies WHERE id = ?");
    $stmt->bind_param("i", $company_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function updateCompany($company_id, $name, $description) {
    global $conn;
    $stmt = $conn->prepare("UPDATE companies SET name = ?, description = ? WHERE id = ?");
    $stmt->bind_param("ssi", $name, $description, $company_id);
    return $stmt->execute();
}

// Generate CSRF token
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verify CSRF token
function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
?>