<?php
require_once 'db.php';
require_once 'utils.php';

header('Content-Type: application/json');

try {
    $pdo = getDB();
    
    // Authenticate user for all requests
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
    // Check read permission
    if (!authorize($pdo, $currentUserId, 'patients:read')) {
        sendJson(['error' => 'Forbidden: Insufficient permissions'], 403);
    }
    
    if (isset($_GET['id'])) {
        // Get single patient
        $patientId = $_GET['id'];
        
        // Check if user can access this specific patient
        if (!authorize($pdo, $currentUserId, 'patients:read', 'patient', $patientId)) {
            sendJson(['error' => 'Forbidden: Cannot access this patient record'], 403);
        }
        
        $stmt = $pdo->prepare("
            SELECT p.*, 
                   COUNT(a.id) as appointment_count,
                   MAX(a.date_time) as last_appointment
            FROM patients p
            LEFT JOIN appointments a ON p.id = a.patient_id
            WHERE p.id = ?
            GROUP BY p.id
        ");
        $stmt->execute([$patientId]);
        $patient = $stmt->fetch();
        
        if (!$patient) {
            sendJson(['error' => 'Patient not found'], 404);
        }
        
        sendJson($patient);
    } else {
        // Get all patients (filtered by role/policy)
        $query = "
            SELECT p.*, 
                   COUNT(a.id) as appointment_count,
                   MAX(a.date_time) as last_appointment
            FROM patients p
            LEFT JOIN appointments a ON p.id = a.patient_id
        ";
        
        $params = [];
        
        // Apply policy-based filtering
        if ($currentUserRole === 'patient') {
            // Patients can only see their own records
            $query .= " WHERE p.email = (SELECT email FROM users WHERE id = ?)";
            $params[] = $currentUserId;
        }
        
        $query .= " GROUP BY p.id ORDER BY p.name ASC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $patients = $stmt->fetchAll();
        
        sendJson($patients);
    }
}

function handlePost($pdo, $currentUserId, $currentUserRole) {
    // Check create permission
    if (!authorize($pdo, $currentUserId, 'patients:create')) {
        sendJson(['error' => 'Forbidden: Insufficient permissions to create patient records'], 403);
    }
    
    $input = getJsonInput();
    validateRequired($input, ['name', 'email']);
    
    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM patients WHERE email = ?");
    $stmt->execute([$input['email']]);
    if ($stmt->fetch()) {
        sendJson(['error' => 'Patient with this email already exists'], 409);
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO patients (name, email, phone, date_of_birth, address, emergency_contact)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $input['name'],
        $input['email'],
        $input['phone'] ?? null,
        $input['date_of_birth'] ?? null,
        $input['address'] ?? null,
        $input['emergency_contact'] ?? null
    ]);
    
    $patientId = $pdo->lastInsertId();
    
    sendJson([
        'message' => 'Patient created successfully',
        'id' => $patientId
    ], 201);
}

function handlePut($pdo, $currentUserId, $currentUserRole) {
    if (!isset($_GET['id'])) {
        sendJson(['error' => 'Missing patient ID'], 400);
    }
    
    $patientId = $_GET['id'];
    
    // Check update permission
    if (!authorize($pdo, $currentUserId, 'patients:update', 'patient', $patientId)) {
        sendJson(['error' => 'Forbidden: Insufficient permissions to update this patient record'], 403);
    }
    
    $input = getJsonInput();
    
    // Verify patient exists
    $stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
    $stmt->execute([$patientId]);
    $patient = $stmt->fetch();
    
    if (!$patient) {
        sendJson(['error' => 'Patient not found'], 404);
    }
    
    // Build update query dynamically
    $updateFields = [];
    $params = [];
    
    $allowedFields = ['name', 'email', 'phone', 'date_of_birth', 'address', 'emergency_contact'];
    
    foreach ($allowedFields as $field) {
        if (isset($input[$field])) {
            $updateFields[] = "$field = ?";
            $params[] = $input[$field];
        }
    }
    
    if (empty($updateFields)) {
        sendJson(['error' => 'No valid fields to update'], 400);
    }
    
    // Check email uniqueness if being updated
    if (isset($input['email'])) {
        $stmt = $pdo->prepare("SELECT id FROM patients WHERE email = ? AND id != ?");
        $stmt->execute([$input['email'], $patientId]);
        if ($stmt->fetch()) {
            sendJson(['error' => 'Patient with this email already exists'], 409);
        }
    }
    
    $params[] = $patientId;
    
    $query = "UPDATE patients SET " . implode(', ', $updateFields) . " WHERE id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    
    sendJson(['message' => 'Patient updated successfully']);
}

function handleDelete($pdo, $currentUserId, $currentUserRole) {
    if (!isset($_GET['id'])) {
        sendJson(['error' => 'Missing patient ID'], 400);
    }
    
    $patientId = $_GET['id'];
    
    // Check delete permission
    if (!authorize($pdo, $currentUserId, 'patients:delete', 'patient', $patientId)) {
        sendJson(['error' => 'Forbidden: Insufficient permissions to delete this patient record'], 403);
    }
    
    // Verify patient exists
    $stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
    $stmt->execute([$patientId]);
    $patient = $stmt->fetch();
    
    if (!$patient) {
        sendJson(['error' => 'Patient not found'], 404);
    }
    
    // Check if patient has appointments
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM appointments WHERE patient_id = ?");
    $stmt->execute([$patientId]);
    $appointmentCount = $stmt->fetch()['count'];
    
    if ($appointmentCount > 0) {
        sendJson(['error' => 'Cannot delete patient with existing appointments'], 409);
    }
    
    $stmt = $pdo->prepare("DELETE FROM patients WHERE id = ?");
    $stmt->execute([$patientId]);
    
    sendJson(['message' => 'Patient deleted successfully']);
}
?>
