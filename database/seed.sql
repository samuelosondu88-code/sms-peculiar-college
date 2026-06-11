-- Seed Data for SMS Peculiar International College
-- Password for all accounts: Password@123 (bcrypt hash)

INSERT INTO academic_sessions (session_name, start_date, end_date, is_current, status) VALUES
('2025/2026', '2025-09-01', '2026-08-31', 1, 'active');

INSERT INTO terms (session_id, term_name, start_date, end_date, is_current) VALUES
(1, 'First Term', '2025-09-15', '2025-12-19', 1),
(1, 'Second Term', '2026-01-06', '2026-04-10', 0),
(1, 'Third Term', '2026-04-27', '2026-08-14', 0);

INSERT INTO departments (name, code, description) VALUES
('Science', 'SCI', 'Science Department'),
('Arts', 'ART', 'Arts and Humanities Department'),
('Commercial', 'COM', 'Commercial Studies Department');

INSERT INTO admission_forms (form_name, description, price, academic_session_id, is_active) VALUES
('2025/2026 Admission Form', 'Application for admission into JSS1 - SS3 for 2025/2026 session', 4000.00, 1, 1);
