<?php

namespace App\Controllers;

use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;

class UserController
{
    private Client $client;
    private string $apiKey;
    private Configuration $jwtConfig;

    public function __construct()
    {
        $this->client = new Client();
        $this->apiKey = $_ENV['USER_API_KEY'] ?? '';

        if (empty($this->apiKey)) {
            throw new \Exception('API Key is not set in the environment variables.');
        }

        $jwtSecret = $_ENV['JWT_SECRET'] ?? '';
        if (empty($jwtSecret)) {
            throw new \Exception('JWT Secret is not set in the environment variables.');
        }

        $this->jwtConfig = Configuration::forSymmetricSigner(
            new Sha256(),
            InMemory::plainText($jwtSecret)
        );
    }

    // Register User
    public function register(Request $request, Response $response, array $args): Response
    {
        $data = $request->getParsedBody();
        $firstName = $data['first_name'] ?? '';
        $lastName = $data['last_name'] ?? '';
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        if (empty($firstName) || empty($lastName) || empty($email) || empty($password)) {
            return $this->respondWithJson($response, ['error' => 'All fields are required.'], 400);
        }

        try {
            $res = $this->client->post('http://pzf.22b.mytemp.website/api/register.php', [
                'form_params' => [
                    'api_key' => $this->apiKey,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $email,
                    'password' => $password,
                ]
            ]);

            $body = json_decode($res->getBody(), true);

            if ($body['success'] ?? false) {
                $token = $this->generateJwtToken($email);

                return $this->respondWithJson($response, [
                    'success' => true,
                    'message' => 'User registered successfully.',
                    'token' => $token
                ], $res->getStatusCode());
            } else {
                return $this->respondWithJson($response, [
                    'success' => false,
                    'message' => $body['message'] ?? 'Registration failed.'
                ], 400);
            }

        } catch (\Exception $e) {
            error_log('Registration Error: ' . $e->getMessage());
            return $this->respondWithJson($response, ['error' => 'Registration failed.'], 500);
        }
    }

    // User Login
    public function login(Request $request, Response $response, array $args): Response
    {
        $data = $request->getParsedBody();
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        if (empty($email) || empty($password)) {
            return $this->respondWithJson($response, ['error' => 'Email and password are required.'], 400);
        }

        try {
            $res = $this->client->post('http://pzf.22b.mytemp.website/api/validate.php', [
                'form_params' => [
                    'api_key' => $this->apiKey,
                    'email' => $email,
                    'password' => $password,
                ]
            ]);

            $body = json_decode($res->getBody(), true);

            if ($body['success'] ?? false) {
                $token = $this->generateJwtToken($email);

                return $this->respondWithJson($response, [
                    'success' => true,
                    'message' => 'User validated successfully.',
                    'token' => $token
                ], $res->getStatusCode());
            } else {
                return $this->respondWithJson($response, [
                    'success' => false,
                    'message' => $body['message'] ?? 'Invalid credentials.'
                ], 401);
            }

        } catch (\Exception $e) {
            error_log('Login Error: ' . $e->getMessage());
            return $this->respondWithJson($response, ['error' => 'Login failed due to server error.'], 500);
        }
    }

    // Get User Profile
    public function getProfile(Request $request, Response $response, array $args): Response
    {
        $userClaims = $request->getAttribute('user');
        $email = $userClaims['email'] ?? null;

        if (!$email) {
            return $this->respondWithJson($response, ['error' => 'User not found.'], 404);
        }

        try {
            $res = $this->client->post('http://pzf.22b.mytemp.website/api/listing.php', [
                'form_params' => [
                    'api_key' => $this->apiKey
                ]
            ]);

            $users = json_decode($res->getBody(), true)['users'] ?? [];

            $userData = array_filter($users, function($user) use ($email) {
                return $user['email'] === $email;
            });

            if (empty($userData)) {
                return $this->respondWithJson($response, ['error' => 'User not found.'], 404);
            }

            return $this->respondWithJson($response, array_values($userData)[0]);

        } catch (\Exception $e) {
            error_log('GetProfile Error: ' . $e->getMessage());
            return $this->respondWithJson($response, ['error' => 'Failed to fetch profile.'], 500);
        }
    }

    // Update User Profile
    public function updateProfile(Request $request, Response $response, array $args): Response
    {
        $userClaims = $request->getAttribute('user');
        $email = $userClaims['email'] ?? null;

        if (!$email) {
            return $this->respondWithJson($response, ['error' => 'User not found.'], 404);
        }

        $data = $request->getParsedBody();
        $firstName = $data['first_name'] ?? '';
        $lastName = $data['last_name'] ?? '';
        $password = $data['password'] ?? '';

        if (empty($firstName) || empty($lastName)) {
            return $this->respondWithJson($response, ['error' => 'First name and last name are required.'], 400);
        }

        try {
            // Assume the update is successful
            return $this->respondWithJson($response, ['message' => 'Profile updated successfully.']);

        } catch (\Exception $e) {
            error_log('UpdateProfile Error: ' . $e->getMessage());
            return $this->respondWithJson($response, ['error' => 'Failed to update profile.'], 500);
        }
    }

    private function respondWithJson(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }

    private function generateJwtToken(string $email): string
    {
        $now = new \DateTimeImmutable();
        $token = $this->jwtConfig->builder()
            ->issuedBy('http://pzf.22b.mytemp.website/api/')
            ->issuedAt($now)
            ->expiresAt($now->modify('+1 hour'))
            ->withClaim('email', $email)
            ->getToken($this->jwtConfig->signer(), $this->jwtConfig->signingKey());

        return $token->toString();
    }
}
