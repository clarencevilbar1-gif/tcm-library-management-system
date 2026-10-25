-- =====================================================================
-- Students table: add an E-mail column (contact collected on the Borrow page)
--
-- You normally do NOT need to run this: the system adds the column by itself the first
-- time it is needed. It is here only in case you prefer to do it by hand.
-- It is safe to run twice. (XAMPP uses MariaDB, which understands "IF NOT EXISTS".)
-- phpMyAdmin -> law_library_db -> SQL tab -> paste -> Go.
-- =====================================================================

USE law_library_db;

ALTER TABLE students ADD COLUMN IF NOT EXISTS email VARCHAR(150) NULL AFTER contact_number;

DESCRIBE students;
