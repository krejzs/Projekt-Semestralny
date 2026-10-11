<?php

declare(strict_types=1);

/**
 * php narzedzia/utworz_admina.php "Jan" "Kowalski" "admin@firma.pl" "MocneHaslo123!"
 * Uruchamiac tylko z konsoli.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('Ten skrypt można uruchomić wyłącznie z linii poleceń (CLI).');
}

require_once __DIR__ . '/../konfiguracja/baza_danych.php';
require_once __DIR__ . '/../pomoce/uwierzytelnianie.php';

[$scriptName, $firstName, $lastName, $email, $password] = array_pad($argv, 5, null);

if ($firstName === null || $lastName === null || $email === null || $password === null) {
    fwrite(STDERR, "Użycie: php narzedzia/utworz_admina.php <imie> <nazwisko> <email> <haslo>\n");
    exit(1);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Podany e-mail jest nieprawidłowy.\n");
    exit(1);
}

if (strlen($password) < 8) {
    fwrite(STDERR, "Hasło musi mieć co najmniej 8 znaków.\n");
    exit(1);
}

$pdo = getPDO();

if (email_exists($pdo, $email)) {
    fwrite(STDERR, "Użytkownik o tym adresie e-mail już istnieje.\n");
    exit(1);
}

$hash   = password_hash($password, PASSWORD_DEFAULT);
$roleId = get_role_id($pdo, 'admin');

$stmt = $pdo->prepare(
    'INSERT INTO users (first_name, last_name, email, password_hash, role_id, is_email_verified)
     VALUES (:first_name, :last_name, :email, :password_hash, :role_id, 1)'
);
$stmt->execute([
    'first_name'    => $firstName,
    'last_name'     => $lastName,
    'email'         => $email,
    'password_hash' => $hash,
    'role_id'       => $roleId,
]);

echo "Konto administratora zostało utworzone (ID: {$pdo->lastInsertId()}).\n";
