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
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            handleGet($pdo, $currentUserId, $currentUserRole);
            break;
        case 'POST':
            handlePost($pdo, $currentUserId, $currentUserRole);
            break;
        case 'PUT':
            handlePut($pdo, $currentUserId, $currentUserRole);
            break;
        case 'DELETE':
            handleDelete($pdo, $currentUserId, $currentUserRole);
            break;
        default:
            sendJson(['error' => 'Method not allowed'], 405);
    }
    
} catch (Exception $e) {
    sendJson(['error' => 'Internal server error'], 500);
}

function handleGet($pdo, $currentUserId, $currentUserRole) {
    if (isset($_GET['id'])) {
        // Get single user (admin or self)
        $userId = $_GET['id'];
        
        if ($currentUserRole !== 'admin' && $userId != $currentUserId) {
            sendJson(['error' => 'Forbidden: Can only view own profile'], 403);
        }
        
        $stmt = $pdo->prepare("
            SELECT u.id, u.name, u.email, u.role_id, u.created_at,
                   r.name as role_name, r.description as role_description
            FROM users u 
            LEFT JOIN roles r ON u.role_id = r.id 
            WHERE u.id = ?
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            sendJson(['error' => 'User not found'], 404);
        }
        
        // Get all user roles
        $stmt = $pdo->prepare("
            SELECT r.id, r.name 
            FROM roles r 
            JOIN user_roles ur ON r.id = ur.role_id 
            WHERE ur.user_id = ?
        ");
        $stmt->execute([$userId]);
        $roles = $stmt->fetchAll();
        
        // Get all user permissions
        $permissions = getUserPermissions($pdo, $userId);
        
        // Get user groups
        $stmt = $pdo->prepare("
            SELECT g.id, g.name, g.description
            FROM groups g
            JOIN user_groups ug ON g.id = ug.group_id
            WHERE ug.user_id = ?
        ");
        $stmt->execute([$userId]);
        $groups = $stmt->fetchAll();
        
        // Get user-specific permission overrides
        $stmt = $pdo->prepare("
            SELECT p.name, up.allowed
            FROM permissions p
            JOIN user_permissions up ON p.id = up.permission_id
            WHERE up.user_id = ?
        ");
        $stmt->execute([$userId]);
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
            'roles' => array_column($roles, 'name'),
            'permissions' => $permissions,
            'groups' => array_column($groups, 'name'),
            'permission_overrides' => $userOverrides
        ]);
    } else {
        // Get all users (admin only)
        if ($currentUserRole !== 'admin') {
            sendJson(['error' => 'Forbidden: Admin access required to view all users'], 403);
        }
        
        $stmt = $pdo->query("
            SELECT u.id, u.name, u.email, u.role_id, u.created_at,
                   r.name as role_name, r.description as role_description
            FROM users u 
            LEFT JOIN roles r ON u.role_id = r.id 
            ORDER BY u.name
        ");
        $users = $stmt->fetchAll();
        
        sendJson(['users' => $users]);
    }
}

function handlePost($pdo, $currentUserId, $currentUserRole) {
    // Only admins can create users
    if ($currentUserRole !== 'admin') {
        sendJson(['error' => 'Forbidden: Admin access required to create users'], 403);
    }
    
    $input = getJsonInput();
    validateRequired($input, ['name', 'email', 'password']);
    
    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$input['email']]);
    if ($stmt->fetch()) {
        sendJson(['error' => 'User with this email already exists'], 409);
    }
    
    // Validate role if provided
    $roleId = null;
    if (isset($input['role_id'])) {
        $stmt = $pdo->prepare("SELECT id FROM roles WHERE id = ?");
        $stmt->execute([$input['role_id']]);
        if (!$stmt->fetch()) {
            sendJson(['error' => 'Invalid role ID'], 400);
        }
        $roleId = $input['role_id'];
    }
    
    // Hash password
    $passwordHash = password_hash($input['password'], PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("
        INSERT INTO users (name, email, password_hash, role_id)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([
        $input['name'],
        $input['email'],
        $passwordHash,
        $roleId
    ]);
    
    $userId = $pdo->lastInsertId();
    
    // Add role assignments if provided
    if (isset($input['roles']) && is_array($input['roles'])) {
        foreach ($input['roles'] as $roleName) {
            $stmt = $pdo->prepare("SELECT id FROM roles WHERE name = ?");
            $stmt->execute([$roleName]);
            $role = $stmt->fetch();
            
            if ($role) {
                $stmt = $pdo->prepare("
                    INSERT INTO user_roles (user_id, role_id)
                    VALUES (?, ?)
                ");
                $stmt->execute([$userId, $role['id']]);
            }
        }
    }
    
    // Add group assignments if provided
    if (isset($input['groups']) && is_array($input['groups'])) {
        foreach ($input['groups'] as $groupName) {
            $stmt = $pdo->prepare("SELECT id FROM groups WHERE name = ?");
            $stmt->execute([$groupName]);
            $group = $stmt->fetch();
            
            if ($group) {
                $stmt = $pdo->prepare("
                    INSERT INTO user_groups (user_id, group_id)
                    VALUES (?, ?)
                ");
                $stmt->execute([$userId, $group['id']]);
            }
        }
    }
    
    sendJson([
        'message' => 'User created successfully',
        'id' => $userId
    ], 201);
}

function handlePut($pdo, $currentUserId, $currentUserRole) {
    if (!isset($_GET['id'])) {
        sendJson(['error' => 'Missing user ID'], 400);
    }
    
    $userId = $_GET['id'];
    $input = getJsonInput();
    
    // Check permissions
    if ($currentUserRole !== 'admin' && $userId != $currentUserId) {
        sendJson(['error' => 'Forbidden: Can only edit own profile'], 403);
    }
    
    // Verify user exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        sendJson(['error' => 'User not found'], 404);
    }
    
    // Update basic user info
    $updateFields = [];
    $params = [];
    
    if (isset($input['name'])) {
        $updateFields[] = "name = ?";
        $params[] = $input['name'];
    }
    
    if (isset($input['email'])) {
        // Check if email already exists (excluding current user)
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$input['email'], $userId]);
        if ($stmt->fetch()) {
            sendJson(['error' => 'User with this email already exists'], 409);
        }
        $updateFields[] = "email = ?";
        $params[] = $input['email'];
    }
    
    if (isset($input['password'])) {
        $updateFields[] = "password_hash = ?";
        $params[] = password_hash($input['password'], PASSWORD_DEFAULT);
    }
    
    if (isset($input['role_id']) && $currentUserRole === 'admin') {
        $stmt = $pdo->prepare("SELECT id FROM roles WHERE id = ?");
        $stmt->execute([$input['role_id']]);
        if (!$stmt->fetch()) {
            sendJson(['error' => 'Invalid role ID'], 400);
        }
        $updateFields[] = "role_id = ?";
        $params[] = $input['role_id'];
    }
    
    if (!empty($updateFields)) {
        $params[] = $userId;
        $query = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
    }
    
    // Update role assignments (admin only)
    if ($currentUserRole === 'admin' && isset($input['roles'])) {
        // Remove existing role assignments
        $stmt = $pdo->prepare("DELETE FROM user_roles WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        // Add new role assignments
        if (is_array($input['roles'])) {
            foreach ($input['roles'] as $roleName) {
                $stmt = $pdo->prepare("SELECT id FROM roles WHERE name = ?");
                $stmt->execute([$roleName]);
                $role = $stmt->fetch();
                
                if ($role) {
                    $stmt = $pdo->prepare("
                        INSERT INTO user_roles (user_id, role_id)
                        VALUES (?, ?)
                    ");
                    $stmt->execute([$userId, $role['id']]);
                }
            }
        }
    }
    
    // Update group assignments (admin only)
    if ($currentUserRole === 'admin' && isset($input['groups'])) {
        // Remove existing group assignments
        $stmt = $pdo->prepare("DELETE FROM user_groups WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        // Add new group assignments
        if (is_array($input['groups'])) {
            foreach ($input['groups'] as $groupName) {
                $stmt = $pdo->prepare("SELECT id FROM groups WHERE name = ?");
                $stmt->execute([$groupName]);
                $group = $stmt->fetch();
                
                if ($group) {
                    $stmt = $pdo->prepare("
                        INSERT INTO user_groups (user_id, group_id)
                        VALUES (?, ?)
                    ");
                    $stmt->execute([$userId, $group['id']]);
                }
            }
        }
    }
    
    // Update direct permissions (admin only)
    if ($currentUserRole === 'admin' && isset($input['permissions'])) {
        // Remove existing permission assignments
        $stmt = $pdo->prepare("DELETE FROM user_permissions WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        // Add new permission assignments
        if (is_array($input['permissions'])) {
            foreach ($input['permissions'] as $permissionId) {
                $stmt = $pdo->prepare("
                    INSERT INTO user_permissions (user_id, permission_id, allowed)
                    VALUES (?, ?, 1)
                ");
                $stmt->execute([$userId, $permissionId]);
            }
        }
    }
    
    sendJson(['message' => 'User updated successfully']);
}

function handleDelete($pdo, $currentUserId, $currentUserRole) {
    // Only admins can delete users
    if ($currentUserRole !== 'admin') {
        sendJson(['error' => 'Forbidden: Admin access required to delete users'], 403);
    }
    
    if (!isset($_GET['id'])) {
        sendJson(['error' => 'Missing user ID'], 400);
    }
    
    $userId = $_GET['id'];
    
    // Prevent self-deletion
    if ($userId == $currentUserId) {
        sendJson(['error' => 'Cannot delete your own account'], 403);
    }
    
    // Verify user exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        sendJson(['error' => 'User not found'], 404);
    }
    
    // Check if user has appointments (clinician)
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM appointments WHERE clinician_id = ?");
    $stmt->execute([$userId]);
    $appointmentCount = $stmt->fetch()['count'];
    
    if ($appointmentCount > 0) {
        sendJson(['error' => 'Cannot delete user with existing appointments'], 409);
    }
    
    // Delete user (related records will be deleted due to CASCADE)
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    
    sendJson(['message' => 'User deleted successfully']);
}
?>
