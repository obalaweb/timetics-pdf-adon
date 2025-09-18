<?php


// Sample data for demonstration purposes
$data = [
    'customer_name' => 'John Doe',
    'invoice_date' => '2024-06-01',
    'invoice_number' => 'INV-1001',
    'service_code' => 'SC123',

    'icd_code' => 'ICD456',
    'service_description' => 'Medical Consultation',
    'unit_price' => 1500.00,
    'due_date' => '2024-06-15',
];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tax Invoice <?php echo $data['invoice_number']; ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            color: #333;
            line-height: 1.4;
            background-color: white;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 30px;
            padding-bottom: 20px;
            min-height: 80px;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-table td {
            vertical-align: top;
            padding: 0;
        }

        .header-left {
            width: 33.33%;
            text-align: left;
            padding-right: 15px;
        }

        .header-center {
            width: 33.33%;
            text-align: left;
            padding: 0 15px;
        }

        .header-right {
            width: 33.33%;
            text-align: left;
            padding-left: 15px;
        }

        .invoice-title {
            font-size: 24px;
            font-weight: bold;
            margin: 0 0 10px 0;
            color: #333;
        }

        .customer-name {
            font-size: 14px;
            font-weight: bold;
            margin-top: 10px;
        }

        .invoice-details {
            font-size: 14px;
        }

        .invoice-details p {
            margin: 8px 0;
            line-height: 1.3;
        }

        .company-name {
            font-weight: bold;
            font-size: 14px;
            color: #333;
            margin-bottom: 8px;
        }

        .company-info {
            font-size: 11px;
            color: #666;
            line-height: 1.4;
        }

        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin: 30px 0;
            table-layout: fixed;
        }

        .invoice-table th {
            background-color: #f5f5f5;
            padding: 12px 8px;
            text-align: left;
            font-size: 13px;
            font-weight: bold;
            vertical-align: top;
            /* Removed border */
        }

        .invoice-table td {
            padding: 12px 8px;
            font-size: 13px;
            vertical-align: top;
            /* Removed border */
        }

        .invoice-table th:first-child,
        .invoice-table td:first-child {
            width: 40%;
        }

        .invoice-table th:nth-child(2),
        .invoice-table td:nth-child(2) {
            width: 25%;
        }

        .invoice-table th:nth-child(3),
        .invoice-table td:nth-child(3) {
            width: 12%;
        }

        .invoice-table th:nth-child(4),
        .invoice-table td:nth-child(4) {
            width: 12%;
        }

        .invoice-table th:nth-child(5),
        .invoice-table td:nth-child(5) {
            width: 15%;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .totals-row td {
            font-weight: normal;
        }

        .totals-row td:nth-child(4) {
            font-weight: bold;
            text-align: right;
        }

        .totals-row td:nth-child(5) {
            font-weight: bold;
            text-align: right;
        }

        .amount-due-row td {
            font-weight: bold;
            background-color: #f0f8ff;
        }

        .amount-due-row td:nth-child(4),
        .amount-due-row td:nth-child(5) {
            font-size: 14px;
        }

        .due-date-section {
            margin-top: 30px;
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 5px;
        }

        .due-date-title {
            font-weight: bold;
            font-size: 16px;
            margin-bottom: 15px;
            text-align: left;
            color: #333;
        }

        .due-date-section p {
            margin: 10px 0;
            font-size: 12px;
            line-height: 1.5;
            color: #555;
        }

        /* Ensure single page layout */
        @page {
            size: A4;
            margin: 15mm;
        }

        @media print {
            body {
                margin: 0;
                padding: 15px;
                font-size: 12px;
            }

            .header {
                margin-bottom: 20px;
                padding-bottom: 15px;
            }

            .invoice-table {
                margin: 20px 0;
            }

            .due-date-section {
                margin-top: 20px;
                padding: 15px;
            }

            /* Prevent page breaks in critical sections */
            .header,
            .totals-row,
            .amount-due-row {
                page-break-inside: avoid;
            }

            .invoice-table {
                page-break-inside: auto;
            }

            .invoice-table tr {
                page-break-inside: avoid;
            }
        }
    </style>
</head>

<body>
    <div class="header">
        <table class="header-table">
            <tr>
                <td class="header-left">
                    <div class="invoice-title">TAX INVOICE</div>
                    <div class="customer-section">
                        <div class="customer-name"><?php echo $data['customer_name'] ?></div>
                    </div>
                </td>

                <td class="header-center">
                    <div class="invoice-details">
                        <p><strong>Invoice Date:</strong><br><?php echo $data['invoice_date'] ?></p>
                        <p><strong>Invoice Number:</strong><br><?php echo $data['invoice_number'] ?></p>
                    </div>
                </td>

                <td class="header-right">
                    <div class="company-name">Dr Ben</div>
                    <div class="company-info">
                        MP0953814 – PR1153307<br>
                        Office A2, 1st floor Polo Village Offices<br>
                        Val de Vie, Paarl, Western Cape<br>
                        7636, South Africa
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <table class="invoice-table">
        <thead>
            <tr style="border-bottom: 2px solid #333;">
                <th>Item</th>
                <th>Item Description</th>
                <th>Quantity</th>
                <th>Unit Price</th>
                <th>Amount ZAR</th>
            </tr>
        </thead>
        <tbody>
            <tr style="border-bottom: 1px solid #4e4d4dff;">
                <td>
                    <?php echo $data['service_code'] ?><br>
                    <?php echo $data['icd_code'] ?>
                </td>
                <td><?php echo $data['service_description'] ?></td>
                <td class="text-center">1.00</td>
                <td class="text-right"><?php echo number_format($data['unit_price'], 2) ?></td>
                <td class="text-right"><?php echo number_format($data['unit_price'], 2) ?></td>
            </tr>
            <tr class="totals-row">
                <td></td>
                <td></td>
                <td></td>
                <td class="text-right" style="border-bottom: 1px solid #333;">Subtotal</td>
                <td class="text-right" style="border-bottom: 1px solid #333;"><?php echo number_format($data['unit_price'], 2) ?></td>
            </tr>
            <tr class="totals-row">
                <td></td>
                <td></td>
                <td></td>
                <td class="text-right" style="border-bottom: 2px solid #333;">TOTAL VAT</td>
                <td class="text-right" style="border-bottom: 2px solid #333;">0.00</td>
            </tr>
            <tr class="totals-row">
                <td></td>
                <td></td>
                <td></td>
                <td class="text-right" style="border-bottom: 2px solid #333;">TOTAL ZAR</td>
                <td class="text-right" style="border-bottom: 2px solid #333;"><?php echo number_format($data['unit_price'], 2) ?></td>
            </tr>
            <tr class="totals-row">
                <td></td>
                <td></td>
                <td></td>
                <td class="text-right" style="border-bottom: 2px solid #333;">Less Amount Paid</td>
                <td class="text-right" style="border-bottom: 2px solid #333;"><?php echo number_format($data['unit_price'], 2) ?></td>
            </tr>
            <tr class="amount-due-row">
                <td></td>
                <td></td>
                <td></td>
                <td class="text-right">AMOUNT DUE ZAR</td>
                <td class="text-right">0.00</td>
            </tr>
        </tbody>
    </table>

    <div class="due-date-section">
        <p class="due-date-title">Due Date: <?php echo $data['due_date'] ?></p>
        <p>This is a cash practice. The patient agrees to submit any medical aid claims independently.</p>
        <p>The patient indemnifies and hold harmless Dr Ben Coetsee and his staff from any claims, liability, or damages arising from this consultation or treatment.</p>
        <p>The patient confirms to have read, understood, and agree to the above terms.</p>
    </div>
</body>

</html>