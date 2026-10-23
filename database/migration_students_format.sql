-- =====================================================================
-- Students table: add Gender and Year Level (for the registrar's file layout)
--
--   * NEW gender      Male / Female
--   * NEW year_level  1st Year ... 4th Year
--   * name gets more room (it is now written "Last, First M. Suffix")
--
-- Nothing else changes: student_no, course, contact_number, birthdate, is_active
-- and every borrowing record stay exactly as they are. It is safe to run twice.
--
-- BEFORE RUNNING: in phpMyAdmin select law_library_db -> Export -> Go (keep that file as a backup).
-- Run once: phpMyAdmin -> law_library_db -> SQL tab -> paste -> Go.
-- (XAMPP uses MariaDB, which understands "IF NOT EXISTS" below.)
-- =====================================================================

USE law_library_db;

ALTER TABLE students ADD COLUMN IF NOT EXISTS gender     VARCHAR(20) NULL AFTER name;
ALTER TABLE students ADD COLUMN IF NOT EXISTS year_level VARCHAR(30) NULL AFTER course;
ALTER TABLE students MODIFY COLUMN name VARCHAR(150) NOT NULL;

-- Check the result: student_no, name, gender, course, year_level, birthdate, contact_number, is_active
DESCRIBE students;
