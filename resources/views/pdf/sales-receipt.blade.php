<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=3.0, user-scalable=yes">
    <title>Sales Receipt</title>
    <style>
        @page {
            margin: 20mm 15mm;
            size: A4 portrait;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 10pt;
            line-height: 1.4;
            color: #000;
            padding: 30px;
            margin: 0;
        }
        
        /* Header Section */
        .header {
            border-bottom: 3px solid #2c3e50;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        
        .header-row {
            width: 100%;
        }
        
        .header-row::after {
            content: "";
            display: table;
            clear: both;
        }
        
        .logo-section {
            float: left;
            width: 15%;
        }
        
        .logo {
            width: 60px;
            height: 60px;
            background: #f0f0f0;
            border: 2px dashed #999;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 8pt;
            color: #999;
        }
        
        .company-section {
            float: left;
            width: 60%;
            text-align: center;
            padding: 0 10px;
        }
        
        .company-name {
            font-size: 18pt;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .tagline {
            font-size: 9pt;
            color: #7f8c8d;
            font-style: italic;
        }
        
        .contact-section {
            float: right;
            width: 25%;
            text-align: right;
            font-size: 9pt;
        }
        
        .contact-item {
            margin-bottom: 3px;
            color: #2c3e50;
        }
        
        /* Document Title */
        .doc-title {
            text-align: center;
            margin: 15px 0;
        }
        
        .receipt-label {
            font-size: 20pt;
            font-weight: bold;
            color: #27ae60;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        /* Info Bar */
        .info-bar {
            background: #ecf0f1;
            padding: 10px 15px;
            margin-bottom: 20px;
            border-left: 4px solid #27ae60;
        }
        
        .info-bar::after {
            content: "";
            display: table;
            clear: both;
        }
        
        .info-left {
            float: left;
            width: 50%;
        }
        
        .info-right {
            float: right;
            width: 50%;
            text-align: right;
        }
        
        .info-label {
            font-weight: bold;
            font-size: 9pt;
            color: #2c3e50;
        }
        
        .info-value {
            font-size: 10pt;
            color: #000;
        }
        
        /* Table Styles */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        
        thead {
            background: #34495e;
            color: white;
        }
        
        th {
            padding: 5px 8px;
            text-align: left;
            font-weight: bold;
            font-size: 9pt;
            border: none;
        }
        
        th.center {
            text-align: center;
        }
        
        th.right {
            text-align: right;
        }
        
        tbody tr {
            border-bottom: 1px solid #ddd;
        }
        
        tbody tr:last-child {
            border-bottom: 2px solid #34495e;
        }
        
        td {
            padding: 4px 8px;
            font-size: 9pt;
        }
        
        td.center {
            text-align: center;
        }
        
        td.right {
            text-align: right;
        }
        
        /* Summary Section */
        .summary-section {
            width: 100%;
            margin-top: 20px;
        }
        
        .summary-section::after {
            content: "";
            display: table;
            clear: both;
        }
        
        .payment-box {
            float: left;
            width: 48%;
            background: #e8f5e9;
            border: 1px solid #27ae60;
            border-left: 4px solid #27ae60;
            padding: 12px;
        }
        
        .payment-title {
            font-weight: bold;
            font-size: 9pt;
            color: #1e7e34;
            margin-bottom: 8px;
            text-transform: uppercase;
        }
        
        .payment-item {
            font-size: 9pt;
            margin-bottom: 4px;
            color: #000;
        }
        
        .totals-box {
            float: right;
            width: 48%;
        }
        
        .totals-table {
            width: 100%;
        }
        
        .totals-table tr {
            border-bottom: 1px solid #ddd;
        }
        
        .totals-table td {
            padding: 6px 12px;
            font-size: 10pt;
        }
        
        .totals-label {
            text-align: right;
            font-weight: 600;
            color: #2c3e50;
            width: 60%;
        }
        
        .totals-value {
            text-align: right;
            font-weight: bold;
            width: 40%;
        }
        
        .grand-total-row {
            background: #27ae60;
            color: white;
            font-size: 11pt;
        }
        
        .grand-total-row td {
            padding: 10px 12px;
        }
        
        /* Footer */
        .footer {
            text-align: center;
            margin-top: 25px;
            padding-top: 15px;
            border-top: 2px solid #ecf0f1;
            font-size: 9pt;
            font-style: italic;
            color: #7f8c8d;
        }
        
        /* Print optimization */
        @media print {
            body {
                margin: 0;
                padding: 15mm;
            }
        }
    </style>
</head>
<body>
    {{-- Header --}}
    <div class="header">
        <div class="header-row">
            <div class="logo-section">
                <div class="logo">LOGO</div>
            </div>
            
            <div class="company-section">
                <div class="company-name">Horizon Metals</div>
                <div class="tagline">For Perfect Structures</div>
            </div>
            
            <div class="contact-section">
                <div class="contact-item">0912667771</div>
                <div class="contact-item">horizonmetals@gmail.com</div>
                <div class="contact-item">Bale Robe, Oromiya, Ethiopia</div>
            </div>
        </div>
    </div>

    {{-- Document Title --}}
    <div class="doc-title">
        <span class="receipt-label">Sales Receipt</span>
    </div>

    {{-- Info Bar --}}
    <div class="info-bar">
        <div class="info-left">
            <div><span class="info-label">Customer:</span> <span class="info-value">{{ $customer_name }}</span></div>
            <div><span class="info-label">Receipt #:</span> <span class="info-value">{{ $receipt_number }}</span></div>
        </div>
        <div class="info-right">
            <div><span class="info-label">Date:</span> <span class="info-value">{{ $sale_date }}</span></div>
            <div><span class="info-label">Status:</span> <span class="info-value">{{ $status }}</span></div>
        </div>
    </div>

    {{-- Items Table --}}
    <table>
        <thead>
            <tr>
                <th style="width: 5%;" class="center">No.</th>
                <th style="width: 45%;">Product</th>
                <th style="width: 10%;" class="center">Qty</th>
                <th style="width: 20%;" class="right">Unit Price</th>
                <th style="width: 20%;" class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $index => $item)
            <tr>
                <td class="center">{{ $index + 1 }}</td>
                <td>{{ $item['product_name'] }}</td>
                <td class="center">{{ number_format($item['quantity'], 0) }}</td>
                <td class="right">{{ number_format($item['unit_price'], 2) }}</td>
                <td class="right"><strong>{{ number_format($item['total_price'], 2) }}</strong></td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Summary Section --}}
    <div class="summary-section">
        <div class="payment-box">
            <div class="payment-title">Payment Information</div>
            <div class="payment-item"><strong>Payment Type:</strong> {{ $payment_type }}</div>
            @if($payment_method)
            <div class="payment-item"><strong>Payment Method:</strong> {{ $payment_method }}</div>
            @endif
            @if($payment_account)
            <div class="payment-item"><strong>Account:</strong> {{ $payment_account }}</div>
            @endif
        </div>

        <div class="totals-box">
            <table class="totals-table">
                <tr class="grand-total-row">
                    <td class="totals-label">TOTAL AMOUNT:</td>
                    <td class="totals-value">ETB {{ number_format($total_amount, 2) }}</td>
                </tr>
            </table>
        </div>
    </div>

    {{-- Footer --}}
    <div class="footer">
        Thank you for your business! This is a computer-generated receipt.
    </div>
</body>
</html>
