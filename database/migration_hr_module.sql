-- Staff Management (HR Module) tables
-- Run this on the production database

CREATE TABLE IF NOT EXISTS staff_leave (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    leave_type ENUM('annual','sick','personal','maternity','paternity','study','other') NOT NULL DEFAULT 'annual',
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('pending','approved','rejected','cancelled') DEFAULT 'pending',
    approved_by INT,
    approved_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS staff_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    doc_name VARCHAR(200) NOT NULL,
    doc_type VARCHAR(50) DEFAULT 'other',
    file_path VARCHAR(255) NOT NULL,
    notes TEXT,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_staff_leave_user ON staff_leave(user_id, status);
CREATE INDEX idx_staff_leave_dates ON staff_leave(start_date, end_date);
CREATE INDEX idx_staff_docs_user ON staff_documents(user_id);
