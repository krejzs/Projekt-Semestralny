# Szkoła Językowa — baza danych

Baza danych (MySQL/MariaDB, InnoDB) obsługująca szkołę językową: nauczycieli, uczniów, kursy, harmonogram zajęć, zapisy, płatności i obecności.

## Struktura

- **languages** — słownik języków
- **levels** — poziomy zaawansowania (A1–C1)
- **teachers** — nauczyciele
- **students** — uczniowie
- **classrooms** — sale lekcyjne
- **teacher_languages** — kwalifikacje nauczycieli (N:M nauczyciele ↔ języki)
- **courses** — kursy (język + poziom + nauczyciel + termin + cena)
- **lessons** — harmonogram konkretnych zajęć danego kursu
- **enrollment_statuses** — słownik statusów zapisu
- **enrollments** — zapisy uczniów na kursy (N:M uczniowie ↔ kursy)
- **payment_methods** — słownik metod płatności
- **payments** — płatności za kurs
- **attendance** — obecności uczniów na lekcjach

Pełny diagram relacji znajduje się w `database/szkola_jezykowa_ERD.md`.

## Uruchomienie

bash 
```
mysql -u <user> -p <database_name> < database/szkola_jezykowa_schema.sql
```

css/         - style
database/    - schemat bazy danych i diagram ERD
images/      - grafiki
jss/         - skrypty JS
public/      - pliki publiczne
README.md

Skrypt tworzy wszystkie tabele wraz z kluczami obcymi, ograniczeniami (`CHECK`, `UNIQUE`) oraz przykładowymi danymi testowymi.
