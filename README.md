# Hospital Management System with RBAC

A comprehensive hospital management system demonstrating secure access control implementation with Role-Based Access Control (RBAC), Permission-Based Access Control (PBAC), and Policy-Based Access Control.

## 🏥 Overview

This system implements a secure REST API for hospital operations with comprehensive access control mechanisms to prevent Broken Access Control vulnerabilities. It includes a dashboard for testing and managing users, roles, permissions, and groups.

## 🔐 Security Features

### Access Control Implementation
- **Authentication**: Secure login with JWT-like session tokens
- **Role-Based Access Control (RBAC)**: Users assigned to roles (admin, clinician, nurse, receptionist, patient)
- **Permission-Based Access Control (PBAC)**: Fine-grained permissions for each action
- **Policy-Based Access Control**: Context-aware policies (e.g., clinicians can only access their own appointments)
- **Resource-Level ACL**: Object-level permissions for specific resources
- **Groups**: Convenient permission assignment to multiple users

### Security Principles Applied
- ✅ **Deny by default**: All access denied unless explicitly permitted
- ✅ **Server-side enforcement**: No reliance on client-side controls
- ✅ **Least privilege**: Users get minimum required permissions
- ✅ **Centralized authorization**: Single authorization function
- ✅ **Separation of duties**: Clear distinction between auth and authz
- ✅ **Fail-safe**: Safe responses on errors (403, 401)
- ✅ **Audit logging**: All access decisions logged

## 📁 Project Structure

```
windsurf-project/
├── database/
│   └── setup.sql              # Database schema and sample data
├── api/
│   ├── db.php                 # Database connection
│   ├── utils.php              # Utility functions and authorization
│   ├── login.php              # Authentication endpoint
│   ├── me.php                 # Current user info
│   ├── appointments.php       # Appointments CRUD API
│   ├── patients.php           # Patients CRUD API
│   ├── users.php              # Users management API
│   ├── roles.php              # Roles management API
│   ├── permissions.php        # Permissions management API
│   ├── groups.php             # Groups management API
│   └── demo_endpoints.php     # Demo endpoints for testing
└── dashboard/
    ├── index.html             # Main dashboard UI
    └── dashboard.js           # Dashboard JavaScript
```

## 🚀 Setup Instructions

### 1. Database Setup
```bash
# Create MySQL database
mysql -u root -p
CREATE DATABASE hospital_management;
USE hospital_management;

# Import schema and sample data
SOURCE database/setup.sql;
```

### 2. Configure Database Connection
Edit `api/db.php` with your database credentials:
```php
private $host = 'localhost';
private $db_name = 'hospital_management';
private $username = 'root';
private $password = 'your_password';
```

### 3. Web Server Setup
Place the project in your web server's document root (e.g., `/var/www/html/hospital/`).

### 4. Access the Dashboard
Open your browser and navigate to: `http://localhost/hospital/dashboard/`

## 👤 Demo Accounts

All accounts use password: `password123`

| Role | Email | Access Level |
|------|-------|-------------|
| Admin | admin@hospital.com | Full system access |
| Clinician | drsmith@hospital.com | Medical appointments and patients |
| Nurse | nwilson@hospital.com | Patient care and updates |
| Receptionist | rbrown@hospital.com | Appointment scheduling |
| Patient | jpatient@email.com | Limited self-service access |

## 🔌 API Endpoints

### Authentication
- `POST /api/login.php` - User login
- `GET /api/me.php` - Get current user info

### Appointments Management
- `GET /api/appointments.php` - List appointments (filtered by role)
- `GET /api/appointments.php?id={id}` - Get specific appointment
- `POST /api/appointments.php` - Create appointment
- `PUT /api/appointments.php?id={id}` - Update appointment
- `DELETE /api/appointments.php?id={id}` - Delete appointment

### Patients Management
- `GET /api/patients.php` - List patients
- `GET /api/patients.php?id={id}` - Get specific patient
- `POST /api/patients.php` - Create patient
- `PUT /api/patients.php?id={id}` - Update patient
- `DELETE /api/patients.php?id={id}` - Delete patient

