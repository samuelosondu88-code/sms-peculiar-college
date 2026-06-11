# School Management System — Peculiar International College

A full-featured, web-based school management system built with PHP, MySQL, HTML, CSS, and JavaScript.

## Features

- **6 User Portals:** Admin, Teacher, Student, Parent, Accountant, and Public/Applicant
- **Academic Management:** Classes, subjects, timetable, attendance, exams, results
- **Student Management:** Admissions, enrollment, profiles, behavior records
- **Finance:** Fee structure, payments (card + bank transfer), expenses, payroll
- **Communication:** Internal messaging, notices/announcements
- **Library:** Book catalog, borrowing management
- **Transport:** Route management, student assignments
- **Hostel:** Dormitory allocation, room management
- **Online Admission:** Public form purchase (₦4,000), application tracking, admission letter
- **Security:** bcrypt hashing, PDO prepared statements, CSRF, RBAC, rate limiting, audit logs

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Frontend | HTML5, CSS3, JavaScript, Bootstrap 5 |
| Backend | PHP 8.x (Core PHP, no heavy framework) |
| Database | MySQL / MariaDB |
| Icons | Font Awesome |
| Charts | Chart.js |

## Requirements

- PHP 8.0 or higher
- MySQL 5.7 or higher
- Apache with mod_rewrite (or Nginx)
- SSL certificate (for production)

## Installation

1. Clone or download the project into your web root (e.g., `htdocs` for XAMPP)
2. Import `database/schema.sql` into your MySQL database
3. Import `database/seed.sql` for initial data
4. Configure database credentials in `config/database.php`
5. Update school information in `config/app.php`
6. Access the application via browser

## Default Login (after seeding)

All seed accounts use password: `Password@123`

## Project Structure

```
sms-peculiar-college/
├── admin/          # Admin portal
├── accountant/     # Accountant portal
├── api/            # API endpoints
├── assets/         # CSS, JS, images, vendors
├── auth/           # Login, logout, profile, password
├── config/         # Database, app config, session
├── database/       # Schema, seed data, migrations
├── documents/      # Uploaded files
├── docs/           # Documentation
├── includes/       # Header, footer, sidebar, functions
├── parent/         # Parent portal
├── public/         # Public pages (landing, admission)
├── student/        # Student portal
├── teacher/        # Teacher portal
└── index.php       # Entry point
```

## License

Proprietary — Peculiar International College
