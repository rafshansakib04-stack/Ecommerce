<?php http_response_code(500); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 Server Error</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .container { max-width: 600px; margin-top: 10vh; }
    </style>
    </head>
<body>
    <div class="container text-center">
        <h1 class="display-4 text-danger">500</h1>
        <p class="lead">Something went wrong on our end. Please try again later.</p>
        <a href="/index.php" class="btn btn-primary mt-3">Go Home</a>
    </div>
</body>
</html>
