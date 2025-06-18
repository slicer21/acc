<?php
require 'db.php';
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

session_start();

function normalizeDate($inputDate) {
    if (empty($inputDate)) {
        return date('Y-m-d');
    }

    if (is_numeric($inputDate)) {
        try {
            return Date::excelToDateTimeObject($inputDate)->format('Y-m-d');
        } catch (Exception $e) {
            return date('Y-m-d');
        }
    }

    $timestamp = strtotime($inputDate);
    if ($timestamp !== false) {
        return date('Y-m-d', $timestamp);
    }

    return date('Y-m-d');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['file'])) {
    try {
        $file = $_FILES['file']['tmp_name'];
        $company_id = (int)$_POST['company_id'];
        
        $file_type = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        if (!in_array($file_type, ['xlsx', 'xls', 'csv'])) {
            throw new Exception("Invalid file type. Only Excel files are allowed.");
        }

        $spreadsheet = IOFactory::load($file);
        $conn->begin_transaction();

        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray(null, true, true, true);
        
        $header = array_shift($rows);
        if (empty($header) || empty(array_filter($header))) {
            throw new Exception("Header row is empty or missing.");
        }

        $imported_count = 0;
        $skipped_count = 0;
        $errors = [];
        $is_lssc = stripos($_FILES['file']['name'], 'jehu') === false;

        // Dynamic column mapping for LSCC files
        $column_map = [];
        $use_fallback = false;
        if ($is_lssc) {
            $header = array_map('strtolower', array_map('trim', $header));
            foreach ($header as $col => $name) {
                if (preg_match('/\b(date|dt|day)\b/i', $name)) {
                    $column_map['date'] = $col;
                } elseif (preg_match('/\b(donor|name|payor|payee|contributor)\b/i', $name)) {
                    $column_map['donor_name'] = $col;
                } elseif (preg_match('/\b(category|cat|revenue)\b/i', $name)) {
                    $column_map['category'] = $col;
                } elseif (preg_match('/\b(sub.*category|tithes|offering|type)\b/i', $name)) {
                    $column_map['sub_category'] = $col;
                } elseif (preg_match('/\b(amount|amt|value|contribution|total)\b/i', $name)) {
                    $column_map['amount'] = $col;
                } elseif (preg_match('/\b(notes|comment|remarks|description)\b/i', $name)) {
                    $column_map['notes'] = $col;
                }
            }

            // Check for required columns
            $required_columns = ['date', 'donor_name', 'amount'];
            $missing_columns = array_diff($required_columns, array_keys($column_map));
            if (!empty($missing_columns)) {
                $use_fallback = true; // Try fallback mapping
                $errors[] = "Dynamic mapping failed. Missing required columns: " . implode(', ', $missing_columns) . ". Header row: " . json_encode($header);
            }
        }

        foreach ($rows as $row_num => $row) {
            try {
                if (empty(array_filter($row, function($value) { 
                    return $value !== null && $value !== '' && $value !== ' '; 
                }))) {
                    continue;
                }

                if (isset($row['A']) && in_array(strtolower($row['A']), ['date', 'dates', ''])) {
                    continue;
                }

                if ($is_lssc) {
                    if ($use_fallback) {
                        // Fallback mapping for LSCC (similar to previous fixed mapping)
                        $date_value = $row['A'] ?? '';
                        $date = normalizeDate($date_value);

                        $donor_name = trim($row['B'] ?? '');
                        $donor_name = preg_replace('/\s+/', ' ', $donor_name);
                        $donor_name = str_ireplace(['&', ' and '], [' and ', ' and '], $donor_name);
                        
                        $category = trim($row['C'] ?? 'Revenue');
                        $sub_category = trim($row['D'] ?? '');
                        $sub_category = stripos($sub_category, 'offering') !== false ? 'Offering' : 'Tithes';
                        
                        $amount_str = trim($row['E'] ?? '');
                        $amount_str = preg_replace('/[^0-9\.\-]/', '', $amount_str);
                        $amount = (is_numeric($amount_str) && $amount_str !== '') ? (float)$amount_str : 0;
                        
                        $notes = trim($row['F'] ?? '');
                        $payor = $donor_name;
                        $invoice_no = null;
                    } else {
                        // Dynamic mapping for LSCC
                        $date_value = $row[$column_map['date']] ?? '';
                        $date = normalizeDate($date_value);

                        $donor_name = trim($row[$column_map['donor_name']] ?? '');
                        $donor_name = preg_replace('/\s+/', ' ', $donor_name);
                        $donor_name = str_ireplace(['&', ' and '], [' and ', ' and '], $donor_name);
                        
                        $category = isset($column_map['category']) ? trim($row[$column_map['category']] ?? 'Revenue') : 'Revenue';
                        $sub_category = isset($column_map['sub_category']) ? trim($row[$column_map['sub_category']] ?? '') : 'Tithes';
                        $sub_category = stripos($sub_category, 'offering') !== false ? 'Offering' : 'Tithes';
                        
                        $amount_str = trim($row[$column_map['amount']] ?? '');
                        $amount_str = preg_replace('/[^0-9\.\-]/', '', $amount_str);
                        $amount = (is_numeric($amount_str) && $amount_str !== '') ? (float)$amount_str : 0;
                        
                        $notes = isset($column_map['notes']) ? trim($row[$column_map['notes']] ?? '') : '';
                        $payor = $donor_name;
                        $invoice_no = null;
                    }

                    if ($amount <= 0 || $amount_str === '') {
                        $skipped_count++;
                        $errors[] = "Row " . ($row_num + 2) . ": Invalid or zero amount (Raw: '$amount_str', Parsed: $amount, Full Row: " . json_encode($row) . ")";
                        continue;
                    }
                } else {
                    // JEHU file format handling (fixed columns)
                    $date_value = $row['A'] ?? '';
                    $date = normalizeDate($date_value);

                    $invoice_no = trim($row['B'] ?? '');
                    if (is_numeric($invoice_no)) {
                        $invoice_no = (string)$invoice_no;
                    } else {
                        $invoice_no = null;
                    }

                    $payor = trim($row['C'] ?? '');
                    $donor_name = $payor;
                    
                    $amount_str = trim($row['E'] ?? '');
                    $amount_str = preg_replace('/[^0-9\.\-]/', '', $amount_str);
                    $amount = (is_numeric($amount_str) && $amount_str !== '') ? (float)$amount_str : 0;
                    
                    $sub_category = 'Sales';
                    $category = 'Revenue';
                    $notes = '';
                }

                if (empty($donor_name)) {
                    $skipped_count++;
                    $errors[] = "Row " . ($row_num + 2) . ": Missing donor/payor name";
                    continue;
                }

                if (!is_numeric($amount) || $amount <= 0) {
                    $skipped_count++;
                    $errors[] = "Row " . ($row_num + 2) . ": Invalid or zero amount (Raw: '$amount_str')";
                    continue;
                }

                // Check for duplicates
                $check_stmt = $conn->prepare("SELECT id FROM income 
                                            WHERE date = ? AND donor_name = ? AND amount = ? 
                                            AND sub_category = ? AND company_id = ?
                                            AND (invoice_no IS NULL OR invoice_no = ?)");
                $check_stmt->bind_param("ssdsis", $date, $donor_name, $amount, $sub_category, $company_id, $invoice_no);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();

                if ($check_result->num_rows > 0) {
                    $skipped_count++;
                    $errors[] = "Row " . ($row_num + 2) . ": Duplicate entry skipped";
                    continue;
                }

                // Insert income record
                $stmt = $conn->prepare("INSERT INTO income 
                                      (date, donor_name, invoice_no, payor, category, sub_category, amount, 
                                      payment_method, notes, company_id) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?, 'Cash', ?, ?)");
                $stmt->bind_param("ssssssdsi", $date, $donor_name, $invoice_no, $payor, $category, 
                                $sub_category, $amount, $notes, $company_id);
                
                if ($stmt->execute()) {
                    setupDefaultAccounts($company_id);
                    
                    $account_code = $sub_category == 'Tithes' ? '4000' : 
                                  ($sub_category == 'Offering' ? '4100' : '4200');
                    
                    $entries = [
                        [
                            'account_code' => $account_code,
                            'amount' => $amount,
                            'entry_type' => 'credit'
                        ],
                        [
                            'account_code' => '1000',
                            'amount' => $amount,
                            'entry_type' => 'debit'
                        ]
                    ];
                    
                    recordTransaction(
                        $date, 
                        "Income: $sub_category from $donor_name", 
                        $entries, 
                        null, 
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
        
        $message = "Income import completed. $imported_count records imported successfully.";
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

        header("Location: ../reports/profit_loss.php?company_id=$company_id&from_import=1");
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['import_result'] = [
            'success' => false,
            'message' => "Income import failed: " . $e->getMessage()
        ];
        header("Location: income.php");
        exit;
    }
}

header("Location: income.php");
exit;
?>