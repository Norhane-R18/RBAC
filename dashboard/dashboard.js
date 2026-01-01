// Hospital RBAC Dashboard JavaScript

let currentUser = null;
let authToken = null;

// Initialize dashboard
$(document).ready(function() {
    // Check if user is logged in
    const token = localStorage.getItem('authToken');
    const user = localStorage.getItem('currentUser');
    
    if (token && user) {
        authToken = token;
        currentUser = JSON.parse(user);
        showDashboard();
        loadUserInfo();
    } else {
        showLogin();
    }
    
    // Global AJAX error handler for 401 responses
    $(document).ajaxError(function(event, xhr, settings, error) {
        if (xhr.status === 401) {
            // Token expired or invalid
            console.log('401 Unauthorized - Session expired');
            
            // Clear stored auth data
            localStorage.removeItem('authToken');
            localStorage.removeItem('currentUser');
            authToken = null;
            currentUser = null;
            
            // Show user-friendly message
            showAuthError('Your session has expired. Please log in again.');
            
            // Redirect to login
            showLogin();
        } else if (xhr.status === 403) {
            // Forbidden - insufficient permissions
            showPermissionError('You don\'t have permission to perform this action.');
        } else if (xhr.status === 404) {
            // Not found
            showError('The requested resource was not found.');
        } else if (xhr.status >= 500) {
            // Server error
            showError('Server error. Please try again later.');
        }
    });

    // Login form submission
    $('#loginForm').on('submit', function(e) {
        e.preventDefault();
        login();
    });

    // Navigation
    $('.sidebar .nav-link[data-page]').on('click', function(e) {
        e.preventDefault();
        const page = $(this).data('page');
        showPage(page);
        
        // Update active nav
        $('.sidebar .nav-link').removeClass('active');
        $(this).addClass('active');
        
        // Load page data
        loadPageData(page);
    });

});

// Login function
function login() {
    const email = $('#email').val();
    const password = $('#password').val();

    $.ajax({
        url: '../api/login.php',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({ email, password }),
        success: function(response) {
            authToken = response.token;
            currentUser = response.user;
            localStorage.setItem('authToken', authToken);
            localStorage.setItem('currentUser', JSON.stringify(currentUser));
            showDashboard();
            loadUserInfo();
        },
        error: function(xhr) {
            const error = xhr.responseJSON ? xhr.responseJSON.error : 'Login failed';
            alert('Error: ' + error);
        }
    });
}

// Logout function
function logout() {
    localStorage.removeItem('authToken');
    localStorage.removeItem('currentUser');
    authToken = null;
    currentUser = null;
    showLogin();
}

// Show login page
function showLogin() {
    $('#loginPage').show();
    $('#dashboardPage').hide();
}

// Show dashboard
function showDashboard() {
    $('#loginPage').hide();
    $('#dashboardPage').show();
}

// Show specific page
function showPage(pageName) {
    $('.page-section').removeClass('active');
    $('#' + pageName).addClass('active');
}

// Load user information
function loadUserInfo() {
    $.ajax({
        url: '../api/me.php',
        method: 'GET',
        headers: {
            'Authorization': 'Bearer ' + authToken
        },
        success: function(response) {
            currentUser = response.user;
            const userDetails = `
                <div class="row">
                    <div class="col-md-6">
                        <strong>Name:</strong> ${response.user.name}<br>
                        <strong>Email:</strong> ${response.user.email}<br>
                        <strong>Role:</strong> <span class="badge bg-primary">${response.user.role_name}</span>
                    </div>
                    <div class="col-md-6">
                        <strong>Roles:</strong> ${response.roles.map(r => `<span class="badge bg-info">${r.name}</span>`).join(' ')}<br>
                        <strong>Groups:</strong> ${response.groups.map(g => `<span class="badge bg-secondary">${g.name}</span>`).join(' ')}<br>
                        <strong>Permissions:</strong> ${response.permissions.length} granted
                    </div>
                </div>
            `;
            $('#userDetails').html(userDetails);
        },
        error: function(xhr) {
            if (xhr.status === 401) {
                logout();
            }
        }
    });
}

// Load page-specific data
function loadPageData(page) {
    switch(page) {
        case 'roles':
            loadRoles();
            break;
        case 'permissions':
            loadPermissions();
            break;
        case 'groups':
            loadGroups();
            break;
        case 'users':
            loadUsers();
            break;
    }
}


// API Test Functions
function callAPI(endpoint) {
    const url = `../api/demo_endpoints.php?endpoint=${endpoint}`;
    
    $.ajax({
        url: url,
        method: 'GET',
        headers: authToken ? { 'Authorization': 'Bearer ' + authToken } : {},
        success: function(response) {
            displayAPIResponse(endpoint, response, 200);
        },
        error: function(xhr) {
            const response = xhr.responseJSON || { error: 'Request failed' };
            displayAPIResponse(endpoint, response, xhr.status);
        }
    });
}

