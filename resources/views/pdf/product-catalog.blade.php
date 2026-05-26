<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Catalog</title>
    <style>
        @page {
            margin: 15mm 10mm;
            size: A4 landscape;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 9pt;
            line-height: 1.3;
            color: #000;
            padding: 20px;
        }
        
        /* Header */
        .header {
            text-align: center;
            border-bottom: 3px solid #2c3e50;
            padding-bottom: 12px;
            margin-bottom: 15px;
        }
        
        .company-name {
            font-size: 20pt;
            font-weight: bold;
            color: #2c3e50;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 4px;
        }
        
        .doc-title {
            font-size: 14pt;
            font-weight: bold;
            color: #27ae60;
            margin-top: 6px;
        }
        
        .contact-info {
            font-size: 8pt;
            color: #7f8c8d;
            margin-top: 4px;
        }
        
        /* Info Bar */
        .info-bar {
            background: #ecf0f1;
            padding: 8px 12px;
            margin-bottom: 12px;
            border-left: 4px solid #3498db;
            font-size: 8pt;
        }
        
        .info-bar::after {
            content: "";
            display: table;
            clear: both;
        }
        
        .info-left {
            float: left;
        }
        
        .info-right {
            float: right;
        }
        
        /* Table */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        
        thead {
            background: #34495e;
            color: white;
        }
        
        th {
            padding: 6px 8px;
            text-align: left;
            font-weight: bold;
            font-size: 8pt;
            border: 1px solid #2c3e50;
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
        
        tbody tr:nth-child(even) {
            background: #f9f9f9;
        }
        
        tbody tr:hover {
            background: #f0f0f0;
        }
        
        td {
            padding: 5px 8px;
            font-size: 8pt;
            border: 1px solid #ddd;
        }
        
        td.center {
            text-align: center;
        }
        
        td.right {
            text-align: right;
        }
        
        .out-of-stock {
            color: #e74c3c;
            font-weight: bold;
        }
        
        .low-stock {
            color: #f39c12;
            font-weight: bold;
        }
        
        /* Footer */
        .footer {
            text-align: center;
            margin-top: 15px;
            padding-top: 10px;
            border-top: 2px solid #ecf0f1;
            font-size: 7pt;
            color: #7f8c8d;
        }
        
        /* Empty column for manual entry */
        .manual-entry {
            background: #fff;
            min-height: 20px;
        }
        
        /* Print optimization */
        @media print {
            body {
                margin: 0;
                padding: 10mm;
            }
        }
    </style>
</head>
<body>
    {{-- Header --}}
    <div class="header">
        <div class="company-name">Horizon Metals</div>
        <div class="doc-title">Product Catalog</div>
        <div class="contact-info">
            📞 0912667771 | 📧 horizonmetals@gmail.com | 📍 Addis Ababa, Ethiopia
        </div>
    </div>

    {{-- Info Bar --}}
    <div class="info-bar">
        <div class="info-left">
            @if($category_filter)
                <strong>Category:</strong> {{ $category_filter }} | 
            @endif
            <strong>Filter:</strong> 
            @if($stock_filter === 'in_stock')
                In Stock Only
            @elseif($stock_filter === 'out_of_stock')
                Out of Stock Only
            @else
                All Products
            @endif
        </div>
        <div class="info-right">
            <strong>Generated:</strong> {{ $generated_at }} | 
            <strong>Total Products:</strong> {{ $products->count() }}
        </div>
    </div>

    {{-- Products Table --}}
    <table>
        <thead>
            <tr>
                <th style="width: 5%;" class="center">No.</th>
                <th style="width: {{ $include_cost_price ? '25%' : ($include_selling_price ? '35%' : '45%') }};">Product Name</th>
                <th style="width: {{ $include_cost_price ? '15%' : ($include_selling_price ? '20%' : '25%') }};">Category</th>
                <th style="width: 10%;" class="center">Unit</th>
                <th style="width: 10%;" class="center">Stock</th>
                
                @if($include_cost_price)
                    <th style="width: 12%;" class="right">Purchase Price</th>
                    <th style="width: 13%;" class="center">Other Costs</th>
                @endif
                
                @if($include_selling_price)
                    <th style="width: {{ $include_cost_price ? '10%' : '15%' }};" class="right">Selling Price</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @forelse($products as $index => $product)
            <tr>
                <td class="center">{{ $index + 1 }}</td>
                <td>{{ $product->name }}</td>
                <td>{{ $product->category?->name ?? 'N/A' }}</td>
                <td class="center">{{ $product->unit_type }}</td>
                <td class="center {{ $product->current_stock <= 0 ? 'out-of-stock' : ($product->current_stock < 10 ? 'low-stock' : '') }}">
                    {{ number_format($product->current_stock, 2) }}
                </td>
                
                @if($include_cost_price)
                    <td class="right">{{ number_format($product->purchase_price, 2) }}</td>
                    <td class="manual-entry center">_____________</td>
                @endif
                
                @if($include_selling_price)
                    <td class="right">{{ number_format($product->selling_price, 2) }}</td>
                @endif
            </tr>
            @empty
            <tr>
                <td colspan="{{ 5 + ($include_cost_price ? 2 : 0) + ($include_selling_price ? 1 : 0) }}" class="center">
                    No products found matching the selected filters.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    {{-- Footer --}}
    <div class="footer">
        @if($include_cost_price)
            <strong>Note:</strong> Purchase prices and "Other Costs" column are for internal use only. 
            Use the "Other Costs" column to manually calculate additional expenses (transport, loading, etc.).
            <br>
        @endif
        This catalog was generated on {{ $generated_at }}. Prices and stock levels are subject to change.
    </div>
</body>
</html>
