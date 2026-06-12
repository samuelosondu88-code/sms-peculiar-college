# Peculiar International College - School Management System

A comprehensive, enterprise-grade School Management System built with PHP, MySQL, Bootstrap 5, and modern security standards.

## Features

### Core Modules
- **Student Management** - Admission, records, class assignments, PIN-based authentication
- **Teacher Management** - Subject allocation, class assignments, lesson plans
- **Class & Subject Management** - Multi-class, multi-section, multi-subject support
- **Attendance Tracking** - Daily attendance with reports
- **Grade Management** - CA, exam scores, termly GPA computation
- **Timetable Management** - Class and exam scheduling
- **Fee Management** - Payment tracking, invoices, receipts
- **Library Management** - Book inventory, borrowing system
- **Transport Management** - Route and vehicle tracking
- **Hostel Management** - Dormitory assignments
- **Application Portal** - Online admission applications
- **Messaging System** - Internal communication
- **Notice Board** - Announcements and circulars

### Examination Systems
- **CBT (Computer-Based Testing)** - WAEC/NECO/JAMB-style MCQ exams with timed sessions, auto-grading, analytics
- **Teacher Examination Module** - Custom exams with 5 question types (MCQ, True/False, Fill-in-blank, Short Answer, Essay), manual grading, results analytics
- **Lesson Plan Management** - Structured lesson planning with AI template generator, review workflow

### Security Features
- CSRF protection on all forms
- Session fingerprinting and timeout
- Brute force protection with rate limiting
- Audit logging for all actions
- SQL injection prevention (PDO prepared statements)
- XSS prevention (output escaping)
- Content Security Policy headers
- Input validation and sanitization
- Secure file upload validation
- Password hashing with bcrypt (cost 12)
- Role-Based Access Control (RBAC)

### Subscription System
- 5 subscription plans (Free Trial to Enterprise)
- Monthly/yearly billing
- Invoice generation
- Payment tracking
- Auto-renewal support

### Student PIN System
- Secure PIN generation for students
- Bulk and single PIN generation
- PIN expiry management
- Login attempt tracking
- PIN revocation
- Printable PIN slips
- Separate PIN-based student login

## System Requirements

- **Web Server:** Apache 2.4+ with mod_rewrite
- **PHP:** 8.0+ with PDO MySQL, OpenSSL, mbstring, cURL
- **Database:** MySQL 5.7+ or MariaDB 10.3+
- **Browsers:** Chrome, Firefox, Edge, Safari (latest 2 versions)
- **HTTPS:** Required for production

## Installation

### Quick Install (Development)

1. **Clone the repository:**
   ```bash
   git clone https://github.com/your-org/sms-peculiar-college.git
   cd sms-peculiar-college
   ```

2. **Configure Database:**
   - Create a MySQL database named `sms_peculiar_college`
   - Run the schema files in order:
     ```bash
     mysql -u root -p sms_peculiar_college < database/schema.sql
     mysql -u root -p sms_peculiar_college < database/security_schema.sql
     mysql -u root -p sms_peculiar_college < database/cbt_schema.sql
     mysql -u root -p sms_peculiar_college < database/lesson_plans_schema.sql
     mysql -u root -p sms_peculiar_college < database/teacher_exams_schema.sql
     ```

3. **Configure Application:**
   - Edit `config/app.php` - Set `BASE_URL`, `APP_URL`, school details
   - Edit `config/database.php` - Set database credentials

4. **Seed Data (optional):**
   ```bash
   php database/cbt_seed.php
   ```

5. **Set Permissions:**
   ```bash
   chmod -R 755 .
   chmod -R 777 documents/
   ```

6. **Access the System:**
   - URL: `http://localhost/sms-peculiar-college/`
   - Default admin: `admin@peculiarcollege.edu.ng` / `Password@123`

### Production Installation

Before deploying to production, complete the [Security Checklist](SECURITY.md):

1. **Generate APP_KEY:**
   ```php
   // Run: php -r "echo bin2hex(random_bytes(32));"
   // Set in config/app.php: define('APP_KEY', 'your-generated-key');
   ```

2. **Create Dedicated Database User:**
   ```sql
   CREATE USER 'sms_user'@'localhost' IDENTIFIED BY 'strong-password';
   GRANT ALL PRIVILEGES ON sms_peculiar_college.* TO 'sms_user'@'localhost';
   FLUSH PRIVILEGES;
   ```

3. **Enable HTTPS** - Configure SSL certificate
4. **Disable PHP display_errors** in `.htaccess`
5. **Configure automated backups**
6. **Remove install directory** if present

## Default Login Credentials

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@peculiarcollege.edu.ng | Password@123 |
| Teacher | teacher@peculiarcollege.edu.ng | Password@123 |
| Student | student@peculiarcollege.edu.ng | Password@123 |
| Parent | parent@peculiarcollege.edu.ng | Password@123 |
| Accountant | accountant@peculiarcollege.edu.ng | Password@123 |

**Note:** Change all default passwords immediately after installation.

## Project Structure

```
├── admin/           # Admin portal
├── assets/          # CSS, JS, images
├── auth/            # Authentication pages
├── config/          # Configuration files
├── database/        # SQL schemas and seeds
├── documents/       # Uploaded files
├── includes/        # Shared functions and templates
├── parent/          # Parent portal
├── public/          # Public-facing pages
├── student/         # Student portal
├── teacher/         # Teacher portal
├── accountant/      # Accountant portal
├── .htaccess        # Apache security rules
├── index.php        # Entry point
└── README.md
```

## Security Rating

The system undergoes continuous security scanning. Run the Security Dashboard at `/admin/security/` to view the current rating and address any issues.

## Deployment

### Free Hosting (Not Recommended)
- Limited to small deployments (< 50 users)
- Security constraints (no SSL control, shared environment)
- Performance limitations
- **Not suitable for production**

### Recommended Hosting
- **Shared Hosting:** cPanel with PHP 8.0+ and MySQL
  - Hostinger, SiteGround, Namecheap
  - Suitable for: Small schools (up to 500 users)
- **VPS:** DigitalOcean, Linode, Vultr
  - Suitable for: Medium schools (up to 2000 users)
  - Estimated: $10-40/month
- **Cloud:** AWS, Google Cloud, Azure
  - Suitable for: Large institutions (2000+ users)
  - Estimated: $50-200+/month

### Resource Estimates (per 1000 users)
- **RAM:** 2-4 GB
- **Storage:** 10-20 GB
- **CPU:** 2-4 cores
- **Bandwidth:** 50-100 GB/month

## License

Proprietary - Peculiar International College. All rights reserved.

## Support

- Email: info@peculiarcollege.edu.ng
- Phone: +234-XXX-XXX-XXXX
