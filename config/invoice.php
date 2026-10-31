<?php

return [
    'company_name' => env('COMPANY_NAME', 'Oggy Marble'),
    'company_address' => env('COMPANY_ADDRESS', 'Pakistan'),
    'company_phone' => env('COMPANY_PHONE', ''),
    'company_ntn' => env('COMPANY_NTN', ''),
    'company_strn' => env('COMPANY_STRN', ''),
    'sales_tax_percentage' => 17.0, // standard rate in Pakistan (display only)
    'withholding_tax_percentage' => 4.5, // standard rate in Pakistan (display only)
    'invoice_footer_notes' => '1. Payments are ledger-credited only. 2. This represents a computerized statement of account voucher.',
    'payment_terms' => 'Net 30 Days',
    'bank_account_details' => '',
    'default_currency' => 'PKR',
    'invoice_prefix' => 'SO-',
    'delivery_challan_prefix' => 'DC-',
];
