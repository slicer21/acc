<?php
require 'db.php';

// Check permissions (you would implement your own authentication)
$is_admin = true; // Replace with actual admin check
if (!$is_admin) {
    header("Location: dashboard.php");
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_accounts'])) {
        // Update chart of accounts
        foreach ($_POST['accounts'] as $id => $account) {
            $stmt = $conn->prepare("UPDATE accounts SET account_name = ?, account_type = ? WHERE id = ?");
            $stmt->bind_param("ssi", $account['name'], $account['type'], $id);
            $stmt->execute();
        }
        
        $_SESSION['success'] = "Chart of accounts updated successfully!";
        header("Location: settings.php");
        exit;
    }
    
    if (isset($_POST['add_account'])) {
        // Add new account
        $code = $_POST['account_code'];
        $name = $_POST['account_name'];
        $type = $_POST['account_type'];
        
        $stmt = $conn->prepare("INSERT INTO accounts (account_code, account_name, account_type) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $code, $name, $type);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Account added successfully!";
        } else {
            $_SESSION['error'] = "Error adding account: " . $conn->error;
        }
        
        header("Location: settings.php");
        exit;
    }
    
    if (isset($_POST['delete_account'])) {
        $delete_id = intval($_POST['delete_account']);
        $stmt = $conn->prepare("DELETE FROM accounts WHERE id = ?");
        $stmt->bind_param("i", $delete_id);
        if ($stmt->execute()) {
            $_SESSION['success'] = "Account deleted successfully!";
        } else {
            $_SESSION['error'] = "Error deleting account: " . $conn->error;
        }
        header("Location: settings.php");
        exit;
    }
}

// Get all accounts
$accounts = $conn->query("SELECT * FROM accounts ORDER BY account_code");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>System Settings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
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
        }
        h2, h5 {
            color: #2D3748;
            font-weight: 600;
        }
        h2 {
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
        .card-header {
            background: #F7FAFC;
            border-bottom: 1px solid #E2E8F0;
            color: #2D3748;
            font-weight: 600;
            padding: 1rem 1.5rem;
            border-radius: 16px 16px 0 0;
        }
        .card-body {
            padding: 1.5rem;
        }
        .table {
            color: #2D3748;
        }
        .table th {
            color: #4A5568;
            background: #F7FAFC;
            border: none;
            font-weight: 500;
        }
        .table td {
            background: transparent;
            border-top: 1px solid #E2E8F0;
        }
        .table-hover tbody tr:hover {
            background: #F7FAFC;
        }
        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #E2E8F0;
            transition: all 0.3s ease;
        }
        .form-control:focus, .form-select:focus {
            border-color: #38B2AC;
            box-shadow: 0 0 0 0.2rem rgba(56, 178, 172, 0.25);
            outline: none;
        }
        .form-label {
            color: #4A5568;
            font-weight: 500;
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
        .btn-success {
            background: #38B2AC;
            border: none;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        .btn-success:hover {
            background: #319795;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(56, 178, 172, 0.3);
        }
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            h2 {
                font-size: 1.5rem;
            }
            .btn {
                padding: 0.5rem 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="background-pattern"></div>

    <?php include 'navbar.php'; ?>

    <div class="container mt-4">
        <h2 class="mb-4"><i class="bi bi-gear"></i> System Settings</h2>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Chart of Accounts</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Account Name</th>
                                        <th>Type</th>
                                        <th>Delete</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($account = $accounts->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= $account['account_code'] ?></td>
                                        <td>
                                            <input type="text" name="accounts[<?= $account['id'] ?>][name]" 
                                                   value="<?= $account['account_name'] ?>" class="form-control">
                                        </td>
                                        <td>
                                            <select name="accounts[<?= $account['id'] ?>][type]" class="form-select">
                                                <option value="Asset" <?= $account['account_type'] == 'Asset' ? 'selected' : '' ?>>Asset</option>
                                                <option value="Liability" <?= $account['account_type'] == 'Liability' ? 'selected' : '' ?>>Liability</option>
                                                <option value="Equity" <?= $account['account_type'] == 'Equity' ? 'selected' : '' ?>>Equity</option>
                                                <option value="Revenue" <?= $account['account_type'] == 'Revenue' ? 'selected' : '' ?>>Revenue</option>
                                                <option value="Expense" <?= $account['account_type'] == 'Expense' ? 'selected' : '' ?>>Expense</option>
                                            </select>
                                        </td>
                                        <td>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this account?');">
                                                <input type="hidden" name="delete_account" value="<?= $account['id'] ?>">
                                                <button type="submit" class="btn btn-danger btn-sm" title="Delete Account">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                            <button type="submit" name="update_accounts" class="btn btn-primary">
                                <i class="bi bi-save"></i> Update Accounts
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Add New Account</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Account Code</label>
                                <input type="text" name="account_code" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Account Name</label>
                                <input type="text" name="account_name" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Account Type</label>
                                <select name="account_type" class="form-select" required>
                                    <option value="Asset">Asset</option>
                                    <option value="Liability">Liability</option>
                                    <option value="Equity">Equity</option>
                                    <option value="Revenue">Revenue</option>
                                    <option value="Expense">Expense</option>
                                </select>
                            </div>
                            <button type="submit" name="add_account" class="btn btn-success">
                                <i class="bi bi-plus-circle"></i> Add Account
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Add confirmation for Add Account form
    document.addEventListener('DOMContentLoaded', function() {
      var addForm = document.querySelector('form button[name="add_account"]')?.closest('form');
      if (addForm) {
        addForm.addEventListener('submit', function(e) {
          if (!confirm('Are you sure you want to add this account?')) {
            e.preventDefault();
          }
        });
      }
    });
    </script>
</body>
</html>