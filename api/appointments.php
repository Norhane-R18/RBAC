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
    if (!authorize($pdo, $currentUserId, 'appointments:read')) {
        sendJson(['error' => 'Forbidden: Insufficient permissions'], 403);
    }
    
    if (isset($_GET['id'])) {
        // Get single appointment
        $appointmentId = $_GET['id'];
        
        // Check if user can access this specific appointment
        if (!authorize($pdo, $currentUserId, 'appointments:read', 'appointment', $appointmentId)) {
            sendJson(['error' => 'Forbidden: Cannot access this appointment'], 403);
        }
        
        $stmt = $pdo->prepare("
            SELECT a.*, 
                   p.name as patient_name, p.email as patient_email, p.phone as patient_phone,
                   u.name as clinician_name, u.email as clinician_email
            FROM appointments a
            JOIN patients p ON a.patient_id = p.id
            JOIN users u ON a.clinician_id = u.id
            WHERE a.id = ?
        ");
        $stmt->execute([$appointmentId]);
        $appointment = $stmt->fetch();
        
        if (!$appointment) {
            sendJson(['error' => 'Appointment not found'], 404);
        }
        
        sendJson($appointment);
    } else {
        // Get all appointments (filtered by role/policy)
        $query = "
            SELECT a.*, 
                   p.name as patient_name, p.email as patient_email, p.phone as patient_phone,
                   u.name as clinician_name, u.email as clinician_email
            FROM appointments a
            JOIN patients p ON a.patient_id = p.id
            JOIN users u ON a.clinician_id = u.id
        ";
        
        $params = [];
        
        // Apply policy-based filtering
        if ($currentUserRole === 'clinician') {
            // Clinicians can only see their own appointments
            $query .= " WHERE a.clinician_id = ?";
            $params[] = $currentUserId;
        } elseif ($currentUserRole === 'patient') {
            // Patients can only see their own appointments
            $query .= " WHERE p.email = (SELECT email FROM users WHERE id = ?)";
            $params[] = $currentUserId;
        }
        // Admins and receptionists can see all appointments
        
        $query .= " ORDER BY a.date_time ASC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $appointments = $stmt->fetchAll();
        
        sendJson($appointments);
    }
}

function handlePost($pdo, $currentUserId, $currentUserRole) {
    // Check create permission
    if (!authorize($pdo, $currentUserId, 'appointments:create')) {
        sendJson(['error' => 'Forbidden: Insufficient permissions to create appointments'], 403);
    }
    
    $input = getJsonInput();
    validateRequired($input, ['patient_id', 'clinician_id', 'date_time', 'reason']);
    
    // Additional validation
    if (!strtotime($input['date_time'])) {
        sendJson(['error' => 'Invalid date_time format'], 400);
    }
    
    // Verify patient exists
    $stmt = $pdo->prepare("SELECT id FROM patients WHERE id = ?");
    $stmt->execute([$input['patient_id']]);
    if (!$stmt->fetch()) {
        sendJson(['error' => 'Patient not found'], 404);
    }
    
    // Verify clinician exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$input['clinician_id']]);
    if (!$stmt->fetch()) {
        sendJson(['error' => 'Clinician not found'], 404);
    }
    
    // Policy check: non-admins can only create appointments for themselves (clinicians)
    if ($currentUserRole !== 'admin' && $input['clinician_id'] != $currentUserId) {
        sendJson(['error' => 'Forbidden: Can only create appointments for yourself'], 403);
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO appointments (patient_id, clinician_id, date_time, reason, status)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $input['patient_id'],
        $input['clinician_id'],
        $input['date_time'],
        $input['reason'],
        $input['status'] ?? 'Scheduled'
    ]);
    
    $appointmentId = $pdo->lastInsertId();
    
    sendJson([
        'message' => 'Appointment created successfully',
        'id' => $appointmentId
    ], 201);
}

