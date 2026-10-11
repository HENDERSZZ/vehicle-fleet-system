<?php
session_start();
?>

<!DOCTYPE html>
<html>
<head>
  <title>Unauthorized</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container text-center mt-5">
  <h1 class="text-danger">Unauthorized Access</h1>
  <p>You do not have permission to view this page.</p>
  <a href="/vehicle/admin.php" class="btn btn-primary mt-3">Return to Dashboard</a>
</div>
</body>
</html>
