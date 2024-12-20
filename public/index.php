<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use App\Controllers\UserController;
use App\Controllers\UpdateUserController;
use App\Middleware\AuthMiddleware;
use App\TursoClient;
use Dotenv\Dotenv;

// Load environment variables
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->load();
}

// Validate database environment variables
$databaseUrl = $_ENV['TURSO_DB_URL'] ?? '';
$authToken = $_ENV['TURSO_AUTH_TOKEN'] ?? '';
if (empty($databaseUrl) || empty($authToken)) {
    throw new \Exception('Database URL or Auth Token is not set in the environment variables.');
}

// Initialize Turso client
$tursoClient = new TursoClient($databaseUrl, $authToken);

// Create Slim app
$app = AppFactory::create();
$app->addRoutingMiddleware();
$app->addBodyParsingMiddleware();

// Controllers
$userController = new UserController(); // Assuming no dependencies
$updateUserController = new UpdateUserController($tursoClient); // Pass the TursoClient dependency

// Routes
$app->group('/user', function ($group) use ($userController, $updateUserController) {
    $group->post('/register', [$userController, 'register']);
    $group->post('/login', [$userController, 'login']);
    $group->post('/refresh-token', [$userController, 'refreshToken']); // New route for token refresh
    $group->get('/profile', [$userController, 'getProfile'])->add(new AuthMiddleware());
    $group->post('/profile', [$userController, 'updateProfile'])->add(new AuthMiddleware());
    $group->post('/check-email', [$userController, 'emailCheck'])->add(new AuthMiddleware());
    $group->post('/update', [$updateUserController, 'updateUser'])->add(new AuthMiddleware());
});

// Add error middleware for debugging
$app->addErrorMiddleware(true, true, true);

// Test route for debugging purposes
$app->get('/test-user', function ($request, $response) use ($tursoClient) {
    $user = $tursoClient->executeQuery("SELECT * FROM Users WHERE email = 'arieru.dev@gmail.com'");

    $response->getBody()->write(json_encode($user));
    return $response->withHeader('Content-Type', 'application/json');
});

// Run the app
$app->run();
