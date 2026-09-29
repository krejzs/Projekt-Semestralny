# Diagram ERD — Szkoła językowa

```mermaid
erDiagram
    TEACHERS ||--o{ TEACHER_LANGUAGES : "posiada kwalifikacje"
    LANGUAGES ||--o{ TEACHER_LANGUAGES : "jest nauczany przez"
    TEACHERS ||--o{ COURSES : "prowadzi"
    LANGUAGES ||--o{ COURSES : "dotyczy"
    LEVELS ||--o{ COURSES : "określa poziom"
    COURSES ||--o{ LESSONS : "ma harmonogram"
    CLASSROOMS ||--o{ LESSONS : "odbywa się w"
    STUDENTS ||--o{ ENROLLMENTS : "zapisuje się"
    COURSES ||--o{ ENROLLMENTS : "przyjmuje zapisy"
    ENROLLMENT_STATUSES ||--o{ ENROLLMENTS : "ma status"
    ENROLLMENTS ||--o{ PAYMENTS : "generuje"
    PAYMENT_METHODS ||--o{ PAYMENTS : "sposobem"
    ENROLLMENTS ||--o{ ATTENDANCE : "ma obecności"
    LESSONS ||--o{ ATTENDANCE : "rejestruje obecność"

    LANGUAGES {
        int id PK
        string name UK
    }

    LEVELS {
        int id PK
        string code UK
        string description
    }

    TEACHERS {
        int id PK
        string first_name
        string last_name
        string email UK
        string phone
        date hire_date
    }

    STUDENTS {
        int id PK
        string first_name
        string last_name
        string email UK
        string phone
        date birth_date
        datetime registered_at
    }

    CLASSROOMS {
        int id PK
        string name UK
        int capacity
    }

    TEACHER_LANGUAGES {
        int teacher_id PK, FK
        int language_id PK, FK
    }

    COURSES {
        int id PK
        string name
        int language_id FK
        int level_id FK
        int teacher_id FK
        date start_date
        date end_date
        decimal price
        int max_participants
    }

    LESSONS {
        int id PK
        int course_id FK
        int classroom_id FK
        date lesson_date
        time start_time
        time end_time
    }

    ENROLLMENT_STATUSES {
        int id PK
        string name UK
    }

    ENROLLMENTS {
        int id PK
        int student_id FK
        int course_id FK
        int status_id FK
        datetime enrolled_at
    }

    PAYMENT_METHODS {
        int id PK
        string name UK
    }

    PAYMENTS {
        int id PK
        int enrollment_id FK
        int payment_method_id FK
        decimal amount
        datetime payment_date
    }

    ATTENDANCE {
        int id PK
        int enrollment_id FK
        int lesson_id FK
        boolean present
    }
```

**Relacje:**
- `teachers` N:M `languages` (przez `teacher_languages`) — nauczyciel może uczyć wielu języków, język może być nauczany przez wielu nauczycieli
- `teachers` 1:N `courses` — jeden nauczyciel prowadzi wiele kursów
- `languages` 1:N `courses`, `levels` 1:N `courses` — kurs dotyczy jednego języka i jednego poziomu
- `courses` 1:N `lessons` — kurs ma wiele terminów zajęć
- `classrooms` 1:N `lessons` — sala ma wiele lekcji (ale nie w tym samym czasie — pilnuje tego `UNIQUE`)
- `students` N:M `courses` (przez `enrollments`) — uczeń zapisuje się na wiele kursów, kurs ma wielu uczniów
- `enrollments` 1:N `payments` — jeden zapis może mieć wiele wpłat (np. raty)
- `enrollments` N:M `lessons` (przez `attendance`) — obecność ucznia na konkretnej lekcji w ramach jego zapisu
