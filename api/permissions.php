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
    
    // Only admins can manage permissions
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
        // Get single permission
        $stmt = $pdo->prepare("
            SELECT p.*,
                   GROUP_CONCAT(DISTINCT r.name) as roles,
                   GROUP_CONCAT(DISTINCT g.name) as groups
            FROM permissions p
            LEFT JOIN role_permissions rp ON p.id = rp.permission_id
            LEFT JOIN roles r ON rp.role_id = r.id
            LEFT JOIN group_permissions gp ON p.id = gp.permission_id
            LEFT JOIN groups g ON gp.group_id = g.id
            WHERE p.id = ?
            GROUP BY p.id
        ");
        $stmt->execute([$_GET['id']]);
        $permission = $stmt->fetch();
        
        if (!$permission) {
            sendJson(['error' => 'Permission not found'], 404);
        }
        
        $permission['roles'] = $permission['roles'] ? explode(',', $permission['roles']) : [];
        $permission['groups'] = $permission['groups'] ? explode(',', $permission['groups']) : [];
        
        sendJson($permission);
    } else {
        // Get all permissions
        $stmt = $pdo->query("
            SELECT p.*,
                   COUNT(rp.permission_id) as role_count,
                   COUNT(gp.permission_id) as group_count
            FROM permissions p
            LEFT JOIN role_permissions rp ON p.id = rp.permission_id
            LEFT JOIN group_permissions gp ON p.id = gp.permission_id
            GROUP BY p.id
            ORDER BY p.name
        ");
        $permissions = $stmt->fetchAll();
        
        sendJson(['permissions' => $permissions]);
    }
}

function handlePost($pdo) {
    $input = getJsonInput();
    validateRequired($input, ['name']);
    
    // Check if permission name already exists
    $stmt = $pdo->prepare("SELECT id FROM permissions WHERE name = ?");
    $stmt->execute([$input['name']]);
    if ($stmt->fetch()) {
        sendJson(['error' => 'Permission with this name already exists'], 409);
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO permissions (name, description)
        VALUES (?, ?)
    ");
    $stmt->execute([
        $input['name'],
        $input['description'] ?? null
    ]);
    
    $permissionId = $pdo->lastInsertId();
    
    sendJson([
        'message' => 'Permission created successfully',
        'id' => $permissionId
    ], 201);
}

function handlePut($pdo) {
    if (!isset($_GET['id'])) {
        sendJson(['error' => 'Missing permission ID'], 400);
    }
    
    $permissionId = $_GET['id'];
    $input = getJsonInput();
    
    // Verify permission exists
    $stmt = $pdo->prepare("SELECT * FROM permissions WHERE id = ?");
    $stmt->execute([$permissionId]);
    $permission = $stmt->fetch();
    
    if (!$permission) {
        sendJson(['error' => 'Permission not found'], 404);
    }
    
    // Update permission details
    $updateFields = [];
    $params = [];
    
    if (isset($input['name'])) {
        // Check if name already exists (excluding current permission)
        $stmt = $pdo->prepare("SELECT id FROM permissions WHERE name = ? AND id != ?");
        $stmt->execute([$input['name'], $permissionId]);
        if ($stmt->fetch()) {
            sendJson(['error' => 'Permission with this name already exists'], 409);
        }
        $updateFields[] = "name = ?";
        $params[] = $input['name'];
    }
    
    if (isset($input['description'])) {
        $updateFields[] = "description = ?";
        $params[] = $input['description'];
    }
    
    if (!empty($updateFields)) {
        $params[] = $permissionId;
        $query = "UPDATE permissions SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
    }
    
    sendJson(['message' => 'Permission updated successfully']);
}

function handleDelete($pdo) {
    if (!isset($_GET['id'])) {
        sendJson(['error' => 'Missing permission ID'], 400);
    }
    
    $permissionId = $_GET['id'];
    
    // Verify permission exists
    $stmt = $pdo->prepare("SELECT * FROM permissions WHERE id = ?");
    $stmt->execute([$permissionId]);
    $permission = $stmt->fetch();
    
    if (!$permission) {
        sendJson(['error' => 'Permission not found'], 404);
    }
    
    // Check if permission is assigned to roles
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM role_permissions WHERE permission_id = ?");
    $stmt->execute([$permissionId]);
    $roleCount = $stmt->fetch()['count'];
    
    // Check if permission is assigned to groups
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM group_permissions WHERE permission_id = ?");
    $stmt->execute([$permissionId]);
    $groupCount = $stmt->fetch()['count'];
    
    // Check if permission is assigned to users directly
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM user_permissions WHERE permission_id = ?");
    $stmt->execute([$permissionId]);
    $userCount = $stmt->fetch()['count'];
    
    if ($roleCount > 0 || $groupCount > 0 || $userCount > 0) {
        sendJson(['error' => 'Cannot delete permission that is in use'], 409);
    }
    
    // Delete permission
    $stmt = $pdo->prepare("DELETE FROM permissions WHERE id = ?");
    $stmt->execute([$permissionId]);
    
    sendJson(['message' => 'Permission deleted successfully']);
}
?>
