<?php
// Utility functions for the API

// Send JSON response with proper headers
function sendJson($data, $statusCode = 200) {
    header_remove();
    header('Content-Type: application/json');
    http_response_code($statusCode);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

// Get JSON input from request body
function getJsonInput() {
    $input = file_get_contents('php://input');
    error_log("Raw input: " . $input);
    $decoded = json_decode($input, true) ?: [];
    error_log("Decoded input: " . json_encode($decoded));
    return $decoded;
}

// Validate required fields in input
function validateRequired($input, $requiredFields) {
    $missing = [];
    foreach ($requiredFields as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            $missing[] = $field;
        }
    }
    if (!empty($missing)) {
        sendJson(['error' => 'Missing required fields: ' . implode(', ', $missing)], 400);
    }
}

// Get Bearer token from Authorization header
function getBearerToken() {
    $headers = getallheaders();
    if (isset($headers['Authorization'])) {
        $authHeader = $headers['Authorization'];
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $matches[1];
        }
    }
    return null;
}

// Authenticate user and return user data
function authenticateUser($pdo) {
    $token = getBearerToken();
    if (!$token) {
        sendJson(['error' => 'Missing authentication token'], 401);
    }

    // Check if token exists and is valid
    $stmt = $pdo->prepare("
        SELECT s.*, u.name, u.email, u.role_id, r.name as role_name 
        FROM sessions s 
        JOIN users u ON s.user_id = u.id 
        LEFT JOIN roles r ON u.role_id = r.id 
        WHERE s.session_token = ? AND s.revoked_at IS NULL AND s.expires_at > NOW()
    ");
    $stmt->execute([$token]);
    $session = $stmt->fetch();

    if (!$session) {
        sendJson(['error' => 'Invalid or expired token'], 401);
    }

    return $session;
}

// Authorization function - check if user has permission
function authorize($pdo, $userId, $permissionName, $resourceType = null, $resourceId = null, $context = []) {
    // 1) Get permission ID
    $stmt = $pdo->prepare("SELECT id FROM permissions WHERE name = ?");
    $stmt->execute([$permissionName]);
    $permission = $stmt->fetch();
    
    if (!$permission) {
        return false; // Unknown permission -> deny
    }
    
    $permissionId = $permission['id'];

    // 2) Check explicit resource ACL (highest priority)
    if ($resourceType && $resourceId) {
        $stmt = $pdo->prepare("
            SELECT allowed, subject_type, subject_id 
            FROM resource_acl 
            WHERE resource_type = ? AND resource_id = ? AND permission_id = ?
        ");
        $stmt->execute([$resourceType, $resourceId, $permissionId]);
        
        while ($acl = $stmt->fetch()) {
            if ($acl['subject_type'] === 'user' && $acl['subject_id'] == $userId) {
                return (bool)$acl['allowed'];
            }
            
            // Check role-based ACL
            if ($acl['subject_type'] === 'role') {
                $roleStmt = $pdo->prepare("
                    SELECT 1 FROM user_roles ur 
                    WHERE ur.user_id = ? AND ur.role_id = ?
                ");
                $roleStmt->execute([$userId, $acl['subject_id']]);
                if ($roleStmt->fetch()) {
                    return (bool)$acl['allowed'];
                }
            }
        }
    }

    // 3) Check user-specific permission overrides
    $stmt = $pdo->prepare("
        SELECT allowed FROM user_permissions 
        WHERE user_id = ? AND permission_id = ?
    ");
    $stmt->execute([$userId, $permissionId]);
    $userPerm = $stmt->fetch();
    
    if ($userPerm) {
        return (bool)$userPerm['allowed'];
    }

    // 4) Check role-based permissions
    $stmt = $pdo->prepare("
        SELECT 1 FROM role_permissions rp
        JOIN user_roles ur ON ur.role_id = rp.role_id
        WHERE ur.user_id = ? AND rp.permission_id = ?
    ");
    $stmt->execute([$userId, $permissionId]);
    
    if (!$stmt->fetch()) {
        return false; // No role permission found
    }

    // 5) Apply policy-based checks
    if ($resourceType && $resourceId) {
        // Example policy: clinicians can only access their own appointments
        if ($permissionName === 'appointments:update' || $permissionName === 'appointments:delete') {
            $stmt = $pdo->prepare("
                SELECT u.role_id FROM users u 
                WHERE u.id = ? AND u.role_id = (SELECT id FROM roles WHERE name = 'clinician')
            ");
            $stmt->execute([$userId]);
            $isClinician = $stmt->fetch();
            
            if ($isClinician && $resourceType === 'appointment') {
                $stmt = $pdo->prepare("
                    SELECT clinician_id FROM appointments WHERE id = ?
                ");
                $stmt->execute([$resourceId]);
                $appointment = $stmt->fetch();
                
                if ($appointment && $appointment['clinician_id'] != $userId) {
                    return false; // Clinician trying to access another's appointment
                }
            }
        }
        
        // Example policy: users can only access their own patient records
        if (strpos($permissionName, 'patients:') === 0) {
            $stmt = $pdo->prepare("
                SELECT u.role_id FROM users u 
                WHERE u.id = ? AND u.role_id NOT IN (SELECT id FROM roles WHERE name IN ('admin', 'clinician', 'nurse'))
            ");
            $stmt->execute([$userId]);
            $isRegularUser = $stmt->fetch();
            
            if ($isRegularUser) {
                // For regular users, implement additional policies as needed
                // This is a placeholder for patient-specific access control
            }
        }
    }

    return true; // Allow by default if no explicit deny found
}

// Generate random token
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

// Log API access for auditing
function logAccess($pdo, $userId, $endpoint, $method, $allowed, $details = '') {
    $stmt = $pdo->prepare("
        INSERT INTO access_logs (user_id, endpoint, method, allowed, details, ip_address, user_agent, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $userId,
        $endpoint,
        $method,
        $allowed ? 1 : 0,
        $details,
        $_SERVER['REMOTE_ADDR'] ?? '',
        $_SERVER['HTTP_USER_AGENT'] ?? ''
    ]);
}

// Get current user's roles
function getUserRoles($pdo, $userId) {
    $stmt = $pdo->prepare("
        SELECT r.id, r.name 
        FROM roles r 
        JOIN user_roles ur ON r.id = ur.role_id 
        WHERE ur.user_id = ?
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

// Get current user's permissions (including from groups)
function getUserPermissions($pdo, $userId) {
    $permissions = [];
    
    // Get direct role permissions
    $stmt = $pdo->prepare("
        SELECT DISTINCT p.id, p.name 
        FROM permissions p 
        JOIN role_permissions rp ON p.id = rp.permission_id 
        JOIN user_roles ur ON rp.role_id = ur.role_id 
        WHERE ur.user_id = ?
    ");
    $stmt->execute([$userId]);
    $rolePerms = $stmt->fetchAll();
    
    // Get group permissions
    $stmt = $pdo->prepare("
        SELECT DISTINCT p.id, p.name 
        FROM permissions p 
        JOIN group_permissions gp ON p.id = gp.permission_id 
        JOIN user_groups ug ON gp.group_id = ug.group_id 
        WHERE ug.user_id = ?
    ");
    $stmt->execute([$userId]);
    $groupPerms = $stmt->fetchAll();
    
    // Get direct user permissions
    $stmt = $pdo->prepare("
        SELECT p.id, p.name, up.allowed 
        FROM permissions p 
        JOIN user_permissions up ON p.id = up.permission_id 
        WHERE up.user_id = ?
    ");
    $stmt->execute([$userId]);
    $userPerms = $stmt->fetchAll();
    
    // Merge permissions (user permissions override role/group permissions)
    $allPerms = array_merge($rolePerms, $groupPerms);
    
    // Apply user permission overrides
    foreach ($userPerms as $userPerm) {
        if ($userPerm['allowed'] == 0) {
            // Remove denied permission
            $allPerms = array_filter($allPerms, function($perm) use ($userPerm) {
                return $perm['id'] != $userPerm['id'];
            });
        } else {
            // Add allowed permission if not already present
            $found = false;
            foreach ($allPerms as $perm) {
                if ($perm['id'] == $userPerm['id']) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $allPerms[] = $userPerm;
            }
        }
    }
    
    return array_values($allPerms);
}
?>
