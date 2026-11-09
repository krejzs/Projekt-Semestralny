-- =====================================================================
-- ETAP 4 - zmiany w bazie pod rezerwowanie lekcji
-- Wgrac PO 02_etap3_panel_admina.sql (kazdy plik tylko raz!)
-- =====================================================================

-- Indeks pod najczestsze zapytanie: "zapisy tego lektora w tym dniu".
-- Uzywamy go przy liczeniu wolnych godzin i sprawdzaniu konfliktow terminow.
-- Musi powstac PRZED usunieciem starego klucza, bo klucz obcy employee_id
-- potrzebuje jakiegos indeksu na tej kolumnie.
CREATE INDEX idx_reservations_employee_date ON reservations (employee_id, reservation_date);

-- Stary klucz UNIQUE (lektor, data, godzina) blokowal ponowne zapisanie sie
-- na termin, ktory wczesniej zostal anulowany (anulowany zapis zostaje w bazie
-- ze zmienionym statusem). Konflikty terminow sprawdzamy teraz w kodzie
-- (pomoce/rezerwacje.php), z blokada wiersza lektora.
ALTER TABLE reservations DROP INDEX uq_employee_slot;
