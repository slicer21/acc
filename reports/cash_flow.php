<?php
require '../db.php';

// Get company ID and year, with validation
$company_id = isset($_GET['company_id']) ? intval($_GET['company_id']) : 1;
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
if ($year < 1900 || $year > 9999) {
    $year = date('Y');
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

// Operating Activities: Cash Inflows (Revenue)
$operating_inflows = $conn->query("
    SELECT 
        sub_category as description,
        SUM(amount) as amount,
        'income' as type
    FROM income
    WHERE payment_method IN ('Cash', 'Bank Transfer')
    AND company_id = $company_id
    AND date BETWEEN '$start_date' AND '$end_date'
    AND sub_category IN ('Receipts', 'Other Income')
    GROUP BY sub_category
");

// Operating Activities: Cash Outflows (Expenses, excluding non-cash items)
$operating_outflows = $conn->query("
    SELECT 
        CONCAT(category, ' - ', sub_category) as description,
        SUM(amount) as amount,
        'expense' as type
    FROM expenses
    WHERE payment_method IN ('Cash', 'Bank Transfer')
    AND company_id = $company_id
    AND date BETWEEN '$start_date' AND '$end_date'
    AND sub_category NOT IN ('Depreciation Expense', 'Bad Debt')
    GROUP BY category, sub_category
");

// Investing Activities: Cash Outflows (Purchases of Property and Equipment)
$investing_outflows = $conn->query("
    SELECT 
        a.account_name as description,
        SUM(CASE WHEN je.entry_type = 'debit' THEN je.amount ELSE -je.amount END) as amount,
        'expense' as type
    FROM journal_entries je
    JOIN transactions t ON je.transaction_id = t.id
    JOIN accounts a ON je.account_code = a.account_code
    WHERE a.account_type = 'Asset'
    AND a.sub_category_1 = 'Non Current Assets'
    AND a.sub_category_2 = 'Property and Equipments'
    AND a.account_name IN ('Furniture and Fixtures', 'Equipments', 'Land', 'Lease Improvements')
    AND je.company_id = $company_id
    AND t.transaction_date BETWEEN '$start_date' AND '$end_date'
    GROUP BY a.account_name
");

// Financing Activities: Cash Inflows (e.g., Capital Contributions, Borrowings)
$financing_inflows = $conn->query("
    SELECT 
        a.account_name as description,
        SUM(CASE WHEN je.entry_type = 'credit' THEN je.amount ELSE -je.amount END) as amount,
        'income' as type
    FROM journal_entries je
    JOIN transactions t ON je.transaction_id = t.id
    JOIN accounts a ON je.account_code = a.account_code
    WHERE a.account_type IN ('Equity', 'Liability')
    AND a.account_name IN ('Capital Equity', 'Notes Payable')
    AND je.company_id = $company_id
    AND t.transaction_date BETWEEN '$start_date' AND '$end_date'
    GROUP BY a.account_name
");

// Financing Activities: Cash Outflows (e.g., Debt Repayments)
$financing_outflows = $conn->query("
    SELECT 
        a.account_name as description,
        SUM(CASE WHEN je.entry_type = 'debit' THEN je.amount ELSE -je.amount END) as amount,
        'expense' as type
    FROM journal_entries je
    JOIN transactions t ON je.transaction_id = t.id
    JOIN accounts a ON je.account_code = a.account_code
    WHERE a.account_type = 'Liability'
    AND a.account_name IN ('Accounts Payable', 'Notes Payable', 'Other Payable', 'Advances from Officers/Employees')
    AND je.company_id = $company_id
    AND t.transaction_date BETWEEN '$start_date' AND '$end_date'
    GROUP BY a.account_name
");

// Calculate totals for each activity
$total_operating_inflows = $conn->query("
    SELECT SUM(amount) as total 
    FROM income 
    WHERE payment_method IN ('Cash', 'Bank Transfer')
    AND company_id = $company_id
    AND date BETWEEN '$start_date' AND '$end_date'
    AND sub_category IN ('Receipts', 'Other Income')
")->fetch_assoc()['total'] ?? 0;

$total_operating_outflows = $conn->query("
    SELECT SUM(amount) as total 
    FROM expenses 
    WHERE payment_method IN ('Cash', 'Bank Transfer')
    AND company_id = $company_id
    AND date BETWEEN '$start_date' AND '$end_date'
    AND sub_category NOT IN ('Depreciation Expense', 'Bad Debt')
")->fetch_assoc()['total'] ?? 0;

$total_investing_outflows = $conn->query("
    SELECT SUM(CASE WHEN je.entry_type = 'debit' THEN je.amount ELSE -je.amount END) as total
    FROM journal_entries je
    JOIN transactions t ON je.transaction_id = t.id
    JOIN accounts a ON je.account_code = a.account_code
    WHERE a.account_type = 'Asset'
    AND a.sub_category_1 = 'Non Current Assets'
    AND a.sub_category_2 = 'Property and Equipments'
    AND a.account_name IN ('Furniture and Fixtures', 'Equipments', 'Land', 'Lease Improvements')
    AND je.company_id = $company_id
    AND t.transaction_date BETWEEN '$start_date' AND '$end_date'
")->fetch_assoc()['total'] ?? 0;

$total_financing_inflows = $conn->query("
    SELECT SUM(CASE WHEN je.entry_type = 'credit' THEN je.amount ELSE -je.amount END) as total
    FROM journal_entries je
    JOIN transactions t ON je.transaction_id = t.id
    JOIN accounts a ON je.account_code = a.account_code
    WHERE a.account_type IN ('Equity', 'Liability')
    AND a.account_name IN ('Capital Equity', 'Notes Payable')
    AND je.company_id = $company_id
    AND t.transaction_date BETWEEN '$start_date' AND '$end_date'
")->fetch_assoc()['total'] ?? 0;

$total_financing_outflows = $conn->query("
    SELECT SUM(CASE WHEN je.entry_type = 'debit' THEN je.amount ELSE -je.amount END) as total
    FROM journal_entries je
    JOIN transactions t ON je.transaction_id = t.id
    JOIN accounts a ON je.account_code = a.account_code
    WHERE a.account_type = 'Liability'
    AND a.account_name IN ('Accounts Payable', 'Notes Payable', 'Other Payable', 'Advances from Officers/Employees')
    AND je.company_id = $company_id
    AND t.transaction_date BETWEEN '$start_date' AND '$end_date'
")->fetch_assoc()['total'] ?? 0;

// Calculate net cash flows for each activity
$net_operating_cash_flow = $total_operating_inflows - $total_operating_outflows;
$net_investing_cash_flow = -$total_investing_outflows;
$net_financing_cash_flow = $total_financing_inflows - $total_financing_outflows;

$net_cash_flow = $net_operating_cash_flow + $net_investing_cash_flow + $net_financing_cash_flow;

// Get cash account balance at beginning and end of period
$beginning_cash = $conn->query("
    SELECT 
        (SELECT IFNULL(SUM(CASE WHEN je.entry_type = 'debit' THEN je.amount ELSE -je.amount END), 0)
         FROM journal_entries je
         JOIN transactions t ON je.transaction_id = t.id
         WHERE je.account_code IN ('1000', '1100')
         AND je.company_id = $company_id
         AND t.transaction_date < '$start_date') as balance
")->fetch_assoc()['balance'] ?? 0;

$ending_cash = $conn->query("
    SELECT 
        (SELECT IFNULL(SUM(CASE WHEN je.entry_type = 'debit' THEN je.amount ELSE -je.amount END), 0)
         FROM journal_entries je
         JOIN transactions t ON je.transaction_id = t.id
         WHERE je.account_code IN ('1000', '1100')
         AND je.company_id = $company_id
         AND t.transaction_date <= '$end_date') as balance
")->fetch_assoc()['balance'] ?? 0;

// Get available years
$years_result = $conn->query("
    SELECT DISTINCT YEAR(date) as year 
    FROM (
        SELECT date FROM income WHERE company_id = $company_id
        UNION 
        SELECT date FROM expenses WHERE company_id = $company_id
        UNION 
        SELECT transaction_date as date FROM transactions WHERE company_id = $company_id
    ) as dates
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
    <title>Cash Flow Statement</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/cash_flow.css">
    <style>
        .cash-flow-table th,
        .cash-flow-table td {
            vertical-align: middle;
            padding: 0.75rem;
        }
        .cash-flow-table th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
        }
        .cash-flow-table .total-row {
            background-color: #e9ecef;
            font-weight: bold;
        }
        .cash-flow-table .subtotal-row {
            background-color: #f1f3f5;
            font-style: italic;
        }
        @media (max-width: 768px) {
            .form-control, .form-select {
                margin-bottom: 0.5rem;
            }
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
                <a class="nav-link" href="balance_sheet.php?company_id=<?= $company_id ?>&year=<?= $year ?>&start_date=<?= htmlspecialchars($start_date) ?>&end_date=<?= htmlspecialchars($end_date) ?>">Balance Sheet</a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="cash_flow.php?company_id=<?= $company_id ?>&year=<?= $year ?>&start_date=<?= htmlspecialchars($start_date) ?>&end_date=<?= htmlspecialchars($end_date) ?>">Cash Flow</a>
            </li>
        </ul>
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Cash Flow Statement for <?= (new DateTime($start_date))->format('F j') . " to " . (new DateTime($end_date))->format('F j, Y') ?></h2>
            <form method="GET" action="cash_flow.php" id="filterForm" class="d-flex align-items-end flex-wrap">
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
                    <a href="/export.php?type=cash_flow&company_id=<?= $company_id ?>&year=<?= $year ?>&start_date=<?= htmlspecialchars($start_date) ?>&end_date=<?= htmlspecialchars($end_date) ?>" class="btn btn-success">
                        <i class="bi bi-file-earmark-excel"></i> Export
                    </a>
                </div>
            </form>
        </div>

        <!-- Operating Activities -->
        <div class="card mb-4">
            <div class="card-header">
                <h4>Cash Flows from Operating Activities</h4>
            </div>
            <div class="card-body">
                <table class="table cash-flow-table">
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <th>Cash Receipts</th>
                            <td></td>
                        </tr>
                        <?php 
                        $operating_inflows->data_seek(0);
                        while($row = $operating_inflows->fetch_assoc()): ?>
                        <tr>
                            <td style="padding-left: 2rem"><?= htmlspecialchars($row['description']) ?></td>
                            <td class="text-end positive"><?= number_format($row['amount'], 2) ?></td>
                        </tr>
                        <?php endwhile; ?>
                        <tr class="subtotal-row">
                            <th>Total Cash Receipts</th>
                            <th class="text-end"><?= number_format($total_operating_inflows, 2) ?></th>
                        </tr>
                        <tr>
                            <th>Cash Payments</th>
                            <td></td>
                        </tr>
                        <?php 
                        $operating_outflows->data_seek(0);
                        while($row = $operating_outflows->fetch_assoc()): ?>
                        <tr>
                            <td style="padding-left: 2rem"><?= htmlspecialchars($row['description']) ?></td>
                            <td class="text-end negative">(<?= number_format($row['amount'], 2) ?>)</td>
                        </tr>
                        <?php endwhile; ?>
                        <tr class="subtotal-row">
                            <th>Total Cash Payments</th>
                            <th class="text-end">(<?= number_format($total_operating_outflows, 2) ?>)</th>
                        </tr>
                        <tr class="total-row">
                            <th>Total Net Cash Flow from Operating Activities</th>
                            <th class="text-end <?= $net_operating_cash_flow >= 0 ? 'positive' : 'negative' ?>">
                                <?= number_format($net_operating_cash_flow, 2) ?>
                            </th>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Investing Activities -->
        <div class="card mb-4">
            <div class="card-header">
                <h4>Cash Flows from Investing Activities</h4>
            </div>
            <div class="card-body">
                <table class="table cash-flow-table">
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $has_investing = false;
                        $investing_outflows->data_seek(0);
                        while($row = $investing_outflows->fetch_assoc()): 
                            if ($row['amount'] != 0):
                                $has_investing = true;
                        ?>
                        <tr>
                            <td>Purchase of <?= htmlspecialchars($row['description']) ?></td>
                            <td class="text-end negative">(<?= number_format($row['amount'], 2) ?>)</td>
                        </tr>
                        <?php endif; endwhile; ?>
                        <?php if ($has_investing): ?>
                        <tr class="total-row">
                            <th>Total Net Cash Flow from Investing Activities</th>
                            <th class="text-end negative">(<?= number_format($total_investing_outflows, 2) ?>)</th>
                        </tr>
                        <?php else: ?>
                        <tr>
                            <td colspan="2" class="text-center">No investing activities recorded</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Financing Activities -->
        <div class="card mb-4">
            <div class="card-header">
                <h4>Cash Flows from Financing Activities</h4>
            </div>
            <div class="card-body">
                <table class="table cash-flow-table">
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $has_financing = false;
                        $financing_inflows->data_seek(0);
                        while($row = $financing_inflows->fetch_assoc()): 
                            if ($row['amount'] != 0):
                                $has_financing = true;
                        ?>
                        <tr>
                            <td>Proceeds from <?= htmlspecialchars($row['description']) ?></td>
                            <td class="text-end positive"><?= number_format($row['amount'], 2) ?></td>
                        </tr>
                        <?php endif; endwhile; ?>
                        <?php 
                        $financing_outflows->data_seek(0);
                        while($row = $financing_outflows->fetch_assoc()): 
                            if ($row['amount'] != 0):
                                $has_financing = true;
                        ?>
                        <tr>
                            <td>Repayment of <?= htmlspecialchars($row['description']) ?></td>
                            <td class="text-end negative">(<?= number_format($row['amount'], 2) ?>)</td>
                        </tr>
                        <?php endif; endwhile; ?>
                        <?php if ($has_financing): ?>
                        <tr class="total-row">
                            <th>Total Net Cash Flow from Financing Activities</th>
                            <th class="text-end <?= $net_financing_cash_flow >= 0 ? 'positive' : 'negative' ?>">
                                <?= number_format($net_financing_cash_flow, 2) ?>
                            </th>
                        </tr>
                        <?php else: ?>
                        <tr>
                            <td colspan="2" class="text-center">No financing activities recorded</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Summary -->
        <div class="card mb-4">
            <div class="card-header">
                <h4>Cash Flow Summary</h4>
            </div>
            <div class="card-body">
                <table class="table cash-flow-table">
                    <tbody>
                        <tr>
                            <th>Net Cash Flow from Operating Activities</th>
                            <td class="text-end <?= $net_operating_cash_flow >= 0 ? 'positive' : 'negative' ?>">
                                <?= number_format($net_operating_cash_flow, 2) ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Net Cash Flow from Investing Activities</th>
                            <td class="text-end <?= $net_investing_cash_flow >= 0 ? 'positive' : 'negative' ?>">
                                <?= number_format($net_investing_cash_flow, 2) ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Net Cash Flow from Financing Activities</th>
                            <td class="text-end <?= $net_financing_cash_flow >= 0 ? 'positive' : 'negative' ?>">
                                <?= number_format($net_financing_cash_flow, 2) ?>
                            </td>
                        </tr>
                        <tr class="total-row">
                            <th>Total Net Increase (Decrease) in Cash</th>
                            <th class="text-end <?= $net_cash_flow >= 0 ? 'positive' : 'negative' ?>">
                                <?= number_format($net_cash_flow, 2) ?>
                            </th>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Cash Balance -->
        <div class="card">
            <div class="card-header">
                <h4>Cash Balance</h4>
            </div>
            <div class="card-body">
                <table class="table cash-flow-table">
                    <tbody>
                        <tr>
                            <th>Beginning Cash Balance (<?= (new DateTime($start_date))->format('F j, Y') ?>)</th>
                            <td class="text-end"><?= number_format($beginning_cash, 2) ?></td>
                        </tr>
                        <tr>
                            <th>Total Net Increase (Decrease) in Cash</th>
                            <td class="text-end <?= $net_cash_flow >= 0 ? 'positive' : 'negative' ?>">
                                <?= number_format($net_cash_flow, 2) ?>
                            </td>
                        </tr>
                        <tr class="total-row">
                            <th>Total Ending Cash Balance (<?= (new DateTime($end_date))->format('F j, Y') ?>)</th>
                            <td class="text-end <?= $ending_cash >= 0 ? 'positive' : 'negative' ?>">
                                 <?= number_format($ending_cash, 2) ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('year').addEventListener('change', function() {
            document.getElementById('start_date').value = '';
            document.getElementById('end_date').value = '';
            document.getElementById('filterForm').submit();
        });
    </script>
</body>
</html>