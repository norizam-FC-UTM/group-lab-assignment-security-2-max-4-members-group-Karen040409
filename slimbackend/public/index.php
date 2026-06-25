<?php
// ==========================================================
// SECJ3483 Web Technology
// Person BMI Insecure Slim Backend Starter
// ==========================================================
// NOTA:
// This backend is intentionally insecure.
// provided for investigation and fixing during lab activiy this week.
// Do NOT use this code in real applications.
// ==========================================================

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/db.php';
require __DIR__ . '/../src/helpers.php';

$app = AppFactory::create();

// Required for JSON/form body parsing in Slim 4.
$app->addBodyParsingMiddleware();

// Helpful for development error display.
// INSECURE: In production, detailed errors should not be shown to users.
$app->addErrorMiddleware(true, true, true);

// ----------------------------------------------------------
// CORS for Vue CLI frontend
// ----------------------------------------------------------
$app->add(function (Request $request, $handler) {
    if ($request->getMethod() === 'OPTIONS') {
        $response = new \Slim\Psr7\Response();
    } else {
        $response = $handler->handle($request);
    }

    return $response
        ->withHeader('Access-Control-Allow-Origin', '*') // INSECURE: convenient untuk aktiviti lab, tidak untuk production.
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
        ->withHeader('Access-Control-Allow-Credentials', 'false');
});

// ----------------------------------------------------------
// Helper functions
// ----------------------------------------------------------
function jsonResponse(Response $response, $data, int $status = 200): Response
{
    $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));

    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus($status);
}

//
function getRequestData(Request $request): array
{
    $data = $request->getParsedBody();

    if (is_array($data) && !empty($data)) {
        return $data;
    }

    $rawBody = (string) $request->getBody();

    if ($rawBody !== '') {
        $jsonData = json_decode($rawBody, true);

        if (is_array($jsonData)) {
            return $jsonData;
        }
    }

    return is_array($data) ? $data : [];
}

// INSECURE: This is NOT a real JWT.
// This is just base64 JSON. You should replace this with real signed JWT sebagai pembaikan.
function createFakeToken(array $user): string
{
    $payload = [
        'user_id' => $user['id'],
        'role' => $user['role'],
        'email' => $user['email'],
        'note' => 'INSECURE_FAKE_TOKEN_NO_SIGNATURE_NO_EXPIRY'
    ];

    return base64_encode(json_encode($payload));
}

// INSECURE: This trusts an unsigned, editable token.
// You should replace this with proper JWT verification.
function getFakeUserFromToken(Request $request): ?array
{
    $auth = $request->getHeaderLine('Authorization');

    if (!$auth || !preg_match('/Bearer\s+(\S+)/', $auth, $matches)) {
        return null;
    }
    $json = base64_decode($matches[1], true);

    if (!$json) {
        return null;
    }
    $payload = json_decode($json, true);
    return is_array($payload) ? $payload : null;
}

function exposeException(Response $response, Throwable $e): Response
{
    // INSECURE: exposes detailed internal error to API client.
    return jsonResponse($response, [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], 500);
}

// ----------------------------------------------------------
// Root routes 
// ----------------------------------------------------------
$app->get('/', function (Request $request, Response $response) {
    return jsonResponse($response, [
        'message' => 'Person BMI Insecure Slim Backend Starter',
        'warning' => 'This backend is intentionally insecure for classroom investigation.'
    ]);
});

$app->get('/api/health', function (Request $request, Response $response) {
    return jsonResponse($response, [
        'status' => 'ok',
        'api' => 'person-bmi-insecure-backend'
    ]);
});

