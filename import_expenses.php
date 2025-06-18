<?php
require 'db.php';
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

// Start session only if not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function normalizeDate($inputDate, $row_num) {
    if (empty($inputDate)) {
        return null;
    }

    // Handle Excel date values
    if (is_numeric($inputDate)) {
        try {
            return Date::excelToDateTimeObject($inputDate)->format('Y-m-d');
        } catch (Exception $e) {
            throw new Exception("Row $row_num: Invalid Excel date value");
        }
    }

    // Date patterns (handling MM/DD/YYYY format)
    $patterns = [
        '/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/' => '$3-$1-$2', // MM/DD/YYYY
        '/^(\d{1,2})\-(\d{1,2})\-(\d{4})$/' => '$3-$1-$2', // MM-DD-YYYY
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

    throw new Exception("Row $row_num: Unrecognized date format");
}

// Function to find column index by header name (case-insensitive)
function findColumnIndex($headerRow, $columnName, $alternatives = []) {
    $names = array_merge([$columnName], $alternatives);
    foreach ($headerRow as $index => $header) {
        // Skip null or empty headers
        if ($header === null || $header === '') {
            continue;
        }
        // Trim and clean header to remove extra spaces or special characters
        $cleanHeader = trim(preg_replace('/[\t\n\r\s]+/', ' ', $header));
        foreach ($names as $name) {
            if (strtolower($cleanHeader) === strtolower($name)) {
                return $index;
            }
        }
    }
    // Log failure to find the column
    file_put_contents('debug_headers.log', "Failed to find column '$columnName' (or alternatives: " . implode(', ', $alternatives) . ")\n", FILE_APPEND);
    return false;
}

