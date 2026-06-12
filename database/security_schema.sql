-- ============================================================
-- Security, Subscription & PIN System Schema
-- Peculiar International College SMS
-- ============================================================

-- 1. LOGIN ATTEMPTS (Brute Force Protection)
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    username VARCHAR(255) NOT NULL,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    success TINYINT(1) DEFAULT 0,
    INDEX idx_ip (ip_address),
    INDEX idx_username (username),
    INDEX idx_attempted_at (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. AUDIT LOGS (already exists but ensure proper schema)
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(50),
    record_id INT,
    old_value TEXT,
    new_value TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_created (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. SUBSCRIPTION PLANS
CREATE TABLE IF NOT EXISTS subscription_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    price_monthly DECIMAL(10,2) DEFAULT 0.00,
    price_yearly DECIMAL(10,2) DEFAULT 0.00,
    max_students INT DEFAULT 0,
    max_teachers INT DEFAULT 0,
    max_admins INT DEFAULT 1,
    features JSON,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. SUBSCRIPTIONS
CREATE TABLE IF NOT EXISTS subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    school_name VARCHAR(255) NOT NULL,
    school_email VARCHAR(255),
    plan_id INT NOT NULL,
    billing_cycle ENUM('monthly','yearly') NOT NULL DEFAULT 'monthly',
    amount DECIMAL(10,2) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('active','expired','cancelled','trial') NOT NULL DEFAULT 'trial',
    payment_method VARCHAR(50),
    payment_reference VARCHAR(100),
    auto_renew TINYINT(1) DEFAULT 1,
    trial_ends DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (plan_id) REFERENCES subscription_plans(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. PAYMENTS / INVOICES
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subscription_id INT NOT NULL,
    invoice_no VARCHAR(50) UNIQUE NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('card','bank_transfer','online','cash','other') NOT NULL DEFAULT 'online',
    payment_status ENUM('pending','completed','failed','refunded') NOT NULL DEFAULT 'pending',
    transaction_reference VARCHAR(255),
    paid_at DATETIME,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. STUDENT PINS
CREATE TABLE IF NOT EXISTS student_pins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    pin VARCHAR(20) NOT NULL,
    status ENUM('active','used','expired','revoked') NOT NULL DEFAULT 'active',
    generated_by INT NOT NULL,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    used_at DATETIME,
    expires_at DATE,
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 5,
    INDEX idx_pin (pin),
    INDEX idx_student (student_id),
    INDEX idx_status (status),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. PIN LOGIN LOG
CREATE TABLE IF NOT EXISTS pin_login_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT,
    pin_id INT,
    ip_address VARCHAR(45),
    success TINYINT(1) DEFAULT 0,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE SET NULL,
    FOREIGN KEY (pin_id) REFERENCES student_pins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. INSERT DEFAULT SUBSCRIPTION PLANS
INSERT IGNORE INTO subscription_plans (name, code, description, price_monthly, price_yearly, max_students, max_teachers, features) VALUES
('Free Trial', 'trial', 'Try the system with basic features for 30 days', 0.00, 0.00, 50, 10, '["Up to 50 students","Up to 10 teachers","Basic reports","Email support"]'),
('Basic', 'basic', 'Essential features for small schools', 29.99, 299.99, 200, 20, '["Up to 200 students","Up to 20 teachers","Attendance tracking","Grade management","Email support"]'),
('Standard', 'standard', 'Advanced features for growing schools', 79.99, 799.99, 500, 50, '["Up to 500 students","Up to 50 teachers","All Basic features","CBT examinations","Lesson plans","Phone support"]'),
('Premium', 'premium', 'Complete solution for large schools', 149.99, 1499.99, 2000, 200, '["Up to 2000 students","Up to 200 teachers","All Standard features","PIN login system","API access","Priority support"]'),
('Enterprise', 'enterprise', 'Custom solution for school groups', 499.99, 4999.99, 10000, 1000, '["Unlimited students","Unlimited teachers","All Premium features","Multi-campus support","White-label","Dedicated account manager","Custom integrations"]');
