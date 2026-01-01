-- Hospital Management System Database Schema
-- This script creates all necessary tables for the hospital management system with RBAC

-- Drop existing tables if they exist (for fresh setup)
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS resource_acl;
DROP TABLE IF EXISTS user_permissions;
DROP TABLE IF EXISTS user_roles;
DROP TABLE IF EXISTS role_permissions;
DROP TABLE IF EXISTS sessions;
DROP TABLE IF EXISTS appointments;
DROP TABLE IF EXISTS patients;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS permissions;
DROP TABLE IF EXISTS roles;
DROP TABLE IF EXISTS groups;
DROP TABLE IF EXISTS group_permissions;
DROP TABLE IF EXISTS user_groups;
SET FOREIGN_KEY_CHECKS = 1;

-- Roles table
CREATE TABLE roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) UNIQUE NOT NULL,
    description VARCHAR(255)
);

-- Permissions table
CREATE TABLE permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(150) UNIQUE NOT NULL,
    description VARCHAR(255)
);

-- Groups table (for convenience in assigning multiple permissions)
CREATE TABLE groups (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) UNIQUE NOT NULL,
    description VARCHAR(255)
);

-- Role-Permission mapping
CREATE TABLE role_permissions (
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
);

-- Group-Permission mapping
CREATE TABLE group_permissions (
    group_id INT NOT NULL,
    permission_id INT NOT NULL,
    PRIMARY KEY (group_id, permission_id),
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
);

-- Users table
CREATE TABLE users (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id)
);