function displayAPIResponse(endpoint, response, status) {
    const responseDiv = $(`#response-${endpoint}`);
    const statusClass = getStatusClass(status);
    const statusText = getStatusText(status);
    
    // Format JSON with proper escaping
    const jsonString = JSON.stringify(response, null, 2);
    
    let html = `
        <div class="status-header">
            <div>
                <strong style="color: #9cdcfe;">HTTP Status Code:</strong>
            </div>
            <span class="status-badge ${statusClass}">${status} ${statusText}</span>
        </div>
        <div style="margin-top: 10px;">
            <strong style="color: #9cdcfe;">Response Body:</strong>
        </div>
        <pre style="margin-top: 8px;">${escapeHtml(jsonString)}</pre>
    `;
    
    responseDiv.html(html);
}

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

function getStatusClass(status) {
    switch(status) {
        case 200: return 'status-200';
        case 401: return 'status-401';
        case 403: return 'status-403';
        case 404: return 'status-404';
        case 500: return 'status-500';
        default: return '';
    }
}

function getStatusText(status) {
    switch(status) {
        case 200: return 'OK';
        case 401: return 'Unauthorized';
        case 403: return 'Forbidden';
        case 404: return 'Not Found';
        case 500: return 'Internal Server Error';
        default: return 'Unknown';
    }
}

// Load roles (Page 2)
function loadRoles() {
    $.ajax({
        url: '../api/roles.php',
        method: 'GET',
        headers: {
            'Authorization': 'Bearer ' + authToken
        },
        success: function(response) {
            let html = '';
            if (response.roles && response.roles.length > 0) {
                response.roles.forEach(role => {
                    html += `
                        <tr>
                            <td>${role.id}</td>
                            <td><strong>${role.name}</strong></td>
                            <td>${role.description || '<em class="text-muted">No description</em>'}</td>
                        </tr>
                    `;
                });
            } else {
                html = '<tr><td colspan="3" class="text-center text-muted">No roles found</td></tr>';
            }
            $('#rolesTableBody').html(html);
        },
        error: function(xhr) {
            $('#rolesTableBody').html('<tr><td colspan="3" class="text-center text-danger">Error loading roles</td></tr>');
        }
    });
}

// Load permissions (Page 3)
function loadPermissions() {
    $.ajax({
        url: '../api/permissions.php',
        method: 'GET',
        headers: {
            'Authorization': 'Bearer ' + authToken
        },
        success: function(response) {
            let html = '';
            if (response.permissions && response.permissions.length > 0) {
                response.permissions.forEach(perm => {
                    html += `
                        <tr>
                            <td>${perm.id}</td>
                            <td><strong>${perm.name}</strong></td>
                            <td>${perm.description || '<em class="text-muted">No description</em>'}</td>
                        </tr>
                    `;
                });
            } else {
                html = '<tr><td colspan="3" class="text-center text-muted">No permissions found</td></tr>';
            }
            $('#permissionsTableBody').html(html);
        },
        error: function(xhr) {
            $('#permissionsTableBody').html('<tr><td colspan="3" class="text-center text-danger">Error loading permissions</td></tr>');
        }
    });
}

