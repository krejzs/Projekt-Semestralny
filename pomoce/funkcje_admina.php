<?php

declare(strict_types=1);


function nazwa_statusu(string $status): string
{
    $nazwy = [
        'pending'   => 'oczekująca',
        'confirmed' => 'potwierdzona',
        'completed' => 'zrealizowana',
        'cancelled' => 'anulowana',
    ];

    return $nazwy[$status] ?? $status;
}

function nazwa_roli(string $rola): string
{
    $nazwy = [
        'client'   => 'uczeń',
        'employee' => 'lektor',
        'admin'    => 'administrator',
    ];

    return $nazwy[$rola] ?? $rola;
}

function nazwa_dnia(int $dzien): string
{
    $dni = [
        1 => 'poniedziałek',
        2 => 'wtorek',
        3 => 'środa',
        4 => 'czwartek',
        5 => 'piątek',
        6 => 'sobota',
        7 => 'niedziela',
    ];

    return $dni[$dzien] ?? '';
}

function id_statusu(PDO $pdo, string $nazwa): int
{
    $stmt = $pdo->prepare('SELECT id FROM reservation_statuses WHERE name = :nazwa');
    $stmt->execute(['nazwa' => $nazwa]);
    $wiersz = $stmt->fetch();

    return $wiersz ? (int) $wiersz['id'] : 0;
}

function poprawna_data(string $tekst): bool
{
    $data = DateTime::createFromFormat('Y-m-d', $tekst);

    return $data !== false && $data->format('Y-m-d') === $tekst;
}

function poprawna_godzina(string $tekst): bool
{
    return preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $tekst) === 1;
}

function numer_strony(): int
{
    $strona = (int) ($_GET['strona'] ?? 1);

    return $strona < 1 ? 1 : $strona;
}

function pokaz_strony(int $aktualna, int $ile_stron): void
{
    if ($ile_stron <= 1) {
        return;
    }

    $filtry = $_GET;
    unset($filtry['strona']);

    echo '<div class="strony">';
    for ($i = 1; $i <= $ile_stron; $i++) {
        $filtry['strona'] = $i;
        $adres = '?' . http_build_query($filtry);
        $klasa = ($i === $aktualna) ? 'aktualna' : '';
        echo '<a class="' . $klasa . '" href="' . e($adres) . '">' . $i . '</a> ';
    }
    echo '</div>';
}

function przycisk_post(string $akcja, int $id, string $napis, string $klasa = 'przycisk-szary', string $pytanie = ''): string
{
    $potwierdzenie = '';
    if ($pytanie !== '') {
        $potwierdzenie = ' onsubmit="return confirm(' . e((string) json_encode($pytanie)) . ');"';
    }

    return '<form method="post" class="w-wierszu"' . $potwierdzenie . '>'
        . csrf_field()
        . '<input type="hidden" name="akcja" value="' . e($akcja) . '">'
        . '<input type="hidden" name="id" value="' . $id . '">'
        . '<button type="submit" class="przycisk przycisk-maly ' . e($klasa) . '">' . e($napis) . '</button>'
        . '</form>';
}


function wszystkie_role(): array
{
    return ['client', 'employee', 'admin'];
}

function rola_konta(PDO $pdo, int $id_konta): ?string
{
    $stmt = $pdo->prepare('SELECT r.name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = :id');
    $stmt->execute(['id' => $id_konta]);
    $rola = $stmt->fetchColumn();

    return $rola === false ? null : (string) $rola;
}

function uzupelnij_profile_lektorow(PDO $pdo): void
{
    $pdo->exec(
        "INSERT IGNORE INTO employees (user_id, first_name, last_name, email, phone)
         SELECT u.id, u.first_name, u.last_name, u.email, u.phone
         FROM users u
         JOIN roles r ON r.id = u.role_id
         WHERE r.name = 'employee'
           AND NOT EXISTS (SELECT 1 FROM employees e WHERE e.user_id = u.id)"
    );
}

function zmien_role(PDO $pdo, int $id_konta, string $nowa_rola): void
{
    $id_roli = get_role_id($pdo, $nowa_rola);

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('UPDATE users SET role_id = :rola WHERE id = :id');
        $stmt->execute(['rola' => $id_roli, 'id' => $id_konta]);

        if ($nowa_rola === 'employee') {
            uzupelnij_profile_lektorow($pdo);
            $aktywny = 1;
        } else {
            $aktywny = 0;
        }

        $stmt = $pdo->prepare('UPDATE employees SET active = :aktywny WHERE user_id = :id');
        $stmt->execute(['aktywny' => $aktywny, 'id' => $id_konta]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