// Function to check if a row contains known header names
function isHeaderRow($row) {
    $knownHeaders = ['date', 'amount', 'vendor name', 'supplier', 'supplier tin', 'receipt #', 'receipt no.', 'category', 'sub-category', 'notes', 'remarks'];
    foreach ($row as $cell) {
        if ($cell === null || $cell === '') {
            continue;
        }
        $cleanCell = trim(preg_replace('/[\t\n\r\s]+/', ' ', $cell));
        if (in_array(strtolower($cleanCell), $knownHeaders)) {
            return true;
        }
    }
    return false;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['file'])) {
    try {
        $file = $_FILES['file']['tmp_name'];
        $company_id = (int)$_POST['company_id'];
        
        // Validate file type
        $file_type = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        if (!in_array($file_type, ['xlsx', 'xls', 'csv'])) {
            throw new Exception("Invalid file type. Only Excel files are allowed.");
        }

        $spreadsheet = IOFactory::load($file);
        $conn->begin_transaction();

        // Get the first worksheet
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray(null, true, true, true);
        
        // Dynamically detect the header row
        $header = array_shift($rows);
        if (!isHeaderRow($header)) {
            $header = array_shift($rows); // Use the second row as header if the first row isn't a header
        }
        
        // Log header row for debugging
        file_put_contents('debug_headers.log', "Header Row: " . print_r($header, true) . "\n", FILE_APPEND);

        // Find column indices based on header names
        $dateCol = findColumnIndex($header, 'date', ['Date']);
        $receiptCol = findColumnIndex($header, 'receipt no.', ['Receipt #', 'Receipt No', 'Receipt Number']);
        $vendorCol = findColumnIndex($header, 'vendor name', ['Vendor Name', 'Supplier']);
        $supplierCol = findColumnIndex($header, 'supplier', ['Supplier']);
        $supplierTinCol = findColumnIndex($header, 'supplier tin', ['Supplier TIN', 'TIN']);
        $explanationCol = findColumnIndex($header, 'explanation', ['Explanation', 'Description']);
        $categoryCol = findColumnIndex($header, 'category', ['Category']);
        $subCategoryCol = findColumnIndex($header, 'sub-category', ['Sub-Category', 'Sub Category']);
        $amountCol = findColumnIndex($header, 'amount', ['Amount', 'Cost', 'Total', 'Price']);
        $notesCol = findColumnIndex($header, 'notes', ['Notes', 'Remarks', 'Comments']);

        // Validate required columns
        if ($amountCol === false) {
            throw new Exception("Required column 'Amount' (or alternatives: Cost, Total, Price) not found in the Excel file. Check debug_headers.log for the header row.");
        }
        if ($vendorCol === false) {
            throw new Exception("Required column 'Vendor Name' (or alternatives: Vendor Name, Supplier) not found in the Excel file. Check debug_headers.log for the header row.");
        }

        // Initialize counters
        $imported_count = 0;
        $skipped_count = 0;
        $errors = [];

        foreach ($rows as $row_num => $row) {
            try {
                // Skip empty rows
                if (empty(array_filter($row, function($value) { 
                    return $value !== null && $value !== '' && $value !== ' '; 
                }))) {
                    continue;
                }

                // Skip header rows if they appear again
                if ($dateCol !== false && isset($row[$dateCol]) && in_array(strtolower($row[$dateCol]), ['date', 'dates'])) {
                    continue;
                }

                // Process date (optional field)
                $date_value = $dateCol !== false ? ($row[$dateCol] ?? '') : '';
                $date = null;
                
                if (!empty($date_value)) {
                    try {
                        $date = normalizeDate($date_value, $row_num + 2); // Adjust row number for skipped rows
                    } catch (Exception $e) {
                        $errors[] = $e->getMessage();
                        $skipped_count++;
                        continue;
                    }
                }
                
                // Default to current date if empty
                $date = $date ?: date('Y-m-d');

                // Get expense data with proper trimming
                $receipt_no = $receiptCol !== false ? substr(trim($row[$receiptCol] ?? ''), 0, 100) : '';
                $vendor_name = $vendorCol !== false ? substr(trim($row[$vendorCol] ?? ''), 0, 255) : '';
                $supplier = $supplierCol !== false ? substr(trim($row[$supplierCol] ?? ''), 0, 255) : '';
                $supplier_tin = $supplierTinCol !== false ? substr(trim($row[$supplierTinCol] ?? ''), 0, 100) : '';
                $explanation = $explanationCol !== false ? substr(trim($row[$explanationCol] ?? ''), 0, 255) : '';
                $category = $categoryCol !== false ? substr(trim($row[$categoryCol] ?? 'General and Administrative Expenses'), 0, 100) : 'General and Administrative Expenses';
                $sub_category = $subCategoryCol !== false ? substr(trim($row[$subCategoryCol] ?? 'Other'), 0, 100) : 'Other';
                
                // Process amount (required field)
                $amount = 0;
                $amount_str = $amountCol !== false ? preg_replace('/[^\d\.]/', '', $row[$amountCol] ?? '0') : '0';
                if (is_numeric($amount_str)) {
                    $amount = (float)$amount_str;
                }

                if ($amount <= 0) {
                    $skipped_count++;
                    $errors[] = "Row " . ($row_num + 2) . ": Invalid amount";
                    continue;
                }

                // Process notes
                $notes = $notesCol !== false ? substr(trim($row[$notesCol] ?? ''), 0, 255) : '';

                // Set payment method to Cash as default
                $payment_method = 'Cash';

                // Insert expense record
                $stmt = $conn->prepare("INSERT INTO expenses 
                                      (date, receipt_no, vendor_name, supplier, supplier_tin, explanation, category, 
                                      sub_category, amount, payment_method, notes, company_id) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssssssdssi", 
                    $date, $receipt_no, $vendor_name, $supplier, $supplier_tin, $explanation, $category,
                    $sub_category, $amount, $payment_method, $notes, $company_id);
                
                if ($stmt->execute()) {
                    // Ensure default accounts exist
                    setupDefaultAccounts($company_id);
                    
                    // Map category to account code
                    $expense_account = '5300'; // Default to Ministry Expenses
                    if (stripos($category, 'General') !== false) {
                        $expense_account = '5000'; // Salaries
                    } elseif (stripos($category, 'Facility') !== false) {
                        $expense_account = '5200'; // Maintenance
                    }
                    
                    // Record transaction with automatic account handling
                    $entries = [
                        [
                            'account_code' => $expense_account,
                            'amount' => $amount,
                            'entry_type' => 'debit'
                        ],
                        [
                            'account_code' => '1000',
                            'amount' => $amount,
                            'entry_type' => 'credit'
                        ]
                    ];
                    
                    recordTransaction(
                        $date, 
                        "Expense: $category - $sub_category to $vendor_name", 
                        $entries, 
                        $receipt_no, 
                        $company_id
                    );
                    
                    $imported_count++;
                } else {
                    throw new Exception("Row " . ($row_num + 2) . ": Database error - " . $conn->error);
                }
                
            } catch (Exception $e) {
                $skipped_count++;
                $errors[] = $e->getMessage();
            }
        }

        $conn->commit();
        
        // Prepare result message
        $message = "Expenses import completed. $imported_count records imported successfully.";
        if ($skipped_count > 0) {
            $message .= " $skipped_count records skipped with errors.";
            $_SESSION['import_errors'] = $errors;
        }
        
        $_SESSION['import_result'] = [
            'success' => true,
            'message' => $message,
            'imported' => $imported_count,
            'skipped' => $skipped_count
        ];

        // Redirect to reports with the company_id
        header("Location: ../reports/profit_loss.php?company_id=$company_id&from_import=1");
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['import_result'] = [
            'success' => false,
            'message' => "Expenses import failed: " . $e->getMessage()
        ];
        header("Location: expenses.php");
        exit;
    }
}

header("Location: expenses.php");
exit;
?>