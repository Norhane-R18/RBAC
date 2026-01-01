<?php
require_once 'db.php';
require_once 'utils.php';

header('Content-Type: application/json');

try {
    $pdo = getDB();
    
    // Authenticate user
    $session = authenticateUser($pdo);
    $currentUserId = $session['user_id'];
    $currentUserRole = $session['role_name'];
    
    // Only admins can manage roles
    if ($currentUserRole !== 'admin') {
        sendJson(['error' => 'Forbidden: Admin access required'], 403);
    }
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            handleGet($pdo);
            break;
        case 'POST':
            handlePost($pdo);
            break;
        case 'PUT':
            handlePut($pdo);
            break;
        case 'DELETE':
            handleDelete($pdo);
            break;
        default:
            sendJson(['error' => 'Method not allowed'], 405);
    }
    
} catch (Exception $e) {
    sendJson(['error' => 'Internal server error'], 500);
}

function handleGet($pdo) {
    if (isset($_GET['id'])) {
        // Get single role
        $stmt = $pdo->prepare("
            SELECT r.*, 
                   GROUP_CONCAT(p.name) as permissions
            FROM roles r
            LEFT JOIN role_permissions rp ON r.id = rp.role_id
            LEFT JOIN permissions p ON rp.permission_id = p.id
            WHERE r.id = ?
            GROUP BY r.id
        ");
        $stmt->execute([$_GET['id']]);
        $role = $stmt->fetch();
        
        if (!$role) {
            sendJson(['error' => 'Role not found'], 404);
        }
        
        // Get permissions list
        $role['permissions'] = $role['permissions'] ? explode(',', $role['permissions']) : [];
        
        sendJson($role);
    } else {
        // Get all roles
        $stmt = $pdo->query("
            SELECT r.*, 
                   COUNT(rp.permission_id) as permission_count
            FROM roles r
            LEFT JOIN role_permissions rp ON r.id = rp.role_id
            GROUP BY r.id
            ORDER BY r.name
        ");
        $roles = $stmt->fetchAll();
        
        sendJson(['roles' => $roles]);
    }
}

function handlePost($pdo) {
    $input = getJsonInput();
    validateRequired($input, ['name']);
    
    // Check if role name already exists
    $stmt = $pdo->prepare("SELECT id FROM roles WHERE name = ?");
    $stmt->execute([$input['name']]);
    if ($stmt->fetch()) {
        sendJson(['error' => 'Role with this name already exists'], 409);
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO roles (name, description)
        VALUES (?, ?)
    ");
    $stmt->execute([
        $input['name'],
        $input['description'] ?? null
    ]);
    
    $roleId = $pdo->lastInsertId();
    
    // Add permissions if provided
    if (isset($input['permissions']) && is_array($input['permissions'])) {
        foreach ($input['permissions'] as $permissionId) {
            $stmt = $pdo->prepare("
                INSERT INTO role_permissions (role_id, permission_id)
                VALUES (?, ?)
            ");
            $stmt->execute([$roleId, $permissionId]);
        }
    }
    
    sendJson([
        'message' => 'Role created successfully',
        'id' => $roleId
    ], 201);
}

function handlePut($pdo) {
    if (!isset($_GET['id'])) {
        sendJson(['error' => 'Missing role ID'], 400);
    }
    
    $roleId = $_GET['id'];
    $input = getJsonInput();
    
    // Verify role exists
    $stmt = $pdo->prepare("SELECT * FROM roles WHERE id = ?");
    $stmt->execute([$roleId]);
    $role = $stmt->fetch();
    
    if (!$role) {
        sendJson(['error' => 'Role not found'], 404);
    }
    
    // Prevent modification of admin role
    if ($role['name'] === 'admin') {
        sendJson(['error' => 'Cannot modify admin role'], 403);
    }
    
    // Update role details
    $updateFields = [];
    $params = [];
    
    if (isset($input['name'])) {
        // Check if name already exists (excluding current role)
        $stmt = $pdo->prepare("SELECT id FROM roles WHERE name = ? AND id != ?");
        $stmt->execute([$input['name'], $roleId]);
        if ($stmt->fetch()) {
            sendJson(['error' => 'Role with this name already exists'], 409);
        }
        $updateFields[] = "name = ?";
        $params[] = $input['name'];
    }
    
    if (isset($input['description'])) {
        $updateFields[] = "description = ?";
        $params[] = $input['description'];
    }
    
    if (!empty($updateFields)) {
        $params[] = $roleId;
        $query = "UPDATE roles SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
    }
    
    // Update permissions if provided
    if (isset($input['permissions']) && is_array($input['permissions'])) {
        // Remove existing permissions
        $stmt = $pdo->prepare("DELETE FROM role_permissions WHERE role_id = ?");
        $stmt->execute([$roleId]);
        
        // Add new permissions
        foreach ($input['permissions'] as $permissionId) {
            $stmt = $pdo->prepare("
                INSERT INTO role_permissions (role_id, permission_id)
                VALUES (?, ?)
            ");
            $stmt->execute([$roleId, $permissionId]);
        }
    }
    
    sendJson(['message' => 'Role updated successfully']);
}

function handleDelete($pdo) {
    if (!isset($_GET['id'])) {
        sendJson(['error' => 'Missing role ID'], 400);
    }
    
    $roleId = $_GET['id'];
    
    // Verify role exists
    $stmt = $pdo->prepare("SELECT * FROM roles WHERE id = ?");
    $stmt->execute([$roleId]);
    $role = $stmt->fetch();
    
    if (!$role) {
        sendJson(['error' => 'Role not found'], 404);
    }
    
    // Prevent deletion of admin role
    if ($role['name'] === 'admin') {
        sendJson(['error' => 'Cannot delete admin role'], 403);
    }
    
    // Check if role is assigned to users
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM user_roles WHERE role_id = ?");
    $stmt->execute([$roleId]);
    $userCount = $stmt->fetch()['count'];
    
    if ($userCount > 0) {
        sendJson(['error' => 'Cannot delete role that is assigned to users'], 409);
    }
    
    // Delete role (permissions will be deleted due to CASCADE)
    $stmt = $pdo->prepare("DELETE FROM roles WHERE id = ?");
    $stmt->execute([$roleId]);
    
    sendJson(['message' => 'Role deleted successfully']);
}
?>
