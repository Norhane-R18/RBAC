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
    
    // Only admins can manage groups
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
        // Get single group
        $stmt = $pdo->prepare("
            SELECT g.*,
                   GROUP_CONCAT(DISTINCT p.name) as permissions,
                   GROUP_CONCAT(DISTINCT u.name) as users
            FROM groups g
            LEFT JOIN group_permissions gp ON g.id = gp.group_id
            LEFT JOIN permissions p ON gp.permission_id = p.id
            LEFT JOIN user_groups ug ON g.id = ug.group_id
            LEFT JOIN users u ON ug.user_id = u.id
            WHERE g.id = ?
            GROUP BY g.id
        ");
        $stmt->execute([$_GET['id']]);
        $group = $stmt->fetch();
        
        if (!$group) {
            sendJson(['error' => 'Group not found'], 404);
        }
        
        $group['permissions'] = $group['permissions'] ? explode(',', $group['permissions']) : [];
        $group['users'] = $group['users'] ? explode(',', $group['users']) : [];
        
        sendJson($group);
    } else {
        // Get all groups
        $stmt = $pdo->query("
            SELECT g.*,
                   COUNT(DISTINCT gp.permission_id) as permission_count,
                   COUNT(DISTINCT ug.user_id) as user_count
            FROM groups g
            LEFT JOIN group_permissions gp ON g.id = gp.group_id
            LEFT JOIN user_groups ug ON g.id = ug.group_id
            GROUP BY g.id
            ORDER BY g.name
        ");
        $groups = $stmt->fetchAll();
        
        // Get permissions for each group
        foreach ($groups as &$group) {
            $stmt = $pdo->prepare("
                SELECT p.id, p.name
                FROM permissions p
                JOIN group_permissions gp ON p.id = gp.permission_id
                WHERE gp.group_id = ?
            ");
            $stmt->execute([$group['id']]);
            $group['permissions'] = $stmt->fetchAll();
        }
        
        sendJson(['groups' => $groups]);
    }
}

function handlePost($pdo) {
    $input = getJsonInput();
    validateRequired($input, ['name']);
    
    // Check if group name already exists
    $stmt = $pdo->prepare("SELECT id FROM groups WHERE name = ?");
    $stmt->execute([$input['name']]);
    if ($stmt->fetch()) {
        sendJson(['error' => 'Group with this name already exists'], 409);
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO groups (name, description)
        VALUES (?, ?)
    ");
    $stmt->execute([
        $input['name'],
        $input['description'] ?? null
    ]);
    
    $groupId = $pdo->lastInsertId();
    
    // Add permissions if provided
    if (isset($input['permissions']) && is_array($input['permissions'])) {
        foreach ($input['permissions'] as $permissionId) {
            $stmt = $pdo->prepare("
                INSERT INTO group_permissions (group_id, permission_id)
                VALUES (?, ?)
            ");
            $stmt->execute([$groupId, $permissionId]);
        }
    }
    
    sendJson([
        'message' => 'Group created successfully',
        'id' => $groupId
    ], 201);
}

function handlePut($pdo) {
    if (!isset($_GET['id'])) {
        sendJson(['error' => 'Missing group ID'], 400);
    }
    
    $groupId = $_GET['id'];
    $input = getJsonInput();
    
    // Verify group exists
    $stmt = $pdo->prepare("SELECT * FROM groups WHERE id = ?");
    $stmt->execute([$groupId]);
    $group = $stmt->fetch();
    
    if (!$group) {
        sendJson(['error' => 'Group not found'], 404);
    }
    
    // Update group details
    $updateFields = [];
    $params = [];
    
    if (isset($input['name'])) {
        // Check if name already exists (excluding current group)
        $stmt = $pdo->prepare("SELECT id FROM groups WHERE name = ? AND id != ?");
        $stmt->execute([$input['name'], $groupId]);
        if ($stmt->fetch()) {
            sendJson(['error' => 'Group with this name already exists'], 409);
        }
        $updateFields[] = "name = ?";
        $params[] = $input['name'];
    }
    
    if (isset($input['description'])) {
        $updateFields[] = "description = ?";
        $params[] = $input['description'];
    }
    
    if (!empty($updateFields)) {
        $params[] = $groupId;
        $query = "UPDATE groups SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
    }
    
    // Update permissions if provided
    if (isset($input['permissions']) && is_array($input['permissions'])) {
        // Remove existing permissions
        $stmt = $pdo->prepare("DELETE FROM group_permissions WHERE group_id = ?");
        $stmt->execute([$groupId]);
        
        // Add new permissions
        foreach ($input['permissions'] as $permissionId) {
            $stmt = $pdo->prepare("
                INSERT INTO group_permissions (group_id, permission_id)
                VALUES (?, ?)
            ");
            $stmt->execute([$groupId, $permissionId]);
        }
    }
    
    sendJson(['message' => 'Group updated successfully']);
}

function handleDelete($pdo) {
    if (!isset($_GET['id'])) {
        sendJson(['error' => 'Missing group ID'], 400);
    }
    
    $groupId = $_GET['id'];
    
    // Verify group exists
    $stmt = $pdo->prepare("SELECT * FROM groups WHERE id = ?");
    $stmt->execute([$groupId]);
    $group = $stmt->fetch();
    
    if (!$group) {
        sendJson(['error' => 'Group not found'], 404);
    }
    
    // Check if group is assigned to users
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM user_groups WHERE group_id = ?");
    $stmt->execute([$groupId]);
    $userCount = $stmt->fetch()['count'];
    
    if ($userCount > 0) {
        sendJson(['error' => 'Cannot delete group that is assigned to users'], 409);
    }
    
    // Delete group (permissions will be deleted due to CASCADE)
    $stmt = $pdo->prepare("DELETE FROM groups WHERE id = ?");
    $stmt->execute([$groupId]);
    
    sendJson(['message' => 'Group deleted successfully']);
}
?>
