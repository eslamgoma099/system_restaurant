<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>طلب رقم {{ $data['order_id'] }}</title>
    <style>
        body { font-family: 'Arial', sans-serif; font-size: 14px; direction: rtl; }
        .header { text-align: center; margin-bottom: 20px; }
        .table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .table th, .table td {
            border: 1px solid #000;
            padding: 8px;
            text-align: right;
        }
        .addon {
            font-size: 12px;
            color: #555;
        }
        .footer { margin-top: 20px; text-align: left; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <h2>طلب رقم {{ $data['order_id'] }}</h2>
        <p><strong>الوجهة:</strong> {{ $data['destination'] }}</p>
        <p><strong>رقم الطاولة:</strong> {{ $data['table_number'] ?? '—' }}</p>
        <p><strong>الحالة:</strong> {{ $data['status'] }}</p>
        <p><strong>تاريخ الطباعة:</strong> {{ $data['printed_at'] }}</p>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>الصنف</th>
                <th>الكمية</th>
                <th>سعر الوحدة</th>
                <th>الإجمالي</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($data['items'] as $item)
                <tr>
                    <td>
                        {{ $item['name'] }}
                        @if (!empty($item['addons']))
                            <div class="addon">
                                <strong>إضافات:</strong>
                                <ul>
                                    @foreach ($item['addons'] as $addon)
                                        <li>{{ $addon['name'] }} ({{ $addon['quantity'] }}) - {{ number_format($addon['price'], 2) }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </td>
                    <td>{{ $item['quantity'] }}</td>
                    <td>{{ number_format($item['price'], 2) }}</td>
                    <td>{{ number_format($item['quantity'] * $item['price'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>الإجمالي الكلي: {{ number_format($data['total_price'], 2) }} ريال</p>
    </div>
</body>
</html>
