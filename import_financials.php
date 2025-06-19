<?php
// Use absolute path for includes based on the script's directory
$base_dir = dirname(__FILE__); // Gets the absolute path to the current script's directory
require $base_dir . '/db.php';
require $base_dir . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Normalize date from various formats
function normalizeDate($inputDate, $row_num) {
    if (empty($inputDate)) {
        throw new Exception("Row $row_num: Date value is empty");
    }

    $inputDate = trim($inputDate);

    if (is_numeric($inputDate)) {
        try {
            return Date::excelToDateTimeObject($inputDate)->format('Y-m-d');
        } catch (Exception $e) {
            throw new Exception("Row $row_num: Invalid Excel date value - " . $e->getMessage());
        }
    }

    if (preg_match('/^(\d{1,2})-([A-Za-z]{3})-(\d{2,4})$/', $inputDate, $matches)) {
        $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
        $monthStr = strtolower($matches[2]);
        $year = $matches[3];

        $monthMap = [
            'jan' => '01', 'feb' => '02', 'mar' => '03', 'apr' => '04',
            'may' => '05', 'jun' => '06', 'jul' => '07', 'aug' => '08',
            'sep' => '09', 'oct' => '10', 'nov' => '11', 'dec' => '12'
        ];

        if (!isset($monthMap[$monthStr])) {
            throw new Exception("Row $row_num: Invalid month abbreviation '$monthStr'");
        }

        $month = $monthMap[$monthStr];

        if (strlen($year) == 2) {
            $year = '20' . $year;
        }

        $normalized = "$year-$month-$day";
        if (checkdate($month, $day, $year)) {
            return $normalized;
        }
        throw new Exception("Row $row_num: Invalid date '$inputDate'");
    }

    $patterns = [
        '/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/' => '$3-$2-$1', // MM/DD/YYYY
        '/^(\d{1,2})\-(\d{1,2})\-(\d{4})$/' => '$3-$2-$1', // MM-DD-YYYY
        '/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/' => '$1-$2-$3', // YYYY/MM/DD
        '/^(\d{4})\-(\d{1,2})\-(\d{1,2})$/' => '$1-$2-$3'  // YYYY-MM-DD
    ];

    foreach ($patterns as $pattern => $replacement) {
        if (preg_match($pattern, $inputDate)) {
            $normalized = preg_replace($pattern, $replacement, $inputDate);
            $parts = explode('-', $normalized);
            if (checkdate($parts[1], $parts[2], $parts[0])) {
                return $normalized;
            }
        }
    }

    $timestamp = strtotime($inputDate);
    if ($timestamp !== false) {
        $date = date('Y-m-d', $timestamp);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }
    }

    throw new Exception("Row $row_num: Unrecognized date format '$inputDate'");
}

// Find column index in header row
function findColumnIndex($headerRow, $columnName, $alternatives = [], $firstDataRow = null) {
    $names = array_merge([$columnName], $alternatives);
    foreach ($headerRow as $index => $header) {
        if ($header === null || $header === '') {
            continue;
        }
        $cleanHeader = trim(preg_replace('/[\t\n\r\s]+/', ' ', str_replace(['-', '.'], ' ', strtolower($header))));
        foreach ($names as $name) {
            $cleanName = str_replace(['-', '.'], ' ', strtolower($name));
            if ($cleanHeader === $cleanName) {
                file_put_contents('import_debug.log', "Found column '$columnName' at index $index (header: $header)\n", FILE_APPEND);
                return $index;
            }
        }
    }

    if ($columnName === 'amount' && $firstDataRow !== null) {
        foreach ($firstDataRow as $index => $value) {
            if ($value !== null && $value !== '' && is_numeric(preg_replace('/[^\d\.,-]/', '', trim($value)))) {
                file_put_contents('import_debug.log', "Inferred amount column at index $index (value: $value)\n", FILE_APPEND);
                return $index;
            }
        }
    }

    file_put_contents('import_debug.log', "Failed to find column '$columnName' (alternatives: " . implode(', ', $alternatives) . ")\n", FILE_APPEND);
    return false;
}

