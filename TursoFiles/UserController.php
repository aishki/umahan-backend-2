<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\TursoClient;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;

class UserController
{
    private TursoClient $db;
    private Configuration $jwtConfig;

    public function __construct(TursoClient $db)
    {
        $this->db = $db;
        $secretKey = $_ENV['JWT_SECRET'] ?? '';
        $this->jwtConfig = Configuration::forSymmetricSigner(
            new Sha256(),
            InMemory::plainText($secretKey)
        );
    }

    public function register(Request $request, Response $response, array $args): Response
    {
        $data = $request->getParsedBody();
        $username = $data['username'] ?? '';
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        if (empty($username) || empty($email) || empty($password)) {
            return $this->respondWithJson($response, ['error' => 'All fields are required.'], 400);
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        try {
            
            $sql = "INSERT INTO users (username, email, password) VALUES (?, ?, ?) RETURNING id, username";
            $result = $this->db->executeQuery($sql, [$username, $email, $hashedPassword]);

            if (empty($result['results'][0]['response']['result']['rows'])) {
                return $this->respondWithJson($response, ['error' => 'Registration failed.'], 500);
            }

            $userRow = $result['results'][0]['response']['result']['rows'][0];
            
            $userId = $userRow[0]['value'];
            $username = $userRow[1]['value'];

            
            $token = $this->generateJWT([
                'id' => $userId,
                'username' => $username,
            ]);

            
            return $this->respondWithJson($response, ['token' => $token], 201);
        } catch (\Exception $e) {
            
            if (strpos($e->getMessage(), 'UNIQUE constraint failed') !== false) {
                return $this->respondWithJson($response, ['error' => 'Username or email already exists.'], 409);
            }
            return $this->respondWithJson($response, ['error' => 'Registration failed.'], 500);
        }
    }

    public function login(Request $request, Response $response, array $args): Response
    {
        $data = $request->getParsedBody();
        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';

        if (empty($username) || empty($password)) {
            return $this->respondWithJson($response, ['error' => 'Username and password are required.'], 400);
        }

        try {
            $sql = "SELECT * FROM users WHERE username = ?";
            $userResult = $this->db->executeQuery($sql, [$username]);

            if (empty($userResult['results'][0]['response']['result']['rows'])) {
                return $this->respondWithJson($response, ['error' => 'User not found'], 404);
            }

            $userRow = $userResult['results'][0]['response']['result']['rows'][0];
            $hashedPassword = $userRow[3]['value'];

            if (!password_verify($password, $hashedPassword)) {
                return $this->respondWithJson($response, ['error' => 'Password does not match.'], 401);
            }

            $token = $this->generateJWT([
                'id' => $userRow[0]['value'],
                'username' => $userRow[1]['value'],
            ]);
            return $this->respondWithJson($response, ['token' => $token]);

        } catch (\Exception $e) {
            return $this->respondWithJson($response, ['error' => 'Login failed due to server error.'], 500);
        }
    }

    private function generateJWT(array $user): string
    {
        $now = new \DateTimeImmutable();
        $expiry = $now->modify('+1 hour');

        return $this->jwtConfig->builder()
            ->issuedBy('umahan-Backend')
            ->permittedFor('your-application')
            ->identifiedBy(bin2hex(random_bytes(16)))
            ->issuedAt($now)
            ->canOnlyBeUsedAfter($now)
            ->expiresAt($expiry)
            ->withClaim('uid', $user['id'])
            ->withClaim('uname', $user['username'])
            ->getToken($this->jwtConfig->signer(), $this->jwtConfig->signingKey())
            ->toString();
    }

    private function respondWithJson(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
