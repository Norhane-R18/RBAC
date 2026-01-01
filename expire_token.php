<?php
// Expire all sessions immediately
require_once 'api/db.php';

try {
    $pdo = getDB();
    $stmt = $pdo->query("UPDATE sessions SET revoked_at = NOW()");
    $count = $stmt->rowCount();
    
    echo "✅ Expired $count sessions<br>";
    echo "🔄 Now reload your dashboard page to see 401 handling!<br>";
    echo "<a href='/dashboard/'>Go to Dashboard</a>";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
