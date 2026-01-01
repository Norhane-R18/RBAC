<?php
// Show raw 401 response directly
echo "<h2>🔍 Raw 401 API Responses</h2>";

echo "<h3>❌ No Token Response:</h3>";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000/api/appointments.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<div style='background: #ffebee; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
echo "<strong>HTTP Status: $http_code</strong><br>";
echo "<strong>Response:</strong><br>";
echo "<pre>" . htmlspecialchars($response) . "</pre>";
echo "</div>";

echo "<h3>❌ Invalid Token Response:</h3>";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000/api/appointments.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer invalid-token-12345'
]);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<div style='background: #ffebee; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
echo "<strong>HTTP Status: $http_code</strong><br>";
echo "<strong>Response:</strong><br>";
echo "<pre>" . htmlspecialchars($response) . "</pre>";
echo "</div>";

echo "<h3>✅ Valid Token Response (if you have one):</h3>";
echo "<p>Login to dashboard first, then refresh this page to see valid response.</p>";

// Try with a real token if it exists
if (isset($_COOKIE['authToken'])) {
    $token = $_COOKIE['authToken'];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000/api/appointments.php');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "<div style='background: #e8f5e8; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
    echo "<strong>HTTP Status: $http_code</strong><br>";
    echo "<strong>Response:</strong><br>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";
    echo "</div>";
}

echo "<hr>";
echo "<p><a href='/dashboard/'>← Back to Dashboard</a> | <a href='/'>← Home</a></p>";
?>
