<?php

require '/app/vendor/autoload.php';

use Slim\Factory\AppFactory;
use App\Controllers\UserController;
use App\Middleware\AuthMiddleware;
use Dotenv\Dotenv;

if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->load();
}

$app = AppFactory::create();
$app->addRoutingMiddleware(); 
$app->addBodyParsingMiddleware();

$userController = new UserController();

$app->post('/register', [$userController, 'register']);
$app->post('/login', [$userController, 'login']);
$app->get('/profile', [$userController, 'getProfile'])->add(new AuthMiddleware());
$app->post('/profile', [$userController, 'updateProfile'])->add(new AuthMiddleware());

$app->addErrorMiddleware(true, true, true);

$app->run();
