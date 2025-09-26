<?php http_response_code(403); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 Forbidden</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .container { max-width: 600px; margin-top: 10vh; }
    </style>
    </head>
<body>
    <div class="container text-center">
        <h1 class="display-4 text-danger">403</h1>
        <p class="lead">You don't have permission to access this resource.</p>
        <a href="/index.php" class="btn btn-primary mt-3">Go Home</a>
    </div>
</body>
</html>