// Load groups
// Load groups (Page 4)
function loadGroups() {
    $.ajax({
        url: '../api/groups.php',
        method: 'GET',
        headers: {
            'Authorization': 'Bearer ' + authToken
        },
        success: function(response) {
            if (!response.groups || response.groups.length === 0) {
                $('#groupsList').html('<div class="alert alert-info">No groups found</div>');
                return;
            }
            
            // Load all permissions for assignment
            $.ajax({
                url: '../api/permissions.php',
                method: 'GET',
                headers: {
                    'Authorization': 'Bearer ' + authToken
                },
                success: function(permResponse) {
                    let html = '<div class="row">';
                    response.groups.forEach(group => {
                        const groupPermIds = group.permissions ? group.permissions.map(p => p.id || p) : [];
                        html += `
                            <div class="col-md-6 mb-4">
                                <div class="card h-100">
                                    <div class="card-header bg-primary text-white">
                                        <h5 class="mb-0">
                                            <i class="fas fa-users"></i> ${group.name}
                                            <span class="badge bg-light text-dark ms-2">ID: ${group.id}</span>
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <p class="text-muted">${group.description || 'No description'}</p>
                                        <hr>
                                        <h6><i class="fas fa-key"></i> Assign Permissions</h6>
                                        <div class="permissions-list" style="max-height: 300px; overflow-y: auto;">
                        `;
                        
                        if (permResponse.permissions && permResponse.permissions.length > 0) {
                            permResponse.permissions.forEach(perm => {
                                const isChecked = groupPermIds.includes(perm.id);
                                html += `
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input group-perm-checkbox" 
                                               type="checkbox" 
                                               id="group_${group.id}_perm_${perm.id}"
                                               data-group-id="${group.id}"
                                               data-perm-id="${perm.id}"
                                               ${isChecked ? 'checked' : ''}
                                               onchange="updateGroupPermission(${group.id}, ${perm.id}, this.checked)">
                                        <label class="form-check-label" for="group_${group.id}_perm_${perm.id}">
                                            <strong>${perm.name}</strong>
                                            ${perm.description ? `<br><small class="text-muted">${perm.description}</small>` : ''}
                                        </label>
                                    </div>
                                `;
                            });
                        } else {
                            html += '<p class="text-muted">No permissions available</p>';
                        }
                        
                        html += `
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    html += '</div>';
                    $('#groupsList').html(html);
                },
                error: function(xhr) {
                    $('#groupsList').html('<div class="alert alert-danger">Error loading permissions</div>');
                }
            });
        },
        error: function(xhr) {
            $('#groupsList').html('<div class="alert alert-danger">Error loading groups</div>');
        }
    });
}

// Update group permission assignment
function updateGroupPermission(groupId, permissionId, isAssigned) {
    // Get current group permissions
    $.ajax({
        url: `../api/groups.php?id=${groupId}`,
        method: 'GET',
        headers: {
            'Authorization': 'Bearer ' + authToken
        },
        success: function(response) {
            let currentPerms = response.permissions ? response.permissions.map(p => p.id || p) : [];
            
            if (isAssigned) {
                // Add permission if not already present
                if (!currentPerms.includes(permissionId)) {
                    currentPerms.push(permissionId);
                }
            } else {
                // Remove permission
                currentPerms = currentPerms.filter(id => id != permissionId);
            }
            
            // Update group with new permissions
            $.ajax({
                url: `../api/groups.php?id=${groupId}`,
                method: 'PUT',
                headers: {
                    'Authorization': 'Bearer ' + authToken,
                    'Content-Type': 'application/json'
                },
                data: JSON.stringify({
                    permissions: currentPerms
                }),
                success: function() {
                    showSuccess(`Permission ${isAssigned ? 'assigned' : 'removed'} successfully`);
                },
                error: function(xhr) {
                    showError('Error updating group permissions: ' + (xhr.responseJSON ? xhr.responseJSON.error : 'Unknown error'));
                    // Revert checkbox
                    $(`#group_${groupId}_perm_${permissionId}`).prop('checked', !isAssigned);
                }
            });
        },
        error: function(xhr) {
            showError('Error loading group details');
            // Revert checkbox
            $(`#group_${groupId}_perm_${permissionId}`).prop('checked', !isAssigned);
        }
    });
}

// Load users
function loadUsers() {
    $.ajax({
        url: '../api/users.php',
        method: 'GET',
        headers: {
            'Authorization': 'Bearer ' + authToken
        },
        success: function(response) {
            let html = '';
            response.users.forEach(user => {
                html += `
                    <tr>
                        <td>${user.id}</td>
                        <td>${user.name}</td>
                        <td>${user.email}</td>
                        <td><span class="badge bg-primary">${user.role_name || 'None'}</span></td>
                        <td>
                            <button class="btn btn-sm btn-primary" onclick="editUser(${user.id})">
                                <i class="fas fa-edit"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });
            $('#usersTableBody').html(html);
        },
        error: function(xhr) {
            $('#usersTableBody').html('<tr><td colspan="5" class="text-center text-danger">Error loading users</td></tr>');
        }
    });
}

// Edit user (show modal) - Page 5
function editUser(userId) {
    $.ajax({
        url: `../api/users.php?id=${userId}`,
        method: 'GET',
        headers: {
            'Authorization': 'Bearer ' + authToken
        },
        success: function(userResponse) {
            const user = userResponse.user;
            // API returns roles and groups as arrays of names, permissions as objects with id
            const userRoleNames = userResponse.roles || [];
            const userGroupNames = userResponse.groups || [];
            const userPermIds = userResponse.permissions ? userResponse.permissions.map(p => typeof p === 'object' ? p.id : p) : [];
            
            // Load all roles, groups, and permissions
            $.when(
                $.ajax({
                    url: '../api/roles.php',
                    method: 'GET',
                    headers: { 'Authorization': 'Bearer ' + authToken }
                }),
                $.ajax({
                    url: '../api/groups.php',
                    method: 'GET',
                    headers: { 'Authorization': 'Bearer ' + authToken }
                }),
                $.ajax({
                    url: '../api/permissions.php',
                    method: 'GET',
                    headers: { 'Authorization': 'Bearer ' + authToken }
                })
            ).done(function(rolesResponse, groupsResponse, permissionsResponse) {
                const roles = rolesResponse[0].roles || [];
                const groups = groupsResponse[0].groups || [];
                const permissions = permissionsResponse[0].permissions || [];
                
                let html = `
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <h5><i class="fas fa-user"></i> ${user.name}</h5>
                            <p class="text-muted mb-0">${user.email}</p>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <h6><i class="fas fa-user-tag"></i> Roles</h6>
                            <div style="max-height: 300px; overflow-y: auto; border: 1px solid #dee2e6; padding: 10px; border-radius: 4px;">
                `;
                
                roles.forEach(role => {
                    const roleId = role.id;
                    const isChecked = userRoleNames.includes(role.name);
                    html += `
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input user-role-switch" 
                                   type="checkbox" 
                                   id="user_role_${roleId}"
                                   data-role-id="${roleId}"
                                   data-role-name="${role.name}"
                                   ${isChecked ? 'checked' : ''}
                                   onchange="updateUserAssignment(${userId}, 'role', ${roleId}, this.checked)">
                            <label class="form-check-label" for="user_role_${roleId}">
                                <strong>${role.name}</strong>
                                ${role.description ? `<br><small class="text-muted">${role.description}</small>` : ''}
                            </label>
                        </div>
                    `;
                });
                
                html += `
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <h6><i class="fas fa-users"></i> Groups</h6>
                            <div style="max-height: 300px; overflow-y: auto; border: 1px solid #dee2e6; padding: 10px; border-radius: 4px;">
                `;
                
                groups.forEach(group => {
                    const groupId = group.id;
                    const isChecked = userGroupNames.includes(group.name);
                    html += `
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input user-group-switch" 
                                   type="checkbox" 
                                   id="user_group_${groupId}"
                                   data-group-id="${groupId}"
                                   data-group-name="${group.name}"
                                   ${isChecked ? 'checked' : ''}
                                   onchange="updateUserAssignment(${userId}, 'group', ${groupId}, this.checked)">
                            <label class="form-check-label" for="user_group_${groupId}">
                                <strong>${group.name}</strong>
                                ${group.description ? `<br><small class="text-muted">${group.description}</small>` : ''}
                            </label>
                        </div>
                    `;
                });
                
                html += `
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <h6><i class="fas fa-key"></i> Permissions</h6>
                            <small class="text-muted">Direct permission assignments</small>
                            <div style="max-height: 300px; overflow-y: auto; border: 1px solid #dee2e6; padding: 10px; border-radius: 4px; margin-top: 5px;">
                `;
                
                permissions.forEach(perm => {
                    const permId = perm.id;
                    const isChecked = userPermIds.includes(permId) || userPermIds.includes(perm.name);
                    html += `
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input user-perm-switch" 
                                   type="checkbox" 
                                   id="user_perm_${permId}"
                                   data-perm-id="${permId}"
                                   ${isChecked ? 'checked' : ''}
                                   onchange="updateUserAssignment(${userId}, 'permission', ${permId}, this.checked)">
                            <label class="form-check-label" for="user_perm_${permId}">
                                <strong>${perm.name}</strong>
                                ${perm.description ? `<br><small class="text-muted">${perm.description}</small>` : ''}
                            </label>
                        </div>
                    `;
                });
                
                html += `
                            </div>
                        </div>
                    </div>
                `;
                
                $('#userEditContent').html(html);
                $('#userEditModal').modal('show');
                $('#userEditModal').data('userId', userId);
            });
        },
        error: function(xhr) {
            showError('Error loading user details');
        }
    });
}

// Update user assignment (role, group, or permission)
function updateUserAssignment(userId, type, itemId, isAssigned) {
    // Get current user data
    $.ajax({
        url: `../api/users.php?id=${userId}`,
        method: 'GET',
        headers: {
            'Authorization': 'Bearer ' + authToken
        },
        success: function(response) {
            // API returns roles and groups as arrays of names
            let roles = response.roles || [];
            let groups = response.groups || [];
            let permissions = response.permissions ? response.permissions.map(p => typeof p === 'object' ? p.id : p) : [];
            
            // Get role/group name from the switch element
            const switchElement = type === 'role' ? $(`#user_role_${itemId}`) : 
                                 type === 'group' ? $(`#user_group_${itemId}`) : null;
            
            if (type === 'role') {
                const roleName = switchElement.data('role-name');
                if (isAssigned) {
                    if (!roles.includes(roleName)) roles.push(roleName);
                } else {
                    roles = roles.filter(name => name !== roleName);
                }
            } else if (type === 'group') {
                const groupName = switchElement.data('group-name');
                if (isAssigned) {
                    if (!groups.includes(groupName)) groups.push(groupName);
                } else {
                    groups = groups.filter(name => name !== groupName);
                }
            } else if (type === 'permission') {
                if (isAssigned) {
                    if (!permissions.includes(itemId)) permissions.push(itemId);
                } else {
                    permissions = permissions.filter(id => id != itemId);
                }
            }
            
            // Update user via AJAX
            $.ajax({
                url: `../api/users.php?id=${userId}`,
                method: 'PUT',
                headers: {
                    'Authorization': 'Bearer ' + authToken,
                    'Content-Type': 'application/json'
                },
                data: JSON.stringify({
                    roles: roles,
                    groups: groups,
                    permissions: permissions
                }),
                success: function() {
                    showSuccess(`${type.charAt(0).toUpperCase() + type.slice(1)} ${isAssigned ? 'assigned' : 'removed'} successfully`);
                },
                error: function(xhr) {
                    showError('Error updating user: ' + (xhr.responseJSON ? xhr.responseJSON.error : 'Unknown error'));
                    // Revert switch
                    const switchId = type === 'role' ? `#user_role_${itemId}` : 
                                   type === 'group' ? `#user_group_${itemId}` : 
                                   `#user_perm_${itemId}`;
                    $(switchId).prop('checked', !isAssigned);
                }
            });
        },
        error: function(xhr) {
            showError('Error loading user details');
            // Revert switch
            const switchId = type === 'role' ? `#user_role_${itemId}` : 
                           type === 'group' ? `#user_group_${itemId}` : 
                           `#user_perm_${itemId}`;
            $(switchId).prop('checked', !isAssigned);
        }
    });
}

// Load direct permissions for user edit modal
function loadDirectPermissions(userId) {
    $.ajax({
        url: '../api/permissions.php',
        method: 'GET',
        headers: {
            'Authorization': 'Bearer ' + authToken
        },
        success: function(response) {
            let html = '';
            response.permissions.forEach(perm => {
                html += `
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="perm_${perm.id}" value="${perm.id}">
                        <label class="form-check-label" for="perm_${perm.id}">${perm.name}</label>
                    </div>
                `;
            });
            $('#directPermissions').html(html);
        }
    });
}

// Save original user changes
function saveOriginalUserChanges() {
    const userId = $('#userEditModal').data('userId');
    
    // Collect roles
    const roles = [];
    if ($('#role_admin').is(':checked')) roles.push('admin');
    if ($('#role_clinician').is(':checked')) roles.push('clinician');
    if ($('#role_nurse').is(':checked')) roles.push('nurse');
    if ($('#role_receptionist').is(':checked')) roles.push('receptionist');
    if ($('#role_patient').is(':checked')) roles.push('patient');
    
    // Collect groups
    const groups = [];
    if ($('#group_clinical').is(':checked')) groups.push('clinical_staff');
    if ($('#group_administrative').is(':checked')) groups.push('administrative_staff');
    if ($('#group_management').is(':checked')) groups.push('management');
    
    // Collect direct permissions
    const permissions = [];
    $('input[id^="perm_"]:checked').each(function() {
        permissions.push($(this).val());
    });
    
    const data = {
        roles: roles,
        groups: groups,
        permissions: permissions
    };
    
    $.ajax({
        url: `../api/users.php?id=${userId}`,
        method: 'PUT',
        headers: {
            'Authorization': 'Bearer ' + authToken,
            'Content-Type': 'application/json'
        },
        data: JSON.stringify(data),
        success: function(response) {
            $('#userEditModal').modal('hide');
            loadUsers(); // Refresh users table
            alert('User updated successfully');
        },
        error: function(xhr) {
            alert('Error updating user: ' + (xhr.responseJSON ? xhr.responseJSON.error : 'Unknown error'));
        }
    });
}

// Edit role (show modal)
// CRUD functions removed for evaluation mode - only viewing/listing is required
/*
function editRole(roleId) {
    // Load role details
    $.ajax({
        url: `../api/roles.php?id=${roleId}`,
        method: 'GET',
        headers: {
            'Authorization': 'Bearer ' + authToken
        },
        success: function(response) {
            const role = response;
            let html = `
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="roleName" class="form-label">Role Name</label>
                            <input type="text" class="form-control" id="roleName" value="${role.name}" ${role.name === 'admin' ? 'readonly' : ''}>
                            <small class="text-muted">${role.name === 'admin' ? 'Admin role cannot be renamed' : ''}</small>
                        </div>
                        <div class="mb-3">
                            <label for="roleDescription" class="form-label">Description</label>
                            <textarea class="form-control" id="roleDescription" rows="3">${role.description || ''}</textarea>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6>Permissions</h6>
                        <div id="rolePermissions">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="selectAllPerms" onchange="toggleAllPermissions()">
                                <label class="form-check-label" for="selectAllPerms">Select All</label>
                            </div>
                            <div id="permissionsList" style="max-height: 300px; overflow-y: auto;">
                                Loading permissions...
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            $('#userEditContent').html(html);
            $('#userEditModal').modal('show');
            $('#userEditModal .modal-title').text('Edit Role');
            
            // Store role ID for saving
            $('#userEditModal').data('roleId', roleId);
            $('#userEditModal').data('editType', 'role');
            
            // Load permissions for selection
            loadPermissionsForRole(role.permissions || []);
        },
        error: function(xhr) {
            alert('Error loading role details');
        }
    });
}

function editPermission(permId) {
    // Load permission details
    $.ajax({
        url: `../api/permissions.php?id=${permId}`,
        method: 'GET',
        headers: {
            'Authorization': 'Bearer ' + authToken
        },
        success: function(response) {
            const permission = response;
            let html = `
                <div class="row">
                    <div class="col-md-12">
                        <div class="mb-3">
                            <label for="permName" class="form-label">Permission Name</label>
                            <input type="text" class="form-control" id="permName" value="${permission.name}">
                            <small class="text-muted">Use format: resource:action (e.g., appointments:create)</small>
                        </div>
                        <div class="mb-3">
                            <label for="permDescription" class="form-label">Description</label>
                            <textarea class="form-control" id="permDescription" rows="3">${permission.description || ''}</textarea>
                        </div>
                        <div class="mb-3">
                            <h6>Currently assigned to:</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>Roles:</strong><br>
                                    ${permission.roles && permission.roles.length > 0 ? permission.roles.map(r => `<span class="badge bg-info me-1">${r}</span>`).join('') : 'None'}
                                </div>
                                <div class="col-md-6">
                                    <strong>Groups:</strong><br>
                                    ${permission.groups && permission.groups.length > 0 ? permission.groups.map(g => `<span class="badge bg-secondary me-1">${g}</span>`).join('') : 'None'}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            $('#userEditContent').html(html);
            $('#userEditModal').modal('show');
            $('#userEditModal .modal-title').text('Edit Permission');
            
            // Store permission ID for saving
            $('#userEditModal').data('permissionId', permId);
            $('#userEditModal').data('editType', 'permission');
        },
        error: function(xhr) {
            alert('Error loading permission details');
        }
    });
}

function editGroup(groupId) {
    // Load group details
    $.ajax({
        url: `../api/groups.php?id=${groupId}`,
        method: 'GET',
        headers: {
            'Authorization': 'Bearer ' + authToken
        },
        success: function(response) {
            const group = response;
            let html = `
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="groupName" class="form-label">Group Name</label>
                            <input type="text" class="form-control" id="groupName" value="${group.name}">
                        </div>
                        <div class="mb-3">
                            <label for="groupDescription" class="form-label">Description</label>
                            <textarea class="form-control" id="groupDescription" rows="3">${group.description || ''}</textarea>
                        </div>
                        <div class="mb-3">
                            <h6>Assigned Users:</h6>
                            ${group.users && group.users.length > 0 ? group.users.map(u => `<span class="badge bg-primary me-1">${u}</span>`).join('') : 'None'}
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6>Permissions</h6>
                        <div id="groupPermissions">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="selectAllGroupPerms" onchange="toggleAllGroupPermissions()">
                                <label class="form-check-label" for="selectAllGroupPerms">Select All</label>
                            </div>
                            <div id="groupPermissionsList" style="max-height: 300px; overflow-y: auto;">
                                Loading permissions...
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            $('#userEditContent').html(html);
            $('#userEditModal').modal('show');
            $('#userEditModal .modal-title').text('Edit Group');
            
            // Store group ID for saving
            $('#userEditModal').data('groupId', groupId);
            $('#userEditModal').data('editType', 'group');
            
            // Load permissions for selection
            loadPermissionsForGroup(group.permissions || []);
        },
        error: function(xhr) {
            alert('Error loading group details');
        }
    });
}

// Helper functions for role, permission, and group editing
function loadPermissionsForRole(selectedPermissions) {
    $.ajax({
        url: '../api/permissions.php',
        method: 'GET',
        headers: {
            'Authorization': 'Bearer ' + authToken
        },
        success: function(response) {
            let html = '';
            response.permissions.forEach(perm => {
                const isChecked = selectedPermissions.some(p => p.id == perm.id || p.name === perm.name);
                html += `
                    <div class="form-check">
                        <input class="form-check-input permission-checkbox" type="checkbox" 
                               id="perm_${perm.id}" value="${perm.id}" ${isChecked ? 'checked' : ''}>
                        <label class="form-check-label" for="perm_${perm.id}">
                            ${perm.name} 
                            <small class="text-muted">(${perm.description || 'No description'})</small>
                        </label>
                    </div>
                `;
            });
            $('#permissionsList').html(html);
        },
        error: function(xhr) {
            $('#permissionsList').html('<div class="text-danger">Error loading permissions</div>');
        }
    });
}

function loadPermissionsForGroup(selectedPermissions) {
    $.ajax({
        url: '../api/permissions.php',
        method: 'GET',
        headers: {
            'Authorization': 'Bearer ' + authToken
        },
        success: function(response) {
            let html = '';
            response.permissions.forEach(perm => {
                const isChecked = selectedPermissions.some(p => p.id == perm.id || p.name === perm.name);
                html += `
                    <div class="form-check">
                        <input class="form-check-input group-permission-checkbox" type="checkbox" 
                               id="group_perm_${perm.id}" value="${perm.id}" ${isChecked ? 'checked' : ''}>
                        <label class="form-check-label" for="group_perm_${perm.id}">
                            ${perm.name} 
                            <small class="text-muted">(${perm.description || 'No description'})</small>
                        </label>
                    </div>
                `;
            });
            $('#groupPermissionsList').html(html);
        },
        error: function(xhr) {
            $('#groupPermissionsList').html('<div class="text-danger">Error loading permissions</div>');
        }
    });
}

function toggleAllPermissions() {
    const selectAll = $('#selectAllPerms').is(':checked');
    $('.permission-checkbox').prop('checked', selectAll);
}

function toggleAllGroupPermissions() {
    const selectAll = $('#selectAllGroupPerms').is(':checked');
    $('.group-permission-checkbox').prop('checked', selectAll);
}

// Update save function to handle different edit types
function saveUserChanges() {
    // CRUD functions removed - only user assignment toggles are used
    // This function now only handles user changes (roles/permissions/groups assignment)
    const editType = $('#userEditModal').data('editType');
    
    if (!editType || editType === 'user') {
        // Original user saving logic (for user role/permission/group assignments)
        saveOriginalUserChanges();
    }
    // All CRUD operations (role/permission/group edit/create/delete) are disabled
}

function saveRoleChanges() {
    const roleId = $('#userEditModal').data('roleId');
    
    const name = $('#roleName').val();
    const description = $('#roleDescription').val();
    
    // Collect selected permissions
    const permissions = [];
    $('.permission-checkbox:checked').each(function() {
        permissions.push(parseInt($(this).val()));
    });
    
    const data = {
        name: name,
        description: description,
        permissions: permissions
    };
    
    $.ajax({
        url: `../api/roles.php?id=${roleId}`,
        method: 'PUT',
        headers: {
            'Authorization': 'Bearer ' + authToken,
            'Content-Type': 'application/json'
        },
        data: JSON.stringify(data),
        success: function(response) {
            $('#userEditModal').modal('hide');
            loadRoles(); // Refresh roles table
            showSuccess('Role updated successfully');
        },
        error: function(xhr) {
            showError('Error updating role: ' + (xhr.responseJSON ? xhr.responseJSON.error : 'Unknown error'));
        }
    });
}

function savePermissionChanges() {
    const permissionId = $('#userEditModal').data('permissionId');
    
    const name = $('#permName').val();
    const description = $('#permDescription').val();
    
    const data = {
        name: name,
        description: description
    };
    
    $.ajax({
        url: `../api/permissions.php?id=${permissionId}`,
        method: 'PUT',
        headers: {
            'Authorization': 'Bearer ' + authToken,
            'Content-Type': 'application/json'
        },
        data: JSON.stringify(data),
        success: function(response) {
            $('#userEditModal').modal('hide');
            loadPermissions(); // Refresh permissions table
            alert('Permission updated successfully');
        },
        error: function(xhr) {
            alert('Error updating permission: ' + (xhr.responseJSON ? xhr.responseJSON.error : 'Unknown error'));
        }
    });
}

function saveGroupChanges() {
    const groupId = $('#userEditModal').data('groupId');
    
    const name = $('#groupName').val();
    const description = $('#groupDescription').val();
    
    // Collect selected permissions
    const permissions = [];
    $('.group-permission-checkbox:checked').each(function() {
        permissions.push(parseInt($(this).val()));
    });
    
    const data = {
        name: name,
        description: description,
        permissions: permissions
    };
    
    $.ajax({
        url: `../api/groups.php?id=${groupId}`,
        method: 'PUT',
        headers: {
            'Authorization': 'Bearer ' + authToken,
            'Content-Type': 'application/json'
        },
        data: JSON.stringify(data),
        success: function(response) {
            $('#userEditModal').modal('hide');
            loadGroups(); // Refresh groups table
            alert('Group updated successfully');
        },
        error: function(xhr) {
            alert('Error updating group: ' + (xhr.responseJSON ? xhr.responseJSON.error : 'Unknown error'));
        }
    });
}

// Add new functions
function addNewRole() {
    let html = `
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="roleName" class="form-label">Role Name</label>
                    <input type="text" class="form-control" id="roleName" placeholder="Enter role name">
                    <small class="text-muted">Use lowercase, underscore separated (e.g., department_head)</small>
                </div>
                <div class="mb-3">
                    <label for="roleDescription" class="form-label">Description</label>
                    <textarea class="form-control" id="roleDescription" rows="3" placeholder="Describe this role's purpose"></textarea>
                </div>
            </div>
            <div class="col-md-6">
                <h6>Permissions</h6>
                <div id="rolePermissions">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="selectAllPerms" onchange="toggleAllPermissions()">
                        <label class="form-check-label" for="selectAllPerms">Select All</label>
                    </div>
                    <div id="permissionsList" style="max-height: 300px; overflow-y: auto;">
                        Loading permissions...
                    </div>
                </div>
            </div>
        </div>
    `;
    
    $('#userEditContent').html(html);
    $('#userEditModal').modal('show');
    $('#userEditModal .modal-title').text('Add New Role');
    
    // Store edit type
    $('#userEditModal').data('editType', 'newRole');
    
    // Load permissions for selection
    loadPermissionsForRole([]);
}

function addNewPermission() {
    let html = `
        <div class="row">
            <div class="col-md-12">
                <div class="mb-3">
                    <label for="permName" class="form-label">Permission Name</label>
                    <input type="text" class="form-control" id="permName" placeholder="e.g., appointments:create">
                    <small class="text-muted">Use format: resource:action (e.g., appointments:create, patients:read)</small>
                </div>
                <div class="mb-3">
                    <label for="permDescription" class="form-label">Description</label>
                    <textarea class="form-control" id="permDescription" rows="3" placeholder="Describe what this permission allows"></textarea>
                </div>
            </div>
        </div>
    `;
    
    $('#userEditContent').html(html);
    $('#userEditModal').modal('show');
    $('#userEditModal .modal-title').text('Add New Permission');
    
    // Store edit type
    $('#userEditModal').data('editType', 'newPermission');
}

function addNewGroup() {
    let html = `
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="groupName" class="form-label">Group Name</label>
                    <input type="text" class="form-control" id="groupName" placeholder="Enter group name">
                    <small class="text-muted">Use lowercase, underscore separated (e.g., clinical_staff)</small>
                </div>
                <div class="mb-3">
                    <label for="groupDescription" class="form-label">Description</label>
                    <textarea class="form-control" id="groupDescription" rows="3" placeholder="Describe this group's purpose"></textarea>
                </div>
            </div>
            <div class="col-md-6">
                <h6>Permissions</h6>
                <div id="groupPermissions">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="selectAllGroupPerms" onchange="toggleAllGroupPermissions()">
                        <label class="form-check-label" for="selectAllGroupPerms">Select All</label>
                    </div>
                    <div id="groupPermissionsList" style="max-height: 300px; overflow-y: auto;">
                        Loading permissions...
                    </div>
                </div>
            </div>
        </div>
    `;
    
    $('#userEditContent').html(html);
    $('#userEditModal').modal('show');
    $('#userEditModal .modal-title').text('Add New Group');
    
    // Store edit type
    $('#userEditModal').data('editType', 'newGroup');
    
    // Load permissions for selection
    loadPermissionsForGroup([]);
}

// Save functions for new items
function saveNewRole() {
    const name = $('#roleName').val();
    const description = $('#roleDescription').val();
    
    if (!name) {
        alert('Role name is required');
        return;
    }
    
    // Collect selected permissions
    const permissions = [];
    $('.permission-checkbox:checked').each(function() {
        permissions.push(parseInt($(this).val()));
    });
    
    const data = {
        name: name,
        description: description,
        permissions: permissions
    };
    
    $.ajax({
        url: '../api/roles.php',
        method: 'POST',
        headers: {
            'Authorization': 'Bearer ' + authToken,
            'Content-Type': 'application/json'
        },
        data: JSON.stringify(data),
        success: function(response) {
            $('#userEditModal').modal('hide');
            loadRoles(); // Refresh roles table
            alert('Role created successfully');
        },
        error: function(xhr) {
            alert('Error creating role: ' + (xhr.responseJSON ? xhr.responseJSON.error : 'Unknown error'));
        }
    });
}

function saveNewPermission() {
    const name = $('#permName').val();
    const description = $('#permDescription').val();
    
    if (!name) {
        alert('Permission name is required');
        return;
    }
    
    const data = {
        name: name,
        description: description
    };
    
    $.ajax({
        url: '../api/permissions.php',
        method: 'POST',
        headers: {
            'Authorization': 'Bearer ' + authToken,
            'Content-Type': 'application/json'
        },
        data: JSON.stringify(data),
        success: function(response) {
            $('#userEditModal').modal('hide');
            loadPermissions(); // Refresh permissions table
            alert('Permission created successfully');
        },
        error: function(xhr) {
            alert('Error creating permission: ' + (xhr.responseJSON ? xhr.responseJSON.error : 'Unknown error'));
        }
    });
}

function saveNewGroup() {
    const name = $('#groupName').val();
    const description = $('#groupDescription').val();
    
    if (!name) {
        alert('Group name is required');
        return;
    }
    
    // Collect selected permissions
    const permissions = [];
    $('.group-permission-checkbox:checked').each(function() {
        permissions.push(parseInt($(this).val()));
    });
    
    const data = {
        name: name,
        description: description,
        permissions: permissions
    };
    
    $.ajax({
        url: '../api/groups.php',
        method: 'POST',
        headers: {
            'Authorization': 'Bearer ' + authToken,
            'Content-Type': 'application/json'
        },
        data: JSON.stringify(data),
        success: function(response) {
            $('#userEditModal').modal('hide');
            loadGroups(); // Refresh groups table
            alert('Group created successfully');
        },
        error: function(xhr) {
            alert('Error creating group: ' + (xhr.responseJSON ? xhr.responseJSON.error : 'Unknown error'));
        }
    });
}

// Delete functions
function deleteRole(roleId) {
    if (confirm('Are you sure you want to delete this role? This action cannot be undone.')) {
        $.ajax({
            url: `../api/roles.php?id=${roleId}`,
            method: 'DELETE',
            headers: {
                'Authorization': 'Bearer ' + authToken
            },
            success: function(response) {
                loadRoles(); // Refresh roles table
                alert('Role deleted successfully');
            },
            error: function(xhr) {
                alert('Error deleting role: ' + (xhr.responseJSON ? xhr.responseJSON.error : 'Unknown error'));
            }
        });
    }
}

function deletePermission(permissionId) {
    if (confirm('Are you sure you want to delete this permission? This action cannot be undone.')) {
        $.ajax({
            url: `../api/permissions.php?id=${permissionId}`,
            method: 'DELETE',
            headers: {
                'Authorization': 'Bearer ' + authToken
            },
            success: function(response) {
                loadPermissions(); // Refresh permissions table
                alert('Permission deleted successfully');
            },
            error: function(xhr) {
                alert('Error deleting permission: ' + (xhr.responseJSON ? xhr.responseJSON.error : 'Unknown error'));
            }
        });
    }
}

function deleteGroup(groupId) {
    if (confirm('Are you sure you want to delete this group? This action cannot be undone.')) {
        $.ajax({
            url: `../api/groups.php?id=${groupId}`,
            method: 'DELETE',
            headers: {
                'Authorization': 'Bearer ' + authToken
            },
            success: function(response) {
                loadGroups(); // Refresh groups table
                alert('Group deleted successfully');
            },
            error: function(xhr) {
                alert('Error deleting group: ' + (xhr.responseJSON ? xhr.responseJSON.error : 'Unknown error'));
            }
        });
    }
}
*/

// Error handling functions
function showAuthError(message) {
    // Show authentication error toast/alert
    const errorHtml = `
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>Session Expired:</strong> ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    // Insert at top of login page
    $('#loginPage .card-body').prepend(errorHtml);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        $('.alert').alert('close');
    }, 5000);
}

function showPermissionError(message) {
    // Show permission error
    const errorHtml = `
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-ban"></i>
            <strong>Access Denied:</strong> ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    // Insert at top of current page
    $('.main-content').prepend(errorHtml);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        $('.alert').alert('close');
    }, 5000);
}

function showError(message) {
    // Show general error
    const errorHtml = `
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle"></i>
            <strong>Error:</strong> ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    // Insert at top of current page
    $('.main-content').prepend(errorHtml);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        $('.alert').alert('close');
    }, 5000);
}

function showSuccess(message) {
    // Show success message
    const successHtml = `
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i>
            <strong>Success:</strong> ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    // Insert at top of current page
    $('.main-content').prepend(successHtml);
    
    // Auto-remove after 3 seconds
    setTimeout(() => {
        $('.alert').alert('close');
    }, 3000);
}
