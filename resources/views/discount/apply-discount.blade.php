<!DOCTYPE html>
<html>
<head>
    <title>Apply Discount</title>
</head>
<body>
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