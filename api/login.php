<?php
require_once 'db.php';
require_once 'utils.php';

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJson(['error' => 'Method not allowed'], 405);
}

try {
    $pdo = getDB();
    
    // Get Basic Auth credentials
    $headers = [];
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
    } else {
        // Fallback for servers without getallheaders()
        foreach ($_SERVER as $name => $value) {
            if (strpos($name, 'HTTP_') === 0) {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
    }
    
    $email = null;
    $password = null;
    
    // Try to get from Authorization header (Basic Auth)
    if (isset($headers['Authorization'])) {
        $authHeader = $headers['Authorization'];
        if (strpos($authHeader, 'Basic ') === 0) {
            $credentials = base64_decode(substr($authHeader, 6));
            if ($credentials) {
                list($email, $password) = explode(':', $credentials, 2);
            }
        }
    }
    
    // Fallback to POST data
    if (!$email || !$password) {
        $input = getJsonInput();
        $email = $input['email'] ?? null;
        $password = $input['password'] ?? null;
    }
    
    // Debug logging (remove in production)
    error_log("Login attempt - Email: " . ($email ?? 'null') . ", Password: " . ($password ? 'provided' : 'null'));
    
    if (!$email || !$password) {
        sendJson(['error' => 'Email and password are required'], 400);
    }
    
    // Find user by email
    $stmt = $pdo->prepare("
        SELECT u.*, r.name as role_name 
        FROM users u 
        LEFT JOIN roles r ON u.role_id = r.id 
        WHERE u.email = ?
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user || !password_verify($password, $user['password_hash'])) {
        sendJson(['error' => 'Invalid credentials'], 401);
    }
    
    // Generate session token
    $token = generateToken(32);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
    
    // Clean up old sessions for this user
    $stmt = $pdo->prepare("
        UPDATE sessions 
        SET revoked_at = NOW() 
        WHERE user_id = ? AND revoked_at IS NULL
    ");
    $stmt->execute([$user['id']]);
    
    // Create new session
    $stmt = $pdo->prepare("
        INSERT INTO sessions (user_id, session_token, ip_address, user_agent, expires_at)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $user['id'],
        $token,
        $_SERVER['REMOTE_ADDR'] ?? '',
        $_SERVER['HTTP_USER_AGENT'] ?? '',
        $expiresAt
    ]);
    
    // Get user's roles and permissions
    $roles = getUserRoles($pdo, $user['id']);
    $permissions = getUserPermissions($pdo, $user['id']);
    
    sendJson([
        'message' => 'Login successful',
        'token' => $token,
        'user' => [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role_id' => $user['role_id'],
            'role_name' => $user['role_name'],
            'roles' => $roles,
            'permissions' => $permissions
        ],
        'expires_at' => $expiresAt
    ]);
    
    
} catch (Exception $e) {
    sendJson(['error' => 'Internal server error'], 500);
}
?>
