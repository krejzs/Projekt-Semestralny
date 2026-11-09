<?php

declare(strict_types=1);

const KROK_MINUT      = 30;   
const MAX_DNI_NAPRZOD = 60;   

function na_minuty(string $godzina): int
{
    $czesci = explode(':', $godzina);

    return (int) $czesci[0] * 60 + (int) $czesci[1];
}

// 630 -> "10:30"
function z_minut(int $minuty): string
{
    return sprintf('%02d:%02d', intdiv($minuty, 60), $minuty % 60);
}

function skrot_dnia(int $dzien): string
{
    $skroty = [1 => 'Pn', 2 => 'Wt', 3 => 'Śr', 4 => 'Cz', 5 => 'Pt', 6 => 'Sb', 7 => 'Nd'];

    return $skroty[$dzien] ?? '';
}

function terminy_sie_nakladaja(int $start_a, int $koniec_a, int $start_b, int $koniec_b): bool
{
    return $start_a < $koniec_b && $koniec_a > $start_b;
}

function pobierz_lekcje(PDO $pdo, int $id_lekcji): ?array
{
    $stmt = $pdo->prepare(
        'SELECT s.id, s.name, s.description, s.price, s.duration_minutes, c.name AS jezyk
         FROM services s
         JOIN categories c ON c.id = s.category_id
         WHERE s.id = :id AND s.active = 1'
    );
    $stmt->execute(['id' => $id_lekcji]);
    $lekcja = $stmt->fetch();

    return $lekcja ?: null;
}

function lektorzy_lekcji(PDO $pdo, int $id_lekcji): array
{
    $stmt = $pdo->prepare(
        'SELECT e.id, e.first_name, e.last_name
         FROM employees e
         JOIN employee_services es ON es.employee_id = e.id
         WHERE es.service_id = :lekcja
           AND e.active = 1
           AND EXISTS (SELECT 1 FROM employee_availability a WHERE a.employee_id = e.id)
         ORDER BY e.last_name, e.first_name'
    );
    $stmt->execute(['lekcja' => $id_lekcji]);

    return $stmt->fetchAll();
}

function lektorzy_jezyka(PDO $pdo, int $id_jezyka): array
{
    $stmt = $pdo->prepare(
        'SELECT DISTINCT e.id, e.first_name, e.last_name
         FROM employees e
         JOIN employee_services es ON es.employee_id = e.id
         JOIN services s ON s.id = es.service_id
         WHERE s.category_id = :jezyk
           AND s.active = 1
           AND e.active = 1
           AND EXISTS (SELECT 1 FROM employee_availability a WHERE a.employee_id = e.id)
         ORDER BY e.last_name, e.first_name'
    );
    $stmt->execute(['jezyk' => $id_jezyka]);

    return $stmt->fetchAll();
}

function zapis_w_przyszlosci(string $data, string $godzina_start): bool
{
    $dzisiaj = date('Y-m-d');

    return $data > $dzisiaj || ($data === $dzisiaj && substr($godzina_start, 0, 5) > date('H:i'));
}

function mozna_anulowac(array $zapis): bool
{
    $otwarty = $zapis['status'] === 'pending' || $zapis['status'] === 'confirmed';

    return $otwarty && zapis_w_przyszlosci($zapis['reservation_date'], $zapis['start_time']);
}

function wolne_godziny(PDO $pdo, int $id_lektora, string $data, int $czas_trwania): array
{
    if (!poprawna_data($data)) {
        return [];
    }

    $dzisiaj       = date('Y-m-d');
    $ostatni_dzien = date('Y-m-d', strtotime('+' . MAX_DNI_NAPRZOD . ' days'));

    if ($data < $dzisiaj || $data > $ostatni_dzien) {
        return [];
    }

    $dzien_tygodnia = (int) date('N', strtotime($data));

    $stmt = $pdo->prepare(
        'SELECT start_time, end_time
         FROM employee_availability
         WHERE employee_id = :lektor AND day_of_week = :dzien
         ORDER BY start_time'
    );
    $stmt->execute(['lektor' => $id_lektora, 'dzien' => $dzien_tygodnia]);
    $okna = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        "SELECT r.start_time, r.end_time
         FROM reservations r
         JOIN reservation_statuses st ON st.id = r.status_id
         WHERE r.employee_id = :lektor
           AND r.reservation_date = :data
           AND st.name <> 'cancelled'"
    );
    $stmt->execute(['lektor' => $id_lektora, 'data' => $data]);
    $zajete = $stmt->fetchAll();

    $teraz = ($data === $dzisiaj) ? na_minuty(date('H:i')) : -1;

    $wolne = [];

    foreach ($okna as $okno) {
        $okno_start  = na_minuty($okno['start_time']);
        $okno_koniec = na_minuty($okno['end_time']);

        for ($start = $okno_start; $start + $czas_trwania <= $okno_koniec; $start += KROK_MINUT) {
            $koniec = $start + $czas_trwania;

            if ($start <= $teraz) {
                continue;
            }

            $wolny = true;
            foreach ($zajete as $zapis) {
                if (terminy_sie_nakladaja($start, $koniec, na_minuty($zapis['start_time']), na_minuty($zapis['end_time']))) {
                    $wolny = false;
                    break;
                }
            }

            if ($wolny) {
                $wolne[] = z_minut($start);
            }
        }
    }

    return $wolne;
}

