# Szkoła Językowa — baza danych

Baza danych (MySQL/MariaDB, InnoDB) obsługująca szkołę językową: nauczycieli, uczniów, kursy, harmonogram zajęć, zapisy, płatności i obecności.

**Dla każdego (bez logowania):** `Oferta lekcji` - lista aktywnych lekcji z filtrem po języku, szczegóły lekcji (opis, język, cena, czas, godziny pracy lektorów).

**Uczeń (klient):**
- rezerwuje lekcję w krokach: lekcja -> lektor -> dzień -> godzina -> potwierdzenie,
- widzi tylko wolne godziny (uwzględniamy godziny pracy lektora, czas trwania lekcji i zajęte terminy),
- w panelu "Moje konto" ma nadchodzące lekcje i historię (zakończone / anulowane),
- może anulować tylko własną, przyszłą lekcję.

**Lektor (pracownik):** panel z zakładkami "Dzisiaj", "Nadchodzące", "Historia", szczegóły wizyty i zmiana statusu (potwierdź / zrealizowana / anuluj). Widzi tylko swoje wizyty.

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
LOGIN DO ADMINA <br>
email: admin@admin.pl <br>
hasło ADMIN123 <br>
Link do strony: marcin798.smallhost.pl


### Kolejność wgrywania bazy
1. `schema.sql`
2. `baza/01_rozszerzenie_logowania.sql`
3. `baza/02_etap3_panel_admina.sql`
4. `baza/04_etap4_rezerwacje.sql`
