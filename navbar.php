<?php
// Check if user is logged in (replace with your actual authentication check)
$logged_in = isset($_SESSION['user_id']);
$current_company_id = $_SESSION['current_company_id'] ?? 1;
$current_company_name = $_SESSION['current_company_name'] ?? 'Main Church';
$base_url = "http://" . $_SERVER['SERVER_ADDR'] . ":8080/acc/"; // Adjusted for port and subdirectory
?>

<nav class="navbar navbar-expand-lg">
  <div class="container">
    <a class="navbar-brand" href="<?php echo $base_url; ?>dashboard.php">
      <img src="<?php echo $base_url; ?>assets/images/logo.png" alt="Accounting Logo" class="navbar-logo">
      Accounting
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav me-auto">
        <li class="nav-item">
          <a class="nav-link" href="<?php echo $base_url; ?>dashboard.php"><i class="bi bi-house-door"></i> Dashboard</a>
        </li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
            <i class="bi bi-files"></i> Records
          </a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="<?php echo $base_url; ?>income.php"><i class="bi bi-cash-coin"></i> Income</a></li>
            <li><a class="dropdown-item" href="<?php echo $base_url; ?>expenses.php"><i class="bi bi-receipt"></i> Expenses</a></li>
            <li><a class="dropdown-item" href="<?php echo $base_url; ?>archive.php"><i class="bi bi-archive"></i> Archived Records</a></li>
          </ul>
        </li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
            <i class="bi bi-graph-up"></i> Reports
          </a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="<?php echo $base_url; ?>reports/profit_loss.php?company_id=<?= $current_company_id ?>">Profit & Loss</a></li>
            <li><a class="dropdown-item" href="<?php echo $base_url; ?>reports/balance_sheet.php?company_id=<?= $current_company_id ?>">Balance Sheet</a></li>
            <li><a class="dropdown-item" href="<?php echo $base_url; ?>reports/cash_flow.php?company_id=<?= $current_company_id ?>">Cash Flow</a></li>
          </ul>
        </li>
      </ul>
      <ul class="navbar-nav">
        <?php if ($logged_in): ?>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
            <i class="bi bi-building"></i> <?= $current_company_name ?>
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <?php
            $companies = $conn->query("SELECT * FROM companies ORDER BY name");
            while ($company = $companies->fetch_assoc()): ?>
            <li>
              <a class="dropdown-item <?= $company['id'] == $current_company_id ? 'active' : '' ?>" 
                 href="<?php echo $base_url; ?>switch_company.php?id=<?= $company['id'] ?>">
                <?= $company['name'] ?>
                <?php if ($company['id'] == $current_company_id): ?>
                <i class="bi bi-check2 float-end"></i>
                <?php endif; ?>
              </a>
            </li>
            <?php endwhile; ?>
          </ul>
        </li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
            <i class="bi bi-person-circle"></i> Admin
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <?php if ($_SESSION['is_admin'] ?? false): ?>
            <li><a class="dropdown-item" href="<?php echo $base_url; ?>manage_companies.php"><i class="bi bi-building"></i> Manage Company</a></li>
            <?php endif; ?>
            <li><a class="dropdown-item" href="<?php echo $base_url; ?>settings.php"><i class="bi bi-gear"></i> Settings</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="<?php echo $base_url; ?>logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
          </ul>
        </li>
        <?php else: ?>
        <li class="nav-item">
          <a class="nav-link" href="<?php echo $base_url; ?>login.php"><i class="bi bi-box-arrow-in-right"></i> Login</a>
        </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<?php
if (isset($_SESSION['success'])) {
    echo '<div class="alert alert-success alert-dismissible fade show m-0 rounded-0" role="alert">
            ' . $_SESSION['success'] . '
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>';
    unset($_SESSION['success']);
}

if (isset($_SESSION['error'])) {
    echo '<div class="alert alert-danger alert-dismissible fade show m-0 rounded-0" role="alert">
            ' . $_SESSION['error'] . '
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>';
    unset($_SESSION['error']);
}

