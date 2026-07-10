<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 13px; color: #222; padding: 30px; }
        .header { display: flex; justify-content: space-between; margin-bottom: 30px; }
        .brand h1 { font-size: 22px; font-weight: bold; color: #1a56db; }
        .brand p { font-size: 11px; color: #555; margin-top: 2px; }
        .invoice-meta { text-align: right; }
        .invoice-meta h2 { font-size: 20px; color: #1a56db; letter-spacing: 2px; }
        .invoice-meta p { font-size: 11px; color: #555; margin-top: 2px; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; }
        .badge-paid { background: #def7ec; color: #03543f; }
        .badge-issued { background: #fef3c7; color: #92400e; }
        .badge-partial { background: #ede9fe; color: #4c1d95; }
        .section { margin-bottom: 24px; }
        .section-title { font-size: 11px; font-weight: bold; text-transform: uppercase; color: #888; margin-bottom: 6px; letter-spacing: 1px; }
        .bill-to p { font-size: 13px; line-height: 1.6; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        thead th { background: #1a56db; color: #fff; padding: 8px 10px; text-align: left; font-size: 12px; }
        tbody td { padding: 10px; border-bottom: 1px solid #e5e7eb; font-size: 13px; }
        .totals { width: 280px; margin-left: auto; }
        .totals tr td { padding: 5px 10px; }
        .totals tr.grand-total td { font-weight: bold; font-size: 15px; border-top: 2px solid #1a56db; padding-top: 8px; }
        .footer { margin-top: 40px; border-top: 1px solid #e5e7eb; padding-top: 14px; font-size: 11px; color: #888; text-align: center; }
    </style>
</head>
<body>

<div class="header">
    <div class="brand">
        <h1>{{ config('app.name', 'ISP Management') }}</h1>
        <p>{{ $invoice->meta['isp_address'] ?? '' }}</p>
        <p>{{ $invoice->meta['isp_phone'] ?? '' }}</p>
    </div>
    <div class="invoice-meta">
        <h2>INVOICE</h2>
        <p><strong>{{ $invoice->invoice_number }}</strong></p>
        <p>Date: {{ $invoice->issued_at?->format('d M Y') }}</p>
        @if($invoice->due_at)
        <p>Due: {{ $invoice->due_at->format('d M Y') }}</p>
        @endif
        <p style="margin-top:6px;">
            <span class="badge badge-{{ $invoice->status === 'paid' ? 'paid' : ($invoice->status === 'issued' ? 'issued' : 'partial') }}">
                {{ strtoupper($invoice->status) }}
            </span>
        </p>
    </div>
</div>

<div class="section bill-to">
    <div class="section-title">Bill To</div>
    <p><strong>{{ $invoice->meta['client_name'] ?? $invoice->client?->full_name ?? 'N/A' }}</strong></p>
    @if($invoice->meta['pppoe_username'] ?? null)
    <p>Username: {{ $invoice->meta['pppoe_username'] }}</p>
    @endif
    @if($invoice->client?->phone_number ?? null)
    <p>Phone: {{ $invoice->client->phone_number }}</p>
    @endif
</div>

<table>
    <thead>
        <tr>
            <th>Description</th>
            <th>Period</th>
            <th>Method</th>
            <th style="text-align:right;">Amount</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>
                {{ $invoice->meta['package_name'] ?? 'Internet Service' }}
                @if($invoice->meta['note'] ?? null)
                    <br><small style="color:#888;">{{ $invoice->meta['note'] }}</small>
                @endif
            </td>
            <td>{{ $invoice->meta['period'] ?? '—' }}</td>
            <td>
                {{ $invoice->meta['method'] ?? '—' }}
                @if($invoice->meta['txn_id'] ?? null)
                    <br><small style="color:#888;">TrxID: {{ $invoice->meta['txn_id'] }}</small>
                @endif
            </td>
            <td style="text-align:right;">৳{{ number_format($invoice->subtotal, 2) }}</td>
        </tr>
    </tbody>
</table>

<table class="totals">
    <tr>
        <td>Subtotal</td>
        <td style="text-align:right;">৳{{ number_format($invoice->subtotal, 2) }}</td>
    </tr>
    @if($invoice->tax_amount > 0)
    <tr>
        <td>Tax / VAT</td>
        <td style="text-align:right;">৳{{ number_format($invoice->tax_amount, 2) }}</td>
    </tr>
    @endif
    <tr class="grand-total">
        <td>Total</td>
        <td style="text-align:right;">৳{{ number_format($invoice->total, 2) }}</td>
    </tr>
</table>

<div class="footer">
    Thank you for your payment. For queries contact {{ $invoice->meta['isp_phone'] ?? config('app.name') }}.
    &nbsp;|&nbsp; This is a computer-generated invoice.
</div>

</body>
</html>
