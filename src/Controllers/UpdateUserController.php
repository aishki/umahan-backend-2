<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\TursoClient;
use Dotenv\Dotenv;


use Exception;

class UpdateUserController
{
    private $tursoClient;

    public function __construct()
    {
        // Load environment variables
        $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__);
        $dotenv->load();

        // Validate database environment variables
        $databaseUrl = $_ENV['TURSO_DB_URL'] ?? '';
        $authToken = $_ENV['TURSO_AUTH_TOKEN'] ?? '';
        if (empty($databaseUrl) || empty($authToken)) {
            throw new Exception('Database URL or Auth Token is not set in the environment variables.');
        }

        // Initialize Turso client
        $this->tursoClient = new \TursoClient($databaseUrl, $authToken);
    }

    public function updateUser()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendJsonResponse(['success' => false, 'message' => 'Invalid request method.'], 405);
            return;
        }

        // Get data from POST request
        $email = $_POST['email'] ?? null;
        $userType = $_POST['userType'] ?? null;
        $businessName = $_POST['businessName'] ?? null;
        $businessType = $_POST['businessType'] ?? null;
        $businessAddress = $_POST['businessAddress'] ?? null;
        $farmName = $_POST['farmName'] ?? null;
        $biography = $_POST['biography'] ?? null;
        $farmLocation = $_POST['farmLocation'] ?? null;

        // Validate required fields
        if (empty($email) || empty($userType)) {
            $this->sendJsonResponse(['success' => false, 'message' => 'Email and user type are required.'], 400);
            return;
        }

        try {
            // Set up the database connection
            $this->tursoClient->connect();

            // Update the user type in the Users table
            $updateUserQuery = "UPDATE Users SET userType = ? WHERE email = ?";
            $this->tursoClient->execute($updateUserQuery, $userType, $email);

            // Insert corresponding data based on user type
            if ($userType === 'Business') {
                $this->handleBusinessUpdate($email, $businessName, $businessType, $businessAddress);
            } elseif ($userType === 'Farmer') {
                $this->handleFarmerUpdate($email, $farmName, $biography, $farmLocation);
            } else {
                $this->sendJsonResponse(['success' => false, 'message' => 'Invalid user type provided.'], 400);
                return;
            }

            // Close the database connection
            $this->tursoClient->close();

            $this->sendJsonResponse(['success' => true, 'message' => 'User details updated successfully.']);
        } catch (Exception $e) {
            // Error handling
            $this->sendJsonResponse(['success' => false, 'message' => 'Error updating user details: ' . $e->getMessage()], 500);
        }
    }

    private function handleBusinessUpdate($email, $businessName, $businessType, $businessAddress)
    {
        $insertBusinessQuery = "INSERT INTO Businesses (email, business_name, business_type, business_address) VALUES (?, ?, ?, ?)";
        $this->tursoClient->execute($insertBusinessQuery, $email, $businessName, $businessType, $businessAddress);
    }

    private function handleFarmerUpdate($email, $farmName, $biography, $farmLocation)
    {
        $insertFarmerQuery = "INSERT INTO Farmers (email, farm_name, biography, farm_location) VALUES (?, ?, ?, ?)";
        $this->tursoClient->execute($insertFarmerQuery, $email, $farmName, $biography, $farmLocation);
    }

    private function sendJsonResponse($data, $statusCode = 200)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}
