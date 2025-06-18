<?php

// Define the chart of accounts based on the Excel file
$chart_of_accounts = [
    // Revenue
    'Receipts' => [
        'main_category' => 'Revenue',
        'sub_category1' => '',
        'sub_category2' => '',
        'account_type' => 'Revenue',
        'default_account_code' => '4000' // Tithes
    ],
    'Other Income' => [
        'main_category' => 'Revenue',
        'sub_category1' => '',
        'sub_category2' => '',
        'account_type' => 'Revenue',
        'default_account_code' => '4100' // Offerings
    ],
    
    // Cost of Services
    'Salaries and Wages- Direct Cost' => [
        'main_category' => 'Cost of Services',
        'sub_category1' => 'Cost of Services',
        'sub_category2' => '',
        'account_type' => 'Expense',
        'default_account_code' => '5000' // Salaries
    ],
    'Property Maintenance' => [
        'main_category' => 'Cost of Services',
        'sub_category1' => 'Cost of Services',
        'sub_category2' => '',
        'account_type' => 'Expense',
        'default_account_code' => '5100' // Facility Costs
    ],
    'Small Tools and Equipment' => [
        'main_category' => 'Cost of Services',
        'sub_category1' => 'Cost of Services',
        'sub_category2' => '',
        'account_type' => 'Expense',
        'default_account_code' => '5100' // Facility Costs
    ],
    'Purchases' => [
        'main_category' => 'Cost of Services',
        'sub_category1' => 'Cost of Services',
        'sub_category2' => '',
        'account_type' => 'Expense',
        'default_account_code' => '5100' // Facility Costs
    ],
    
    // General and Administrative Expenses
    'Office Supplies' => [
        'main_category' => 'Expenses',
        'sub_category1' => 'General and Administrative Cost',
        'sub_category2' => '',
        'account_type' => 'Expense',
        'default_account_code' => '5400' // Administrative
    ],
    'Salaries and Wages- Administrative' => [
        'main_category' => 'Expenses',
        'sub_category1' => 'General and Administrative Cost',
        'sub_category2' => '',
        'account_type' => 'Expense',
        'default_account_code' => '5000' // Salaries
    ],
    'Depreciation Expense' => [
        'main_category' => 'Expenses',
        'sub_category1' => 'General and Administrative Cost',
        'sub_category2' => '',
        'account_type' => 'Expense',
        'default_account_code' => '5400' // Administrative
    ],
    'Repairs and Maintenance' => [
        'main_category' => 'Expenses',
        'sub_category1' => 'General and Administrative Cost',
        'sub_category2' => '',
        'account_type' => 'Expense',
        'default_account_code' => '5100' // Facility Costs
    ],
    'Utilities' => [
        'main_category' => 'Expenses',
        'sub_category1' => 'General and Administrative Cost',
        'sub_category2' => '',
        'account_type' => 'Expense',
        'default_account_code' => '5100' // Facility Costs
    ],
    'Meals and Allowance' => [
        'main_category' => 'Expenses',
        'sub_category1' => 'General and Administrative Cost',
        'sub_category2' => '',
        'account_type' => 'Expense',
        'default_account_code' => '5400' // Administrative
    ],
    'Professional Expense' => [
        'main_category' => 'Expenses',
        'sub_category1' => 'General and Administrative Cost',
        'sub_category2' => '',
        'account_type' => 'Expense',
        'default_account_code' => '5400' // Administrative
    ],
    'Taxes and Licenses' => [
        'main_category' => 'Expenses',
        'sub_category1' => 'General and Administrative Cost',
        'sub_category2' => '',
        'account_type' => 'Expense',
        'default_account_code' => '5400' // Administrative
    ],
    'Government Mandatory Benefits' => [
        'main_category' => 'Expenses',
        'sub_category1' => 'General and Administrative Cost',
        'sub_category2' => '',
        'account_type' => 'Expense',
        'default_account_code' => '5400' // Administrative
    ],
    'Ministry Expenses' => [
        'main_category' => 'Expenses',
        'sub_category1' => 'General and Administrative Cost',
        'sub_category2' => '',
        'account_type' => 'Expense',
        'default_account_code' => '5200' // Ministry Expenses
    ],
    'Bad Debt' => [
        'main_category' => 'Expenses',
        'sub_category1' => 'General and Administrative Cost',
        'sub_category2' => '',
        'account_type' => 'Expense',
        'default_account_code' => '5400' // Administrative
    ],
    'Interest Expense' => [
        'main_category' => 'Expenses',
        'sub_category1' => 'General and Administrative Cost',
        'sub_category2' => '',
        'account_type' => 'Expense',
        'default_account_code' => '5400' // Administrative
    ],
    'Provision for Income Tax' => [
        'main_category' => 'Expenses',
        'sub_category1' => 'General and Administrative Cost',
        'sub_category2' => '',
        'account_type' => 'Expense',
        'default_account_code' => '5400' // Administrative
    ],
    
    // Assets
    'Cash on Hand' => [
        'main_category' => 'Balance Sheet',
        'sub_category1' => 'Current Assets',
        'sub_category2' => 'Cash and Cash Equivalent',
        'account_type' => 'Asset',
        'default_account_code' => '1000' // Cash
    ],
    'Cash in Bank' => [
        'main_category' => 'Balance Sheet',
        'sub_category1' => 'Current Assets',
        'sub_category2' => 'Cash and Cash Equivalent',
        'account_type' => 'Asset',
        'default_account_code' => '1000' // Cash
    ],
    'Accounts Receivable' => [
        'main_category' => 'Balance Sheet',
        'sub_category1' => 'Current Assets',
        'sub_category2' => 'Receivables',
        'account_type' => 'Asset',
        'default_account_code' => '1100' // Accounts Receivable
    ],
    'Advances to Officers/ Employees' => [
        'main_category' => 'Balance Sheet',
        'sub_category1' => 'Current Assets',
        'sub_category2' => 'Receivables',
        'account_type' => 'Asset',
        'default_account_code' => '1100' // Accounts Receivable
    ],
    'Other Receivables' => [
        'main_category' => 'Balance Sheet',
        'sub_category1' => 'Current Assets',
        'sub_category2' => 'Receivables',
        'account_type' => 'Asset',
        'default_account_code' => '1100' // Accounts Receivable
    ],
    'Allowance for Bad Debt' => [
        'main_category' => 'Balance Sheet',
        'sub_category1' => 'Current Assets',
        'sub_category2' => 'Receivables',
        'account_type' => 'Asset',
        'default_account_code' => '1100' // Accounts Receivable (negative)
    ],
    'Furniture and Fixtures' => [
        'main_category' => 'Balance Sheet',
        'sub_category1' => 'Non Current Assets',
        'sub_category2' => 'Property and Equipments',
        'account_type' => 'Asset',
        'default_account_code' => '1300' // Equipment
    ],
    'Equipments' => [
        'main_category' => 'Balance Sheet',
        'sub_category1' => 'Non Current Assets',
        'sub_category2' => 'Property and Equipments',
        'account_type' => 'Asset',
        'default_account_code' => '1300' // Equipment
    ],
    'Land' => [
        'main_category' => 'Balance Sheet',
        'sub_category1' => 'Non Current Assets',
        'sub_category2' => 'Property and Equipments',
        'account_type' => 'Asset',
        'default_account_code' => '1300' // Equipment
    ],
    'Lease Improvements' => [
        'main_category' => 'Balance Sheet',
        'sub_category1' => 'Non Current Assets',
        'sub_category2' => 'Property and Equipments',
        'account_type' => 'Asset',
        'default_account_code' => '1300' // Equipment
    ],
    'Accumulated Depreciation' => [
        'main_category' => 'Balance Sheet',
        'sub_category1' => 'Non Current Assets',
        'sub_category2' => 'Property and Equipments',
        'account_type' => 'Asset',
        'default_account_code' => '1400' // Accumulated Depreciation
    ],
    
    // Liabilities
    'Accounts Payable' => [
        'main_category' => 'Balance Sheet',
        'sub_category1' => 'Current Liability',
        'sub_category2' => '',
        'account_type' => 'Liability',
        'default_account_code' => '2000' // Accounts Payable
    ],
    'Notes Payable' => [
        'main_category' => 'Balance Sheet',
        'sub_category1' => 'Non Current Liability',
        'sub_category2' => '',
        'account_type' => 'Liability',
        'default_account_code' => '2200' // Long-Term Loans
    ],
    'Other Payable' => [
        'main_category' => 'Balance Sheet',
        'sub_category1' => 'Current Liability',
        'sub_category2' => '',
        'account_type' => 'Liability',
        'default_account_code' => '2000' // Accounts Payable
    ],
    'Advances from Officers/ Employees' => [
        'main_category' => 'Balance Sheet',
        'sub_category1' => 'Current Liability',
        'sub_category2' => '',
        'account_type' => 'Liability',
        'default_account_code' => '2000' // Accounts Payable
    ],
    
    // Equity
    'Capital Equity' => [
        'main_category' => 'Equity',
        'sub_category1' => 'Equity',
        'sub_category2' => '',
        'account_type' => 'Equity',
        'default_account_code' => '3000' // General Fund
    ],
    'Excess of Expense over Revenue' => [
        'main_category' => 'Equity',
        'sub_category1' => 'Equity',
        'sub_category2' => '',
        'account_type' => 'Equity',
        'default_account_code' => '3200' // Retained Earnings
    ],
];

