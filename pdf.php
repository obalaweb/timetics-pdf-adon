<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tax Invoice INV-0255</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            color: #333;
            line-height: 1.4;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #333;
        }
        
        .company-info {
            font-size: 12px;
            margin-bottom: 20px;
            text-align: center;
            color: #666;
        }
        
        .invoice-title {
            font-size: 24px;
            font-weight: bold;
            margin: 20px 0;
            text-align: center;
        }
        
        .invoice-details {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        
        .bill-to, .invoice-info {
            width: 48%;
        }
        
        .bill-to h3, .invoice-info h3 {
            margin-bottom: 10px;
            font-size: 14px;
            color: #333;
        }
        
        .bill-to p, .invoice-info p {
            margin: 5px 0;
            font-size: 13px;
        }
        
        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin: 30px 0;
        }
        
        .invoice-table th {
            background-color: #f5f5f5;
            padding: 12px 8px;
            text-align: left;
            border: 1px solid #ddd;
            font-size: 13px;
            font-weight: bold;
        }
        
        .invoice-table td {
            padding: 12px 8px;
            border: 1px solid #ddd;
            font-size: 13px;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .totals {
            margin-top: 20px;
            width: 100%;
        }
        
        .totals table {
            width: 300px;
            margin-left: auto;
            border-collapse: collapse;
        }
        
        .totals td {
            padding: 8px 12px;
            border: 1px solid #ddd;
            font-size: 13px;
        }
        
        .totals .total-label {
            font-weight: bold;
            background-color: #f5f5f5;
        }
        
        .amount-due {
            font-weight: bold;
            font-size: 16px;
            background-color: #e8f4f8;
        }
        
        .terms {
            margin-top: 30px;
            font-size: 11px;
            line-height: 1.5;
            color: #555;
            border-top: 1px solid #ddd;
            padding-top: 20px;
        }
        
        .due-date {
            font-weight: bold;
            margin: 20px 0;
            font-size: 14px;
        }
        
        @media print {
            body {
                margin: 0;
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-info">
            Company Registration No: 2024/748523/21<br>
            Registered Office: DR Ben Coetsee MP0953814 – PR1153307<br>
            Office A2, 1st floor Polo Village Offices, Val de Vie, Paarl, Western Cape, 7636, South Africa
        </div>
        
        <div class="invoice-title">TAX INVOICE</div>
    </div>
    
    <div class="invoice-details">
        <div class="bill-to">
            <h3>Bill To:</h3>
            <p><strong><?php echo esc_html($data['customer_name'] ?? 'Vanessa Barnes'); ?></strong></p>
        </div>
        
        <div class="invoice-info">
            <p><strong>Invoice Date:</strong> <?php echo esc_html($data['invoice_date'] ?? '8 Aug 2025'); ?></p>
            <p><strong>Invoice Number:</strong> <?php echo esc_html($data['invoice_number'] ?? 'INV-0255'); ?></p>
            <br>
            <p><strong>Dr Ben</strong><br>
            MP0953814 – PR1153307<br>
            A2 Office<br>
            1st Floor<br>
            Polo Village Offices<br>
            Val de Vie<br>
            Paarl<br>
            7636</p>
        </div>
    </div>
    
    <table class="invoice-table">
        <thead>
            <tr>
                <th>Item Description</th>
                <th class="text-center">Quantity</th>
                <th class="text-right">Unit Price</th>
                <th class="text-right">Amount ZAR</th>
            </tr>
        </thead>
        <tbody>
            <?php if (isset($data['items']) && is_array($data['items'])): ?>
                <?php foreach ($data['items'] as $item): ?>
                <tr>
                    <td>
                        <strong><?php echo esc_html($item['service_code'] ?? 'Code 0190'); ?></strong><br>
                        <?php echo esc_html($item['icd_code'] ?? 'Z00.0'); ?><br>
                        <?php echo esc_html($item['description'] ?? 'General Consultation (Blood Results)'); ?>
                    </td>
                    <td class="text-center"><?php echo number_format($item['quantity'] ?? 1.00, 2); ?></td>
                    <td class="text-right"><?php echo number_format($item['unit_price'] ?? 960.00, 2); ?></td>
                    <td class="text-right"><?php echo number_format($item['subtotal'] ?? 960.00, 2); ?></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
            <tr>
                <td>
                    <strong>Code 0190</strong><br>
                    Z00.0<br>
                    General Consultation (Blood Results)
                </td>
                <td class="text-center">1.00</td>
                <td class="text-right">960.00</td>
                <td class="text-right">960.00</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
    
    <div class="totals">
        <table>
            <tr>
                <td class="total-label">Subtotal</td>
                <td class="text-right"><?php echo number_format($data['subtotal'] ?? 960.00, 2); ?></td>
            </tr>
            <tr>
                <td class="total-label">TOTAL VAT</td>
                <td class="text-right"><?php echo number_format($data['vat_amount'] ?? 0.00, 2); ?></td>
            </tr>
            <tr>
                <td class="total-label">TOTAL ZAR</td>
                <td class="text-right"><?php echo number_format($data['total_amount'] ?? 960.00, 2); ?></td>
            </tr>
            <tr>
                <td class="total-label">Less Amount Paid</td>
                <td class="text-right"><?php echo number_format($data['amount_paid'] ?? 960.00, 2); ?></td>
            </tr>
            <tr class="amount-due">
                <td class="total-label">AMOUNT DUE ZAR</td>
                <td class="text-right"><?php echo number_format($data['amount_due'] ?? 0.00, 2); ?></td>
            </tr>
        </table>
    </div>
    
    <div class="due-date">
        Due Date: <?php echo esc_html($data['due_date'] ?? '8 Aug 2025'); ?>
    </div>
    
    <div class="terms">
        <p><strong>Terms and Conditions:</strong></p>
        <p>This is a cash practice. The patient agrees to submit any medical aid claims independently.</p>
        <p>The patient indemnifies and hold harmless Dr Ben Coetsee and his staff from any claims, liability, or damages arising from this consultation or treatment.</p>
        <p>The patient confirms to have read, understood, and agree to the above terms.</p>
    </div>
</body>
</html>