if (isset($_SESSION['expense_message'])) {
    $alert_class = $_SESSION['expense_message']['success'] ? 'alert-success' : 'alert-danger';
    echo '<div class="alert ' . $alert_class . ' alert-dismissible fade show m-0 rounded-0" role="alert">
            ' . $_SESSION['expense_message']['message'] . '
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>';
    unset($_SESSION['expense_message']);
}
?>

<style>
    .navbar {
        background: linear-gradient(to right, #ffffff, #f8fafc);
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
        position: sticky;
        top: 0;
        z-index: 10;
        font-family: 'Inter', 'Poppins', sans-serif;
        padding: 0.8rem 0;
    }
    .navbar-logo {
        height: 42px;
        margin-right: 12px;
        vertical-align: middle;
        transition: transform 0.3s ease;
    }
    .navbar-logo:hover {
        transform: scale(1.05);
    }
    .navbar-brand {
        color: #1a202c;
        font-weight: 700;
        font-size: 1.35rem;
        letter-spacing: -0.5px;
        transition: all 0.3s ease;
    }
    .navbar-brand:hover {
        color: #2563eb;
    }
    .nav-link {
        color: #4a5568;
        padding: 0.6rem 1.2rem;
        transition: all 0.2s ease;
        border-radius: 12px;
        font-weight: 500;
        position: relative;
    }
    .nav-link:hover {
        color: #2563eb;
        background: rgba(37, 99, 235, 0.08);
    }
    .nav-link.active {
        color: #2563eb;
        background: rgba(37, 99, 235, 0.12);
    }
    .dropdown-toggle::after {
        margin-left: 8px;
        transition: transform 0.2s ease;
    }
    .dropdown-toggle:hover::after {
        transform: rotate(180deg);
    }
    .dropdown-menu {
        background: #ffffff;
        border: none;
        border-radius: 16px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        padding: 0.8rem;
        margin-top: 0.5rem;
        min-width: 220px;
    }
    .dropdown-item {
        color: #4a5568;
        padding: 0.7rem 1rem;
        border-radius: 10px;
        transition: all 0.2s ease;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .dropdown-item:hover {
        background: rgba(37, 99, 235, 0.08);
        color: #2563eb;
        transform: translateX(4px);
    }
    .dropdown-item.active {
        background: #2563eb;
        color: #ffffff;
    }
    .dropdown-divider {
        border-top: 1px solid #e2e8f0;
        margin: 0.5rem 0;
    }
    .navbar-toggler {
        border: none;
        padding: 0.5rem;
        border-radius: 12px;
        transition: all 0.2s ease;
    }
    .navbar-toggler:hover {
        background: rgba(37, 99, 235, 0.08);
    }
    .navbar-toggler-icon {
        background-image: url("data:image/svg+xml;charset=utf8,%3Csvg viewBox='0 0 30 30' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath stroke='rgba(37, 99, 235, 0.8)' stroke-width='2' stroke-linecap='round' stroke-miterlimit='10' d='M4 7h22M4 15h22M4 23h22'/%3E%3C/svg%3E");
    }
    .alert {
        background: #ffffff;
        border: none;
        color: #1a202c;
        border-radius: 16px;
        padding: 1rem 1.5rem;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        margin: 1rem 0;
    }
    .alert-success {
        background: #f0fdf4;
        border-left: 4px solid #22c55e;
    }
    .alert-danger {
        background: #fef2f2;
        border-left: 4px solid #ef4444;
    }
    .btn-close {
        opacity: 0.5;
        transition: all 0.2s ease;
        padding: 0.8rem;
    }
    .btn-close:hover {
        opacity: 1;
        transform: rotate(90deg);
    }
    @media (max-width: 768px) {
        .navbar {
            padding: 0.6rem 0;
        }
        .navbar-logo {
            height: 36px;
        }
        .navbar-brand {
            font-size: 1.2rem;
        }
        .nav-link {
            padding: 0.8rem 1rem;
        }
        .dropdown-menu {
            border-radius: 12px;
            margin-top: 0.3rem;
        }
    }
</style>