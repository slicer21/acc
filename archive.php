<?php include 'db.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Archived Records</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
  <style>
    body {
      margin: 0;
      min-height: 100vh;
      font-family: 'Poppins', sans-serif;
      background: linear-gradient(180deg, #E6F0FA 0%, #F0F9FF 100%);
      color: #2D3748;
      position: relative;
      overflow-x: hidden;
    }
    .background-pattern {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100" fill="none"><path d="M10 10H90M10 50H90M10 90H90" stroke="#A0AEC0" stroke-opacity="0.2" stroke-width="2"/></svg>') repeat;
      opacity: 0.3;
      z-index: 0;
    }
    .container {
      position: relative;
      z-index: 5;
      padding: 2rem;
      max-width: 1400px;
    }
    h2 {
      color: #2D3748;
      font-weight: 600;
      font-size: 1.75rem;
    }
    .card {
      background: #FFFFFF;
      border: none;
      border-radius: 16px;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
    }
    .filter-section {
      background: #F7FAFC;
      padding: 1.5rem;
      border-radius: 12px;
      margin-bottom: 1.5rem;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }
    .table {
      color: #2D3748;
    }
    .table th {
      color: #4A5568;
      background: #F7FAFC;
      border: none;
      font-weight: 500;
      padding: 1rem;
    }
    .table td {
      background: transparent;
      border-top: 1px solid #E2E8F0;
      padding: 1rem;
      vertical-align: middle;
    }
    .table-hover tbody tr:hover {
      background: #F7FAFC;
    }
    .total-row {
      font-weight: 600;
      background: #F7FAFC;
    }
    .no-records {
      text-align: center;
      padding: 2rem;
      color: #6b7280;
      font-size: 1rem;
    }
    .btn-primary {
      background: #38B2AC;
      border: none;
      border-radius: 10px;
      padding: 0.75rem 1.5rem;
      font-weight: 600;
      transition: all 0.3s ease;
    }
    .btn-primary:hover {
      background: #319795;
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(56, 178, 172, 0.3);
    }
    .btn-danger {
      background: #dc2626;
      border: none;
      border-radius: 10px;
      padding: 0.75rem 1.5rem;
      font-weight: 600;
      transition: all 0.3s ease;
    }
    .btn-danger:hover {
      background: #b91c1c;
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
    }
    .btn-outline-secondary {
      color: #38B2AC;
      border-color: #38B2AC;
      border-radius: 8px;
      padding: 0.5rem 1rem;
      font-weight: 500;
      transition: all 0.3s ease;
    }
    .btn-outline-secondary:hover {
      background: #38B2AC;
      color: #FFFFFF;
      transform: translateY(-2px);
    }
    .form-control, .form-select {
      background: #F7FAFC;
      border: 1px solid #E2E8F0;
      color: #2D3748;
      border-radius: 10px;
      padding: 0.75rem;
      transition: all 0.3s ease;
    }
    .form-control:focus, .form-select:focus {
      background: #FFFFFF;
      border-color: #38B2AC;
      box-shadow: 0 0 8px rgba(56, 178, 172, 0.2);
      color: #2D3748;
    }
    .form-control::placeholder {
      color: #A0AEC0;
    }
    .form-label {
      color: #4A5568;
      font-size: 0.9rem;
      font-weight: 500;
    }
    .nav-tabs {
      border-bottom: none;
      margin-bottom: 0;
      background: #F7FAFC;
      padding: 1rem 1.5rem;
      border-radius: 12px 12px 0 0;
    }
    .nav-tabs .nav-link {
      border: none;
      border-radius: 8px;
      padding: 0.75rem 1.5rem;
      color: #6b7280;
      font-weight: 500;
      transition: all 0.3s ease;
    }
    .nav-tabs .nav-link:hover {
      background: #E2E8F0;
      color: #2D3748;
    }
    .nav-tabs .nav-link.active {
      background: #38B2AC;
      color: #FFFFFF;
      box-shadow: 0 2px 8px rgba(56, 178, 172, 0.2);
    }
    .pagination .page-link {
      border-radius: 8px;
      margin: 0 0.25rem;
      color: #38B2AC;
      border: 1px solid #E2E8F0;
      transition: all 0.3s ease;
    }
    .pagination .page-item.active .page-link {
      background: #38B2AC;
      border-color: #38B2AC;
      color: #FFFFFF;
    }
    .pagination .page-item.disabled .page-link {
      color: #A0AEC0;
      border-color: #E2E8F0;
    }
    @media (max-width: 768px) {
      .container {
        padding: 1rem;
      }
      .filter-section {
        padding: 1rem;
      }
      .filter-section .row {
        flex-direction: column;
      }
      .filter-section .col-md-3 {
        margin-bottom: 1rem;
      }
      .table-responsive {
        font-size: 0.875rem;
      }
    }
  </style>
</head>
<body>
  <div class="background-pattern"></div>

  <?php include 'navbar.php'; ?>

  <div class="container">
    <h2 class="mb-4"><i class="bi bi-archive me-2"></i> Archived Records</h2>
    <div class="card">
      <div class="card-body p-0">
        <?php
        $company_id = $_SESSION['current_company_id'] ?? 1;
        $records_per_page = 10;
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $page = max($page, 1);
        $view_all = isset($_GET['view']) && $_GET['view'] === 'all';
        $offset = ($page - 1) * $records_per_page;
        $tab = isset($_GET['tab']) ? $_GET['tab'] : 'income';
        ?>

        <ul class="nav nav-tabs">
          <li class="nav-item">
            <a class="nav-link <?= $tab === 'income' ? 'active' : '' ?>" href="?tab=income&company_id=<?= $company_id ?>">Archived Income</a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?= $tab === 'expenses' ? 'active' : '' ?>" href="?tab=expenses&company_id=<?= $company_id ?>">Archived Expenses</a>
          </li>
        </ul>

        <div class="p-4">
          <?php if ($tab === 'income'): ?>
            <!-- Archived Income -->
            <?php
            // Fetch available sub categories for filter
            $type_query = "SELECT DISTINCT sub_category FROM archive_income WHERE company_id = ?";
            $type_params = [$company_id];
            $type_types = "i";
            
            if (!empty($_GET['from_date'])) {
                $type_query .= " AND date >= ?";
                $type_params[] = $_GET['from_date'];
                $type_types .= 's';
            }
            if (!empty($_GET['to_date'])) {
                $type_query .= " AND date <= ?";
                $type_params[] = $_GET['to_date'];
                $type_types .= 's';
            }
            
            $type_query .= " ORDER BY sub_category";
            
            $type_stmt = $conn->prepare($type_query);
            $type_stmt->bind_param($type_types, ...$type_params);
            $type_stmt->execute();
            $type_result = $type_stmt->get_result();
            $available_types = [];
            while ($type_row = $type_result->fetch_assoc()) {
                $available_types[] = $type_row['sub_category'];
            }
            $type_stmt->close();

            // Count total records for pagination
            $count_query = "SELECT COUNT(*) as total FROM archive_income WHERE company_id = ?";
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
            if (!empty($_GET['sub_category'])) {
                $count_query .= " AND sub_category = ?";
                $count_params[] = $_GET['sub_category'];
                $count_types .= 's';
            }
            
            $count_stmt = $conn->prepare($count_query);
            $count_stmt->bind_param($count_types, ...$count_params);
            $count_stmt->execute();
            $count_result = $count_stmt->get_result();
            $total_rows = $count_result->fetch_assoc()['total'];
            $total_pages = ceil($total_rows / $records_per_page);
            $count_stmt->close();
            ?>

            <div class="filter-section">
              <form method="GET" class="row g-3">
                <input type="hidden" name="tab" value="income">
                <input type="hidden" name="company_id" value="<?= $company_id ?>">
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
                  <label class="form-label">Sub Category</label>
                  <select name="sub_category" class="form-select">
                    <option value="">All Sub Categories</option>
                    <?php foreach ($available_types as $type): ?>
                      <option value="<?= htmlspecialchars($type) ?>" 
                        <?= ($_GET['sub_category'] ?? '') == $type ? 'selected' : '' ?>>
                        <?= htmlspecialchars($type) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                  <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-funnel"></i> Filter
                  </button>
                </div>
              </form>
            </div>

            <form id="delete-income-form" action="process_archive.php" method="POST">
              <input type="hidden" name="action" value="delete_income">
              <input type="hidden" name="company_id" value="<?= $company_id ?>">
              <div class="d-flex justify-content-between mb-3">
                <div>
                  <?php if ($view_all): ?>
                    <a href="?tab=income&company_id=<?= $company_id ?>&from_date=<?= urlencode($_GET['from_date'] ?? '') ?>&to_date=<?= urlencode($_GET['to_date'] ?? '') ?>&sub_category=<?= urlencode($_GET['sub_category'] ?? '') ?>" class="btn btn-outline-secondary btn-sm">
                      <i class="bi bi-list"></i> Show Paginated
                    </a>
                  <?php else: ?>
                    <a href="?tab=income&company_id=<?= $company_id ?>&view=all&from_date=<?= urlencode($_GET['from_date'] ?? '') ?>&to_date=<?= urlencode($_GET['to_date'] ?? '') ?>&sub_category=<?= urlencode($_GET['sub_category'] ?? '') ?>" class="btn btn-outline-secondary btn-sm">
                      <i class="bi bi-list"></i> See All
                    </a>
                  <?php endif; ?>
                </div>
                <button type="submit" class="btn btn-danger btn-sm" id="delete-income-btn" disabled>
                  <i class="bi bi-trash"></i> Permanently Delete Selected
                </button>
              </div>

              <div class="table-responsive">
                <table class="table table-hover">
                  <thead>
                    <tr>
                      <th><input type="checkbox" id="select-all-income"></th>
                      <th>Date</th>
                      <th>Reference No.</th>
                      <th>Source</th>
                      <th>Sub Category</th>
                      <th>Amount</th>
                      <th>Payment Method</th>
                      <th>Archived At</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php
                    $query = "SELECT * FROM archive_income WHERE company_id = ?";
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
                    if (!empty($_GET['sub_category'])) {
                        $query .= " AND sub_category = ?";
                        $params[] = $_GET['sub_category'];
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
                    $stmt->bind_param($types, ...$params);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        $total_amount = 0;
                        while ($row = $result->fetch_assoc()) {
                            $total_amount += $row['amount'];
                    ?>
                    <tr>
                      <td><input type="checkbox" name="ids[]" value="<?= $row['id'] ?>" class="record-checkbox"></td>
                      <td><?= htmlspecialchars($row['date']) ?></td>
                      <td><?= htmlspecialchars($row['reference_no'] ?? '') ?></td>
                      <td><?= htmlspecialchars($row['source']) ?></td>
                      <td><?= htmlspecialchars($row['sub_category']) ?></td>
                      <td><?= number_format($row['amount'], 2) ?></td>
                      <td><?= htmlspecialchars($row['payment_method']) ?></td>
                      <td><?= htmlspecialchars($row['archived_at']) ?></td>
                    </tr>
                    <?php
                        }
                    ?>
                    <tr class="total-row">
                      <td></td>
                      <td colspan="4" class="text-end">Total:</td>
                      <td><?= number_format($total_amount, 2) ?></td>
                      <td colspan="2"></td>
                    </tr>
                    <?php
                    } else {
                    ?>
                    <tr>
                      <td colspan="8" class="no-records">
                        <i class="bi bi-exclamation-circle"></i> No archived income records found matching your criteria
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
              <nav aria-label="Archived income pagination" class="mt-4">
                <ul class="pagination justify-content-center">
                  <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?tab=income&company_id=<?= $company_id ?>&page=<?= $page - 1 ?>&from_date=<?= urlencode($_GET['from_date'] ?? '') ?>&to_date=<?= urlencode($_GET['to_date'] ?? '') ?>&sub_category=<?= urlencode($_GET['sub_category'] ?? '') ?>" aria-label="Previous">
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
                      <a class="page-link" href="?tab=income&company_id=<?= $company_id ?>&page=<?= $i ?>&from_date=<?= urlencode($_GET['from_date'] ?? '') ?>&to_date=<?= urlencode($_GET['to_date'] ?? '') ?>&sub_category=<?= urlencode($_GET['sub_category'] ?? '') ?>"><?= $i ?></a>
                    </li>
                  <?php endfor; ?>
                  <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?tab=income&company_id=<?= $company_id ?>&page=<?= $page + 1 ?>&from_date=<?= urlencode($_GET['from_date'] ?? '') ?>&to_date=<?= urlencode($_GET['to_date'] ?? '') ?>&sub_category=<?= urlencode($_GET['sub_category'] ?? '') ?>" aria-label="Next">
                      <span aria-hidden="true">»</span>
                    </a>
                  </li>
                </ul>
              </nav>
            <?php endif; ?>

          <?php else: ?>
            <!-- Archived Expenses -->
            <?php
            // Fetch available categories for filter
            $category_query = "SELECT DISTINCT category FROM archive_expenses WHERE company_id = ?";
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
            $category_stmt->bind_param($category_types, ...$category_params);
            $category_stmt->execute();
            $category_result = $category_stmt->get_result();
            $available_categories = [];
            while ($category_row = $category_result->fetch_assoc()) {
                $available_categories[] = $category_row['category'];
            }
            $category_stmt->close();

            // Count total records for pagination
            $count_query = "SELECT COUNT(*) as total FROM archive_expenses WHERE company_id = ?";
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
            
            $count_stmt = $conn->prepare($count_query);
            $count_stmt->bind_param($count_types, ...$count_params);
            $count_stmt->execute();
            $count_result = $count_stmt->get_result();
            $total_rows = $count_result->fetch_assoc()['total'];
            $total_pages = ceil($total_rows / $records_per_page);
            $count_stmt->close();
            ?>

            <div class="filter-section">
              <form method="GET" class="row g-3">
                <input type="hidden" name="tab" value="expenses">
                <input type="hidden" name="company_id" value="<?= $company_id ?>">
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
                <div class="col-md-3 d-flex align-items-end">
                  <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-funnel"></i> Filter
                  </button>
                </div>
              </form>
            </div>

            <form id="delete-expenses-form" action="process_archive.php" method="POST">
              <input type="hidden" name="action" value="delete_expenses">
              <input type="hidden" name="company_id" value="<?= $company_id ?>">
              <div class="d-flex justify-content-between mb-3">
                <div>
                  <?php if ($view_all): ?>
                    <a href="?tab=expenses&company_id=<?= $company_id ?>&from_date=<?= urlencode($_GET['from_date'] ?? '') ?>&to_date=<?= urlencode($_GET['to_date'] ?? '') ?>&category=<?= urlencode($_GET['category'] ?? '') ?>" class="btn btn-outline-secondary btn-sm">
                      <i class="bi bi-list"></i> Show Paginated
                    </a>
                  <?php else: ?>
                    <a href="?tab=expenses&company_id=<?= $company_id ?>&view=all&from_date=<?= urlencode($_GET['from_date'] ?? '') ?>&to_date=<?= urlencode($_GET['to_date'] ?? '') ?>&category=<?= urlencode($_GET['category'] ?? '') ?>" class="btn btn-outline-secondary btn-sm">
                      <i class="bi bi-list"></i> See All
                    </a>
                  <?php endif; ?>
                </div>
                <button type="submit" class="btn btn-danger btn-sm" id="delete-expenses-btn" disabled>
                  <i class="bi bi-trash"></i> Permanently Delete Selected
                </button>
              </div>

              <div class="table-responsive">
                <table class="table table-hover">
                  <thead>
                    <tr>
                      <th><input type="checkbox" id="select-all-expenses"></th>
                      <th>Date</th>
                      <th>Receipt No.</th>
                      <th>Vendor</th>
                      <th>Supplier</th>
                      <th>Supplier TIN</th>
                      <th>Explanation</th>
                      <th>Category</th>
                      <th>Sub Category</th>
                      <th>Amount</th>
                      <th>Payment Method</th>
                      <th>Archived At</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php
                    $query = "SELECT * FROM archive_expenses WHERE company_id = ?";
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
                    
                    $query .= " ORDER BY date DESC, id DESC";
                    if (!$view_all) {
                        $query .= " LIMIT ? OFFSET ?";
                        $params[] = $records_per_page;
                        $params[] = $offset;
                        $types .= 'ii';
                    }
                    
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param($types, ...$params);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        $total_amount = 0;
                        while ($row = $result->fetch_assoc()) {
                            $total_amount += $row['amount'];
                    ?>
                    <tr>
                      <td><input type="checkbox" name="ids[]" value="<?= $row['id'] ?>" class="record-checkbox"></td>
                      <td><?= htmlspecialchars($row['date']) ?></td>
                      <td><?= htmlspecialchars($row['receipt_no'] ?? '') ?></td>
                      <td><?= htmlspecialchars($row['vendor_name']) ?></td>
                      <td><?= htmlspecialchars($row['supplier'] ?? '') ?></td>
                      <td><?= htmlspecialchars($row['supplier_tin'] ?? '') ?></td>
                      <td><?= htmlspecialchars($row['explanation'] ?? '') ?></td>
                      <td><?= htmlspecialchars($row['category']) ?></td>
                      <td><?= htmlspecialchars($row['sub_category']) ?></td>
                      <td><?= number_format($row['amount'], 2) ?></td>
                      <td><?= htmlspecialchars($row['payment_method']) ?></td>
                      <td><?= htmlspecialchars($row['archived_at']) ?></td>
                    </tr>
                    <?php
                        }
                    ?>
                    <tr class="total-row">
                      <td></td>
                      <td colspan="8" class="text-end">Total:</td>
                      <td><?= number_format($total_amount, 2) ?></td>
                      <td colspan="2"></td>
                    </tr>
                    <?php
                    } else {
                    ?>
                    <tr>
                      <td colspan="12" class="no-records">
                        <i class="bi bi-exclamation-circle"></i> No archived expense records found matching your criteria
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
              <nav aria-label="Archived expense pagination" class="mt-4">
                <ul class="pagination justify-content-center">
                  <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?tab=expenses&company_id=<?= $company_id ?>&page=<?= $page - 1 ?>&from_date=<?= urlencode($_GET['from_date'] ?? '') ?>&to_date=<?= urlencode($_GET['to_date'] ?? '') ?>&category=<?= urlencode($_GET['category'] ?? '') ?>" aria-label="Previous">
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
                      <a class="page-link" href="?tab=expenses&company_id=<?= $company_id ?>&page=<?= $i ?>&from_date=<?= urlencode($_GET['from_date'] ?? '') ?>&to_date=<?= urlencode($_GET['to_date'] ?? '') ?>&category=<?= urlencode($_GET['category'] ?? '') ?>"><?= $i ?></a>
                    </li>
                  <?php endfor; ?>
                  <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?tab=expenses&company_id=<?= $company_id ?>&page=<?= $page + 1 ?>&from_date=<?= urlencode($_GET['from_date'] ?? '') ?>&to_date=<?= urlencode($_GET['to_date'] ?? '') ?>&category=<?= urlencode($_GET['category'] ?? '') ?>" aria-label="Next">
                      <span aria-hidden="true">»</span>
                    </a>
                  </li>
                </ul>
              </nav>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Function to handle checkbox selection and button state
    function setupCheckboxHandlers(formId, selectAllId, deleteBtnId) {
      const selectAll = document.getElementById(selectAllId);
      const checkboxes = document.querySelectorAll(`${formId} .record-checkbox`);
      const deleteBtn = document.getElementById(deleteBtnId);

      // Toggle all checkboxes
      selectAll.addEventListener('change', function () {
        checkboxes.forEach(checkbox => {
          checkbox.checked = this.checked;
        });
        updateDeleteButton();
      });

      // Update delete button state
      function updateDeleteButton() {
        const anyChecked = Array.from(checkboxes).some(checkbox => checkbox.checked);
        deleteBtn.disabled = !anyChecked;
      }

      // Update button state on individual checkbox change
      checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function () {
          selectAll.checked = Array.from(checkboxes).every(checkbox => checkbox.checked);
          updateDeleteButton();
        });
      });

      // Confirmation dialog on form submit
      document.querySelector(formId).addEventListener('submit', function (e) {
        const checkedCount = Array.from(checkboxes).filter(checkbox => checkbox.checked).length;
        if (checkedCount > 0) {
          if (!confirm(`Are you sure you want to permanently delete ${checkedCount} selected record(s)? This action cannot be undone.`)) {
            e.preventDefault();
          }
        } else {
          e.preventDefault();
          alert('Please select at least one record to delete.');
        }
      });
    }

    // Initialize for income and expenses forms
    setupCheckboxHandlers('#delete-income-form', 'select-all-income', 'delete-income-btn');
    setupCheckboxHandlers('#delete-expenses-form', 'select-all-expenses', 'delete-expenses-btn');
  </script>
</body>
</html>
<?php $conn->close(); ?>