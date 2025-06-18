<?php
require 'db.php';
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

session_start();

function normalizeDate($inputDate, $row_num) {
    if (empty($inputDate)) {
        return date('Y-m-d');
    }

    if (is_numeric($inputDate)) {
        try {
            return Date::excelToDateTimeObject($inputDate)->format('Y-m-d');
        } catch (Exception $e) {
            throw new Exception("Row $row_num: Invalid Excel date value");
        }
    }

    $formats = [
        'm/d/Y', 'm-d-Y', 'd/m/Y', 'd-m-Y', 
        'Y-m-d', 'Y/m/d', 'n/j/Y', 'n-j-Y'
    ];
    
    foreach ($formats as $format) {
        $date = DateTime::createFromFormat($format, $inputDate);
        if ($date !== false) {
            return $date->format('Y-m-d');
        }
    }
    
    $timestamp = strtotime($inputDate);
    if ($timestamp !== false) {
        return date('Y-m-d', $timestamp);
    }
    
    throw new Exception("Row $row_num: Unrecognized date format - ".htmlspecialchars($inputDate));
}

function cleanAmount($amount) {
    // If it's already a number, return it directly
    if (is_numeric($amount)) {
        return round(floatval($amount), 2);
    }

    // Handle empty values
    if ($amount === null || $amount === '' || $amount === ' ') {
        return 0.00;
    }

    // Convert to string if it isn't already
    $amountStr = (string)$amount;

    // Remove all non-numeric characters except decimal point and comma
    $cleaned = preg_replace('/[^\d.,-]/', '', $amountStr);

    // Handle negative numbers
    $isNegative = strpos($cleaned, '-') !== false;
    $cleaned = str_replace('-', '', $cleaned);

    // Handle European-style numbers (1.234,56)
    if (strpos($cleaned, ',') !== false && strpos($cleaned, '.') !== false) {
        $cleaned = str_replace('.', '', $cleaned);
        $cleaned = str_replace(',', '.', $cleaned);
    }
    // Handle comma as decimal separator (1,23)
    elseif (strpos($cleaned, ',') !== false) {
        $cleaned = str_replace(',', '.', $cleaned);
    }

    // Convert to float and handle negatives
    $result = floatval($cleaned);
    if ($isNegative) {
        $result = -$result;
    }

    return round($result, 2);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['file'])) {
    try {
        $file = $_FILES['file']['tmp_name'];
        $company_id = (int)$_POST['company_id'];
        
        $spreadsheet = IOFactory::load($file);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray(null, true, true, true);
        
        $header = array_shift($rows);
        $highestColumn = $worksheet->getHighestColumn();
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);

        // Debug: Log the first few rows to see raw data
        error_log("First row data: " . print_r(reset($rows), true));
        
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

                // Determine format automatically by checking column headers or content
                $is_second_format = false;
                $amount_col_index = null;
                
                // Try to detect format by checking column headers
                foreach ($row as $col => $value) {
                    if (strpos(strtolower($value), 'amount') !== false) {
                        $amount_col_index = Coordinate::columnIndexFromString($col);
                        break;
                    }
                }
                
                // If no header found, try to detect format by content
                if ($amount_col_index === null) {
                    // Check if this looks like second format
                    if (isset($row['B']) && (strpos($row['B'], 'Petty Cash Voucher') !== false || strpos($row['B'], 'Lazada') !== false)) {
                        $is_second_format = true;
                        $amount_col_index = 6; // Column F
                    } else {
                        // Default to first format (amount in column G)
                        $amount_col_index = 7; // Column G
                    }
                }

                // Get amount from the correct column
                $amount_col = Coordinate::stringFromColumnIndex($amount_col_index);
                $amount_value = $row[$amount_col] ?? '';
                
                // Debug log for amount detection
                error_log("Row $row_num - Raw amount value: " . print_r($amount_value, true));
                
                $amount = cleanAmount($amount_value);
                
                // Debug log after cleaning
                error_log("Row $row_num - Cleaned amount: $amount");

                // Rest of your field mapping...
                if ($is_second_format) {
                    $date_value = $row['A'] ?? '';
                    $vendor_name = $row['B'] ?? 'Unknown Vendor';
                    $category = $row['D'] ?? 'General and Administrative Expenses';
                    $sub_category = $row['E'] ?? 'Other Expense';
                    $notes = $row['G'] ?? '';
                    $receipt_no = '';
                    $explanation = $notes;
                } else {
                    $date_value = $row['A'] ?? '';
                    $receipt_no = $row['B'] ?? '';
                    $vendor_name = $row['C'] ?? 'Unknown Vendor';
                    $category = $row['E'] ?? 'General and Administrative Expenses';
                    $sub_category = $row['F'] ?? 'Other Expense';
                    $notes = $row['H'] ?? '';
                    $explanation = $row['D'] ?? '';
                }

                // Process date
                try {
                    $date = normalizeDate($date_value, $row_num + 2);
                } catch (Exception $e) {
                    $errors[] = $e->getMessage();
                    $skipped_count++;
                    continue;
                }

                // Insert record
                $stmt = $conn->prepare("INSERT INTO expenses 
                                    (date, receipt_no, vendor_name, explanation, category, 
                                    sub_category, amount, payment_method, notes, company_id) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, 'Cash', ?, ?)");
                $stmt->bind_param("ssssssdsi", 
                    $date, $receipt_no, $vendor_name, $explanation, $category,
                    $sub_category, $amount, $notes, $company_id);
                
                if ($stmt->execute()) {
                    if ($amount > 0) {
                        $expense_account = '5300';
                        if (stripos($category, 'General') !== false) {
                            $expense_account = '5000';
                        } elseif (stripos($category, 'Facility') !== false) {
                            $expense_account = '5200';
                        } elseif (stripos($category, 'Cost of Goods') !== false) {
                            $expense_account = '5100';
                        }
                        
                        $entries = [
                            ['account_code' => $expense_account, 'amount' => $amount, 'entry_type' => 'debit'],
                            ['account_code' => '1000', 'amount' => $amount, 'entry_type' => 'credit']
                        ];
                        
                        recordTransaction(
                            $date, 
                            "Expense: $category - $sub_category to $vendor_name", 
                            $entries, 
                            $receipt_no,
                            $company_id
                        );
                    }
                    $imported_count++;
                } else {
                    throw new Exception("Row ".($row_num+2).": Database error - ".$conn->error);
                }
            } catch (Exception $e) {
                $skipped_count++;
                $errors[] = $e->getMessage();
                error_log("Import error on row $row_num: " . $e->getMessage());
            }
        }

        $conn->commit();
        
        $_SESSION['import_result'] = [
            'success' => true,
            'message' => "Expenses import completed. $imported_count records imported successfully." . 
                        ($skipped_count > 0 ? " $skipped_count records skipped with errors." : ""),
            'imported' => $imported_count,
            'skipped' => $skipped_count
        ];

        if (!empty($errors)) {
            $_SESSION['import_errors'] = $errors;
            error_log("Import errors: " . print_r($errors, true));
        }

        header("Location: ../reports/profit_loss.php?company_id=$company_id&from_import=1");
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['import_result'] = [
            'success' => false,
            'message' => "Expenses import failed: " . $e->getMessage()
        ];
        error_log("Import failed: " . $e->getMessage());
        header("Location: expenses.php");
        exit;
    }
}

header("Location: expenses.php");
exit;
?>