<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use App\TursoClient;
use App\Controllers\UpdateUserController;
use App\Controllers\UserController;
use App\Middleware\AuthMiddleware;
use Dotenv\Dotenv;



if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->load();
}


$databaseUrl = $_ENV['TURSO_DB_URL'];
$authToken = $_ENV['TURSO_AUTH_TOKEN'];
$tursoClient = new TursoClient($databaseUrl, $authToken);


$app = AppFactory::create();
$app->addBodyParsingMiddleware();


$userController = new UserController($tursoClient);
$updateUserController = new UpdateUserController($tursoClient);


$app->post('/register', [$userController, 'register']);
$app->post('/login', [$userController, 'login']);
$app->post('/refresh-token', [$userController, 'refreshToken']); // New route for token refresh
$app->get('/profile', [$userController, 'getProfile'])->add(new AuthMiddleware());
$app->post('/profile', [$userController, 'updateProfile'])->add(new AuthMiddleware());
$app->post('/check-email', [$userController, 'emailCheck'])->add(new AuthMiddleware());
$app->post('/update', [$updateUserController, 'updateUser'])->add(new AuthMiddleware());



$app->get('/test-user', function ($request, $response) use ($tursoClient) {
    $user = $tursoClient->executeQuery("SELECT * FROM users WHERE username = 'leeyam20'");

    $response->getBody()->write(json_encode($user));
    return $response->withHeader('Content-Type', 'application/json');
});


$app->run();