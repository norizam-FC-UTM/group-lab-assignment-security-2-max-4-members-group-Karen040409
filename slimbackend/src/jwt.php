<?php

use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Psr7\Response as SlimResponse;

const JWT_EXPIRY_SECONDS = 3600;

function loadJwtEnvFile(string $path): void
{
    if (!file_exists($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);
        putenv(trim($name) . '=' . trim($value));
    }
}

function getJwtSecret(): string
{
    static $secret = null;

    if ($secret !== null) {
        return $secret;
    }

    loadJwtEnvFile(dirname(__DIR__) . '/.env');

    $envSecret = getenv('JWT_SECRET');
    if (is_string($envSecret) && $envSecret !== '') {
        $secret = $envSecret;
        return $secret;
    }

    $localConfigPath = dirname(__DIR__) . '/jwt.config.local.php';
    if (file_exists($localConfigPath)) {
        $config = require $localConfigPath;
        if (!empty($config['secret']) && is_string($config['secret'])) {
            $secret = $config['secret'];
            return $secret;
        }
    }

    throw new RuntimeException(
        'JWT secret is not configured. Copy .env.example to .env or jwt.config.local.php.example to jwt.config.local.php'
    );
}

function createJwtToken(array $user): string
{
    $now = time();

    $payload = [
        'user_id' => (int) $user['id'],
        'role' => $user['role'],
        'iat' => $now,
        'exp' => $now + JWT_EXPIRY_SECONDS,
    ];

    return JWT::encode($payload, getJwtSecret(), 'HS256');
}

function verifyJwtToken(string $token): stdClass
{
    return JWT::decode($token, new Key(getJwtSecret(), 'HS256'));
}

function jwtUnauthorizedResponse(): Response
{
    $response = new SlimResponse();
    $response->getBody()->write(json_encode(['error' => 'Unauthorized'], JSON_PRETTY_PRINT));

    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(401);
}

function jwtAuthMiddleware(): callable
{
    return function (Request $request, $handler) {
        $auth = $request->getHeaderLine('Authorization');

        if (!$auth || !preg_match('/Bearer\s+(\S+)/', $auth, $matches)) {
            return jwtUnauthorizedResponse();
        }

        try {
            $payload = verifyJwtToken($matches[1]);
        } catch (ExpiredException | BeforeValidException | SignatureInvalidException $e) {
            return jwtUnauthorizedResponse();
        } catch (Throwable $e) {
            return jwtUnauthorizedResponse();
        }

        if (!isset($payload->user_id, $payload->role)) {
            return jwtUnauthorizedResponse();
        }

        $request = $request
            ->withAttribute('user_id', (int) $payload->user_id)
            ->withAttribute('role', (string) $payload->role);

        return $handler->handle($request);
    };
}

function getAuthUserId(Request $request): int
{
    return (int) $request->getAttribute('user_id');
}

function getAuthRole(Request $request): string
{
    return (string) $request->getAttribute('role');
}

function jwtForbiddenResponse(): Response
{
    $response = new SlimResponse();
    $response->getBody()->write(json_encode(['error' => 'Forbidden'], JSON_PRETTY_PRINT));

    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(403);
}

function requireRolesMiddleware(array $roles): callable
{
    return function (Request $request, $handler) use ($roles) {
        if (!in_array(getAuthRole($request), $roles, true)) {
            return jwtForbiddenResponse();
        }

        return $handler->handle($request);
    };
}
