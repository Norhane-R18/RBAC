<!DOCTYPE html>
<html>
<head>
    <title>Test 401 Response</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .response { background: #f5f5f5; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .error { background: #ffebee; color: #c62828; }
        .success { background: #e8f5e8; color: #2e7d32; }
        pre { white-space: pre-wrap; }
    </style>
</head>
<body>
    <h1>🔍 Test 401 Responses</h1>
    
    <button onclick="testNoToken()">Test No Token</button>
    <button onclick="testInvalidToken()">Test Invalid Token</button>
    <button onclick="testValidToken()">Test Valid Token</button>
    
    <div id="results"></div>
    
    <script>
        function testNoToken() {
            fetch('http://localhost:8000/api/appointments.php')
                .then(response => {
                    const result = document.getElementById('results');
                    result.innerHTML = `
                        <div class="response error">
                            <h3>❌ No Token - Status: ${response.status}</h3>
                            <p><strong>Response:</strong></p>
                            <pre>${response.status} ${response.statusText}</pre>
                        </div>
                    `;
                })
                .catch(error => {
                    document.getElementById('results').innerHTML = `
                        <div class="response error">
                            <h3>❌ Network Error</h3>
                            <pre>${error.message}</pre>
                        </div>
                    `;
                });
        }
        
        function testInvalidToken() {
            fetch('http://localhost:8000/api/appointments.php', {
                headers: {
                    'Authorization': 'Bearer invalid-token-12345'
                }
            })
                .then(response => response.json())
                .then(data => {
                    const result = document.getElementById('results');
                    result.innerHTML = `
                        <div class="response error">
                            <h3>❌ Invalid Token Response</h3>
                            <pre>${JSON.stringify(data, null, 2)}</pre>
                        </div>
                    `;
                })
                .catch(error => {
                    document.getElementById('results').innerHTML = `
                        <div class="response error">
                            <h3>❌ Error</h3>
                            <pre>${error.message}</pre>
                        </div>
                    `;
                });
        }
        
        function testValidToken() {
            // You need to replace this with a real token from login
            const token = prompt('Enter a valid token from login:');
            if (!token) return;
            
            fetch('http://localhost:8000/api/appointments.php', {
                headers: {
                    'Authorization': 'Bearer ' + token
                }
            })
                .then(response => response.json())
                .then(data => {
                    const result = document.getElementById('results');
                    result.innerHTML = `
                        <div class="response success">
                            <h3>✅ Valid Token Response</h3>
                            <pre>${JSON.stringify(data, null, 2)}</pre>
                        </div>
                    `;
                })
                .catch(error => {
                    document.getElementById('results').innerHTML = `
                        <div class="response error">
                            <h3>❌ Error</h3>
                            <pre>${error.message}</pre>
                        </div>
                    `;
                });
        }
    </script>
</body>
</html>
