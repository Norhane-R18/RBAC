<!DOCTYPE html>
<html>
<head>
    <title>401 vs 403 Test</title>
    <style>
        body { font-family: Arial; margin: 20px; }
        .test { background: #f5f5f5; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .error { background: #ffebee; }
        .forbidden { background: #fff3e0; }
        .success { background: #e8f5e8; }
        pre { background: #fff; padding: 10px; border-radius: 3px; }
        button { padding: 8px 16px; margin: 5px; }
    </style>
</head>
<body>
    <h1>🔍 Test 401 vs 403 Responses</h1>
    
    <div class="test">
        <h3>❌ 401 Unauthorized Tests</h3>
        <button onclick="test401NoToken()">No Token</button>
        <button onclick="test401InvalidToken()">Invalid Token</button>
        <div id="result401"></div>
    </div>
    
    <div class="test">
        <h3>🚫 403 Forbidden Tests</h3>
        <button onclick="test403Patient()">Patient trying Admin</button>
        <button onclick="test403Clinician()">Clinician trying Roles</button>
        <div id="result403"></div>
    </div>
    
    <div class="test">
        <h3>✅ Success Tests</h3>
        <button onclick="testSuccessAdmin()">Admin Access</button>
        <button onclick="testSuccessPatient()">Patient Own Data</button>
        <div id="resultSuccess"></div>
    </div>
    
    <script>
        // 401 Tests
        function test401NoToken() {
            fetch('http://localhost:8000/api/appointments.php')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('result401').innerHTML = `
                        <div class="error">
                            <strong>❌ 401 - No Token</strong><br>
                            <pre>${JSON.stringify(data, null, 2)}</pre>
                        </div>
                    `;
                })
                .catch(error => {
                    document.getElementById('result401').innerHTML = `
                        <div class="error">
                            <strong>Error: ${error.message}</strong>
                        </div>
                    `;
                });
        }
        
        function test401InvalidToken() {
            fetch('http://localhost:8000/api/appointments.php', {
                headers: { 'Authorization': 'Bearer invalid-token-12345' }
            })
                .then(response => response.json())
                .then(data => {
                    document.getElementById('result401').innerHTML = `
                        <div class="error">
                            <strong>❌ 401 - Invalid Token</strong><br>
                            <pre>${JSON.stringify(data, null, 2)}</pre>
                        </div>
                    `;
                })
                .catch(error => {
                    document.getElementById('result401').innerHTML = `
                        <div class="error">
                            <strong>Error: ${error.message}</strong>
                        </div>
                    `;
                });
        }
        
        // 403 Tests
        function test403Patient() {
            // First login as patient, then try admin endpoint
            fetch('http://localhost:8000/api/login.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    email: 'jpatient@email.com',
                    password: 'password123'
                })
            })
                .then(response => response.json())
                .then(loginData => {
                    // Now try to access admin-only endpoint
                    return fetch('http://localhost:8000/api/roles.php', {
                        headers: { 'Authorization': 'Bearer ' + loginData.token }
                    });
                })
                .then(response => response.json())
                .then(data => {
                    document.getElementById('result403').innerHTML = `
                        <div class="forbidden">
                            <strong>🚫 403 - Patient trying Admin</strong><br>
                            <pre>${JSON.stringify(data, null, 2)}</pre>
                        </div>
                    `;
                })
                .catch(error => {
                    document.getElementById('result403').innerHTML = `
                        <div class="forbidden">
                            <strong>Error: ${error.message}</strong>
                        </div>
                    `;
                });
        }
        
        function test403Clinician() {
            // Login as clinician, then try roles endpoint
            fetch('http://localhost:8000/api/login.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    email: 'drsmith@hospital.com',
                    password: 'password123'
                })
            })
                .then(response => response.json())
                .then(loginData => {
                    // Try to access admin-only roles endpoint
                    return fetch('http://localhost:8000/api/roles.php', {
                        headers: { 'Authorization': 'Bearer ' + loginData.token }
                    });
                })
                .then(response => response.json())
                .then(data => {
                    document.getElementById('result403').innerHTML = `
                        <div class="forbidden">
                            <strong>🚫 403 - Clinician trying Roles Management</strong><br>
                            <pre>${JSON.stringify(data, null, 2)}</pre>
                        </div>
                    `;
                })
                .catch(error => {
                    document.getElementById('result403').innerHTML = `
                        <div class="forbidden">
                            <strong>Error: ${error.message}</strong>
                        </div>
                    `;
                });
        }
        
        // Success Tests
        function testSuccessAdmin() {
            fetch('http://localhost:8000/api/login.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    email: 'admin@hospital.com',
                    password: 'password123'
                })
            })
                .then(response => response.json())
                .then(loginData => {
                    // Admin should access roles successfully
                    return fetch('http://localhost:8000/api/roles.php', {
                        headers: { 'Authorization': 'Bearer ' + loginData.token }
                    });
                })
                .then(response => response.json())
                .then(data => {
                    document.getElementById('resultSuccess').innerHTML = `
                        <div class="success">
                            <strong>✅ Admin Success - Roles Access</strong><br>
                            <pre>${JSON.stringify(data, null, 2)}</pre>
                        </div>
                    `;
                })
                .catch(error => {
                    document.getElementById('resultSuccess').innerHTML = `
                        <div class="success">
                            <strong>Error: ${error.message}</strong>
                        </div>
                    `;
                });
        }
        
        function testSuccessPatient() {
            fetch('http://localhost:8000/api/login.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    email: 'jpatient@email.com',
                    password: 'password123'
                })
            })
                .then(response => response.json())
                .then(loginData => {
                    // Patient should access their own data
                    return fetch('http://localhost:8000/api/patients.php?id=1', {
                        headers: { 'Authorization': 'Bearer ' + loginData.token }
                    });
                })
                .then(response => response.json())
                .then(data => {
                    document.getElementById('resultSuccess').innerHTML = `
                        <div class="success">
                            <strong>✅ Patient Success - Own Data Access</strong><br>
                            <pre>${JSON.stringify(data, null, 2)}</pre>
                        </div>
                    `;
                })
                .catch(error => {
                    document.getElementById('resultSuccess').innerHTML = `
                        <div class="success">
                            <strong>Error: ${error.message}</strong>
                        </div>
                    `;
                });
        }
    </script>
    
    <hr>
    <h3>📋 Expected Results:</h3>
    <ul>
        <li><strong>401 Unauthorized</strong>: No token or invalid token</li>
        <li><strong>403 Forbidden</strong>: Valid token but wrong permissions</li>
        <li><strong>200 OK</strong>: Valid token and correct permissions</li>
    </ul>
    
    <p><a href="/dashboard/">← Back to Dashboard</a></p>
</body>
</html>
