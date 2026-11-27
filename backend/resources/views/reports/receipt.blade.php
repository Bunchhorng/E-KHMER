<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Order {{ $order->order_number }}</title>
    <style>
        @page { size: A4 portrait; margin: 14mm 14mm 16mm 14mm; }
        * { box-sizing: border-box; border-radius: 0; }
        body {
            background: #ffffff;
            color: #111827;
            font-family: DejaVu Sans, sans-serif;
            font-size: 9.5px;
            line-height: 1.45;
            margin: 0;
        }
        table { border-collapse: collapse; width: 100%; }
        td, th { vertical-align: top; }

        .header-table { border-bottom: 2px solid #1e3a8a; padding-bottom: 12px; }
        .header-table td { padding-bottom: 12px; }
        .brand-col { width: 58%; padding-right: 16px; }
        .brand-logo { display: inline-block; height: 52px; max-width: 140px; object-fit: contain; vertical-align: middle; }
        .brand-mark {
            background: #2563eb;
            color: #ffffff;
            display: inline-block;
            font-size: 22px;
            font-weight: bold;
            height: 46px;
            line-height: 46px;
            text-align: center;
            vertical-align: middle;
            width: 46px;
        }
        .brand-text { display: inline-block; margin-left: 10px; vertical-align: middle; }
        .merchant-name { color: #0f172a; font-size: 18px; font-weight: bold; letter-spacing: 0.3px; line-height: 1.15; }
        .merchant-tagline { color: #475569; font-size: 8.5px; font-weight: bold; letter-spacing: 0.4px; margin-top: 2px; text-transform: uppercase; }
        .merchant-contact { color: #475569; font-size: 8.5px; line-height: 1.55; margin-top: 8px; overflow-wrap: anywhere; word-wrap: break-word; }

        .doc-col { text-align: right; width: 42%; }
        .doc-title { color: #1e3a8a; font-size: 13px; font-weight: bold; letter-spacing: 0.5px; text-transform: uppercase; }
        .doc-ref { color: #0f172a; font-size: 12px; font-weight: bold; margin-top: 4px; }
        .doc-meta { color: #475569; font-size: 8.5px; margin-top: 3px; }
        .status-pill {
            border: 1px solid #cbd5e1;
            display: inline-block;
            font-size: 8px;
            font-weight: bold;
            margin-top: 6px;
            padding: 3px 8px;
            text-transform: uppercase;
        }
        .status-pill.paid { background: #ecfdf5; border-color: #6ee7b7; color: #065f46; }
        .status-pill.unpaid { background: #fffbeb; border-color: #fcd34d; color: #92400e; }
        .status-pill.failed { background: #fef2f2; border-color: #fca5a5; color: #991b1b; }
        .status-pill.refunded { background: #fff7ed; border-color: #fdba74; color: #9a3412; }

        .info-grid { margin-top: 14px; border: 1px solid #cbd5e1; }
        .info-grid td { border-right: 1px solid #cbd5e1; padding: 10px 12px; width: 33.33%; }
        .info-grid td:last-child { border-right: 0; }
        .panel-heading {
            background: #f1f5f9;
            border-bottom: 1px solid #cbd5e1;
            color: #0f172a;
            font-size: 8.5px;
            font-weight: bold;
            letter-spacing: 0.5px;
            margin: -10px -12px 8px;
            padding: 6px 12px;
            text-transform: uppercase;
        }
        .detail-line { color: #334155; font-size: 8.8px; line-height: 1.55; margin-bottom: 2px; overflow-wrap: anywhere; word-wrap: break-word; }
        .detail-line.primary { color: #0f172a; font-weight: bold; }
        .kv-row { color: #334155; font-size: 8.6px; line-height: 1.65; }
        .kv-row strong { color: #0f172a; float: right; }

        .items { margin-top: 14px; table-layout: fixed; border: 1px solid #cbd5e1; }
        .items thead { display: table-header-group; }
        .items th {
            background: #eff6ff;
            border-bottom: 1.5px solid #1e3a8a;
            color: #0f172a;
            font-size: 8.5px;
            font-weight: bold;
            padding: 8px 8px;
            text-align: left;
            text-transform: uppercase;
        }
        .items th.right, .items td.right { text-align: right; }
        .items th.center, .items td.center { text-align: center; }
        .items td { border-bottom: 1px solid #e2e8f0; padding: 9px 8px; vertical-align: top; }
        .items tr { page-break-inside: avoid; }

        .col-qty { width: 7%; }
        .col-item { width: 45%; }
        .col-price { width: 12%; }
        .col-tax { width: 8%; }
        .col-tax-amt { width: 12%; }
        .col-sub { width: 16%; }

        .item-wrap { width: 100%; }
        .item-wrap td { border: 0; padding: 0; }
        .item-thumb-cell { width: 46px; padding-right: 8px !important; }
        .product-image { background: #f8fafc; border: 1px solid #cbd5e1; height: 42px; object-fit: contain; width: 42px; }
        .product-placeholder {
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            color: #475569;
            display: inline-block;
            font-size: 14px;
            font-weight: bold;
            height: 42px;
            line-height: 42px;
            text-align: center;
            width: 42px;
        }
        .product-name { color: #0f172a; font-size: 9.5px; font-weight: bold; line-height: 1.3; overflow-wrap: anywhere; word-wrap: break-word; }
        .product-detail { color: #475569; font-size: 8px; line-height: 1.45; margin-top: 2px; overflow-wrap: anywhere; word-wrap: break-word; }
        .shop-tag { color: #1d4ed8; font-size: 8px; font-weight: bold; margin-top: 2px; }
        .money { color: #0f172a; font-size: 9px; white-space: nowrap; }
        .strong { font-weight: bold; }

        .summary-wrap { margin-top: 14px; page-break-inside: avoid; }
        .summary-note-col { width: 52%; padding-right: 16px; }
        .summary-totals-col { width: 48%; border: 1px solid #cbd5e1; padding: 10px 12px; background: #f8fafc; }
        .notice-box { background: #f8fafc; border: 1px solid #cbd5e1; border-left: 3px solid #2563eb; padding: 9px 11px; font-size: 8.5px; color: #334155; line-height: 1.5; }
        .notice-box.unpaid { border-left-color: #d97706; background: #fffbeb; color: #78350f; }

        .total-row { padding: 3px 0; overflow: hidden; }
        .total-label { color: #334155; font-size: 9px; }
        .total-value { color: #0f172a; float: right; font-size: 9px; font-weight: bold; }
        .discount .total-value { color: #dc2626; }
        .grand-total { border-top: 1.5px solid #1e3a8a; margin-top: 6px; padding-top: 7px; }
        .grand-total .total-label { color: #0f172a; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .grand-total .total-value { color: #1d4ed8; font-size: 13.5px; font-weight: bold; }

        .footer-block { border-top: 1px solid #cbd5e1; margin-top: 18px; padding-top: 10px; page-break-inside: avoid; }
        .footer-left { color: #334155; font-size: 8.5px; width: 60%; }
        .footer-right { color: #64748b; font-size: 8px; text-align: right; width: 40%; }
    </style>
</head>
<body>
    @php
        $branding = ($branding ?? []) + ['name' => config('app.name', 'E-KHMER'), 'tagline' => 'E-KHMER Online Marketplace', 'logo' => null, 'mark' => 'E', 'address' => [], 'email' => null, 'phone' => null];
        $rawName = trim((string) $branding['name']);
        $displayName = in_array($rawName, ['', 'Laravel', 'Ecommerce'], true) ? 'E-KHMER' : $rawName;
        $displayMark = mb_strtoupper(mb_substr($displayName, 0, 1)) ?: 'E';

        $logoSrc = $branding['logo'];
        if (!$logoSrc && $displayName === 'E-KHMER') {
            $defaultLogoPath = resource_path('assets/e-khmer-logo.jpg');
            if (is_file($defaultLogoPath)) {
                $logoSrc = 'data:image/jpeg;base64,' . base64_encode((string) file_get_contents($defaultLogoPath));
            }
        }

        $shipping = $order->shipping_address ?: $order->billing_address;
        $currencySymbol = match ($order->currency ?? 'USD') { 'EUR' => 'EUR ', 'KHR' => 'KHR ', default => '$' };
        $taxRate = (float) $order->subtotal > 0 ? ((float) $order->tax_amount / (float) $order->subtotal) : 0;

        $methodRaw = $order->payment?->method;
        $paymentMethodLabel = match ($methodRaw) {
            'cod' => 'Cash on Delivery',
            'bakong' => 'Bakong / KHQR',
            'card' => 'Credit / Debit Card',
            'bank' => 'Bank Transfer',
            'gateway' => 'Online Gateway',
            default => $methodRaw ? ucwords(str_replace('_', ' ', $methodRaw)) : 'Not specified',
        };

        $isPaid = $order->payment_status === 'paid';
        $paymentClass = match ($order->payment_status) {
            'paid' => 'paid',
            'failed', 'cancelled' => 'failed',
            'refunded', 'partially_refunded' => 'refunded',
            default => 'unpaid',
        };
        $paymentStatusLabel = match ($order->payment_status) {
            'paid' => 'Paid (Verified)',
            'partial' => 'Partially Paid',
            'partially_refunded' => 'Partially Refunded',
            'refunded' => 'Refunded',
            'failed' => 'Payment Failed',
            'cancelled' => 'Cancelled',
            default => $methodRaw === 'cod' ? 'Pending Cash Collection' : 'Unpaid / Awaiting Payment',
        };

        $documentTitle = match (true) {
            $isPaid => 'OFFICIAL PAYMENT RECEIPT',
            in_array($order->payment_status, ['refunded', 'partially_refunded'], true) => 'REFUND SUMMARY',
            default => 'ORDER CONFIRMATION / PRO FORMA',
        };
        $documentRefLabel = $isPaid ? 'Receipt #' : 'Reference #';

        $shippingLines = is_array($shipping) ? array_filter([
            $shipping['full_name'] ?? $order->customer_name,
            $shipping['address_line1'] ?? $shipping['address_line'] ?? null,
            $shipping['address_line2'] ?? null,
            trim(implode(', ', array_filter([$shipping['city'] ?? null, $shipping['state'] ?? null])) . ' ' . ($shipping['postal_code'] ?? '')),
            $shipping['country'] ?? null,
            $shipping['phone'] ?? $order->phone,
        ]) : array_filter([$order->customer_name, $order->email, $order->phone]);

        $uniqueShopCount = $order->items->pluck('shop_id')->filter()->unique()->count();
    @endphp

    <table class="header-table">
        <tr>
            <td class="brand-col">
                @if($logoSrc)
                    <img class="brand-logo" src="{{ $logoSrc }}" alt="{{ $displayName }} logo">
                @else
                    <span class="brand-mark">{{ $displayMark }}</span>
                @endif
                <span class="brand-text">
                    <div class="merchant-name">{{ $displayName }}</div>
                    <div class="merchant-tagline">{{ $branding['tagline'] === 'E-Commerce Store' ? 'E-KHMER Online Marketplace' : $branding['tagline'] }}</div>
                </span>
                @if(!empty($branding['address']) || $branding['email'] || $branding['phone'])
                    <div class="merchant-contact">
                        @foreach($branding['address'] as $addressLine)<div>{{ $addressLine }}</div>@endforeach
                        @if($branding['email'])<div>{{ $branding['email'] }}</div>@endif
                        @if($branding['phone'])<div>{{ $branding['phone'] }}</div>@endif
                    </div>
                @endif
            </td>
            <td class="doc-col">
                <div class="doc-title">{{ $documentTitle }}</div>
                <div class="doc-ref">{{ $documentRefLabel }}{{ $order->order_number }}</div>
                <div class="doc-meta">Order date: {{ $order->placed_at?->format('M d, Y H:i') ?? '—' }}</div>
                <span class="status-pill {{ $paymentClass }}">{{ $paymentStatusLabel }}</span>
            </td>
        </tr>
    </table>

    <table class="info-grid">
        <tr>
            <td>
                <div class="panel-heading">Customer &amp; Payment</div>
                <div class="detail-line primary">{{ $order->customer_name ?: 'Customer' }}</div>
                @if($order->email)<div class="detail-line">{{ $order->email }}</div>@endif
                @if($order->phone)<div class="detail-line">{{ $order->phone }}</div>@endif
                <div class="detail-line" style="margin-top: 4px;">Method: <strong>{{ $paymentMethodLabel }}</strong></div>
            </td>
            <td>
                <div class="panel-heading">Shipping Address</div>
                @foreach($shippingLines as $index => $line)
                    <div class="detail-line {{ $index === 0 ? 'primary' : '' }}">{{ $line }}</div>
                @endforeach
            </td>
            <td>
                <div class="panel-heading">Order Snapshot</div>
                <div class="kv-row">Order # <strong>{{ $order->order_number }}</strong></div>
                <div class="kv-row">Order status <strong>{{ ucfirst((string) $order->status) }}</strong></div>
                <div class="kv-row">Payment status <strong>{{ $paymentStatusLabel }}</strong></div>
                @if($order->payment?->paid_at)
                    <div class="kv-row">Paid at <strong>{{ \Illuminate\Support\Carbon::parse($order->payment->paid_at)->format('M d, Y H:i') }}</strong></div>
                @endif
                <div class="kv-row">Total <strong>{{ $currencySymbol }}{{ number_format((float) $order->total, 2) }}</strong></div>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th class="center col-qty">Qty</th>
                <th class="col-item">Item &amp; Variant</th>
                <th class="right col-price">Unit Price</th>
                <th class="right col-tax">Tax</th>
                <th class="right col-tax-amt">Tax Amt</th>
                <th class="right col-sub">Line Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $item)
                @php $lineTax = round((float) $item->line_total * $taxRate, 2); @endphp
                <tr>
                    <td class="center money">{{ $item->quantity }}</td>
                    <td>
                        <table class="item-wrap">
                            <tr>
                                <td class="item-thumb-cell">
                                    @if($item->receipt_image)
                                        <img class="product-image" src="{{ $item->receipt_image }}" alt="">
                                    @else
                                        <span class="product-placeholder">{{ mb_strtoupper(mb_substr($item->product_name, 0, 1)) }}</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="product-name">{{ $item->product_name }}</div>
                                    @if($item->variant_label)
                                        <div class="product-detail">{{ $item->variant_label }}</div>
                                    @endif
                                    <div class="product-detail">SKU: {{ $item->sku ?: 'N/A' }}</div>
                                    @if($uniqueShopCount > 1 && $item->shop)
                                        <div class="shop-tag">Seller: {{ $item->shop->name }}</div>
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </td>
                    <td class="right money">{{ $currencySymbol }}{{ number_format((float) $item->unit_price, 2) }}</td>
                    <td class="right money">{{ $taxRate > 0 ? number_format($taxRate * 100, 0).'%' : '—' }}</td>
                    <td class="right money">{{ $currencySymbol }}{{ number_format($lineTax, 2) }}</td>
                    <td class="right money strong">{{ $currencySymbol }}{{ number_format((float) $item->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="summary-wrap">
        <tr>
            <td class="summary-note-col">
                <div class="notice-box {{ $isPaid ? '' : 'unpaid' }}">
                    @if($isPaid)
                        <strong>Payment Verified:</strong> Full payment has been confirmed on record. Retain this receipt as your proof of purchase.
                    @elseif($methodRaw === 'cod')
                        <strong>Order Confirmation (Unpaid):</strong> Cash on Delivery payment will be collected upon delivery. A paid receipt is issued after collection is recorded.
                    @else
                        <strong>Order Summary (Unpaid):</strong> Payment has not yet been verified. This document is an order confirmation only, not a paid receipt.
                    @endif
                </div>
            </td>
            <td class="summary-totals-col">
                <div class="total-row">
                    <span class="total-label">Subtotal ({{ $order->items->sum('quantity') }} items)</span>
                    <span class="total-value">{{ $currencySymbol }}{{ number_format((float) $order->subtotal, 2) }}</span>
                </div>
                @if((float) $order->discount_amount > 0)
                    <div class="total-row discount">
                        <span class="total-label">Discount{{ $order->coupon_code ? ' ('.$order->coupon_code.')' : '' }}</span>
                        <span class="total-value">-{{ $currencySymbol }}{{ number_format((float) $order->discount_amount, 2) }}</span>
                    </div>
                @endif
                <div class="total-row">
                    <span class="total-label">Shipping</span>
                    <span class="total-value">{{ $currencySymbol }}{{ number_format((float) $order->shipping_amount, 2) }}</span>
                </div>
                <div class="total-row">
                    <span class="total-label">Tax{{ $taxRate > 0 ? ' ('.number_format($taxRate * 100, 0).'%)' : '' }}</span>
                    <span class="total-value">{{ $currencySymbol }}{{ number_format((float) $order->tax_amount, 2) }}</span>
                </div>
                <div class="total-row grand-total">
                    <span class="total-label">{{ $isPaid ? 'Total Paid' : 'Order Total' }}</span>
                    <span class="total-value">{{ $currencySymbol }}{{ number_format((float) $order->total, 2) }}</span>
                </div>
            </td>
        </tr>
    </table>

    <table class="footer-block">
        <tr>
            <td class="footer-left">
                <strong>Thank you for shopping with {{ $displayName }}!</strong><br>
                Historical order pricing and variant details are preserved from checkout.
            </td>
            <td class="footer-right">
                Issued by {{ $displayName }}<br>
                {{ $isPaid ? 'Official Receipt' : 'Pro Forma Order Summary' }} · {{ $order->order_number }}
            </td>
        </tr>
    </table>
</body>
</html>
