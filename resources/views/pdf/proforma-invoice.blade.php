<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Proforma Invoice</title>
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
        }logo-secti
        
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
            text-align: right;
            margin: 15px 0;
        }
        
        .proforma-label {
            font-size: 16pt;
            font-weight: bold;
            color: #3498db;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        /* Info Bar */
        .info-bar {
            background: #ecf0f1;
            padding: 10px 15px;
            margin-bottom: 20px;
            border-left: 4px solid #3498db;
        }
        
        .info-bar::after {
            content: "";
            display: table;
            clear: both;
        }
        
        .info-left {
            float: left;
            width: 60%;
        }
        
        .info-right {
            float: right;
            width: 40%;
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
            padding: 8px;
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
            padding: 6px 8px;
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
        
        .notes-box {
            float: left;
            width: 48%;
            background: #fff9e6;
            border: 1px solid #f39c12;
            border-left: 4px solid #f39c12;
            padding: 12px;
        }
        
        .notes-title {
            font-weight: bold;
            font-size: 9pt;
            color: #d68910;
            margin-bottom: 8px;
            text-transform: uppercase;
        }
        
        .note-item {
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
            background: #2c3e50;
            color: white;
            font-size: 11pt;
        }
        
        .grand-total-row td {
            padding: 10px 12px;
        }
        
        /* Bank Section */
        .bank-section {
            margin-top: 30px;
            border: 2px solid #34495e;
        }
        
        .bank-header {
            background: #34495e;
            color: white;
            padding: 8px;
            text-align: center;
            font-weight: bold;
            font-size: 10pt;
            text-transform: uppercase;
        }
        
        .bank-grid {
            padding: 15px;
        }
        
        .bank-grid::after {
            content: "";
            display: table;
            clear: both;
        }
        
        .bank-item {
            float: left;
            width: 33.33%;
            padding: 8px;
        }
        
        .bank-name {
            background: #27ae60;
            color: white;
            padding: 6px;
            text-align: center;
            font-weight: bold;
            font-size: 9pt;
            margin-bottom: 5px;
        }
        
        .bank-details {
            text-align: center;
            font-size: 12pt;
            line-height: 1.5;
        }
        
        .account-holder {
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 2px;
        }
        
        .account-num {
            color: #000;
            font-family: 'Courier New', monospace;
            font-size:17pt;
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
                <div class="logo">HMH</div>
            </div>
            
            <div class="company-section">
                <div class="company-name">Horizon Metal House</div>
                <div class="tagline">For Perfect Structures</div>
            </div>
            
            <div class="contact-section">
                <div class="contact-item">0912667771</div>
                <div class="contact-item">horizonmetal@gmail.com</div>
                <div class="contact-item">Bale Robe,Oromiya,Ethiopia</div>
            </div>
        </div>
    </div>

    {{-- Document Title --}}
    <div class="doc-title">
        <span class="proforma-label">Proforma Invoice</span>
    </div>

    {{-- Info Bar --}}
    <div class="info-bar">
        <div class="info-left">
            <span class="info-label">Customer:</span>
            <span class="info-value">{{ $customer_name }}</span>
        </div>
        <div class="info-right">
            <div><span class="info-label">Date:</span> <span class="info-value">{{ $date }}</span></div>
            <div><span class="info-label">Proforma #:</span> <span class="info-value">{{ $proforma_number }}</span></div>
        </div>
    </div>

    {{-- Items Table --}}
    <table>
        <thead>
            <tr>
                <th style="width: 5%;" class="center">No.</th>
                <th style="width: 40%;">Description</th>
                <th style="width: 12%;" class="center">Unit</th>
                <th style="width: 10%;" class="center">Qty</th>
                <th style="width: 15%;" class="right">Unit Price</th>
                <th style="width: 18%;" class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $index => $item)
            <tr>
                <td class="center">{{ $index + 1 }}</td>
                <td>{{ $item['description'] }}</td>
                <td class="center">{{ $item['unit'] }}</td>
                <td class="center">{{ number_format($item['quantity'], 0) }}</td>
                <td class="right">{{ number_format($item['unit_price'], 2) }}</td>
                <td class="right"><strong>{{ number_format($item['total'], 2) }}</strong></td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Summary Section --}}
    <div class="summary-section">
        <div class="notes-box">
            <div class="notes-title">Terms & Conditions</div>
            @if($include_vat ?? true)
            <div class="note-item">All prices are VAT inclusive</div>
            @endif
            <div class="note-item">Price validity: {{ $validity_days ?? 1 }} day(s)</div>
            <div class="note-item">Payment: 100% in advance</div>
        </div>

        <div class="totals-box">
            <table class="totals-table">
                <tr>
                    <td class="totals-label">Subtotal:</td>
                    <td class="totals-value">{{ number_format($subtotal, 2) }}</td>
                </tr>
                @if($include_vat ?? true)
                <tr>
                    <td class="totals-label">VAT ({{ $vat_rate ?? 15 }}%):</td>
                    <td class="totals-value">{{ number_format($vat_amount, 2) }}</td>
                </tr>
                @endif
                <tr class="grand-total-row">
                    <td class="totals-label">GRAND TOTAL:</td>
                    <td class="totals-value">ETB {{ number_format($grand_total, 2) }}</td>
                </tr>
            </table>
        </div>
    </div>

    {{-- Bank Information --}}
    <div class="bank-section">
        <div class="bank-header">Bank Account Information</div>
        <div class="bank-grid">
            <div class="bank-item">
                <div class="bank-name">CBE</div>
                <div class="bank-details">
                    <div class="account-holder">YAHYA ZAKRIYA AMAN</div>
                    <div class="account-num">1000352324888</div>
                </div>
            </div>
            
            <div class="bank-item">
                <div class="bank-name">AWASH</div>
                <div class="bank-details">
                    <div class="account-holder">YAHYA ZAKARIYA AMAN</div>
                    <div class="account-num">01425999966200</div>
                </div>
            </div>
            
            <div class="bank-item">
                <div class="bank-name">COOP</div>
                <div class="bank-details">
                    <div class="account-holder">YAHYA ZAKARIYA AMAN</div>
                    <div class="account-num">1041500204496</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Footer --}}
    <div class="footer">
        Thank you for your business! We look forward to working with you.
    </div>
</body>
</html>