// Check if row is a header row
function isHeaderRow($row) {
    $knownHeaders = ['date', 'amount', 'vendor', 'supplier', 'tin', 'receipt', 'category', 'subcategory', 'notes',
                     'remarks', 'donor', 'payor', 'name', 'invoice', 'type', 'description', 'total', 'value', 'donation amount',
                     'income', 'donation', 'contribution', 'funds', 'vendor name', 'recipient', 'sub category', 'total amount',
                     'transaction amount', 'payment', 'cost', 'price', 'bill', 'expense amount', 'invoice amount', 'receipt no',
                     'invoice no', 'payor', 'tin no', 'main account', 'supplier tin', 'mode', 'remarks', 'explanation', 'input_vat', 'output_vat'];
    foreach ($row as $cell) {
        if ($cell === null || $cell === '') {
            continue;
        }
        $cleanCell = trim(preg_replace('/[\t\n\r\s]+/', ' ', str_replace(['-', '.'], ' ', strtolower($cell))));
        if (in_array($cleanCell, $knownHeaders)) {
            return true;
        }
    }
    return false;
}

// Parse amount from string
function parseAmount($value, $row_num) {
    if ($value === null || $value === '') {
        throw new Exception("Row $row_num: Empty amount value");
    }

    $amount_str = preg_replace('/[^\d\.,-]/', '', trim($value));
    $amount_str = str_replace(',', '', $amount_str);

    if (!is_numeric($amount_str)) {
        throw new Exception("Row $row_num: Invalid amount value '$value'");
    }

    $amount = (float)$amount_str;

    if ($amount <= 0) {
        throw new Exception("Row $row_num: Amount must be greater than 0 (Value: $amount)");
    }

    return $amount;
}

// Check expenses table schema
function checkExpensesTableSchema($conn) {
    $result = $conn->query("SHOW COLUMNS FROM expenses LIKE 'explanation'");
    if ($result->num_rows == 0) {
        throw new Exception("The 'expenses' table is missing the 'explanation' column. Please add this column to the database schema.");
    }
    $result = $conn->query("SHOW COLUMNS FROM expenses LIKE 'input_vat'");
    if ($result->num_rows == 0) {
        $conn->query("ALTER TABLE expenses ADD COLUMN input_vat DECIMAL(15,2) DEFAULT 0.00");
    }
}

