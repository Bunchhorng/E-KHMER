<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 34px 40px; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #171717; font-family: DejaVu Sans, sans-serif; font-size: 9px; line-height: 1.42; }
        .header, .info-grid, .items, .receipt-footer { width: 100%; border-collapse: collapse; }
        .header td, .info-grid td, .receipt-footer td { vertical-align: top; }
        .merchant { width: 63%; }
        .merchant-name { color: #080808; font-size: 19px; font-weight: bold; letter-spacing: -0.3px; line-height: 1.15; }
        .merchant-detail { color: #363636; font-size: 8px; line-height: 1.65; }
        .brand-logo-cell { width: 37%; text-align: right; }
        .brand-logo { display: inline-block; max-width: 160px; max-height: 60px; object-fit: contain; }
        .brand-mark { display: inline-block; border: 2px solid #101010; color: #101010; font-size: 21px; font-weight: bold; line-height: 50px; min-width: 50px; padding: 0 10px; text-align: center; }
        .rule { border-top: 1.5px solid #111111; height: 1px; margin: 16px 0; }
        .info-grid { margin-bottom: 16px; }
        .info-grid td { padding-right: 15px; }
        .info-grid td:last-child { padding-right: 0; }
        .payment-column { width: 33%; }
        .shipping-column { width: 35%; }
        .invoice-column { width: 32%; text-align: right; }
        .section-title { color: #111111; font-size: 12px; font-weight: bold; line-height: 1.2; text-transform: uppercase; }
        .info-line { color: #343434; font-size: 8.5px; line-height: 1.55; }
        .invoice-number { color: #111111; font-size: 15px; font-weight: bold; line-height: 1.2; }
        .invoice-meta { color: #454545; font-size: 8px; line-height: 1.65; }
        .invoice-meta strong { color: #111111; }
        .items { border-top: 1.5px solid #111111; }
        .items th { border-bottom: 1.5px solid #111111; color: #252525; font-size: 8px; font-weight: bold; padding: 9px 4px; text-align: left; text-transform: uppercase; }
        .items th.right, .items td.right { text-align: right; }
        .items td { padding: 11px 4px; vertical-align: middle; }
        .qty { width: 7%; text-align: center; }
        .item { width: 47%; }
        .price { width: 15%; }
        .tax { width: 12%; }
        .subtotal { width: 19%; }
        .product-image { border: 1px solid #e0e0e0; height: 58px; object-fit: contain; vertical-align: middle; width: 58px; }
        .product-placeholder { background: #f4f4f4; border: 1px solid #e0e0e0; color: #777777; display: inline-block; font-size: 20px; font-weight: bold; height: 58px; line-height: 58px; text-align: center; vertical-align: middle; width: 58px; }
        .item-description { display: inline-block; margin-left: 11px; max-width: 225px; vertical-align: middle; }
        .product-name { color: #111111; font-size: 9px; font-weight: bold; line-height: 1.25; }
        .sku { color: #555555; font-size: 8px; line-height: 1.35; }
        .amount { color: #1e1e1e; font-size: 8.5px; }
        .bold { font-weight: bold; }
        .totals { margin-left: auto; margin-top: 18px; width: 43%; }
        .total-row { min-height: 21px; padding: 4px 2px; }
        .total-label { color: #282828; font-size: 8.5px; font-weight: bold; }
        .total-value { color: #222222; float: right; font-size: 8.5px; }
        .total-rule { border-top: 1.5px solid #111111; margin: 8px 0; }
        .grand-total .total-label, .grand-total .total-value { color: #111111; font-size: 10px; font-weight: bold; }
        .footer-rule { border-top: 1.5px solid #111111; height: 1px; margin: 21px 0 13px; }
        .thank-you { color: #242424; font-size: 8.5px; font-weight: bold; }
        .barcode { color: #050505; font-family: DejaVu Sans Mono, monospace; font-size: 23px; font-weight: bold; letter-spacing: -1.2px; line-height: 1; margin: 13px 0 1px; overflow: hidden; white-space: nowrap; }
        .barcode-number { color: #343434; font-size: 8px; letter-spacing: 1.2px; }
        .generated { color: #555555; font-size: 7.5px; text-align: right; vertical-align: bottom !important; }
    </style>
</head>
<body>
    @php
        $branding = $branding ?? ['name' => config('app.name', 'E-KHMER'), 'tagline' => 'E-Commerce Store', 'logo' => null, 'mark' => 'E'];
        $shipping = $order->shipping_address ?: $order->billing_address;
        $currencySymbol = match ($order->currency ?? 'USD') { 'EUR' => '€', 'KHR' => '៛', default => '$' };
        $taxRate = (float) $order->subtotal > 0 ? ((float) $order->tax_amount / (float) $order->subtotal) : 0;
        $barcode = str_repeat('|', 3).implode(' ', str_split(preg_replace('/[^A-Za-z0-9]/', '', $order->order_number))).str_repeat('|', 3);
        $merchantLines = array_filter([$branding['tagline']]);
        $shippingLines = is_array($shipping) ? array_filter([
            $order->customer_name,
            $shipping['address_line1'] ?? $shipping['address_line'] ?? null,
            $shipping['address_line2'] ?? null,
            trim(implode(', ', array_filter([$shipping['city'] ?? null, $shipping['state'] ?? null])) . ' ' . ($shipping['postal_code'] ?? '')),
            $shipping['country'] ?? null,
        ]) : array_filter([$order->customer_name, $order->email, $order->phone]);
    @endphp

    <table class="header">
        <tr>
            <td class="merchant">
                <div class="merchant-name">{{ $branding['name'] }}</div>
                @foreach($merchantLines as $line)
                    <div class="merchant-detail">{{ $line }}</div>
                @endforeach
            </td>
            <td class="brand-logo-cell">
                @if($branding['logo'])
                    <img class="brand-logo" src="{{ $branding['logo'] }}" alt="{{ $branding['name'] }} logo">
                @else
                    <span class="brand-mark">{{ $branding['mark'] }}</span>
                @endif
            </td>
        </tr>
    </table>

    <div class="rule"></div>

    <table class="info-grid">
        <tr>
            <td class="payment-column">
                <div class="section-title">Payment information</div>
                <div class="info-line">{{ $order->customer_name ?: 'Customer' }}</div>
                @if($order->payment)<div class="info-line">{{ ucfirst($order->payment->method) }} · {{ ucfirst($order->payment_status) }}</div>@endif
                @if($order->email)<div class="info-line">{{ $order->email }}</div>@endif
                @if($order->phone)<div class="info-line">{{ $order->phone }}</div>@endif
            </td>
            <td class="shipping-column">
                <div class="section-title">Shipping address</div>
                @foreach($shippingLines as $line)
                    <div class="info-line">{{ $line }}</div>
                @endforeach
            </td>
            <td class="invoice-column">
                <div class="section-title">Invoice</div>
                <div class="invoice-number">#{{ $order->order_number }}</div>
                <div class="invoice-meta"><strong>Amount:</strong> {{ $currencySymbol }}{{ number_format((float) $order->total, 2) }}</div>
                <div class="invoice-meta"><strong>Order date:</strong> {{ $order->placed_at?->format('Y-m-d H:i') }}</div>
                <div class="invoice-meta"><strong>Payment:</strong> {{ ucfirst($order->payment_status) }}</div>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th class="qty">Qty</th>
                <th class="item">Items</th>
                <th class="price right">Price</th>
                <th class="tax right">Tax</th>
                <th class="subtotal right">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $item)
                @php
                    $lineTax = round((float) $item->line_total * $taxRate, 2);
                    $itemSubtotal = (float) $item->line_total + $lineTax;
                @endphp
                <tr>
                    <td class="qty amount">{{ $item->quantity }}</td>
                    <td class="item">
                        @if($item->receipt_image)
                            <img class="product-image" src="{{ $item->receipt_image }}" alt="">
                        @else
                            <span class="product-placeholder">{{ mb_strtoupper(mb_substr($item->product_name, 0, 1)) }}</span>
                        @endif
                        <span class="item-description">
                            <span class="product-name">{{ $item->product_name }}</span><br>
                            <span class="sku">SKU: {{ $item->sku ?: '—' }}@if($item->variant_label) · {{ $item->variant_label }}@endif</span>
                        </span>
                    </td>
                    <td class="price right amount">{{ $currencySymbol }}{{ number_format((float) $item->unit_price, 2) }}</td>
                    <td class="tax right amount">{{ $currencySymbol }}{{ number_format($lineTax, 2) }}</td>
                    <td class="subtotal right amount bold">{{ $currencySymbol }}{{ number_format($itemSubtotal, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <div class="total-row"><span class="total-label">Subtotal</span><span class="total-value">{{ $currencySymbol }}{{ number_format((float) $order->subtotal, 2) }}</span></div>
        @if((float) $order->discount_amount > 0)
            <div class="total-row"><span class="total-label">Discount</span><span class="total-value">-{{ $currencySymbol }}{{ number_format((float) $order->discount_amount, 2) }}</span></div>
        @endif
        <div class="total-row"><span class="total-label">Shipping</span><span class="total-value">{{ $currencySymbol }}{{ number_format((float) $order->shipping_amount, 2) }}</span></div>
        <div class="total-row"><span class="total-label">Tax</span><span class="total-value">{{ $currencySymbol }}{{ number_format((float) $order->tax_amount, 2) }}</span></div>
        <div class="total-rule"></div>
        <div class="total-row grand-total"><span class="total-label">GRAND TOTAL</span><span class="total-value">{{ $currencySymbol }}{{ number_format((float) $order->total, 2) }}</span></div>
    </div>

    <div class="footer-rule"></div>
    <table class="receipt-footer">
        <tr>
            <td>
                <div class="thank-you">Thank you for your purchase!</div>
                <div class="barcode">{{ $barcode }}</div>
                <div class="barcode-number">{{ $order->order_number }}</div>
            </td>
            <td class="generated">Generated by {{ $branding['name'] }}</td>
        </tr>
    </table>
</body>
</html>
