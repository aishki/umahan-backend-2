<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\TursoClient;
use Exception;

class UpdateUserController
{
    private TursoClient $tursoClient;

    public function __construct()
    {
        // Validate and initialize TursoClient
        $databaseUrl = $_ENV['TURSO_DB_URL'] ?? '';
        $authToken = $_ENV['TURSO_AUTH_TOKEN'] ?? '';
        if (empty($databaseUrl) || empty($authToken)) {
            throw new Exception('Database URL or Auth Token is not set in the environment variables.');
        }

        $this->tursoClient = new TursoClient($databaseUrl, $authToken);
    }

    public function updateUser(Request $request, Response $response): Response
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->sendJsonResponse($response, ['success' => false, 'message' => 'Invalid request method.'], 405);
        }

        $data = $request->getParsedBody();
        $email = $data['email'] ?? null;
        $userType = $data['userType'] ?? null;
        $businessName = $data['businessName'] ?? null;
        $businessType = $data['businessType'] ?? null;
        $businessAddress = $data['businessAddress'] ?? null;
        $farmName = $data['farmName'] ?? null;
        $biography = $data['biography'] ?? null;
        $farmLocation = $data['farmLocation'] ?? null;

        // Validate required fields
        if (empty($email) || empty($userType)) {
            return $this->sendJsonResponse($response, ['success' => false, 'message' => 'Email and user type are required.'], 400);
        }

        try {
            // Update the user type in the Users table
            $updateUserQuery = "UPDATE Users SET userType = ? WHERE email = ?";
            $this->tursoClient->executeQuery($updateUserQuery, [$userType, $email]);

            // Insert corresponding data based on user type
            if ($userType === 'Business') {
                $this->handleBusinessUpdate($email, $businessName, $businessType, $businessAddress);
            } elseif ($userType === 'Farmer') {
                $this->handleFarmerUpdate($email, $farmName, $biography, $farmLocation);
            } else {
                return $this->sendJsonResponse($response, ['success' => false, 'message' => 'Invalid user type provided.'], 400);
            }

            return $this->sendJsonResponse($response, ['success' => true, 'message' => 'User details updated successfully.']);
        } catch (Exception $e) {
            return $this->sendJsonResponse($response, ['success' => false, 'message' => 'Error updating user details: ' . $e->getMessage()], 500);
        }
    }

    private function handleBusinessUpdate(string $email, ?string $businessName, ?string $businessType, ?string $businessAddress): void
    {
        $insertBusinessQuery = "INSERT INTO Businesses (email, business_name, business_type, business_address) VALUES (?, ?, ?, ?)";
        $this->tursoClient->executeQuery($insertBusinessQuery, [$email, $businessName, $businessType, $businessAddress]);
    }

    private function handleFarmerUpdate(string $email, ?string $farmName, ?string $biography, ?string $farmLocation): void
    {
        $insertFarmerQuery = "INSERT INTO Farmers (email, farm_name, biography, farm_location) VALUES (?, ?, ?, ?)";
        $this->tursoClient->executeQuery($insertFarmerQuery, [$email, $farmName, $biography, $farmLocation]);
    }

    private function sendJsonResponse(Response $response, array $data, int $statusCode = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($statusCode);
    }
}