// ----------------------------------------------------------
// Public route: Register
// ----------------------------------------------------------
$app->post('/api/register', function (Request $request, Response $response) {
    try {
        $pdo = getPDO();
        $data = getRequestData($request);

        // INSECURE: Role is accepted from frontend, so user can register as admin/staff.
        $name = $data['name'] ?? '';
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';
        $role = $data['role'] ?? 'user';
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $sql = 'INSERT INTO users (name, email, password, password_hash, role)
                VALUES (:name, :email, NULL, :password_hash, :role)';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'name' => $name,
            'email' => $email,
            'password_hash' => $passwordHash,
            'role' => $role,
        ]);
        $id = $pdo->lastInsertId();

        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $user = sanitizeUser($stmt->fetch());

        return jsonResponse($response, [
            'message' => 'User registered. This route is intentionally insecure.',
            'user' => $user,
            'debug_received_body' => $data,
            'debug_sql' => $sql
        ], 201);
    } catch (Throwable $e) {
        return exposeException($response, $e);
    }
});

// ----------------------------------------------------------
// Public route: Login
// ----------------------------------------------------------
$app->post('/api/login', function (Request $request, Response $response) {
    try {
        $pdo = getPDO();
        $data = getRequestData($request);

        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        $sql = 'SELECT * FROM users WHERE email = :email LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return jsonResponse($response, [
                'error' => 'Invalid login',
                'debug_received_body' => $data,
                'debug_sql' => $sql
            ], 401);
        }

        // INSECURE: fake unsigned token with no expiry.
        $token = createFakeToken($user);

        return jsonResponse($response, [
            'message' => 'Login successful. This token is intentionally insecure.',
            'token' => $token,
            'user' => sanitizeUser($user),
            'debug_received_body' => $data,
            'debug_sql' => $sql
        ]);
    } catch (Throwable $e) {
        return exposeException($response, $e);
    }
});

// ----------------------------------------------------------
// Protected-ish route: Profile
// ----------------------------------------------------------
$app->get('/api/profile', function (Request $request, Response $response) {
    try {
        $pdo = getPDO();

        // INSECURE:
        // If token missing, defaults to user 1.
        // If token exists, trusts unsigned editable token.
        $fakeUser = getFakeUserFromToken($request);
        $userId = $fakeUser['user_id'] ?? 1;

        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute(['id' => $userId]);
        $user = sanitizeUser($stmt->fetch());

        return jsonResponse($response, [
            'message' => 'Profile returned. This route trusts insecure token/default user.',
            'user' => $user,
            'token_payload_trusted_by_backend' => $fakeUser
        ]);
    } catch (Throwable $e) {
        return exposeException($response, $e);
    }
});

// ----------------------------------------------------------
// BMI routes
// ----------------------------------------------------------
$app->get('/api/persons', function (Request $request, Response $response) {
    try {
        $pdo = getPDO();

        // INSECURE:
        // Trusts unsigned token. If no token, returns all records.
        // Also accepts ?user_id= to override owner.
        $fakeUser = getFakeUserFromToken($request);
        $params = $request->getQueryParams();
        $userId = $params['user_id'] ?? ($fakeUser['user_id'] ?? null);

        if ($userId) {
            $sql = 'SELECT * FROM persons WHERE user_id = :user_id ORDER BY id DESC';
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['user_id' => $userId]);
        } else {
            $sql = 'SELECT * FROM persons ORDER BY id DESC';
            $stmt = $pdo->query($sql);
        }

        $persons = $stmt->fetchAll();

        return jsonResponse($response, [
            'message' => 'BMI records returned. This route is intentionally weak.',
            'persons' => $persons,
            'debug_sql' => $sql
        ]);
    } catch (Throwable $e) {
        return exposeException($response, $e);
    }
});

$app->post('/api/persons', function (Request $request, Response $response) {
    try {
        $pdo = getPDO();
        $data = getRequestData($request);

        $errors = validatePersonData($data);
        if (!empty($errors)) {
            return jsonResponse($response, ['errors' => $errors], 400);
        }

        // INSECURE: Trusts user_id from frontend.
        $user_id = $data['user_id'] ?? 1;
        $fields = normalizedPersonFields($data);
        $calculated = calculateBmiAndCategory($fields['height'], $fields['weight']);

        $sql = 'INSERT INTO persons (user_id, name, age, height, weight, bmi, category, notes)
                VALUES (:user_id, :name, :age, :height, :weight, :bmi, :category, :notes)';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'user_id' => $user_id,
            'name' => $fields['name'],
            'age' => $fields['age'],
            'height' => $fields['height'],
            'weight' => $fields['weight'],
            'bmi' => $calculated['bmi'],
            'category' => $calculated['category'],
            'notes' => $fields['notes'],
        ]);
        $id = $pdo->lastInsertId();

        $stmt = $pdo->prepare('SELECT * FROM persons WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $person = $stmt->fetch();

        return jsonResponse($response, [
            'message' => 'BMI record created. BMI and category calculated at backend.',
            'person' => $person,
            'debug_received_body' => $data,
            'debug_sql' => $sql
        ], 201);
    } catch (Throwable $e) {
        return exposeException($response, $e);
    }
});

