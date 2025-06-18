<?php
require 'db.php';

$base_url = "http://" . $_SERVER['SERVER_ADDR'] . ":8080/acc/"; // Adjusted for port and subdirectory

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Church Accounting System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/dashboard.css">
</head>
<body>
    <div class="background-pattern" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: url('<?php echo $base_url; ?>assets/images/background.jpg') repeat; z-index: -1;"></div>

    <?php include 'navbar.php'; ?>

    <div class="container-fluid mt-3">
        <?php if (isset($_SESSION['import_result'])): ?>
            <div class="alert alert-<?= $_SESSION['import_result']['success'] ? 'success' : 'danger' ?>">
                <?= $_SESSION['import_result']['message'] ?>
                <?php if (isset($_SESSION['import_result']['details'])): ?>
                    <div class="mt-2">
                        <strong>Income:</strong> <?= $_SESSION['import_result']['details']['income']['imported'] ?> imported, 
                        <?= $_SESSION['import_result']['details']['income']['skipped'] ?> skipped<br>
                        <strong>Expenses:</strong> <?= $_SESSION['import_result']['details']['expenses']['imported'] ?> imported, 
                        <?= $_SESSION['import_result']['details']['expenses']['skipped'] ?> skipped
                    </div>
                <?php endif; ?>
            </div>
            <?php unset($_SESSION['import_result']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['import_errors'])): ?>
            <div class="alert alert-danger">
                <h5>Import Errors</h5>
                <ul>
                    <?php foreach ($_SESSION['import_errors'] as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php unset($_SESSION['import_errors']); ?>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-3">
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Navigation</h5>
                        <span class="company-badge">
                            <?= $_SESSION['current_company_name'] ?? 'Main Church' ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <ul class="nav flex-column">
                            <li class="nav-item">
                                <a class="nav-link active" href="dashboard.php"><i class="bi bi-house-door"></i> Dashboard</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="income.php"><i class="bi bi-cash-coin"></i> Income</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="expenses.php"><i class="bi bi-receipt"></i> Expenses</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="reports/profit_loss.php"><i class="bi bi-graph-up"></i> Financial Reports</a>
                            </li>
                        </ul>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Financial Summary</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $company_id = $_SESSION['current_company_id'] ?? 1;
                        
                        // Total Income
                        $income = $conn->prepare("SELECT SUM(amount) as total FROM income WHERE company_id = ?");
                        $income->bind_param("i", $company_id);
                        $income->execute();
                        $total_income = $income->get_result()->fetch_assoc()['total'] ?? 0;
                        
                        // Total Expenses
                        $expenses = $conn->prepare("SELECT SUM(amount) as total FROM expenses WHERE company_id = ?");
                        $expenses->bind_param("i", $company_id);
                        $expenses->execute();
                        $total_expenses = $expenses->get_result()->fetch_assoc()['total'] ?? 0;
                        
                        // Cash Balance
                        $cash = $conn->prepare("SELECT balance FROM accounts WHERE account_code = '1000' AND company_id = ?");
                        $cash->bind_param("i", $company_id);
                        $cash->execute();
                        $cash_balance = $cash->get_result()->fetch_assoc()['balance'] ?? 0;
                        
                        $net = $total_income - $total_expenses;
                        ?>
                        <p>Total Income: <span class="positive"><?= number_format($total_income, 2) ?></span></p>
                        <p>Total Expenses: <span class="negative"><?= number_format($total_expenses, 2) ?></span></p>
                        <p>Net Balance: <span class="<?= $net >= 0 ? 'positive' : 'negative' ?>"><?= number_format($net, 2) ?></span></p>
                        <p>Cash on Hand: <span class="<?= $cash_balance >= 0 ? 'positive' : 'negative' ?>"><?= number_format($cash_balance, 2) ?></span></p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-9">
                <div class="card">
                    <div class="card-header">
                        <ul class="nav nav-tabs card-header-tabs">
                            <li class="nav-item">
                                <a class="nav-link active" data-bs-toggle="tab" href="#recent-income">Recent Income</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#recent-expenses">Recent Expenses</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#import-data">Import Data</a>
                            </li>
                        </ul>
                    </div>
                    
                    <div class="card-body">
                        <div class="tab-content">
                            <div class="tab-pane active" id="recent-income">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Payor</th>
                                                <th>Type</th>
                                                <th>Amount</th>
                                                <th>Method</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $company_id = $_SESSION['current_company_id'] ?? 1;
                                            $income_items = $conn->prepare("SELECT * FROM income 
                                                                           WHERE company_id = ? 
                                                                           ORDER BY date DESC LIMIT 10");
                                            $income_items->bind_param("i", $company_id);
                                            $income_items->execute();
                                            $result = $income_items->get_result();
                                            
                                            while($row = $result->fetch_assoc()):
                                            ?>
                                            <tr>
                                                <td><?= date('m/d/Y', strtotime($row['date'])) ?></td>
                                                <td><?= htmlspecialchars($row['donor_name']) ?></td>
                                                <td><?= htmlspecialchars($row['sub_category']) ?></td>
                                                <td><?= number_format($row['amount'], 2) ?></td>
                                                <td><?= htmlspecialchars($row['payment_method']) ?></td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <a href="income.php" class="btn btn-primary mt-2">View All Income</a>
                            </div>
                            
                            <div class="tab-pane" id="recent-expenses">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Vendor</th>
                                                <th>Category</th>
                                                <th>Amount</th>
                                                <th>Receipt No.</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $company_id = $_SESSION['current_company_id'] ?? 1;
                                            $expense_items = $conn->prepare("SELECT * FROM expenses 
                                                                            WHERE company_id = ? 
                                                                            ORDER BY date DESC LIMIT 10");
                                            $expense_items->bind_param("i", $company_id);
                                            $expense_items->execute();
                                            $result = $expense_items->get_result();
                                            
                                            while ($row = $result->fetch_assoc()):
                                            ?>
                                            <tr>
                                                <td><?= date('m/d/Y', strtotime($row['date'])) ?></td>
                                                <td><?= htmlspecialchars($row['vendor_name']) ?></td>
                                                <td><?= htmlspecialchars($row['category']) ?> - <?= htmlspecialchars($row['sub_category']) ?></td>
                                                <td><?= number_format($row['amount'], 2) ?></td>
                                                <td><?= htmlspecialchars($row['receipt_no']) ?></td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <a href="expenses.php" class="btn btn-primary mt-2">View All Expenses</a>
                            </div>
                            
                            <div class="tab-pane" id="import-data">
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5>Import Financial Data</h5>
                                            </div>
                                            <div class="card-body">
                                                <form action="import_financials.php" method="POST" enctype="multipart/form-data">
                                                    <div class="mb-3">
                                                        <label class="form-label">Select Excel File</label>
                                                        <input type="file" name="file" class="form-control" accept=".xlsx, .xls, .csv" required>
                                                        <small class="text-muted">File should contain 'Income' and 'Expenses' sheets</small>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Church</label>
                                                        <select name="company_id" class="form-select" required>
                                                            <?php
                                                            $companies = $conn->query("SELECT * FROM companies");
                                                            while ($company = $companies->fetch_assoc()): ?>
                                                            <option value="<?= $company['id'] ?>" 
                                                                    <?= ($company['id'] == ($_SESSION['current_company_id'] ?? 1)) ? 'selected' : '' ?>>
                                                                <?= $company['name'] ?>
                                                            </option>
                                                            <?php endwhile; ?>
                                                        </select>
                                                    </div>
                                                    <button type="submit" class="btn btn-primary">
                                                        <i class="bi bi-upload"></i> Import Financial Data
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Activate tab based on URL hash
            if (window.location.hash) {
                const tab = new bootstrap.Tab(document.querySelector(`a[href="${window.location.hash}"]`));
                if (tab) tab.show();
            }
            
            // Update the URL hash when tabs are clicked
            const tabLinks = document.querySelectorAll('[data-bs-toggle="tab"]');
            tabLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const tab = new bootstrap.Tab(this);
                    if (tab) tab.show();
                    window.location.hash = this.getAttribute('href');
                });
            });
        });
    </script>
</body>
</html>