<!DOCTYPE html>
<html>
<head>
    <title>Easy 401/403 Test</title>
    <style>
        body { font-family: Arial; margin: 20px; }
        .result { background: #f5f5f5; padding: 10px; margin: 10px 0; border-radius: 5px; }
        .error { background: #ffebee; color: #c62828; }
        .forbidden { background: #fff3e0; color: #f57c00; }
        pre { background: white; padding: 8px; }
    </style>
</head>
<body>
    <h1>🔍 Easy 401/403 Test</h1>
    
    <h2>❌ 401 Test - No Token</h2>
    <button onclick="test401()">Test 401</button>
    <div id="result401" class="result"></div>
    
    <h2>🚫 403 Test - Wrong Role</h2>
    <button onclick="test403()">Test 403</button>
    <div id="result403" class="result"></div>
    
    <script>
        function test401() {
            fetch('http://localhost:8000/api/appointments.php')
                .then(response => {
                    const result = document.getElementById('result401');
                    result.className = 'result error';
                    result.innerHTML = `
                        <strong>Status: ${response.status} ${response.statusText}</strong><br>
                        <strong>This is a 401 Unauthorized response!</strong><br>
                        <small>No authentication token provided</small>
                    `;
                })
                .catch(error => {
                    document.getElementById('result401').innerHTML = `Error: ${error.message}`;
                });
        }
        
        function test403() {
            // Login as patient first
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
                    // Now try to access admin endpoint
                    return fetch('http://localhost:8000/api/roles.php', {
                        headers: { 'Authorization': 'Bearer ' + loginData.token }
                    });
                })
                .then(response => response.json())
                .then(data => {
                    const result = document.getElementById('result403');
                    result.className = 'result forbidden';
                    result.innerHTML = `
                        <strong>Status: 403 Forbidden</strong><br>
                        <strong>This is a 403 response!</strong><br>
                        <pre>${JSON.stringify(data, null, 2)}</pre>
                        <small>Patient trying to access admin-only roles endpoint</small>
                    `;
                })
                .catch(error => {
                    document.getElementById('result403').innerHTML = `Error: ${error.message}`;
                });
        }
    </script>
    
    <hr>
    <p><strong>What you'll see:</strong></p>
    <ul>
        <li><strong>401</strong>: No token = Unauthorized</li>
        <li><strong>403</strong>: Valid token but wrong role = Forbidden</li>
    </ul>
    
    <p><a href="/dashboard/">← Back to Dashboard</a></p>
</body>
</html>