function zapisz_rezerwacje(PDO $pdo, int $id_ucznia, int $id_lekcji, int $id_lektora, string $data, string $godzina): ?string
{
    if (!poprawna_data($data) || !poprawna_godzina($godzina)) {
        return 'Podano nieprawidłową datę lub godzinę.';
    }

    $lekcja = pobierz_lekcje($pdo, $id_lekcji);
    if (!$lekcja) {
        return 'Ta lekcja nie jest już dostępna.';
    }

    $lektor_ok = false;
    foreach (lektorzy_lekcji($pdo, $id_lekcji) as $lektor) {
        if ((int) $lektor['id'] === $id_lektora) {
            $lektor_ok = true;
        }
    }
    if (!$lektor_ok) {
        return 'Ten lektor nie prowadzi już tej lekcji.';
    }

    $id_statusu = id_statusu($pdo, 'pending');
    if ($id_statusu === 0) {
        return 'Nie udało się zapisać rezerwacji. Spróbuj ponownie później.';
    }

    $czas   = (int) $lekcja['duration_minutes'];
    $koniec = z_minut(na_minuty($godzina) + $czas);

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('SELECT id FROM employees WHERE id = :id FOR UPDATE');
        $stmt->execute(['id' => $id_lektora]);

        if (!in_array($godzina, wolne_godziny($pdo, $id_lektora, $data, $czas), true)) {
            $pdo->rollBack();

            return 'Ten termin jest już niedostępny. Wybierz inny.';
        }

        $stmt = $pdo->prepare(
            'INSERT INTO reservations
                (client_id, employee_id, service_id, status_id, reservation_date, start_time, end_time, price_at_booking)
             VALUES
                (:uczen, :lektor, :lekcja, :status, :data, :poczatek, :koniec, :cena)'
        );
        $stmt->execute([
            'uczen'    => $id_ucznia,
            'lektor'   => $id_lektora,
            'lekcja'   => $id_lekcji,
            'status'   => $id_statusu,
            'data'     => $data,
            'poczatek' => $godzina,
            'koniec'   => $koniec,
            'cena'     => $lekcja['price'],
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log($e->getMessage());

        return 'Nie udało się zapisać rezerwacji. Spróbuj ponownie.';
    }

    return null;
}

function anuluj_zapis_ucznia(PDO $pdo, int $id_zapisu, int $id_ucznia): bool
{
    $stmt = $pdo->prepare(
        'UPDATE reservations
         SET status_id = :anulowana
         WHERE id = :id
           AND client_id = :uczen
           AND status_id IN (:oczekujaca, :potwierdzona)
           AND (reservation_date > :dzis1 OR (reservation_date = :dzis2 AND start_time > :teraz))'
    );
    $stmt->execute([
        'anulowana'    => id_statusu($pdo, 'cancelled'),
        'id'           => $id_zapisu,
        'uczen'        => $id_ucznia,
        'oczekujaca'   => id_statusu($pdo, 'pending'),
        'potwierdzona' => id_statusu($pdo, 'confirmed'),
        'dzis1'        => date('Y-m-d'),
        'dzis2'        => date('Y-m-d'),
        'teraz'        => date('H:i:s'),
    ]);

    return $stmt->rowCount() === 1;
}

function zmien_status_wizyty(PDO $pdo, int $id_zapisu, int $id_lektora, string $akcja): ?string
{
    $nowe_statusy = [
        'potwierdz' => 'confirmed',
        'zrealizuj' => 'completed',
        'anuluj'    => 'cancelled',
    ];

    if (!isset($nowe_statusy[$akcja])) {
        return 'Nieznana akcja.';
    }
    $nowy_status = $nowe_statusy[$akcja];

    $stmt = $pdo->prepare(
        'SELECT r.id, r.reservation_date, r.start_time, st.name AS status
         FROM reservations r
         JOIN reservation_statuses st ON st.id = r.status_id
         WHERE r.id = :id AND r.employee_id = :lektor'
    );
    $stmt->execute(['id' => $id_zapisu, 'lektor' => $id_lektora]);
    $zapis = $stmt->fetch();

    if (!$zapis) {
        return 'Nie masz dostępu do tej wizyty.';
    }

    if ($zapis['status'] !== 'pending' && $zapis['status'] !== 'confirmed') {
        return 'Status tej wizyty jest już zamknięty i nie można go zmienić.';
    }

    if ($nowy_status === 'confirmed' && $zapis['status'] === 'confirmed') {
        return 'Ta wizyta jest już potwierdzona.';
    }

    if ($nowy_status === 'completed' && zapis_w_przyszlosci($zapis['reservation_date'], $zapis['start_time'])) {
        return 'Nie można oznaczyć jako zrealizowanej lekcji, która jeszcze się nie odbyła.';
    }

    $stmt = $pdo->prepare('UPDATE reservations SET status_id = :status WHERE id = :id AND employee_id = :lektor');
    $stmt->execute([
        'status' => id_statusu($pdo, $nowy_status),
        'id'     => $id_zapisu,
        'lektor' => $id_lektora,
    ]);

    return null;
}
