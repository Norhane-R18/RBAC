<?php
echo "🎉 PHP Server is Working!<br>";
echo "Time: " . date('Y-m-d H:i:s') . "<br>";
echo "Server: " . $_SERVER['SERVER_NAME'] . "<br>";
echo "Port: " . $_SERVER['SERVER_PORT'] . "<br>";

// Test database
try {
    require_once 'api/db.php';
    $pdo = getDB();
    echo "✅ Database: Connected<br>";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $count = $stmt->fetch()['count'];
    echo "📊 Users: $count<br>";
} catch (Exception $e) {
    echo "❌ Database Error: " . $e->getMessage() . "<br>";
}

echo "<hr>";
echo "<a href='/easy_test.php'>Test 401/403</a> | ";
echo "<a href='/dashboard/'>Dashboard</a>";
?>
