-- Online Government Internship/Attachment Application System
-- Works on MariaDB 10.4+ (XAMPP) and MariaDB 11 / MySQL 8. Re-running resets all data.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS audit_log, notifications, assessments, placements, applications,
  vacancies, application_windows, documents, students, users, ministries, institutions, counties;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE counties (
  id INT UNSIGNED NOT NULL PRIMARY KEY,
  name VARCHAR(60) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE institutions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL UNIQUE,
  code VARCHAR(20) NULL,
  county_id INT UNSIGNED NULL,
  contact_email VARCHAR(150) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_inst_county FOREIGN KEY (county_id) REFERENCES counties(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ministries (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL UNIQUE,
  abbreviation VARCHAR(20) NULL,
  contact_email VARCHAR(150) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('student','coordinator','ministry','admin') NOT NULL,
  full_name VARCHAR(120) NOT NULL,
  phone VARCHAR(25) NULL,
  institution_id INT UNSIGNED NULL,
  ministry_id INT UNSIGNED NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_users_role (role),
  CONSTRAINT fk_users_inst FOREIGN KEY (institution_id) REFERENCES institutions(id) ON DELETE RESTRICT,
  CONSTRAINT fk_users_min FOREIGN KEY (ministry_id) REFERENCES ministries(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE students (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL UNIQUE,
  institution_id INT UNSIGNED NOT NULL,
  reg_no VARCHAR(40) NOT NULL,
  course VARCHAR(150) NOT NULL,
  area_of_interest VARCHAR(120) NOT NULL,
  preferred_county_id INT UNSIGNED NOT NULL,
  period_months TINYINT UNSIGNED NOT NULL,
  available_from DATE NULL,
  available_to DATE NULL,
  verification_status ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
  verification_reason VARCHAR(255) NULL,
  verified_by INT UNSIGNED NULL,
  verified_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_student_reg (institution_id, reg_no),
  KEY idx_students_status (verification_status),
  CONSTRAINT fk_stu_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_stu_inst FOREIGN KEY (institution_id) REFERENCES institutions(id) ON DELETE RESTRICT,
  CONSTRAINT fk_stu_county FOREIGN KEY (preferred_county_id) REFERENCES counties(id) ON DELETE RESTRICT,
  CONSTRAINT fk_stu_verifier FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE documents (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  student_id INT UNSIGNED NOT NULL,
  doc_type ENUM('insurance','recommendation_letter','transcript','cv') NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_name VARCHAR(64) NOT NULL UNIQUE,
  mime_type VARCHAR(100) NOT NULL,
  size_bytes INT UNSIGNED NOT NULL,
  uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_doc_type (student_id, doc_type),
  CONSTRAINT fk_doc_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE application_windows (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  opens_on DATE NOT NULL,
  closes_on DATE NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_win_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE vacancies (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ministry_id INT UNSIGNED NOT NULL,
  posted_by INT UNSIGNED NULL,
  title VARCHAR(150) NOT NULL,
  department VARCHAR(120) NOT NULL,
  office_location VARCHAR(150) NOT NULL,
  county_id INT UNSIGNED NOT NULL,
  course_area VARCHAR(150) NOT NULL,
  description TEXT NULL,
  slots_total SMALLINT UNSIGNED NOT NULL,
  slots_filled SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  duration_months TINYINT UNSIGNED NOT NULL,
  opens_on DATE NOT NULL,
  closes_on DATE NOT NULL,
  status ENUM('open','closed','filled') NOT NULL DEFAULT 'open',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_vac_county_status (county_id, status),
  KEY idx_vac_course (course_area),
  CONSTRAINT fk_vac_min FOREIGN KEY (ministry_id) REFERENCES ministries(id) ON DELETE RESTRICT,
  CONSTRAINT fk_vac_user FOREIGN KEY (posted_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_vac_county FOREIGN KEY (county_id) REFERENCES counties(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE applications (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  student_id INT UNSIGNED NOT NULL,
  vacancy_id INT UNSIGNED NOT NULL,
  status ENUM('applied','shortlisted','placed','unsuccessful','withdrawn') NOT NULL DEFAULT 'applied',
  match_score TINYINT UNSIGNED NULL,
  decision_note VARCHAR(255) NULL,
  decided_by INT UNSIGNED NULL,
  decided_at DATETIME NULL,
  applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_app (student_id, vacancy_id),
  KEY idx_app_status (status),
  CONSTRAINT fk_app_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  CONSTRAINT fk_app_vac FOREIGN KEY (vacancy_id) REFERENCES vacancies(id) ON DELETE RESTRICT,
  CONSTRAINT fk_app_decider FOREIGN KEY (decided_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE placements (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  application_id INT UNSIGNED NOT NULL UNIQUE,
  student_id INT UNSIGNED NOT NULL UNIQUE,
  vacancy_id INT UNSIGNED NOT NULL,
  start_date DATE NULL,
  end_date DATE NULL,
  supervisor_name VARCHAR(120) NULL,
  supervisor_phone VARCHAR(25) NULL,
  supervisor_email VARCHAR(150) NULL,
  status ENUM('pending','confirmed','ongoing','completed') NOT NULL DEFAULT 'confirmed',
  confirmed_by INT UNSIGNED NULL,
  confirmed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pl_app FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE RESTRICT,
  CONSTRAINT fk_pl_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE RESTRICT,
  CONSTRAINT fk_pl_vac FOREIGN KEY (vacancy_id) REFERENCES vacancies(id) ON DELETE RESTRICT,
  CONSTRAINT fk_pl_user FOREIGN KEY (confirmed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE assessments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  placement_id INT UNSIGNED NOT NULL,
  coordinator_id INT UNSIGNED NULL,
  assessed_on DATE NOT NULL,
  score TINYINT UNSIGNED NULL,
  comments TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_as_pl FOREIGN KEY (placement_id) REFERENCES placements(id) ON DELETE CASCADE,
  CONSTRAINT fk_as_user FOREIGN KEY (coordinator_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  type VARCHAR(30) NOT NULL,
  title VARCHAR(150) NOT NULL,
  message TEXT NOT NULL,
  link VARCHAR(255) NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_notif_user (user_id, is_read),
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_log (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  action VARCHAR(50) NOT NULL,
  entity_type VARCHAR(40) NOT NULL,
  entity_id INT UNSIGNED NULL,
  details VARCHAR(500) NULL,
  ip_address VARCHAR(45) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_audit_entity (entity_type, entity_id),
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
