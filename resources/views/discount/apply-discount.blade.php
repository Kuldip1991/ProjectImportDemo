<!DOCTYPE html>
<html>
<head>
    <title>Apply Discount</title>
</head>
<body>

    @if(session('result'))
        <h3>Discount Summary</h3>
        <p><strong>Original Amount:</strong> {{ session('result')['original_amount'] }}</p>
        <p><strong>Discount Amount:</strong> {{ session('result')['discount_amount'] }}</p>
        <p><strong>Final Amount:</strong> {{ session('result')['final_amount'] }}</p>

        <h4>Applied Discounts:</h4>
        <ul>
            @foreach(session('result')['applied_discounts'] as $discount)
                <li>
                    <strong>{{ $discount['code'] }}</strong> — ₹{{ $discount['discount_amount'] }}
                </li>
            @endforeach
        </ul>
    @endif
    <h1>Apply Discount</h1>
    @if(session('message'))
        <p style="color: green;">{{ session('message') }}</p>
    @endif

    @if($errors->any())
        <ul style="color: red;">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ url('/apply-discount') }}">
        @csrf
        <label for="amount">Amount:</label>
        <input type="number" step="0.01" name="amount" required><br><br>

        <label for="context">Context:</label>
        <input type="text" name="context" required><br><br>

        <button type="submit">Apply Discount</button>
    </form>
</body>
</html>
