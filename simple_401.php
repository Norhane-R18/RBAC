<!DOCTYPE html>
<html>
<head>
    <title>401 Test</title>
    <style>
        body { font-family: Arial; margin: 20px; }
        .test { background: #f5f5f5; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .error { background: #ffebee; }
        pre { background: #fff; padding: 10px; border-radius: 3px; }
    </style>
</head>
<body>
    <h1>🔍 401 Response Test</h1>
    
    <div class="test">
        <h3>❌ Test 1: No Token</h3>
        <button onclick="testNoToken()">Click to Test</button>
        <div id="result1"></div>
    </div>
    
    <div class="test">
        <h3>❌ Test 2: Invalid Token</h3>
        <button onclick="testInvalidToken()">Click to Test</button>
        <div id="result2"></div>
    </div>
    
    <script>
        function testNoToken() {
            fetch('http://localhost:8000/api/appointments.php')
                .then(response => {
                    document.getElementById('result1').innerHTML = `
                        <strong>Status: ${response.status} ${response.statusText}</strong><br>
                        <pre>${response.status === 401 ? '401 Unauthorized - No token provided' : 'Unexpected response'}</pre>
                    `;
                })
                .catch(error => {
                    document.getElementById('result1').innerHTML = `<pre>Error: ${error.message}</pre>`;
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
                    document.getElementById('result2').innerHTML = `
                        <strong>Status: 401 Unauthorized</strong><br>
                        <pre>${JSON.stringify(data, null, 2)}</pre>
                    `;
                })
                .catch(error => {
                    document.getElementById('result2').innerHTML = `<pre>Error: ${error.message}</pre>`;
                });
        }
    </script>
    
    <hr>
    <p><a href="/dashboard/">← Back to Dashboard</a></p>
</body>
</html>
