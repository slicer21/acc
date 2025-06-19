<?php
require '../db.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
    if (!isset($_SESSION['current_company_id'])) {
        $_SESSION['current_company_id'] = $company_id; // Set default if not set
    }
}

// Get company ID and year, with validation and logging
$company_id = isset($_GET['company_id']) ? intval($_GET['company_id']) : (isset($_SESSION['current_company_id']) ? $_SESSION['current_company_id'] : 1);
if ($company_id <= 0) {
    file_put_contents('profit_loss_errors.log', "Invalid company ID: $company_id at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    die("Invalid company ID");
}
file_put_contents('profit_loss_errors.log', "Company ID set to: $company_id at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

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
    $startDateObj = DateTime::createFromFormat('Y-m-d', $start_date);
    $endDateObj = DateTime::createFromFormat('Y-m-d', $end_date);
    file_put_contents('profit_loss_errors.log', "Date range reset to $start_date to $end_date at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
}

// Sanitize dates for SQL
$start_date = $conn->real_escape_string($start_date);
$end_date = $conn->real_escape_string($end_date);

// Get totals for display with date range filter - exclude equity/balance sheet items
$total_income_query = $conn->query("
    SELECT IFNULL(SUM(amount), 0) as total 
    FROM income 
    WHERE company_id = $company_id
    AND date BETWEEN '$start_date' AND '$end_date'
    AND sub_category NOT IN (
        'Balance Sheet', 
        'Other Payable', 
        'Advances from Officers', 
        'Accumulated Depreciation', 
        'Accounts Receivable', 
        'Cash', 
        'Furniture and Equipment', 
        'Depreciation Expense',
        'Owners Drawings',
        'Equity',
        'Retained Earnings'
    )
");
if ($total_income_query === false) {
    file_put_contents('profit_loss_errors.log', "Income query failed: " . $conn->error . " at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    $total_income = 0;
} else {
    $total_income = $total_income_query->fetch_assoc()['total'] ?? 0;
    file_put_contents('profit_loss_errors.log', "Total income: $total_income at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
}

$total_expenses_query = $conn->query("
    SELECT IFNULL(SUM(amount), 0) as total 
    FROM expenses 
    WHERE company_id = $company_id
    AND date BETWEEN '$start_date' AND '$end_date'
    AND category NOT IN ('Equity', 'Owners Equity')
");
if ($total_expenses_query === false) {
    file_put_contents('profit_loss_errors.log', "Expenses query failed: " . $conn->error . " at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    $total_expenses = 0;
} else {
    $total_expenses = $total_expenses_query->fetch_assoc()['total'] ?? 0;
    file_put_contents('profit_loss_errors.log', "Total expenses: $total_expenses at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
}

$net = $total_income - $total_expenses;
file_put_contents('profit_loss_errors.log', "Net: $net at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

// Get income breakdown - exclude equity/balance sheet items
$income_breakdown = $conn->query("
    SELECT sub_category, SUM(amount) as total 
    FROM income 
    WHERE company_id = $company_id
    AND date BETWEEN '$start_date' AND '$end_date'
    AND sub_category NOT IN (
        'Balance Sheet', 
        'Other Payable', 
        'Advances from Officers', 
        'Accumulated Depreciation', 
        'Accounts Receivable', 
        'Cash', 
        'Furniture and Equipment', 
        'Depreciation Expense',
        'Owners Drawings',
        'Equity',
        'Retained Earnings'
    )
    GROUP BY sub_category
    ORDER BY sub_category
");
if ($income_breakdown === false) {
    file_put_contents('profit_loss_errors.log', "Income breakdown query failed: " . $conn->error . " at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
}

// Get expense breakdown - exclude equity categories
$expense_breakdown_query = $conn->query("
    SELECT category, COALESCE(sub_category, 'Other') as sub_category, SUM(amount) as total 
    FROM expenses 
    WHERE company_id = $company_id
    AND date BETWEEN '$start_date' AND '$end_date'
    AND category NOT IN ('Equity', 'Owners Equity')
    GROUP BY category, sub_category
    ORDER BY 
        FIELD(category, 'Direct cost', 'Expenses'),
        sub_category
");
if ($expense_breakdown_query === false) {
    file_put_contents('profit_loss_errors.log', "Expense breakdown query failed: " . $conn->error . " at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
}

// Store expense breakdown in an array for easier processing
$expense_breakdown = [];
if ($expense_breakdown_query && $expense_breakdown_query->num_rows > 0) {
    while ($row = $expense_breakdown_query->fetch_assoc()) {
        $expense_breakdown[] = $row;
    }
}

// Get available years
$years_result = $conn->query("
    SELECT DISTINCT YEAR(date) as year 
    FROM (
        SELECT date FROM income WHERE company_id = $company_id
        UNION 
        SELECT date FROM expenses WHERE company_id = $company_id
    ) as dates
    ORDER BY year DESC
");
if ($years_result === false) {
    file_put_contents('profit_loss_errors.log', "Years query failed: " . $conn->error . " at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
}
$years = [];
if ($years_result) {
    while ($row = $years_result->fetch_assoc()) {
        $years[] = $row['year'];
    }
}
if (empty($years)) {
    $years[] = date('Y');
    file_put_contents('profit_loss_errors.log', "No years found, defaulting to " . date('Y') . " at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
}

// Map categories for display
$category_mapping = [
    'Direct cost' => 'Cost of Services',
    'Expenses' => 'General and Administrative Cost'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Profit & Loss Statement</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/profit_loss.css">
</head>
<body>
    <div class="background-pattern"></div>

    <?php include '../navbar.php'; ?>

    <div class="container mt-4">
        <?php if (isset($_GET['from_import']) && isset($_SESSION['import_result'])): ?>
            <div class="alert alert-<?= $_SESSION['import_result']['success'] ? 'success' : 'danger' ?> alert-dismissible fade show">
                <?= htmlspecialchars($_SESSION['import_result']['message']) ?>
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
                <a class="nav-link active" href="profit_loss.php?company_id=<?= $company_id ?>&year=<?= $year ?>&start_date=<?= htmlspecialchars($start_date) ?>&end_date=<?= htmlspecialchars($end_date) ?>">
                    Profit & Loss
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="balance_sheet.php?company_id=<?= $company_id ?>&year=<?= $year ?>&start_date=<?= htmlspecialchars($start_date) ?>&end_date=<?= htmlspecialchars($end_date) ?>">
                    Balance Sheet
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="cash_flow.php?company_id=<?= $company_id ?>&year=<?= $year ?>&start_date=<?= htmlspecialchars($start_date) ?>&end_date=<?= htmlspecialchars($end_date) ?>">
                    Cash Flow
                </a>
            </li>
        </ul>
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Profit & Loss Statement for <?= $startDateObj->format('F j') . " to " . $endDateObj->format('F j, Y') ?></h2>
            <form method="GET" action="profit_loss.php" id="filterForm" class="d-flex align-items-end flex-wrap">
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
                    <a href="../export.php?type=profit_loss&company_id=<?= $company_id ?>&year=<?= $year ?>&start_date=<?= htmlspecialchars($start_date) ?>&end_date=<?= htmlspecialchars($end_date) ?>" class="btn btn-success">
                        <i class="bi bi-file-earmark-excel"></i> Export
                    </a>
                </div>
            </form>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h4>Revenue</h4>
                    </div>
                    <div class="card-body">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th class="text-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($income_breakdown && $income_breakdown->num_rows > 0): ?>
                                    <?php while($row = $income_breakdown->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['sub_category']) ?></td>
                                        <td class="text-end"><?= number_format($row['total'], 2) ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="2" class="text-center" style="color: #718096; font-style: italic;">No income recorded for this period</td>
                                    </tr>
                                <?php endif; ?>
                                <tr class="table-primary">
                                    <th>Total Revenue</th>
                                    <th class="text-end"><?= number_format($total_income, 2) ?></th>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <?php
                // Organize expenses by main category
                $grouped_expenses = [];
                $category_totals = [];
                foreach ($expense_breakdown as $row) {
                    $main_category = $category_mapping[$row['category']] ?? $row['category'];
                    if (!isset($grouped_expenses[$main_category])) {
                        $grouped_expenses[$main_category] = [];
                        $category_totals[$main_category] = 0;
                    }
                    $grouped_expenses[$main_category][] = [
                        'sub_category' => $row['sub_category'],
                        'total' => $row['total']
                    ];
                    $category_totals[$main_category] += $row['total'];
                }

                if (!empty($grouped_expenses)):
                    foreach ($grouped_expenses as $main_category => $sub_categories): ?>
                        <div class="card mb-4">
                            <div class="card-header">
                                <h4><?= htmlspecialchars($main_category) ?></h4>
                            </div>
                            <div class="card-body">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Sub-Category</th>
                                            <th class="text-end">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($sub_categories as $sub): ?>
                                            <tr>
                                                <td style="padding-left: 2rem"><?= htmlspecialchars($sub['sub_category']) ?></td>
                                                <td class="text-end"><?= number_format($sub['total'], 2) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <tr class="table-light">
                                            <th>Total <?= htmlspecialchars($main_category) ?></th>
                                            <th class="text-end"><?= number_format($category_totals[$main_category], 2) ?></th>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div class="card mb-4">
                        <div class="card-body">
                            <table class="table">
                                <tr class="table-danger">
                                    <th>Total Expenses</th>
                                    <th class="text-end"><?= number_format($total_expenses, 2) ?></th>
                                </tr>
                            </table>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card mb-4">
                        <div class="card-body">
                            <table class="table">
                                <tr>
                                    <td colspan="2" class="text-center" style="color: #718096; font-style: italic;">No expenses recorded for this period</td>
                                </tr>
                                <tr class="table-danger">
                                    <th>Total Expenses</th>
                                    <th class="text-end"><?= number_format($total_expenses, 2) ?></th>
                                </tr>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="net-result <?= $net >= 0 ? 'positive' : 'negative' ?> mb-4">
            <h3 class="card-title">Net <?= $net >= 0 ? 'Profit' : 'Loss' ?> for <?= $startDateObj->format('F j') . " to " . $endDateObj->format('F j, Y') ?></h3>
            <h2 class="<?= $net >= 0 ? 'positive' : 'negative' ?>"><?= number_format(abs($net), 2) ?></h2>
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