### Demo Endpoints (for testing)
- `GET /api/demo_endpoints.php?endpoint=public` - Public endpoint (no auth)
- `GET /api/demo_endpoints.php?endpoint=user_basic` - Authenticated users only
- `GET /api/demo_endpoints.php?endpoint=admin_only` - Admin only
- `GET /api/demo_endpoints.php?endpoint=clinician_only` - Clinician only
- `GET /api/demo_endpoints.php?endpoint=receptionist_only` - Receptionist only
- `GET /api/demo_endpoints.php?endpoint=nurse_only` - Nurse only
- `GET /api/demo_endpoints.php?endpoint=multi_role` - Multiple roles allowed

## 🛡️ Access Control Examples

### Role-Based Restrictions
```php
// Only admins can manage users
if ($currentUserRole !== 'admin') {
    sendJson(['error' => 'Forbidden: Admin access required'], 403);
}
```

### Permission-Based Checks
```php
// Check specific permission
if (!authorize($pdo, $currentUserId, 'appointments:create')) {
    sendJson(['error' => 'Forbidden: Insufficient permissions'], 403);
}
```

### Policy-Based Controls
```php
// Clinicians can only access their own appointments
if ($currentUserRole === 'clinician') {
    $query .= " WHERE a.clinician_id = ?";
    $params[] = $currentUserId;
}
```

### Resource-Level ACL
```php
// Check object-level permissions
if (!authorize($pdo, $currentUserId, 'appointments:update', 'appointment', $appointmentId)) {
    sendJson(['error' => 'Forbidden: Cannot access this appointment'], 403);
}
```

## 🧪 Testing the System

### 1. API Testing with Dashboard
1. Login with different user accounts
2. Navigate to "API Tester" page
3. Test each endpoint to see different responses based on permissions
4. Observe 200/401/403 responses

### 2. Manual API Testing
```bash
# Login as admin
curl -X POST http://localhost/hospital/api/login.php \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@hospital.com","password":"password123"}'

# Use token to access protected endpoint
curl -X GET http://localhost/hospital/api/appointments.php \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

### 3. Test Access Control Scenarios
- **Unauthorized Access**: Try accessing endpoints without token (should get 401)
- **Forbidden Access**: Use clinician token to access admin-only endpoints (should get 403)
- **Policy Violation**: Clinician trying to access another's appointments (should get 403)
- **Successful Access**: Use appropriate roles for permitted actions (should get 200)

## 🔍 Security Vulnerabilities Prevented

This implementation prevents the following Broken Access Control vulnerabilities:

1. **Missing Server-Side Checks**: All authorization happens server-side
2. **Inconsistent Enforcement**: Centralized authorization function
3. **Predictable Object IDs**: Resource-level ACL prevents IDOR attacks
4. **Overly Broad Permissions**: Fine-grained permission system
5. **Mass Assignment**: Explicit field validation and whitelisting
6. **Function-Level Bypass**: Every function checks permissions
7. **Hidden Endpoint Discovery**: All endpoints protected by default
8. **Client-Side Trust**: No reliance on client-side controls

## 📊 Dashboard Features

### API Tester Page
- Test 7 different demo endpoints
- Visual display of HTTP status codes (200/401/403)
- Swagger-like response formatting
- Real-time permission testing

### Management Pages
- **Roles**: View and manage system roles
- **Permissions**: View and manage fine-grained permissions
- **Groups**: Manage permission groups for convenience
- **Users**: Manage user assignments with toggle switches

### User Management
- Assign multiple roles to users
- Add users to permission groups
- Set direct permission overrides
- Visual feedback for all changes

## 🔄 Workflow Examples

### Clinician Workflow
1. Login as clinician
2. View only their appointments (policy-based filtering)
3. Update their appointments (allowed)
4. Try to access admin functions (forbidden - 403)
5. Try to access other clinicians' appointments (forbidden - 403)

### Admin Workflow
1. Login as admin
2. Access all appointments (full access)
3. Manage users, roles, permissions
4. View system-wide data
5. All operations succeed (200 OK)

## 🛠️ Technologies Used

- **Backend**: PHP with PDO
- **Database**: MySQL
- **Frontend**: HTML5, CSS3, Bootstrap 5
- **JavaScript**: jQuery
- **Authentication**: Session-based tokens
- **Security**: Password hashing, prepared statements, input validation

## 📝 License

This project is for educational purposes to demonstrate secure coding practices and access control implementation.

## 🤝 Contributing

Feel free to submit issues and enhancement requests to improve the security and functionality of this hospital management system.

---

**⚠️ Important**: This is a demonstration system. In production, additional security measures like HTTPS, rate limiting, input sanitization, and comprehensive logging should be implemented.
