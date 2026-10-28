# Online Government Internship/Attachment Application System

A web application that lets university and college students in Kenya apply for internship and attachment places in government Ministries. Institutions verify their own students, Ministries post vacancies by county, and the system places matching students automatically, first come first served, with the Ministry officer approving each placement.

Final-year project, Bachelor of Business and Information Technology, Mount Kenya University.

## The problem it solves

- Students crowd into a few large towns looking for attachment places.
- Applications are made on paper, office by office, and unsuccessful students are rarely told.
- Institution coordinators cannot easily see where their students are placed, which makes assessment hard.
- Students on newer or specialised courses cannot tell which Ministry or office suits them.

## How it works

1. A student **registers** (institution, course, area of interest, preferred county, period of 3, 6 or 12 months) and **uploads four documents**: insurance cover, recommendation letter, transcript and CV.
2. The **coordinator of the student's own institution** verifies the student, or rejects with a reason.
3. **Ministry officers** post vacancies by county, area, number of slots and duration. The **administrator** opens application windows.
4. A verified student **applies**. Every application gets a **match score** (area of interest 50, county 30, period 20 or 10).
5. If the student's area of interest matches, the student is **placed automatically, first come first served**. The placement is pending until the officer **approves** it, or **declines** it with a reason (which frees the slot).
6. Other applicants wait for the officer, who can place or reject them.
7. Coordinators see where their students are placed (with the supervisor's contact) and **record assessments**.
8. The administrator gets **reports** (per county, Ministry and institution; open against filled slots), CSV export, a print view and an **audit log**.

## Roles

| Role | Main tasks |
|---|---|
| Student | Register, upload documents, browse and apply, track applications and placement |
| Institution coordinator | Verify students, see placed students, record assessments |
| Ministry officer | Post vacancies, approve or decline placements, decide on waiting applicants |
| Administrator | Application windows, accounts, institutions and Ministries, reports, audit log |

## Technology

- PHP 8.1 or later (developed on 8.5), plain PHP with a small controller/model/view structure and no framework
- MariaDB 10.4 or later or MySQL 8 (InnoDB, utf8mb4), accessed with PDO prepared statements
- HTML5, CSS3, no JavaScript framework
- Runs on **Fedora 44** (PHP built-in server or Apache) and **Windows 10/11 with XAMPP**

## Quick start on Fedora 44

```bash
# 1. software
sudo dnf install php-cli php-pdo php-mysqlnd php-mbstring php-xml mariadb-server git
sudo systemctl enable --now mariadb

# 2. database and user (choose your own password)
sudo mariadb <<'SQL'
CREATE DATABASE IF NOT EXISTS internship_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'internship_user'@'localhost' IDENTIFIED BY 'CHOOSE_A_STRONG_PASSWORD';
GRANT ALL PRIVILEGES ON internship_db.* TO 'internship_user'@'localhost';
FLUSH PRIVILEGES;
SQL

# 3. configuration (set the database password)
cp app/config/config.sample.php app/config/config.php
nano app/config/config.php

# 4. tables and starting data (47 counties, sample institutions and Ministries)
mariadb -u internship_user -p internship_db < database/schema.sql
mariadb -u internship_user -p internship_db < database/seed.sql

# 5. first administrator, then run
php bin/create_user.php admin admin you@example.com "System Administrator"
php -S localhost:8000 -t public
```

Open <http://localhost:8000>.

If `dnf` picks Oracle's MySQL packages and stops with a signature error, install with `sudo dnf install --disablerepo='mysql*' mariadb-server`.

## Quick start on Windows (XAMPP)

1. Install XAMPP with PHP 8.1 or newer and start **Apache** and **MySQL**.
2. Copy this folder to `C:\xampp\htdocs\internship-system`.
3. In phpMyAdmin create a database `internship_db` (collation `utf8mb4_unicode_ci`) and import `database/schema.sql`, then `database/seed.sql`.
4. Copy `app/config/config.sample.php` to `app/config/config.php` and set `base_url` to `/internship-system/public`, `user` to `root` and `pass` to an empty string.
5. In Command Prompt:
   ```
   cd C:\xampp\htdocs\internship-system
   C:\xampp\php\php.exe bin\create_user.php admin admin you@example.com "System Administrator"
   ```
6. Open <http://localhost/internship-system/public/>.

Full instructions, including Apache on Fedora with SELinux, backup and troubleshooting, are in the **Installation Guide**.

## Demonstration data

```bash
php bin/demo_seed.php            # create a coordinator, 3 Ministry officers, 8 students, 8 vacancies
php bin/demo_seed.php --reset    # rebuild
php bin/demo_seed.php --remove   # delete
php bin/demo_check.php --fix     # verify or repair the demo passwords
```

Every demo account uses the password `Demo@12345`. Log in with the **username**, for example:

| Username | Role |
|---|---|
| `demo_coord` | Coordinator, Mount Kenya University |
| `demo_moe`, `demo_ict`, `demo_moh` | Ministry officers |
| `demo_amina` | Student with an approved placement |
| `demo_brian`, `demo_halima` | Students with a placement awaiting approval |
| `demo_cynthia` | Student whose application waits for the officer |
| `demo_daniel`, `demo_esther`, `demo_faith`, `demo_george` | Students awaiting, or rejected at, verification |

The script only runs when `env` in `config.php` is `development`. Remove the demo data before real use.

## Configuration

Everything that differs between machines is in `app/config/config.php` (not committed; copy it from `config.sample.php`).

| Setting | Meaning |
|---|---|
| `app.base_url` | Path before every link: empty for `php -S` or a `public` web root, `/internship-system/public` for XAMPP |
| `app.env` | `development` shows errors and allows demo data; `production` hides them |
| `db.*` | Host, port, name, user and password of the database |
| `placement.auto_min_match` | Lowest match score for automatic placement (area of interest must also match). 50 means area only, 80 means area and county |
| `uploads.dir`, `uploads.max_size` | Where documents are kept (outside the web root) and the size limit (2 MB) |

## Project structure

```
public/            index.php (front controller) and assets/ (CSS)
app/config/        config.sample.php, config.php (local)
app/core/          bootstrap, router, auth, CSRF, database, helpers
app/models/        business rules: students, vacancies, applications and placements, reports
app/controllers/   one file per area (auth, student, coordinator, vacancy, application, ...)
app/views/         HTML templates
database/          schema.sql, seed.sql, migrations/
storage/uploads/   uploaded documents (random names, outside the web root)
bin/               command-line tools: create_user.php, demo_seed.php, demo_check.php
tests/             automated test scripts
docs/              project documentation (Word files)
```

Pages are reached as `index.php?r=controller/action`, which works the same on PHP's built-in server and on Apache without URL rewriting.

## Security summary

Passwords hashed with `password_hash`; CSRF token on every form; PDO prepared statements; escaped output; role check on every page and records filtered by the user's own institution or Ministry; session cookie HttpOnly and SameSite with the id regenerated at login; login lockout after five failures; uploads limited to PDF, DOC and DOCX up to 2 MB, content-type checked, stored under random names outside the web root and served only through a permission check; CSV exports protected against spreadsheet formula injection; audit log of key actions.

## Running the tests

The tests drive the running application over HTTP with `curl` and check the database. Start the server on the address they expect, then run the suites from a second terminal (Git Bash on Windows):

```bash
php -S 127.0.0.1:8000 -t public            # terminal 1

bash tests/smoke.sh                         # terminal 2: access and authentication
bash tests/smoke_module2.sh                 # profile, documents, verification
bash tests/smoke_module3.sh                 # windows and vacancies
bash tests/smoke_auto_placement.sh          # automatic placement and approval
bash tests/smoke_module6.sh                 # placed students, assessments, administration
bash tests/smoke_module7.sh                 # reports, CSV and audit log
bash tests/smoke_demo.sh                    # demo data (leaves it in place)
php tests/csv_test.php                      # CSV formula protection
```

Each script creates its own temporary users and data, prints `Result: N passed, M failed`, and removes what it created. Some checks assume particular conditions, such as no active application window; those are skipped with a message.

## Documentation

| File | Content |
|---|---|
| `Chapter4_System_Design.docx` | Architecture, use cases, data flow diagrams, ERD and data dictionary, placement procedure, state and sequence diagrams, interface, security and portability design |
| `Chapter5_Implementation_and_Testing.docx` | Implementation by module, code extracts, problems and solutions, test suites and cases, security and portability testing, evaluation against the objectives, limitations |
| `User_Manual.docx` | Step-by-step guides for each role, troubleshooting, glossary |
| `Installation_Guide.docx` | Fedora 44 and Windows (XAMPP) installation, configuration, backup, hardening, troubleshooting |

## Known limitations

Notifications are shown in the application only (no email or SMS); there is no self-service password reset; the login lockout is per browser session; automatic placement is eligibility-based first come first served, not ranked on grades; a student whose placement is declined must apply again elsewhere.

## Author

[Your full name], [registration number], Bachelor of Business and Information Technology, Mount Kenya University. Supervisor: [supervisor name].
