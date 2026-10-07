<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 26px 30px; }
        * { box-sizing: border-box; }
        body { color: #15233f; font-family: DejaVu Sans, sans-serif; font-size: 10px; line-height: 1.45; margin: 0; }
        .header, .info-grid, .items, .footer { border-collapse: collapse; width: 100%; }
        .header td, .info-grid td, .footer td { vertical-align: top; }

        .merchant { padding-right: 20px; width: 56%; }
        .merchant-name { color: #0b1d3a; font-size: 24px; font-weight: bold; letter-spacing: .1px; line-height: 1.1; }
        .merchant-tagline { color: #43536e; font-size: 11px; font-weight: bold; letter-spacing: .35px; margin-top: 3px; }
        .merchant-contact { color: #586982; font-size: 9px; line-height: 1.7; margin-top: 11px; }
        .merchant-contact div { margin-top: 2px; }
        .contact-dot { background: #1877f2; border-radius: 50%; display: inline-block; height: 5px; margin: 0 10px 1px 1px; vertical-align: middle; width: 5px; }
        .brand { text-align: right; width: 44%; }
        .brand-logo { display: inline-block; height: 66px; max-width: 72px; object-fit: contain; vertical-align: middle; }
        .brand-mark { background: #1977f3; border-radius: 9px; color: #ffffff; display: inline-block; font-size: 31px; font-weight: bold; height: 60px; line-height: 60px; min-width: 60px; text-align: center; vertical-align: middle; }
        .brand-copy { display: inline-block; margin-left: 11px; text-align: left; vertical-align: middle; }
        .brand-name { color: #0a1d3a; font-size: 25px; font-weight: bold; letter-spacing: .15px; line-height: 1.08; }
        .brand-links { color: #63728b; font-size: 7px; font-weight: bold; letter-spacing: 1.8px; margin-top: 8px; white-space: nowrap; }
        .rule { border-top: 1.5px solid #9caabc; height: 1px; margin: 19px 0 18px; }

        .info-grid { border-bottom: 1.5px solid #9caabc; }
        .info-grid td { border-right: 1px solid #d8e2ee; padding: 0 15px 17px; }
        .info-grid td:first-child { padding-left: 0; }
        .info-grid td:last-child { border-right: 0; padding-right: 0; }
        .payment { width: 32%; }
        .shipping { width: 36%; }
        .invoice { width: 32%; }
        .panel-heading { background: #edf6ff; border: 1px solid #e0efff; border-radius: 6px; color: #102344; font-size: 11px; font-weight: bold; margin-bottom: 10px; padding: 8px 9px; text-transform: uppercase; }
        .detail-line { color: #44556f; font-size: 9px; line-height: 1.75; }
        .detail-dot { background: #72849e; border-radius: 50%; display: inline-block; height: 5px; margin: 0 8px 1px 0; vertical-align: middle; width: 5px; }
        .detail-line.primary { color: #182845; font-size: 9.5px; font-weight: bold; }
        .payment-status { border-radius: 7px; display: inline-block; font-size: 8.8px; font-weight: bold; margin-top: 8px; padding: 7px 10px; }
        .payment-status.paid { background: #dff8e8; color: #178143; }
        .payment-status.unpaid, .payment-status.failed { background: #ffe5e8; color: #d54259; }
        .payment-status.refunded { background: #fff0d2; color: #9d690d; }
        .invoice-row { color: #4b5b73; font-size: 8.8px; line-height: 1.82; }
        .invoice-row strong { color: #162640; float: right; font-weight: bold; }
        .invoice-number { color: #102344; float: right; font-size: 10px; font-weight: bold; text-transform: none; }
        .order-status { background: #1977f3; border-radius: 6px; color: #ffffff; float: right; font-size: 8px; font-weight: bold; line-height: 1.2; margin-top: 3px; padding: 5px 10px; }

        .items { margin-top: 20px; table-layout: fixed; }
        .items th { background: #eaf5ff; color: #102344; font-size: 9px; font-weight: bold; padding: 12px 8px; text-align: left; }
        .items th.right, .items td.right { text-align: right; }
        .items td { border-bottom: 1px solid #dce5ef; padding: 12px 8px; vertical-align: middle; }
        .items tr { page-break-inside: avoid; }
        .qty { text-align: center; width: 7%; }
        .item { width: 44%; }
        .price { width: 12%; }
        .tax { width: 8%; }
        .tax-amount { width: 13%; }
        .subtotal { width: 16%; }
        .product-image { background: #f4f7fb; border: 1px solid #e0e8f2; border-radius: 7px; height: 68px; object-fit: contain; vertical-align: middle; width: 68px; }
        .product-placeholder { background: #edf4fd; border: 1px solid #e0e8f2; border-radius: 7px; color: #58708f; display: inline-block; font-size: 22px; font-weight: bold; height: 68px; line-height: 68px; text-align: center; vertical-align: middle; width: 68px; }
        .item-copy { display: inline-block; margin-left: 12px; max-width: 210px; vertical-align: middle; }
        .product-name { color: #132442; font-size: 10.5px; font-weight: bold; line-height: 1.3; }
        .product-detail { color: #65758d; font-size: 8.5px; line-height: 1.5; }
        .money { color: #162640; font-size: 9px; }
        .strong { font-weight: bold; }

        .totals { border-left: 1px solid #d7e1ed; margin-left: auto; margin-top: 16px; padding-left: 19px; width: 46%; }
        .total-row { padding: 4px 0; }
        .total-label { color: #31435f; font-size: 9.5px; }
        .total-value { color: #152641; float: right; font-size: 9.5px; }
        .discount .total-value { color: #1977f3; font-weight: bold; }
        .total-rule { border-top: 1.5px solid #7f8fa5; margin: 9px 0 7px; }
        .grand-total { background: #eaf5ff; border: 1px solid #dcecff; border-radius: 6px; padding: 10px 11px; }
        .grand-total .total-label { color: #0e2142; font-size: 13px; font-weight: bold; }
        .grand-total .total-value { color: #1674f0; font-size: 17px; font-weight: bold; }

        .footer-rule { border-top: 1.5px solid #9caabc; height: 1px; margin: 25px 0 15px; }
        .thanks-icon { background: #1977f3; border-radius: 50%; color: #ffffff; font-size: 24px; font-weight: bold; height: 56px; line-height: 56px; text-align: center; width: 56px; }
        .thanks-copy { border-left: 1px solid #c8d5e4; color: #102344; font-size: 12.5px; font-weight: bold; padding: 7px 18px; vertical-align: middle !important; }
        .thanks-subtitle { color: #63738b; font-size: 9px; font-weight: normal; margin-top: 4px; }
        .footer-note { color: #6b7c93; font-size: 8.5px; line-height: 1.55; text-align: right; vertical-align: middle !important; width: 27%; }
    </style>
</head>
<body>
    @php
        $branding = ($branding ?? []) + ['name' => config('app.name', 'E-KHMER'), 'tagline' => 'E-Commerce Store', 'logo' => null, 'mark' => 'E', 'address' => [], 'email' => null, 'phone' => null];
        $shipping = $order->shipping_address ?: $order->billing_address;
        $currencySymbol = match ($order->currency ?? 'USD') { 'EUR' => 'EUR ', 'KHR' => 'KHR ', default => '$' };
        $taxRate = (float) $order->subtotal > 0 ? ((float) $order->tax_amount / (float) $order->subtotal) : 0;
        $paymentClass = in_array($order->payment_status, ['unpaid', 'failed', 'refunded'], true) ? $order->payment_status : 'paid';
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
                <div class="merchant-tagline">{{ $branding['tagline'] }}</div>
                <div class="merchant-contact">
                    @foreach($branding['address'] as $addressLine)<div><span class="contact-dot"></span>{{ $addressLine }}</div>@endforeach
                    @if($branding['email'])<div><span class="contact-dot"></span>{{ $branding['email'] }}</div>@endif
                    @if($branding['phone'])<div><span class="contact-dot"></span>{{ $branding['phone'] }}</div>@endif
                </div>
            </td>
            <td class="brand">
                @if($branding['logo'])
                    <img class="brand-logo" src="{{ $branding['logo'] }}" alt="{{ $branding['name'] }} logo">
                @else
                    <span class="brand-mark">{{ $branding['mark'] }}</span>
                @endif
                <span class="brand-copy"><span class="brand-name">{{ $branding['name'] }}</span><br><span class="brand-links">SHOP  /  DISCOVER  /  SUPPORT</span></span>
            </td>
        </tr>
    </table>

    <div class="rule"></div>

    <table class="info-grid">
        <tr>
            <td class="payment">
                <div class="panel-heading">Payment information</div>
                <div class="detail-line primary"><span class="detail-dot"></span>{{ $order->customer_name ?: 'Customer' }}</div>
                <div class="detail-line"><span class="detail-dot"></span>{{ $order->payment ? ucfirst($order->payment->method) : 'Payment pending' }}</div>
                <div class="detail-line"><span class="detail-dot"></span>{{ $order->placed_at?->format('M d, Y H:i') }}</div>
                <span class="payment-status {{ $paymentClass }}">{{ $order->payment_status === 'paid' ? 'Payment completed' : ucfirst($order->payment_status) }}</span>
            </td>
            <td class="shipping">
                <div class="panel-heading">Shipping address</div>
                @foreach($shippingLines as $index => $line)<div class="detail-line {{ $index === 0 ? 'primary' : '' }}"><span class="detail-dot"></span>{{ $line }}</div>@endforeach
            </td>
            <td class="invoice">
                <div class="panel-heading">Invoice <span class="invoice-number">#{{ $order->order_number }}</span></div>
                <div class="invoice-row">Order date <strong>{{ $order->placed_at?->format('M d, Y H:i') }}</strong></div>
                <div class="invoice-row">Total amount <strong>{{ $currencySymbol }}{{ number_format((float) $order->total, 2) }}</strong></div>
                <div class="invoice-row">Payment method <strong>{{ $order->payment ? ucfirst($order->payment->method) : 'N/A' }}</strong></div>
                <div class="invoice-row">Order status <span class="order-status">{{ ucfirst($order->status) }}</span></div>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th class="qty">Qty</th><th class="item">Items</th><th class="price right">Price</th><th class="tax right">Tax</th><th class="tax-amount right">Tax amount</th><th class="subtotal right">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $item)
                @php $lineTax = round((float) $item->line_total * $taxRate, 2); @endphp
                <tr>
                    <td class="qty money">{{ $item->quantity }}</td>
                    <td class="item">
                        @if($item->receipt_image)<img class="product-image" src="{{ $item->receipt_image }}" alt="">@else<span class="product-placeholder">{{ mb_strtoupper(mb_substr($item->product_name, 0, 1)) }}</span>@endif
                        <span class="item-copy"><span class="product-name">{{ $item->product_name }}</span><br><span class="product-detail">{{ $item->variant_label ?: 'Product order item' }}</span><br><span class="product-detail">SKU: {{ $item->sku ?: 'N/A' }}</span></span>
                    </td>
                    <td class="price right money">{{ $currencySymbol }}{{ number_format((float) $item->unit_price, 2) }}</td>
                    <td class="tax right money">{{ $taxRate > 0 ? number_format($taxRate * 100, 0).'%' : '-' }}</td>
                    <td class="tax-amount right money">{{ $currencySymbol }}{{ number_format($lineTax, 2) }}</td>
                    <td class="subtotal right money strong">{{ $currencySymbol }}{{ number_format((float) $item->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <div class="total-row"><span class="total-label">Subtotal ({{ $order->items->sum('quantity') }} items)</span><span class="total-value">{{ $currencySymbol }}{{ number_format((float) $order->subtotal, 2) }}</span></div>
        @if((float) $order->discount_amount > 0)<div class="total-row discount"><span class="total-label">Discount</span><span class="total-value">-{{ $currencySymbol }}{{ number_format((float) $order->discount_amount, 2) }}</span></div>@endif
        <div class="total-row"><span class="total-label">Shipping</span><span class="total-value">{{ $currencySymbol }}{{ number_format((float) $order->shipping_amount, 2) }}</span></div>
        <div class="total-row"><span class="total-label">Tax{{ $taxRate > 0 ? ' ('.number_format($taxRate * 100, 0).'%)' : '' }}</span><span class="total-value">{{ $currencySymbol }}{{ number_format((float) $order->tax_amount, 2) }}</span></div>
        <div class="total-rule"></div>
        <div class="grand-total"><span class="total-label">GRAND TOTAL</span><span class="total-value">{{ $currencySymbol }}{{ number_format((float) $order->total, 2) }}</span></div>
    </div>

    <div class="footer-rule"></div>
    <table class="footer">
        <tr>
            <td style="width: 62px;"><div class="thanks-icon">OK</div></td>
            <td class="thanks-copy">Thank you for shopping with {{ $branding['name'] }}!<div class="thanks-subtitle">We appreciate your support and look forward to serving you again.</div></td>
            <td class="footer-note">This receipt is your proof of purchase.<br>Issued by {{ $branding['name'] }}</td>
        </tr>
    </table>
</body>
</html>
