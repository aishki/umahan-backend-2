
<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use App\Controllers\ProductController;
use App\Controllers\UserController;
use App\Controllers\UserExtraController;
use App\Controllers\CartController;
use App\Controllers\OrderController;
use App\Middleware\AuthMiddleware;
use App\TursoClient;
use App\Repository\UserExtraRepository;
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
$userController = new UserController();

$app->group('/user', function ($group) use ($userController) {
    $group->post('/register', [$userController, 'register']);
    $group->post('/login', [$userController, 'login']);
    $group->post('/refresh-token', [$userController, 'refreshToken']); // New route for token refresh
    $group->get('/profile', [$userController, 'getProfile'])->add(new AuthMiddleware());
    $group->post('/profile', [$userController, 'updateProfile'])->add(new AuthMiddleware());
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
