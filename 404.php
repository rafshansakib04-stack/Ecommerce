<?php http_response_code(404); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 Not Found</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .container { max-width: 600px; margin-top: 10vh; }
    </style>
    </head>
<body>
    <div class="container text-center">
        <h1 class="display-4 text-warning">404</h1>
        <p class="lead">The page you're looking for doesn't exist.</p>
        <a href="/index.php" class="btn btn-primary mt-3">Go Home</a>
    </div>
</body>
</html>
