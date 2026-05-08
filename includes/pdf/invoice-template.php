<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice - <?php echo $order['order_number']; ?></title>
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 12px;
            color: #333;
            line-height: 1.5;
            margin: 0;
            padding: 0;
        }
        .invoice-box {
            max-width: 800px;
            margin: auto;
            padding: 30px;
            border: 1px solid #eee;
            background: #fff;
        }
        .header {
            border-bottom: 2px solid #b8892a;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        .header table {
            width: 100%;
        }
        .logo {
            font-size: 28px;
            font-weight: bold;
            color: #b8892a;
            text-transform: uppercase;
        }
        .store-info {
            text-align: right;
            font-size: 11px;
        }
        .invoice-details {
            width: 100%;
            margin-bottom: 20px;
        }
        .invoice-details td {
            width: 50%;
            vertical-align: top;
        }
        .section-title {
            font-size: 10px;
            font-weight: bold;
            color: #888;
            text-transform: uppercase;
            margin-bottom: 5px;
            border-bottom: 1px solid #eee;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .items-table th {
            background: #f8f8f8;
            border-bottom: 1px solid #eee;
            padding: 10px;
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
        }
        .items-table td {
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        .summary-wrapper {
            width: 100%;
        }
        .summary-table {
            width: 250px;
            float: right;
        }
        .summary-table td {
            padding: 5px 0;
        }
        .summary-table .label {
            text-align: left;
            color: #666;
        }
        .summary-table .value {
            text-align: right;
            font-weight: bold;
        }
        .summary-table .grand-total {
            font-size: 16px;
            color: #b8892a;
            border-top: 2px solid #b8892a;
            padding-top: 10px;
        }
        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            text-align: center;
            font-size: 10px;
            color: #999;
        }
        .terms {
            text-align: left;
            margin-top: 20px;
            font-size: 9px;
            line-height: 1.2;
        }
        .clearfix {
            clear: both;
        }
    </style>
</head>
<body>
    <div class="invoice-box">
        <div class="header">
            <table>
                <tr>
                    <td class="logo">DesiVastra</td>
                    <td class="store-info">
                        <strong>DesiVastra Luxury Fashion</strong><br>
                        123 Fashion Street, Surat, Gujarat - 395006<br>
                        Email: contact@desivastra.in | Phone: +91 9876543210<br>
                        <strong>GSTIN: 24AAAAA0000A1Z5</strong>
                    </td>
                </tr>
            </table>
        </div>

        <table class="invoice-details">
            <tr>
                <td>
                    <div class="section-title">Bill To / Ship To</div>
                    <strong><?php echo htmlspecialchars($order['customer_name']); ?></strong><br>
                    <?php 
                        $addr = json_decode($order['shipping_address'], true);
                        echo htmlspecialchars($addr['address_line1']) . ', ' . htmlspecialchars($addr['address_line2'] ?? '') . '<br>';
                        echo htmlspecialchars($addr['city']) . ', ' . htmlspecialchars($addr['state']) . ' - ' . htmlspecialchars($addr['pincode']) . '<br>';
                        echo 'Phone: ' . htmlspecialchars($order['customer_phone']);
                    ?>
                </td>
                <td style="text-align: right;">
                    <div class="section-title">Invoice Information</div>
                    <strong>Invoice #:</strong> <?php echo str_replace('DV-', 'INV-', $order['order_number']); ?><br>
                    <strong>Order ID:</strong> #<?php echo htmlspecialchars($order['order_number']); ?><br>
                    <strong>Date:</strong> <?php echo date('d M Y', strtotime($order['created_at'])); ?><br>
                    <strong>Payment:</strong> <?php echo strtoupper(htmlspecialchars($order['payment_method'])); ?> (<?php echo strtoupper(htmlspecialchars($order['payment_status'])); ?>)
                </td>
            </tr>
        </table>

        <table class="items-table">
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Product Description</th>
                    <th style="text-align: center;">Qty</th>
                    <th style="text-align: right;">Price</th>
                    <th style="text-align: right;">GST</th>
                    <th style="text-align: right;">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['sku'] ?? 'N/A'); ?></td>
                    <td>
                        <strong><?php echo htmlspecialchars($item['product_name']); ?></strong>
                        <?php if(!empty($item['size']) || !empty($item['color'])): ?>
                            <br><small>Variant: <?php echo htmlspecialchars($item['size'] . ' ' . $item['color']); ?></small>
                        <?php endif; ?>
                    </td>
                    <td style="text-align: center;"><?php echo $item['quantity']; ?></td>
                    <td style="text-align: right;"><?php echo number_format($item['price'], 2); ?></td>
                    <td style="text-align: right;"><?php echo number_format($item['total'] * 0.05, 2); ?> (5%)</td>
                    <td style="text-align: right;"><?php echo number_format($item['total'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="summary-wrapper">
            <table class="summary-table">
                <tr>
                    <td class="label">Subtotal</td>
                    <td class="value">₹<?php echo number_format($order['subtotal'], 2); ?></td>
                </tr>
                <tr>
                    <td class="label">Shipping</td>
                    <td class="value">₹<?php echo number_format($order['shipping_cost'], 2); ?></td>
                </tr>
                <?php if ($order['discount'] > 0): ?>
                <tr>
                    <td class="label">Discount</td>
                    <td class="value">-₹<?php echo number_format($order['discount'], 2); ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td class="label grand-total">Grand Total</td>
                    <td class="value grand-total">₹<?php echo number_format($order['total'], 2); ?></td>
                </tr>
            </table>
        </div>
        <div class="clearfix"></div>

        <div class="footer">
            <p>Thank you for shopping with DesiVastra! We appreciate your business.</p>
            <div class="terms">
                <strong>Terms & Conditions:</strong><br>
                1. This is a computer-generated invoice and does not require a physical signature.<br>
                2. Goods once sold can only be returned according to our return policy within 7 days.<br>
                3. All disputes are subject to Surat Jurisdiction only.
            </div>
        </div>
    </div>
</body>
</html>