// Function to map an account to the chart of accounts
function mapAccount($account_name, $category = '', $sub_category = '') {
    global $chart_of_accounts;
    
    // Clean the account name for comparison
    $account_name = trim($account_name);
    if (empty($account_name)) {
        return [
            'account_code' => '4000', // Default to Tithes for income
            'category' => 'Revenue',
            'sub_category' => 'Other'
        ];
    }
    
    // Check if the account name exists in the chart of accounts
    if (isset($chart_of_accounts[$account_name])) {
        $account = $chart_of_accounts[$account_name];
        return [
            'account_code' => $account['default_account_code'],
            'category' => $account['main_category'],
            'sub_category' => !empty($account['sub_category1']) ? $account['sub_category1'] : $account['sub_category2']
        ];
    }
    
    // If not found, try to match based on category and subcategory
    foreach ($chart_of_accounts as $name => $account) {
        $matches_category = empty($category) || stripos($account['main_category'], $category) !== false;
        $matches_subcategory = empty($sub_category) || 
                             stripos($account['sub_category1'], $sub_category) !== false || 
                             stripos($account['sub_category2'], $sub_category) !== false;
                             
        if ($matches_category && $matches_subcategory) {
            return [
                'account_code' => $account['default_account_code'],
                'category' => $account['main_category'],
                'sub_category' => !empty($account['sub_category1']) ? $account['sub_category1'] : $account['sub_category2']
            ];
        }
    }
    
    // Default mapping if no match is found
    $default_account_code = '4000'; // Default for income
    $default_category = 'Revenue';
    $default_sub_category = 'Other';
    
    if (stripos($category, 'Expense') !== false || stripos($sub_category, 'Expense') !== false) {
        $default_account_code = '5400'; // Administrative
        $default_category = 'Expenses';
        $default_sub_category = 'General and Administrative Cost';
    } elseif (stripos($category, 'Cost') !== false || stripos($sub_category, 'Cost') !== false) {
        $default_account_code = '5100'; // Facility Costs
        $default_category = 'Cost of Services';
        $default_sub_category = 'Cost of Services';
    } elseif (stripos($category, 'Asset') !== false || stripos($sub_category, 'Asset') !== false) {
        $default_account_code = '1000'; // Cash
        $default_category = 'Balance Sheet';
        $default_sub_category = 'Current Assets';
    } elseif (stripos($category, 'Liability') !== false || stripos($sub_category, 'Liability') !== false) {
        $default_account_code = '2000'; // Accounts Payable
        $default_category = 'Balance Sheet';
        $default_sub_category = 'Current Liability';
    } elseif (stripos($category, 'Equity') !== false || stripos($sub_category, 'Equity') !== false) {
        $default_account_code = '3000'; // General Fund
        $default_category = 'Equity';
        $default_sub_category = 'Equity';
    }
    
    return [
        'account_code' => $default_account_code,
        'category' => $default_category,
        'sub_category' => $default_sub_category
    ];
}

?>