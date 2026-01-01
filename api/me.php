<?php
require_once 'db.php';
require_once 'utils.php';

header('Content-Type: application/json');

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJson(['error' => 'Method not allowed'], 405);
}

try {
    $pdo = getDB();
    
    // Authenticate user
    $session = authenticateUser($pdo);
    
    // Get user's detailed information
    $stmt = $pdo->prepare("
        SELECT u.id, u.name, u.email, u.role_id, u.created_at,
               r.name as role_name, r.description as role_description
        FROM users u 
        LEFT JOIN roles r ON u.role_id = r.id 
        WHERE u.id = ?
    ");
    $stmt->execute([$session['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        sendJson(['error' => 'User not found'], 404);
    }
    
    // Get all user roles
    $roles = getUserRoles($pdo, $user['id']);
    
    // Get all user permissions
    $permissions = getUserPermissions($pdo, $user['id']);
    
    // Get user groups
    $stmt = $pdo->prepare("
        SELECT g.id, g.name, g.description
        FROM groups g
        JOIN user_groups ug ON g.id = ug.group_id
        WHERE ug.user_id = ?
    ");
    $stmt->execute([$user['id']]);
    $groups = $stmt->fetchAll();
    
    // Get user-specific permission overrides
    $stmt = $pdo->prepare("
        SELECT p.name, up.allowed
        FROM permissions p
        JOIN user_permissions up ON p.id = up.permission_id
        WHERE up.user_id = ?
    ");
    $stmt->execute([$user['id']]);
    $userOverrides = $stmt->fetchAll();
    
    sendJson([
        'user' => [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role_id' => $user['role_id'],
            'role_name' => $user['role_name'],
            'role_description' => $user['role_description'],
            'created_at' => $user['created_at']
        ],
        'roles' => $roles,
        'permissions' => $permissions,
        'groups' => $groups,
        'permission_overrides' => $userOverrides,
        'session' => [
            'token' => $session['session_token'],
            'expires_at' => $session['expires_at'],
            'created_at' => $session['created_at']
        ]
    ]);
    
} catch (Exception $e) {
    sendJson(['error' => 'Internal server error'], 500);
}
?>
