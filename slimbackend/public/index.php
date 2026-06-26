<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/db.php';
require __DIR__ . '/../src/helpers.php';
require __DIR__ . '/../src/jwt.php';

$app = AppFactory::create();

// Required for JSON/form body parsing in Slim 4.
$app->addBodyParsingMiddleware();

// Log errors server-side; do not expose details to API clients.
$app->addErrorMiddleware(true, false, false);

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
        ->withHeader('Access-Control-Allow-Origin', '*')
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

function handleApiException(Response $response, Throwable $e): Response
{
    logInternalError($e);

    return jsonResponse($response, ['error' => 'Unable to process request'], 500);
}

// ----------------------------------------------------------
// Root routes 
// ----------------------------------------------------------
$app->get('/', function (Request $request, Response $response) {
    return jsonResponse($response, [
        'message' => 'Person BMI API',
        'status' => 'ok'
    ]);
});

$app->get('/api/health', function (Request $request, Response $response) {
    return jsonResponse($response, [
        'status' => 'ok',
        'api' => 'person-bmi-api'
    ]);
});

// ----------------------------------------------------------
// Public route: Register
// ----------------------------------------------------------
$app->post('/api/register', function (Request $request, Response $response) {
    try {
        $pdo = getPDO();
        $data = getRequestData($request);

        // Registration always creates a standard user account.
        $name = trim((string) ($data['name'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        if ($name === '' || $email === '' || $password === '') {
            return jsonResponse($response, ['error' => 'Name, email, and password are required'], 400);
        }

        $role = 'user';
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

        $stmt = $pdo->prepare('SELECT id, name, email, role FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $user = formatPublicUser($stmt->fetch());

        return jsonResponse($response, [
            'message' => 'User registered.',
            'user' => $user,
        ], 201);
    } catch (Throwable $e) {
        return handleApiException($response, $e);
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

        $sql = 'SELECT id, name, email, password_hash, role FROM users WHERE email = :email LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return jsonResponse($response, ['error' => 'Invalid login'], 401);
        }

        $token = createJwtToken($user);

        return jsonResponse($response, [
            'message' => 'Login successful.',
            'token' => $token,
            'user' => formatPublicUser($user),
        ]);
    } catch (Throwable $e) {
        return handleApiException($response, $e);
    }
});

// ----------------------------------------------------------
// JWT-protected routes
// ----------------------------------------------------------
$app->group('/api', function ($group) {
    $group->get('/profile', function (Request $request, Response $response) {
        try {
            $pdo = getPDO();
            $userId = getAuthUserId($request);

            $stmt = $pdo->prepare('SELECT id, name, email, role FROM users WHERE id = :id');
            $stmt->execute(['id' => $userId]);
            $user = formatProfileUser($stmt->fetch());

            if (!$user) {
                return jsonResponse($response, ['error' => 'User not found'], 404);
            }

            return jsonResponse($response, $user);
        } catch (Throwable $e) {
            return handleApiException($response, $e);
        }
    });

    $group->get('/persons', function (Request $request, Response $response) {
        try {
            $pdo = getPDO();

            $userId = getAuthUserId($request);

            $sql = 'SELECT ' . PERSON_SELECT_COLUMNS . ' FROM persons WHERE user_id = :user_id ORDER BY id DESC';
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['user_id' => $userId]);
            $persons = formatPersonRecords($stmt->fetchAll());

            return jsonResponse($response, [
                'message' => 'BMI records returned.',
                'persons' => $persons,
            ]);
        } catch (Throwable $e) {
            return handleApiException($response, $e);
        }
    });

    $group->post('/persons', function (Request $request, Response $response) {
        try {
            $pdo = getPDO();
            $data = filterAllowedPersonInput(getRequestData($request));

            $errors = validatePersonData($data);
            if (!empty($errors)) {
                return jsonResponse($response, ['errors' => $errors], 400);
            }

            $userId = getAuthUserId($request);
            $fields = normalizedPersonFields($data);
            $calculated = calculateBmiAndCategory($fields['height'], $fields['weight']);

            $sql = 'INSERT INTO persons (user_id, name, age, height, weight, bmi, category, notes)
                    VALUES (:user_id, :name, :age, :height, :weight, :bmi, :category, :notes)';

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'user_id' => $userId,
                'name' => $fields['name'],
                'age' => $fields['age'],
                'height' => $fields['height'],
                'weight' => $fields['weight'],
                'bmi' => $calculated['bmi'],
                'category' => $calculated['category'],
                'notes' => $fields['notes'],
            ]);
            $id = $pdo->lastInsertId();
            $person = fetchPersonById($pdo, $id);

            return jsonResponse($response, [
                'message' => 'BMI record created.',
                'person' => $person,
            ], 201);
        } catch (Throwable $e) {
            return handleApiException($response, $e);
        }
    });

    $group->get('/persons/{id}', function (Request $request, Response $response, array $args) {
        try {
            $pdo = getPDO();
            $id = $args['id'];

            $person = fetchPersonById($pdo, $id);

            if (!$person) {
                return jsonResponse($response, ['error' => 'Record not found'], 404);
            }

            if (!canAccessPersonOnUserRoute($request, $person)) {
                return jwtForbiddenResponse();
            }

            return jsonResponse($response, [
                'message' => 'BMI record returned.',
                'person' => $person,
            ]);
        } catch (Throwable $e) {
            return handleApiException($response, $e);
        }
    });

    $group->put('/persons/{id}', function (Request $request, Response $response, array $args) {
        try {
            $pdo = getPDO();
            $id = $args['id'];
            $data = filterAllowedPersonInput(getRequestData($request));

            if (!hasAllowedPersonField($data)) {
                return jsonResponse($response, ['error' => 'No fields to update'], 400);
            }

            $existing = fetchPersonById($pdo, $id);

            if (!$existing) {
                return jsonResponse($response, ['error' => 'Record not found'], 404);
            }

            if (!canAccessPersonOnUserRoute($request, $existing)) {
                return jwtForbiddenResponse();
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

            $sql = 'UPDATE persons
                    SET name = :name,
                        age = :age,
                        height = :height,
                        weight = :weight,
                        bmi = :bmi,
                        category = :category,
                        notes = :notes
                    WHERE id = :id';

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'name' => $fields['name'],
                'age' => $fields['age'],
                'height' => $fields['height'],
                'weight' => $fields['weight'],
                'bmi' => $calculated['bmi'],
                'category' => $calculated['category'],
                'notes' => $fields['notes'],
                'id' => $id,
            ]);

            $person = fetchPersonById($pdo, $id);

            return jsonResponse($response, [
                'message' => 'BMI record updated.',
                'person' => $person,
            ]);
        } catch (Throwable $e) {
            return handleApiException($response, $e);
        }
    });

    $group->delete('/persons/{id}', function (Request $request, Response $response, array $args) {
        try {
            $pdo = getPDO();
            $id = $args['id'];

            $person = fetchPersonById($pdo, $id);

            if (!$person) {
                return jsonResponse($response, ['error' => 'Record not found'], 404);
            }

            if (!canDeletePersonOnUserRoute($request, $person)) {
                return jwtForbiddenResponse();
            }

            $stmt = $pdo->prepare('DELETE FROM persons WHERE id = :id');
            $stmt->execute(['id' => $id]);

            return jsonResponse($response, [
                'message' => 'BMI record deleted.',
            ]);
        } catch (Throwable $e) {
            return handleApiException($response, $e);
        }
    });

    $group->group('/staff', function ($staffGroup) {
        $staffGroup->get('/persons', function (Request $request, Response $response) {
            try {
                $pdo = getPDO();

                $sql = 'SELECT ' . PERSON_SELECT_COLUMNS . '
                        FROM persons
                        ORDER BY id DESC';

                $persons = formatPersonRecords($pdo->query($sql)->fetchAll());

                return jsonResponse($response, [
                    'message' => 'All BMI records returned.',
                    'persons' => $persons,
                ]);
            } catch (Throwable $e) {
                return handleApiException($response, $e);
            }
        });

        $staffGroup->get('/persons/{id}', function (Request $request, Response $response, array $args) {
            try {
                $pdo = getPDO();
                $id = $args['id'];

                $sql = 'SELECT ' . PERSON_SELECT_COLUMNS . ' FROM persons WHERE id = :id';

                $stmt = $pdo->prepare($sql);
                $stmt->execute(['id' => $id]);
                $person = formatPersonRecord($stmt->fetch());

                if (!$person) {
                    return jsonResponse($response, ['error' => 'Record not found'], 404);
                }

                return jsonResponse($response, [
                    'message' => 'BMI record returned.',
                    'person' => $person,
                ]);
            } catch (Throwable $e) {
                return handleApiException($response, $e);
            }
        });
    })->add(requireRolesMiddleware(['staff', 'admin']));

    $group->group('/admin', function ($adminGroup) {
        $adminGroup->get('/users', function (Request $request, Response $response) {
            try {
                $pdo = getPDO();

                $sql = 'SELECT id, name, email, role FROM users ORDER BY id ASC';
                $stmt = $pdo->query($sql);
                $users = formatPublicUsers($stmt->fetchAll());

                return jsonResponse($response, [
                    'message' => 'All users returned.',
                    'users' => $users,
                ]);
            } catch (Throwable $e) {
                return handleApiException($response, $e);
            }
        });

        $adminGroup->put('/users/{id}/role', function (Request $request, Response $response, array $args) {
            try {
                $pdo = getPDO();
                $id = $args['id'];
                $data = getRequestData($request);
                $role = $data['role'] ?? '';

                if (!isValidRole($role)) {
                    return jsonResponse($response, ['error' => 'Invalid role'], 400);
                }

                $sql = 'UPDATE users SET role = :role WHERE id = :id';
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    'role' => $role,
                    'id' => $id,
                ]);

                $stmt = $pdo->prepare('SELECT id, name, email, role FROM users WHERE id = :id');
                $stmt->execute(['id' => $id]);
                $user = formatPublicUser($stmt->fetch());

                if (!$user) {
                    return jsonResponse($response, ['error' => 'User not found'], 404);
                }

                return jsonResponse($response, [
                    'message' => 'User role updated.',
                    'user' => $user,
                ]);
            } catch (Throwable $e) {
                return handleApiException($response, $e);
            }
        });

        $adminGroup->delete('/persons/{id}', function (Request $request, Response $response, array $args) {
            try {
                $pdo = getPDO();
                $id = $args['id'];

                $person = fetchPersonById($pdo, $id);

                if (!$person) {
                    return jsonResponse($response, ['error' => 'Record not found'], 404);
                }

                $stmt = $pdo->prepare('DELETE FROM persons WHERE id = :id');
                $stmt->execute(['id' => $id]);

                return jsonResponse($response, [
                    'message' => 'BMI record deleted.',
                ]);
            } catch (Throwable $e) {
                return handleApiException($response, $e);
            }
        });
    })->add(requireRolesMiddleware(['admin']));
})->add(jwtAuthMiddleware());

// Preflight catch-all
$app->options('/{routes:.+}', function (Request $request, Response $response) {
    return $response;
});

$app->run();