function handlePut($pdo, $currentUserId, $currentUserRole) {
    if (!isset($_GET['id'])) {
        sendJson(['error' => 'Missing appointment ID'], 400);
    }
    
    $appointmentId = $_GET['id'];
    
    // Check update permission
    if (!authorize($pdo, $currentUserId, 'appointments:update', 'appointment', $appointmentId)) {
        sendJson(['error' => 'Forbidden: Insufficient permissions to update this appointment'], 403);
    }
    
    $input = getJsonInput();
    
    // Verify appointment exists
    $stmt = $pdo->prepare("SELECT * FROM appointments WHERE id = ?");
    $stmt->execute([$appointmentId]);
    $appointment = $stmt->fetch();
    
    if (!$appointment) {
        sendJson(['error' => 'Appointment not found'], 404);
    }
    
    // Policy check: clinicians can only update their own appointments
    if ($currentUserRole === 'clinician' && $appointment['clinician_id'] != $currentUserId) {
        sendJson(['error' => 'Forbidden: Can only update your own appointments'], 403);
    }
    
    // Build update query dynamically
    $updateFields = [];
    $params = [];
    
    $allowedFields = ['patient_id', 'clinician_id', 'date_time', 'reason', 'status', 'notes'];
    
    foreach ($allowedFields as $field) {
        if (isset($input[$field])) {
            $updateFields[] = "$field = ?";
            $params[] = $input[$field];
        }
    }
    
    if (empty($updateFields)) {
        sendJson(['error' => 'No valid fields to update'], 400);
    }
    
    // Additional validation for specific fields
    if (isset($input['patient_id'])) {
        $stmt = $pdo->prepare("SELECT id FROM patients WHERE id = ?");
        $stmt->execute([$input['patient_id']]);
        if (!$stmt->fetch()) {
            sendJson(['error' => 'Patient not found'], 404);
        }
    }
    
    if (isset($input['clinician_id'])) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->execute([$input['clinician_id']]);
        if (!$stmt->fetch()) {
            sendJson(['error' => 'Clinician not found'], 404);
        }
        
        // Policy check: clinicians can't reassign appointments to others
        if ($currentUserRole === 'clinician' && $input['clinician_id'] != $currentUserId) {
            sendJson(['error' => 'Forbidden: Cannot reassign appointment to another clinician'], 403);
        }
    }
    
    if (isset($input['date_time']) && !strtotime($input['date_time'])) {
        sendJson(['error' => 'Invalid date_time format'], 400);
    }
    
    $params[] = $appointmentId;
    
    $query = "UPDATE appointments SET " . implode(', ', $updateFields) . " WHERE id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    
    sendJson(['message' => 'Appointment updated successfully']);
}

function handleDelete($pdo, $currentUserId, $currentUserRole) {
    if (!isset($_GET['id'])) {
        sendJson(['error' => 'Missing appointment ID'], 400);
    }
    
    $appointmentId = $_GET['id'];
    
    // Check delete permission
    if (!authorize($pdo, $currentUserId, 'appointments:delete', 'appointment', $appointmentId)) {
        sendJson(['error' => 'Forbidden: Insufficient permissions to delete this appointment'], 403);
    }
    
    // Verify appointment exists
    $stmt = $pdo->prepare("SELECT * FROM appointments WHERE id = ?");
    $stmt->execute([$appointmentId]);
    $appointment = $stmt->fetch();
    
    if (!$appointment) {
        sendJson(['error' => 'Appointment not found'], 404);
    }
    
    // Policy check: clinicians can only delete their own appointments
    if ($currentUserRole === 'clinician' && $appointment['clinician_id'] != $currentUserId) {
        sendJson(['error' => 'Forbidden: Can only delete your own appointments'], 403);
    }
    
    $stmt = $pdo->prepare("DELETE FROM appointments WHERE id = ?");
    $stmt->execute([$appointmentId]);
    
    sendJson(['message' => 'Appointment deleted successfully']);
}
?>
