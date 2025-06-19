curl -X POST http://localhost:8080/api/discador-control.php \
  -H "Content-Type: application/json" \
  -d '{"action": "control", "command": "status"}' \
  --cookie "PHPSESSID=test123" \
  -v
