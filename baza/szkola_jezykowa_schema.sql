
-- SZKOŁA JĘZYKOWA
-- Silnik: MySQL / MariaDB (InnoDB).

DROP TABLE IF EXISTS attendance;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS enrollments;
DROP TABLE IF EXISTS enrollment_statuses;
DROP TABLE IF EXISTS lessons;
DROP TABLE IF EXISTS teacher_languages;
DROP TABLE IF EXISTS courses;
DROP TABLE IF EXISTS classrooms;
DROP TABLE IF EXISTS levels;
DROP TABLE IF EXISTS languages;
DROP TABLE IF EXISTS teachers;
DROP TABLE IF EXISTS students;
DROP TABLE IF EXISTS payment_methods;
DROP TABLE IF EXISTS reservations;
DROP TABLE IF EXISTS employee_availability;
DROP TABLE IF EXISTS employee_services;
DROP TABLE IF EXISTS services;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS employees;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS reservation_statuses;

CREATE TABLE languages (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(50) NOT NULL,
    CONSTRAINT uq_languages_name UNIQUE (name)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 2. POZIOMY ZAAWANSOWANIA (słownik, np. A1, A2, B1, B2, C1, C2)
-- ---------------------------------------------------------------------
CREATE TABLE levels (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    code            VARCHAR(10) NOT NULL,
    description     VARCHAR(100),
    CONSTRAINT uq_levels_code UNIQUE (code)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3. NAUCZYCIELE
-- ---------------------------------------------------------------------
CREATE TABLE teachers (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    first_name      VARCHAR(50)  NOT NULL,
    last_name       VARCHAR(50)  NOT NULL,
    email           VARCHAR(100) NOT NULL,
    phone           VARCHAR(20),
    hire_date       DATE NOT NULL,
    CONSTRAINT uq_teachers_email UNIQUE (email)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 4. UCZNIOWIE (słuchacze)
-- ---------------------------------------------------------------------
CREATE TABLE students (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    first_name      VARCHAR(50)  NOT NULL,
    last_name       VARCHAR(50)  NOT NULL,
    email           VARCHAR(100) NOT NULL,
    phone           VARCHAR(20),
    birth_date      DATE,
    registered_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_students_email UNIQUE (email)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 5. SALE LEKCYJNE
-- ---------------------------------------------------------------------
CREATE TABLE classrooms (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(30) NOT NULL,
    capacity        INT NOT NULL CHECK (capacity > 0),
    CONSTRAINT uq_classrooms_name UNIQUE (name)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 6. KWALIFIKACJE NAUCZYCIELI - relacja N:M (nauczyciel <-> język)
--    Jeden nauczyciel może uczyć wielu języków, jeden język może być
--    nauczany przez wielu nauczycieli.
-- ---------------------------------------------------------------------
CREATE TABLE teacher_languages (
    teacher_id      INT NOT NULL,
    language_id     INT NOT NULL,
    PRIMARY KEY (teacher_id, language_id),
    CONSTRAINT fk_tl_teacher
        FOREIGN KEY (teacher_id) REFERENCES teachers(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_tl_language
        FOREIGN KEY (language_id) REFERENCES languages(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 7. KURSY (grupy zajęciowe)
--    Kurs = konkretny język + poziom + prowadzący nauczyciel,
--    prowadzony w danym okresie czasu.
-- ---------------------------------------------------------------------
CREATE TABLE courses (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100) NOT NULL,
    language_id     INT NOT NULL,
    level_id        INT NOT NULL,
    teacher_id      INT NOT NULL,
    start_date      DATE NOT NULL,
    end_date        DATE NOT NULL,
    price           DECIMAL(8,2) NOT NULL CHECK (price >= 0),
    max_participants INT NOT NULL CHECK (max_participants > 0),
    CONSTRAINT chk_course_dates CHECK (end_date > start_date),
    CONSTRAINT fk_courses_language
        FOREIGN KEY (language_id) REFERENCES languages(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_courses_level
        FOREIGN KEY (level_id) REFERENCES levels(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_courses_teacher
        FOREIGN KEY (teacher_id) REFERENCES teachers(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 8. LEKCJE (harmonogram konkretnych zajęć danego kursu)
--    Wydzielone z kursu, żeby uniknąć powielania sali/godzin i pozwolić
--    kontrolować kolizje sal (jedna sala nie może mieć dwóch lekcji
--    w tym samym czasie).
-- ---------------------------------------------------------------------
CREATE TABLE lessons (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    course_id       INT NOT NULL,
    classroom_id    INT NOT NULL,
    lesson_date     DATE NOT NULL,
    start_time      TIME NOT NULL,
    end_time        TIME NOT NULL,
    CONSTRAINT chk_lesson_hours CHECK (end_time > start_time),
    CONSTRAINT fk_lessons_course
        FOREIGN KEY (course_id) REFERENCES courses(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_lessons_classroom
        FOREIGN KEY (classroom_id) REFERENCES classrooms(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    -- ta sama sala nie może mieć dwóch lekcji w tym samym terminie
    CONSTRAINT uq_classroom_slot UNIQUE (classroom_id, lesson_date, start_time)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 9. SŁOWNIK STATUSÓW ZAPISU NA KURS
-- ---------------------------------------------------------------------
CREATE TABLE enrollment_statuses (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(30) NOT NULL,
    CONSTRAINT uq_enrollment_status_name UNIQUE (name)
) ENGINE=InnoDB;

INSERT INTO enrollment_statuses (name) VALUES
    ('active'), ('completed'), ('cancelled'), ('pending_payment');

-- ---------------------------------------------------------------------
-- 10. ZAPISY UCZNIÓW NA KURSY
-- ---------------------------------------------------------------------
CREATE TABLE enrollments (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    student_id      INT NOT NULL,
    course_id       INT NOT NULL,
    status_id       INT NOT NULL DEFAULT 4,
    enrolled_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_enrollments_student
        FOREIGN KEY (student_id) REFERENCES students(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_enrollments_course
        FOREIGN KEY (course_id) REFERENCES courses(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_enrollments_status
        FOREIGN KEY (status_id) REFERENCES enrollment_statuses(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    -- uczeń nie może zapisać się na ten sam kurs dwa razy
    CONSTRAINT uq_student_course UNIQUE (student_id, course_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 11. METODY PŁATNOŚCI (słownik)
-- ---------------------------------------------------------------------
CREATE TABLE payment_methods (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(30) NOT NULL,
    CONSTRAINT uq_payment_method_name UNIQUE (name)
) ENGINE=InnoDB;

INSERT INTO payment_methods (name) VALUES
    ('cash'), ('card'), ('bank_transfer'), ('online');

-- ---------------------------------------------------------------------
-- 12. PŁATNOŚCI ZA KURS
-- ---------------------------------------------------------------------
CREATE TABLE payments (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    enrollment_id       INT NOT NULL,
    payment_method_id   INT NOT NULL,
    amount              DECIMAL(8,2) NOT NULL CHECK (amount > 0),
    payment_date        DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_payments_enrollment
        FOREIGN KEY (enrollment_id) REFERENCES enrollments(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_payments_method
        FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 13. OBECNOŚCI NA LEKCJACH
--     Łączy zapis ucznia na kurs (enrollment) z konkretną lekcją.
-- ---------------------------------------------------------------------
CREATE TABLE attendance (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    enrollment_id   INT NOT NULL,
    lesson_id       INT NOT NULL,
    present         BOOLEAN NOT NULL DEFAULT FALSE,
    CONSTRAINT fk_attendance_enrollment
        FOREIGN KEY (enrollment_id) REFERENCES enrollments(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_attendance_lesson
        FOREIGN KEY (lesson_id) REFERENCES lessons(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    -- jeden wpis obecności na daną lekcję dla danego zapisu
    CONSTRAINT uq_enrollment_lesson UNIQUE (enrollment_id, lesson_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 1. UŻYTKOWNICY (klienci)
-- ---------------------------------------------------------------------
CREATE TABLE users (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    first_name      VARCHAR(50)  NOT NULL,
    last_name       VARCHAR(50)  NOT NULL,
    email           VARCHAR(100) NOT NULL,
    password_hash   VARCHAR(255) NOT NULL,
    phone           VARCHAR(20),
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_users_email UNIQUE (email)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 2. PRACOWNICY
-- ---------------------------------------------------------------------
CREATE TABLE employees (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    first_name      VARCHAR(50)  NOT NULL,
    last_name       VARCHAR(50)  NOT NULL,
    email           VARCHAR(100) NOT NULL,
    phone           VARCHAR(20),
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_employees_email UNIQUE (email)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3. KATEGORIE USŁUG
-- ---------------------------------------------------------------------
CREATE TABLE categories (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(80) NOT NULL,
    CONSTRAINT uq_categories_name UNIQUE (name)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 4. USŁUGI
-- ---------------------------------------------------------------------
CREATE TABLE services (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    category_id     INT NOT NULL,
    name            VARCHAR(100) NOT NULL,
    description     TEXT,
    price           DECIMAL(8,2) NOT NULL CHECK (price >= 0),
    duration_minutes INT NOT NULL CHECK (duration_minutes > 0),
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_services_category
        FOREIGN KEY (category_id) REFERENCES categories(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 5. RELACJA N:M PRACOWNICY <-> USŁUGI
--    (który pracownik jaką usługę potrafi/może wykonać)
-- ---------------------------------------------------------------------
CREATE TABLE employee_services (
    employee_id     INT NOT NULL,
    service_id      INT NOT NULL,
    PRIMARY KEY (employee_id, service_id),
    CONSTRAINT fk_es_employee
        FOREIGN KEY (employee_id) REFERENCES employees(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_es_service
        FOREIGN KEY (service_id) REFERENCES services(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 6. DOSTĘPNOŚĆ PRACOWNIKÓW
--    (cykliczne okna dostępności w tygodniu)
-- ---------------------------------------------------------------------
CREATE TABLE employee_availability (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    employee_id     INT NOT NULL,
    day_of_week     TINYINT NOT NULL CHECK (day_of_week BETWEEN 1 AND 7), -- 1=pon ... 7=nd
    start_time      TIME NOT NULL,
    end_time        TIME NOT NULL,
    CONSTRAINT chk_availability_hours CHECK (end_time > start_time),
    CONSTRAINT fk_availability_employee
        FOREIGN KEY (employee_id) REFERENCES employees(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 7. SŁOWNIK STATUSÓW REZERWACJI
-- ---------------------------------------------------------------------
CREATE TABLE reservation_statuses (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(30) NOT NULL,
    CONSTRAINT uq_status_name UNIQUE (name)
) ENGINE=InnoDB;

INSERT INTO reservation_statuses (name) VALUES
    ('pending'), ('confirmed'), ('cancelled'), ('completed');

-- ---------------------------------------------------------------------
-- 8. REZERWACJE (łączy klienta, pracownika i usługę)
-- ---------------------------------------------------------------------
CREATE TABLE reservations (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    client_id       INT NOT NULL,
    employee_id     INT NOT NULL,
    service_id      INT NOT NULL,
    status_id       INT NOT NULL DEFAULT 1,
    reservation_date DATE NOT NULL,
    start_time      TIME NOT NULL,
    end_time        TIME NOT NULL,
    price_at_booking DECIMAL(8,2) NOT NULL, -- świadoma denormalizacja:
                                             -- zachowuje cenę usługi z chwili rezerwacji,
                                             -- niezależnie od późniejszych zmian cennika
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_reservation_hours CHECK (end_time > start_time),
    CONSTRAINT fk_res_client
        FOREIGN KEY (client_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_res_employee
        FOREIGN KEY (employee_id) REFERENCES employees(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_res_service
        FOREIGN KEY (service_id) REFERENCES services(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_res_status
        FOREIGN KEY (status_id) REFERENCES reservation_statuses(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    -- Zapobiega podwójnej rezerwacji tego samego pracownika w tym samym terminie
    CONSTRAINT uq_employee_slot UNIQUE (employee_id, reservation_date, start_time)
) ENGINE=InnoDB;

-- =====================================================================
-- PRZYKŁADOWE DANE TESTOWE
-- =====================================================================
INSERT INTO languages (name) VALUES ('Angielski'), ('Niemiecki'), ('Hiszpański');

INSERT INTO levels (code, description) VALUES
    ('A1', 'Początkujący'), ('A2', 'Podstawowy'), ('B1', 'Średnio zaawansowany'),
    ('B2', 'Wyżej średnio zaawansowany'), ('C1', 'Zaawansowany');

INSERT INTO teachers (first_name, last_name, email, phone, hire_date) VALUES
    ('Katarzyna', 'Wiśniewska', 'k.wisniewska@szkolajezykowa.pl', '600111222', '2022-09-01'),
    ('John', 'Smith', 'j.smith@szkolajezykowa.pl', '600333444', '2023-02-15');

INSERT INTO teacher_languages (teacher_id, language_id) VALUES (1, 1), (1, 2), (2, 1);

INSERT INTO students (first_name, last_name, email, phone, birth_date) VALUES
    ('Marta', 'Zielińska', 'marta.zielinska@example.com', '700100200', '2000-05-12'),
    ('Tomasz', 'Kowalczyk', 'tomasz.kowalczyk@example.com', '700300400', '1998-11-03');

INSERT INTO classrooms (name, capacity) VALUES ('Sala 1', 12), ('Sala 2', 8);

INSERT INTO courses (name, language_id, level_id, teacher_id, start_date, end_date, price, max_participants) VALUES
    ('Angielski B1 - grupa wieczorowa', 1, 3, 2, '2026-10-01', '2027-01-31', 1200.00, 12);

INSERT INTO lessons (course_id, classroom_id, lesson_date, start_time, end_time) VALUES
    (1, 1, '2026-10-01', '18:00:00', '19:30:00'),
    (1, 1, '2026-10-08', '18:00:00', '19:30:00');

INSERT INTO enrollments (student_id, course_id, status_id) VALUES (1, 1, 1), (2, 1, 1);

INSERT INTO payments (enrollment_id, payment_method_id, amount) VALUES
    (1, 3, 1200.00), (2, 2, 1200.00);

INSERT INTO attendance (enrollment_id, lesson_id, present) VALUES
    (1, 1, TRUE), (2, 1, TRUE), (1, 2, FALSE), (2, 2, TRUE);

INSERT INTO categories (name) VALUES ('Fryzjerstwo'), ('Kosmetyka'), ('Masaż');

INSERT INTO users (first_name, last_name, email, password_hash, phone) VALUES
    ('Anna', 'Kowalska', 'anna.kowalska@example.com', 'hash123', '600100200');

INSERT INTO employees (first_name, last_name, email, phone) VALUES
    ('Piotr', 'Nowak', 'piotr.nowak@example.com', '600300400');

INSERT INTO services (category_id, name, description, price, duration_minutes) VALUES
    (1, 'Strzyżenie damskie', 'Mycie, strzyżenie, stylizacja', 80.00, 45),
    (3, 'Masaż relaksacyjny', 'Masaż całego ciała', 150.00, 60);

INSERT INTO employee_services (employee_id, service_id) VALUES (1, 1), (1, 2);

INSERT INTO employee_availability (employee_id, day_of_week, start_time, end_time) VALUES
    (1, 1, '09:00:00', '17:00:00'),
    (1, 2, '09:00:00', '17:00:00');

INSERT INTO reservations (client_id, employee_id, service_id, status_id, reservation_date, start_time, end_time, price_at_booking)
VALUES (1, 1, 1, 2, '2026-09-20', '10:00:00', '10:45:00', 80.00);