$app->get('/api/persons/{id}', function (Request $request, Response $response, array $args) {
    try {
        $pdo = getPDO();
        $id = $args['id'];

        // TODO: Review whether this route should allow all users to access any record.
        $sql = 'SELECT * FROM persons WHERE id = :id';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $person = $stmt->fetch();

        if (!$person) {
            return jsonResponse($response, ['error' => 'Record not found'], 404);
        }

        return jsonResponse($response, [
            'message' => 'Record returned without ownership check.',
            'person' => $person,
            'debug_sql' => $sql
        ]);
    } catch (Throwable $e) {
        return exposeException($response, $e);
    }
});

$app->put('/api/persons/{id}', function (Request $request, Response $response, array $args) {
    try {
        $pdo = getPDO();
        $id = $args['id'];
        $data = getRequestData($request);

        $allowedInInsecureStarter = [
            'user_id',
            'name',
            'age',
            'height',
            'weight',
            'notes'
        ];

        $hasUpdateField = false;
        foreach ($allowedInInsecureStarter as $field) {
            if (array_key_exists($field, $data)) {
                $hasUpdateField = true;
                break;
            }
        }

        if (!$hasUpdateField) {
            return jsonResponse($response, [
                'error' => 'No fields to update',
                'debug_received_body' => $data
            ], 400);
        }

        $stmt = $pdo->prepare('SELECT * FROM persons WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $existing = $stmt->fetch();

        if (!$existing) {
            return jsonResponse($response, ['error' => 'Record not found'], 404);
        }

        $merged = [
            'name' => array_key_exists('name', $data) ? $data['name'] : $existing['name'],
            'age' => array_key_exists('age', $data) ? $data['age'] : $existing['age'],
            'height' => array_key_exists('height', $data) ? $data['height'] : $existing['height'],
            'weight' => array_key_exists('weight', $data) ? $data['weight'] : $existing['weight'],
            'notes' => array_key_exists('notes', $data) ? $data['notes'] : $existing['notes'],
        ];

        $errors = validatePersonData($merged);
        if (!empty($errors)) {
            return jsonResponse($response, ['errors' => $errors], 400);
        }

        $fields = normalizedPersonFields($merged);
        $calculated = calculateBmiAndCategory($fields['height'], $fields['weight']);
        $user_id = array_key_exists('user_id', $data) ? $data['user_id'] : $existing['user_id'];

        $sql = 'UPDATE persons
                SET user_id = :user_id,
                    name = :name,
                    age = :age,
                    height = :height,
                    weight = :weight,
                    bmi = :bmi,
                    category = :category,
                    notes = :notes
                WHERE id = :id';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'user_id' => $user_id,
            'name' => $fields['name'],
            'age' => $fields['age'],
            'height' => $fields['height'],
            'weight' => $fields['weight'],
            'bmi' => $calculated['bmi'],
            'category' => $calculated['category'],
            'notes' => $fields['notes'],
            'id' => $id,
        ]);

        $stmt = $pdo->prepare('SELECT * FROM persons WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $person = $stmt->fetch();

        return jsonResponse($response, [
            'message' => 'BMI record updated. BMI and category recalculated at backend.',
            'person' => $person,
            'debug_received_body' => $data,
            'debug_sql' => $sql
        ]);
    } catch (Throwable $e) {
        return exposeException($response, $e);
    }
});

