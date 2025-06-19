<?php
require '../db.php';

// Get company ID and year, with validation
$company_id = isset($_GET['company_id']) ? intval($_GET['company_id']) : 1;
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
if ($year < 1900 || $year > 9999) {
    $year = date('Y'); // Fallback to current year
}
$start_date = isset($_GET['start_date']) && !empty($_GET['start_date']) ? $_GET['start_date'] : "$year-01-01";
$end_date = isset($_GET['end_date']) && !empty($_GET['end_date']) ? $_GET['end_date'] : "$year-12-31";

// Validate dates
$startDateObj = DateTime::createFromFormat('Y-m-d', $start_date);
$endDateObj = DateTime::createFromFormat('Y-m-d', $end_date);
$currentYear = date('Y');
if (!$startDateObj || !$endDateObj || $startDateObj > $endDateObj || $startDateObj->format('Y') > $currentYear || $endDateObj->format('Y') > $currentYear) {
    $start_date = "$year-01-01";
    $end_date = "$year-12-31";
}

// Sanitize dates for SQL
$start_date = $conn->real_escape_string($start_date);
$end_date = $conn->real_escape_string($end_date);

// Get account balances (cumulative up to selected end date)
$assets = $conn->query("
    SELECT a.account_name, a.account_code, a.sub_category_1,
           (SELECT IFNULL(SUM(CASE WHEN je.entry_type = 'debit' THEN je.amount ELSE -je.amount END), 0)
            FROM journal_entries je
            JOIN transactions t ON je.transaction_id = t.id
            WHERE je.account_code = a.account_code
            AND je.company_id = a.company_id
            AND t.transaction_date <= '$end_date') as balance
    FROM accounts a
    WHERE a.account_type = 'Asset' 
    AND a.company_id = $company_id
    AND a.account_code NOT IN ('3000')
    ORDER BY 
        CASE 
            WHEN a.sub_category_1 = 'Current Assets' THEN 1
            WHEN a.sub_category_1 = 'Non Current Assets' THEN 2
            ELSE 3
        END, a.account_name
");

$liabilities = $conn->query("
    SELECT a.account_name, a.account_code, a.sub_category_1,
           (SELECT IFNULL(SUM(CASE WHEN je.entry_type = 'credit' THEN je.amount ELSE -je.amount END), 0)
            FROM journal_entries je
            JOIN transactions t ON je.transaction_id = t.id
            WHERE je.account_code = a.account_code
            AND je.company_id = a.company_id
            AND t.transaction_date <= '$end_date') as balance
    FROM accounts a
    WHERE a.account_type = 'Liability' 
    AND a.company_id = $company_id
    ORDER BY 
        CASE 
            WHEN a.sub_category_1 = 'Current Liability' THEN 1
            WHEN a.sub_category_1 = 'Non Current Liability' THEN 2
            ELSE 3
        END, a.account_name
");

$equity = $conn->query("
    SELECT a.account_name, a.account_code,
           (SELECT IFNULL(SUM(CASE WHEN je.entry_type = 'credit' THEN je.amount ELSE -je.amount END), 0)
            FROM journal_entries je
            JOIN transactions t ON je.transaction_id = t.id
            WHERE je.account_code = a.account_code
            AND je.company_id = a.company_id
            AND t.transaction_date <= '$end_date') as balance
    FROM accounts a
    WHERE a.account_type = 'Equity' 
    AND a.company_id = $company_id
    ORDER BY a.account_code
");

// Get Owner's Drawings
$owners_drawings = $conn->query("
    SELECT IFNULL(SUM(amount), 0) as total
    FROM expenses
    WHERE company_id = $company_id
    AND date <= '$end_date'
    AND sub_category IN ('Owner\'s Drawing', 'Owner\'s Drawings', 'Owners Drawings')
")->fetch_assoc()['total'] ?? 0;

// Calculate totals, adjusting for contra-accounts
$total_assets = $conn->query("
    SELECT IFNULL(SUM(
        (SELECT IFNULL(SUM(CASE WHEN je.entry_type = 'debit' THEN je.amount ELSE -je.amount END), 0)
         FROM journal_entries je
         JOIN transactions t ON je.transaction_id = t.id
         WHERE je.account_code = a.account_code
         AND je.company_id = a.company_id
         AND t.transaction_date <= '$end_date')
    ), 0) as total
    FROM accounts a
    WHERE a.account_type = 'Asset' 
    AND a.company_id = $company_id
    AND a.account_code NOT IN ('3000')
")->fetch_assoc()['total'] ?? 0;

$contra_assets = $conn->query("
    SELECT IFNULL(SUM(
        (SELECT IFNULL(SUM(CASE WHEN je.entry_type = 'credit' THEN je.amount ELSE -je.amount END), 0)
         FROM journal_entries je
         JOIN transactions t ON je.transaction_id = t.id
         WHERE je.account_code = a.account_code
         AND je.company_id = a.company_id
         AND t.transaction_date <= '$end_date')
    ), 0) as total
    FROM accounts a
    WHERE a.account_type = 'Asset' 
    AND a.company_id = $company_id
    AND a.account_code IN ('1400', '1800')
")->fetch_assoc()['total'] ?? 0;

$total_assets -= $contra_assets;

$total_liabilities = $conn->query("
    SELECT IFNULL(SUM(
        (SELECT IFNULL(SUM(CASE WHEN je.entry_type = 'credit' THEN je.amount ELSE -je.amount END), 0)
         FROM journal_entries je
         JOIN transactions t ON je.transaction_id = t.id
         WHERE je.account_code = a.account_code
         AND je.company_id = a.company_id
         AND t.transaction_date <= '$end_date')
    ), 0) as total
    FROM accounts a
    WHERE a.account_type = 'Liability' 
    AND a.company_id = $company_id
")->fetch_assoc()['total'] ?? 0;

$total_equity = $conn->query("
    SELECT IFNULL(SUM(
        (SELECT IFNULL(SUM(CASE WHEN je.entry_type = 'credit' THEN je.amount ELSE -je.amount END), 0)
         FROM journal_entries je
         JOIN transactions t ON je.transaction_id = t.id
         WHERE je.account_code = a.account_code
         AND je.company_id = a.company_id
         AND t.transaction_date < '$start_date')
    ), 0) as total
    FROM accounts a
    WHERE a.account_type = 'Equity' 
    AND a.company_id = $company_id
")->fetch_assoc()['total'] ?? 0;

$net_income = $conn->query("
    SELECT 
        (SELECT IFNULL(SUM(amount), 0) 
         FROM income 
         WHERE company_id = $company_id 
         AND date BETWEEN '$start_date' AND '$end_date'
         AND sub_category NOT IN ('Balance Sheet', 'Other Payable', 'Advances from Officers', 'Accumulated Depreciation', 'Accounts Receivable', 'Cash', 'Furniture and Equipment', 'Depreciation Expense'))
        - 
        (SELECT IFNULL(SUM(amount), 0) 
         FROM expenses 
         WHERE company_id = $company_id 
         AND date BETWEEN '$start_date' AND '$end_date'
         AND sub_category NOT IN ('Owner\'s Drawing', 'Owner\'s Drawings', 'Owners Drawings')) as net_income
")->fetch_assoc()['net_income'] ?? 0;

// Get available years
$years_result = $conn->query("
    SELECT DISTINCT YEAR(transaction_date) as year 
    FROM transactions 
    WHERE company_id = $company_id
    UNION
    SELECT DISTINCT YEAR(date) as year FROM income WHERE company_id = $company_id
    UNION
    SELECT DISTINCT YEAR(date) as year FROM expenses WHERE company_id = $company_id
    ORDER BY year DESC
");
$years = [];
while ($row = $years_result->fetch_assoc()) {
    $years[] = $row['year'];
}
if (empty($years)) {
    $years[] = date('Y');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Balance Sheet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        .balance-sheet-section {
            margin-bottom: 2rem;
        }
        .balance-sheet-table th,
        .balance-sheet-table td {
            vertical-align: middle;
            padding: 0.75rem;
        }
        .balance-sheet-table th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
        }
        .balance-sheet-table .total-row {
            background-color: #e9ecef;
            font-weight: bold;
        }
        @media (max-width: 768px) {
            .col-md-6 {
                flex: 0 0 100%;
                max-width: 100%;
            }
            .form-control, .form-select {
                margin-bottom: 0.5rem;
            }
        }
        .negative-equity {
            color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="background-pattern"></div>

    <?php include '../navbar.php'; ?>

    <div class="container mt-4">
        <?php if (isset($_GET['from_import']) && isset($_SESSION['import_result'])): ?>
            <div class="alert alert-<?= $_SESSION['import_result']['success'] ? 'success' : 'danger' ?> alert-dismissible fade show">
                <?= $_SESSION['import_result']['message'] ?>
                <div class="mt-2">
                    <a href="profit_loss.php?company_id=<?= $company_id ?>&year=<?= $year ?>" class="btn btn-sm btn-outline-primary me-2">Profit & Loss</a>
                    <a href="balance_sheet.php?company_id=<?= $company_id ?>&year=<?= $year ?>" class="btn btn-sm btn-outline-primary me-2">Balance Sheet</a>
                    <a href="cash_flow.php?company_id=<?= $company_id ?>&year=<?= $year ?>" class="btn btn-sm btn-outline-primary">Cash Flow</a>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['import_result']); ?>
        <?php endif; ?>

        <ul class="nav nav-tabs mb-4">
            <li class="nav-item">
                <a class="nav-link" href="profit_loss.php?company_id=<?= $company_id ?>&year=<?= $year ?>&start_date=<?= htmlspecialchars($start_date) ?>&end_date=<?= htmlspecialchars($end_date) ?>">Profit & Loss</a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="balance_sheet.php?company_id=<?= $company_id ?>&year=<?= $year ?>&start_date=<?= htmlspecialchars($start_date) ?>&end_date=<?= htmlspecialchars($end_date) ?>">Balance Sheet</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="cash_flow.php?company_id=<?= $company_id ?>&year=<?= $year ?>&start_date=<?= htmlspecialchars($start_date) ?>&end_date=<?= htmlspecialchars($end_date) ?>">Cash Flow</a>
            </li>
        </ul>
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Balance Sheet as of <?= (new DateTime($end_date))->format('F j, Y') ?></h2>
            <form method="GET" action="balance_sheet.php" id="filterForm" class="d-flex align-items-end flex-wrap">
                <input type="hidden" name="company_id" value="<?= $company_id ?>">
                <div class="me-2">
                    <label for="year" class="form-label">Year</label>
                    <select name="year" id="year" class="form-select">
                        <?php foreach ($years as $y): ?>
                            <option value="<?= $y ?>" <?= $y == $year ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="me-2">
                    <label for="start_date" class="form-label">Start Date</label>
                    <input type="date" name="start_date" id="start_date" class="form-control" value="<?= htmlspecialchars($_GET['start_date'] ?? '') ?>">
                </div>
                <div class="me-2">
                    <label for="end_date" class="form-label">End Date</label>
                    <input type="date" name="end_date" id="end_date" class="form-control" value="<?= htmlspecialchars($_GET['end_date'] ?? '') ?>">
                </div>
                <div class="align-self-end me-2">
                    <button type="submit" class="btn btn-primary">Filter</button>
                </div>
                <div class="align-self-end">
                    <a href="../export.php?type=balance_sheet&company_id=<?= $company_id ?>&year=<?= $year ?>&start_date=<?= htmlspecialchars($start_date) ?>&end_date=<?= htmlspecialchars($end_date) ?>" class="btn btn-success">
                        <i class="bi bi-file-earmark-excel"></i> Export
                    </a>
                </div>
            </form>
        </div>

        <div class="row">
            <!-- Assets -->
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h4>Assets</h4>
                    </div>
                    <div class="card-body">
                        <?php 
                        $has_assets = false;
                        $current_subcategory = '';
                        if ($assets->num_rows > 0):
                            $assets->data_seek(0);
                            while($row = $assets->fetch_assoc()): 
                                $balance = $row['balance'];
                                $sub_category = $row['sub_category_1'] ?? 'Uncategorized';
                                if ($row['account_code'] == '1400' || $row['account_code'] == '1800') {
                                    continue;
                                }
                                if ($balance != 0):
                                    $has_assets = true;
                                    if ($current_subcategory !== $sub_category) {
                                        if ($current_subcategory !== ''): ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h5><?= htmlspecialchars($sub_category) ?></h5>
                                    </div>
                                    <div class="card-body">
                                        <table class="table balance-sheet-table">
                                            <thead>
                                                <tr>
                                                    <th>Account</th>
                                                    <th class="text-end">Balance</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                        <?php
                                        $current_subcategory = $sub_category;
                                    }
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($row['account_name']) ?></td>
                                <td class="text-end"><?= number_format($balance, 2) ?></td>
                            </tr>
                            <?php 
                                endif;
                            endwhile;
                        endif; 
                        if ($has_assets): ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($assets->num_rows > 0): ?>
                <div class="card mb-4">
                    <div class="card-body">
                        <table class="table balance-sheet-table">
                            <tbody>
                                <tr class="total-row">
                                    <th>Total Assets</th>
                                    <th class="text-end"><?= number_format($total_assets, 2) ?></th>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php else: ?>
                <div class="card mb-4">
                    <div class="card-body">
                        <table class="table balance-sheet-table">
                            <tbody>
                                <tr>
                                    <td colspan="2" class="empty-message">No asset accounts with balances</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Liabilities & Equity -->
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h4>Liabilities</h4>
                    </div>
                    <div class="card-body">
                        <?php 
                        $has_liabilities = false;
                        $current_subcategory = '';
                        if ($liabilities->num_rows > 0):
                            $liabilities->data_seek(0);
                            while($row = $liabilities->fetch_assoc()): 
                                $balance = $row['balance'];
                                $sub_category = $row['sub_category_1'] ?? 'Uncategorized';
                                if ($balance != 0):
                                    $has_liabilities = true;
                                    if ($current_subcategory !== $sub_category) {
                                        if ($current_subcategory !== ''): ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h5><?= htmlspecialchars($sub_category) ?></h5>
                                    </div>
                                    <div class="card-body">
                                        <table class="table balance-sheet-table">
                                            <thead>
                                                <tr>
                                                    <th>Account</th>
                                                    <th class="text-end">Balance</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                        <?php
                                        $current_subcategory = $sub_category;
                                    }
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($row['account_name']) ?></td>
                                <td class="text-end"><?= number_format($balance, 2) ?></td>
                            </tr>
                            <?php 
                                endif;
                            endwhile;
                        endif; 
                        if ($has_liabilities): ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($liabilities->num_rows > 0): ?>
                <div class="card mb-4">
                    <div class="card-body">
                        <table class="table balance-sheet-table">
                            <tbody>
                                <tr class="total-row">
                                    <th>Total Liabilities</th>
                                    <th class="text-end"><?= number_format($total_liabilities, 2) ?></th>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php else: ?>
                <div class="card mb-4">
                    <div class="card-body">
                        <table class="table balance-sheet-table">
                            <tbody>
                                <tr>
                                    <td colspan="2" class="empty-message">No liability accounts with balances</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h4>Equity</h4>
                    </div>
                    <div class="card-body">
                        <table class="table balance-sheet-table">
                            <thead>
                                <tr>
                                    <th>Account</th>
                                    <th class="text-end">Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $has_equity = false;
                                $total_equity_balance = 0;
                                
                                if ($equity->num_rows > 0):
                                    $equity->data_seek(0);
                                    while($row = $equity->fetch_assoc()): 
                                        $balance = $row['balance'];
                                        if ($balance != 0):
                                            $has_equity = true;
                                            $total_equity_balance += $balance;
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['account_name']) ?></td>
                                    <td class="text-end"><?= number_format($balance, 2) ?></td>
                                </tr>
                                <?php 
                                        endif;
                                    endwhile;
                                endif; 
                                
                                // Add Owner's Drawings
                                if ($owners_drawings != 0): ?>
                                <tr>
                                    <td>Owner's Drawings</td>
                                    <td class="text-end negative-equity"><?= number_format(-$owners_drawings, 2) ?></td>
                                </tr>
                                <?php 
                                    $total_equity_balance -= $owners_drawings;
                                endif;
                                
                                if ($net_income != 0): 
                                ?>
                                <tr>
                                    <td>Current Period Earnings</td>
                                    <td class="text-end"><?= number_format($net_income, 2) ?></td>
                                </tr>
                                <?php 
                                    $total_equity_balance += $net_income;
                                endif; 
                                
                                if (!$has_equity && $net_income == 0 && $owners_drawings == 0): ?>
                                <tr>
                                    <td colspan="2" class="empty-message">No equity accounts with balances</td>
                                </tr>
                                <?php endif; ?>
                                
                                <?php if ($has_equity || $net_income != 0 || $owners_drawings != 0): ?>
                                <tr class="total-row">
                                    <th>Total Equity</th>
                                    <th class="text-end"><?= number_format($total_equity_balance, 2) ?></th>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if ($total_assets != 0 || $total_liabilities != 0 || $total_equity_balance != 0): ?>
        <div class="summary-section mt-4">
            <h4>Total Liabilities & Equity: <?= number_format($total_liabilities + $total_equity_balance, 2) ?></h4>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('year').addEventListener('change', function() {
            // Clear date inputs
            document.getElementById('start_date').value = '';
            document.getElementById('end_date').value = '';
            // Submit form
            document.getElementById('filterForm').submit();
        });
    </script>
</body>
</html>