-- User-Role assignments (supporting multiple roles)
CREATE TABLE user_roles (
    user_id BIGINT NOT NULL,
    role_id INT NOT NULL,
    PRIMARY KEY (user_id, role_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- User-Group assignments
CREATE TABLE user_groups (
    user_id BIGINT NOT NULL,
    group_id INT NOT NULL,
    PRIMARY KEY (user_id, group_id),
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Direct user permissions (overrides/exceptions)
CREATE TABLE user_permissions (
    user_id BIGINT NOT NULL,
    permission_id INT NOT NULL,
    allowed TINYINT(1) NOT NULL DEFAULT 1, -- 1 = allow, 0 = deny
    PRIMARY KEY (user_id, permission_id),
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Resource-level ACL (object-level)
CREATE TABLE resource_acl (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    resource_type VARCHAR(100) NOT NULL, -- e.g., "appointment", "patient"
    resource_id VARCHAR(255) NOT NULL,   -- object identifier
    subject_type ENUM('user','role') NOT NULL,
    subject_id VARCHAR(255) NOT NULL,    -- user_id or role_id
    permission_id INT NOT NULL,
    allowed TINYINT(1) DEFAULT 1,
    FOREIGN KEY (permission_id) REFERENCES permissions(id)
);

-- Sessions table for authentication
CREATE TABLE sessions (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    session_token VARCHAR(255) UNIQUE NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    revoked_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Patients table
CREATE TABLE patients (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    phone VARCHAR(50),
    date_of_birth DATE,
    address TEXT,
    emergency_contact VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Appointments table
CREATE TABLE appointments (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    patient_id BIGINT NOT NULL,
    clinician_id BIGINT NOT NULL,
    date_time TIMESTAMP NOT NULL,
    reason TEXT,
    status ENUM('Scheduled', 'Confirmed', 'Completed', 'Cancelled') DEFAULT 'Scheduled',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (clinician_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Insert default roles
INSERT INTO roles (name, description) VALUES 
('admin', 'System administrator with full access'),
('clinician', 'Healthcare provider who can manage appointments and patients'),
('receptionist', 'Front desk staff who can schedule appointments'),
('nurse', 'Nursing staff who can view and update patient information'),
('patient', 'Patient role for self-service access');

-- Insert default permissions
INSERT INTO permissions (name, description) VALUES 
('appointments:create', 'Create new appointments'),
('appointments:read', 'View appointments'),
('appointments:update', 'Update existing appointments'),
('appointments:delete', 'Delete appointments'),
('patients:create', 'Create new patient records'),
('patients:read', 'View patient information'),
('patients:update', 'Update patient information'),
('patients:delete', 'Delete patient records'),
('users:create', 'Create new users'),
('users:read', 'View user information'),
('users:update', 'Update user information'),
('users:delete', 'Delete users'),
('roles:manage', 'Manage roles and permissions'),
('system:admin', 'Full system administration');

-- Create default groups
INSERT INTO groups (name, description) VALUES 
('clinical_staff', 'Group for all clinical staff members'),
('administrative_staff', 'Group for administrative staff'),
('management', 'Group for management level staff');

-- Assign permissions to groups
INSERT INTO group_permissions (group_id, permission_id)
SELECT g.id, p.id FROM groups g, permissions p 
WHERE g.name = 'clinical_staff' AND p.name IN (
    'appointments:read', 'appointments:update', 'patients:read', 'patients:update'
);

INSERT INTO group_permissions (group_id, permission_id)
SELECT g.id, p.id FROM groups g, permissions p 
WHERE g.name = 'administrative_staff' AND p.name IN (
    'appointments:create', 'appointments:read', 'patients:create', 'patients:read'
);

INSERT INTO group_permissions (group_id, permission_id)
SELECT g.id, p.id FROM groups g, permissions p 
WHERE g.name = 'management' AND p.name IN (
    'users:read', 'users:update', 'appointments:read', 'patients:read'
);

-- Assign permissions to roles
-- Admin gets all permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p WHERE r.name = 'admin';

-- Clinician permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p 
WHERE r.name = 'clinician' AND p.name IN (
    'appointments:read', 'appointments:update', 'patients:read', 'patients:update'
);

-- Receptionist permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p 
WHERE r.name = 'receptionist' AND p.name IN (
    'appointments:create', 'appointments:read', 'patients:create', 'patients:read'
);

-- Nurse permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p 
WHERE r.name = 'nurse' AND p.name IN (
    'appointments:read', 'appointments:update', 'patients:read', 'patients:update'
);

-- Patient permissions (limited)
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p 
WHERE r.name = 'patient' AND p.name IN (
    'appointments:read', 'patients:read'
);

-- Create sample users with password 'password123'
INSERT INTO users (name, email, password_hash, role_id) VALUES 
('Admin User', 'admin@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', (SELECT id FROM roles WHERE name = 'admin')),
('Dr. Smith', 'drsmith@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', (SELECT id FROM roles WHERE name = 'clinician')),
('Dr. Johnson', 'drjohnson@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', (SELECT id FROM roles WHERE name = 'clinician')),
('Nurse Wilson', 'nwilson@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', (SELECT id FROM roles WHERE name = 'nurse')),
('Receptionist Brown', 'rbrown@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', (SELECT id FROM roles WHERE name = 'receptionist')),
('John Patient', 'jpatient@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', (SELECT id FROM roles WHERE name = 'patient'));

-- Assign users to roles (supporting multiple roles)
INSERT INTO user_roles (user_id, role_id)
SELECT u.id, r.id FROM users u, roles r 
WHERE u.email IN ('admin@hospital.com', 'drsmith@hospital.com', 'drjohnson@hospital.com', 'nwilson@hospital.com', 'rbrown@hospital.com', 'jpatient@email.com')
AND r.name = CASE 
    WHEN u.email = 'admin@hospital.com' THEN 'admin'
    WHEN u.email IN ('drsmith@hospital.com', 'drjohnson@hospital.com') THEN 'clinician'
    WHEN u.email = 'nwilson@hospital.com' THEN 'nurse'
    WHEN u.email = 'rbrown@hospital.com' THEN 'receptionist'
    WHEN u.email = 'jpatient@email.com' THEN 'patient'
END;

-- Assign users to groups
INSERT INTO user_groups (user_id, group_id)
SELECT u.id, g.id FROM users u, groups g
WHERE u.email IN ('drsmith@hospital.com', 'drjohnson@hospital.com', 'nwilson@hospital.com') AND g.name = 'clinical_staff'
OR u.email = 'rbrown@hospital.com' AND g.name = 'administrative_staff'
OR u.email = 'admin@hospital.com' AND g.name = 'management';

-- Create sample patients
INSERT INTO patients (name, email, phone, date_of_birth, address, emergency_contact) VALUES 
('Alice Johnson', 'alice@email.com', '555-0101', '1985-03-15', '123 Main St, City, State', 'Bob Johnson - 555-0102'),
('Bob Smith', 'bob@email.com', '555-0202', '1978-07-22', '456 Oak Ave, City, State', 'Carol Smith - 555-0203'),
('Carol Davis', 'carol@email.com', '555-0303', '1992-11-08', '789 Pine Rd, City, State', 'David Davis - 555-0304'),
('David Wilson', 'david@email.com', '555-0404', '1965-05-30', '321 Elm St, City, State', 'Eve Wilson - 555-0405');

-- Create sample appointments
INSERT INTO appointments (patient_id, clinician_id, date_time, reason, status) VALUES 
(1, 2, '2024-01-15 09:00:00', 'Annual checkup', 'Scheduled'),
(2, 2, '2024-01-15 10:30:00', 'Follow-up consultation', 'Confirmed'),
(3, 3, '2024-01-15 14:00:00', 'Initial consultation', 'Scheduled'),
(4, 3, '2024-01-16 11:00:00', 'Routine examination', 'Scheduled'),
(1, 3, '2024-01-20 15:30:00', 'Specialist referral', 'Scheduled');

-- Create indexes for better performance
CREATE INDEX idx_sessions_token ON sessions(session_token);
CREATE INDEX idx_sessions_user ON sessions(user_id);
CREATE INDEX idx_appointments_patient ON appointments(patient_id);
CREATE INDEX idx_appointments_clinician ON appointments(clinician_id);
CREATE INDEX idx_appointments_datetime ON appointments(date_time);
CREATE INDEX idx_resource_acl_resource ON resource_acl(resource_type, resource_id);
