@echo off
echo Testing endpoints without authentication token (should return 401)
echo.

echo Testing user_basic endpoint:
curl -X GET "http://localhost/RBAC/api/demo_endpoints.php?endpoint=user_basic" -H "Content-Type: application/json" -w "\nHTTP Status: %{http_code}\n" -s
echo.

echo Testing admin_only endpoint:
curl -X GET "http://localhost/RBAC/api/demo_endpoints.php?endpoint=admin_only" -H "Content-Type: application/json" -w "\nHTTP Status: %{http_code}\n" -s
echo.

echo Testing clinician_only endpoint:
curl -X GET "http://localhost/RBAC/api/demo_endpoints.php?endpoint=clinician_only" -H "Content-Type: application/json" -w "\nHTTP Status: %{http_code}\n" -s
echo.

echo Testing public endpoint (should work without auth):
curl -X GET "http://localhost/RBAC/api/demo_endpoints.php?endpoint=public" -H "Content-Type: application/json" -w "\nHTTP Status: %{http_code}\n" -s
echo.

pause