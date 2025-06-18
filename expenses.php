<?php
require 'db.php';

$base_url = "http://" . $_SERVER['SERVER_ADDR'] . ":8080/acc/"; // Adjusted for port and subdirectory
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Expenses</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/expenses.css">
    <style>
        body {
            position: relative;
            min-height: 100vh;
        }
        .background-pattern {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: #f8f9fa;
            opacity: 0.05;
            z-index: -1;
            background-image: radial-gradient(#adb5bd 1px, transparent 1px);
            background-size: 20px 20px;
        }
        .total-row {
            font-weight: bold;
            background-color: #f8f9fa;
        }
        .no-records {
            text-align: center;
            padding: 20px;
            color: #6c757d;
        }
        .pagination {
            margin-top: 20px;
            justify-content: center;
        }
        .page-item.disabled .page-link {
            pointer-events: none;
        }
        .view-toggle {
            margin-bottom: 15px;
        }
        .card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        }
        .card-header {
            background: #f8fafc;
            border-bottom: none;
            border-radius: 16px 16px 0 0;
        }
        .btn-primary {
            background: linear-gradient(90deg, #3B82F6, #9333EA);
            border: none;
        }
        .btn-primary:hover {
            background: linear-gradient(90deg, #2563EB, #7C3AED);
        }
        .btn-danger {
            background-color: #dc3545;
        }
        .btn-danger:hover {
            background-color: #c82333;
        }
    </style>
</head>
<body>
    <div class="background-pattern"></div>

    <?php include 'navbar.php'; ?>

    <div class="container mt-4">
        <div class="row mb-4 align-items-center">
            <div class="col-md-8">
                <h2><i class="bi bi-receipt"></i> Expenses</h2>
            </div>
            <div class="col-md-4 text-end">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
                    <i class="bi bi-plus-circle"></i> Add New
                </button>
                <button class="btn btn-danger" id="deleteSelected" disabled>
                    <i class="bi bi-trash"></i> Delete Selected
                </button>
                <a href="<?= $base_url ?>export.php?type=expense&company_id=<?= $_SESSION['current_company_id'] ?? 1 ?>&from_date=<?= urlencode($_GET['from_date'] ?? '') ?>&to_date=<?= urlencode($_GET['to_date'] ?? '') ?>&category=<?= urlencode($_GET['category'] ?? '') ?>&search=<?= urlencode($_GET['search'] ?? '') ?>" class="btn btn-success">
                    <i class="bi bi-file-earmark-excel"></i> Export
                </a>
            </div>
        </div>

        <?php
        $company_id = $_SESSION['current_company_id'] ?? 1;
        $records_per_page = 10;
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $page = max($page, 1);
        $view_all = isset($_GET['view']) && $_GET['view'] === 'all';
        $offset = ($page - 1) * $records_per_page;

        // Fetch available categories for filter
        $category_query = "SELECT DISTINCT category FROM expenses WHERE company_id = ?";
        $category_params = [$company_id];
        $category_types = "i";
        
        if (!empty($_GET['from_date'])) {
            $category_query .= " AND date >= ?";
            $category_params[] = $_GET['from_date'];
            $category_types .= 's';
        }
        if (!empty($_GET['to_date'])) {
            $category_query .= " AND date <= ?";
            $category_params[] = $_GET['to_date'];
            $category_types .= 's';
        }
        
        $category_query .= " ORDER BY category";
        
        $category_stmt = $conn->prepare($category_query);
        if (count($category_params) > 1) {
            $category_stmt->bind_param($category_types, ...$category_params);
        } else {
            $category_stmt->bind_param($category_types, $category_params[0]);
        }
        $category_stmt->execute();
        $category_result = $category_stmt->get_result();
        $available_categories = [];
        while ($category_row = $category_result->fetch_assoc()) {
            $available_categories[] = $category_row['category'];
        }
        $category_stmt->close();

        // Count total records for pagination
        $count_query = "SELECT COUNT(*) as total FROM expenses WHERE company_id = ?";
        $count_params = [$company_id];
        $count_types = "i";
        
        if (!empty($_GET['from_date'])) {
            $count_query .= " AND date >= ?";
            $count_params[] = $_GET['from_date'];
            $count_types .= 's';
        }
        if (!empty($_GET['to_date'])) {
            $count_query .= " AND date <= ?";
            $count_params[] = $_GET['to_date'];
            $count_types .= 's';
        }
        if (!empty($_GET['category'])) {
            $count_query .= " AND category = ?";
            $count_params[] = $_GET['category'];
            $count_types .= 's';
        }
        if (!empty($_GET['search'])) {
            $count_query .= " AND vendor_name LIKE ?";
            $count_params[] = '%' . $_GET['search'] . '%';
            $count_types .= 's';
        }
        
        $count_stmt = $conn->prepare($count_query);
        if (count($count_params) > 1) {
            $count_stmt->bind_param($count_types, ...$count_params);
        } else {
            $count_stmt->bind_param($count_types, $count_params[0]);
        }
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        $total_rows = $count_result->fetch_assoc()['total'];
        $total_pages = ceil($total_rows / $records_per_page);
        $count_stmt->close();
        ?>

        <div class="filter-section">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">From Date</label>
                    <input type="date" name="from_date" class="form-control" 
                           value="<?= htmlspecialchars($_GET['from_date'] ?? '') ?>"
                           max="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">To Date</label>
                    <input type="date" name="to_date" class="form-control" 
                           value="<?= htmlspecialchars($_GET['to_date'] ?? '') ?>"
                           max="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Category</label>
                    <select name="category" class="form-select">
                        <option value="">All Categories</option>
                        <?php foreach ($available_categories as $category): ?>
                            <option value="<?= htmlspecialchars($category) ?>" 
                                    <?= ($_GET['category'] ?? '') == $category ? 'selected' : '' ?>>
                                <?= htmlspecialchars($category) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Search Vendor</label>
                    <input type="text" name="search" class="form-control" 
                           value="<?= htmlspecialchars($_GET['search'] ?? '') ?>"
                           placeholder="Search by Vendor">
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-funnel"></i> Filter
                    </button>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="view-toggle">
                    <?php if ($view_all): ?>
                        <a href="?company_id=<?= $company_id ?>&from_date=<?= urlencode($_GET['from_date'] ?? '') ?>&to_date=<?= urlencode($_GET['to_date'] ?? '') ?>&category=<?= urlencode($_GET['category'] ?? '') ?>&search=<?= urlencode($_GET['search'] ?? '') ?>" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-list"></i> Show Paginated
                        </a>
                    <?php else: ?>
                        <a href="?company_id=<?= $company_id ?>&view=all&from_date=<?= urlencode($_GET['from_date'] ?? '') ?>&to_date=<?= urlencode($_GET['to_date'] ?? '') ?>&category=<?= urlencode($_GET['category'] ?? '') ?>&search=<?= urlencode($_GET['search'] ?? '') ?>" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-list"></i> See All
                        </a>
                    <?php endif; ?>
                </div>

                <form id="deleteExpenseForm" action="process_expense.php" method="POST">
                    <input type="hidden" name="action" value="delete">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th><input type="checkbox" id="selectAll"></th>
                                    <th>Date</th>
                                    <th>Vendor</th>
                                    <th>Supplier</th>
                                    <th>Supplier TIN</th>
                                    <th>Explanation</th>
                                    <th>Category</th>
                                    <th>Sub Category</th>
                                    <th>Amount</th>
                                    <th>Input VAT (12%)</th>
                                    <th>Receipt No.</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $query = "SELECT * FROM expenses WHERE company_id = ?";
                                $params = [$company_id];
                                $types = "i";
                                
                                if (!empty($_GET['from_date'])) {
                                    $query .= " AND date >= ?";
                                    $params[] = $_GET['from_date'];
                                    $types .= 's';
                                }
                                if (!empty($_GET['to_date'])) {
                                    $query .= " AND date <= ?";
                                    $params[] = $_GET['to_date'];
                                    $types .= 's';
                                }
                                if (!empty($_GET['category'])) {
                                    $query .= " AND category = ?";
                                    $params[] = $_GET['category'];
                                    $types .= 's';
                                }
                                if (!empty($_GET['search'])) {
                                    $query .= " AND vendor_name LIKE ?";
                                    $params[] = '%' . $_GET['search'] . '%';
                                    $types .= 's';
                                }
                                
                                $query .= " ORDER BY date DESC, id DESC";
                                if (!$view_all) {
                                    $query .= " LIMIT ? OFFSET ?";
                                    $params[] = $records_per_page;
                                    $params[] = $offset;
                                    $types .= 'ii';
                                }
                                
                                $stmt = $conn->prepare($query);
                                if ($types) {
                                    $stmt->bind_param($types, ...$params);
                                }
                                
                                $stmt->execute();
                                $result = $stmt->get_result();
                                
                                if ($result->num_rows > 0) {
                                    $total_amount = 0;
                                    $total_input_vat = 0;
                                    while ($row = $result->fetch_assoc()):
                                        $total_amount += $row['amount'];
                                        $input_vat = $row['input_vat'] ?? ($row['amount'] * 0.12);
                                        $total_input_vat += $input_vat;
                                ?>
                                <tr>
                                    <td><input type="checkbox" name="ids[]" value="<?= $row['id'] ?>" class="selectRow"></td>
                                    <td><?= htmlspecialchars($row['date']) ?></td>
                                    <td><?= htmlspecialchars($row['vendor_name']) ?></td>
                                    <td><?= htmlspecialchars($row['supplier'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($row['supplier_tin'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($row['explanation'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($row['category']) ?></td>
                                    <td><?= htmlspecialchars($row['sub_category']) ?></td>
                                    <td><?= number_format($row['amount'], 2) ?></td>
                                    <td><?= number_format($input_vat, 2) ?></td>
                                    <td><?= htmlspecialchars($row['receipt_no'] ?? '') ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editExpenseModal" 
                                                data-id="<?= $row['id'] ?>"
                                                data-date="<?= $row['date'] ?>"
                                                data-receipt="<?= htmlspecialchars($row['receipt_no'] ?? '') ?>"
                                                data-vendor="<?= htmlspecialchars($row['vendor_name']) ?>"
                                                data-supplier="<?= htmlspecialchars($row['supplier'] ?? '') ?>"
                                                data-supplier-tin="<?= htmlspecialchars($row['supplier_tin'] ?? '') ?>"
                                                data-explanation="<?= htmlspecialchars($row['explanation'] ?? '') ?>"
                                                data-category="<?= htmlspecialchars($row['category']) ?>"
                                                data-subcategory="<?= htmlspecialchars($row['sub_category']) ?>"
                                                data-amount="<?= $row['amount'] ?>"
                                                data-input-vat="<?= $input_vat ?>"
                                                data-method="<?= htmlspecialchars($row['payment_method']) ?>"
                                                data-notes="<?= htmlspecialchars($row['notes'] ?? '') ?>">
                                            <i class="bi bi-pencil"></i> Edit
                                        </button>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                                <tr class="total-row">
                                    <td></td>
                                    <td colspan="7" class="text-end">Total:</td>
                                    <td><?= number_format($total_amount, 2) ?></td>
                                    <td><?= number_format($total_input_vat, 2) ?></td>
                                    <td colspan="2"></td>
                                </tr>
                                <?php
                                } else {
                                ?>
                                <tr>
                                    <td colspan="12" class="no-records">
                                        <i class="bi bi-exclamation-circle"></i> No expense records found matching your criteria
                                    </td>
                                </tr>
                                <?php
                                }
                                $stmt->close();
                                ?>
                            </tbody>
                        </table>
                    </div>
                </form>

                <?php if ($total_pages > 1 && !$view_all): ?>
                    <nav aria-label="Expense records pagination">
                        <ul class="pagination">
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="?company_id=<?= $company_id ?>&page=<?= $page - 1 ?>&from_date=<?= urlencode($_GET['from_date'] ?? '') ?>&to_date=<?= urlencode($_GET['to_date'] ?? '') ?>&category=<?= urlencode($_GET['category'] ?? '') ?>&search=<?= urlencode($_GET['search'] ?? '') ?>" aria-label="Previous">
                                    <span aria-hidden="true">«</span>
                                </a>
                            </li>
                            <?php
                            $max_pages_display = 5;
                            $half_range = floor($max_pages_display / 2);
                            $start_page = max(1, $page - $half_range);
                            $end_page = min($total_pages, $start_page + $max_pages_display - 1);
                            
                            if ($end_page - $start_page + 1 < $max_pages_display) {
                                $start_page = max(1, $end_page - $max_pages_display + 1);
                            }
                            
                            for ($i = $start_page; $i <= $end_page; $i++): ?>
                                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?company_id=<?= $company_id ?>&page=<?= $i ?>&from_date=<?= urlencode($_GET['from_date'] ?? '') ?>&to_date=<?= urlencode($_GET['to_date'] ?? '') ?>&category=<?= urlencode($_GET['category'] ?? '') ?>&search=<?= urlencode($_GET['search'] ?? '') ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                <a class="page-link" href="?company_id=<?= $company_id ?>&page=<?= $page + 1 ?>&from_date=<?= urlencode($_GET['from_date'] ?? '') ?>&to_date=<?= urlencode($_GET['to_date'] ?? '') ?>&category=<?= urlencode($_GET['category'] ?? '') ?>&search=<?= urlencode($_GET['search'] ?? '') ?>" aria-label="Next">
                                    <span aria-hidden="true">»</span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Expense Modal -->
    <div class="modal fade" id="addExpenseModal" tabindex="-1" aria-labelledby="addExpenseModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="process_expense.php" method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addExpenseModalLabel">Add Expense</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Date <span class="text-danger">*</span></label>
                            <input type="date" name="date" class="form-control" required 
                                   value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Receipt No.</label>
                            <input type="text" name="receipt_no" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Vendor Name <span class="text-danger">*</span></label>
                            <input type="text" name="vendor_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Supplier</label>
                            <input type="text" name="supplier" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Supplier TIN</label>
                            <input type="text" name="supplier_tin" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Explanation</label>
                            <input type="text" name="explanation" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Category <span class="text-danger">*</span></label>
                            <select name="category" class="form-select" required>
                                <option value="">-- Select Category --</option>
                                <?php
                                $company_id = $_SESSION['current_company_id'] ?? 1;
                                $categories = $conn->query("SELECT DISTINCT category FROM expenses WHERE company_id = $company_id ORDER BY category");
                                while ($cat = $categories->fetch_assoc()): ?>
                                <option value="<?= htmlspecialchars($cat['category']) ?>">
                                    <?= htmlspecialchars($cat['category']) ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Sub Category <span class="text-danger">*</span></label>
                            <select name="sub_category" class="form-select" required>
                                <option value="">-- Select Sub Category --</option>
                                <?php
                                $company_id = $_SESSION['current_company_id'] ?? 1;
                                $sub_categories = $conn->query("SELECT DISTINCT sub_category FROM expenses WHERE company_id = $company_id ORDER BY sub_category");
                                while ($sub_cat = $sub_categories->fetch_assoc()): ?>
                                <option value="<?= htmlspecialchars($sub_cat['sub_category']) ?>">
                                    <?= htmlspecialchars($sub_cat['sub_category']) ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Amount <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01" name="amount" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Input VAT (12%)</label>
                            <input type="number" step="0.01" min="0.00" name="input_vat" class="form-control" readonly value="0.00">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                            <select name="payment_method" class="form-select" required>
                                <option value="Cash">Cash</option>
                                <option value="Check">Check</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Credit Card">Credit Card</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_expense" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Expense Modal -->
    <div class="modal fade" id="editExpenseModal" tabindex="-1" aria-labelledby="editExpenseModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="process_expense.php" method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editExpenseModalLabel">Edit Expense</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id" id="edit_expense_id">
                        <div class="mb-3">
                            <label class="form-label">Date <span class="text-danger">*</span></label>
                            <input type="date" name="date" id="edit_expense_date" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Receipt No.</label>
                            <input type="text" name="receipt_no" id="edit_expense_receipt" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Vendor Name <span class="text-danger">*</span></label>
                            <input type="text" name="vendor_name" id="edit_expense_vendor" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Supplier</label>
                            <input type="text" name="supplier" id="edit_expense_supplier" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Supplier TIN</label>
                            <input type="text" name="supplier_tin" id="edit_expense_supplier_tin" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Explanation</label>
                            <input type="text" name="explanation" id="edit_expense_explanation" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Category <span class="text-danger">*</span></label>
                            <select name="category" id="edit_expense_category" class="form-select" required>
                                <option value="">-- Select Category --</option>
                                <?php
                                $company_id = $_SESSION['current_company_id'] ?? 1;
                                $categories = $conn->query("SELECT DISTINCT category FROM expenses WHERE company_id = $company_id ORDER BY category");
                                while ($cat = $categories->fetch_assoc()): ?>
                                <option value="<?= htmlspecialchars($cat['category']) ?>">
                                    <?= htmlspecialchars($cat['category']) ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Sub Category <span class="text-danger">*</span></label>
                            <select name="sub_category" id="edit_expense_subcategory" class="form-select" required>
                                <option value="">-- Select Sub Category --</option>
                                <?php
                                $company_id = $_SESSION['current_company_id'] ?? 1;
                                $sub_categories = $conn->query("SELECT DISTINCT sub_category FROM expenses WHERE company_id = $company_id ORDER BY sub_category");
                                while ($sub_cat = $sub_categories->fetch_assoc()): ?>
                                <option value="<?= htmlspecialchars($sub_cat['sub_category']) ?>">
                                    <?= htmlspecialchars($sub_cat['sub_category']) ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Amount <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01" name="amount" id="edit_expense_amount" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Input VAT (12%)</label>
                            <input type="number" step="0.01" min="0.00" name="input_vat" id="edit_expense_input_vat" class="form-control" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                            <select name="payment_method" id="edit_expense_method" class="form-select" required>
                                <option value="Cash">Cash</option>
                                <option value="Check">Check</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Credit Card">Credit Card</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" id="edit_expense_notes" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="edit_expense" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('editExpenseModal').addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const modal = this;
            
            modal.querySelector('#edit_expense_id').value = button.getAttribute('data-id');
            modal.querySelector('#edit_expense_date').value = button.getAttribute('data-date');
            modal.querySelector('#edit_expense_receipt').value = button.getAttribute('data-receipt') || '';
            modal.querySelector('#edit_expense_vendor').value = button.getAttribute('data-vendor');
            modal.querySelector('#edit_expense_supplier').value = button.getAttribute('data-supplier') || '';
            modal.querySelector('#edit_expense_supplier_tin').value = button.getAttribute('data-supplier-tin') || '';
            modal.querySelector('#edit_expense_explanation').value = button.getAttribute('data-explanation') || '';
            
            const categorySelect = modal.querySelector('#edit_expense_category');
            const subCategorySelect = modal.querySelector('#edit_expense_subcategory');
            categorySelect.value = button.getAttribute('data-category');
            subCategorySelect.value = button.getAttribute('data-subcategory');
            
            const amountInput = modal.querySelector('#edit_expense_amount');
            const inputVatInput = modal.querySelector('#edit_expense_input_vat');
            amountInput.value = button.getAttribute('data-amount');
            inputVatInput.value = button.getAttribute('data-input-vat') || (amountInput.value * 0.12).toFixed(2);
            amountInput.addEventListener('input', function() {
                inputVatInput.value = (this.value * 0.12).toFixed(2);
            });
            
            modal.querySelector('#edit_expense_method').value = button.getAttribute('data-method');
            modal.querySelector('#edit_expense_notes').value = button.getAttribute('data-notes') || '';
        });

        document.getElementById('addExpenseModal').addEventListener('shown.bs.modal', function() {
            const amountInput = this.querySelector('input[name="amount"]');
            const inputVatInput = this.querySelector('input[name="input_vat"]');
            amountInput.addEventListener('input', function() {
                inputVatInput.value = (this.value * 0.12).toFixed(2);
            });
        });

        document.getElementById('addExpenseModal').addEventListener('hidden.bs.modal', function() {
            this.querySelector('form').reset();
            const inputVatInput = this.querySelector('input[name="input_vat"]');
            inputVatInput.value = '0.00';
        });

        // Select All Checkbox
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.selectRow');
            checkboxes.forEach(checkbox => checkbox.checked = this.checked);
            toggleDeleteButton();
        });

        // Individual Checkbox
        document.querySelectorAll('.selectRow').forEach(checkbox => {
            checkbox.addEventListener('change', toggleDeleteButton);
        });

        function toggleDeleteButton() {
            const deleteButton = document.getElementById('deleteSelected');
            const checkedCheckboxes = document.querySelectorAll('.selectRow:checked');
            deleteButton.disabled = checkedCheckboxes.length === 0;
        }

        // Delete Selected
        document.getElementById('deleteSelected').addEventListener('click', function(e) {
            e.preventDefault();
            if (confirm('Are you sure you want to archive the selected expense records? This action cannot be undone.')) {
                const form = document.getElementById('deleteExpenseForm');
                form.submit();
            }
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>