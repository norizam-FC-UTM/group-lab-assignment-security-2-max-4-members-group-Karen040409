<?php

function sanitizeUser(?array $user): ?array
{
    if (!$user) {
        return null;
    }

    unset($user['password'], $user['password_hash']);

    return $user;
}

function sanitizeUsers(array $users): array
{
    return array_map('sanitizeUser', $users);
}

function validatePersonData(array $data): array
{
    $errors = [];

    $name = trim((string) ($data['name'] ?? ''));
    if ($name === '') {
        $errors['name'] = 'Name is required and cannot be empty';
    }

    if (!isset($data['age']) || !is_numeric($data['age'])) {
        $errors['age'] = 'Age must be a number between 1 and 120';
    } else {
        $age = (int) $data['age'];
        if ($age < 1 || $age > 120) {
            $errors['age'] = 'Age must be between 1 and 120';
        }
    }

    if (!isset($data['height']) || !is_numeric($data['height'])) {
        $errors['height'] = 'Height must be a number between 0.5 and 2.5 metres';
    } else {
        $height = (float) $data['height'];
        if ($height < 0.5 || $height > 2.5) {
            $errors['height'] = 'Height must be between 0.5 and 2.5 metres';
        }
    }

    if (!isset($data['weight']) || !is_numeric($data['weight'])) {
        $errors['weight'] = 'Weight must be a number between 2 and 300 kg';
    } else {
        $weight = (float) $data['weight'];
        if ($weight < 2 || $weight > 300) {
            $errors['weight'] = 'Weight must be between 2 and 300 kg';
        }
    }

    return $errors;
}

function calculateBmiAndCategory(float $height, float $weight): array
{
    $bmi = round($weight / ($height * $height), 2);

    if ($bmi < 18.5) {
        $category = 'Underweight';
    } elseif ($bmi < 25) {
        $category = 'Normal';
    } elseif ($bmi < 30) {
        $category = 'Overweight';
    } else {
        $category = 'Obese';
    }

    return [
        'bmi' => $bmi,
        'category' => $category,
    ];
}

function normalizedPersonFields(array $data): array
{
    return [
        'name' => trim((string) ($data['name'] ?? '')),
        'age' => (int) $data['age'],
        'height' => (float) $data['height'],
        'weight' => (float) $data['weight'],
        'notes' => isset($data['notes']) ? (string) $data['notes'] : '',
    ];
}
