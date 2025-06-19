<?php
require 'db.php';
require __DIR__ . '/vendor/autoload.php';

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if (isset($_GET['type'])) {
    $company_id = (int)($_SESSION['current_company_id'] ?? ($_GET['company_id'] ?? 1));
    $year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

    // Validate year
    if ($year < 1900 || $year > 9999) {
        $year = date('Y');
    }

    $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : (isset($_GET['from_date']) ? $_GET['from_date'] : "$year-01-01");
    $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : (isset($_GET['to_date']) ? $_GET['to_date'] : "$year-12-31");

    // Validate dates
    $startDateObj = DateTime::createFromFormat('Y-m-d', $start_date);
    $endDateObj = DateTime::createFromFormat('Y-m-d', $end_date);
    $currentYear = date('Y');

    if (!$startDateObj || !$endDateObj || $startDateObj > $endDateObj || 
        $startDateObj->format('Y') > $currentYear || $endDateObj->format('Y') > $currentYear) {
        $start_date = "$year-01-01";
        $end_date = "$year-12-31";
    }

    try {
        $spreadsheet = new Spreadsheet();
        $date_range = ($start_date === "$year-01-01" && $end_date === "$year-12-31")
            ? $year
            : (new DateTime($start_date))->format('F j') . " to " . (new DateTime($end_date))->format('F j, Y');

        switch ($_GET['type']) {
            case 'income':
                $sheet = $spreadsheet->getActiveSheet();
                $sheet->setTitle('Income Records');
                $sheet->setCellValue('A1', "INCOME RECORDS - $date_range")->getStyle('A1')->getFont()->setBold(true);
                $sheet->mergeCells('A1:H1');
                
                // Headers
                $headers = ['Date', 'Invoice No.', 'Payor', 'Sub Category', 'Amount', 'Output VAT (12%)', 'Payment Method', 'Notes'];
                $sheet->fromArray($headers, null, 'A2');
                $sheet->getStyle('A2:H2')->getFont()->setBold(true);

                // Build query
                $query = "SELECT date, invoice_no, payor, sub_category, amount, output_vat, payment_method, notes 
                          FROM income WHERE company_id = ?";
                $params = [$company_id];
                $types = "i";

                // Date range
                $query .= " AND date BETWEEN ? AND ?";
                $params[] = $start_date;
                $params[] = $end_date;
                $types .= 'ss';

                // Sub-category filter
                if (!empty($_GET['sub_category'])) {
                    $query .= " AND sub_category = ?";
                    $params[] = $_GET['sub_category'];
                    $types .= 's';
                }

                // Search filter
                if (!empty($_GET['search'])) {
                    $query .= " AND payor LIKE ?";
                    $params[] = '%' . $_GET['search'] . '%';
                    $types .= 's';
                }

                $query .= " ORDER BY date DESC";

                // Execute query
                $stmt = $conn->prepare($query);
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }

                if (!empty($params)) {
                    $stmt->bind_param($types, ...$params);
                }

                if (!$stmt->execute()) {
                    throw new Exception("Execute failed: " . $stmt->error);
                }

                $result = $stmt->get_result();
                $row = 3;
                $total_amount = 0;
                $total_vat = 0;
                
                if ($result->num_rows === 0) {
                    $sheet->setCellValue('A3', 'No income records found');
                    $sheet->mergeCells('A3:H3');
                } else {
                    while ($data = $result->fetch_assoc()) {
                        $sheet->fromArray([
                            $data['date'],
                            $data['invoice_no'] ?? '',
                            $data['payor'],
                            $data['sub_category'],
                            (float)$data['amount'],
                            (float)$data['output_vat'],
                            $data['payment_method'],
                            $data['notes'] ?? ''
                        ], null, "A{$row}");
                        
                        $total_amount += $data['amount'];
                        $total_vat += $data['output_vat'];
                        $row++;
                    }

                    // Add totals row
                    $sheet->setCellValue("A{$row}", 'Total')->getStyle("A{$row}")->getFont()->setBold(true);
                    $sheet->setCellValue("E{$row}", $total_amount)->getStyle("E{$row}")->getFont()->setBold(true);
                    $sheet->setCellValue("F{$row}", $total_vat)->getStyle("F{$row}")->getFont()->setBold(true);
                }

                // Format numbers
                $sheet->getStyle('E3:F'.$row)->getNumberFormat()->setFormatCode('#,##0.00');
                
                // Auto-size columns
                foreach (range('A', 'H') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }

                $filename = "income_records_{$date_range}.xlsx";
                $stmt->close();
                break;

            case 'expense':
                $sheet = $spreadsheet->getActiveSheet();
                $sheet->setTitle('Expense Records');
                $sheet->setCellValue('A1', "EXPENSE RECORDS - $date_range")->getStyle('A1')->getFont()->setBold(true);
                $sheet->mergeCells('A1:L1');
                
                // Headers
                $headers = ['Date', 'Receipt No.', 'Vendor', 'Supplier', 'Supplier TIN', 'Explanation', 
                          'Category', 'Sub Category', 'Amount', 'Input VAT (12%)', 'Payment Method', 'Notes'];
                $sheet->fromArray($headers, null, 'A2');
                $sheet->getStyle('A2:L2')->getFont()->setBold(true);

                // Build query
                $query = "SELECT date, receipt_no, vendor_name, supplier, supplier_tin, explanation, 
                                 category, sub_category, amount, input_vat, payment_method, notes 
                          FROM expenses WHERE company_id = ?";
                $params = [$company_id];
                $types = "i";

                // Date range
                $query .= " AND date BETWEEN ? AND ?";
                $params[] = $start_date;
                $params[] = $end_date;
                $types .= 'ss';

                // Category filter
                if (!empty($_GET['category'])) {
                    $query .= " AND category = ?";
                    $params[] = $_GET['category'];
                    $types .= 's';
                }

                // Search filter
                if (!empty($_GET['search'])) {
                    $query .= " AND vendor_name LIKE ?";
                    $params[] = '%' . $_GET['search'] . '%';
                    $types .= 's';
                }

                $query .= " ORDER BY date DESC";

                // Execute query
                $stmt = $conn->prepare($query);
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }

                if (!empty($params)) {
                    $stmt->bind_param($types, ...$params);
                }

                if (!$stmt->execute()) {
                    throw new Exception("Execute failed: " . $stmt->error);
                }

                $result = $stmt->get_result();
                $row = 3;
                $total_amount = 0;
                $total_vat = 0;
                
                if ($result->num_rows === 0) {
                    $sheet->setCellValue('A3', 'No expense records found');
                    $sheet->mergeCells('A3:L3');
                } else {
                    while ($data = $result->fetch_assoc()) {
                        $input_vat = $data['input_vat'] ?? ($data['amount'] * 0.12);
                        $sheet->fromArray([
                            $data['date'],
                            $data['receipt_no'] ?? '',
                            $data['vendor_name'],
                            $data['supplier'] ?? '',
                            $data['supplier_tin'] ?? '',
                            $data['explanation'] ?? '',
                            $data['category'],
                            $data['sub_category'],
                            (float)$data['amount'],
                            (float)$input_vat,
                            $data['payment_method'],
                            $data['notes'] ?? ''
                        ], null, "A{$row}");
                        
                        $total_amount += $data['amount'];
                        $total_vat += $input_vat;
                        $row++;
                    }

                    // Add totals row
                    $sheet->setCellValue("A{$row}", 'Total')->getStyle("A{$row}")->getFont()->setBold(true);
                    $sheet->setCellValue("I{$row}", $total_amount)->getStyle("I{$row}")->getFont()->setBold(true);
                    $sheet->setCellValue("J{$row}", $total_vat)->getStyle("J{$row}")->getFont()->setBold(true);
                }

                // Format numbers
                $sheet->getStyle('I3:J'.$row)->getNumberFormat()->setFormatCode('#,##0.00');
                
                // Auto-size columns
                foreach (range('A', 'L') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }

                $filename = "expense_records_{$date_range}.xlsx";
                $stmt->close();
                break;

            case 'cash_flow':
                $sheet = $spreadsheet->getActiveSheet();
                $sheet->setTitle('Cash Flow');
                $sheet->setCellValue('A1', "CASH FLOW STATEMENT - $date_range")->getStyle('A1')->getFont()->setBold(true);
                $sheet->mergeCells('A1:B1');
                $sheet->setCellValue('A3', 'CASH INFLOWS')->getStyle('A3')->getFont()->setBold(true);

                // Cash Inflows
                $inflows = $conn->query("
                    SELECT sub_category, SUM(amount) as total 
                    FROM income 
                    WHERE payment_method IN ('Cash', 'Bank Transfer') 
                    AND company_id = $company_id
                    AND date BETWEEN '$start_date' AND '$end_date'
                    GROUP BY sub_category
                ");

                $row = 4;
                $total_inflows = 0;
                while ($data = $inflows->fetch_assoc()) {
                    $sheet->setCellValue("A{$row}", $data['sub_category']);
                    $sheet->setCellValue("B{$row}", (float)$data['total']);
                    $total_inflows += $data['total'];
                    $row++;
                }

                $sheet->setCellValue("A{$row}", 'Total Inflows')->getStyle("A{$row}")->getFont()->setBold(true);
                $sheet->setCellValue("B{$row}", (float)$total_inflows)->getStyle("B{$row}")->getFont()->setBold(true);
                $sheet->getStyle("B4:B{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
                $row += 2;

                // Cash Outflows
                $sheet->setCellValue("A{$row}", 'CASH OUTFLOWS')->getStyle("A{$row}")->getFont()->setBold(true);
                $row++;

                $outflows = $conn->query("
                    SELECT CONCAT(category, ' - ', sub_category) as description, 
                           SUM(amount) as total 
                    FROM expenses 
                    WHERE payment_method IN ('Cash', 'Bank Transfer') 
                    AND company_id = $company_id
                    AND date BETWEEN '$start_date' AND '$end_date'
                    GROUP BY category, sub_category
                ");

                $total_outflows = 0;
                while ($data = $outflows->fetch_assoc()) {
                    $sheet->setCellValue("A{$row}", $data['description']);
                    $sheet->setCellValue("B{$row}", (float)$data['total']);
                    $total_outflows += $data['total'];
                    $row++;
                }

                $sheet->setCellValue("A{$row}", 'Total Outflows')->getStyle("A{$row}")->getFont()->setBold(true);
                $sheet->setCellValue("B{$row}", (float)$total_outflows)->getStyle("B{$row}")->getFont()->setBold(true);
                $sheet->getStyle("B4:B{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
                $row += 2;

                // Net Cash Flow
                $net_cash = $total_inflows - $total_outflows;
                $sheet->setCellValue("A{$row}", 'NET CASH FLOW')->getStyle("A{$row}")->getFont()->setBold(true);
                $sheet->setCellValue("B{$row}", (float)$net_cash)->getStyle("B{$row}")->getFont()->setBold(true);
                $sheet->getStyle("B{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
                $row += 2;

                // Cash Balances
                $cash_balance = $conn->query("
                    SELECT 
                        (SELECT IFNULL(SUM(CASE WHEN je.entry_type = 'debit' THEN je.amount ELSE -je.amount END), 0)
                        FROM journal_entries je
                        JOIN transactions t ON je.transaction_id = t.id
                        WHERE je.account_code = '1000'
                        AND je.company_id = $company_id
                        AND t.transaction_date <= '$end_date') as balance
                ")->fetch_assoc();

                $ending_cash = $cash_balance['balance'] ?? 0;
                $beginning_cash = $conn->query("
                    SELECT 
                        (SELECT IFNULL(SUM(CASE WHEN je.entry_type = 'debit' THEN je.amount ELSE -je.amount END), 0)
                        FROM journal_entries je
                        JOIN transactions t ON je.transaction_id = t.id
                        WHERE je.account_code = '1000'
                        AND je.company_id = $company_id
                        AND t.transaction_date < '$start_date') as balance
                ")->fetch_assoc()['balance'] ?? 0;

                $sheet->setCellValue("A{$row}", 'Beginning Cash Balance');
                $sheet->setCellValue("B{$row}", (float)$beginning_cash);
                $row++;

                $sheet->setCellValue("A{$row}", 'Ending Cash Balance')->getStyle("A{$row}")->getFont()->setBold(true);
                $sheet->setCellValue("B{$row}", (float)$ending_cash)->getStyle("B{$row}")->getFont()->setBold(true);
                $sheet->getStyle("B4:B{$row}")->getNumberFormat()->setFormatCode('#,##0.00');

                // Auto-size columns
                foreach (range('A', 'B') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }

                $filename = "cash_flow_{$date_range}.xlsx";
                break;

            case 'balance_sheet':
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Balance Sheet');
    $sheet->setCellValue('A1', "BALANCE SHEET - $date_range")->getStyle('A1')->getFont()->setBold(true);
    $sheet->mergeCells('A1:B1');
    $sheet->setCellValue('A3', 'ASSETS')->getStyle('A3')->getFont()->setBold(true);

    // Assets
    $assets = $conn->query("
        SELECT a.account_name, 
               (SELECT IFNULL(SUM(CASE WHEN je.entry_type = 'debit' THEN je.amount ELSE -je.amount END), 0)
               FROM journal_entries je
               JOIN transactions t ON je.transaction_id = t.id
               WHERE je.account_code = a.account_code
               AND je.company_id = a.company_id
               AND t.transaction_date <= '$end_date') as balance
        FROM accounts a
        WHERE a.account_type = 'Asset' 
        AND a.company_id = $company_id
        ORDER BY a.account_code
    ");

    $row = 4;
    $total_assets = 0;
    while ($data = $assets->fetch_assoc()) {
        if ($data['balance'] != 0) {
            $sheet->setCellValue("A{$row}", $data['account_name']);
            $sheet->setCellValue("B{$row}", (float)$data['balance']);
            $total_assets += $data['balance'];
            $row++;
        }
    }

    $sheet->setCellValue("A{$row}", 'TOTAL ASSETS')->getStyle("A{$row}")->getFont()->setBold(true);
    $sheet->setCellValue("B{$row}", (float)$total_assets)->getStyle("B{$row}")->getFont()->setBold(true);
    $sheet->getStyle("B4:B{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
    $row += 2;

    // Liabilities
    $sheet->setCellValue("A{$row}", 'LIABILITIES')->getStyle("A{$row}")->getFont()->setBold(true);
    $row++;

    $liabilities = $conn->query("
        SELECT a.account_name, 
               (SELECT IFNULL(SUM(CASE WHEN je.entry_type = 'credit' THEN je.amount ELSE -je.amount END), 0)
               FROM journal_entries je
               JOIN transactions t ON je.transaction_id = t.id
               WHERE je.account_code = a.account_code
               AND je.company_id = a.company_id
               AND t.transaction_date <= '$end_date') as balance
        FROM accounts a
        WHERE a.account_type = 'Liability' 
        AND a.company_id = $company_id
        ORDER BY a.account_code
    ");

    $total_liabilities = 0;
    while ($data = $liabilities->fetch_assoc()) {
        if ($data['balance'] != 0) {
            $sheet->setCellValue("A{$row}", $data['account_name']);
            $sheet->setCellValue("B{$row}", (float)$data['balance']);
            $total_liabilities += $data['balance'];
            $row++;
        }
    }

    $sheet->setCellValue("A{$row}", 'TOTAL LIABILITIES')->getStyle("A{$row}")->getFont()->setBold(true);
    $sheet->setCellValue("B{$row}", (float)$total_liabilities)->getStyle("B{$row}")->getFont()->setBold(true);
    $sheet->getStyle("B4:B{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
    $row += 2;

    // Equity - Include all equity accounts
    $sheet->setCellValue("A{$row}", 'EQUITY')->getStyle("A{$row}")->getFont()->setBold(true);
    $row++;

    $equity = $conn->query("
        SELECT a.account_name, 
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

    $total_equity = 0;
    while ($data = $equity->fetch_assoc()) {
        if ($data['account_name'] != 'Current Period Earnings') { // We'll add this separately
            $sheet->setCellValue("A{$row}", $data['account_name']);
            $sheet->setCellValue("B{$row}", (float)$data['balance']);
            $total_equity += $data['balance'];
            $row++;
        }
    }

    // Add Current Period Earnings (Net Income)
    $net_income_result = $conn->query("
        SELECT (SELECT IFNULL(SUM(amount), 0) FROM income WHERE company_id = $company_id AND date BETWEEN '$start_date' AND '$end_date') -
               (SELECT IFNULL(SUM(amount), 0) FROM expenses WHERE company_id = $company_id AND date BETWEEN '$start_date' AND '$end_date') as net_income
    ");
    $net_income = $net_income_result->fetch_assoc()['net_income'] ?? 0;
    
    $sheet->setCellValue("A{$row}", "Current Period Earnings");
    $sheet->setCellValue("B{$row}", (float)$net_income);
    $total_equity += $net_income;
    $row++;

    $sheet->setCellValue("A{$row}", 'TOTAL EQUITY')->getStyle("A{$row}")->getFont()->setBold(true);
    $sheet->setCellValue("B{$row}", (float)$total_equity)->getStyle("B{$row}")->getFont()->setBold(true);
    $sheet->getStyle("B4:B{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
    $row += 2;

    // Total Liabilities & Equity
    $sheet->setCellValue("A{$row}", 'TOTAL LIABILITIES & EQUITY')->getStyle("A{$row}")->getFont()->setBold(true);
    $sheet->setCellValue("B{$row}", (float)($total_liabilities + $total_equity))->getStyle("B{$row}")->getFont()->setBold(true);
    $sheet->getStyle("B{$row}")->getNumberFormat()->setFormatCode('#,##0.00');

    // Auto-size columns
    foreach (range('A', 'B') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    $filename = "balance_sheet_{$date_range}.xlsx";
    break;

            case 'profit_loss':
                $sheet = $spreadsheet->getActiveSheet();
                $sheet->setTitle('Profit & Loss');
                $sheet->setCellValue('A1', "PROFIT & LOSS STATEMENT - $date_range")->getStyle('A1')->getFont()->setBold(true);
                $sheet->mergeCells('A1:B1');
                
                // Income section - exclude equity-related categories
                $sheet->setCellValue('A3', 'INCOME')->getStyle('A3')->getFont()->setBold(true);

                // Get income - exclude equity/balance sheet items
                $income = $conn->query("
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

                $row = 4;
                $total_income = 0;
                while ($data = $income->fetch_assoc()) {
                    $sheet->setCellValue("A{$row}", $data['sub_category']);
                    $sheet->setCellValue("B{$row}", (float)$data['total']);
                    $total_income += $data['total'];
                    $row++;
                }

                $sheet->setCellValue("A{$row}", 'TOTAL INCOME')->getStyle("A{$row}")->getFont()->setBold(true);
                $sheet->setCellValue("B{$row}", (float)$total_income)->getStyle("B{$row}")->getFont()->setBold(true);
                $sheet->getStyle("B4:B{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
                $row += 2;

                // Expenses - exclude equity categories
                $sheet->setCellValue("A{$row}", 'EXPENSES')->getStyle("A{$row}")->getFont()->setBold(true);
                $row++;

                $expenses = $conn->query("
                    SELECT category, sub_category, SUM(amount) as total 
                    FROM expenses 
                    WHERE company_id = $company_id
                    AND date BETWEEN '$start_date' AND '$end_date'
                    AND category NOT IN ('Equity', 'Owners Equity')
                    GROUP BY category, sub_category
                    ORDER BY 
                        FIELD(category, 'Direct cost', 'Expenses'),
                        sub_category
                ");

                $total_expenses = 0;
                $current_category = '';
                $category_total = 0;

                while ($data = $expenses->fetch_assoc()) {
                    if ($current_category !== $data['category']) {
                        if ($current_category !== '') {
                            $sheet->setCellValue("A{$row}", "Total " . $current_category)->getStyle("A{$row}")->getFont()->setBold(true);
                            $sheet->setCellValue("B{$row}", (float)$category_total)->getStyle("B{$row}")->getFont()->setBold(true);
                            $sheet->getStyle("B{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
                            $row++;
                        }
                        $current_category = $data['category'];
                        $category_total = 0;
                        $sheet->setCellValue("A{$row}", $current_category)->getStyle("A{$row}")->getFont()->setBold(true);
                        $row++;
                    }

                    $sheet->setCellValue("A{$row}", "    " . $data['sub_category']);
                    $sheet->setCellValue("B{$row}", (float)$data['total']);
                    $category_total += $data['total'];
                    $total_expenses += $data['total'];
                    $row++;
                }

                if ($current_category !== '') {
                    $sheet->setCellValue("A{$row}", "Total " . $current_category)->getStyle("A{$row}")->getFont()->setBold(true);
                    $sheet->setCellValue("B{$row}", (float)$category_total)->getStyle("B{$row}")->getFont()->setBold(true);
                    $sheet->getStyle("B{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
                    $row++;
                }

                $row++;
                $sheet->setCellValue("A{$row}", 'TOTAL EXPENSES')->getStyle("A{$row}")->getFont()->setBold(true);
                $sheet->setCellValue("B{$row}", (float)$total_expenses)->getStyle("B{$row}")->getFont()->setBold(true);
                $sheet->getStyle("B{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
                $row += 2;

                // Net Profit/Loss
                $net = $total_income - $total_expenses;
                $sheet->setCellValue("A{$row}", 'NET ' . ($net >= 0 ? 'PROFIT' : 'LOSS'))->getStyle("A{$row}")->getFont()->setBold(true);
                $sheet->setCellValue("B{$row}", abs((float)$net))->getStyle("B{$row}")->getFont()->setBold(true);
                $sheet->getStyle("B{$row}")->getNumberFormat()->setFormatCode('#,##0.00');

                // Auto-size columns
                foreach (range('A', 'B') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }

                $filename = "profit_loss_{$date_range}.xlsx";
                break;

            default:
                throw new Exception("Invalid export type specified");
        }

        // Generate safe filename
        $filename = preg_replace('/[^a-zA-Z0-9-_\.]/', '_', $filename);

        // Create temp file
        $temp_file = tempnam(sys_get_temp_dir(), 'export_');
        $writer = new Xlsx($spreadsheet);
        $writer->save($temp_file);

        // Clear output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }

        // Set headers
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        header('Content-Length: ' . filesize($temp_file));

        // Output file
        readfile($temp_file);
        
        // Clean up
        unlink($temp_file);
        exit;

    } catch (Exception $e) {
        error_log("Export Error: " . $e->getMessage());
        $_SESSION['error'] = "Export failed: " . $e->getMessage();
        header("Location: ../dashboard.php");
        exit;
    }
}

// If no type specified, redirect to dashboard
header("Location: ../dashboard.php");
exit;
?>