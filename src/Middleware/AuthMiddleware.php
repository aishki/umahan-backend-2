<?php

namespace App\Middleware;

use Lcobucci\Clock\SystemClock;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Validation\Constraint\LooseValidAt;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class AuthMiddleware
{
    private Configuration $jwtConfig;

    public function __construct()
    {
        
        $secretKey = $_ENV['JWT_SECRET'] ?? '';

        $this->jwtConfig = Configuration::forSymmetricSigner(
            new Sha256(),
            \Lcobucci\JWT\Signer\Key\InMemory::plainText($secretKey)
        );

        
        $clock = SystemClock::fromSystemTimezone();
        $this->jwtConfig->setValidationConstraints(
            new SignedWith($this->jwtConfig->signer(), $this->jwtConfig->verificationKey()),
            new LooseValidAt($clock)
        );
    }

    public function __invoke(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        
        $authHeader = $request->getHeaderLine('Authorization');

        if (!$authHeader) {
            return $this->unauthorizedResponse('Authorization header required');
        }

        list($type, $tokenString) = explode(' ', $authHeader, 2);

        if ($type !== 'Bearer' || !$tokenString) {
            return $this->unauthorizedResponse('Invalid authorization header format');
        }

        try {
            
            $token = $this->jwtConfig->parser()->parse($tokenString);
            $constraints = $this->jwtConfig->validationConstraints();

            
            if (!$this->jwtConfig->validator()->validate($token, ...$constraints)) {
                return $this->unauthorizedResponse('Token validation failed');
            }

            
            $claims = $token->claims()->all();
            $request = $request->withAttribute('user', $claims);

            return $handler->handle($request);

        } catch (\Exception $e) {
            error_log("Token validation error: " . $e->getMessage()); 
            return $this->unauthorizedResponse('Invalid or expired token');
        }
    }

    private function unauthorizedResponse($message)
    {
        $response = new Response();
        $response->getBody()->write(json_encode(['error' => $message]));
        return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
    }
}