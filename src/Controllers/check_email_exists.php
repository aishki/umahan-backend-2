<?php

require_once 'vendor/autoload.php'; // If using composer
require_once 'TursoClient.php'; // Include your TursoClient class

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Validate database environment variables
$databaseUrl = $_ENV['TURSO_DB_URL'] ?? '';
$authToken = $_ENV['TURSO_AUTH_TOKEN'] ?? '';
if (empty($databaseUrl) || empty($authToken)) {
    throw new \Exception('Database URL or Auth Token is not set in the environment variables.');
}

// Initialize the Turso client
$tursoClient = new App\TursoClient($databaseUrl, $authToken);

// Get the email parameter from the request
$email = isset($_GET['email']) ? $_GET['email'] : null;

if (empty($email)) {
    echo json_encode([
        'success' => false,
        'message' => 'Email parameter is required'
    ]);
    exit;
}

// Sanitize the email to prevent SQL injection
$email = filter_var($email, FILTER_SANITIZE_EMAIL);

// Validate the email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid email format'
    ]);
    exit;
}

// Define the SQL query to check if the email exists
$sql = "SELECT * FROM Users WHERE email = ?";

// Execute the query
$response = $tursoClient->executeQuery($sql, [$email]);

// Check if the email exists
if (isset($response['results']) && count($response['results']) > 0) {
    echo json_encode([
        'success' => true,
        'message' => 'Email exists'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Email not found'
    ]);
}

?>
