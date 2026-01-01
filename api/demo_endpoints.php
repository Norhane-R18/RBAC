<?php
require_once 'db.php';
require_once 'utils.php';

header('Content-Type: application/json');

// Demo endpoints for testing RBAC system
// These endpoints simulate different permission requirements

try {
    $pdo = getDB();
    
    // Get the endpoint name from URL parameter
    $endpoint = $_GET['endpoint'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'];
    
    // Authenticate user (optional for some endpoints to test 401)
    $session = null;
    $currentUserId = null;
    $currentUserRole = null;
    
    // For endpoints that require authentication
    if ($endpoint !== 'public') {
        try {
            $session = authenticateUser($pdo);
            $currentUserId = $session['user_id'];
            $currentUserRole = $session['role_name'];
        } catch (Exception $e) {
            // Return 401 for endpoints that require auth
            sendJson(['error' => 'Authentication required', 'endpoint' => $endpoint], 401);
        }
    }
    
    switch ($endpoint) {
        case 'public':
            handlePublicEndpoint();
            break;
        case 'user_basic':
            handleUserBasicEndpoint($pdo, $currentUserId, $currentUserRole);
            break;
        case 'admin_only':
            handleAdminOnlyEndpoint($pdo, $currentUserId, $currentUserRole);
            break;
        case 'clinician_only':
            handleClinicianOnlyEndpoint($pdo, $currentUserId, $currentUserRole);
            break;
        case 'receptionist_only':
            handleReceptionistOnlyEndpoint($pdo, $currentUserId, $currentUserRole);
            break;
        case 'nurse_only':
            handleNurseOnlyEndpoint($pdo, $currentUserId, $currentUserRole);
            break;
        case 'multi_role':
            handleMultiRoleEndpoint($pdo, $currentUserId, $currentUserRole);
            break;
        default:
            sendJson(['error' => 'Endpoint not found'], 404);
    }
    
} catch (Exception $e) {
    sendJson(['error' => 'Internal server error'], 500);
}

function handlePublicEndpoint() {
    sendJson([
        'message' => 'This is a public endpoint - no authentication required',
        'status' => 'success',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

function handleUserBasicEndpoint($pdo, $currentUserId, $currentUserRole) {
    // Requires basic user permissions (any authenticated user)
    if (!authorize($pdo, $currentUserId, 'appointments:read')) {
        sendJson(['error' => 'Forbidden: Basic user access required'], 403);
    }
    
    sendJson([
        'message' => 'User basic endpoint - authenticated users only',
        'user_id' => $currentUserId,
        'role' => $currentUserRole,
        'status' => 'success',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

function handleAdminOnlyEndpoint($pdo, $currentUserId, $currentUserRole) {
    // Requires admin role
    if ($currentUserRole !== 'admin') {
        sendJson(['error' => 'Forbidden: Admin access required'], 403);
    }
    
    sendJson([
        'message' => 'Admin only endpoint - administrative access granted',
        'user_id' => $currentUserId,
        'role' => $currentUserRole,
        'admin_data' => [
            'total_users' => getTotalUsers($pdo),
            'total_appointments' => getTotalAppointments($pdo),
            'system_status' => 'operational'
        ],
        'status' => 'success',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

function handleClinicianOnlyEndpoint($pdo, $currentUserId, $currentUserRole) {
    // Requires clinician role or admin
    if (!in_array($currentUserRole, ['clinician', 'admin'])) {
        sendJson(['error' => 'Forbidden: Clinician access required'], 403);
    }
    
    // Get clinician-specific data
    $clinicianData = [];
    if ($currentUserRole === 'clinician') {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as my_appointments 
            FROM appointments 
            WHERE clinician_id = ?
        ");
        $stmt->execute([$currentUserId]);
        $clinicianData['my_appointments'] = $stmt->fetch()['my_appointments'];
    }
    
    sendJson([
        'message' => 'Clinician only endpoint - clinical access granted',
        'user_id' => $currentUserId,
        'role' => $currentUserRole,
        'clinician_data' => $clinicianData,
        'status' => 'success',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

function handleReceptionistOnlyEndpoint($pdo, $currentUserId, $currentUserRole) {
    // Requires receptionist role or admin
    if (!in_array($currentUserRole, ['receptionist', 'admin'])) {
        sendJson(['error' => 'Forbidden: Receptionist access required'], 403);
    }
    
    sendJson([
        'message' => 'Receptionist only endpoint - front desk access granted',
        'user_id' => $currentUserId,
        'role' => $currentUserRole,
        'receptionist_data' => [
            'today_appointments' => getTodayAppointments($pdo),
            'pending_confirmations' => getPendingConfirmations($pdo)
        ],
        'status' => 'success',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

function handleNurseOnlyEndpoint($pdo, $currentUserId, $currentUserRole) {
    // Requires nurse role or admin
    if (!in_array($currentUserRole, ['nurse', 'admin'])) {
        sendJson(['error' => 'Forbidden: Nurse access required'], 403);
    }
    
    sendJson([
        'message' => 'Nurse only endpoint - nursing access granted',
        'user_id' => $currentUserId,
        'role' => $currentUserRole,
        'nurse_data' => [
            'patient_count' => getPatientCount($pdo),
            'medication_alerts' => getMedicationAlerts($pdo)
        ],
        'status' => 'success',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

function handleMultiRoleEndpoint($pdo, $currentUserId, $currentUserRole) {
    // Allows multiple roles: admin, clinician, nurse
    $allowedRoles = ['admin', 'clinician', 'nurse'];
    
    if (!in_array($currentUserRole, $allowedRoles)) {
        sendJson(['error' => 'Forbidden: Clinical staff access required'], 403);
    }
    
    $roleSpecificData = [];
    switch ($currentUserRole) {
        case 'clinician':
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM appointments WHERE clinician_id = ?");
            $stmt->execute([$currentUserId]);
            $roleSpecificData['my_appointments'] = $stmt->fetch()['count'];
            break;
        case 'nurse':
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM patients");
            $stmt->execute();
            $roleSpecificData['total_patients'] = $stmt->fetch()['count'];
            break;
        case 'admin':
            $roleSpecificData['system_access'] = 'full';
            break;
    }
    
    sendJson([
        'message' => 'Multi-role endpoint - clinical staff access granted',
        'user_id' => $currentUserId,
        'role' => $currentUserRole,
        'allowed_roles' => $allowedRoles,
        'role_specific_data' => $roleSpecificData,
        'status' => 'success',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

// Helper functions for demo data
function getTotalUsers($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    return $stmt->fetch()['count'];
}

function getTotalAppointments($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM appointments");
    return $stmt->fetch()['count'];
}

function getTodayAppointments($pdo) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM appointments WHERE DATE(date_time) = CURDATE()");
    $stmt->execute();
    return $stmt->fetch()['count'];
}

function getPendingConfirmations($pdo) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM appointments WHERE status = 'Scheduled'");
    $stmt->execute();
    return $stmt->fetch()['count'];
}

function getPatientCount($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM patients");
    return $stmt->fetch()['count'];
}

function getMedicationAlerts($pdo) {
    // Demo function - returns mock data
    return 3;
}
?>
