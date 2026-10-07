<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 30px 34px; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #0f1e3c; font-family: DejaVu Sans, sans-serif; font-size: 9px; line-height: 1.42; }
        .header, .information, .items, .footer { border-collapse: collapse; width: 100%; }
        .header td, .information td, .footer td { vertical-align: top; }
        .merchant { width: 58%; }
        .merchant-name { color: #0a1934; font-size: 22px; font-weight: bold; letter-spacing: .3px; line-height: 1.05; }
        .merchant-tagline { color: #35425a; font-size: 11px; font-weight: bold; letter-spacing: .5px; margin-top: 3px; }
        .merchant-detail { color: #4f5e77; font-size: 8.5px; line-height: 1.55; margin-top: 4px; }
        .contact-mark { color: #196ff0; display: inline-block; font-weight: bold; width: 14px; }
        .brand { text-align: right; width: 42%; }
        .brand-logo { display: inline-block; height: 58px; max-width: 82px; object-fit: contain; vertical-align: middle; }
        .brand-mark { background: #1676f7; border-radius: 8px; color: #ffffff; display: inline-block; font-size: 27px; font-weight: bold; height: 54px; line-height: 54px; min-width: 54px; text-align: center; vertical-align: middle; }
        .brand-copy { display: inline-block; margin-left: 10px; text-align: left; vertical-align: middle; }
        .brand-name { color: #081936; font-size: 22px; font-weight: bold; letter-spacing: .5px; line-height: 1.05; }
        .brand-links { color: #4f5e77; font-size: 7.5px; font-weight: bold; letter-spacing: 2px; margin-top: 8px; white-space: nowrap; }
        .rule { border-top: 1.5px solid #50617e; height: 1px; margin: 18px 0 17px; }
        .information { border-bottom: 1.2px solid #53627b; }
        .information td { border-right: 1px solid #d6dfec; padding: 0 13px 16px; }
        .information td:first-child { padding-left: 0; }
        .information td:last-child { border-right: 0; padding-right: 0; }
        .payment { width: 32%; }
        .shipping { width: 37%; }
        .invoice { width: 31%; }
        .info-heading { background: #edf6ff; border-radius: 5px; color: #102144; font-size: 11px; font-weight: bold; margin-bottom: 10px; padding: 8px 9px; text-transform: uppercase; }
        .info-line { color: #33435d; font-size: 8.6px; line-height: 1.72; }
        .info-icon { color: #4b5d7a; display: inline-block; font-weight: bold; text-align: center; width: 18px; }
        .status { background: #d5f7df; border-radius: 7px; color: #168341; display: inline-block; font-size: 8.5px; font-weight: bold; margin-top: 8px; padding: 7px 10px; }
        .status.unpaid, .status.failed { background: #ffe4e7; color: #d64257; }
        .status.refunded { background: #fff0cf; color: #a56a07; }
        .invoice-value { color: #102144; float: right; font-weight: bold; }
        .invoice-status { background: #1676f7; border-radius: 7px; color: #ffffff; display: inline-block; font-weight: bold; margin-top: 5px; padding: 6px 12px; }
        .items { margin-top: 20px; }
        .items th { background: #eaf5ff; color: #0f1e3c; font-size: 9px; font-weight: bold; padding: 11px 8px; text-align: left; }
        .items th.right, .items td.right { text-align: right; }
        .items td { border-bottom: 1px solid #dbe3ed; padding: 11px 8px; vertical-align: middle; }
        .qty { text-align: center; width: 7%; }
        .item { width: 50%; }
        .price { width: 14%; }
        .tax { width: 10%; }
        .tax-amount { width: 11%; }
        .subtotal { width: 13%; }
        .product-image { background: #f4f6fa; border-radius: 6px; height: 62px; object-fit: contain; vertical-align: middle; width: 62px; }
        .product-placeholder { background: #eef4fb; border-radius: 6px; color: #5a6c8b; display: inline-block; font-size: 20px; font-weight: bold; height: 62px; line-height: 62px; text-align: center; vertical-align: middle; width: 62px; }
        .item-copy { display: inline-block; margin-left: 12px; max-width: 240px; vertical-align: middle; }
        .product-name { color: #102144; font-size: 10px; font-weight: bold; line-height: 1.3; }
        .product-detail { color: #60708a; font-size: 8.2px; line-height: 1.45; }
        .money { color: #17253e; font-size: 9px; }
        .strong { font-weight: bold; }
        .totals { border-left: 1px solid #d8e0eb; margin-left: auto; margin-top: 15px; padding-left: 18px; width: 47%; }
        .total-row { padding: 4px 0; }
        .total-label { color: #293a57; font-size: 9px; }
        .total-value { color: #102144; float: right; font-size: 9px; }
        .discount .total-value { color: #126df2; font-weight: bold; }
        .total-rule { border-top: 1.5px solid #50617e; margin: 9px 0 6px; }
        .grand-total { background: #eaf5ff; border-radius: 4px; padding: 9px 10px; }
        .grand-total .total-label { color: #0d1d3b; font-size: 13px; font-weight: bold; }
        .grand-total .total-value { color: #126df2; font-size: 16px; font-weight: bold; }
        .footer-rule { border-top: 1.5px solid #50617e; height: 1px; margin: 24px 0 14px; }
        .thanks-icon { background: #1676f7; border-radius: 50%; color: #ffffff; font-size: 22px; height: 52px; line-height: 52px; text-align: center; width: 52px; }
        .thanks-copy { border-left: 1px solid #c6d2e2; color: #102144; font-size: 12px; font-weight: bold; padding: 8px 18px; vertical-align: middle !important; }
        .thanks-subtitle { color: #63718a; font-size: 8.5px; font-weight: normal; margin-top: 4px; }
        .footer-note { color: #63718a; font-size: 8px; padding-left: 16px; text-align: right; vertical-align: middle !important; width: 28%; }
    </style>
</head>
<body>
    @php
        $branding = ($branding ?? []) + ['name' => config('app.name', 'E-KHMER'), 'tagline' => 'E-Commerce Store', 'logo' => null, 'mark' => 'E', 'address' => [], 'email' => null, 'phone' => null];
        $shipping = $order->shipping_address ?: $order->billing_address;
        $currencySymbol = match ($order->currency ?? 'USD') { 'EUR' => '€', 'KHR' => '៛', default => '$' };
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
                @foreach($branding['address'] as $addressLine)
                    <div class="merchant-detail"><span class="contact-mark">●</span>{{ $addressLine }}</div>
                @endforeach
                @if($branding['email'])<div class="merchant-detail"><span class="contact-mark">@</span>{{ $branding['email'] }}</div>@endif
                @if($branding['phone'])<div class="merchant-detail"><span class="contact-mark">+</span>{{ $branding['phone'] }}</div>@endif
            </td>
            <td class="brand">
                @if($branding['logo'])
                    <img class="brand-logo" src="{{ $branding['logo'] }}" alt="{{ $branding['name'] }} logo">
                @else
                    <span class="brand-mark">{{ $branding['mark'] }}</span>
                @endif
                <span class="brand-copy">
                    <span class="brand-name">{{ $branding['name'] }}</span><br>
                    <span class="brand-links">SHOP · DISCOVER · SUPPORT</span>
                </span>
            </td>
        </tr>
    </table>

    <div class="rule"></div>

    <table class="information">
        <tr>
            <td class="payment">
                <div class="info-heading">Payment information</div>
                <div class="info-line"><span class="info-icon">●</span>{{ $order->customer_name ?: 'Customer' }}</div>
                <div class="info-line"><span class="info-icon">▣</span>{{ $order->payment ? ucfirst($order->payment->method) : 'Payment pending' }}</div>
                <div class="info-line"><span class="info-icon">▦</span>{{ $order->placed_at?->format('M d, Y H:i') }}</div>
                <span class="status {{ $paymentClass }}">{{ $order->payment_status === 'paid' ? '✓ Payment Completed' : ucfirst($order->payment_status) }}</span>
            </td>
            <td class="shipping">
                <div class="info-heading">Shipping address</div>
                @foreach($shippingLines as $index => $line)
                    <div class="info-line"><span class="info-icon">{{ $index === 0 ? '●' : '•' }}</span>{{ $line }}</div>
                @endforeach
            </td>
            <td class="invoice">
                <div class="info-heading">Invoice <span class="invoice-value">#{{ $order->order_number }}</span></div>
                <div class="info-line"><span class="info-icon">▤</span>Order date: <span class="invoice-value">{{ $order->placed_at?->format('M d, Y H:i') }}</span></div>
                <div class="info-line"><span class="info-icon">◆</span>Total amount: <span class="invoice-value">{{ $currencySymbol }}{{ number_format((float) $order->total, 2) }}</span></div>
                <div class="info-line"><span class="info-icon">▣</span>Payment method: <span class="invoice-value">{{ $order->payment ? ucfirst($order->payment->method) : '—' }}</span></div>
                <div class="info-line"><span class="info-icon">▰</span>Order status: <span class="invoice-status">{{ ucfirst($order->status) }}</span></div>
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
                <th class="tax-amount right">Tax amount</th>
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
                    <td class="qty money">{{ $item->quantity }}</td>
                    <td class="item">
                        @if($item->receipt_image)
                            <img class="product-image" src="{{ $item->receipt_image }}" alt="">
                        @else
                            <span class="product-placeholder">{{ mb_strtoupper(mb_substr($item->product_name, 0, 1)) }}</span>
                        @endif
                        <span class="item-copy">
                            <span class="product-name">{{ $item->product_name }}</span><br>
                            <span class="product-detail">{{ $item->variant_label ?: 'Product order item' }}</span><br>
                            <span class="product-detail">SKU: {{ $item->sku ?: '—' }}</span>
                        </span>
                    </td>
                    <td class="price right money">{{ $currencySymbol }}{{ number_format((float) $item->unit_price, 2) }}</td>
                    <td class="tax right money">{{ $taxRate > 0 ? number_format($taxRate * 100, 0).'%' : '—' }}</td>
                    <td class="tax-amount right money">{{ $currencySymbol }}{{ number_format($lineTax, 2) }}</td>
                    <td class="subtotal right money strong">{{ $currencySymbol }}{{ number_format($itemSubtotal, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <div class="total-row"><span class="total-label">Subtotal ({{ $order->items->sum('quantity') }} items)</span><span class="total-value">{{ $currencySymbol }}{{ number_format((float) $order->subtotal, 2) }}</span></div>
        @if((float) $order->discount_amount > 0)
            <div class="total-row discount"><span class="total-label">Discount</span><span class="total-value">-{{ $currencySymbol }}{{ number_format((float) $order->discount_amount, 2) }}</span></div>
        @endif
        <div class="total-row"><span class="total-label">Shipping</span><span class="total-value">{{ $currencySymbol }}{{ number_format((float) $order->shipping_amount, 2) }}</span></div>
        <div class="total-row"><span class="total-label">Tax{{ $taxRate > 0 ? ' ('.number_format($taxRate * 100, 0).'%)' : '' }}</span><span class="total-value">{{ $currencySymbol }}{{ number_format((float) $order->tax_amount, 2) }}</span></div>
        <div class="total-rule"></div>
        <div class="grand-total"><span class="total-label">GRAND TOTAL</span><span class="total-value">{{ $currencySymbol }}{{ number_format((float) $order->total, 2) }}</span></div>
    </div>

    <div class="footer-rule"></div>
    <table class="footer">
        <tr>
            <td style="width: 58px;"><div class="thanks-icon">▣</div></td>
            <td class="thanks-copy">Thank you for shopping with {{ $branding['name'] }}!<div class="thanks-subtitle">We appreciate your support and look forward to serving you again.</div></td>
            <td class="footer-note">Please keep this receipt for your records.<br>Issued by {{ $branding['name'] }}</td>
        </tr>
    </table>
</body>
</html>