// Find account code based on category/sub_category
function findAccountCode($conn, $company_id, $category, $sub_category, $type = 'Revenue') {
    $category = $conn->real_escape_string(trim($category));
    $sub_category = $conn->real_escape_string(trim($sub_category));

    // First, try to match by sub_category and account_type
    $query = "SELECT account_code 
              FROM accounts 
              WHERE company_id = ? 
              AND (LOWER(account_name) = LOWER(?) OR LOWER(sub_category_1) = LOWER(?) OR LOWER(sub_category_2) = LOWER(?))
              AND account_type = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("issss", $company_id, $sub_category, $sub_category, $sub_category, $type);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['account_code'];
    }

    // Then, try to match by category and account_type
    $query = "SELECT account_code 
              FROM accounts 
              WHERE company_id = ? 
              AND (LOWER(account_name) = LOWER(?) OR LOWER(main_category) = LOWER(?))
              AND account_type = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("isss", $company_id, $category, $category, $type);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['account_code'];
    }

    // Fallback to default accounts
    if ($type === 'Revenue') {
        return '4200'; // Donations
    } elseif ($type === 'Expense') {
        return '5300'; // Outreach (default expense)
    } elseif ($type === 'Asset') {
        return '1000'; // Cash
    } elseif ($type === 'Liability') {
        return '2000'; // Accounts Payable
    }

    return false;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['file'])) {
    try {
        $file = $_FILES['file']['tmp_name'];
        $company_id = (int)$_POST['company_id'];

        if ($company_id <= 0) {
            throw new Exception("Invalid company ID: $company_id");
        }

        $file_type = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        if (!in_array($file_type, ['xlsx', 'xls', 'csv'])) {
            throw new Exception("Invalid file type. Only Excel files (XLSX, XLS, CSV) are allowed.");
        }

        $spreadsheet = IOFactory::load($file);

        file_put_contents('import_debug.log', "Uploaded file for company $company_id: " . $_FILES['file']['name'] . ", Size: " . $_FILES['file']['size'] . "\n", FILE_APPEND);

        $sheetNames = $spreadsheet->getSheetNames();
        file_put_contents('import_debug.log', "Detected sheet names for company $company_id: " . print_r($sheetNames, true) . "\n", FILE_APPEND);

        $incomeSheetNames = ['income', 'receipts', 'donations', 'revenue', 'income data', 'donation', 'income sheet', 'sales'];
        $expenseSheetNames = ['expenses', 'payments', 'bills', 'expense', 'expenditures', 'expense sheet'];

        $lowerSheetNames = array_map('strtolower', $sheetNames);
        $lowerIncomeSheetNames = array_map('strtolower', $incomeSheetNames);
        $lowerExpenseSheetNames = array_map('strtolower', $expenseSheetNames);

        if (!array_intersect($lowerSheetNames, $lowerIncomeSheetNames) && !array_intersect($lowerSheetNames, $lowerExpenseSheetNames)) {
            throw new Exception("No valid sheets found for company $company_id. Found sheets: " . implode(", ", $sheetNames) .
                               ". Expected Income sheets: " . implode(", ", $incomeSheetNames) .
                               "; Expected Expenses sheets: " . implode(", ", $expenseSheetNames));
        }

        checkExpensesTableSchema($conn);

        $conn->begin_transaction();

        $results = [
            'income' => ['imported' => 0, 'skipped' => 0, 'errors' => []],
            'expenses' => ['imported' => 0, 'skipped' => 0, 'errors' => []]
        ];

        // Process Income Sheet
        foreach ($sheetNames as $sheetName) {
            if (in_array(strtolower($sheetName), $lowerIncomeSheetNames)) {
                $worksheet = $spreadsheet->getSheetByName($sheetName);
                $rows = $worksheet->toArray(null, true, true, true);

                $debugRows = array_slice($rows, 0, 3);
                file_put_contents('import_debug.log', "Income sheet ($sheetName) for company $company_id first 3 rows: " . print_r($debugRows, true) . "\n", FILE_APPEND);

                $header = array_shift($rows);
                file_put_contents('import_debug.log', "Income sheet ($sheetName) for company $company_id first header attempt: " . print_r($header, true) . "\n", FILE_APPEND);

                $attempt = 1;
                while ($header && !isHeaderRow($header) && $attempt <= 2 && !empty($rows)) {
                    $header = array_shift($rows);
                    $attempt++;
                    file_put_contents('import_debug.log', "Income sheet ($sheetName) for company $company_id header attempt $attempt: " . print_r($header, true) . "\n", FILE_APPEND);
                }

                if (!isHeaderRow($header)) {
                    throw new Exception("Income sheet ($sheetName) for company $company_id: Could not identify header row after $attempt attempts.");
                }

                $firstDataRow = !empty($rows) ? array_shift($rows) : null;
                if ($firstDataRow) {
                    array_unshift($rows, $firstDataRow);
                }

                $dateCol = findColumnIndex($header, 'date', ['transaction date', 'date of transaction', 'entry date']);
                $invoiceCol = findColumnIndex($header, 'invoice no', ['invoice #', 'invoice number', 'invoice no.', 'receipt']);
                $payorCol = findColumnIndex($header, 'name', ['payor', 'donor', 'contributor', 'donor name', 'payor name', 'payor']);
                $subCategoryCol = findColumnIndex($header, 'sub category', ['subcategory', 'type', 'sub category name', 'category']);
                $amountCol = findColumnIndex($header, 'amount', ['total', 'value', 'donation amount', 'income', 'donation', 'contribution', 'funds', 'total amount', 'transaction amount', 'payment'], $firstDataRow);
                $notesCol = findColumnIndex($header, 'notes', ['description', 'remarks', 'comments', 'memo']);
                $paymentMethodCol = findColumnIndex($header, 'payment method', ['payment', 'method', 'payment type']);
                $outputVatCol = findColumnIndex($header, 'output_vat', ['output vat', 'vat output', 'output vat 12%', 'vat', 'vat 12%', 'output tax', 'sales tax'], $firstDataRow);

                if ($amountCol === false) {
                    $headerList = implode(", ", array_filter($header, fn($h) => $h !== null && $h !== ''));
                    throw new Exception("Income sheet ($sheetName) for company $company_id: Could not find or infer amount column. Detected headers: $headerList");
                }

                $emptyRowCount = 0;
                $maxEmptyRows = 5;
                foreach ($rows as $row_num => $row) {
                    try {
                        $isEmpty = empty(array_filter($row, function($value) {
                            return $value !== null && $value !== '' && $value !== ' ';
                        }));

                        if ($isEmpty) {
                            $emptyRowCount++;
                            if ($emptyRowCount >= $maxEmptyRows) {
                                file_put_contents('import_debug.log', "Stopped processing Income sheet at row $row_num for company $company_id due to $maxEmptyRows consecutive empty rows\n", FILE_APPEND);
                                break;
                            }
                            continue;
                        } else {
                            $emptyRowCount = 0;
                        }

                        $date = $dateCol !== false ? normalizeDate($row[$dateCol] ?? '', $row_num + 2) : '';
                        if (empty($date)) {
                            throw new Exception("Row $row_num: Date is required");
                        }
                        $invoice_no = $invoiceCol !== false ? substr(trim($row[$invoiceCol] ?? ''), 0, 100) : '';
                        $payor = $payorCol !== false ? substr(trim($row[$payorCol] ?? ''), 0, 255) : '';
                        $donor_name = $payor;
                        $sub_category = $subCategoryCol !== false ? substr(trim($row[$subCategoryCol] ?? 'Other'), 0, 100) : 'Other';
                        $amount = parseAmount($row[$amountCol], $row_num + 2);
                        $notes = $notesCol !== false ? substr(trim($row[$notesCol] ?? ''), 0, 255) : '';
                        $payment_method = $paymentMethodCol !== false ? substr(trim($row[$paymentMethodCol] ?? 'Cash'), 0, 50) : 'Cash';
                        
                        // Improved Output VAT handling
                        if ($outputVatCol === false) {
                            file_put_contents('import_debug.log', "Income sheet ($sheetName) for company $company_id: Could not find output_vat column. Calculating 12% of amount.\n", FILE_APPEND);
                            $output_vat = round($amount * 0.12, 2);
                        } else {
                            $output_vat_raw = trim($row[$outputVatCol] ?? '');
                            
                            if (is_numeric($output_vat_raw)) {
                                $output_vat = (float)$output_vat_raw;
                            } elseif (strpos($output_vat_raw, '%') !== false) {
                                $percent = (float)str_replace('%', '', $output_vat_raw);
                                $output_vat = round($amount * ($percent / 100), 2);
                            } elseif ($output_vat_raw === '' || $output_vat_raw === '-') {
                                $output_vat = round($amount * 0.12, 2);
                            } else {
                                $output_vat = round($amount * 0.12, 2);
                            }
                            
                            $output_vat = max(0, $output_vat);
                            
                            file_put_contents('import_debug.log', "Income sheet ($sheetName) row $row_num: Detected output_vat '$output_vat_raw' parsed as $output_vat\n", FILE_APPEND);
                        }
                        
                        // Validate VAT doesn't exceed amount
                        if ($output_vat > 0 && $output_vat >= $amount) {
                            throw new Exception("Row $row_num: Output VAT ($output_vat) cannot be greater than or equal to amount ($amount)");
                        }

                        $stmt = $conn->prepare("INSERT INTO income
                                              (date, donor_name, invoice_no, payor, sub_category, amount,
                                              payment_method, notes, company_id, output_vat)
                                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("sssssdssid", $date, $donor_name, $invoice_no, $payor, $sub_category, $amount, $payment_method, $notes, $company_id, $output_vat);

                        if ($stmt->execute()) {
                            setupDefaultAccounts($company_id);

                            // Map sub_category to account code
                            $account_code = findAccountCode($conn, $company_id, 'Revenue', $sub_category, 'Revenue');
                            if (!$account_code) {
                                throw new Exception("Row $row_num: No matching account found for sub_category '$sub_category' in Revenue accounts");
                            }

                            if (strcasecmp($sub_category, 'Accounts Receivable') === 0) {
                                $entries = [
                                    ['account_code' => '1000', 'amount' => $amount, 'entry_type' => 'debit'], // Cash
                                    ['account_code' => '1100', 'amount' => $amount, 'entry_type' => 'credit'] // Accounts Receivable
                                ];
                            } elseif (in_array(strtolower($sub_category), ['accounts payable', 'other payable', 'notes payable', 'short-term loans', 'advances from officers'])) {
                                $liability_account = findAccountCode($conn, $company_id, 'Liability', $sub_category, 'Liability');
                                if (!$liability_account) {
                                    throw new Exception("Row $row_num: No matching liability account found for sub_category '$sub_category'");
                                }
                                $debit_account = $payment_method === 'Cash' ? '1000' : '5000'; // Cash or General Expense
                                $entries = [
                                    ['account_code' => $debit_account, 'amount' => $amount, 'entry_type' => 'debit'],
                                    ['account_code' => $liability_account, 'amount' => $amount, 'entry_type' => 'credit']
                                ];
                            } else {
                                $base_amount = $amount - $output_vat;
                                if ($output_vat > 0) {
                                    $entries = [
                                        ['account_code' => '1000', 'amount' => $amount, 'entry_type' => 'debit'], // Cash
                                        ['account_code' => $account_code, 'amount' => $base_amount, 'entry_type' => 'credit'], // Revenue
                                        ['account_code' => '2400', 'amount' => $output_vat, 'entry_type' => 'credit'] // Output VAT
                                    ];
                                } else {
                                    $entries = [
                                        ['account_code' => '1000', 'amount' => $amount, 'entry_type' => 'debit'], // Cash
                                        ['account_code' => $account_code, 'amount' => $amount, 'entry_type' => 'credit'] // Revenue
                                    ];
                                }
                            }

                            recordTransaction(
                                $date,
                                "Income: $sub_category" . ($payor ? " from $payor" : ""),
                                $entries,
                                $invoice_no,
                                $company_id
                            );

                            $results['income']['imported']++;
                        } else {
                            throw new Exception("Database error: " . $conn->error);
                        }
                    } catch (Exception $e) {
                        $results['income']['skipped']++;
                        $results['income']['errors'][] = $e->getMessage();
                        file_put_contents('import_errors.log', "Income row $row_num for company $company_id: " . $e->getMessage() . ", Row data: " . print_r($row, true) . "\n", FILE_APPEND);
                        continue;
                    }
                }
                break;
            }
        }

        // Process Expenses Sheet
        foreach ($sheetNames as $sheetName) {
            if (in_array(strtolower($sheetName), $lowerExpenseSheetNames)) {
                $worksheet = $spreadsheet->getSheetByName($sheetName);
                $rows = $worksheet->toArray(null, true, true, true);

                $debugRows = array_slice($rows, 0, 3);
                file_put_contents('import_debug.log', "Expenses sheet ($sheetName) for company $company_id first 3 rows: " . print_r($debugRows, true) . "\n", FILE_APPEND);

                $header = array_shift($rows);
                file_put_contents('import_debug.log', "Expenses sheet ($sheetName) for company $company_id first header attempt: " . print_r($header, true) . "\n", FILE_APPEND);

                $attempt = 1;
                while ($header && !isHeaderRow($header) && $attempt <= 2 && !empty($rows)) {
                    $header = array_shift($rows);
                    $attempt++;
                    file_put_contents('import_debug.log', "Expenses sheet ($sheetName) for company $company_id header attempt $attempt: " . print_r($header, true) . "\n", FILE_APPEND);
                }

                if (!isHeaderRow($header)) {
                    throw new Exception("Expenses sheet ($sheetName) for company $company_id: Could not identify header row after $attempt attempts.");
                }

                $firstDataRow = !empty($rows) ? array_shift($rows) : null;
                if ($firstDataRow) {
                    array_unshift($rows, $firstDataRow);
                }

                $dateCol = findColumnIndex($header, 'date', ['transaction date', 'date of transaction', 'entry date']);
                $receiptCol = findColumnIndex($header, 'receipt no', ['receipt #', 'invoice no', 'receipt number', 'invoice', 'receipt no']);
                $vendorCol = findColumnIndex($header, 'vendor', ['vendor name', 'payee', 'recipient', 'paid to']);
                $supplierCol = findColumnIndex($header, 'supplier', ['vendor', 'payee', 'vendor name', 'recipient', 'paid to']);
                $supplierTinCol = findColumnIndex($header, 'supplier tin', ['tin', 'supplier tin', 'tin no']);
                $categoryCol = findColumnIndex($header, 'category', ['expense type', 'expense category', 'category', 'main account']);
                $subCategoryCol = findColumnIndex($header, 'sub category', ['subcategory', 'type', 'sub category name', 'category type', 'mode', 'description', 'sub category']);
                $amountCol = findColumnIndex($header, 'amount', ['total', 'value', 'expense amount', 'cost', 'price', 'bill', 'invoice amount', 'payment'], $firstDataRow);
                $explanationCol = findColumnIndex($header, 'explanation', ['purpose', 'details']);
                $notesCol = findColumnIndex($header, 'notes', ['description', 'remarks', 'comments', 'memo']);
                $inputVatCol = findColumnIndex($header, 'input_vat', ['input vat', 'vat input', 'input_vat', 'Input VAT', 'VAT Input', 'input vat 12%']);

                if ($amountCol === false) {
                    $headerList = implode(", ", array_filter($header, fn($h) => $h !== null && $h !== ''));
                    throw new Exception("Expenses sheet ($sheetName) for company $company_id: Could not find or infer amount column. Detected headers: $headerList");
                }

                $emptyRowCount = 0;
                $maxEmptyRows = 5;
                foreach ($rows as $row_num => $row) {
                    try {
                        $isEmpty = empty(array_filter($row, function($value) {
                            return $value !== null && $value !== '' && $value !== ' ';
                        }));

                        if ($isEmpty) {
                            $emptyRowCount++;
                            if ($emptyRowCount >= $maxEmptyRows) {
                                file_put_contents('import_debug.log', "Stopped processing Expenses sheet at row $row_num for company $company_id due to $maxEmptyRows consecutive empty rows\n", FILE_APPEND);
                                break;
                            }
                            continue;
                        } else {
                            $emptyRowCount = 0;
                        }

                        $date = $dateCol !== false ? normalizeDate($row[$dateCol] ?? '', $row_num + 2) : date('Y-m-d');
                        $receipt_no = $receiptCol !== false ? substr(trim($row[$receiptCol] ?? ''), 0, 100) : '';
                        $vendor_name = $vendorCol !== false ? substr(trim($row[$vendorCol] ?? ''), 0, 255) : '';
                        $explanation = $explanationCol !== false ? substr(trim($row[$explanationCol] ?? ''), 0, 65535) : '';
                        $category_raw = $categoryCol !== false ? trim($row[$categoryCol] ?? 'General') : 'General';
                        $sub_category_raw = $subCategoryCol !== false ? trim($row[$subCategoryCol] ?? '') : '';
                        $amount = parseAmount($row[$amountCol], $row_num + 2);
                        $notes = $notesCol !== false ? substr(trim($row[$notesCol] ?? ''), 0, 65535) : '';
                        $payment_method = 'Cash';
                        $supplier = $supplierCol !== false ? substr(trim($row[$supplierCol] ?? ''), 0, 255) : '';
                        $supplier_tin = $supplierTinCol !== false ? ($row[$supplierTinCol] !== null && trim($row[$supplierTinCol]) !== '' ? substr(trim($row[$supplierTinCol]), 0, 100) : '') : '';

                        // Improved Input VAT handling
                        $inputVatCol = findColumnIndex($header, 'input_vat', ['input vat', 'vat input', 'input_vat', 'Input VAT', 'VAT Input', 'input vat 12%']);
                        $input_vat = $inputVatCol !== false ? (is_numeric(trim($row[$inputVatCol] ?? '')) ? (float)trim($row[$inputVatCol]) : 0) : 0;

                        // If input_vat is still 0 and the column exists, attempt to parse it as a formatted number
                        if ($inputVatCol !== false && $input_vat == 0) {
                            $vatValue = trim($row[$inputVatCol] ?? '');
                            if (!empty($vatValue) && $vatValue !== '-') {
                                $vatValue = preg_replace('/[^\d\.,-]/', '', $vatValue); // Remove non-numeric characters except decimal and comma
                                $vatValue = str_replace(',', '', $vatValue); // Remove commas
                                $input_vat = is_numeric($vatValue) ? (float)$vatValue : 0;
                            }
                        }

                        $category = $category_raw;
                        if (stripos($category_raw, 'sales') !== false) {
                            $category = 'Sales';
                        }
                        $sub_category = $sub_category_raw ?: 'Other';

                        $stmt = $conn->prepare("INSERT INTO expenses
                                              (date, receipt_no, vendor_name, explanation, category, sub_category, amount,
                                              payment_method, notes, company_id, supplier, supplier_tin, input_vat)
                                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("ssssssdssisss", $date, $receipt_no, $vendor_name, $explanation, $category, $sub_category, $amount, $payment_method, $notes, $company_id, $supplier, $supplier_tin, $input_vat);

                        if ($stmt->execute()) {
                            setupDefaultAccounts($company_id);

                            // Map category/sub_category to expense account
                            $expense_account = findAccountCode($conn, $company_id, $category, $sub_category, 'Expense');
                            if (!$expense_account) {
                                throw new Exception("Row $row_num: No matching account found for category '$category' or sub_category '$sub_category' in Expense accounts");
                            }

                            $base_amount = $amount - $input_vat; // Use provided input_vat directly
                            $entries = [
                                ['account_code' => $expense_account, 'amount' => $base_amount, 'entry_type' => 'debit'],
                                ['account_code' => '2500', 'amount' => $input_vat, 'entry_type' => 'debit'], // Input VAT
                                ['account_code' => '1000', 'amount' => $amount, 'entry_type' => 'credit'] // Cash
                            ];

                            recordTransaction(
                                $date,
                                "Expense: $category - $sub_category" . ($vendor_name ? " to $vendor_name" : ""),
                                $entries,
                                $receipt_no,
                                $company_id
                            );

                            $results['expenses']['imported']++;
                        } else {
                            throw new Exception("Database error: " . $conn->error);
                        }
                    } catch (Exception $e) {
                        $results['expenses']['skipped']++;
                        $results['expenses']['errors'][] = $e->getMessage();
                        file_put_contents('import_errors.log', "Expenses row $row_num for company $company_id: " . $e->getMessage() . ", Row data: " . print_r($row, true) . "\n", FILE_APPEND);
                        continue;
                    }
                }
                break;
            }
        }

        $conn->commit();

        $message = "Import completed for company $company_id. ";
        $message .= "Income: {$results['income']['imported']} imported, {$results['income']['skipped']} skipped. ";
        $message .= "Expenses: {$results['expenses']['imported']} imported, {$results['expenses']['skipped']} skipped.";

        $_SESSION['import_result'] = [
            'success' => true,
            'message' => $message,
            'details' => $results
        ];

        if (!empty($results['income']['errors']) || !empty($results['expenses']['errors'])) {
            $_SESSION['import_errors'] = array_merge(
                array_slice($results['income']['errors'], 0, 10),
                array_slice($results['expenses']['errors'], 0, 10)
            );
        }

        // Use an absolute redirect URL based on the server context
        $base_url = "http://" . $_SERVER['SERVER_ADDR'] . ":8080/acc/";
        header("Location: " . $base_url . "dashboard.php?company_id=$company_id&from_import=1");
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['import_result'] = [
            'success' => false,
            'message' => "Import failed for company $company_id: " . $e->getMessage()
        ];
        file_put_contents('import_errors.log', "Import failed for company $company_id: " . $e->getMessage() . "\n", FILE_APPEND);
        $base_url = "http://" . $_SERVER['SERVER_ADDR'] . ":8080/acc/";
        header("Location: " . $base_url . "dashboard.php");
        exit;
    }
}

// Default redirect if not a POST request
$base_url = "http://" . $_SERVER['SERVER_ADDR'] . ":8080/acc/";
header("Location: " . $base_url . "dashboard.php");
exit;
?>