$app->delete('/api/persons/{id}', function (Request $request, Response $response, array $args) {
    try {
        $pdo = getPDO();
        $id = $args['id'];

        // INSECURE: No auth, no ownership check, no role check.
        $sql = 'DELETE FROM persons WHERE id = :id';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $id]);

        return jsonResponse($response, [
            'message' => 'BMI record deleted without role or ownership check.',
            'debug_sql' => $sql
        ]);
    } catch (Throwable $e) {
        return exposeException($response, $e);
    }
});

// ----------------------------------------------------------
// Staff routes
// ----------------------------------------------------------
$app->get('/api/staff/persons', function (Request $request, Response $response) {
    try {
        $pdo = getPDO();

        // INSECURE: No staff/admin role check.
        $sql = "SELECT persons.*, users.email AS owner_email, users.role AS owner_role
                FROM persons
                JOIN users ON persons.user_id = users.id
                ORDER BY persons.id DESC";

        $persons = $pdo->query($sql)->fetchAll();

        return jsonResponse($response, [
            'message' => 'All BMI records returned without staff role check.',
            'persons' => $persons,
            'debug_sql' => $sql
        ]);
    } catch (Throwable $e) {
        return exposeException($response, $e);
    }
});

$app->get('/api/staff/persons/{id}', function (Request $request, Response $response, array $args) {
    try {
        $pdo = getPDO();
        $id = $args['id'];

        // INSECURE - Review whether this route should allow all users
        $sql = 'SELECT persons.*, users.email AS owner_email, users.role AS owner_role
                FROM persons
                JOIN users ON persons.user_id = users.id
                WHERE persons.id = :id';

        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $person = $stmt->fetch();

        if (!$person) {
            return jsonResponse($response, ['error' => 'Record not found'], 404);
        }

        return jsonResponse($response, [
            'message' => 'Staff record returned without role check.',
            'person' => $person,
            'debug_sql' => $sql
        ]);
    } catch (Throwable $e) {
        return exposeException($response, $e);
    }
});

// ----------------------------------------------------------
// Admin routes
// ----------------------------------------------------------
$app->get('/api/admin/users', function (Request $request, Response $response) {
    try {
        $pdo = getPDO();

        // INSECURE:
        // - No admin role check.
        $sql = 'SELECT * FROM users ORDER BY id ASC';
        $stmt = $pdo->query($sql);
        $users = sanitizeUsers($stmt->fetchAll());

        return jsonResponse($response, [
            'message' => 'All users returned without admin role check.',
            'users' => $users,
            'debug_sql' => $sql
        ]);
    } catch (Throwable $e) {
        return exposeException($response, $e);
    }
});

$app->put('/api/admin/users/{id}/role', function (Request $request, Response $response, array $args) {
    try {
        $pdo = getPDO();
        $id = $args['id'];
        $data = getRequestData($request);
        $role = $data['role'] ?? 'user';

        // INSECURE: No admin role check. Anyone can change any user role.
        $sql = 'UPDATE users SET role = :role WHERE id = :id';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'role' => $role,
            'id' => $id,
        ]);

        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $user = sanitizeUser($stmt->fetch());

        return jsonResponse($response, [
            'message' => 'User role changed without admin verification.',
            'user' => $user,
            'debug_received_body' => $data,
            'debug_sql' => $sql
        ]);
    } catch (Throwable $e) {
        return exposeException($response, $e);
    }
});

$app->delete('/api/admin/persons/{id}', function (Request $request, Response $response, array $args) {
    try {
        $pdo = getPDO();
        $id = $args['id'];

        // INSECURE: No admin role check.
        $sql = 'DELETE FROM persons WHERE id = :id';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $id]);

        return jsonResponse($response, [
            'message' => 'Admin delete executed without admin role verification.',
            'debug_sql' => $sql
        ]);
    } catch (Throwable $e) {
        return exposeException($response, $e);
    }
});

// Preflight catch-all
$app->options('/{routes:.+}', function (Request $request, Response $response) {
    return $response;
});

$app->run();