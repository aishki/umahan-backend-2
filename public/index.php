<?php

require __DIR__ . '/vendor/autoload.php';

use Slim\Factory\AppFactory;
use App\Controllers\UserController;
use App\Middleware\AuthMiddleware;
use Dotenv\Dotenv;

if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->load();
}

$app = AppFactory::create();
$app->addBodyParsingMiddleware();

// Instantiate UserController without requiring TursoClient for user handling
$userController = new UserController();

// Define routes
$app->post('/register', [$userController, 'register']);
$app->post('/login', [$userController, 'login']);
// Profile routes still use AuthMiddleware (modify as needed)
$app->get('/profile', [$userController, 'getProfile'])->add(new AuthMiddleware());
$app->post('/profile', [$userController, 'updateProfile'])->add(new AuthMiddleware());

// Run the app
$app->run();
