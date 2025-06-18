<?php
require 'db.php';

if (!($_SESSION['is_admin'] ?? false)) {
    $_SESSION['error'] = "Administrator access required";
    header("Location: dashboard.php");
    exit;
}

$companies_result = $conn->query("SELECT * FROM companies ORDER BY id = 1 DESC, name");
$companies = [];
if ($companies_result) {
    while ($row = $companies_result->fetch_assoc()) {
        $companies[] = $row;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = "Invalid security token";
        header("Location: manage_companies.php");
        exit;
    }

    if (isset($_POST['add_company'])) {
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        
        if (empty($name)) {
            $_SESSION['error'] = "Company name is required";
            header("Location: manage_companies.php");
            exit;
        }
        
        $stmt = $conn->prepare("INSERT INTO companies (name, description) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $description);
        
        if ($stmt->execute()) {
            $company_id = $conn->insert_id;
            setupDefaultAccounts($company_id);
            $_SESSION['success'] = "Company added successfully";
            header("Location: manage_companies.php");
            exit;
        } else {
            $_SESSION['error'] = "Error adding company: " . $conn->error;
            header("Location: manage_companies.php");
            exit;
        }
    }
    
    if (isset($_POST['edit_company'])) {
        $company_id = (int)$_POST['company_id'];
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        
        if (empty($name)) {
            $_SESSION['error'] = "Company name is required";
            header("Location: manage_companies.php");
            exit;
        }
        
        $stmt = $conn->prepare("UPDATE companies SET name = ?, description = ? WHERE id = ?");
        $stmt->bind_param("ssi", $name, $description, $company_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Company updated successfully";
            header("Location: manage_companies.php");
            exit;
        } else {
            $_SESSION['error'] = "Error updating company: " . $conn->error;
            header("Location: manage_companies.php");
            exit;
        }
    }
    
    if (isset($_POST['delete_company'])) {
        $company_id = (int)$_POST['company_id'];
        
        $deletion_check = canDeleteCompany($company_id);
        if (!$deletion_check['can_delete']) {
            $_SESSION['error'] = $deletion_check['reason'];
            header("Location: manage_companies.php");
            exit;
        }
        
        $conn->begin_transaction();
        try {
            $conn->query("DELETE FROM accounts WHERE company_id = $company_id");
            
            $stmt = $conn->prepare("DELETE FROM companies WHERE id = ?");
            $stmt->bind_param("i", $company_id);
            $stmt->execute();
            
            $conn->commit();
            $_SESSION['success'] = "Company deleted successfully";
            header("Location: manage_companies.php");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = "Error deleting company: " . $e->getMessage();
            header("Location: manage_companies.php");
            exit;
        }
    }
}

$csrf_token = generateCsrfToken();
$edit_company = null;
if (isset($_GET['edit'])) {
    $edit_company = getCompanyById((int)$_GET['edit']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Companies</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="./assets/css/manage_companies.css">
</head>
<body>
    <div class="background-pattern"></div>
    
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-building"></i> Manage Companies</h2>
            <a href="dashboard.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
        </div>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?= $_SESSION['error'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= $_SESSION['success'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-lg-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h4 class="mb-0">
                            <i class="bi bi-<?= $edit_company ? 'pencil' : 'plus' ?>-circle"></i> 
                            <?= $edit_company ? 'Edit Company' : 'Add New Company' ?>
                        </h4>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            <?php if ($edit_company): ?>
                                <input type="hidden" name="company_id" value="<?= $edit_company['id'] ?>">
                            <?php endif; ?>
                            <div class="mb-3">
                                <label class="form-label">Company Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" 
                                       value="<?= $edit_company ? htmlspecialchars($edit_company['name']) : '' ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control" rows="2"><?= 
                                    $edit_company ? htmlspecialchars($edit_company['description']) : '' 
                                ?></textarea>
                            </div>
                            <div class="d-flex justify-content-between">
                                <button type="submit" name="<?= $edit_company ? 'edit_company' : 'add_company' ?>" 
                                        class="btn btn-primary">
                                    <i class="bi bi-save"></i> <?= $edit_company ? 'Update' : 'Save' ?> Company
                                </button>
                                <?php if ($edit_company): ?>
                                    <a href="manage_companies.php" class="btn btn-outline-secondary">
                                        Cancel
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0"><i class="bi bi-list-ul"></i> Company List</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Description</th>
                                        <th class="action-btns">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($companies)): ?>
                                        <?php foreach ($companies as $company): 
                                            $deletion_check = canDeleteCompany($company['id']);
                                        ?>
                                        <tr class="<?= $company['id'] == 1 ? 'main-company' : ($deletion_check['can_delete'] ? '' : 'protected-company') ?>">
                                            <td>
                                                <?= htmlspecialchars($company['name']) ?>
                                                <?php if ($company['id'] == 1): ?>
                                                    <span class="badge-main">Main</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($company['description'] ?? '') ?></td>
                                            <td class="action-btns">
                                                <a href="switch_company.php?id=<?= $company['id'] ?>" 
                                                   class="btn btn-sm btn-outline-primary"
                                                   title="Switch to this company">
                                                    <i class="bi bi-arrow-right-circle"></i>
                                                </a>
                                                <a href="manage_companies.php?edit=<?= $company['id'] ?>" 
                                                   class="btn btn-sm btn-outline-success"
                                                   title="Edit this company">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <?php if ($company['id'] != 1): ?>
                                                    <form method="POST" style="display:inline;">
                                                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                        <input type="hidden" name="company_id" value="<?= $company['id'] ?>">
                                                        <button type="submit" name="delete_company" 
                                                                class="btn btn-sm btn-outline-danger <?= $deletion_check['can_delete'] ? '' : 'disabled' ?>"
                                                                title="<?= $deletion_check['can_delete'] ? 'Delete company' : $deletion_check['reason'] ?>"
                                                                onclick="return confirmDelete(<?= $company['id'] ?>, '<?= addslashes($company['name']) ?>')">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="3" class="text-center">No companies found. Add your first company using the form.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmDelete(companyId, companyName) {
            if (companyId === 1) {
                alert('Cannot delete the main company organization');
                return false;
            }
            
            return confirm(`Are you sure you want to permanently delete "${companyName}"?\n\nThis action cannot be undone.`);
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
</body>
</html>