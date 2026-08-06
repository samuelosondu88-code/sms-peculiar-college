<?php
require_once __DIR__ . '/env.php';

@ini_set('display_errors', env('PHP_DISPLAY_ERRORS') ?: '0');
$errorReporting = env('PHP_ERROR_REPORTING') ?: '';
$errorReporting = is_numeric($errorReporting) ? (int)$errorReporting : (defined($errorReporting) ? constant($errorReporting) : E_ALL);
error_reporting($errorReporting);

$appUrl = env('APP_URL') ?: 'https://peculiar-college.example.com';
$schoolName = env('SCHOOL_NAME') ?: 'Peculiar International College';
$schoolPhone = env('SCHOOL_PHONE') ?: '+234-XXX-XXX-XXXX';
$schoolEmail = env('SCHOOL_EMAIL') ?: 'info@peculiarcollege.edu.ng';
$baseUrl = env('BASE_URL') ?: '/sms-peculiar-college';

!defined('APP_ENV') && define('APP_ENV', env('APP_ENV') ?: 'production');
!defined('APP_DEBUG') && define('APP_DEBUG', (bool)(env('APP_DEBUG') ?: false));
!defined('APP_NAME') && define('APP_NAME', env('APP_NAME') ?: 'Peculiar International College - School Management System');
!defined('APP_SHORT_NAME') && define('APP_SHORT_NAME', 'PIC SMS');
!defined('APP_VERSION') && define('APP_VERSION', '2.0.0');
!defined('APP_URL') && define('APP_URL', $appUrl);

$appKey = env('APP_KEY') ?: '';
if (empty($appKey)) {
    error_log('APP_KEY not set in .env. Run: php -r "echo bin2hex(random_bytes(32));" and set APP_KEY.');
} elseif (strlen($appKey) !== 64 || !ctype_xdigit($appKey)) {
    error_log('APP_KEY must be a 64-character hex string. Generate with: php -r "echo bin2hex(random_bytes(32));"');
    $appKey = '';
}
!defined('APP_KEY') && define('APP_KEY', $appKey);

!defined('SCHOOL_NAME') && define('SCHOOL_NAME', $schoolName);
!defined('SCHOOL_ADDRESS') && define('SCHOOL_ADDRESS', env('SCHOOL_ADDRESS') ?: 'After Technical College Bukuru, Trade Centre Kuru, Plateau State');
!defined('SCHOOL_PHONE') && define('SCHOOL_PHONE', $schoolPhone);
!defined('SCHOOL_EMAIL') && define('SCHOOL_EMAIL', $schoolEmail);
!defined('SCHOOL_MOTTO') && define('SCHOOL_MOTTO', 'Excellence in Education, Character in Life');
!defined('SCHOOL_VISION') && define('SCHOOL_VISION', 'To be a leading institution of academic excellence, producing well-rounded graduates with strong moral character, critical thinking skills, and a global perspective who will positively impact their communities and the world.');
!defined('SCHOOL_MISSION') && define('SCHOOL_MISSION', 'To provide a holistic, student-centered education that fosters academic excellence, character development, and lifelong learning through innovative teaching methods, modern facilities, and a supportive environment that nurtures each student\'s unique potential.');
!defined('SCHOOL_VALUES') && define('SCHOOL_VALUES', 'Integrity, Excellence, Discipline, Innovation, Respect, Responsibility');
!defined('TIMEZONE') && define('TIMEZONE', 'Africa/Lagos');
!defined('BASE_URL') && define('BASE_URL', $baseUrl);
!defined('UPLOAD_MAX_SIZE') && define('UPLOAD_MAX_SIZE', (int)(env('UPLOAD_MAX_SIZE') ?: 2 * 1024 * 1024));
!defined('ALLOWED_EXTENSIONS') && define('ALLOWED_EXTENSIONS', ['jpg','jpeg','png','pdf','doc','docx','xls','xlsx']);
!defined('PAGINATION_LIMIT') && define('PAGINATION_LIMIT', 20);
!defined('ADMISSION_FORM_PRICE') && define('ADMISSION_FORM_PRICE', 4000.00);
!defined('PAYSTACK_PUBLIC_KEY') && define('PAYSTACK_PUBLIC_KEY', env('PAYSTACK_PUBLIC_KEY') ?: '');
!defined('PAYSTACK_SECRET_KEY') && define('PAYSTACK_SECRET_KEY', env('PAYSTACK_SECRET_KEY') ?: '');

// AI Teaching Assistant provider configuration.
// AI_PROVIDER: 'template' (default, offline) | 'openai' | 'anthropic' | 'gemini'
!defined('AI_PROVIDER') && define('AI_PROVIDER', env('AI_PROVIDER') ?: 'template');
!defined('AI_TIMEOUT') && define('AI_TIMEOUT', (int)(env('AI_TIMEOUT') ?: 60));
!defined('OPENAI_API_KEY') && define('OPENAI_API_KEY', env('OPENAI_API_KEY') ?: '');
!defined('OPENAI_MODEL') && define('OPENAI_MODEL', env('OPENAI_MODEL') ?: 'gpt-4o-mini');
!defined('ANTHROPIC_API_KEY') && define('ANTHROPIC_API_KEY', env('ANTHROPIC_API_KEY') ?: '');
!defined('ANTHROPIC_MODEL') && define('ANTHROPIC_MODEL', env('ANTHROPIC_MODEL') ?: 'claude-3-5-sonnet-20241022');
!defined('GEMINI_API_KEY') && define('GEMINI_API_KEY', env('GEMINI_API_KEY') ?: '');
!defined('GEMINI_MODEL') && define('GEMINI_MODEL', env('GEMINI_MODEL') ?: 'gemini-1.5-pro');

date_default_timezone_set(TIMEZONE);
