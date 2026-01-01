<?php
// Show raw 401 response
echo "<h2>🔍 Testing 401 Responses</h2>";

echo "<h3>1. No Token Test:</h3>";
$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => "Content-Type: application/json\r\n"
    ]
]);

$response = file_get_contents('http://localhost:8000/api/appointments.php', false, $context);
echo "<pre style='background: #ffebee; padding: 10px; border-radius: 5px;'>";
echo htmlspecialchars($response);
echo "</pre>";

echo "<h3>2. Invalid Token Test:</h3>";
$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => "Content-Type: application/json\r\nAuthorization: Bearer invalid-token-12345\r\n"
    ]
]);

$response = file_get_contents('http://localhost:8000/api/appointments.php', false, $context);
echo "<pre style='background: #ffebee; padding: 10px; border-radius: 5px;'>";
echo htmlspecialchars($response);
echo "</pre>";

echo "<h3>3. Valid Token Test (needs real token):</h3>";
echo "<p>Get a valid token from login first, then test with it.</p>";

echo "<p><a href='/dashboard/'>Back to Dashboard</a> | <a href='/test_401.php'>Interactive Test</a></p>";
?>
