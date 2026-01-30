<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payments Summary</title>
    <style>
        body {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            font-size: 12px;
            color: #111;
        }

        .header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 16px;
        }

        .logo {
            height: 50px;
        }

        h1 {
            font-size: 18px;
            margin: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 8px;
        }

        th {
            background: #f3f4f6;
            text-align: left;
        }

        .meta {
            margin-bottom: 10px;
            color: #374151;
        }

        .right {
            text-align: right;
        }
    </style>
</head>
<body>
<div class="header">
    @if(!empty($event->img))
        <img src="{{ $event->img }}" class="logo" alt="Event Logo">
    @endif
    <div>
        <h1>Payments Summary</h1>
        <div class="meta">
            <div><strong>Event:</strong> {{ $event->name }}</div>
            <div><strong>Date Generated:</strong> {{ $generated_at->format('Y-m-d H:i') }}</div>
        </div>
    </div>
</div>

<table>
    <thead>
    <tr>
        @if($group_by !== 'method')
            <th>Payment Gateway</th>
        @endif
        @if($group_by !== 'gateway')
            <th>Payment Method</th>
        @endif
        <th class="right">Count</th>
        <th class="right">Total ({{ $event->currency }})</th>
    </tr>
    </thead>
    <tbody>
    @php $grandCount=0; $grandTotal=0; @endphp
    @foreach($rows as $row)
        @php $grandCount += (int)($row['count'] ?? 0); $grandTotal += (float)($row['total'] ?? 0); @endphp
        <tr>
            @if($group_by !== 'method')
                <td>{{ $row['gateway'] ?? '-' }}</td>
            @endif
            @if($group_by !== 'gateway')
                <td>{{ $row['payment_method'] ?? '-' }}</td>
            @endif
            <td class="right">{{ $row['count'] }}</td>
            <td class="right">{{ number_format($row['total'], 2) }}</td>
        </tr>
    @endforeach
    <tr>
        <td colspan="{{ $group_by === 'gateway_method' ? 2 : 1 }}" style="text-align:right"><strong>Grand Total</strong>
        </td>
        <td class="right"><strong>{{ $grandCount }}</strong></td>
        <td class="right"><strong>{{ number_format($grandTotal, 2) }}</strong></td>
    </tr>
    </tbody>
</table>

</body>
</html>
