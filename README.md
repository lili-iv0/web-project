# Fitness Tracker – fixed version

## Setup
1. Copy this folder into `htdocs` (XAMPP) and start Apache + MySQL.
2. Import `database.sql` in phpMyAdmin (it creates only what is missing, so it is safe on your existing `users` DB).
3. Open `http://localhost/project_web3/` (or `login.php`).
4. DB credentials live in `connection.php` (or env vars DB_HOST / DB_USER / DB_PASS / DB_NAME).

## Starter admin (teacher) account – created by database.sql
- Username: `admin`
- Password: `Admin@12345`  → change it after first login.

Your ORIGINAL admin account is stored in the `teachers` table of your own database (it is not in the code).
Old plain-text passwords still work and are upgraded to hashes automatically on the next login.

## What changed in this version
- **Edit profile:** click the avatar in the sidebar (or the avatar in the top bar on mobile) – works for students and teachers.
  Students can change first/last name, username, age, section, photo and password; teachers can change display name, username, photo and password.
  Handled by `save-profile.php` (own account only, password change requires the current password).
- **Consistent maths:** every page now treats the *first* entry as the pre-test and the *latest* as the post-test
  (`student_entries()` / `split_entries()` in `helpers.php`), so the student dashboard, teacher modal and class summary agree.
- **Class summary:** participation, current class averages, first-vs-latest averages (skills and BMI/VO2 on separate charts),
  BMI category and VO2 level distributions, VO2 change by student, and a per-student table.
- **Student dashboard:** KPI strip with change since the first entry, BMI chart with a normal-range band, VO2 trend, per-skill trend.
- **UI:** new flat design system in `app.css` (single green accent, hairline borders, no gradients), light + dark mode.
- No database changes are needed.
